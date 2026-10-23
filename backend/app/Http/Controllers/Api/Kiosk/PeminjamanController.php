<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kiosk\PeminjamanStoreRequest;
use App\Services\PeminjamanService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

class PeminjamanController extends Controller
{
    public function store(PeminjamanStoreRequest $request, PeminjamanService $service): JsonResponse
    {
        $data = $request->validated();
        $kunci = $data['idempotency_key'];

        // BR-20: permintaan ulang dengan kunci sama mengembalikan hasil yang sama.
        if ($id = $service->cariIdByKunci($kunci)) {
            return response()->json(['data' => $service->tampilkan($id)], 200);
        }

        try {
            $id = $service->buat($data);
        } catch (UniqueConstraintViolationException $e) {
            // Dua permintaan dengan kunci sama masuk bersamaan: yang kalah mengambil hasil yang menang.
            $id = $service->cariIdByKunci($kunci);
            if (! $id) {
                throw $e;
            }

            return response()->json(['data' => $service->tampilkan($id)], 200);
        }

        return response()->json(['data' => $service->tampilkan($id)], 201);
    }
}