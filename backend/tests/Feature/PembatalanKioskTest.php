<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembatalanKioskTest extends TestCase
{
    use RefreshDatabase;

    private int $divisiId;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->divisiId = DB::table('divisions')->insertGetId(['nama' => 'Keuangan', 'aktif' => true]);
        $kategoriId = DB::table('categories')->insertGetId(['nama' => 'Laptop', 'aktif' => true]);
        $this->itemId = DB::table('items')->insertGetId([
            'nama' => 'Laptop Dell',
            'kategori_id' => $kategoriId,
            'total' => 5,
            'tersedia' => 3, // 2 unit sedang dipinjam oleh data uji
            'rusak' => 0,
        ]);
    }

    private function hari(int $offset = 0): string
    {
        return now('Asia/Jakarta')->addDays($offset)->toDateString();
    }

    private function pinjaman(int $mulai, array $ganti = []): string
    {
        $data = array_merge([
            'kode_pinjam' => 'PJM-ABC234',
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $this->divisiId,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => $this->hari($mulai),
            'batas_kembali_awal' => $this->hari($mulai + 3),
            'batas_kembali' => $this->hari($mulai + 3),
        ], $ganti);

        $loanId = DB::table('loans')->insertGetId($data);
        DB::table('loan_items')->insert([
            'loan_id' => $loanId,
            'item_id' => $this->itemId,
            'qty_dipinjam' => 2,
        ]);

        return $data['kode_pinjam'];
    }

    private function batal(string $kode, ?string $alasan = null)
    {
        return $this->postJson('/api/kiosk/peminjaman/batal', array_filter([
            'kode_pinjam' => $kode,
            'alasan' => $alasan,
        ]));
    }

    private function tersedia(): int
    {
        return (int) DB::table('items')->where('id', $this->itemId)->value('tersedia');
    }

    public function test_pemesanan_berhasil_dibatalkan_dan_stok_kembali(): void
    {
        $kode = $this->pinjaman(2);

        $this->batal($kode, 'Salah tanggal')
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan');

        $this->assertSame(5, $this->tersedia());
        $this->assertDatabaseHas('loans', ['kode_pinjam' => $kode, 'alasan_batal' => 'Salah tanggal']);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $this->itemId,
            'jenis' => 'BATAL_PINJAM',
            'delta_tersedia' => 2,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'batal_pinjam_kiosk']);
    }

    public function test_alasan_kosong_memakai_teks_bawaan(): void
    {
        $kode = $this->pinjaman(2);

        $this->batal($kode)->assertOk();

        $this->assertDatabaseHas('loans', ['kode_pinjam' => $kode, 'alasan_batal' => 'Dibatalkan oleh peminjam']);
    }

    public function test_pembatalan_ulang_tidak_menggandakan_stok(): void
    {
        $kode = $this->pinjaman(2);

        $this->batal($kode)->assertOk();
        $this->batal($kode)->assertOk()->assertJsonPath('data.status', 'dibatalkan');

        $this->assertSame(5, $this->tersedia());
        $this->assertSame(1, DB::table('stock_movements')->where('jenis', 'BATAL_PINJAM')->count());
    }

    public function test_peminjaman_berjalan_tidak_bisa_dibatalkan(): void
    {
        $kode = $this->pinjaman(-1);

        $this->batal($kode)->assertUnprocessable()->assertJsonValidationErrors('kode_pinjam');

        $this->assertSame(3, $this->tersedia());
        $this->assertDatabaseHas('loans', ['kode_pinjam' => $kode, 'dibatalkan_pada' => null]);
    }

    public function test_peminjaman_yang_mulai_hari_ini_tidak_bisa_dibatalkan(): void
    {
        $kode = $this->pinjaman(0);

        $this->batal($kode)->assertUnprocessable();

        $this->assertSame(3, $this->tersedia());
    }

    public function test_kode_tidak_ada_ditolak(): void
    {
        $this->batal('PJM-ZZZ999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Kode pinjam tidak ditemukan. Periksa kembali kode di struk.');
    }

    public function test_format_kode_salah_mendapat_pesan_yang_sama(): void
    {
        $this->batal('ngawur')
            ->assertNotFound()
            ->assertJsonPath('message', 'Kode pinjam tidak ditemukan. Periksa kembali kode di struk.');
    }

    public function test_kode_huruf_kecil_dan_spasi_tetap_dikenali(): void
    {
        $this->pinjaman(2);

        $this->batal('  pjm-abc234 ')->assertOk()->assertJsonPath('data.status', 'dibatalkan');
    }

    public function test_barang_yang_sudah_dihapus_tetap_mendapat_stok_kembali(): void
    {
        $kode = $this->pinjaman(2);
        DB::table('items')->where('id', $this->itemId)->update(['dihapus_pada' => now()]);

        $this->batal($kode)->assertOk();

        $this->assertSame(5, $this->tersedia());
    }

    public function test_terkunci_setelah_lima_kali_gagal(): void
    {
        $kode = $this->pinjaman(2);

        for ($i = 0; $i < 5; $i++) {
            $this->batal('PJM-ZZZ999')->assertNotFound();
        }

        // Kode yang benar pun ditolak selama terkunci, dan tidak ada yang dibatalkan.
        $this->batal($kode)->assertStatus(429);
        $this->assertSame(3, $this->tersedia());
    }
}