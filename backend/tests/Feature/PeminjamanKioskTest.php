<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeminjamanKioskTest extends TestCase
{
    use RefreshDatabase;

    private int $divisiId;
    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->divisiId = DB::table('divisions')->insertGetId(['nama' => 'Keuangan', 'aktif' => true]);
        $this->kategoriId = DB::table('categories')->insertGetId(['nama' => 'Laptop', 'aktif' => true]);
    }

    private function barang(string $nama, int $total, int $tersedia, bool $terhapus = false): int
    {
        return DB::table('items')->insertGetId([
            'nama' => $nama,
            'kategori_id' => $this->kategoriId,
            'total' => $total,
            'tersedia' => $tersedia,
            'rusak' => 0,
            'dihapus_pada' => $terhapus ? now() : null,
        ]);
    }

    private function hari(int $offset = 0): string
    {
        return now('Asia/Jakarta')->addDays($offset)->toDateString();
    }

    private function payload(array $items, array $ganti = []): array
    {
        return array_merge([
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $this->divisiId,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => $this->hari(0),
            'batas_kembali' => $this->hari(3),
            'items' => $items,
        ], $ganti);
    }

    private function kirim(array $payload, ?string $kunci = null)
    {
        return $this->postJson('/api/kiosk/peminjaman', $payload, [
            'Idempotency-Key' => $kunci ?? (string) Str::uuid(),
        ]);
    }

    private function tersedia(int $itemId): int
    {
        return (int) DB::table('items')->where('id', $itemId)->value('tersedia');
    }

    // T-5.2
    public function test_peminjaman_berhasil_mengurangi_stok_dan_mencatat_buku_besar(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $res = $this->kirim($this->payload([['item_id' => $id, 'qty' => 2]]))->assertCreated();

        $kode = $res->json('data.kode_pinjam');
        $this->assertMatchesRegularExpression('/^PJM-[A-HJKMNP-Z2-9]{6}$/', $kode);
        $res->assertJsonPath('data.status', 'dipinjam')->assertJsonPath('data.pemesanan', false);

        $this->assertSame(3, $this->tersedia($id));
        $this->assertDatabaseHas('loan_items', ['item_id' => $id, 'qty_dipinjam' => 2]);
        $this->assertDatabaseHas('loans', [
            'kode_pinjam' => $kode,
            'batas_kembali_awal' => $this->hari(3),
            'batas_kembali' => $this->hari(3),
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $id,
            'jenis' => 'PINJAM',
            'delta_tersedia' => -2,
            'ref_tipe' => 'loan',
        ]);
    }

    // T-5.2: ditolak oleh server
    public function test_jumlah_melebihi_tersedia_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 2);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 3]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(2, $this->tersedia($id));
        $this->assertDatabaseCount('loans', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_satu_barang_kurang_membatalkan_seluruh_peminjaman(): void
    {
        $a = $this->barang('Laptop Dell', 5, 5);
        $b = $this->barang('Proyektor', 1, 1);

        $this->kirim($this->payload([
            ['item_id' => $a, 'qty' => 2],
            ['item_id' => $b, 'qty' => 2],
        ]))->assertUnprocessable();

        $this->assertSame(5, $this->tersedia($a));
        $this->assertSame(1, $this->tersedia($b));
        $this->assertDatabaseCount('loans', 0);
    }

    // T-5.11 / BR-20
    public function test_kunci_idempotensi_sama_tidak_membuat_peminjaman_kedua(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);
        $kunci = (string) Str::uuid();
        $payload = $this->payload([['item_id' => $id, 'qty' => 1]]);

        $pertama = $this->kirim($payload, $kunci)->assertCreated();
        $kedua = $this->kirim($payload, $kunci)->assertOk();

        $this->assertSame($pertama->json('data.kode_pinjam'), $kedua->json('data.kode_pinjam'));
        $this->assertDatabaseCount('loans', 1);
        $this->assertSame(4, $this->tersedia($id));
    }

    public function test_tanpa_kunci_idempotensi_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->postJson('/api/kiosk/peminjaman', $this->payload([['item_id' => $id, 'qty' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');
    }

    // BR-22
    public function test_barang_yang_sama_dua_kali_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([
            ['item_id' => $id, 'qty' => 1],
            ['item_id' => $id, 'qty' => 1],
        ]))->assertUnprocessable();

        $this->assertSame(5, $this->tersedia($id));
    }

    public function test_minimal_satu_barang(): void
    {
        $this->kirim($this->payload([]))->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    // BR-30
    public function test_tipe_peminjam_wajib_dan_hanya_karyawan_atau_magang(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);
        $items = [['item_id' => $id, 'qty' => 1]];

        $tanpa = $this->payload($items);
        unset($tanpa['tipe_peminjam']);

        $this->kirim($tanpa)->assertUnprocessable()->assertJsonValidationErrors('tipe_peminjam');
        $this->kirim($this->payload($items, ['tipe_peminjam' => 'tamu']))
            ->assertUnprocessable()->assertJsonValidationErrors('tipe_peminjam');
        $this->kirim($this->payload($items, ['tipe_peminjam' => 'magang']))->assertCreated();

        $this->assertDatabaseHas('loans', ['tipe_peminjam' => 'magang']);
    }

    // BR-27
    public function test_divisi_nonaktif_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);
        $lama = DB::table('divisions')->insertGetId(['nama' => 'Divisi Lama', 'aktif' => false]);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]], ['division_id' => $lama]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('division_id');
    }

    public function test_barang_yang_sudah_dihapus_tidak_bisa_dipinjam(): void
    {
        $id = $this->barang('Laptop Lama', 1, 1, true);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]]))->assertUnprocessable();
        $this->assertDatabaseCount('loans', 0);
    }

    // T-5.24 / D-10
    public function test_pemesanan_menahan_stok_sejak_dibuat(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 2]], [
            'tanggal_pinjam' => $this->hari(2),
            'batas_kembali' => $this->hari(5),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'dipesan')
            ->assertJsonPath('data.pemesanan', true);

        $this->assertSame(3, $this->tersedia($id));
    }

    // BR-23
    public function test_tanggal_pinjam_lampau_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]], [
            'tanggal_pinjam' => $this->hari(-1),
            'batas_kembali' => $this->hari(2),
        ]))->assertUnprocessable()->assertJsonValidationErrors('tanggal_pinjam');

        $this->assertSame(5, $this->tersedia($id));
    }

    public function test_tanggal_pinjam_melebihi_batas_booking_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]], [
            'tanggal_pinjam' => $this->hari(8),
            'batas_kembali' => $this->hari(9),
        ]))->assertUnprocessable()->assertJsonValidationErrors('tanggal_pinjam');
    }

    public function test_batas_kembali_sebelum_tanggal_pinjam_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]], [
            'tanggal_pinjam' => $this->hari(2),
            'batas_kembali' => $this->hari(1),
        ]))->assertUnprocessable()->assertJsonValidationErrors('batas_kembali');
    }

    public function test_batas_kembali_melebihi_durasi_awal_ditolak(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]], [
            'tanggal_pinjam' => $this->hari(0),
            'batas_kembali' => $this->hari(8),
        ]))->assertUnprocessable()->assertJsonValidationErrors('batas_kembali');
    }

    // BR-18
    public function test_telepon_tidak_ditampilkan_di_respons(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);

        $res = $this->kirim($this->payload([['item_id' => $id, 'qty' => 1]]))->assertCreated();

        $res->assertJsonMissingPath('data.telepon');
        $this->assertStringNotContainsString('081234567890', $res->getContent());
    }

    public function test_telepon_opsional_tapi_harus_8_sampai_15_digit(): void
    {
        $id = $this->barang('Laptop Dell', 5, 5);
        $items = [['item_id' => $id, 'qty' => 1]];

        $this->kirim($this->payload($items, ['telepon' => 'abc']))
            ->assertUnprocessable()->assertJsonValidationErrors('telepon');
        $this->kirim($this->payload($items, ['telepon' => null]))->assertCreated();
    }
}