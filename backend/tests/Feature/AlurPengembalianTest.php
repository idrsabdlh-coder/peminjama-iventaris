<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlurPengembalianTest extends TestCase
{
    use RefreshDatabase;

    private int $kategoriId;
    private int $divisiId;
    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategoriId = DB::table('categories')->insertGetId(['nama' => 'Elektronik']); // SESUAIKAN kolom wajib
        $this->divisiId   = DB::table('divisions')->insertGetId(['nama' => 'IT']);          // SESUAIKAN kolom wajib

        $this->itemId = DB::table('items')->insertGetId([
            'nama' => 'Laptop', 'kategori_id' => $this->kategoriId,
            'total' => 10, 'tersedia' => 10, 'kondisi' => 0, 'boleh_diperpanjang' => true,
        ]);
    }

    private function buatAdmin(): User
    {
        // SESUAIKAN: samakan dengan cara MasterDataAdminTest membuat admin
        return User::factory()->create();
    }

    /** Pinjam langsung lewat DB: 4 unit laptop, stok tersedia jadi 6. */
    private function buatPinjaman(int $qty = 4): array
    {
        $loanId = DB::table('loans')->insertGetId([
            'kode_pinjam' => 'PJM-TEST23', 'nama_peminjam' => 'Budi',
            'division_id' => $this->divisiId, 'tipe_peminjam' => 'karyawan',
            'tanggal_pinjam' => now()->subDays(5)->toDateString(),
            'batas_kembali_awal' => now()->subDays(2)->toDateString(),
            'batas_kembali' => now()->subDays(2)->toDateString(),
        ]);
        $loanItemId = DB::table('loan_items')->insertGetId([
            'loan_id' => $loanId, 'item_id' => $this->itemId, 'qty_dipinjam' => $qty,
        ]);
        DB::table('items')->where('id', $this->itemId)->update(['tersedia' => 10 - $qty]);

        return [$loanId, $loanItemId, 'PJM-TEST23'];
    }

    private function kirimKlaim(string $kode, array $baris, ?string $key = null)
    {
        return $this->postJson('/api/kiosk/pengembalian', array_filter([
            'kode_pinjam' => $kode, 'baris' => $baris, 'idempotency_key' => $key,
        ])); // SESUAIKAN bila kiosk meminta nama/telepon untuk identifikasi
    }

    public function test_alur_penuh_pinjam_klaim_verifikasi_laporan_dan_audit(): void
    {
        [$loanId, $loanItemId, $kode] = $this->buatPinjaman(4);

        // 1. Klaim: 2 baik, 1 rusak, 1 hilang. Stok belum boleh berubah.
        $res = $this->kirimKlaim($kode, [
            ['item_id' => $this->itemId, 'kondisi' => 'baik',   'qty' => 2],
            ['item_id' => $this->itemId, 'kondisi' => 'rusak',  'qty' => 1],
            ['item_id' => $this->itemId, 'kondisi' => 'hilang', 'qty' => 1],
        ])->assertCreated()->assertJsonPath('data.status', 'menunggu');

        $this->assertSame(2, $res->json('data.hari_terlambat'));
        $klaimId = DB::table('return_claims')->value('id');
        $this->assertSame(6, (int) DB::table('items')->where('id', $this->itemId)->value('tersedia'));

        // 2. Admin lihat daftar klaim menunggu
        $admin = $this->buatAdmin();
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/klaim?status=menunggu')
            ->assertOk()->assertJsonCount(1, 'data');

        // 3. Verifikasi: sesuai laporan peminjam
        $baris = DB::table('return_claim_lines')->where('claim_id', $klaimId)->get()->keyBy('kondisi_dilaporkan');

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/klaim/{$klaimId}/verifikasi", [
            'catatan_admin' => 'Cek fisik selesai',
            'baris' => [
                ['id' => $baris['baik']->id,   'qty_baik' => 2, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0],
                ['id' => $baris['rusak']->id,  'qty_baik' => 0, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0],
                ['id' => $baris['hilang']->id, 'qty_baik' => 0, 'qty_rusak' => 0, 'qty_hilang' => 1, 'qty_ditolak' => 0],
            ],
        ])->assertOk();

        // 4. Stok: tersedia 6+2=8, rusak 1, total 10-1=9
        $item = DB::table('items')->where('id', $this->itemId)->first();
        $this->assertSame(8, (int) $item->tersedia);
        $this->assertSame(1, (int) $item->kondisi);
        $this->assertSame(9, (int) $item->total);

        $this->assertSame('diverifikasi', DB::table('return_claims')->where('id', $klaimId)->value('status'));
        $this->assertSame(3, DB::table('stock_movements')->where('ref_tipe', 'klaim')->where('ref_id', $klaimId)->count());

        // 5. Audit log
        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'klaim_verifikasi', 'entitas' => 'klaim', 'entitas_id' => $klaimId, 'user_id' => $admin->id,
        ]);

        // 6. Laporan stok konsisten dengan item
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/laporan/stok')
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 9)
            ->assertJsonPath('data.ringkasan.tersedia', 8)
            ->assertJsonPath('data.ringkasan.rusak', 1)
            ->assertJsonPath('data.ringkasan.di_luar', 0);
    }

    public function test_klaim_melebihi_sisa_ditolak(): void
    {
        [, , $kode] = $this->buatPinjaman(4);

        $this->kirimKlaim($kode, [['item_id' => $this->itemId, 'kondisi' => 'baik', 'qty' => 5]])
            ->assertUnprocessable();

        $this->assertSame(0, DB::table('return_claims')->count());
    }

    public function test_klaim_pada_pinjaman_dibatalkan_ditolak(): void
    {
        [$loanId, , $kode] = $this->buatPinjaman(2);
        DB::table('loans')->where('id', $loanId)->update(['dibatalkan_pada' => now()]);

        $this->kirimKlaim($kode, [['item_id' => $this->itemId, 'kondisi' => 'baik', 'qty' => 1]])
            ->assertUnprocessable();
    }

    public function test_kirim_ulang_dengan_idempotency_key_sama_tidak_menggandakan(): void
    {
        [, , $kode] = $this->buatPinjaman(2);
        $key = '3f6c1b0e-8a52-4d0e-9d3b-1c2f4a5b6c7d';
        $baris = [['item_id' => $this->itemId, 'kondisi' => 'baik', 'qty' => 1]];

        $a = $this->kirimKlaim($kode, $baris, $key)->assertCreated();
        $b = $this->kirimKlaim($kode, $baris, $key)->assertOk();

        $this->assertSame($a->json('data.kode_klaim'), $b->json('data.kode_klaim'));
        $this->assertSame(1, DB::table('return_claims')->count());
    }

    public function test_verifikasi_jumlah_tidak_cocok_ditolak_dan_stok_tetap(): void
    {
        [, , $kode] = $this->buatPinjaman(2);
        $this->kirimKlaim($kode, [['item_id' => $this->itemId, 'kondisi' => 'baik', 'qty' => 2]])->assertCreated();

        $klaimId = DB::table('return_claims')->value('id');
        $lineId  = DB::table('return_claim_lines')->value('id');

        $this->actingAs($this->buatAdmin(), 'sanctum')
            ->postJson("/api/admin/klaim/{$klaimId}/verifikasi", [
                'baris' => [['id' => $lineId, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]],
            ])->assertUnprocessable();

        $this->assertSame(8, (int) DB::table('items')->where('id', $this->itemId)->value('tersedia'));
        $this->assertSame('menunggu', DB::table('return_claims')->where('id', $klaimId)->value('status'));
    }

    public function test_verifikasi_dua_kali_ditolak(): void
    {
        [, , $kode] = $this->buatPinjaman(1);
        $this->kirimKlaim($kode, [['item_id' => $this->itemId, 'kondisi' => 'baik', 'qty' => 1]])->assertCreated();

        $klaimId = DB::table('return_claims')->value('id');
        $lineId  = DB::table('return_claim_lines')->value('id');
        $admin   = $this->buatAdmin();
        $body = ['baris' => [['id' => $lineId, 'qty_baik' => 1, 'qty_rusak' => 0, 'qty_hilang' => 0, 'qty_ditolak' => 0]]];

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/klaim/{$klaimId}/verifikasi", $body)->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/klaim/{$klaimId}/verifikasi", $body)->assertUnprocessable();

        $this->assertSame(10, (int) DB::table('items')->where('id', $this->itemId)->value('tersedia'));
    }

    public function test_rute_admin_butuh_login(): void
    {
        $this->getJson('/api/admin/klaim')->assertUnauthorized();
        $this->getJson('/api/admin/laporan/stok')->assertUnauthorized();
        $this->postJson('/api/admin/klaim/1/verifikasi', [])->assertUnauthorized();
    }
}