<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Division;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /** Aman dijalankan berulang: data yang sudah ada tidak ditimpa. */
    public function run(): void
    {
        foreach ([
            'IT Support', 'Software Engineering', 'HRD & GA',
            'Keuangan', 'Redaksi / Konten', 'Pemasaran',
        ] as $nama) {
            Division::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        foreach ([
            'Tanpa Kategori', 'Laptop / Komputer', 'Monitor & Display',
            'Aksesori & Periferal', 'Jaringan & Kabel', 'Proyektor & Presentasi',
        ] as $nama) {
            Category::firstOrCreate(['nama' => $nama], ['aktif' => true]);
        }

        foreach ([
            'maks_durasi_awal_hari' => 7,
            'maks_perpanjangan' => 2,
            'maks_hari_per_perpanjangan' => 7,
            'maks_booking_hari' => 7,
            'sesi_idle_menit' => 15,
            'kiosk_idle_detik' => 60,
            'kiosk_lookup_maks_gagal' => 5,
            'kiosk_lookup_kunci_menit' => 5,
        ] as $kunci => $nilai) {
            // Kembalikan ke 'kunci' dan 'nilai'
            Setting::firstOrCreate(['kunci' => $kunci], ['nilai' => (string) $nilai]);
        }
    }
}