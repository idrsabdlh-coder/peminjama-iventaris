<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'store']);

    Route::middleware(['auth:sanctum', 'admin.aktif'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy']);
        Route::get('me', [LoginController::class, 'me']);
        Route::post('change-password', [PasswordController::class, 'update']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.aktif'])->group(function () {
    // SEMENTARA: penanda rute admin terlindungi untuk uji T-5.1. Diganti rute asli di Fase 4.
    Route::get('ping', fn () => response()->json(['ok' => true]));
});

// Rute kiosk ditambahkan di Fase 2.