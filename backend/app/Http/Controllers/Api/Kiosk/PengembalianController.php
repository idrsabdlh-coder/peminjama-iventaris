<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Services\KlaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class PengembalianController extends Controller
{
    public function __invoke(Request $request, KlaimService $klaimService): JsonResponse
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

        $maksGagal  = (int) (config('inventaris.kiosk_lookup_maks_gagal') ?? 5);
        $kunciMenit = (int) (config('inventaris.kiosk_lookup_kunci_menit') ?? 5);
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

        $claimId = $klaimService->ajukan($d);
        $klaim = DB::table('return_claims')->where('id', $claimId)->first();

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
