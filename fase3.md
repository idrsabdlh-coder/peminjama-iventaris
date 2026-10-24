# Phase 3 – Penyelesaian & Optimasi (PRD)

Setelah fase 1 (setup dasar) dan fase 2 (fitur bisnis) selesai, fase 3 berfokus pada:
- Penyempurnaan UI/UX SPA
- Optimasi performa & keamanan
- Dokumentasi & CI/CD
- Monitoring & observability
- Release & rollback strategy

---

## 🎯 Tujuan Utama fase 3
| # | Target | Deskripsi |
|---|--------|-----------|
| 3.1 | **SPA Front‑end** (Vue/React) | Integrasi API yang sudah dibangun, tampilan daftar barang, peminjaman, riwayat, dan dashboard admin. |
| 3.2 | **Role‑Based UI** | Admin dapat mengelola Setting, User, dan laporan; Operator hanya dapat meminjam/kembalikan. |
| 3.3 | **Performance Tuning** | Caching (Redis) untuk laporan stok, eager loading pada query, dan pagination. |
| 3.4 | **Security Harden** | Rate‑limiting, CORS whitelist, CSP, audit log retention, dan token revocation endpoint. |
| 3.5 | **Testing End‑to‑End** | Cypress / Playwright skenario lengkap (login → pinjam → kembali → logout). |
| 3.6 | **CI/CD Pipeline** | GitHub Actions workflow menjalankan PHPStan, PHPUnit, linting front‑end, dan deploy ke staging. |
| 3.7 | **Monitoring** | Laravel Telescope & Horizon (jika queue di‑aktifkan), serta Health check endpoint. |
| 3.8 | **Rollback & Backup** | Skrip migration rollback, DB dump otomatis, dan strategi blue‑green deploy. |

---

## 1️⃣ Front‑end SPA (Vue 3 + Vite) – Struktur
```
frontend/
├─ src/
│  ├─ components/
│  │  ├─ ItemList.vue
│  │  ├─ LoanForm.vue
│  │  └─ Dashboard.vue
│  ├─ views/
│  │  ├─ DashboardView.vue
│  │  ├─ InventoryView.vue
│  │  └─ SettingsView.vue
│  ├─ router/
│  │  └─ index.js
│  └─ store/
│      └─ index.js (Pinia)
└─ vite.config.ts
```
### Perintah setup
```powershell
cd C:\laragon\www\Peminjaman-iventaris
mkdir frontend
cd frontend
npm init vite@latest . --template vue
npm install axios pinia vue-router@4
npm i -D @vue/test-utils vitest
```
### Integrasi API
Buat file `src/services/api.js` dengan instance Axios yang menyertakan token Sanctum secara otomatis (withCredentials).
```js
import axios from 'axios';
const api = axios.create({ baseURL: 'http://localhost/api', withCredentials: true });
export default api;
```
### Uji SPA
```bash
npm run dev   # buka http://localhost:5173
# lakukan login, lihat tabel item, buat pinjaman, kembali, logout
```
---

## 2️⃣ Caching & Optimasi Backend
- **Redis**: `composer require predis/predis`
- **Cache laporan stock** di `ReportController::stock` menggunakan `Cache::remember('stock_report', now()->addMinutes(10), fn()=> ...)`.
- **Eager load** pada model yang sering dipakai:
  ```php
  Item::with(['category','division','loans'])->paginate(20);
  ```
- **Pagination** pada semua collection API (`->paginate(15)`), kirim meta `current_page`, `last_page`.
- **Query index**: tambahkan indeks pada `items.division_id`, `items.category_id`, `loans.user_id`.

### Perintah migrasi indeks
```bash
php artisan make:migration add_indexes_to_items_and_loans --table=items
```
Isi migration dengan `$table->index(['division_id','category_id']);` dan `$table->index('user_id');` pada tabel loans.
---

## 3️⃣ Keamanan Lanjutan
| Area | Implementasi |
|------|--------------|
| Rate limiting | `Route::middleware('throttle:60,1')->group(...);` |
| CORS | `fruitcake/laravel-cors` → whitelist domain front‑end |
| CSP & Helmet (front‑end) | meta header via Vite plugin |
| Token revocation | Endpoint `POST /api/logout-all` yang menghapus semua personal access token milik pengguna |
| Audit retention | Scheduler (`php artisan schedule:run`) untuk memindahkan audit log > 90 hari ke tabel `audit_logs_archive`.

### Perintah schedule
```bash
php artisan make:command PurgeOldAuditLogs --command=purge:audit-logs
```
Isi `handle()` dengan query delete `where created_at < now()->subDays(90)`.
---

## 4️⃣ End‑to‑End Testing (Cypress)
1. **Install** di `frontend/`:
```bash
npm i -D cypress
```
2. **File contoh** `cypress/e2e/loan_flow.cy.js`:
```js
describe('Loan flow',()=>{
  it('Login, borrow, return, logout',()=>{
    cy.visit('/')
    cy.get('[data-cy=email]').type('admin@example.com')
    cy.get('[data-cy=password]').type('admin123')
    cy.get('[data-cy=login]').click()
    cy.contains('Item List')
    cy.get('[data-cy=borrow-btn]').first().click()
    cy.get('[data-cy=quantity]').type('2')
    cy.get('[data-cy=confirm]').click()
    cy.contains('Pinjaman berhasil')
    // Return
    cy.get('[data-cy=return-btn]').first().click()
    cy.contains('Pengembalian berhasil')
    cy.get('[data-cy=logout]').click()
    cy.contains('Login')
  })
})
```
3. **Run**: `npx cypress run`.
---

## 5️⃣ CI/CD (GitHub Actions)
Buat file `.github/workflows/ci.yml`:
```yaml
name: CI
on: [push, pull_request]
jobs:
  php-tests:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: inventory
        ports: ['3306:3306']
        options: >
          --health-cmd="mysqladmin ping --silent"
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, bcmath, intl, json, mysql
      - name: Install Composer deps
        run: composer install --prefer-dist --no-progress --no-suggest
      - name: Copy env
        run: cp .env.example .env && php artisan key:generate
      - name: Run migrations & seed
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: inventory
          DB_USERNAME: root
          DB_PASSWORD: secret
        run: |
          php artisan migrate --force
      - name: Run PHPStan
        run: vendor/bin/phpstan analyse
      - name: Run PHPUnit
        run: vendor/bin/phpunit --coverage-text
  frontend-tests:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: frontend
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm ci
      - run: npm run build
      - name: Cypress run
        uses: cypress-io/github-action@v5
        with:
          start: npm run dev
          wait-on: http://localhost:5173
          wait-on-timeout: 120
```
---

## 6️⃣ Monitoring & Observability
- **Laravel Telescope**: `composer require laravel/telescope --dev` → `php artisan telescope:install && php artisan migrate`.
- **Horizon** (jika queue dipakai): `composer require laravel/horizon` → `php artisan horizon`.
- **Health Check** endpoint `GET /api/health` yang mengembalikan `status: ok` dan versi aplikasi.
- **Grafana / Prometheus** (opsional) – expose `/metrics` via `spatie/laravel-metrics`.
---

## 7️⃣ Release & Rollback Strategy
1. **Tag release**: `git tag -a v1.0.0 -m "Release v1.0.0" && git push origin v1.0.0`.
2. **Deploy script** (bash) yang melakukan:
   - `git pull`
   - `composer install --optimize-autoloader --no-dev`
   - `php artisan migrate --force`
   - `php artisan config:cache && php artisan route:cache`
   - `npm ci && npm run build` (frontend)
   - Restart **php-fpm** / **nginx**.
3. **Rollback**:
   - `git revert <release-commit>` atau `git checkout <previous-tag>`
   - `php artisan migrate:rollback --step=1`
   - Reload services.
---

## 8️⃣ Dokumentasi
- **API Docs**: gunakan **Laravel Scribe** (`composer require knuckleswtf/scribe`) → `php artisan scribe:generate`.
- **Frontend Docs**: Storybook untuk komponen UI (`npm i -D storybook`).
- **Runbooks**: Markdown di `docs/` (install, backup, emergency restore).
---

## 📦 Checklist Penyelesaian Fase 3
1️⃣ Implementasi SPA UI & router.  
2️⃣ Tambahkan caching, indeks, dan pagination.  
3️⃣ Hardening security (rate‑limit, CORS, token revocation).  
4️⃣ End‑to‑end Cypress tests & CI pipeline.  
5️⃣ Setup Telescope & optional Horizon.  
6️⃣ Buat health‑check endpoint.  
7️⃣ Dokumentasi API + UI.  
8️⃣ Deploy ke staging, verifikasi semua tes, lalu release.

---

Jika ada detail tambahan yang ingin dimasukkan atau penyesuaian urutan, beri tahu saya agar artefak dapat diperbarui.

*Artifact metadata:* RequestFeedback = false, Summary = "Phase 3 plan", UserFacing = true.