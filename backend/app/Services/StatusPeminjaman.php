<?php

namespace App\Services;

use App\Support\Tanggal;
use Illuminate\Support\Facades\DB;

class StatusPeminjaman
{
    /**
     * Menghitung status peminjaman secara dinamis berdasarkan data transaksi dan pengembalian.
     * Mengembalikan salah satu dari: 'dibatalkan', 'dipesan', 'selesai', 'menunggu_verifikasi', 'terlambat', 'dipinjam'.
     */
    public function hitungStatus(array $loan): string
    {
        // 1. Dibatalkan
        if (!empty($loan['dibatalkan_pada'])) {
            return 'dibatalkan';
        }

        $hariIni = Tanggal::sekarang()->startOfDay();
        $tanggalPinjam = Tanggal::keAwalHari($loan['tanggal_pinjam']);

        // 2. Dipesan (tanggal pinjam di masa depan)
        if ($tanggalPinjam->gt($hariIni)) {
            return 'dipesan';
        }

        $loanId = (int) $loan['id'];

        // Ambil total kuantitas dipinjam
        $totalDipinjam = (int) DB::table('loan_items')
            ->where('loan_id', $loanId)
            ->sum('qty_dipinjam');

        // Ambil rincian unit yang sudah diverifikasi dan yang sedang menunggu verifikasi
        $klaim = DB::table('return_claim_lines as rcl')
            ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
            ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
            ->where('li.loan_id', $loanId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN rc.status = 'diverifikasi' THEN (rcl.qty_baik + rcl.qty_rusak + rcl.qty_hilang) ELSE 0 END), 0) as unit_selesai,
                COALESCE(SUM(CASE WHEN rc.status = 'menunggu' THEN rcl.qty ELSE 0 END), 0) as unit_menunggu
            ")
            ->first();

        $unitSelesai = (int) ($klaim->unit_selesai ?? 0);
        $unitMenunggu = (int) ($klaim->unit_menunggu ?? 0);

        // 3. Selesai jika seluruh unit sudah diverifikasi
        if ($totalDipinjam > 0 && $unitSelesai >= $totalDipinjam) {
            return 'selesai';
        }

        $sisaUnitPeminjam = max(0, $totalDipinjam - $unitSelesai - $unitMenunggu);

        // 4. Menunggu Verifikasi jika seluruh sisa unit fisik sedang diajukan dalam klaim menunggu
        if ($sisaUnitPeminjam === 0 && $unitMenunggu > 0) {
            return 'menunggu_verifikasi';
        }

        // Cek keterlambatan
        $batasKembali = Tanggal::keAwalHari($loan['batas_kembali']);
        $terlambat = $hariIni->gt($batasKembali);

        // 5. Terlambat jika masih ada unit di peminjam dan hari ini > batas kembali
        if ($terlambat) {
            return 'terlambat';
        }

        // 6. Dipinjam jika masih ada unit di peminjam dan belum lewat batas
        return 'dipinjam';
    }
}
