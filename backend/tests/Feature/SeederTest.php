<?php

namespace Tests\Feature;

use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_mengisi_master_data_dan_aman_diulang(): void
    {
        $this->seed(MasterDataSeeder::class);
        $this->seed(MasterDataSeeder::class);

        $this->assertSame(6, DB::table('divisions')->count());
        $this->assertSame(6, DB::table('categories')->count());
        $this->assertSame(8, DB::table('settings')->count());
        $this->assertDatabaseHas('categories', ['nama' => 'Tanpa Kategori']);
        $this->assertDatabaseHas('settings', ['kunci' => 'sesi_idle_menit', 'nilai' => '15']);
    }

    // G3 dan FR-20: tidak ada kredensial di repo
    public function test_seeder_tidak_membuat_akun_admin(): void
    {
        $this->seed();

        $this->assertSame(0, DB::table('users')->count());
    }
}