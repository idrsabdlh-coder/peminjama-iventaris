<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function verifikasi(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'catatan_admin'         => ['nullable', 'string', 'max:500'],
            'baris'                 => ['required', 'array', 'min:1'],
            'baris.*.id'            => ['required', 'integer', 'distinct'],
            'baris.*.qty_baik'      => ['required', 'integer', 'min:0'],
            'baris.*.qty_rusak'     => ['required', 'integer', 'min:0'],
            'baris.*.qty_hilang'    => ['required', 'integer', 'min:0'],
            'baris.*.qty_ditolak'   => ['required', 'integer', 'min:0'],
        ]);

        $adminId = $request->user()->id;

        DB::transaction(function () use ($d, $id, $adminId) {
            $klaim = DB::table('return_claims')->where('id', $id)->lockForUpdate()->first();

            abort_if(! $klaim, 404, 'Klaim tidak ditemukan.');

            if ($klaim->status !== 'menunggu') {
                throw ValidationException::withMessages(['klaim' => 'Klaim ini sudah diverifikasi.']);
            }

            $baris = DB::table('return_claim_lines as l')
                ->join('loan_items as li', 'li.id', '=', 'l.loan_item_id')
                ->where('l.claim_id', $id)
                ->select('l.id', 'l.qty', 'li.item_id')
                ->get()->keyBy('id');

            $kirim = collect($d['baris'])->keyBy(fn ($b) => (int) $b['id']);

            $a = $baris->keys()->map(fn ($k) => (int) $k)->sort()->values()->all();
            $b = $kirim->keys()->sort()->values()->all();

            if ($a !== $b) {
                throw ValidationException::withMessages(['baris' => 'Semua baris klaim harus diverifikasi, tidak kurang dan tidak lebih.']);
            }

            $perItem = [];

            foreach ($baris as $lineId => $line) {
                $k = $kirim[(int) $lineId];

                $jumlah = $k['qty_baik'] + $k['qty_rusak'] + $k['qty_hilang'] + $k['qty_ditolak'];

                if ($jumlah !== (int) $line->qty) {
                    throw ValidationException::withMessages([
                        'baris' => "Baris #{$lineId}: jumlah keempat hasil ({$jumlah}) harus sama dengan qty klaim ({$line->qty}).",
                    ]);
                }

                DB::table('return_claim_lines')->where('id', $lineId)->update([
                    'qty_baik'    => $k['qty_baik'],
                    'qty_rusak'   => $k['qty_rusak'],
                    'qty_hilang'  => $k['qty_hilang'],
                    'qty_ditolak' => $k['qty_ditolak'],
                ]);

                $p = &$perItem[$line->item_id];
                $p['baik']   = ($p['baik'] ?? 0) + $k['qty_baik'];
                $p['rusak']  = ($p['rusak'] ?? 0) + $k['qty_rusak'];
                $p['hilang'] = ($p['hilang'] ?? 0) + $k['qty_hilang'];
                unset($p);
            }

            ksort($perItem); // urutan kunci tetap, mencegah deadlock antar admin

            foreach ($perItem as $itemId => $v) {
                DB::table('items')->where('id', $itemId)->lockForUpdate()->first();

                DB::table('items')->where('id', $itemId)->incrementEach([
                    'tersedia' => $v['baik'],
                    'rusak'    => $v['rusak'],
                    'total'    => -$v['hilang'],
                ]);

                $gerak = [
                    ['VERIFIKASI_BAIK',   0,              $v['baik'], 0,           $v['baik']],
                    ['VERIFIKASI_RUSAK',  0,              0,          $v['rusak'], $v['rusak']],
                    ['VERIFIKASI_HILANG', -$v['hilang'],  0,          0,           $v['hilang']],
                    ];
                

                foreach ($gerak as [$jenis, $dTotal, $dTersedia, $dRusak, $cek]) {
                    if ($cek === 0) {
                        continue;
                    }

                    DB::table('stock_movements')->insert([
                        'item_id'        => $itemId,
                        'jenis'          => $jenis,
                        'delta_total'    => $dTotal,
                        'delta_tersedia' => $dTersedia,
                        'delta_rusak'    => $dRusak,
                        'ref_tipe'       => 'klaim',
                        'ref_id'         => $id,
                        'user_id'        => $adminId,
                        'catatan'        => 'Verifikasi pengembalian '.$klaim->kode_klaim,
                    ]);
                }
            }

            DB::table('return_claims')->where('id', $id)->update([
                'status'            => 'diverifikasi',
                'diverifikasi_oleh' => $adminId,
                'diverifikasi_pada' => now(),
                'catatan_admin'     => $d['catatan_admin'] ?? null,
            ]);

            DB::table('audit_logs')->insert([
                'user_id'    => $adminId,
                'aksi'       => 'klaim_verifikasi',
                'entitas'    => 'klaim',
                'entitas_id' => $id,
                'detail'     => json_encode(['kode_klaim' => $klaim->kode_klaim, 'per_barang' => $perItem]),
            ]);
        });

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