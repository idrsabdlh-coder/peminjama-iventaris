<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\PembatalanRequest;
use App\Services\PeminjamanService;
use Illuminate\Http\JsonResponse;

class PembatalanController extends Controller
{
    public function __invoke(PembatalanRequest $request, PeminjamanService $service): JsonResponse
    {
        $kartu = $service->batal($request->validated('kode_pinjam'), $request->validated('alasan'));

        if ($kartu === null) {
            return response()->json(['message' => 'Kode peminjaman tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $kartu]);
    }
}