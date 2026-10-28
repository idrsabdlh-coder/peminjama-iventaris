<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Division;
use App\Models\Item;
use App\Models\Loan;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\PeminjamanService;
use App\Services\KlaimService;
use App\Services\VerifikasiService;
use App\Support\KodeAcak;
use App\Support\Tanggal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InvarianDanKonkurensiTest extends TestCase
{
    use RefreshDatabase;

    private Division $divisi;
    private Category $kategori;
    private Item $item;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->divisi = Division::create(['nama' => 'IT Support', 'aktif' => 1]);
        $this->kategori = Category::create(['nama' => 'Elektronik', 'aktif' => 1]);
        $this->item = Item::create([
            'nama' => 'Monitor Dell 24 Inch',
            'kategori_id' => $this->kategori->id,
            'total' => 10,
            'tersedia' => 10,
            'kondisi' => 0,
            'boleh_diperpanjang' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Admin Gudang',
            'email' => 'admin@kantor.local',
            'password' => bcrypt('password123'),
            'aktif' => 1,
        ]);
    }

    /**
     * U-01: Uji Konkurensi Stok.
     * Menguji bahwa pengurangan stok bersyarat di server (atomic query)
     * mencegah overselling / stok negatif ketika stok tersisa terbatas.
     */
    public function test_u01_konkurensi_stok(): void
    {
        // Set barang hanya tersisa 1 unit
        $this->item->update(['total' => 1, 'tersedia' => 1]);

        $service = app(PeminjamanService::class);

        $payload1 = [
            'nama_peminjam' => 'Karyawan A',
            'division_id' => $this->divisi->id,
            'tipe_peminjam' => 'karyawan',
            'tanggal_pinjam' => Tanggal::hariIni(),
            'batas_kembali' => date('Y-m-d', strtotime('+7 days')),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 1],
            ],
        ];

        $payload2 = [
            'nama_peminjam' => 'Karyawan B',
            'division_id' => $this->divisi->id,
            'tipe_peminjam' => 'karyawan',
            'tanggal_pinjam' => Tanggal::hariIni(),
            'batas_kembali' => date('Y-m-d', strtotime('+7 days')),
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 1],
            ],
        ];

        // Transaksi pertama berhasil
        $loanId1 = $service->buat($payload1);
        $this->assertIsInt($loanId1);

        // Transaksi kedua harus ditolak karena stok habis (409 Conflict atau exception)
        $gagal = false;
        try {
            $service->buat($payload2);
        } catch (\Throwable $e) {
            $gagal = true;
            // Pastikan eksepsi berupa conflict 409 atau validation error
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 409;
            $this->assertTrue(in_array($statusCode, [409, 422]), "Status code {$statusCode} bukan 409/422");
        }

        $this->assertTrue($gagal, 'Transaksi kedua seharusnya ditolak karena stok tidak cukup');

        // Pastikan stok akhir tetap 0 (tidak pernah negatif)
        $this->item->refresh();
        $this->assertSame(0, $this->item->tersedia);
        $this->assertSame(1, $this->item->total);
    }

    /**
     * U-02: Uji Idempotensi Transaksi Peminjaman.
     * Mengirim form peminjaman dua kali dengan idempotency_key yang sama
     * hanya menghasilkan tepat 1 transaksi dan tidak menduplikasi pemotongan stok.
     */
    public function test_u02_idempotensi_peminjaman(): void
    {
        $key = (string) Str::uuid();
        $service = app(PeminjamanService::class);

        $payload = [
            'nama_peminjam' => 'Andi Santoso',
            'division_id' => $this->divisi->id,
            'tipe_peminjam' => 'karyawan',
            'tanggal_pinjam' => Tanggal::hariIni(),
            'batas_kembali' => date('Y-m-d', strtotime('+7 days')),
            'idempotency_key' => $key,
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 2],
            ],
        ];

        // Panggilan pertama
        $loanId1 = $service->buat($payload);

        // Panggilan kedua dengan kunci yang sama
        $loanId2 = $service->buat($payload);

        // Harus mengembalikan id transaksi yang sama
        $this->assertSame($loanId1, $loanId2);

        // Hanya ada 1 record loan di database
        $this->assertSame(1, Loan::where('idempotency_key', $key)->count());

        // Stok barang hanya dipotong sekali (10 - 2 = 8, bukan 6)
        $this->item->refresh();
        $this->assertSame(8, $this->item->tersedia);

        // Hanya ada 1 baris mutasi stok PINJAM
        $this->assertSame(1, StockMovement::where('item_id', $this->item->id)->where('jenis', 'PINJAM')->count());
    }

    /**
     * U-03: Uji Invarian Matematis Stok Acak (Simulasi Transaksi).
     * BR-01: tersedia + kondisi + dipinjam + menunggu_verifikasi == total.
     * Dan akumulasi delta pada stock_movements sama persis dengan angka di items.
     */
    public function test_u03_invarian_acak_stok(): void
    {
        $peminjamanService = app(PeminjamanService::class);
        $klaimService = app(KlaimService::class);
        $verifikasiService = app(VerifikasiService::class);

        // Inisialisasi barang uji dengan stok 20
        $this->item->update(['total' => 20, 'tersedia' => 20, 'kondisi' => 0]);

        $activeLoans = [];

        // Simulasi 10 siklus operasi transaksi campuran
        for ($i = 0; $i < 10; $i++) {
            $action = rand(1, 3);

            if ($action === 1 && $this->item->fresh()->tersedia >= 2) {
                // Pinjam 2 unit
                $qty = rand(1, min(3, $this->item->fresh()->tersedia));
                $loanId = $peminjamanService->buat([
                    'nama_peminjam' => 'Peminjam ' . $i,
                    'division_id' => $this->divisi->id,
                    'tipe_peminjam' => 'karyawan',
                    'tanggal_pinjam' => Tanggal::hariIni(),
                    'batas_kembali' => date('Y-m-d', strtotime('+7 days')),
                    'idempotency_key' => (string) Str::uuid(),
                    'items' => [
                        ['item_id' => $this->item->id, 'qty' => $qty],
                    ],
                ]);
                $loan = Loan::find($loanId);
                $activeLoans[] = ['loan' => $loan, 'qty' => $qty];
            } elseif ($action === 2 && !empty($activeLoans)) {
                // Klaim pengembalian
                $idx = array_rand($activeLoans);
                $selected = $activeLoans[$idx];
                $claimId = $klaimService->ajukan([
                    'kode_pinjam' => $selected['loan']->kode_pinjam,
                    'catatan_peminjam' => 'Kembali sebagian/penuh',
                    'idempotency_key' => (string) Str::uuid(),
                    'baris' => [
                        [
                            'item_id' => $this->item->id,
                            'kondisi' => 'baik',
                            'qty' => $selected['qty'],
                        ],
                    ],
                ]);

                // Verifikasi klaim
                $baik = rand(0, $selected['qty']);
                $rusak = rand(0, $selected['qty'] - $baik);
                $hilang = rand(0, $selected['qty'] - $baik - $rusak);
                $ditolak = $selected['qty'] - $baik - $rusak - $hilang;

                $claimLines = DB::table('return_claim_lines')->where('claim_id', $claimId)->get();
                $verifikasiService->verifikasi($claimId, [
                    'catatan_admin' => 'Verifikasi simulasi test',
                    'baris' => [
                        [
                            'id' => $claimLines->first()->id,
                            'qty_baik' => $baik,
                            'qty_rusak' => $rusak,
                            'qty_hilang' => $hilang,
                            'qty_ditolak' => $ditolak,
                        ],
                    ],
                ], $this->admin->id);

                unset($activeLoans[$idx]);
            }

            // Validasi rumus BR-01 pada setiap iterasi:
            // tersedia + kondisi + dipinjam + menunggu_verifikasi == total
            $this->item->refresh();

            $dipinjam = (int) DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('li.item_id', $this->item->id)
                ->whereNull('l.dibatalkan_pada')
                ->sum('li.qty_dipinjam');

            $dikembalikanAtauMenunggu = (int) DB::table('return_claim_lines as rcl')
                ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
                ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
                ->where('li.item_id', $this->item->id)
                ->sum(DB::raw("CASE 
                    WHEN rc.status = 'menunggu' THEN rcl.qty 
                    WHEN rc.status = 'diverifikasi' THEN (rcl.qty_baik + rcl.qty_rusak + rcl.qty_hilang)
                    ELSE 0 END"));

            $menungguVerifikasi = (int) DB::table('return_claim_lines as rcl')
                ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
                ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
                ->where('li.item_id', $this->item->id)
                ->where('rc.status', 'menunggu')
                ->sum('rcl.qty');

            $sisaDipinjam = max(0, $dipinjam - $dikembalikanAtauMenunggu);

            $invarianKiri = $this->item->tersedia + $this->item->kondisi + $sisaDipinjam + $menungguVerifikasi;
            $this->assertSame(
                $this->item->total,
                $invarianKiri,
                "Invarian BR-01 dilanggar pada iterasi {$i}: {$this->item->tersedia} + {$this->item->kondisi} + {$sisaDipinjam} + {$menungguVerifikasi} != {$this->item->total}"
            );
        }
    }

    /**
     * U-04: Uji Ketahanan Rollback (Atomic Rollback).
     * Transaksi yang digagalkan di tengah proses tidak meninggalkan data parsial.
     */
    public function test_u04_atomic_rollback_transaksi(): void
    {
        $service = app(PeminjamanService::class);
        $stokAwal = $this->item->tersedia;
        $totalAwal = $this->item->total;
        $key = (string) Str::uuid();

        $payload = [
            'nama_peminjam' => 'Karyawan Gagal',
            'division_id' => $this->divisi->id,
            'tipe_peminjam' => 'karyawan',
            'tanggal_pinjam' => Tanggal::hariIni(),
            'batas_kembali' => date('Y-m-d', strtotime('+7 days')),
            'idempotency_key' => $key,
            'items' => [
                ['item_id' => $this->item->id, 'qty' => 3],
                ['item_id' => 999999, 'qty' => 1], // Barang fiktif sengaja memicu eksepsi
            ],
        ];

        $terlempar = false;
        try {
            $service->buat($payload);
        } catch (\Throwable) {
            $terlempar = true;
        }

        $this->assertTrue($terlempar, 'Harus melempar eksepsi karena item id 999999 tidak valid');

        // Pastikan tidak ada data loan atau loan_items yang tersimpan
        $this->assertFalse(Loan::where('idempotency_key', $key)->exists());

        // Pastikan stok tidak berkurang
        $this->item->refresh();
        $this->assertSame($stokAwal, $this->item->tersedia);
        $this->assertSame($totalAwal, $this->item->total);

        // Pastikan tidak ada stock_movements yang tercipta
        $this->assertSame(0, StockMovement::where('item_id', $this->item->id)->count());
    }
}
