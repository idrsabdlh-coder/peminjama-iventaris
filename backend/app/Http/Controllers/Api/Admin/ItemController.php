<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ItemStoreRequest;
use App\Http\Requests\Admin\ItemUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cari'        => ['nullable', 'string', 'max:100'],
            'kategori_id' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $cari = $data['cari'] ?? null;
        $like = $cari !== null
            ? '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari).'%'
            : null;

        $hasil = $this->dasar()
            ->when($like, fn ($q) => $q->where('items.nama', 'like', $like))
            ->when($data['kategori_id'] ?? null, fn ($q, $id) => $q->where('items.kategori_id', $id))
            ->orderBy('items.nama')
            ->paginate($data['per_halaman'] ?? 15)
            ->through(fn ($b) => $this->format($b));

        return response()->json($hasil);
    }

    public function show(int $id): JsonResponse
    {
        $barang = $this->dasar()->where('items.id', $id)->first();

        abort_if(! $barang, 404, 'Barang tidak ditemukan.');

        return response()->json(['data' => $this->format($barang)]);
    }

    public function store(ItemStoreRequest $request): JsonResponse
    {
        $d       = $request->validated();
        $adminId = $request->user()->id;

        $id = DB::transaction(function () use ($d, $adminId) {
            $id = DB::table('items')->insertGetId([
                'nama'              => $d['nama'],
                'tipe'              => $d['tipe'] ?? null,
                'deskripsi'         => $d['deskripsi'] ?? null,
                'kategori_id'       => $d['kategori_id'],
                'total'             => $d['total'],
                'tersedia'          => $d['total'],
                'kondisi'           => 0,
                'boleh_diperpanjang'=> $d['boleh_diperpanjang'] ?? true,
            ]);

            if ((int) $d['total'] > 0) {
                $this->catatStok($id, 'TAMBAH_STOK', (int) $d['total'], $adminId, 'Stok awal');
            }

            $this->catatAudit($adminId, 'item_tambah', $id, [
                'nama'  => $d['nama'],
                'tipe'  => $d['tipe'] ?? null,
                'total' => (int) $d['total'],
            ]);

            return $id;
        });

        $barang = $this->dasar()->where('items.id', $id)->first();

        return response()->json(['data' => $this->format($barang)], 201);
    }

    public function update(ItemUpdateRequest $request, int $id): JsonResponse
    {
        $d       = $request->validated();
        $adminId = $request->user()->id;

        DB::transaction(function () use ($d, $id, $adminId) {
            $lama = DB::table('items')
                ->where('id', $id)
                ->whereNull('dihapus_pada')
                ->lockForUpdate()
                ->first();

            abort_if(! $lama, 404, 'Barang tidak ditemukan.');

            $ubah  = Arr::only($d, ['nama', 'tipe', 'deskripsi', 'kategori_id', 'boleh_diperpanjang']);
            $delta = 0;

            if (array_key_exists('total', $d)) {
                $delta = (int) $d['total'] - (int) $lama->total;

                if ((int) $lama->tersedia + $delta < 0) {
                    $minimal = (int) $lama->total - (int) $lama->tersedia;

                    throw ValidationException::withMessages([
                        'total' => "Total tidak boleh kurang dari {$minimal}, karena sebagian unit sedang dipinjam atau rusak.",
                    ]);
                }

                if ($delta !== 0) {
                    $ubah['total']    = (int) $d['total'];
                    $ubah['tersedia'] = (int) $lama->tersedia + $delta;
                }
            }

            if ($ubah === []) {
                return;
            }

            DB::table('items')->where('id', $id)->update($ubah);

            if ($delta !== 0) {
                $this->catatStok($id, $delta > 0 ? 'TAMBAH_STOK' : 'KURANG_STOK', $delta, $adminId, 'Penyesuaian total oleh admin');
            }

            $this->catatAudit($adminId, 'item_ubah', $id, [
                'sebelum' => Arr::only((array) $lama, array_keys($ubah)),
                'sesudah' => $ubah,
            ]);
        });

        $barang = $this->dasar()->where('items.id', $id)->first();

        return response()->json(['data' => $this->format($barang)]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $adminId = $request->user()->id;

        DB::transaction(function () use ($id, $adminId) {
            $barang = DB::table('items')
                ->where('id', $id)
                ->whereNull('dihapus_pada')
                ->lockForUpdate()
                ->first();

            abort_if(! $barang, 404, 'Barang tidak ditemukan.');

            $diLuar = (int) $barang->total - (int) $barang->tersedia - (int) $barang->kondisi;

            if ($diLuar > 0) {
                throw ValidationException::withMessages([
                    'barang' => "Barang masih dipakai: {$diLuar} unit sedang dipinjam, dipesan, atau menunggu verifikasi. Selesaikan dulu sebelum menghapus.",
                ]);
            }

            DB::table('items')->where('id', $id)->update(['dihapus_pada' => now()]);

            $this->catatAudit($adminId, 'item_hapus', $id, ['nama' => $barang->nama]);
        });

        return response()->noContent();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function catatStok(int $itemId, string $jenis, int $delta, int $adminId, string $catatan): void
    {
        DB::table('stock_movements')->insert([
            'item_id'       => $itemId,
            'jenis'         => $jenis,
            'delta_total'   => $delta,
            'delta_tersedia'=> $delta,
            'delta_rusak'   => 0,
            'ref_tipe'      => 'item',
            'ref_id'        => $itemId,
            'user_id'       => $adminId,
            'catatan'       => $catatan,
        ]);
    }

    private function catatAudit(int $adminId, string $aksi, int $itemId, array $detail): void
    {
        DB::table('audit_logs')->insert([
            'user_id'    => $adminId,
            'aksi'       => $aksi,
            'entitas'    => 'item',
            'entitas_id' => $itemId,
            'detail'     => json_encode($detail),
        ]);
    }

    private function dasar()
    {
        return DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.kategori_id')
            ->whereNull('items.dihapus_pada')
            ->select([
                'items.id', 'items.nama', 'items.tipe', 'items.deskripsi', 'items.kategori_id',
                'categories.nama as kategori_nama',
                'items.total', 'items.tersedia', 'items.kondisi', 'items.boleh_diperpanjang',
            ]);
    }

    private function format(object $b): array
    {
        return [
            'id'               => $b->id,
            'nama'             => $b->nama,
            'tipe'             => $b->tipe,
            'deskripsi'        => $b->deskripsi,
            'kategori'         => ['id' => $b->kategori_id, 'nama' => $b->kategori_nama],
            'total'            => (int) $b->total,
            'tersedia'         => (int) $b->tersedia,
            'kondisi'          => (int) $b->kondisi,
            // Unit yang sedang di luar: dipinjam, dipesan, atau menunggu verifikasi pengembalian.
            'di_luar'          => (int) $b->total - (int) $b->tersedia - (int) $b->kondisi,
            'boleh_diperpanjang'=> (bool) $b->boleh_diperpanjang,
        ];
    }
}
