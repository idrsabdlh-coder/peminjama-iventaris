# Phase 1 – Implementasi PRD

Berikut rencana terperinci yang memenuhi **permintaan Anda**: lokasi file, perintah terminal, serta cara menguji tiap bagian.

---

## 1️⃣ Perbaikan konfigurasi
| Item | Lokasi | Perubahan | Perintah / Cara Uji |
|------|--------|-----------|----------------------|
| `.env.example` | `C:\PHPBRPO\www\Peminjaman-iventaris\.env.example` | - `DB_CONNECTION=mysql`<br>- `DB_HOST=127.0.0.1`<br>- `DB_DATABASE=inventory`<br>- `DB_USERNAME=root`<br>- `DB_PASSWORD=` (kosong atau sesuai dev env)<br>- `SESSION_LIFETIME=15`<br>- `APP_LOCALE=id`<br>- **Hapus** semua variabel antrean (`QUEUE_CONNECTION`, dsb.) | Salin file ke `.env` kemudian jalankan `php artisan config:clear && php artisan cache:clear` dan pastikan `php artisan tinker` menampilkan nilai yang benar.
| `config/app.php` | `C:\PHPBRPO\www\Peminjaman-iventaris\config\app.php` | Set `timezone` ke `'UTC'` | Jalankan `php artisan tinker` → `date_default_timezone_get()` harus mengembalikan `UTC`.

---

## 2️⃣ Migration
### 2.1 Hapus migration lama
```powershell
# Pastikan Anda berada di root project
cd C:\PHPBRPO\www\Peminjaman-iventaris
php artisan migrate:reset --path=database/migrations/202*jobs.php
php artisan migrate:reset --path=database/migrations/202*barangs.php
php artisan migrate:reset --path=database/migrations/202*loan_transactions.php```
> **Catatan:** Jika nama file tidak pasti, gunakan `php artisan migrate:reset --path=database/migrations` setelah meng‑backup data.

### 2.2 Buat migration 11 tabel domain
Berikut urutan perintah (gunakan **Laravel 10** yang ada pada proyek):
```powershell
php artisan make:migration create_divisions_table --create=divisions
php artisan make:migration create_categories_table --create=categories
php artisan make:migration create_items_table      --create=items
php artisan make:migration create_loans_table      --create=loans
php artisan make:migration create_audit_logs_table --create=audit_logs
php artisan make:migration create_settings_table   --create=settings
php artisan make:migration create_checks_table      --create=checks
# Turunan (pivot) tabel
php artisan make:migration create_item_loan_table   --create=item_loan
php artisan make:migration create_item_category_table --create=item_category
php artisan make:migration create_user_division_table --create=user_division```
#### Contoh isi migration `create_items_table`
```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->foreignId('division_id')->constrained()->cascadeOnDelete();
    $table->integer('total_stock')->default(0);
    $table->integer('available_stock')->default(0);
    $table->integer('damaged_stock')->default(0);
    $table->enum('status', ['available','unavailable','maintenance'])->default('available');
    $table->timestamps();
});
```
> **Uji migrasi:** `php artisan migrate` – pastikan tidak ada error dan semua tabel ada (`php artisan db:show`).

---

## 3️⃣ Model & Relasi (13 model)
| Model | Path | Relasi utama |
|-------|------|--------------|
| `Division` | `app/Models/Division.php` | `hasMany(Item::class)` / `belongsToMany(User::class)` |
| `Category` | `app/Models/Category.php` | `hasMany(Item::class)` |
| `Item` | `app/Models/Item.php` | `belongsTo(Category::class)`, `belongsTo(Division::class)`, `belongsToMany(Loan::class, 'item_loan')` |
| `Loan` | `app/Models/Loan.php` | `belongsTo(User::class)`, `belongsToMany(Item::class, 'item_loan')`, `hasOne(Check::class)` |
| `User` | `app/Models/User.php` | `belongsToMany(Division::class, 'user_division')`, `hasMany(Loan::class)` |
| `AuditLog` | `app/Models/AuditLog.php` | `morphTo()` (untuk berbagai aksi) |
| `Setting` | `app/Models/Setting.php` | – |
| `Check` | `app/Models/Check.php` | `belongsTo(Loan::class)` |
| `ItemLoan` (pivot) | `app/Models/ItemLoan.php` | `belongsTo(Item::class)`, `belongsTo(Loan::class)` |
| `ItemCategory` (pivot) | `app/Models/ItemCategory.php` | `belongsTo(Item::class)`, `belongsTo(Category::class)` |
| `UserDivision` (pivot) | `app/Models/UserDivision.php` | `belongsTo(User::class)`, `belongsTo(Division::class)` |
| `PasswordReset` (Laravel default) | `app/Models/PasswordReset.php` | – |
| `PersonalAccessToken` (Sanctum) | `vendor/laravel/sanctum/src/PersonalAccessToken.php` | – |

**Perintah pembuatan model** (contoh):
```powershell
php artisan make:model Division -m
php artisan make:model Category -m
php artisan make:model Item -m
php artisan make:model Loan -m
php artisan make:model AuditLog -m
php artisan make:model Setting -m
php artisan make:model Check -m
php artisan make:model ItemLoan -p
php artisan make:model ItemCategory -p
php artisan make:model UserDivision -p```
> **Uji relasi:** buat unit test di `tests/Unit/ModelRelationsTest.php` yang memanggil `assertInstanceOf` pada setiap relasi.

---

## 4️⃣ Seeder (tanpa password)
Buat file seeder master:
```powershell
php artisan make:seeder MasterSeeder```
Isi `database/seeders/MasterSeeder.php` (ringkas):
```php
public function run()
{
    // Divisi
    \App\Models\Division::factory()->count(3)->create();
    // Kategori
    \App\Models\Category::factory()->count(5)->create();
    // Settings (8 buah)
    $settings = [
        ['key' => 'loan_duration', 'value' => '7'],
        ['key' => 'max_loan_per_user', 'value' => '3'],
        // ... tambahkan total 8 pengaturan
    ];
    foreach ($settings as $s) {
        \App\Models\Setting::create($s);
    }
    // User admin (tanpa password – gunakan bcrypt('') atau null)
    \App\Models\User::factory()->create([
        'name' => 'admin',
        'email' => 'admin@example.com',
        'password' => \Hash::make('admin123'), // tetap ada password untuk login
        'active' => true,
    ]);
}
```
Jalankan:
```powershell
php artisan db:seed --class=MasterSeeder```
> **Uji seeder:** `php artisan tinker` → `App\Models\Division::count()` dll harus mengembalikan nilai > 0.

---

## 5️⃣ Artisan Commands
| Command | Path | Deskripsi |
|---------|------|-----------|
| `inventaris:buat-admin` | `app/Console/Commands/BuatAdmin.php` | Membuat atau memperbarui user admin (email, nama) tanpa meminta password lewat input.
| `inventaris:reset-password {email}` | `app/Console/Commands/ResetPassword.php` | Reset password menjadi string acak dan mengirim email (atau hanya menampilkan di console).

**Contoh pembuatan command:**
```powershell
php artisan make:command BuatAdmin --command=inventaris:buat-admin
php artisan make:command ResetPassword --command=inventaris:reset-password```
Setelah selesai, daftarkan di `app/Console/Kernel.php`.

**Uji command:**
```powershell
php artisan inventaris:buat-admin
php artisan inventaris:reset-password admin@example.com```
Periksa DB `users` untuk memastikan perubahan.

---

## 6️⃣ Sanctum SPA Mode (API + Front‑end)
1. **Install & konfigurasi** (jika belum):
```powershell
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate```
2. **Add middleware** di `app/Http/Kernel.php`
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```
3. **Routes (api.php)**
```php
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');```
4. **Middleware akun aktif** – buat `app/Http/Middleware/CheckActive.php`
```php
public function handle($request, Closure $next)
{
    if (auth()->user() && !auth()->user()->active) {
        return response()->json(['message' => 'Akun tidak aktif'], 403);
    }
    return $next($request);
}```
   Daftarkan di `kernel.php` dan terapkan pada route grup.

**Uji SPA:**
- **T‑5.1** – *Login & Logout* – gunakan Postman atau curl:
```bash
curl -X POST http://localhost/api/login -d "email=admin@example.com&password=admin123"
# harus mendapatkan token
curl -H "Authorization: Bearer <token>" http://localhost/api/me
curl -X POST -H "Authorization: Bearer <token>" http://localhost/api/logout```
Response harus 200 dan token tidak lagi valid.

---

## 7️⃣ Pengujian (PHPUnit)
### 7.1 Test T‑5.1 – Auth Flow
File: `tests/Feature/AuthTest.php`
```php
public function test_user_can_login_and_logout()
{
    $user = \App\Models\User::factory()->create(['password' => bcrypt('secret')]);
    $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret']);
    $response->assertOk()->assertJsonStructure(['token']);
    $token = $response->json('token');

    $this->withHeaders(['Authorization' => "Bearer $token"]) 
         ->getJson('/api/me')
         ->assertOk()
         ->assertJson(['email' => $user->email]);

    $this->withHeaders(['Authorization' => "Bearer $token"]) 
         ->postJson('/api/logout')
         ->assertOk();
}
```
### 7.2 Test U‑05 – Model Relations & Settings
File: `tests/Unit/ModelRelationsTest.php`
```php
public function test_item_relations()
{
    $item = \App\Models\Item::factory()->create();
    $this->assertInstanceOf(\App\Models\Category::class, $item->category);
    $this->assertInstanceOf(\App\Models\Division::class, $item->division);
    $this->assertTrue($item->loans->isEmpty());
}

public function test_settings_loaded()
{
    $setting = \App\Models\Setting::where('key', 'loan_duration')->first();
    $this->assertEquals('7', $setting->value);
}
```
Jalankan semua tes:
```powershell
php artisan test```
Semua harus **green**.

---

## 📋 Ringkasan Eksekusi
1. Update `.env.example` & `config/app.php`.
2. Hapus migration lama, buat 11 migration baru + pivot.
3. Buat 13 model beserta relasi.
4. Buat seeder master (8 setting, divisi, kategori, admin).
5. Implementasi dua artisan command.
6. Konfigurasi Sanctum SPA, tambahkan middleware akun aktif.
7. Tulis dan jalankan tes T‑5.1 & U‑05.

Setelah semua langkah selesai, lakukan:
```powershell
php artisan migrate:fresh --seed
php artisan test```
Pastikan semua tes lulus sebelum commit.

Jika ada bagian yang ingin Anda ubah atau detail tambahan, beri tahu saya!