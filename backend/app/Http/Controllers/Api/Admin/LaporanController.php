<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function stok(Request $request): JsonResponse
    {
        $d = $request->validate([
            'kategori_id' => ['nullable', 'integer', 'min:1'],
            'rincian'     => ['nullable', 'boolean'],
        ]);

        $kategori = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.kategori_id')
            ->whereNull('items.dihapus_pada')
            ->when($d['kategori_id'] ?? null, fn ($q, $id) => $q->where('items.kategori_id', $id))
            ->groupBy('categories.id', 'categories.nama')
            ->orderBy('categories.nama')
            ->selectRaw('categories.id, categories.nama,
                         COUNT(items.id) AS jumlah_barang,
                         SUM(items.total) AS total,
                         SUM(items.tersedia) AS tersedia,
                         SUM(items.kondisi) AS kondisi')
            ->get()
            ->map(fn ($k) => [
                'id'            => $k->id,
                'nama'          => $k->nama,
                'jumlah_barang' => (int) $k->jumlah_barang,
                'total'         => (int) $k->total,
                'tersedia'      => (int) $k->tersedia,
                'kondisi'       => (int) $k->kondisi,
                'di_luar'       => (int) $k->total - (int) $k->tersedia - (int) $k->kondisi,
            ])
            ->values();

        if ($request->boolean('rincian')) {
            $barang = DB::table('items')
                ->whereNull('dihapus_pada')
                ->when($d['kategori_id'] ?? null, fn ($q, $id) => $q->where('kategori_id', $id))
                ->orderBy('nama')
                ->get(['id', 'nama', 'tipe', 'kategori_id', 'total', 'tersedia', 'kondisi'])
                ->groupBy('kategori_id');

            $kategori = $kategori->map(function ($k) use ($barang) {
                $k['barang'] = ($barang[$k['id']] ?? collect())->map(fn ($b) => [
                    'id'       => $b->id,
                    'nama'     => $b->nama,
                    'tipe'     => $b->tipe,
                    'total'    => (int) $b->total,
                    'tersedia' => (int) $b->tersedia,
                    'kondisi'  => (int) $b->kondisi,
                    'di_luar'  => (int) $b->total - (int) $b->tersedia - (int) $b->kondisi,
                ])->values();

                return $k;
            });
        }

        return response()->json([
            'data' => [
                'ringkasan' => [
                    'total'    => $kategori->sum('total'),
                    'tersedia' => $kategori->sum('tersedia'),
                    'kondisi'  => $kategori->sum('kondisi'),
                    'di_luar'  => $kategori->sum('di_luar'),
                ],
                'kategori' => $kategori,
            ],
        ]);
    }
}
