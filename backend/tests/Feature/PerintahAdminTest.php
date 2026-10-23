<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerintahAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_buat_admin_lewat_prompt(): void
    {
        $this->artisan('inventaris:buat-admin')
            ->expectsQuestion('Nama admin', 'Petugas IT')
            ->expectsQuestion('Email admin', 'petugas@kantor.test')
            ->expectsQuestion('Password (minimal 10 karakter)', 'Rahasia-Uji-123')
            ->expectsQuestion('Ulangi password', 'Rahasia-Uji-123')
            ->expectsOutputToContain('Admin dibuat')
            ->assertSuccessful();

        $user = User::where('email', 'petugas@kantor.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Rahasia-Uji-123', $user->password));
    }

    public function test_reset_password_menghentikan_sesi_lama(): void
    {
        $user = User::factory()->create(['email' => 'petugas@kantor.test']);
        DB::table('sessions')->insert([
            'id' => 'sesi-lama', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->artisan('inventaris:reset-password', ['email' => 'petugas@kantor.test'])
            ->expectsQuestion('Password baru (minimal 10 karakter)', 'Sandi-Reset-7777')
            ->expectsQuestion('Ulangi password baru', 'Sandi-Reset-7777')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('Sandi-Reset-7777', $user->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }
}