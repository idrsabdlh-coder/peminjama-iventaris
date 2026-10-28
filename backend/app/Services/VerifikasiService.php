<?php

namespace App\Services;

use App\Support\Tanggal;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifikasiService
{
    /**
     * Memverifikasi pengembalian fisik barang oleh admin inventaris.
     * Menerapkan mutasi stok akhir ke buku besar dan tabel items.
     */
    public function verifikasi(int $claimId, array $data, int $adminId): void
    {
        DB::transaction(function () use ($claimId, $data, $adminId) {
            $klaim = DB::table('return_claims')->where('id', $claimId)->lockForUpdate()->first();

            if (!$klaim) {
                throw new HttpException(404, 'Data klaim pengembalian tidak ditemukan.');
            }

            // BR-07: Tolak verifikasi ulang dengan 409 Conflict
            if ($klaim->status !== 'menunggu') {
                throw new HttpException(409, 'Klaim pengembalian ini sudah diverifikasi sebelumnya.');
            }

            $barisKlaim = DB::table('return_claim_lines as l')
                ->join('loan_items as li', 'li.id', '=', 'l.loan_item_id')
                ->where('l.claim_id', $claimId)
                ->select('l.id', 'l.qty', 'l.kondisi_dilaporkan', 'li.item_id')
                ->get()
                ->keyBy('id');

            $inputBaris = collect($data['baris'])->keyBy(fn ($b) => (int) $b['id']);

            // Validasi semua baris harus diverifikasi lengkap
            $idsAsli = $barisKlaim->keys()->map(fn ($k) => (int) $k)->sort()->values()->all();
            $idsInput = $inputBaris->keys()->sort()->values()->all();

            if ($idsAsli !== $idsInput) {
                throw new HttpException(422, 'Seluruh baris klaim harus diverifikasi.');
            }

            $perItem = [];
            $perluCatatanAdmin = false;

            foreach ($barisKlaim as $lineId => $line) {
                $input = $inputBaris[(int) $lineId];

                $baik = (int) $input['qty_baik'];
                $rusak = (int) $input['qty_rusak'];
                $hilang = (int) $input['qty_hilang'];
                $ditolak = (int) $input['qty_ditolak'];

                $totalHasil = $baik + $rusak + $hilang + $ditolak;

                if ($totalHasil !== (int) $line->qty) {
                    throw new HttpException(422, "Baris #{$lineId}: Jumlah 4 hasil ({$totalHasil}) harus sama dengan qty klaim ({$line->qty}).");
                }

                // BR-06: Wajib catatan admin jika ada yang ditolak atau kondisi berbeda dari yang dilaporkan
                if ($ditolak > 0) {
                    $perluCatatanAdmin = true;
                }
                if ($line->kondisi_dilaporkan === 'baik' && ($rusak > 0 || $hilang > 0)) {
                    $perluCatatanAdmin = true;
                }

                DB::table('return_claim_lines')->where('id', $lineId)->update([
                    'qty_baik' => $baik,
                    'qty_rusak' => $rusak,
                    'qty_hilang' => $hilang,
                    'qty_ditolak' => $ditolak,
                ]);

                $itemId = (int) $line->item_id;
                $p = &$perItem[$itemId];
                $p['baik'] = ($p['baik'] ?? 0) + $baik;
                $p['rusak'] = ($p['rusak'] ?? 0) + $rusak;
                $p['hilang'] = ($p['hilang'] ?? 0) + $hilang;
                unset($p);
            }

            if ($perluCatatanAdmin && empty(trim($data['catatan_admin'] ?? ''))) {
                throw new HttpException(422, 'Catatan admin wajib diisi apabila ada barang ditolak atau kondisi fisik berbeda dari laporan peminjam.');
            }

            // Kunci baris items dengan urutan ID menaik untuk mencegah deadlock
            ksort($perItem);
            $itemIds = array_keys($perItem);

            DB::table('items')
                ->whereIn('id', $itemIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Terapkan mutasi stok
            foreach ($perItem as $itemId => $v) {
                $baik = $v['baik'];
                $rusak = $v['rusak'];
                $hilang = $v['hilang'];

                // Update items:
                // Baik: kembali ke rak (tersedia += baik)
                // Rusak: masuk ke jumlah unit rusak (kondisi += rusak)
                // Hilang: hilang dari aset inventaris (total -= hilang)
                if ($baik > 0 || $rusak > 0 || $hilang > 0) {
                    DB::statement(
                        'UPDATE items SET tersedia = tersedia + :baik, kondisi = kondisi + :rusak, total = total - :hilang WHERE id = :id',
                        [
                            'baik' => $baik,
                            'rusak' => $rusak,
                            'hilang' => $hilang,
                            'id' => $itemId,
                        ]
                    );
                }

                // Catat mutasi buku besar
                if ($baik > 0) {
                    DB::table('stock_movements')->insert([
                        'item_id' => $itemId,
                        'jenis' => 'VERIFIKASI_BAIK',
                        'delta_total' => 0,
                        'delta_tersedia' => $baik,
                        'delta_kondisi' => 0,
                        'ref_tipe' => 'klaim',
                        'ref_id' => $claimId,
                        'user_id' => $adminId,
                        'catatan' => 'Verifikasi pengembalian kondisi baik ' . $klaim->kode_klaim,
                    ]);
                }

                if ($rusak > 0) {
                    DB::table('stock_movements')->insert([
                        'item_id' => $itemId,
                        'jenis' => 'VERIFIKASI_RUSAK',
                        'delta_total' => 0,
                        'delta_tersedia' => 0,
                        'delta_kondisi' => $rusak,
                        'ref_tipe' => 'klaim',
                        'ref_id' => $claimId,
                        'user_id' => $adminId,
                        'catatan' => 'Verifikasi pengembalian kondisi rusak ' . $klaim->kode_klaim,
                    ]);
                }

                if ($hilang > 0) {
                    DB::table('stock_movements')->insert([
                        'item_id' => $itemId,
                        'jenis' => 'VERIFIKASI_HILANG',
                        'delta_total' => -$hilang,
                        'delta_tersedia' => 0,
                        'delta_kondisi' => 0,
                        'ref_tipe' => 'klaim',
                        'ref_id' => $claimId,
                        'user_id' => $adminId,
                        'catatan' => 'Verifikasi pengembalian barang hilang ' . $klaim->kode_klaim,
                    ]);
                }
            }

            // Perbarui status klaim menjadi diverifikasi
            DB::table('return_claims')->where('id', $claimId)->update([
                'status' => 'diverifikasi',
                'diverifikasi_oleh' => $adminId,
                'diverifikasi_pada' => Tanggal::sekarang()->toDateTimeString(),
                'catatan_admin' => $data['catatan_admin'] ?? null,
            ]);

            // Audit log
            DB::table('audit_logs')->insert([
                'user_id' => $adminId,
                'aksi' => 'klaim_verifikasi',
                'entitas' => 'klaim',
                'entitas_id' => $claimId,
                'detail' => json_encode(['kode_klaim' => $klaim->kode_klaim, 'per_barang' => $perItem]),
            ]);
        });
    }
}
