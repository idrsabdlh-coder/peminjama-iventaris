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
                         SUM(items.rusak) AS rusak')
            ->get()
            ->map(fn ($k) => [
                'id'            => $k->id,
                'nama'          => $k->nama,
                'jumlah_barang' => (int) $k->jumlah_barang,
                'total'         => (int) $k->total,
                'tersedia'      => (int) $k->tersedia,
                'rusak'         => (int) $k->rusak,
                'di_luar'       => (int) $k->total - (int) $k->tersedia - (int) $k->rusak,
            ])
            ->values();

        if ($request->boolean('rincian')) {
            $barang = DB::table('items')
                ->whereNull('dihapus_pada')
                ->when($d['kategori_id'] ?? null, fn ($q, $id) => $q->where('kategori_id', $id))
                ->orderBy('nama')
                ->get(['id', 'nama', 'kategori_id', 'total', 'tersedia', 'rusak'])
                ->groupBy('kategori_id');

            $kategori = $kategori->map(function ($k) use ($barang) {
                $k['barang'] = ($barang[$k['id']] ?? collect())->map(fn ($b) => [
                    'id'       => $b->id,
                    'nama'     => $b->nama,
                    'total'    => (int) $b->total,
                    'tersedia' => (int) $b->tersedia,
                    'rusak'    => (int) $b->rusak,
                    'di_luar'  => (int) $b->total - (int) $b->tersedia - (int) $b->rusak,
                ])->values();

                return $k;
            });
        }

        return response()->json([
            'data' => [
                'ringkasan' => [
                    'total'    => $kategori->sum('total'),
                    'tersedia' => $kategori->sum('tersedia'),
                    'rusak'    => $kategori->sum('rusak'),
                    'di_luar'  => $kategori->sum('di_luar'),
                ],
                'kategori' => $kategori,
            ],
        ]);
    }
}