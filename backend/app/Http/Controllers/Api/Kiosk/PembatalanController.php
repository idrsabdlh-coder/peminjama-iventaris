<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\PembatalanRequest;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class PembatalanController extends Controller
{
    public function __invoke(PembatalanRequest $request, PeminjamanService $service): JsonResponse
    {
        $maksGagal = (int) config('inventaris.kiosk_lookup_maks_gagal');
        $kunciMenit = (int) config('inventaris.kiosk_lookup_kunci_menit');
        $kunci = 'kiosk-lookup:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, $maksGagal)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit atau hubungi petugas IT.',
                'retry_after' => $detik,
            ], 429);
        }

        $kode = $request->validated('kode_pinjam');

        $kartu = preg_match('/^PJM-[A-HJKMNP-Z2-9]{6}$/', $kode)
            ? $service->batal($kode, $request->validated('alasan'))
            : null;

        if (! $kartu) {
            RateLimiter::hit($kunci, $kunciMenit * 60);

            return response()->json([
                'message' => 'Kode pinjam tidak ditemukan. Periksa kembali kode di struk.',
            ], 404);
        }

        return response()->json(['data' => $kartu]);
    }
}