<?php

namespace App\Services;

use App\Support\KodeAcak;
use App\Support\Tanggal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KlaimService
{
    /**
     * Mengajukan klaim pengembalian dari kiosk.
     * Mengembalikan id return_claims yang dibuat.
     */
    public function ajukan(array $data): int
    {
        $kodePinjam = strtoupper(trim($data['kode_pinjam']));

        // Cek idempotensi
        if (!empty($data['idempotency_key'])) {
            $ada = DB::table('return_claims')->where('idempotency_key', $data['idempotency_key'])->value('id');
            if ($ada) {
                return (int) $ada;
            }
        }

        return DB::transaction(function () use ($data, $kodePinjam) {
            $loan = DB::table('loans')->where('kode_pinjam', $kodePinjam)->lockForUpdate()->first();

            if (!$loan) {
                throw new HttpException(404, 'Kode peminjaman tidak ditemukan.');
            }

            if (!empty($loan->dibatalkan_pada)) {
                throw new HttpException(422, 'Peminjaman ini sudah dibatalkan.');
            }

            $hariIni = Tanggal::sekarang()->startOfDay();
            $tanggalPinjam = Tanggal::keAwalHari($loan->tanggal_pinjam);

            if ($tanggalPinjam->gt($hariIni)) {
                throw new HttpException(422, 'Peminjaman ini belum dimulai.');
            }

            $loanItems = DB::table('loan_items')->where('loan_id', $loan->id)->get()->keyBy('item_id');

            $sudahAda = [];
            $perItem = [];

            foreach ($data['baris'] as $i => $b) {
                $itemId = (int) $b['item_id'];
                if (!$loanItems->has($itemId)) {
                    throw new HttpException(422, "Barang #{$itemId} tidak ada dalam peminjaman ini.");
                }

                $kondisi = $b['kondisi'];
                $kunci = "{$itemId}|{$kondisi}";
                if (isset($sudahAda[$kunci])) {
                    throw new HttpException(422, 'Barang dan kondisi yang sama tidak boleh diduplikasi dalam satu klaim.');
                }
                $sudahAda[$kunci] = true;

                $perItem[$itemId] = ($perItem[$itemId] ?? 0) + (int) $b['qty'];
            }

            // Validasi sisa unit yang belum dikembalikan (BR-04)
            foreach ($perItem as $itemId => $qtyKlaim) {
                $li = $loanItems[$itemId];

                $terpakai = (int) DB::table('return_claim_lines as l')
                    ->join('return_claims as c', 'c.id', '=', 'l.claim_id')
                    ->where('l.loan_item_id', $li->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN c.status = 'menunggu' THEN l.qty
                                                   ELSE l.qty_baik + l.qty_rusak + l.qty_hilang END), 0) AS n")
                    ->value('n');

                $sisa = (int) $li->qty_dipinjam - $terpakai;

                if ($qtyKlaim > $sisa) {
                    throw new HttpException(422, "Jumlah pengembalian ({$qtyKlaim}) melebihi sisa unit yang belum dikembalikan ({$sisa}) untuk barang #{$itemId}.");
                }
            }

            // Pembekuan keterlambatan (BR-10): dihitung saat klaim diajukan
            $hariTerlambat = Tanggal::hitungKeterlambatan($loan->batas_kembali);

            $kodeKlaim = KodeAcak::klaim();

            $claimId = DB::table('return_claims')->insertGetId([
                'kode_klaim' => $kodeKlaim,
                'loan_id' => $loan->id,
                'catatan_peminjam' => $data['catatan_peminjam'] ?? null,
                'diklaim_pada' => Tanggal::sekarang()->toDateTimeString(),
                'hari_terlambat' => $hariTerlambat,
                'status' => 'menunggu',
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($data['baris'] as $b) {
                $li = $loanItems[(int) $b['item_id']];

                DB::table('return_claim_lines')->insert([
                    'claim_id' => $claimId,
                    'loan_item_id' => $li->id,
                    'kondisi_dilaporkan' => $b['kondisi'],
                    'qty' => (int) $b['qty'],
                    'qty_baik' => 0,
                    'qty_rusak' => 0,
                    'qty_hilang' => 0,
                    'qty_ditolak' => 0,
                ]);
            }

            // Sesuai BR-03: Klaim mandiri TIDAK MENGUBAH stok barang fisik sama sekali.
            return $claimId;
        });
    }
}
