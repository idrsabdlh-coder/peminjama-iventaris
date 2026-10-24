<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CekPeminjamanKioskTest extends TestCase
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
            'tersedia' => 5,
            'rusak' => 0,
        ]);
    }

    private function hari(int $offset = 0): string
    {
        return now('Asia/Jakarta')->addDays($offset)->toDateString();
    }

    private function pinjaman(array $ganti = []): string
    {
        $data = array_merge([
            'kode_pinjam' => 'PJM-ABC234',
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $this->divisiId,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => $this->hari(-1),
            'batas_kembali_awal' => $this->hari(3),
            'batas_kembali' => $this->hari(3),
        ], $ganti);

        $loanId = DB::table('loans')->insertGetId($data);
        DB::table('loan_items')->insert([
            'loan_id' => $loanId,
            'item_id' => $this->itemId,
            'qty_dipinjam' => 2,
        ]);

        return $data['kode_pinjam'];
    }

    private function cek(string $kode)
    {
        return $this->postJson('/api/kiosk/peminjaman/cek', ['kode' => $kode]);
    }

    // T-5.16
    public function test_kode_valid_menampilkan_kartu_peminjaman(): void
    {
        $kode = $this->pinjaman();

        $this->cek($kode)
            ->assertOk()
            ->assertJsonPath('data.kode_pinjam', $kode)
            ->assertJsonPath('data.nama_peminjam', 'Budi Santoso')
            ->assertJsonPath('data.divisi', 'Keuangan')
            ->assertJsonPath('data.status', 'dipinjam')
            ->assertJsonPath('data.batas_kembali', $this->hari(3))
            ->assertJsonPath('data.sisa_hari', 3)
            ->assertJsonPath('data.hari_terlambat', 0)
            ->assertJsonPath('data.items.0.nama', 'Laptop Dell')
            ->assertJsonPath('data.items.0.dipinjam', 2);
    }

    public function test_kode_huruf_kecil_dan_spasi_tetap_dikenali(): void
    {
        $this->pinjaman();

        $this->cek('  pjm-abc234 ')->assertOk()->assertJsonPath('data.kode_pinjam', 'PJM-ABC234');
    }

    // BR-10 / T-5.16
    public function test_peminjaman_lewat_batas_kembali_berstatus_terlambat(): void
    {
        $kode = $this->pinjaman([
            'tanggal_pinjam' => $this->hari(-5),
            'batas_kembali_awal' => $this->hari(-2),
            'batas_kembali' => $this->hari(-2),
        ]);

        $this->cek($kode)
            ->assertOk()
            ->assertJsonPath('data.status', 'terlambat')
            ->assertJsonPath('data.hari_terlambat', 2)
            ->assertJsonPath('data.sisa_hari', 0);
    }

    public function test_pemesanan_menampilkan_status_dipesan_dan_boleh_dibatalkan(): void
    {
        $kode = $this->pinjaman([
            'tanggal_pinjam' => $this->hari(2),
            'batas_kembali_awal' => $this->hari(5),
            'batas_kembali' => $this->hari(5),
        ]);

        $this->cek($kode)
            ->assertOk()
            ->assertJsonPath('data.status', 'dipesan')
            ->assertJsonPath('data.pemesanan', true)
            ->assertJsonPath('data.boleh_dibatalkan', true);
    }

    public function test_peminjaman_berjalan_tidak_bisa_dibatalkan_dari_kiosk(): void
    {
        $kode = $this->pinjaman();

        $this->cek($kode)->assertOk()->assertJsonPath('data.boleh_dibatalkan', false);
    }

    public function test_peminjaman_dibatalkan_ditampilkan_sebagai_dibatalkan(): void
    {
        $kode = $this->pinjaman([
            'dibatalkan_pada' => now(),
            'alasan_batal' => 'Tidak diambil',
        ]);

        $this->cek($kode)
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan')
            ->assertJsonPath('data.boleh_dibatalkan', false);
    }

    // T-5.6
    public function test_kode_salah_tidak_menampilkan_data_apa_pun(): void
    {
        $this->pinjaman();

        $res = $this->cek('PJM-ZZZ999')->assertNotFound();

        $res->assertJsonMissingPath('data');
        $this->assertStringNotContainsString('Budi', $res->getContent());
    }

    public function test_format_kode_yang_salah_ditolak_dengan_pesan_sama(): void
    {
        $this->cek('abc')->assertNotFound()->assertJsonMissingPath('data');
    }

    // BR-18
    public function test_telepon_tidak_pernah_tampil(): void
    {
        $kode = $this->pinjaman();

        $res = $this->cek($kode)->assertOk();

        $res->assertJsonMissingPath('data.telepon');
        $this->assertStringNotContainsString('081234567890', $res->getContent());
    }

    // T-5.6: kunci muncul setelah batas gagal
    public function test_terkunci_setelah_lima_kali_gagal(): void
    {
        $kode = $this->pinjaman();

        for ($i = 0; $i < 5; $i++) {
            $this->cek('PJM-ZZZ999')->assertNotFound();
        }

        $this->cek($kode)->assertStatus(429)->assertJsonMissingPath('data');
    }

    public function test_berhasil_mengosongkan_hitungan_gagal(): void
    {
        $kode = $this->pinjaman();

        for ($i = 0; $i < 4; $i++) {
            $this->cek('PJM-ZZZ999')->assertNotFound();
        }
        $this->cek($kode)->assertOk();

        for ($i = 0; $i < 4; $i++) {
            $this->cek('PJM-ZZZ999')->assertNotFound();
        }
        $this->cek($kode)->assertOk();
    }

    public function test_kode_wajib_diisi(): void
    {
        $this->postJson('/api/kiosk/peminjaman/cek', [])->assertUnprocessable()->assertJsonValidationErrors('kode');
    }
}