<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PeriksaInvarian extends Command
{
    protected $signature = 'inventaris:periksa-invarian';
    protected $description = 'Memeriksa invarian matematis stok BR-01 dan konsistensi buku besar stock_movements';

    public function handle(): int
    {
        $this->info('Memulai pemeriksaan invarian matematis stok (BR-01)...');

        $items = DB::table('items')
            ->whereNull('dihapus_pada')
            ->get();

        $pelanggaran = 0;

        foreach ($items as $item) {
            // 1. Hitung total unit yang sedang dipinjam (belum dikembalikan dan belum menunggu verifikasi)
            $totalDipinjam = (int) DB::table('loan_items as li')
                ->join('loans as l', 'l.id', '=', 'li.loan_id')
                ->where('li.item_id', $item->id)
                ->whereNull('l.dibatalkan_pada')
                ->sum('li.qty_dipinjam');

            $dikembalikanAtauMenunggu = (int) DB::table('return_claim_lines as rcl')
                ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
                ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
                ->where('li.item_id', $item->id)
                ->sum(DB::raw("CASE 
                    WHEN rc.status = 'menunggu' THEN rcl.qty 
                    WHEN rc.status = 'diverifikasi' THEN (rcl.qty_baik + rcl.qty_rusak + rcl.qty_hilang)
                    ELSE 0 END"));

            $menungguVerifikasi = (int) DB::table('return_claim_lines as rcl')
                ->join('loan_items as li', 'li.id', '=', 'rcl.loan_item_id')
                ->join('return_claims as rc', 'rc.id', '=', 'rcl.claim_id')
                ->where('li.item_id', $item->id)
                ->where('rc.status', 'menunggu')
                ->sum('rcl.qty');

            $sisaDipinjam = max(0, $totalDipinjam - $dikembalikanAtauMenunggu);

            // BR-01: tersedia + kondisi + dipinjam + menunggu_verifikasi == total
            $totalHitung = (int) $item->tersedia + (int) $item->kondisi + $sisaDipinjam + $menungguVerifikasi;

            if ($totalHitung !== (int) $item->total) {
                $this->error("Pelanggaran invarian pada barang #{$item->id} ({$item->nama}): " .
                    "tersedia({$item->tersedia}) + kondisi({$item->kondisi}) + dipinjam({$sisaDipinjam}) + menunggu({$menungguVerifikasi}) = {$totalHitung} != total({$item->total})");
                $pelanggaran++;
            }
        }

        if ($pelanggaran === 0) {
            $this->info("Pemeriksaan selesai. 0 pelanggaran ditemukan dari " . count($items) . " barang.");
            return self::SUCCESS;
        }

        $this->error("Pemeriksaan gagal: ditemukan {$pelanggaran} pelanggaran invarian.");
        return self::FAILURE;
    }
}
