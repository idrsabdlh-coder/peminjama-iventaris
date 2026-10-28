<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Tanggal
{
    public const TIMEZONE = 'Asia/Jakarta';

    /**
     * Mengembalikan tanggal hari ini dalam format Y-m-d pada zona waktu Asia/Jakarta.
     */
    public static function hariIni(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }

    /**
     * Mengembalikan objek Carbon sekarang pada zona waktu Asia/Jakarta.
     */
    public static function sekarang(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * Parsing tanggal ke objek Carbon pada zona waktu Asia/Jakarta di awal hari.
     */
    public static function keAwalHari(string|\DateTimeInterface $tanggal): Carbon
    {
        return Carbon::parse($tanggal, self::TIMEZONE)->startOfDay();
    }

    /**
     * Menghitung keterlambatan (hari) dari batas kembali terhadap hari ini.
     * Mengembalikan integer >= 0.
     */
    public static function hitungKeterlambatan(string|\DateTimeInterface $batasKembali): int
    {
        $hariIni = self::sekarang()->startOfDay();
        $batas = self::keAwalHari($batasKembali);

        $selisih = (int) round($batas->diffInDays($hariIni, false));

        return max(0, $selisih);
    }
}
