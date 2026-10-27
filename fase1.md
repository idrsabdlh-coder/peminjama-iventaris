# Fase 1 – Fondasi (Skema Database, Model & Auth Admin)

Dokumen ini merupakan panduan pelaksanaan **Fase 1** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Membangun fondasi arsitektur backend Laravel 13 yang kuat dan aman:
1. Skema basis data lengkap untuk seluruh fitur proyek sejak awal (11 tabel domain + `settings`, `audit_logs`, `sessions`) dengan aturan integritas ketat (FK `RESTRICT`, CHECK constraints, generated column).
2. Model Eloquent lengkap beserta definisinya.
3. Seeder data awal master (divisi, kategori, 8 kunci pengaturan).
4. Otentikasi admin berbasis sesi aman (Sanctum SPA, idle 15 menit, middleware akun aktif) dan perintah console Artisan untuk manajemen kredensial admin.

---

## 📋 FR yang Dicakup
- **FR-11**: Login admin, logout, sesi idle 15 menit, pembatasan laju (*rate limiting*).
- **FR-20**: Pembuatan akun admin pertama dan reset password via perintah Artisan (tanpa kredensial *hardcoded*).

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Konfigurasi Backend & Sanctum SPA
- [x] Atur timezone default ke `'UTC'` di `backend/config/app.php`. Waktu transaksi internal selalu disimpan dalam format UTC.
- [x] Konfigurasi sesi database di `.env`: `SESSION_DRIVER=database` dan `SESSION_LIFETIME=15`.
- [x] Konfigurasi Sanctum mode SPA: daftarkan domain lokal frontend di `SANCTUM_STATEFUL_DOMAINS` (misal: `localhost:5173,127.0.0.1:5173`).
- [x] Pastikan cookie sesi memakai `httpOnly`, `SameSite=Lax` (atau `Strict` pada produksi), dan dilindungi CSRF.

### 2. Migrasi Skema Basis Data (11 Tabel Domain + Tabel Sistem)
Semua foreign key **wajib memakai `RESTRICT`** (tidak boleh ada `CASCADE`). Kolom nama entitas memakai Bahasa Indonesia:
1. **`users`**: Akun admin (`id`, `name`, `email` UNIQUE, `password` hash, `aktif` TINYINT(1) default 1, `login_terakhir`, `dibuat_pada`).
2. **`divisions`**: Divisi peminjam (`id`, `nama` UNIQUE, `aktif` TINYINT(1) default 1).
3. **`categories`**: Kategori inventaris (`id`, `nama` UNIQUE, `aktif` TINYINT(1) default 1).
4. **`settings`**: Parameter kebijakan (`kunci` VARCHAR(60) PK, `nilai` VARCHAR(255), `diubah_pada`).
5. **`items`**: Barang inventaris (`id`, `nama`, `deskripsi`, `kategori_id` FK categories RESTRICT, `total`, `tersedia`, `rusak` default 0, `boleh_diperpanjang` default 1, `dibuat_pada`, `diubah_pada`, `dihapus_pada` soft-delete, `nama_aktif` GENERATED STORED UNIQUE).
   - CHECK: `total >= 0`, `tersedia >= 0`, `rusak >= 0`, `tersedia + rusak <= total`.
6. **`loans`**: Header transaksi pinjam (`id`, `kode_pinjam` UNIQUE format PJM-XXXXXX, `nama_peminjam`, `division_id` FK divisions RESTRICT, `tipe_peminjam` ENUM('karyawan','magang'), `telepon`, `tanggal_pinjam` DATE, `batas_kembali_awal` DATE, `batas_kembali` DATE, `idempotency_key` CHAR(36) UNIQUE, `dibuat_pada`, `dibatalkan_pada`, `alasan_batal`, `dibatalkan_oleh` FK users RESTRICT).
7. **`loan_items`**: Detail barang per pinjaman (`id`, `loan_id` FK loans RESTRICT, `item_id` FK items RESTRICT, `qty_dipinjam` CHECK >= 1). UNIQUE(`loan_id`, `item_id`).
8. **`return_claims`**: Pengajuan klaim pengembalian kiosk (`id`, `kode_klaim` UNIQUE format KMB-XXXXXX, `loan_id` FK loans RESTRICT, `catatan_peminjam`, `diklaim_pada`, `hari_terlambat` default 0, `status` ENUM('menunggu','diverifikasi'), `diverifikasi_oleh` FK users RESTRICT, `diverifikasi_pada`, `catatan_admin`, `idempotency_key` UNIQUE).
9. **`return_claim_lines`**: Rincian fisik barang yang diklaim & hasil verifikasi (`id`, `claim_id` FK return_claims RESTRICT, `loan_item_id` FK loan_items RESTRICT, `kondisi_dilaporkan` ENUM('baik','rusak','hilang'), `qty` CHECK >= 1, `qty_baik`, `qty_rusak`, `qty_hilang`, `qty_ditolak`).
10. **`loan_extensions`**: Riwayat perpanjangan durasi pinjam (`id`, `loan_id` FK loans RESTRICT, `batas_lama`, `batas_baru`, `tambahan_hari` CHECK >= 1, `alasan`, `diajukan_oleh` ENUM('peminjam','admin'), `user_id` FK users RESTRICT, `dibuat_pada`).
11. **`loan_corrections`**: Log perubahan koreksi data transaksi oleh admin (`id`, `loan_id` FK loans RESTRICT, `kolom`, `nilai_lama`, `nilai_baru`, `alasan` NOT NULL, `user_id` FK users RESTRICT, `dibuat_pada`).
12. **`stock_movements`**: Buku besar mutasi stok (`id`, `item_id` FK items RESTRICT, `jenis` ENUM, `delta_total`, `delta_tersedia`, `delta_rusak`, `ref_tipe`, `ref_id`, `user_id` FK users, `catatan`, `dibuat_pada`).
13. **`audit_logs`**: Rekam jejak audit keamanan (`id`, `user_id` FK users, `aksi`, `entitas`, `entitas_id`, `detail` JSON, `dibuat_pada`).
14. **`sessions`**: Tabel bawaan Laravel untuk session management database.

### 3. Model Eloquent & Relasi
- [x] Buat Model: `User`, `Division`, `Category`, `Item`, `Loan`, `LoanItem`, `ReturnClaim`, `ReturnClaimLine`, `LoanExtension`, `LoanCorrection`, `StockMovement`, `AuditLog`, `Setting`.
- [x] Pada model domain, terapkan `$timestamps = false` dan tangani timestamp sesuai kolom database (`dibuat_pada`, `diubah_pada`).
- [x] Terapkan relasi Eloquent yang tepat (`belongsTo`, `hasMany`).

### 4. Seeder Data Awal Master
- [x] **`DivisionSeeder`**: Membuat data divisi awal (IT, HRD, Finance, Operational, Marketing).
- [x] **`CategorySeeder`**: Wajib membuat kategori ID 1 "Tanpa Kategori", serta kategori umum IT (Laptop, Monitor, Proyektor, Adapter, Kabel, Periferal).
- [x] **`SettingSeeder`**: Mengisi 8 parameter kebijakan baku:
  1. `maks_durasi_awal_hari` = `7`
  2. `maks_perpanjangan` = `2`
  3. `maks_hari_per_perpanjangan` = `7`
  4. `maks_booking_hari` = `7`
  5. `sesi_idle_menit` = `15`
  6. `kiosk_idle_detik` = `60`
  7. `kiosk_lookup_maks_gagal` = `5`
  8. `kiosk_lookup_kunci_menit` = `5`

### 5. Artisan Commands (FR-20)
- [x] `php artisan inventaris:buat-admin`:
  Membuat akun admin pertama via CLI dengan prompt interaktif (nama, email, password tersembunyi `secret()`). Password langsung di-hash menggunakan `Hash::make()`.
- [x] `php artisan inventaris:reset-password {email}`:
  Mereset password admin jika terkunci, meminta input password baru yang aman.

### 6. Endpoint & Middleware Autentikasi Admin (FR-11)
- [x] `GET /sanctum/csrf-cookie`: Endpoint cookie proteksi CSRF.
- [x] `POST /api/auth/login`: Validasi email & password, login via guard web/sanctum, perbarui `login_terakhir`, kembalikan info user. Diberikan pembatas laju (`throttle:5,1`).
- [x] `POST /api/auth/logout`: Logout sesi dan batalkan sesi aktif.
- [x] `GET /api/auth/me`: Mengembalikan data admin yang sedang login.
- [x] `POST /api/auth/change-password`: Ganti password admin sendiri (wajib memasukkan password lama).
- [x] Middleware `admin.aktif`: Memeriksa apakah `auth()->user()->aktif == 1`. Jika 0, batalkan sesi dan tolak dengan `403 Forbidden`.

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 1)
Fase 1 dinyatakan **SELESAI** jika:
- [x] **T-5.1 Lolos**: Mengakses rute admin (`/api/admin/*`) tanpa login menghasilkan penolakan `401 Unauthorized`.
- [x] **U-05 Lolos**: Password tersimpan di basis data dalam format hash bcrypt/argon2id, tidak pernah ada teks biasa (*plain text*).
- [x] **Sesi Idle**: Sesi otomatis kedaluwarsa setelah 15 menit idle.
- [x] **Bebas Kredensial (G3)**: Tidak ada kredensial admin default di file kode sumber atau repository.
- [x] **Portabilitas (G6)**: Perintah `php artisan migrate:fresh --seed` berjalan mulus dari repo bersih tanpa error.
