# Fase 0 – Persiapan & Lingkungan

Dokumen ini merupakan panduan pelaksanaan **Fase 0** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Memastikan lingkungan pengembangan lokal (PHPBRO, Adminer, MySQL, PHP 8.4) dan keputusan arsitektural telah siap, terverifikasi, dan disepakati sebelum penulisan kode aplikasi dimulai.

---

## 📦 Informasi & Keputusan Teknis
| Item | Ketetapan |
|---|---|
| **Backend** | Laravel 13 (PHP ^8.3 / PHP 8.4), RESTful API JSON |
| **Frontend** | Vue 3 + Vite + Tailwind CSS (SPA terpisah di folder `frontend/`) |
| **Database** | MySQL 8.4 (InnoDB, utf8mb4) pada database `inventaris_db` |
| **Server Lokal** | PHPBRO dengan antarmuka database Adminer |
| **Sistem Autentikasi** | Laravel Sanctum mode SPA (berbasis cookie httpOnly, sesi idle 15 menit) |
| **Hak Akses** | Peminjam tanpa akun (Kiosk); Admin 1 level hak akses |

---

## 📋 Rincian Pekerjaan (Tasks)

### 1. Infrastruktur & Database Lokal (PHPBRO & Adminer)
- [ ] Buka dan jalankan **PHPBRO**. Pastikan service **MySQL 8.4** dan **PHP 8.4** aktif.
- [ ] Buka database management tool **Adminer** bawaan PHPBRO.
- [ ] Buat basis data dengan nama `inventaris_db` (Collation: `utf8mb4_unicode_ci`).
- [ ] Buat user database aplikasi atau pastikan kredensial user lokal siap dipakai.
- [ ] Verifikasi port database default `3306` pada host `127.0.0.1`.

### 2. Konfigurasi Lingkungan Backend (`backend/`)
- [ ] Pastikan dependensi backend terpasang via Composer (`composer install`).
- [ ] Pastikan file `backend/.env` tersedia dan terhubung ke MySQL PHPBRO:
  ```env
  DB_CONNECTION=mysql
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=inventaris_db
  DB_USERNAME=root
  DB_PASSWORD=
  SESSION_DRIVER=database
  SESSION_LIFETIME=15
  APP_TIMEZONE=UTC
  ```
- [ ] Jalankan perintah pengujian koneksi:
  ```powershell
  cd backend
  php artisan migrate:status
  ```
- [ ] Pastikan koneksi sukses dan tidak memicu error PDO/koneksi.

### 3. Konfigurasi Lingkungan Frontend (`frontend/`)
- [ ] Periksa dan baca `frontend/package.json`.
- [ ] Pasang dependensi frontend menggunakan Node.js:
  ```powershell
  cd frontend
  npm install
  ```
- [ ] Pastikan proxy Vite telah disiapkan untuk meneruskan rute `/api` dan `/sanctum` ke backend (`http://127.0.0.1:8000`).

### 4. Keputusan & Pertanyaan Terbuka PRD
- [ ] Konfirmasi pembagian cakupan MVP: Fitur Must selesai di Fase 5; Fitur lanjutan (booking, perpanjangan, pembatalan, koreksi, stok rusak) diselesaikan di Fase 6.
- [ ] Konfirmasi parameter kebijakan awal:
  - Durasi peminjaman: 7 hari
  - Maksimal booking: 7 hari
  - Maksimal kuota perpanjangan: 2 kali (@ 7 hari)
  - Idle reset kiosk: 60 detik
  - Sesi idle admin: 15 menit
  - Lockout lookup kode salah: 5 kali gagal beruntun selama 5 menit
- [ ] Menentukan kategori awal seeder (wajib ada kategori "Tanpa Kategori" dan kategori IT standar: Laptop, Proyektor, Adapter, Kabel, Aksesoris).
- [ ] Menentukan daftar divisi awal seeder (misal: IT, HRD, Operasional, Keuangan, Pemasaran).
- [ ] Verifikasi lokasi data lama: Jika tidak ada database PostgreSQL lama dari sistem sebelumnya, migrasi data lama ditiadakan.

### 5. Repositori & Kontrol Versi
- [ ] Pastikan `.gitignore` di root, backend, dan frontend mengabaikan `.env`, file build (`node_modules/`, `vendor/`), dan berkas cache.
- [ ] Verifikasi remote GitHub terhubung dan perintah `git fetch` berjalan lancar.

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 0)
Fase 0 dinyatakan **SELESAI** dan tim diizinkan masuk ke Fase 1 bila:
- [ ] `php artisan migrate:status` berhasil terhubung ke MySQL 8.4 di PHPBRO tanpa kendala.
- [ ] Pilihan menjalankan aplikasi (PHPBRO + `php artisan serve` port 8000 dan Vite port 5173) tercatat di dokumentasi setup.
- [ ] Repositori Git bersih dari kredensial dan `git fetch` berhasil.
- [ ] Kategori dan divisi awal untuk seeder telah disepakati.
