<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class MasterDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now('Asia/Jakarta');

        // 1. Akun Admin Pertama (FR-A-01, FR-A-02)
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@it.local'],
            [
                'name' => 'Petugas IT Inventaris',
                'password' => Hash::make('password123'),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        // 2. Data Master Divisi Aktif (BR-27)
        $divisidata = [
            ['nama' => 'IT Support', 'is_active' => true],
            ['nama' => 'Software Engineering', 'is_active' => true],
            ['nama' => 'HRD & GA', 'is_active' => true],
            ['nama' => 'Keuangan', 'is_active' => true],
            ['nama' => 'Redaksi / Konten', 'is_active' => true],
            ['nama' => 'Pemasaran', 'is_active' => true],
        ];

        foreach ($divisidata as $divisi) {
            DB::table('divisis')->updateOrInsert(
                ['nama' => $divisi['nama']],
                [
                    'is_active' => $divisi['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 3. Data Master Kategori Barang (BR-28)
        $kategoriData = [
            ['nama' => 'Laptop / Komputer', 'is_active' => true],
            ['nama' => 'Monitor & Display', 'is_active' => true],
            ['nama' => 'Aksesori & Periferal', 'is_active' => true],
            ['nama' => 'Jaringan & Kabel', 'is_active' => true],
            ['nama' => 'Proyektor & Presentasi', 'is_active' => true],
        ];

        foreach ($kategoriData as $kategori) {
            DB::table('kategoris')->updateOrInsert(
                ['nama' => $kategori['nama']],
                [
                    'is_active' => $kategori['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}