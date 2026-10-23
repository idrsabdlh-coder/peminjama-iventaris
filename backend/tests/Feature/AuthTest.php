<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:5173']]);
        $this->withHeaders([
            'Referer' => 'http://localhost:5173/',
            'Accept' => 'application/json',
        ]);
    }

    // T-5.1
    public function test_rute_admin_tanpa_login_ditolak(): void
    {
        $this->getJson('/api/admin/ping')->assertUnauthorized();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_login_berhasil(): void
    {
        $user = User::factory()->create(['email' => 'admin@kantor.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@kantor.test',
            'password' => UserFactory::KATA_SANDI_UJI,
        ])->assertOk()->assertJsonPath('data.email', 'admin@kantor.test');

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->login_terakhir);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'aksi' => 'login']);
    }

    public function test_password_salah_ditolak(): void
    {
        User::factory()->create(['email' => 'admin@kantor.test']);

        $this->postJson('/api/auth/login', ['email' => 'admin@kantor.test', 'password' => 'salah-total'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        User::factory()->nonaktif()->create(['email' => 'nonaktif@kantor.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'nonaktif@kantor.test',
            'password' => UserFactory::KATA_SANDI_UJI,
        ])->assertUnprocessable()->assertJsonPath('errors.email.0', 'Email atau password salah.');
    }

    public function test_login_dibatasi_setelah_lima_kali_gagal(): void
    {
        User::factory()->create(['email' => 'admin@kantor.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@kantor.test', 'password' => 'salah'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'admin@kantor.test',
            'password' => UserFactory::KATA_SANDI_UJI,
        ])->assertStatus(429);
    }

    public function test_me_dan_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.password');

        $this->actingAs($user)->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest('web');
    }

    public function test_akun_dinonaktifkan_saat_sesi_berjalan_ditolak(): void
    {
        $user = User::factory()->nonaktif()->create();

        $this->actingAs($user)->getJson('/api/admin/ping')->assertForbidden();
    }

    public function test_ganti_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'password_lama' => 'bukan-password-lama',
            'password' => 'Sandi-Baru-98765',
            'password_confirmation' => 'Sandi-Baru-98765',
        ])->assertUnprocessable()->assertJsonValidationErrors('password_lama');

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'password_lama' => UserFactory::KATA_SANDI_UJI,
            'password' => 'Sandi-Baru-98765',
            'password_confirmation' => 'Sandi-Baru-98765',
        ])->assertOk();

        $this->assertTrue(Hash::check('Sandi-Baru-98765', $user->fresh()->password));
    }

    // U-05
    public function test_password_tersimpan_sebagai_hash(): void
    {
        $user = User::factory()->create();

        $this->assertNotSame(UserFactory::KATA_SANDI_UJI, $user->fresh()->password);
        $this->assertNotNull(password_get_info($user->fresh()->password)['algo']);
    }

    // BR-19
    public function test_konfigurasi_sesi(): void
    {
        $this->assertSame(15, (int) config('session.lifetime'), 'Isi SESSION_LIFETIME=15 di .env');
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('strict', config('session.same_site'), 'Isi SESSION_SAME_SITE=strict di .env');
    }
}