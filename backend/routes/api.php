<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Kiosk\CekPeminjamanController;
use App\Http\Controllers\Api\Kiosk\KatalogController;
use App\Http\Controllers\Api\Kiosk\PeminjamanController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Kiosk\PembatalanController;
use App\Http\Controllers\Api\Admin\ItemController;

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

    Route::get('items', [\App\Http\Controllers\Api\Admin\ItemController::class, 'index']);
    Route::get('items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'show'])->whereNumber('id');
    Route::post('items', [\App\Http\Controllers\Api\Admin\ItemController::class, 'store']);
    Route::match(['put', 'patch'], 'items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'update'])->whereNumber('id');
    Route::delete('items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'destroy'])->whereNumber('id');
});

Route::prefix('kiosk')->group(function () {
    Route::get('katalog', [KatalogController::class, 'index']);
    Route::get('kategori', [KatalogController::class, 'kategori']);
    Route::get('divisi', [KatalogController::class, 'divisi']);
    Route::post('peminjaman', [PeminjamanController::class, 'store']);
    Route::post('peminjaman/cek', CekPeminjamanController::class);
    Route::post('peminjaman/batal', PembatalanController::class);      
});   
