<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataAdminTest extends TestCase
{
    use RefreshDatabase;

    public static function jenis(): array
    {
        return [
            'kategori' => ['categories', 'kategori'],
            'divisi' => ['divisions', 'divisi'],
        ];
    }

    private function masuk(): User
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        return $admin;
    }

    private function buat(string $u, string $nama = 'Master Uji'): int
    {
        return DB::table($u)->insertGetId(['nama' => $nama, 'aktif' => true]);
    }

    private function pakai(string $u, int $id): void
    {
        if ($u === 'categories') {
            DB::table('items')->insert([
                'nama' => 'Barang Uji', 'kategori_id' => $id,
                'total' => 1, 'tersedia' => 1, 'rusak' => 0,
            ]);

            return;
        }

        DB::table('loans')->insert([
            'kode_pinjam' => 'PJM-ABC234',
            'nama_peminjam' => 'Budi Santoso',
            'division_id' => $id,
            'tipe_peminjam' => 'karyawan',
            'telepon' => '081234567890',
            'tanggal_pinjam' => now('Asia/Jakarta')->addDays(2)->toDateString(),
            'batas_kembali_awal' => now('Asia/Jakarta')->addDays(5)->toDateString(),
            'batas_kembali' => now('Asia/Jakarta')->addDays(5)->toDateString(),
        ]);
    }

    #[DataProvider('jenis')]
    public function test_tanpa_login_ditolak(string $u, string $aksi): void
    {
        $this->getJson("/api/admin/{$u}")->assertUnauthorized();
        $this->postJson("/api/admin/{$u}", [])->assertUnauthorized();
        $this->deleteJson("/api/admin/{$u}/1")->assertUnauthorized();
    }

    #[DataProvider('jenis')]
    public function test_tambah_dan_nama_kembar_ditolak(string $u, string $aksi): void
    {
        $this->masuk();

        $this->postJson("/api/admin/{$u}", ['nama' => '  Baru '])
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Baru')
            ->assertJsonPath('data.aktif', true)
            ->assertJsonPath('data.dipakai', 0);

        $this->assertDatabaseHas('audit_logs', ['aksi' => "{$aksi}_tambah"]);

        $this->postJson("/api/admin/{$u}", ['nama' => 'Baru'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nama');
    }

    #[DataProvider('jenis')]
    public function test_ubah_nama_dan_nonaktifkan(string $u, string $aksi): void
    {
        $this->masuk();
        $id = $this->buat($u);

        // Nama sendiri tidak dianggap kembar.
        $this->patchJson("/api/admin/{$u}/{$id}", ['nama' => 'Master Uji', 'aktif' => false])
            ->assertOk()
            ->assertJsonPath('data.aktif', false);

        $this->patchJson("/api/admin/{$u}/{$id}", ['nama' => 'Nama Baru'])
            ->assertOk()
            ->assertJsonPath('data.nama', 'Nama Baru');

        $this->assertDatabaseHas('audit_logs', ['aksi' => "{$aksi}_ubah", 'entitas_id' => $id]);
    }

    #[DataProvider('jenis')]
    public function test_daftar_menampilkan_jumlah_pemakai_dan_yang_nonaktif(string $u, string $aksi): void
    {
        $this->masuk();
        $id = $this->buat($u, 'Dipakai');
        $nonaktif = $this->buat($u, 'Nonaktif');
        DB::table($u)->where('id', $nonaktif)->update(['aktif' => false]);
        $this->pakai($u, $id);

        $res = $this->getJson("/api/admin/{$u}")->assertOk();

        $this->assertCount(2, $res->json('data'));
        $this->assertSame(1, collect($res->json('data'))->firstWhere('nama', 'Dipakai')['dipakai']);
        $this->assertFalse(collect($res->json('data'))->firstWhere('nama', 'Nonaktif')['aktif']);
    }

    #[DataProvider('jenis')]
    public function test_hapus_yang_belum_dipakai_berhasil(string $u, string $aksi): void
    {
        $this->masuk();
        $id = $this->buat($u);

        $this->deleteJson("/api/admin/{$u}/{$id}")->assertNoContent();

        $this->assertDatabaseMissing($u, ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => "{$aksi}_hapus", 'entitas_id' => $id]);
    }

    #[DataProvider('jenis')]
    public function test_hapus_yang_sudah_dipakai_ditolak(string $u, string $aksi): void
    {
        $this->masuk();
        $id = $this->buat($u);
        $this->pakai($u, $id);

        $this->deleteJson("/api/admin/{$u}/{$id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($aksi);

        $this->assertDatabaseHas($u, ['id' => $id]);
    }

    #[DataProvider('jenis')]
    public function test_id_tidak_ada_mendapat_404(string $u, string $aksi): void
    {
        $this->masuk();

        $this->getJson("/api/admin/{$u}/9999")->assertNotFound();
        $this->patchJson("/api/admin/{$u}/9999", ['nama' => 'X'])->assertNotFound();
        $this->deleteJson("/api/admin/{$u}/9999")->assertNotFound();
    }
}