# Phase 2 – Pengembangan Lanjutan (PRD)

Setelah **fase 1** selesai (konfigurasi, migration, model, seeder, perintah artisan, API auth, dan unit test), fase 2 berfokus pada fitur‑fitur bisnis utama yang belum ada pada proyek lama.

---

## 🎯 Tujuan Utama fase 2
| # | Target | Deskripsi |
|---|--------|-----------|
| 2.1 | **Endpoint CRUD lengkap** untuk `Item`, `Loan`, `Category`, `Division` | Menyediakan API RESTful yang mengembalikan data dalam format JSON yang siap dipakai SPA.
| 2.2 | **Proses peminjaman & pengembalian** | Logika pengecekan stok, pembuatan `Check` (approval), dan update status otomatis.
| 2.3 | **Audit Log** | Merekam semua aksi penting (create, update, delete, loan, return) dengan `user_id`, `action`, `model`, `model_id`.
| 2.4 | **Middleware hak‑akses** | `admin` vs `operator` (role based). Hanya admin dapat meng‑manage setting & user.
| 2.5 | **Report & Dashboard** | Endpoint `GET /api/reports/stock` yang mengembalikan total/tersedia/rusak per division & category.
| 2.6 | **Queue & Notification (opsional)** | Event `LoanCreated` → notifikasi email/SMS (bisa di‑disable pada dev).
| 2.7 | **Testing integrasi** | Feature test untuk seluruh alur (pinjam → kembali, audit, role).

---

## 1️⃣ Struktur Direktori tambahan
```
app/
├─ Http/
│  ├─ Controllers/
│  │  ├─ Api/
│  │  │  ├─ ItemController.php
│  │  │  ├─ LoanController.php
│  │  │  ├─ CategoryController.php
│  │  │  ├─ DivisionController.php
│  │  │  └─ ReportController.php
│  │  └─ Middleware/
│  │     └─ CheckRole.php
├─ Events/
│  └─ LoanCreated.php
├─ Listeners/
│  └─ SendLoanNotification.php
└─ Policies/
   └─ ItemPolicy.php
```
---

## 2️⃣ API CRUD Endpoints
| Method | URI | Controller | Request Validation | Response |
|--------|-----|------------|-------------------|----------|
| **GET** | `/api/items` | `ItemController@index` | – | List pagination + filter (category, division, status) |
| **POST** | `/api/items` | `ItemController@store` | `ItemStoreRequest` (nama, kategori, divisi, total_stock) | Item baru + 201 |
| **GET** | `/api/items/{id}` | `ItemController@show` | – | Detail item |
| **PUT/PATCH** | `/api/items/{id}` | `ItemController@update` | `ItemUpdateRequest` | Item ter‑update |
| **DELETE** | `/api/items/{id}` | `ItemController@destroy` | – | 204 No Content |

> **Uji endpoint**: gunakan Postman atau `php artisan test --filter=ItemCrudTest`.

### 2.1 Membuat Request Form Request
```bash
php artisan make:request ItemStoreRequest
php artisan make:request ItemUpdateRequest
```
Isikan aturan `rules()` misalnya:
```php
return [
    'name' => 'required|string|max:255',
    'category_id' => 'required|exists:categories,id',
    'division_id' => 'required|exists:divisions,id',
    'total_stock' => 'required|integer|min:0',
];
```
---

## 3️⃣ Proses Peminjaman & Pengembalian (Loan)
### 3.1 Endpoint
| Method | URI | Controller | Catatan |
|--------|-----|------------|--------|
| **POST** | `/api/loans` | `LoanController@store` | Membuat loan, mengurangi `available_stock`, menambah `item_loan` pivot |
| **POST** | `/api/loans/{id}/return` | `LoanController@return` | Meningkatkan `available_stock`, membuat `Check` dengan status `returned` |

### 3.2 Logika di `LoanController@store`
```php
public function store(LoanStoreRequest $request)
{
    $user = $request->user();
    $items = $request->input('items'); // [{item_id, qty}, ...]

    DB::transaction(function () use ($user, $items) {
        $loan = Loan::create(['user_id' => $user->id, 'status' => 'pending']);
        foreach ($items as $data) {
            $item = Item::findOrFail($data['item_id']);
            if ($item->available_stock < $data['qty']) {
                throw new \Exception('Stok tidak cukup untuk item '.$item->name);
            }
            // pivot insert
            $loan->items()->attach($item->id, ['qty' => $data['qty']]);
            // update stock
            $item->decrement('available_stock', $data['qty']);
        }
        // optional: fire event
        event(new \App\Events\LoanCreated($loan));
    });

    return response()->json(['message' => 'Loan dibuat'], 201);
}
```
### 3.3 Endpoint `return`
```php
public function return(Request $request, Loan $loan)
{
    if ($loan->status !== 'approved') {
        return response()->json(['error' => 'Loan tidak dapat dikembalikan'], 400);
    }
    DB::transaction(function () use ($loan) {
        foreach ($loan->items as $item) {
            $pivot = $item->pivot; // qty
            $item->increment('available_stock', $pivot->qty);
        }
        $loan->update(['status' => 'returned', 'returned_at' => now()]);
        // buat record check
        Check::create(['loan_id' => $loan->id, 'status' => 'returned']);
    });
    return response()->json(['message' => 'Loan dikembalikan']);
}
```
#### Uji
```bash
# Pinjam 2 item
curl -X POST http://localhost/api/loans \
 -H "Authorization: Bearer <token>" \
 -d '{"items":[{"item_id":1,"qty":2}]}'
# Kembalikan
curl -X POST http://localhost/api/loans/1/return \
 -H "Authorization: Bearer <token>"
```
Periksa tabel `items` stoknya kembali.
---

## 4️⃣ Audit Log
### 4.1 Migration (jika belum ada)
```bash
php artisan make:migration create_audit_logs_table --create=audit_logs
```
Isi migration:
```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('action'); // created, updated, deleted, loaned, returned
    $table->string('model');
    $table->unsignedBigInteger('model_id');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->timestamps();
});
```
### 4.2 Observer
Buat `app/Observers/AuditObserver.php` dan daftarkan di `AppServiceProvider`:
```php
public function boot()
{
    \App\Models\Item::observe(\App\Observers\AuditObserver::class);
    \App\Models\Loan::observe(\App\Observers\AuditObserver::class);
    // ... tambahkan model lain bila perlu
}
```
Observer sederhana:
```php
public function created($model)
{
    \App\Models\AuditLog::create([
        'user_id' => auth()->id() ?? 0,
        'action' => 'created',
        'model' => get_class($model),
        'model_id' => $model->id,
        'new_values' => $model->toJson(),
    ]);
}
```
### 4.3 Uji
Buat feature test `tests/Feature/AuditLogTest.php` yang memanggil endpoint create item dan memeriksa tabel `audit_logs` ada catatan.
---

## 5️⃣ Middleware Hak‑Akses (Role Based)
### 5️⃣1 Tambahkan kolom `role` pada tabel `users`
```bash
php artisan make:migration add_role_to_users_table --table=users
```
Isi migration:
```php
$table->enum('role', ['admin','operator'])->default('operator');
```
### 5️⃣2 Middleware `CheckRole`
Buat `app/Http/Middleware/CheckRole.php`:
```php
public function handle($request, Closure $next, $role)
{
    if (auth()->user()->role !== $role) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
    return $next($request);
}
```
Register di `kernel.php`:
```php
'role.admin' => \App\Http\Middleware\CheckRole::class.':admin',
'role.operator' => \App\Http\Middleware\CheckRole::class.':operator',
```
Gunakan pada route grup:
```php
Route::middleware(['auth:sanctum', 'role.admin'])->group(function(){
    Route::apiResource('settings', SettingController::class);
    Route::apiResource('users', UserController::class);
});
```
### Uji
```bash
# login sebagai operator, coba akses /api/settings → 403
# login sebagai admin, harus berhasil
```
---

## 6️⃣ Report & Dashboard API
### 6️⃣1 Endpoint
```php
Route::get('/api/reports/stock', [ReportController::class, 'stock'])
     ->middleware(['auth:sanctum', 'role.admin']);
```
### 6️⃣2 Implementasi
```php
public function stock()
{
    $data = Division::with(['items' => function($q){
        $q->select('division_id',
            DB::raw('SUM(total_stock) as total'),
            DB::raw('SUM(available_stock) as available'),
            DB::raw('SUM(damaged_stock) as damaged'))
            ->groupBy('division_id');
    }])->get();
    return response()->json($data);
}
```
### Uji
```bash
curl -H "Authorization: Bearer <admin-token>" http://localhost/api/reports/stock
```
Harus mengembalikan JSON dengan ringkasan stok per division.
---

## 7️⃣ Event & Notification (opsional, dev only)
1. Buat event `LoanCreated` (sudah ada pada fase 1).  
2. Listener `SendLoanNotification` yang meng‑log ke file atau meng‑send email via `Mail::to($user)->send(new LoanCreatedMail($loan));`
3. Daftarkan di `EventServiceProvider`.
4. **Uji**: Pastikan file `storage/logs/loan_notifications.log` berisi entry ketika loan dibuat.
---

## 8️⃣ Testing Integrasi (Feature)
### 8.1 File: `tests/Feature/LoanFlowTest.php`
```php
public function test_full_loan_return_flow()
{
    $admin = User::factory()->create(['role'=>'admin', 'password'=>bcrypt('secret')]);
    $token = $this->postJson('/api/login', ['email'=>$admin->email,'password'=>'secret'])->json('token');

    // buat item & stock
    $item = Item::factory()->create(['total_stock'=>10,'available_stock'=>10]);

    // pinjam 3
    $this->withHeaders(['Authorization'=>"Bearer $token"]) 
         ->postJson('/api/loans', ['items'=>[['item_id'=>$item->id,'qty'=>3]]])
         ->assertCreated();

    $loan = Loan::first();
    $this->assertEquals(7, $item->fresh()->available_stock);

    // kembali
    $this->withHeaders(['Authorization'=>"Bearer $token"]) 
         ->postJson("/api/loans/{$loan->id}/return")
         ->assertOk();

    $this->assertEquals(10, $item->fresh()->available_stock);
    $this->assertDatabaseHas('checks', ['loan_id'=>$loan->id,'status'=>'returned']);
}
```
Jalankan:
```powershell
php artisan test --filter=LoanFlowTest
```
Semua harus **green**.
---

## 📦 Deployment Checklist (setelah semua tes lulus)
1. `php artisan migrate:fresh --seed`
2. `php artisan config:cache && php artisan route:cache`
3. Pastikan **SESSION_LIFETIME=15** di `.env` (production).
4. Build front‑end (jika ada) dengan `npm run build` dan sesuaikan `vite.config.js` untuk `base: '/'`.
5. Push ke repository & buat **GitHub Actions** workflow untuk `phpunit` dan `phpstan`.

---

## 📌 Ringkasan Eksekusi (Urutan langkah)
1️⃣ Update konfigurasi `.env.example` & `config/app.php` (sama seperti fase 1).  
2️⃣ Buat migration, model, dan policy tambahan (role, audit).  
3️⃣ Implementasi controller CRUD + request validation.  
4️⃣ Tambahkan event & listener (opsional).  
5️⃣ Buat middleware role & aktif.  
6️⃣ Buat endpoint report.  
7️⃣ Tulis feature test untuk seluruh alur.  
8️⃣ Jalankan `php artisan test` → semua lulus.  
9️⃣ Deploy & monitor.

---

Jika ada **penyesuaian** atau **pertanyaan** mengenai urutan, beri tahu saya agar saya dapat memperbaharui artefak.

---

*Artifact metadata:* RequestFeedback = false, Summary = "Phase 2 plan", UserFacing = true.