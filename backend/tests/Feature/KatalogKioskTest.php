<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KatalogKioskTest extends TestCase
{
    use RefreshDatabase;

    private function kategori(string $nama, bool $aktif = true): int
    {
        return DB::table('categories')->insertGetId(['nama' => $nama, 'aktif' => $aktif]);
    }

    private function barang(int $kategoriId, string $nama, int $total, int $tersedia, int $rusak = 0, bool $terhapus = false): int
    {
        return DB::table('items')->insertGetId([
            'nama' => $nama,
            'kategori_id' => $kategoriId,
            'total' => $total,
            'tersedia' => $tersedia,
            'rusak' => $rusak,
            'dihapus_pada' => $terhapus ? now() : null,
        ]);
    }

    public function test_katalog_dapat_dibuka_tanpa_login(): void
    {
        $k = $this->kategori('Laptop');
        $this->barang($k, 'Laptop Dell', 3, 3);

        $this->getJson('/api/kiosk/katalog')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Laptop Dell')
            ->assertJsonPath('data.0.tersedia', 3);
    }

    public function test_barang_yang_dihapus_tidak_tampil(): void
    {
        $k = $this->kategori('Laptop');
        $this->barang($k, 'Laptop Lama', 1, 1, 0, true);

        $this->getJson('/api/kiosk/katalog')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_jumlah_rusak_dan_total_tidak_ditampilkan(): void
    {
        $k = $this->kategori('Kabel');
        $this->barang($k, 'Kabel HDMI', 5, 3, 2);

        $item = $this->getJson('/api/kiosk/katalog')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('rusak', $item);
        $this->assertArrayNotHasKey('total', $item);
        $this->assertArrayNotHasKey('menunggu_verifikasi', $item);
    }

    public function test_barang_habis_tampil_tapi_tidak_bisa_dipilih(): void
    {
        $k = $this->kategori('Kabel');
        $this->barang($k, 'Kabel LAN', 2, 0);
        $this->barang($k, 'Kabel VGA', 2, 2);

        $this->getJson('/api/kiosk/katalog')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Kabel LAN')
            ->assertJsonPath('data.0.boleh_dipilih', false)
            ->assertJsonPath('data.1.boleh_dipilih', true);
    }

    public function test_pencarian_berdasarkan_nama(): void
    {
        $k = $this->kategori('Laptop');
        $this->barang($k, 'Laptop Dell', 1, 1);
        $this->barang($k, 'Proyektor Epson', 1, 1);

        $this->getJson('/api/kiosk/katalog?cari=proyek')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Proyektor Epson');
    }

    public function test_karakter_wildcard_pada_pencarian_tidak_dianggap_pola(): void
    {
        $k = $this->kategori('Laptop');
        $this->barang($k, 'Laptop Dell', 1, 1);

        $this->getJson('/api/kiosk/katalog?cari=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filter_kategori(): void
    {
        $a = $this->kategori('Laptop');
        $b = $this->kategori('Kabel');
        $this->barang($a, 'Laptop Dell', 1, 1);
        $this->barang($b, 'Kabel HDMI', 1, 1);

        $this->getJson("/api/kiosk/katalog?kategori_id={$b}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Kabel HDMI');
    }

    public function test_daftar_kategori_hanya_yang_aktif(): void
    {
        $this->kategori('Laptop', true);
        $this->kategori('Lama', false);

        $this->getJson('/api/kiosk/kategori')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Laptop');
    }

    public function test_daftar_divisi_hanya_yang_aktif(): void
    {
        DB::table('divisions')->insert([
            ['nama' => 'Keuangan', 'aktif' => true],
            ['nama' => 'Divisi Lama', 'aktif' => false],
        ]);

        $this->getJson('/api/kiosk/divisi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Keuangan');
    }
}