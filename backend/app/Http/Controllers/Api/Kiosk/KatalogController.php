<?php

namespace App\Http\Controllers\Api\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KatalogController extends Controller
{
    // FR-K-01: hanya nama dan jumlah tersedia. rusak/total/menunggu tidak dikirim ke kiosk.
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'kategori_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $cari = $data['cari'] ?? null;
        $like = $cari !== null
            ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari).'%'
            : null;

        $barang = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.kategori_id')
            ->whereNull('items.dihapus_pada')
            ->when($like, fn ($q) => $q->where('items.nama', 'like', $like))
            ->when($data['kategori_id'] ?? null, fn ($q, $id) => $q->where('items.kategori_id', $id))
            ->orderBy('items.nama')
            ->get([
                'items.id',
                'items.nama',
                'items.deskripsi',
                'items.kategori_id',
                'categories.nama as kategori_nama',
                'items.tersedia',
            ])
            ->map(fn ($b) => [
                'id' => $b->id,
                'nama' => $b->nama,
                'deskripsi' => $b->deskripsi,
                'kategori' => ['id' => $b->kategori_id, 'nama' => $b->kategori_nama],
                'tersedia' => (int) $b->tersedia,
                'boleh_dipilih' => $b->tersedia > 0,
            ]);

        return response()->json(['data' => $barang]);
    }

    // BR-31: filter memakai daftar kategori aktif.
    public function kategori(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('categories')->where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    // BR-27 / D-11: pilihan divisi di form hanya yang aktif.
    public function divisi(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('divisions')->where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
        ]);
    }
}