# Phase 7 – Pengujian (PHPUnit)

## 7️⃣ Test Suite Overview
- **Feature Tests**: Menguji endpoint API (login, logout, me) dan command Artisan.
- **Unit Tests**: Menguji model‑model dan relasi Eloquent.
- **Database Refresh**: Setiap test menggunakan trait `RefreshDatabase` untuk migrasi bersih.

## 7.1 Feature Test – Auth Flow (`tests/Feature/AuthTest.php`)
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_login_and_receive_token()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('admin123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
        $this->assertNotNull($response->json('token'));
    }

    /** @test */
    public function authenticated_user_can_access_me_endpoint()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('admin123'),
        ]);
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin123',
        ])->json('token');

        $this->withHeaders(['Authorization' => "Bearer $token"]))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    /** @test */
    public function logout_invalidates_token()
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('admin123'),
        ]);
        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'admin123',
        ])->json('token');

        $this->withHeaders(['Authorization' => "Bearer $token"]))
            ->postJson('/api/logout')
            ->assertOk();

        // token should now be invalid
        $this->withHeaders(['Authorization' => "Bearer $token"]))
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}
```

## 7.2 Unit Test – Model Relations (`tests/Unit/ModelRelationsTest.php`)
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Division;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function division_has_many_items()
    {
        $division = Division::factory()->create();
        $item = Item::factory()->create(['division_id' => $division->id]);

        $this->assertTrue($division->items->contains($item));
    }

    /** @test */
    public function category_has_many_items()
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($category->items->contains($item));
    }

    /** @test */
    public function loan_belongs_to_user_and_has_many_items()
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['user_id' => $user->id]);
        $item = Item::factory()->create();
        $loan->items()->attach($item->id);

        $this->assertEquals($user->id, $loan->user->id);
        $this->assertTrue($loan->items->contains($item));
    }
}
```

## 7.3 Artisan Command Tests (`tests/Feature/CommandTest.php`)
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_or_updates_admin_user()
    {
        $this->artisan('inventaris:buat-admin --email=admin@example.com --name=admin')
             ->expectsOutput('Admin user created with default password (admin123).')
             ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'name' => 'admin']);
    }
}
```

## 7.4 Running the Tests
```bash
php artisan test --parallel
```
Or use PHPUnit directly:
```bash
vendor/bin/phpunit
```
All tests should pass on a fresh SQLite or MySQL testing database.

---

**Catatan:** Pastikan factory‑factory untuk masing‑masing model sudah didefinisikan (`database/factories`). Jika belum, buat quick factories dengan `php artisan make:factory ModelNameFactory --model=ModelName`.

