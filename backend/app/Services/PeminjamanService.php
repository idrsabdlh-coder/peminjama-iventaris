<?php

namespace App\Services;

use App\Support\KodeAcak;
use App\Support\Tanggal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PeminjamanService
{
    /**
     * Mencari id transaksi loan berdasarkan idempotency_key.
     */
    public function cariIdByKunci(string $kunci): ?int
    {
        $id = DB::table('loans')->where('idempotency_key', $kunci)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Membuat peminjaman secara atomik dan idempoten.
     * Mengembalikan id loan yang dibuat.
     */
    public function buat(array $data): int
    {
        // 1. Cek idempotensi awal jika kunci sudah pernah diproses
        if (!empty($data['idempotency_key'])) {
            $idAda = $this->cariIdByKunci($data['idempotency_key']);
            if ($idAda) {
                return $idAda;
            }
        }

        // Urutan kunci baris tetap (id naik) untuk mencegah deadlock (BR-02)
        $baris = collect($data['items'])->sortBy('item_id')->values();
        $ids = $baris->pluck('item_id')->all();

        return DB::transaction(function () use ($data, $baris, $ids) {
            // Cek ulang idempotensi di dalam transaksi
            if (!empty($data['idempotency_key'])) {
                $idAda = $this->cariIdByKunci($data['idempotency_key']);
                if ($idAda) {
                    return $idAda;
                }
            }

            // Kunci baris items dengan urutan id menaik
            $barang = DB::table('items')
                ->whereIn('id', $ids)
                ->whereNull('dihapus_pada')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Validasi keberadaan barang di database
            foreach ($baris as $b) {
                $item = $barang->get($b['item_id']);
                if (!$item) {
                    throw new HttpException(404, "Barang dengan ID {$b['item_id']} tidak ditemukan.");
                }
            }

            // Pengurangan stok bersyarat secara atomic di SQL:
            // UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :qty
            foreach ($baris as $b) {
                $itemId = (int) $b['item_id'];
                $qty = (int) $b['qty'];

                $terupdate = DB::update(
                    'UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :min_qty',
                    [
                        'qty' => $qty,
                        'id' => $itemId,
                        'min_qty' => $qty,
                    ]
                );

                if ($terupdate === 0) {
                    $item = $barang->get($itemId);
                    $nama = $item ? $item->nama : "Barang #{$itemId}";
                    $tersedia = $item ? $item->tersedia : 0;
                    throw new HttpException(409, "Stok {$nama} tidak mencukupi. Tersedia: {$tersedia}, diminta: {$qty}.");
                }
            }

            $tanggalPinjam = $data['tanggal_pinjam'] ?? Tanggal::hariIni();
            $batasKembali = $data['batas_kembali'] ?? date('Y-m-d', strtotime('+7 days'));

            $loanId = DB::table('loans')->insertGetId([
                'kode_pinjam' => KodeAcak::pinjam(),
                'nama_peminjam' => $data['nama_peminjam'],
                'division_id' => $data['division_id'],
                'tipe_peminjam' => $data['tipe_peminjam'],
                'telepon' => $data['telepon'] ?? null,
                'tanggal_pinjam' => $tanggalPinjam,
                'batas_kembali_awal' => $batasKembali,
                'batas_kembali' => $batasKembali,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($baris as $b) {
                $qty = (int) $b['qty'];

                DB::table('loan_items')->insert([
                    'loan_id' => $loanId,
                    'item_id' => $b['item_id'],
                    'qty_dipinjam' => $qty,
                ]);

                // FR-18: Pencatatan ke buku besar stock_movements
                DB::table('stock_movements')->insert([
                    'item_id' => $b['item_id'],
                    'jenis' => 'PINJAM',
                    'delta_total' => 0,
                    'delta_tersedia' => -$qty,
                    'delta_kondisi' => 0,
                    'ref_tipe' => 'loan',
                    'ref_id' => $loanId,
                    'user_id' => null,
                    'catatan' => 'Peminjaman inventaris',
                ]);
            }

            return $loanId;
        }, 3);
    }

    /**
     * Menampilkan data kartu untuk struk/kiosk berdasarkan loan id.
     */
    public function tampilkan(int $id): ?array
    {
        $kode = DB::table('loans')->where('id', $id)->value('kode_pinjam');

        return $kode ? $this->kartuByKode($kode) : null;
    }

    /**
     * Mengambil detail kartu peminjaman berdasarkan kode (tanpa menyertakan nomor telepon peminjam).
     */
    public function kartuByKode(string $kode): ?array
    {
        $loan = DB::table('loans')
            ->join('divisions', 'divisions.id', '=', 'loans.division_id')
            ->where('loans.kode_pinjam', $kode)
            ->first(['loans.*', 'divisions.nama as divisi_nama']);

        if (!$loan) {
            return null;
        }

        $items = DB::table('loan_items')
            ->join('items', 'items.id', '=', 'loan_items.item_id')
            ->where('loan_items.loan_id', $loan->id)
            ->orderBy('items.nama')
            ->get(['items.id as item_id', 'items.nama', 'loan_items.id as loan_item_id', 'loan_items.qty_dipinjam'])
            ->map(fn ($i) => [
                'item_id' => (int) $i->item_id,
                'loan_item_id' => (int) $i->loan_item_id,
                'nama' => $i->nama,
                'dipinjam' => (int) $i->qty_dipinjam,
            ])
            ->all();

        $statusPeminjaman = app(StatusPeminjaman::class);
        $status = $statusPeminjaman->hitungStatus((array) $loan);

        $hariIni = Tanggal::sekarang()->startOfDay();
        $batas = Tanggal::keAwalHari($loan->batas_kembali);
        $selisih = (int) round($hariIni->diffInDays($batas, false));

        return [
            'id' => (int) $loan->id,
            'kode_pinjam' => $loan->kode_pinjam,
            'nama_peminjam' => $loan->nama_peminjam,
            'divisi' => $loan->divisi_nama,
            'tipe_peminjam' => $loan->tipe_peminjam,
            'tanggal_pinjam' => $loan->tanggal_pinjam,
            'batas_kembali' => $loan->batas_kembali,
            'status' => $status,
            'pemesanan' => $status === 'dipesan',
            'boleh_dibatalkan' => $status === 'dipesan',
            'sisa_hari' => max(0, $selisih),
            'hari_terlambat' => max(0, -$selisih),
            'items' => $items,
        ];
    }
}
