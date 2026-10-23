<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeminjamanService
{
    // BR-17: 31 karakter tidak ambigu (tanpa I, L, O; angka 2-9).
    private const ALFABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function cariIdByKunci(string $kunci): ?int
    {
        $id = DB::table('loans')->where('idempotency_key', $kunci)->value('id');

        return $id ? (int) $id : null;
    }

    /** Membuat peminjaman atau pemesanan secara atomik. Mengembalikan id loan. */
    public function buat(array $data): int
    {
        // Urutan kunci baris tetap (id naik) agar tidak saling kunci (deadlock).
        $baris = collect($data['items'])->sortBy('item_id')->values();
        $ids = $baris->pluck('item_id')->all();

        return DB::transaction(function () use ($data, $baris, $ids) {
            $barang = DB::table('items')
                ->whereIn('id', $ids)
                ->whereNull('dihapus_pada')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // BR-02: validasi stok di server, setelah baris dikunci.
            foreach ($baris as $b) {
                $item = $barang->get($b['item_id']);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada barang yang tidak ditemukan di katalog. Muat ulang halaman lalu coba lagi.',
                    ]);
                }

                if ($item->tersedia < $b['qty']) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$item->nama} tidak cukup. Tersedia {$item->tersedia}, diminta {$b['qty']}.",
                    ]);
                }
            }

            $loanId = DB::table('loans')->insertGetId([
                'kode_pinjam' => $this->kodeBaru(),
                'nama_peminjam' => $data['nama_peminjam'],
                'division_id' => $data['division_id'],
                'tipe_peminjam' => $data['tipe_peminjam'],
                'telepon' => $data['telepon'] ?? null,
                'tanggal_pinjam' => $data['tanggal_pinjam'],
                'batas_kembali_awal' => $data['batas_kembali'],
                'batas_kembali' => $data['batas_kembali'],
                'idempotency_key' => $data['idempotency_key'],
            ]);

            foreach ($baris as $b) {
                $qty = (int) $b['qty'];

                DB::table('loan_items')->insert([
                    'loan_id' => $loanId,
                    'item_id' => $b['item_id'],
                    'qty_dipinjam' => $qty,
                ]);

                DB::table('items')->where('id', $b['item_id'])->decrement('tersedia', $qty);

                // FR-S-02: setiap perubahan stok tercatat di buku besar.
                DB::table('stock_movements')->insert([
                    'item_id' => $b['item_id'],
                    'jenis' => 'PINJAM',
                    'delta_total' => 0,
                    'delta_tersedia' => -$qty,
                    'delta_rusak' => 0,
                    'ref_tipe' => 'loan',
                    'ref_id' => $loanId,
                    'user_id' => null,
                ]);
            }

            return $loanId;
        }, 3); // diulang otomatis bila terjadi deadlock
    }

    /** Data untuk struk dan respons kiosk. Telepon sengaja tidak disertakan (BR-18). */
    public function tampilkan(int $id): array
    {
        $loan = DB::table('loans')
            ->join('divisions', 'divisions.id', '=', 'loans.division_id')
            ->where('loans.id', $id)
            ->first(['loans.*', 'divisions.nama as divisi_nama']);

        $items = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', $id)
            ->orderBy('items.nama')
            ->get(['items.nama', 'loan_items.qty_dipinjam'])
            ->map(fn ($i) => ['nama' => $i->nama, 'qty' => (int) $i->qty_dipinjam])
            ->all();

        $pemesanan = $loan->tanggal_pinjam > Carbon::today('Asia/Jakarta')->toDateString();

        return [
            'kode_pinjam' => $loan->kode_pinjam,
            'nama_peminjam' => $loan->nama_peminjam,
            'divisi' => $loan->divisi_nama,
            'tipe_peminjam' => $loan->tipe_peminjam,
            'tanggal_pinjam' => $loan->tanggal_pinjam,
            'batas_kembali' => $loan->batas_kembali,
            'pemesanan' => $pemesanan,
            'status' => $pemesanan ? 'dipesan' : 'dipinjam',
            'items' => $items,
        ];
    }

    private function kodeBaru(): string
    {
        do {
            $acak = '';
            for ($i = 0; $i < 6; $i++) {
                $acak .= self::ALFABET[random_int(0, strlen(self::ALFABET) - 1)];
            }
            $kode = 'PJM-'.$acak;
        } while (DB::table('loans')->where('kode_pinjam', $kode)->exists());

        return $kode;
    }
}