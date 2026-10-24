<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CekPeminjamanController extends Controller
{
    public function __invoke(Request $request, PeminjamanService $service): JsonResponse
    {
        $request->validate(['kode' => ['required', 'string', 'max:20']]);

        $maksGagal = (int) config('inventaris.kiosk_lookup_maks_gagal');
        $kunciMenit = (int) config('inventaris.kiosk_lookup_kunci_menit');
        $kunci = 'kiosk-lookup:'.$request->ip();

        // BR-18: percobaan gagal dibatasi.
        if (RateLimiter::tooManyAttempts($kunci, $maksGagal)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit atau hubungi petugas IT.',
                'retry_after' => $detik,
            ], 429);
        }

        $kode = strtoupper(trim((string) $request->input('kode')));
        $kartu = preg_match('/^PJM-[A-HJKMNP-Z2-9]{6}$/', $kode)
            ? $service->kartuByKode($kode)
            : null;

        // Format salah dan kode tidak ada diperlakukan sama: pesan dan hitungan gagal yang sama.
        if (! $kartu) {
            RateLimiter::hit($kunci, $kunciMenit * 60);

            return response()->json([
                'message' => 'Kode pinjam tidak ditemukan. Periksa kembali kode di struk.',
            ], 404);
        }

        RateLimiter::clear($kunci);

        return response()->json(['data' => $kartu]);
    }
}