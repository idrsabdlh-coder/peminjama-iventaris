<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemAdminTest extends TestCase
{
    use RefreshDatabase;

    private int $kategoriId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId(['nama' => 'Laptop', 'aktif' => true]);
    }

    private function masuk(): User
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        return $admin;
    }

    private function barang(int $total = 5, int $tersedia = 5, string $nama = 'Laptop Dell'): int
    {
        return DB::table('items')->insertGetId([
            'nama' => $nama,
            'kategori_id' => $this->kategoriId,
            'total' => $total,
            'tersedia' => $tersedia,
            'rusak' => 0,
        ]);
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/admin/items')->assertUnauthorized();
        $this->postJson('/api/admin/items', [])->assertUnauthorized();
        $this->deleteJson('/api/admin/items/1')->assertUnauthorized();
    }

    public function test_daftar_dan_detail_barang(): void
    {
        $this->masuk();
        $id = $this->barang(5, 3);

        $this->getJson('/api/admin/items')
            ->assertOk()
            ->assertJsonPath('data.0.nama', 'Laptop Dell')
            ->assertJsonPath('data.0.di_luar', 2);

        $this->getJson("/api/admin/items/{$id}")
            ->assertOk()
            ->assertJsonPath('data.tersedia', 3);

        $this->getJson('/api/admin/items/9999')->assertNotFound();
    }

    public function test_tambah_barang_mencatat_stok_dan_audit(): void
    {
        $admin = $this->masuk();

        $res = $this->postJson('/api/admin/items', [
            'nama' => '  Proyektor ',
            'kategori_id' => $this->kategoriId,
            'total' => 4,
        ])->assertCreated()
            ->assertJsonPath('data.nama', 'Proyektor')
            ->assertJsonPath('data.tersedia', 4);

        $id = $res->json('data.id');

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $id,
            'jenis' => 'TAMBAH_STOK',
            'delta_total' => 4,
            'delta_tersedia' => 4,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'item_tambah', 'entitas' => 'item', 'entitas_id' => $id]);
    }

    public function test_nama_kembar_dan_kategori_salah_ditolak(): void
    {
        $this->masuk();
        $this->barang(1, 1, 'Proyektor');

        $this->postJson('/api/admin/items', [
            'nama' => 'Proyektor',
            'kategori_id' => $this->kategoriId,
            'total' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('nama');

        $this->postJson('/api/admin/items', [
            'nama' => 'Kamera',
            'kategori_id' => 9999,
            'total' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('kategori_id');
    }

    public function test_ubah_total_menggeser_tersedia(): void
    {
        $this->masuk();
        $id = $this->barang(5, 3);

        $this->patchJson("/api/admin/items/{$id}", ['total' => 8])
            ->assertOk()
            ->assertJsonPath('data.total', 8)
            ->assertJsonPath('data.tersedia', 6);

        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $id, 'jenis' => 'TAMBAH_STOK', 'delta_total' => 3, 'delta_tersedia' => 3,
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'item_ubah', 'entitas_id' => $id]);
    }

    public function test_total_tidak_boleh_di_bawah_unit_yang_dipinjam(): void
    {
        $this->masuk();
        $id = $this->barang(5, 3); // 2 unit di luar

        $this->patchJson("/api/admin/items/{$id}", ['total' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('total');

        $this->assertDatabaseHas('items', ['id' => $id, 'total' => 5, 'tersedia' => 3]);
    }

    public function test_ubah_nama_sendiri_tidak_dianggap_kembar(): void
    {
        $this->masuk();
        $id = $this->barang();

        $this->patchJson("/api/admin/items/{$id}", ['nama' => 'Laptop Dell', 'deskripsi' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('data.deskripsi', 'Baru');
    }

    public function test_hapus_barang_tersedia_berhasil_dan_nama_bisa_dipakai_lagi(): void
    {
        $this->masuk();
        $id = $this->barang();

        $this->deleteJson("/api/admin/items/{$id}")->assertNoContent();

        $this->assertNotNull(DB::table('items')->where('id', $id)->value('dihapus_pada'));
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'item_hapus', 'entitas_id' => $id]);

        $this->postJson('/api/admin/items', [
            'nama' => 'Laptop Dell',
            'kategori_id' => $this->kategoriId,
            'total' => 1,
        ])->assertCreated();
    }

    public function test_barang_yang_masih_dipinjam_tidak_bisa_dihapus(): void
    {
        $this->masuk();
        $id = $this->barang(5, 3);

        $this->deleteJson("/api/admin/items/{$id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barang');

        $this->assertNull(DB::table('items')->where('id', $id)->value('dihapus_pada'));
    }
}