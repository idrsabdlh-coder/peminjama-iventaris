<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Skema
{
    public static function pastikanMysql(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Skema ini butuh MySQL 8.0.16+. Driver sekarang: {$driver}.");
        }

        if ($driver === 'mysql') {
            $versi = (string) DB::selectOne('SELECT VERSION() AS v')->v;
            $angka = preg_replace('/[^0-9.].*$/', '', $versi);

            if (version_compare($angka, '8.0.16', '<')) {
                throw new RuntimeException("MySQL {$versi} tidak menegakkan CHECK. Pakai 8.0.16 atau lebih baru.");
            }
        }
    }

    public static function check(string $tabel, string $nama, string $ekspresi): void
    {
        DB::statement("ALTER TABLE `{$tabel}` ADD CONSTRAINT `{$nama}` CHECK ({$ekspresi})");
    }

    /**
     * Hapus CHECK constraint berdasarkan nama.
     * Dipakai di migration down() saat perlu mengganti nama kolom yang menjadi bagian constraint.
     */
    public static function dropCheck(string $tabel, string $nama): void
    {
        DB::statement("ALTER TABLE `{$tabel}` DROP CHECK `{$nama}`");
    }
}
