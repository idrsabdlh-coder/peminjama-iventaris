<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengembalianController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $d = $request->validate([
            'kode_pinjam'      => ['required', 'string', 'max:20'],
            'catatan_peminjam' => ['nullable', 'string', 'max:500'],
            'idempotency_key'  => ['nullable', 'uuid'],
            'baris'            => ['required', 'array', 'min:1'],
            'baris.*.item_id'  => ['required', 'integer', 'min:1'],
            'baris.*.kondisi'  => ['required', 'in:baik,rusak,hilang'],
            'baris.*.qty'      => ['required', 'integer', 'min:1'],
        ]);

        $maksGagal  = (int) config('inventaris.kiosk_lookup_maks_gagal');
        $kunciMenit = (int) config('inventaris.kiosk_lookup_kunci_menit');
        $kunciRl    = 'kiosk-lookup:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciRl, $maksGagal)) {
            $detik = RateLimiter::availableIn($kunciRl);

            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit atau hubungi petugas IT.',
                'retry_after' => $detik,
            ], 429);
        }

        $kode = strtoupper(trim($d['kode_pinjam']));
        $tidakDitemukan = fn () => response()->json([
            'message' => 'Kode pinjam tidak ditemukan. Periksa kembali kode di struk.',
        ], 404);

        if (! preg_match('/^PJM-[A-HJKMNP-Z2-9]{6}$/', $kode)) {
            RateLimiter::hit($kunciRl, $kunciMenit * 60);

            return $tidakDitemukan();
        }

        if (! empty($d['idempotency_key'])) {
            $ada = DB::table('return_claims')->where('idempotency_key', $d['idempotency_key'])->first();
            if ($ada) {
                return response()->json(['data' => $this->format($ada)]);
            }
        }

        $klaim = DB::transaction(function () use ($d, $kode) {
            $loan = DB::table('loans')->where('kode_pinjam', $kode)->lockForUpdate()->first();

            if (! $loan) {
                return null;
            }

            if ($loan->dibatalkan_pada !== null) {
                throw ValidationException::withMessages(['kode_pinjam' => 'Peminjaman ini sudah dibatalkan.']);
            }

            $hariIni = Carbon::today('Asia/Jakarta');

            if (Carbon::parse($loan->tanggal_pinjam, 'Asia/Jakarta')->startOfDay()->gt($hariIni)) {
                throw ValidationException::withMessages(['kode_pinjam' => 'Peminjaman ini belum dimulai.']);
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

            $terlambat = (int) max(0, Carbon::parse($loan->batas_kembali, 'Asia/Jakarta')->startOfDay()
                ->diffInDays($hariIni, false));

            $klaimId = DB::table('return_claims')->insertGetId([
                'kode_klaim'       => 'KLM-'.Str::upper(Str::random(8)),
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

        if (! $klaim) {
            RateLimiter::hit($kunciRl, $kunciMenit * 60);

            return $tidakDitemukan();
        }

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