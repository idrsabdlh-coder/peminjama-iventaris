# Phase 6 – Sanctum SPA Mode (API + Front‑end)

## 1️⃣ Install & publish configuration
```powershell
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

## 2️⃣ Add Sanctum middleware
Edit **`app/Http/Kernel.php`** and add the Sanctum middleware to the `api` middleware group:
```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

## 3️⃣ API routes (authentication)
Create **`routes/api.php`** (or edit if it exists) with the following endpoints:
```php
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
```

## 4️⃣ Middleware to block inactive accounts
Create **`app/Http/Middleware/CheckActive.php`**:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckActive
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if ($user && !$user->active) {
            return response()->json(['message' => 'Akun tidak aktif'], 403);
        }
        return $next($request);
    }
}
```
Register the middleware in **`app/Http/Kernel.php`** (e.g., under `$routeMiddleware`):
```php
'active' => \App\Http\Middleware\CheckActive::class,
```
Then apply it to the protected API routes, e.g.:
```php
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // protected routes here
});
```

## 5️⃣ Front‑end configuration (SPA)
* In your Vue (or React) app, set `axios.defaults.withCredentials = true`.
* Store the token returned from `/login` (e.g., in Vuex or Pinia) and include it in the `Authorization: Bearer <token>` header for subsequent requests.
* Example login request (using `fetch`):
```js
await fetch('http://localhost/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: 'admin@example.com', password: 'admin123' })
});
```

## 6️⃣ Test the SPA flow (using curl or Postman)
```bash
# Login – should return a token
curl -X POST http://localhost/api/login \
     -H "Content-Type: application/json" \
     -d '{"email":"admin@example.com","password":"admin123"}'

# Use the token to get the current user
curl http://localhost/api/me \
     -H "Authorization: Bearer <token>"

# Logout
curl -X POST http://localhost/api/logout \
     -H "Authorization: Bearer <token>"
```
All responses should be `200 OK`. After logout the token must be invalid.

---

**Catatan:** Pastikan `APP_URL` di `.env` mengarah ke host yang sama dengan front‑end, atau tambahkan domain front‑end ke `SANCTUM_STATEFUL_DOMAINS` di `.env`.
