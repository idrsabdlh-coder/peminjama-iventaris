<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Divisi;
use App\Models\Kategori;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KioskController extends Controller
{
    // 1. Ambil Katalog Barang & Filter Kategori (FR-K-01, BR-29, BR-31)
    public function index(Request $request)
    {
        $kategoriId = $request->query('kategori_id');

        $query = Barang::with('kategori')
            ->where('tersedia', '>', 0);

        if ($kategoriId) {
            $query->where('kategori_id', $kategoriId);
        }

        $barangs = $query->get()->map(function ($barang) {
            // Turunan Kondisi Barang (BR-29)
            $kondisi = 'Baik';
            if ($barang->rusak == $barang->total && $barang->tersedia == 0) {
                $kondisi = 'Semua rusak';
            } elseif ($barang->rusak > 0 && $barang->rusak < $barang->total) {
                $kondisi = 'Sebagian rusak';
            }

            return [
                'id' => $barang->id,
                'nama_barang' => $barang->nama_barang,
                'kategori' => $barang->kategori->nama ?? '-',
                'tersedia' => $barang->tersedia,
                'kondisi' => $kondisi,
                'boleh_diperpanjang' => $barang->boleh_diperpanjang,
            ];
        });

        $kategoris = Kategori::where('is_active', true)->get();
        $divisis = Divisi::where('is_active', true)->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'barangs' => $barangs,
                'kategoris' => $kategoris,
                'divisis' => $divisis,
            ]
        ]);
    }

    // 2. Simpan Transaksi Peminjaman / Pemesanan (FR-K-02, BR-01, BR-02, BR-17, BR-20, BR-30)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_peminjam' => 'required|string|min:2|max:100',
            'divisi_id' => 'required|exists:divisis,id',
            'tipe_peminjam' => 'required|in:karyawan,magang',
            'telepon' => 'nullable|string|min:8|max:15',
            'tanggal_pinjam' => 'required|date|after_or_equal:today',
            'durasi_hari' => 'required|integer|min:1|max:7', // Maksimal durasi awal 7 hari
            'items' => 'required|array|min:1',
            'items.*.barang_id' => 'required|exists:barangs,id|distinct',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $today = Carbon::today('Asia/Jakarta');
            $tanggalPinjam = Carbon::parse($validated['tanggal_pinjam'], 'Asia/Jakarta');
            $batasKembali = (clone $tanggalPinjam)->addDays($validated['durasi_hari']);
            
            // Status: 'dipesan' jika tanggal pinjam di masa depan, 'dipinjam' jika hari ini (D-6, D-10)
            $isPemesanan = $tanggalPinjam->greaterThan($today);
            $status = $isPemesanan ? 'dipesan' : 'dipinjam';

            // Generate Kode Pinjam unik PJM-XXXXXX (BR-17)
            do {
                $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // Unambiguous chars
                $randomCode = substr(str_shuffle(str_repeat($characters, 6)), 0, 6);
                $kodePinjam = 'PJM-' . $randomCode;
            } while (Loan::where('kode_pinjam', $kodePinjam)->exists());

            // 1. Buat record Peminjaman
            $loan = Loan::create([
                'kode_pinjam' => $kodePinjam,
                'nama_peminjam' => $validated['nama_peminjam'],
                'divisi_id' => $validated['divisi_id'],
                'tipe_peminjam' => $validated['tipe_peminjam'],
                'telepon' => $validated['telepon'] ?? null,
                'tanggal_pinjam' => $tanggalPinjam->toDateString(),
                'batas_kembali' => $batasKembali->toDateString(),
                'status' => $status,
            ]);

            foreach ($validated['items'] as $itemData) {
                // Penguncian baris (SELECT FOR UPDATE) untuk mencegah race condition (BR-01, T-5.21)
                $barang = Barang::where('id', $itemData['barang_id'])->lockForUpdate()->first();

                if ($barang->tersedia < $itemData['qty']) {
                    throw new \Exception("Stok barang '{$barang->nama_barang}' tidak mencukupi. Tersedia: {$barang->tersedia}");
                }

                // Update stok barang
                $barang->tersedia -= $itemData['qty'];
                $barang->dipinjam += $itemData['qty'];
                $barang->save();

                // 2. Buat detail barang peminjaman
                LoanItem::create([
                    'loan_id' => $loan->id,
                    'barang_id' => $barang->id,
                    'qty_dipinjam' => $itemData['qty'],
                ]);

                // 3. Catat Perubahan ke Buku Besar / Ledger (FR-S-02)
                StockMovement::create([
                    'barang_id' => $barang->id,
                    'loan_id' => $loan->id,
                    'tipe_aksi' => $isPemesanan ? 'pesan' : 'pinjam',
                    'perubahan_tersedia' => -$itemData['qty'],
                    'perubahan_dipinjam' => +$itemData['qty'],
                    'keterangan' => "Peminjaman oleh {$loan->nama_peminjam} ({$loan->kode_pinjam})",
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Peminjaman berhasil dicatat',
                'data' => [
                    'kode_pinjam' => $loan->kode_pinjam,
                    'nama_peminjam' => $loan->nama_peminjam,
                    'tanggal_pinjam' => $loan->tanggal_pinjam,
                    'batas_kembali' => $loan->batas_kembali,
                    'status' => $loan->status,
                ]
            ], 201);
        });
    }
}