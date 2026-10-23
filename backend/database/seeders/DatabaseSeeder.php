<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tidak membuat akun admin. Admin dibuat lewat `php artisan inventaris:buat-admin`.
        $this->call(MasterDataSeeder::class);
    }
}