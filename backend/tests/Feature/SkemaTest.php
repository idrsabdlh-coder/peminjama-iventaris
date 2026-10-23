<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkemaTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $nama, int $total = 10, int $tersedia = 10, int $rusak = 0): int
    {
        $kategori = DB::table('categories')->value('id')
            ?? DB::table('categories')->insertGetId(['nama' => 'Uji']);

        return DB::table('items')->insertGetId([
            'nama' => $nama, 'kategori_id' => $kategori,
            'total' => $total, 'tersedia' => $tersedia, 'rusak' => $rusak,
        ]);
    }

    private function peminjaman(string $kode, string $pinjam, string $batas): int
    {
        $divisi = DB::table('divisions')->value('id')
            ?? DB::table('divisions')->insertGetId(['nama' => 'Div Uji']);

        return DB::table('loans')->insertGetId([
            'kode_pinjam' => $kode, 'nama_peminjam' => 'Budi', 'division_id' => $divisi,
            'tipe_peminjam' => 'karyawan', 'tanggal_pinjam' => $pinjam,
            'batas_kembali_awal' => $batas, 'batas_kembali' => $batas,
        ]);
    }

    public function test_stok_tidak_boleh_negatif_atau_melebihi_total(): void
    {
        foreach ([[-1, 0, 0], [5, -1, 0], [5, 0, -1], [5, 4, 3]] as $i => [$total, $tersedia, $rusak]) {
            try {
                $this->item("Barang {$i}", $total, $tersedia, $rusak);
                $this->fail("CHECK stok tidak menolak [{$total}, {$tersedia}, {$rusak}]");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_nama_barang_unik_hanya_di_antara_barang_aktif(): void
    {
        $id = $this->item('Mouse');

        try {
            $this->item('Mouse');
            $this->fail('Nama barang aktif ganda seharusnya ditolak');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        DB::table('items')->where('id', $id)->update(['dihapus_pada' => now()]);
        $this->item('Mouse'); // setelah soft-delete, nama boleh dipakai lagi (BR-14)

        $this->assertSame(2, DB::table('items')->where('nama', 'Mouse')->count());
    }

    public function test_kategori_yang_dipakai_tidak_bisa_dihapus(): void
    {
        $this->item('Keyboard');

        $this->expectException(QueryException::class);
        DB::table('categories')->delete();
    }

    public function test_batas_kembali_tidak_boleh_sebelum_tanggal_pinjam(): void
    {
        $this->peminjaman('PJM-AAA222', '2026-10-23', '2026-10-23'); // sama hari: boleh
        $this->addToAssertionCount(1);

        $this->expectException(QueryException::class);
        $this->peminjaman('PJM-BBB333', '2026-10-23', '2026-10-22');
    }

    // BR-05
    public function test_hasil_verifikasi_harus_berjumlah_sama_dengan_qty(): void
    {
        $loan = $this->peminjaman('PJM-ABC234', '2026-10-23', '2026-10-30');
        $loanItem = DB::table('loan_items')->insertGetId([
            'loan_id' => $loan, 'item_id' => $this->item('Proyektor'), 'qty_dipinjam' => 3,
        ]);
        $klaim = DB::table('return_claims')->insertGetId(['kode_klaim' => 'KMB-ABC234', 'loan_id' => $loan]);

        $id = DB::table('return_claim_lines')->insertGetId([
            'claim_id' => $klaim, 'loan_item_id' => $loanItem, 'kondisi_dilaporkan' => 'baik', 'qty' => 3,
        ]);

        DB::table('return_claim_lines')->where('id', $id)->update([
            'qty_baik' => 2, 'qty_rusak' => 1, 'qty_hilang' => 0, 'qty_ditolak' => 0, // 3 = 3
        ]);
        $this->addToAssertionCount(1);

        $this->expectException(QueryException::class);
        DB::table('return_claim_lines')->where('id', $id)->update([
            'qty_baik' => 2, 'qty_rusak' => 2, 'qty_hilang' => 0, 'qty_ditolak' => 0, // 4 != 3
        ]);
    }
}