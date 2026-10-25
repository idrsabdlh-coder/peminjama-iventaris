<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class MasterController extends Controller
{
    abstract protected function tabel(): string;

    /** Awalan untuk entitas dan aksi di audit_logs, mis. "kategori" → kategori_tambah. */
    abstract protected function entitas(): string;

    abstract protected function label(): string;

    /** @return array{0: string, 1: string, 2: string} [tabel pemakai, kolom FK, kata benda] */
    abstract protected function pemakai(): array;

    public function index(): JsonResponse
    {
        $data = $this->dasar()
            ->orderBy($this->tabel().'.nama')
            ->get()
            ->map(fn ($r) => $this->format($r));

        return response()->json(['data' => $data]);
    }

    public function show(int $id): JsonResponse
    {
        $r = $this->dasar()->where($this->tabel().'.id', $id)->first();

        abort_if(! $r, 404, $this->label().' tidak ditemukan.');

        return response()->json(['data' => $this->format($r)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->rapikan($request);

        $d = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique($this->tabel(), 'nama')],
            'aktif' => ['sometimes', 'boolean'],
        ], $this->pesan());

        $adminId = $request->user()->id;

        $id = DB::transaction(function () use ($d, $adminId) {
            $id = DB::table($this->tabel())->insertGetId([
                'nama' => $d['nama'],
                'aktif' => (bool) ($d['aktif'] ?? true),
            ]);

            $this->audit($adminId, 'tambah', $id, ['nama' => $d['nama']]);

            return $id;
        });

        $r = $this->dasar()->where($this->tabel().'.id', $id)->first();

        return response()->json(['data' => $this->format($r)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->rapikan($request);

        $d = $request->validate([
            'nama' => ['sometimes', 'required', 'string', 'max:100', Rule::unique($this->tabel(), 'nama')->ignore($id)],
            'aktif' => ['sometimes', 'boolean'],
        ], $this->pesan());

        $adminId = $request->user()->id;

        DB::transaction(function () use ($d, $id, $adminId) {
            $lama = DB::table($this->tabel())->where('id', $id)->lockForUpdate()->first();

            abort_if(! $lama, 404, $this->label().' tidak ditemukan.');

            $ubah = [];

            if (array_key_exists('nama', $d) && $d['nama'] !== $lama->nama) {
                $ubah['nama'] = $d['nama'];
            }

            if (array_key_exists('aktif', $d) && (bool) $d['aktif'] !== (bool) $lama->aktif) {
                $ubah['aktif'] = (bool) $d['aktif'];
            }

            if ($ubah === []) {
                return;
            }

            DB::table($this->tabel())->where('id', $id)->update($ubah);

            $this->audit($adminId, 'ubah', $id, [
                'sebelum' => array_intersect_key((array) $lama, $ubah),
                'sesudah' => $ubah,
            ]);
        });

        $r = $this->dasar()->where($this->tabel().'.id', $id)->first();

        return response()->json(['data' => $this->format($r)]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $adminId = $request->user()->id;

        DB::transaction(function () use ($id, $adminId) {
            $lama = DB::table($this->tabel())->where('id', $id)->lockForUpdate()->first();

            abort_if(! $lama, 404, $this->label().' tidak ditemukan.');

            [$tabelPemakai, $kolom, $kata] = $this->pemakai();
            $dipakai = DB::table($tabelPemakai)->where($kolom, $id)->count();

            if ($dipakai > 0) {
                throw ValidationException::withMessages([
                    $this->entitas() => $this->label()." masih dipakai oleh {$dipakai} {$kata}. Nonaktifkan saja supaya tidak muncul di pilihan.",
                ]);
            }

            DB::table($this->tabel())->where('id', $id)->delete();

            $this->audit($adminId, 'hapus', $id, ['nama' => $lama->nama]);
        });

        return response()->noContent();
    }

    private function dasar()
    {
        $t = $this->tabel();
        [$pt, $pk] = $this->pemakai();

        return DB::table($t)
            ->select("{$t}.id", "{$t}.nama", "{$t}.aktif")
            ->selectSub(
                DB::table($pt)->selectRaw('COUNT(*)')->whereColumn("{$pt}.{$pk}", "{$t}.id"),
                'dipakai'
            );
    }

    private function format(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'nama' => $r->nama,
            'aktif' => (bool) $r->aktif,
            'dipakai' => (int) $r->dipakai,
        ];
    }

    private function rapikan(Request $request): void
    {
        if ($request->has('nama')) {
            $request->merge(['nama' => trim((string) $request->input('nama'))]);
        }
    }

    private function pesan(): array
    {
        return ['nama.unique' => $this->label().' dengan nama itu sudah ada.'];
    }

    private function audit(int $adminId, string $aksi, int $id, array $detail): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $adminId,
            'aksi' => $this->entitas().'_'.$aksi,
            'entitas' => $this->entitas(),
            'entitas_id' => $id,
            'detail' => json_encode($detail),
        ]);
    }
}