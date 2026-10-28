<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class KodeAcak
{
    // BR-17: 31 karakter alfabet tidak ambigu (tanpa I, L, O, 0, 1)
    public const ALFABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Menghasilkan string acak sepanjang $panjang dari alfabet baku.
     */
    public static function generate(int $panjang = 6): string
    {
        $hasil = '';
        $maks = strlen(self::ALFABET) - 1;

        for ($i = 0; $i < $panjang; $i++) {
            $hasil .= self::ALFABET[random_int(0, $maks)];
        }

        return $hasil;
    }

    /**
     * Menghasilkan kode unik peminjaman (format: PJM-XXXXXX).
     */
    public static function pinjam(): string
    {
        do {
            $kode = 'PJM-' . self::generate(6);
        } while (DB::table('loans')->where('kode_pinjam', $kode)->exists());

        return $kode;
    }

    /**
     * Menghasilkan kode unik klaim pengembalian (format: KMB-XXXXXX).
     */
    public static function klaim(): string
    {
        do {
            $kode = 'KMB-' . self::generate(6);
        } while (DB::table('return_claims')->where('kode_klaim', $kode)->exists());

        return $kode;
    }
}
