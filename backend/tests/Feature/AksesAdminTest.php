<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AksesAdminTest extends TestCase
{
    use RefreshDatabase;

    // G3: 100% rute admin menolak akses tanpa login
    public function test_semua_rute_admin_menolak_tanpa_login(): void
    {
        $diperiksa = 0;

        foreach (Route::getRoutes() as $rute) {
            $uri = $rute->uri();

            if (! str_starts_with($uri, 'api/admin/') || $uri === 'api/admin/login') {
                continue;
            }

            $uriUji = preg_replace('/\{[^}]+\}/', '1', $uri);

            foreach ($rute->methods() as $metode) {
                if (in_array($metode, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $status = $this->json($metode, '/' . $uriUji)->status();

                $this->assertSame(401, $status, "Rute {$metode} {$uri} bisa diakses tanpa login (status {$status})");
                $diperiksa++;
            }
        }

        $this->assertGreaterThan(10, $diperiksa);
    }
}