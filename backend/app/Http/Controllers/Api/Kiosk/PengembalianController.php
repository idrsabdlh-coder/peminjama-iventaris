<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengembalianController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $d = $request->validate([
            'kode_pinjam'      => ['required', 'string', 'max:12'],
            'catatan_peminjam' => ['nullable', 'string', 'max:500'],
            'idempotency_key'  => ['nullable', 'uuid'],
            'baris'            => ['required', 'array', 'min:1'],
            'baris.*.item_id'  => ['required', 'integer', 'min:1'],
            'baris.*.kondisi'  => ['required', 'in:baik,rusak,hilang'],
            'baris.*.qty'      => ['required', 'integer', 'min:1'],
        ]);

        if (! empty($d['idempotency_key'])) {
            $ada = DB::table('return_claims')->where('idempotency_key', $d['idempotency_key'])->first();
            if ($ada) {
                return response()->json(['data' => $this->format($ada)]);
            }
        }

        $klaim = DB::transaction(function () use ($d) {
            $loan = DB::table('loans')->where('kode_pinjam', $d['kode_pinjam'])->lockForUpdate()->first();

            abort_if(! $loan, 404, 'Peminjaman tidak ditemukan.');

            if ($loan->dibatalkan_pada !== null) {
                throw ValidationException::withMessages(['kode_pinjam' => 'Peminjaman ini sudah dibatalkan.']);
            }

            $loanItems = DB::table('loan_items')->where('loan_id', $loan->id)->get()->keyBy('item_id');

            $sudahAda = [];
            $perItem = [];

            foreach ($d['baris'] as $i => $b) {
                if (! $loanItems->has($b['item_id'])) {
                    throw ValidationException::withMessages(["baris.$i.item_id" => 'Barang ini tidak ada dalam peminjaman.']);
                }

                $kunci = $b['item_id'].'|'.$b['kondisi'];
                if (isset($sudahAda[$kunci])) {
                    throw ValidationException::withMessages(["baris.$i.kondisi" => 'Barang dan kondisi yang sama tidak boleh ditulis dua kali.']);
                }
                $sudahAda[$kunci] = true;

                $perItem[$b['item_id']] = ($perItem[$b['item_id']] ?? 0) + (int) $b['qty'];
            }

            foreach ($perItem as $itemId => $qty) {
                $li = $loanItems[$itemId];

                // Yang sudah diverifikasi dihitung dari tiga hasil kembali; yang menunggu dihitung penuh.
                $terpakai = (int) DB::table('return_claim_lines as l')
                    ->join('return_claims as c', 'c.id', '=', 'l.claim_id')
                    ->where('l.loan_item_id', $li->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN c.status = 'menunggu' THEN l.qty
                                                   ELSE l.qty_baik + l.qty_rusak + l.qty_hilang END), 0) AS n")
                    ->value('n');

                $sisa = (int) $li->qty_dipinjam - $terpakai;

                if ($qty > $sisa) {
                    throw ValidationException::withMessages([
                        'baris' => "Jumlah melebihi sisa yang belum dikembalikan ({$sisa}) untuk barang #{$itemId}.",
                    ]);
                }
            }

            $terlambat = (int) max(0, Carbon::parse($loan->batas_kembali)->startOfDay()->diffInDays(Carbon::today(), false));

            $klaimId = DB::table('return_claims')->insertGetId([
                'kode_klaim'       => 'KLM-'.Str::upper(Str::random(8)), // 12 karakter
                'loan_id'          => $loan->id,
                'catatan_peminjam' => $d['catatan_peminjam'] ?? null,
                'hari_terlambat'   => $terlambat,
                'idempotency_key'  => $d['idempotency_key'] ?? null,
            ]);

            foreach ($d['baris'] as $b) {
                DB::table('return_claim_lines')->insert([
                    'claim_id'           => $klaimId,
                    'loan_item_id'       => $loanItems[$b['item_id']]->id,
                    'kondisi_dilaporkan' => $b['kondisi'],
                    'qty'                => (int) $b['qty'],
                ]);
            }

            return DB::table('return_claims')->where('id', $klaimId)->first();
        });

        return response()->json(['data' => $this->format($klaim)], 201);
    }

    private function format(object $k): array
    {
        return [
            'kode_klaim'     => $k->kode_klaim,
            'status'         => $k->status,
            'hari_terlambat' => (int) $k->hari_terlambat,
        ];
    }
}