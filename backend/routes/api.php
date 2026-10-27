<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Kiosk\CekPeminjamanController;
use App\Http\Controllers\Api\Kiosk\KatalogController;
use App\Http\Controllers\Api\Kiosk\PeminjamanController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Kiosk\PembatalanController;
use App\Http\Controllers\Api\Admin\ItemController;
use App\Http\Controllers\Api\Kiosk\PengembalianController;
use App\Http\Controllers\Api\Admin\KlaimController;
use App\Http\Controllers\Api\Admin\PengembalianController as AdminPengembalianController;
 

Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'store']);
    Route::post('logout-all', function (Request $request) {
    $request->user()->tokens()->delete();

    return response()->noContent();
});

    Route::middleware(['auth:sanctum', 'admin.aktif'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy']);
        Route::get('me', [LoginController::class, 'me']);
        Route::post('change-password', [PasswordController::class, 'update']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.aktif'])->group(function () {
    // SEMENTARA: penanda rute admin terlindungi untuk uji T-5.1. Diganti rute asli di Fase 4.
    Route::get('ping', fn () => response()->json(['ok' => true]));
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('items', [\App\Http\Controllers\Api\Admin\ItemController::class, 'index']);
    Route::get('items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'show'])->whereNumber('id');
    Route::post('items', [\App\Http\Controllers\Api\Admin\ItemController::class, 'store']);
    Route::match(['put', 'patch'], 'items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'update'])->whereNumber('id');
    Route::delete('items/{id}', [\App\Http\Controllers\Api\Admin\ItemController::class, 'destroy'])->whereNumber('id');

    Route::get('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'index']);
    Route::post('categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'store']);
    Route::get('categories/{id}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'categories/{id}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'update'])->whereNumber('id');
    Route::delete('categories/{id}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'destroy'])->whereNumber('id');

    Route::get('divisions', [\App\Http\Controllers\Api\Admin\DivisionController::class, 'index']);
    Route::post('divisions', [\App\Http\Controllers\Api\Admin\DivisionController::class, 'store']);
    Route::get('divisions/{id}', [\App\Http\Controllers\Api\Admin\DivisionController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'divisions/{id}', [\App\Http\Controllers\Api\Admin\DivisionController::class, 'update'])->whereNumber('id');
    Route::delete('divisions/{id}', [\App\Http\Controllers\Api\Admin\DivisionController::class, 'destroy'])->whereNumber('id');
    Route::get('klaim', [KlaimController::class, 'index']);
    Route::get('klaim/{id}', [KlaimController::class, 'show'])->whereNumber('id');
    Route::post('klaim/{id}/verifikasi', [KlaimController::class, 'verifikasi'])->whereNumber('id');
    Route::get('laporan/stok', [\App\Http\Controllers\Api\Admin\LaporanController::class, 'stok']);
    Route::get('laporan/stok', [\App\Http\Controllers\Api\Admin\LaporanController::class, 'stok']);
});

Route::prefix('kiosk')->group(function () {
    Route::get('katalog', [KatalogController::class, 'index']);
    Route::get('kategori', [KatalogController::class, 'kategori']);
    Route::get('divisi', [KatalogController::class, 'divisi']);
    Route::post('peminjaman', [PeminjamanController::class, 'store']);
    Route::post('peminjaman/cek', CekPeminjamanController::class);
    Route::post('peminjaman/batal', PembatalanController::class);      
    Route::post('peminjaman', [PeminjamanController::class, 'store'])->middleware('throttle:10,1');
    Route::post('pengembalian', PengembalianController::class)->middleware('throttle:10,1');
});   

Route::get('health', function () {
    try {
        DB::select('select 1');
        $db = true;
    } catch (\Throwable) {
        $db = false;
    }

    return response()->json([
        'status' => $db ? 'ok' : 'error',
        'db'     => $db,
    ], $db ? 200 : 503);
});