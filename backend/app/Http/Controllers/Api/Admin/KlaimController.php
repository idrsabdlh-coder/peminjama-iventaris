<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\VerifikasiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KlaimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status'      => ['nullable', 'in:menunggu,diverifikasi'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $hasil = DB::table('return_claims as c')
            ->join('loans as p', 'p.id', '=', 'c.loan_id')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('c.status', $s))
            ->orderByDesc('c.diklaim_pada')
            ->orderByDesc('c.id')
            ->select('c.id', 'c.kode_klaim', 'c.status', 'c.hari_terlambat', 'c.diklaim_pada',
                     'p.kode_pinjam', 'p.nama_peminjam')
            ->paginate($data['per_halaman'] ?? 15);

        return response()->json($hasil);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->detail($id)]);
    }

    public function verifikasi(Request $request, int $id, VerifikasiService $verifikasiService): JsonResponse
    {
        $d = $request->validate([
            'catatan_admin'       => ['nullable', 'string', 'max:500'],
            'baris'               => ['required', 'array', 'min:1'],
            'baris.*.id'          => ['required', 'integer', 'distinct'],
            'baris.*.qty_baik'    => ['required', 'integer', 'min:0'],
            'baris.*.qty_rusak'   => ['required', 'integer', 'min:0'],
            'baris.*.qty_hilang'  => ['required', 'integer', 'min:0'],
            'baris.*.qty_ditolak' => ['required', 'integer', 'min:0'],
        ]);

        $adminId = $request->user()->id;
        $verifikasiService->verifikasi($id, $d, $adminId);

        return response()->json(['data' => $this->detail($id)]);
    }

        return response()->json(['data' => $this->detail($id)]);
    }

    private function detail(int $id): array
    {
        $k = DB::table('return_claims as c')
            ->join('loans as p', 'p.id', '=', 'c.loan_id')
            ->join('divisions as d', 'd.id', '=', 'p.division_id')
            ->where('c.id', $id)
            ->select('c.*', 'p.kode_pinjam', 'p.nama_peminjam', 'p.telepon', 'd.nama as divisi_nama')
            ->first();

        abort_if(! $k, 404, 'Klaim tidak ditemukan.');

        $baris = DB::table('return_claim_lines as l')
            ->join('loan_items as li', 'li.id', '=', 'l.loan_item_id')
            ->join('items as i', 'i.id', '=', 'li.item_id')
            ->where('l.claim_id', $id)
            ->select('l.id', 'i.nama as barang', 'li.qty_dipinjam', 'l.kondisi_dilaporkan', 'l.qty',
                     'l.qty_baik', 'l.qty_rusak', 'l.qty_hilang', 'l.qty_ditolak')
            ->get();

        return ['klaim' => $k, 'baris' => $baris];
    }
}
