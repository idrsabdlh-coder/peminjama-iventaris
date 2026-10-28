# TODO List – Sistem Peminjaman & Inventaris Barang

Dokumen rencana kerja terstruktur dari **Fase 0 hingga Fase 7** yang diturunkan dari `PRD v5.0` dan dokumen perencanaan fase.

---

## 📌 Ringkasan Status Proyek

| Fase | Nama Fase | Status | Keterangan |
| :--- | :--- | :---: | :--- |
| **Fase 0** | Persiapan Lingkungan & Keputusan Awal | ✅ **SELESAI** | Infrastruktur, Git repo, backup awal, standarisasi setup. |
| **Fase 1** | Fondasi Backend (Skema Database, Model & Auth) | ✅ **SELESAI** | 11 tabel domain + sistem, model, seeder, Sanctum SPA, Artisan commands. |
| **Fase 2** | Inti Stok & Mesin Transaksi (ACID & Invarian) | ✅ **SELESAI** | TDD (U-01 s.d U-04), PeminjamanService, KlaimService, VerifikasiService. |
| **Fase 3** | Frontend Kiosk (Layar Sentuh Peminjam) | ⏳ **PENDING** | UI katalog, form pinjam, lookup kode, klaim kembali, idle reset 60d. |
| **Fase 4** | Panel Admin (Manajemen Data, Verifikasi & Dasbor) | ⏳ **PENDING** | Dasbor, master data, verifikasi fisik 4 kondisi, soft-delete aman. |
| **Fase 5** | Laporan, Jejak Audit & Pengaturan (Titik MVP) | ⏳ **PENDING** | PDF server-side, audit logs, 8 setting kebijakan, tonggak MVP. |
| **Fase 6** | Fitur Lanjutan (Booking, Perpanjangan, Koreksi, Bengkel) | ⏳ **PENDING** | Booking masa depan, perpanjangan mandiri/admin, afkir stok, ekspor CSV. |
| **Fase 7** | Pengujian Penuh, Hardening, Dokumentasi & Rilis | ⏳ **PENDING** | UAT, benchmark 5.000 data, hardening keamanan, prosedur backup restore. |

---

## ⚙️ Aturan & Keputusan Teknis Baku

1. **Stack Inti**:
   - Backend: Laravel 13 (PHP 8.2+)
   - Frontend: Vue 3 + Vite + Tailwind CSS
   - Database: MySQL 8.4 (Server lokal / PHPBRO)
2. **Aturan Database**:
   - Database aplikasi: `inventaris_db` (dilarang menyentuh `inventaris_it_dev` dan `solopos_membership`).
   - Database pengujian: `inventaris_test`.
   - `migrate:fresh` hanya boleh dijalankan setelah memastikan `DB_DATABASE=inventaris_db`. Dilarang `DROP DATABASE`.
   - Tabel belum pernah dirilis ke produksi: edit file migration aslinya, bukan menambah migration baru pengubah skema.
3. **Standar Kolom Stok**:
   - Tabel `items`: `total`, `tersedia`, `kondisi` (kuantitas unit rusak, BUKAN status teks).
   - Tabel `stock_movements`: `delta_total`, `delta_tersedia`, `delta_kondisi`.
   - Invarian BR-01: `tersedia + kondisi + dipinjam + menunggu_verifikasi == total`.
4. **Mutasi Stok**:
   - Wajib atomic bersyarat di server (`UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :qty`), bukan baca lalu tulis.

---

## 🚀 Rincian Fase

### 🏁 Fase 0 – Persiapan Lingkungan & Keputusan Awal
- [x] **0.1 Verifikasi Koneksi Database**: Koneksi MySQL 8.4 ke `inventaris_db`.
- [x] **0.2 Setup Backend**: Instalasi Laravel via Composer.
- [x] **0.3 Setup Frontend**: Node.js & Vue 3 (`npm install`).
- [x] **0.4 Standarisasi Lingkungan**: Dokumentasi cara menjalankan aplikasi di `README.md`.
- [x] **0.5 Panduan Setup**: Konfigurasi template `.env.example`, `APP_KEY`, migrasi & seeder.
- [x] **0.6 Manajemen Git**: Remote GitHub terhubung, branch `main` & `dev`.
- [x] **0.7 Keamanan Kredensial**: Tidak ada kredensial hardcoded pada repository.
- [x] **0.8 Backup Awal**: Simpan dump awal database di `storage/backups/`.

---

### 🧱 Fase 1 – Fondasi Backend (Skema Database, Model & Auth)
- [x] **1.1 Konfigurasi Sanctum SPA & Sesi**:
  - [x] Timezone internal aplikasi `'UTC'` (`config/app.php`).
  - [x] Sesi database `SESSION_DRIVER=database`, lifetime 15 menit.
  - [x] Sanctum stateful domain lokal dan proteksi CSRF cookie.
- [x] **1.2 Migrasi Skema Basis Data (FK RESTRICT, No Cascade)**:
  - [x] `users`: Akun admin (`id`, `name`, `email`, `password`, `aktif`, `login_terakhir`, `dibuat_pada`).
  - [x] `divisions`: Divisi peminjam (`id`, `nama`, `aktif`).
  - [x] `categories`: Kategori inventaris (`id`, `nama`, `aktif`).
  - [x] `settings`: Parameter kebijakan (`kunci` PK, `nilai`, `diubah_pada`).
  - [x] `items`: Barang inventaris (`id`, `nama`, `deskripsi`, `tipe`, `kategori_id`, `total`, `tersedia`, `kondisi`, `boleh_diperpanjang`, `nama_aktif` UNIQUE generated, `dihapus_pada`).
  - [x] `loans`: Transaksi peminjaman (`id`, `kode_pinjam` UNIQUE, `nama_peminjam`, `division_id`, `tipe_peminjam`, `telepon`, `tanggal_pinjam`, `batas_kembali_awal`, `batas_kembali`, `idempotency_key` UNIQUE, `dibatalkan_pada`, dll).
  - [x] `loan_items`: Detail barang pinjaman (`id`, `loan_id`, `item_id`, `qty_dipinjam`).
  - [x] `return_claims`: Pengajuan klaim pengembalian kiosk (`id`, `kode_klaim` UNIQUE, `loan_id`, `status`, `diklaim_pada`, `hari_terlambat`, dll).
  - [x] `return_claim_lines`: Rincian fisik klaim (`id`, `claim_id`, `loan_item_id`, `kondisi_dilaporkan`, `qty`, `qty_baik`, `qty_rusak`, `qty_hilang`, `qty_ditolak`).
  - [x] `loan_extensions`: Riwayat perpanjangan (`id`, `loan_id`, `batas_lama`, `batas_baru`, `tambahan_hari`, `alasan`, `diajukan_oleh`, `user_id`).
  - [x] `loan_corrections`: Log koreksi data (`id`, `loan_id`, `kolom`, `nilai_lama`, `nilai_baru`, `alasan`, `user_id`).
  - [x] `stock_movements`: Buku besar mutasi stok (`id`, `item_id`, `jenis`, `delta_total`, `delta_tersedia`, `delta_kondisi`, `ref_tipe`, `ref_id`, `user_id`, `catatan`).
  - [x] `audit_logs`: Log audit keamanan (`id`, `user_id`, `aksi`, `entitas`, `entitas_id`, `detail`, `dibuat_pada`).
- [x] **1.3 Model Eloquent & Relasi**:
  - [x] Model `User`, `Division`, `Category`, `Item`, `Loan`, `LoanItem`, `ReturnClaim`, `ReturnClaimLine`, `LoanExtension`, `LoanCorrection`, `StockMovement`, `AuditLog`, `Setting`.
  - [x] Setup `$timestamps = false` dan pemetaan waktu internal.
- [x] **1.4 Seeder Master Data**:
  - [x] `DivisionSeeder`: Divisi awal (IT, HRD, Finance, Operational, Marketing).
  - [x] `CategorySeeder`: Kategori ID 1 "Tanpa Kategori" + kategori IT umum.
  - [x] `SettingSeeder`: 8 konfigurasi kebijakan baku sistem.
- [x] **1.5 Artisan Console Commands (FR-20)**:
  - [x] `php artisan inventaris:buat-admin`: CLI prompt interaktif, password tersembunyi `secret()`.
  - [x] `php artisan inventaris:reset-password {email}`: Reset password admin jika terkunci.
- [x] **1.6 Endpoint & Middleware Auth Admin (FR-11)**:
  - [x] `GET /sanctum/csrf-cookie`
  - [x] `POST /api/auth/login` (rate limited `throttle:5,1`)
  - [x] `POST /api/auth/logout`
  - [x] `GET /api/auth/me`
  - [x] `POST /api/auth/change-password`
  - [x] Middleware `admin.aktif` (tolak jika `aktif == 0`).

---

### ⚡ Fase 2 – Inti Stok & Mesin Transaksi (ACID & Invarian)
- [x] **2.1 Unit & Concurrency Test (TDD: U-01 s.d U-04)**:
  - [x] **U-01**: Uji persaingan stok serentak (konkurensi). 2 request pada sisa 1 unit -> tepat 1 sukses, 1 gagal `409 Conflict`.
  - [x] **U-02**: Uji idempotensi. 2 request dengan `idempotency_key` sama -> hanya 1 transaksi dan kode pinjam terbentuk.
  - [x] **U-03**: Uji invarian acak (1.000 operasi transaksi) -> 0 pelanggaran rumus BR-01.
  - [x] **U-04**: Uji atomic rollback jika proses gagal di tengah transaksi.
- [x] **2.2 Helper & Support Engine**:
  - [x] `App\Support\Tanggal::hariIni()`: Tanggal kalender konsisten zona `Asia/Jakarta` (YYYY-MM-DD).
  - [x] `App\Support\KodeAcak`: Generator kode acak kriptografis 31 karakter alfabet (tanpa `I`, `L`, `O`, `0`, `1`), format `PJM-XXXXXX` dan `KMB-XXXXXX`.
- [x] **2.3 Service Transaksi `PeminjamanService` (FR-02)**:
  - [x] Penanganan idempotensi via `idempotency_key`.
  - [x] Lock ordering baris `items` urut ID menaik (`lockForUpdate()`) anti deadlock.
  - [x] Pengurangan stok atomic bersyarat (`UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :qty`).
  - [x] Pencatatan transaksi peminjaman dan baris mutasi `PINJAM` ke `stock_movements`.
- [x] **2.4 Service Klaim Pengembalian `KlaimService` (FR-05)**:
  - [x] Validasi status pinjam & perhitungan sisa unit yang belum dikembalikan (BR-04).
  - [x] Pembekuan hari keterlambatan (`hari_terlambat = MAX(0, hari_ini - batas_kembali)`) pada `return_claims` (BR-10).
  - [x] Simpan klaim berstatus `menunggu` tanpa mengubah stok barang (BR-03).
- [x] **2.5 Service Verifikasi Klaim `VerifikasiService` (FR-06)**:
  - [x] Kunci baris klaim status `menunggu` (tolak 409 jika sudah diverifikasi / anti verifikasi ganda).
  - [x] Validasi matematis fisik 4 kondisi: `qty_baik + qty_rusak + qty_hilang + qty_ditolak == qty_klaim`.
  - [x] Wajib catatan admin jika ada barang ditolak atau kondisi berbeda (BR-06).
  - [x] Terapkan mutasi stok akhir:
    - `qty_baik > 0` -> `tersedia += qty_baik`, mutasi `VERIFIKASI_BAIK`.
    - `qty_rusak > 0` -> `kondisi += qty_rusak`, mutasi `VERIFIKASI_RUSAK` (`delta_kondisi = +n`).
    - `qty_hilang > 0` -> `total -= qty_hilang`, mutasi `VERIFIKASI_HILANG`.
    - `qty_ditolak > 0` -> unit kembali berstatus dipinjam/terlambat (tanpa mutasi stok).
  - [x] Perbarui status klaim menjadi `'diverifikasi'`.
- [x] **2.6 Status Peminjaman Dinamis (`StatusPeminjaman`)**:
  - [x] Menghitung status turunan (Dipesan, Dibatalkan, Selesai, Menunggu Verifikasi, Terlambat, Dipinjam).
- [x] **2.7 Perintah Verifikasi Invarian (`inventaris:periksa-invarian`)**:
  - [x] Periksa kepatuhan rumus BR-01: `tersedia + kondisi + dipinjam + menunggu_verifikasi == total`.
  - [x] Bandingkan keselarasan angka kumulatif `stock_movements` terhadap `items`.
- [x] **2.8 Endpoint API Kiosk & Admin Terkait**:
  - [x] `GET /api/kiosk/items`: Katalog dan ketersediaan barang.
  - [x] `POST /api/kiosk/loans`: Pembuatan peminjaman langsung (FR-02).
  - [x] `POST /api/kiosk/loans/lookup`: Cek peminjaman kode `PJM-XXXXXX` (dengan rate limit & lockout).
  - [x] `POST /api/kiosk/return-claims`: Pengajuan klaim mandiri (FR-05).
  - [x] `GET /api/admin/return-claims?status=menunggu`: Antrean klaim menunggu verifikasi.
  - [x] `POST /api/admin/return-claims/{id}/verify`: Eksekusi verifikasi admin (FR-06).

---

### 🖥️ Fase 3 – Frontend Kiosk (Layar Sentuh Peminjam)
- [ ] **3.1 Fondasi Frontend & Konfigurasi Kiosk**:
  - [ ] Setup Vue 3 + Tailwind CSS di folder `frontend/`.
  - [ ] Proxy `/api` dan `/sanctum` di `vite.config.js`.
  - [ ] Axios client konfigurasi base URL `/api`.
  - [ ] Router Vue untuk alur rute publik mode Kiosk.
- [ ] **3.2 Komponen Dasar Mode Layar Sentuh**:
  - [ ] `TombolBesar.vue`: Target sentuh besar (minimal tinggi 44 px).
  - [ ] `KartuBarang.vue`: Kartu informasi barang & indikator stok habis.
  - [ ] `BadgeStatus.vue`: Warna status PRD (Ungu, Biru, Merah, Kuning, Hijau, Abu).
  - [ ] `BadgeLencana.vue`: Status kondisi parsial/kerusakan.
  - [ ] `InputJumlah.vue`: Tombol tambah/kurang kuantitas unit.
  - [ ] `InputKode.vue`: Input kode auto-uppercase.
  - [ ] `FormField.vue`, `ModalKonfirmasi.vue`, `NotifikasiToast.vue`.
  - [ ] `StrukCetak.vue`: CSS `@media print` untuk cetak struk bersih tanpa tombol navigasi.
- [ ] **3.3 Halaman & Alur Pengguna Kiosk**:
  - [ ] `/`: Beranda dengan 3 menu aksi utama.
  - [ ] `/katalog`: Pencarian barang, filter pill kategori, indikator stok habis.
  - [ ] `/pinjam`: Form peminjaman langsung (divisi, identitas, batas tanggal, UUID idempotensi).
  - [ ] `/pinjam/selesai`: Struk tampilan kode `PJM-XXXXXX` dan tombol cetak.
  - [ ] `/cek`: Form lookup kode pinjaman dengan lockout screen jika gagal 5x.
  - [ ] `/cek/kartu`: Kartu rincian pinjaman (sembunyikan nomor HP demi privasi BR-18).
  - [ ] `/klaim`: Form pengajuan klaim mandiri & pilihan kondisi barang.
  - [ ] `/klaim/selesai`: Bukti kode `KMB-XXXXXX` dan petunjuk penyerahan fisik.
- [ ] **3.4 Otomasi Kiosk**:
  - [ ] Idle Reset 60 Detik (`useIdleReset.js`): Reset form dan redirect ke `/` saat tanpa interaksi.
  - [ ] Proteksi URL: Kode peminjaman dilarang tampil di query string browser (BR-18).

---

### 🛡️ Fase 4 – Panel Admin (Manajemen Data, Verifikasi & Dasbor)
- [ ] **4.1 Layout & Navigasi Admin**:
  - [ ] `LayoutAdmin.vue`, `Sidebar.vue` (responsif drawer pada tablet/ponsel), `Navbar.vue`.
  - [ ] `TabelData.vue`, `FilterBar.vue`, `KartuAngka.vue`, `LiniMasa.vue`.
- [ ] **4.2 Proteksi Autentikasi Frontend**:
  - [ ] `/admin/login`: CSRF handshake, form kredensial, proteksi rate-limit.
  - [ ] Vue Router Navigation Guard: Validasi sesi admin aktif pada rute `/admin/*`.
  - [ ] Axios Interceptor: Tangani 401 / 419 dan tampilkan dialog sesi kedaluwarsa.
- [ ] **4.3 Dasbor & Antrean Verifikasi**:
  - [ ] `/admin`: 4 metrik utama (Dipinjam, Terlambat, Antrean Verifikasi, Stok Rusak) + auto-refresh tiap 30 detik.
  - [ ] `/admin/verifikasi`: Antrean klaim FIFO berstatus `menunggu`.
  - [ ] `/admin/verifikasi/:id`: Form cek fisik 4 kondisi (`qty_baik`, `qty_rusak`, `qty_hilang`, `qty_ditolak`), validasi selisih nol, dan catatan wajib admin.
- [ ] **4.4 Manajemen Master Data**:
  - [ ] `/admin/barang`: CRUD barang, modal penyesuaian stok manual (FR-13 M), soft-delete aman (tolak 409 jika sedang dipinjam).
  - [ ] `/admin/kategori` & `/admin/divisi`: Tambah, edit, aktifkan/nonaktifkan entitas (tanpa hard delete).
- [ ] **4.5 Manajemen Transaksi Peminjaman**:
  - [ ] `/admin/peminjaman`: Filter multi-kriteria, pencarian, paginasi.
  - [ ] `/admin/peminjaman/:id`: Detail peminjam, lini masa riwayat transaksi, rincian unit fisik.
- [ ] **4.6 Halaman Status Galat**: `/403`, `/404`, `/419`.

---

### 📄 Fase 5 – Laporan, Jejak Audit & Pengaturan (Puncak MVP)
- [ ] **5.1 Engine Pembuatan Laporan PDF di Sisi Server (FR-17)**:
  - [ ] Instalasi pustaka PDF server (`barryvdh/laravel-dompdf`).
  - [ ] View Blade template PDF standar A4, kop resmi, penomoran dan tanggal waktu `Asia/Jakarta`.
  - [ ] Laporan Rekapitulasi Peminjaman (`/api/admin/reports/rekap.pdf`).
  - [ ] Laporan Barang Belum Dikembalikan (`/api/admin/reports/belum-kembali.pdf`).
  - [ ] Laporan Stok & Kondisi Barang (`/api/admin/reports/stok.pdf`).
- [ ] **5.2 Antarmuka Laporan Admin**:
  - [ ] `/admin/laporan`: Pilihan jenis laporan, filter interaktif, pratinjau dan unduh PDF.
- [ ] **5.3 Antarmuka Jejak Audit (FR-18 UI)**:
  - [ ] `/admin/audit`: Penampil log keamanan read-only, rincian JSON, filter entitas & rentang tanggal.
- [ ] **5.4 Antarmuka Pengaturan Parameter Kebijakan (FR-19)**:
  - [ ] `/admin/pengaturan`: Form konfigurasi 8 parameter operasional dengan validasi batas nilai.
  - [ ] Otomatis pencatatan perubahan setting ke `audit_logs`.
- [ ] **5.5 Manajemen Akun Administrator (FR-20 UI)**:
  - [ ] `/admin/akun`: Daftar admin, tambah akun, nonaktifkan akun, ganti password mandiri.
- [ ] **5.6 Desain Cetak Bersih (UI-06)**: Penyesuaian Tailwind print layout.

---

### 🚀 Fase 6 – Fitur Lanjutan (Pemesanan, Perpanjangan, Pembatalan, Koreksi & Bengkel)
- [ ] **6.1 Pemesanan Barang (Booking) & Pembatalan (FR-03, FR-09)**:
  - [ ] Pemesanan masa depan (`tanggal_pinjam > hari_ini`), penahanan stok langsung, status `Dipesan`.
  - [ ] Pembatalan mandiri peminjam (sebelum hari H).
  - [ ] Pembatalan oleh admin (sebelum ada klaim pengembalian fisik), kembalikan unit ke `tersedia`, catat `BATAL_PINJAM`.
- [ ] **6.2 Perpanjangan Masa Peminjaman (FR-07, FR-08)**:
  - [ ] Perpanjangan mandiri kiosk: Validasi batas kuota (maks 2x), belum terlambat, flag `boleh_diperpanjang = 1`.
  - [ ] Perpanjangan diskresi admin via panel: Diizinkan untuk yang terlambat, alasan wajib diisi.
- [ ] **6.3 Service Koreksi Peminjaman (FR-10)**:
  - [ ] Koreksi kesalahan data peminjam & kuantitas (tolak jika sudah ada klaim fisik).
  - [ ] Rekam histori perubahan pada `loan_corrections`.
- [ ] **6.4 Siklus Bengkel Stok Rusak (FR-13 Bagian S)**:
  - [ ] `/admin/stok-rusak`: Antarmuka manajemen barang berkondisi rusak (`kondisi > 0`).
  - [ ] Aksi Tandai Rusak (Opname): `tersedia -= n`, `kondisi += n`, mutasi `TANDAI_RUSAK`.
  - [ ] Aksi Selesai Diperbaiki: `kondisi -= n`, `tersedia += n`, mutasi `PERBAIKAN_SELESAI`.
  - [ ] Aksi Afkir (Pemusnahan): `kondisi -= n`, `total -= n`, mutasi `AFKIR`.
- [ ] **6.5 Layar Kiosk Lanjutan & Ekspor Data**:
  - [ ] Rute kiosk `/perpanjang`, `/perpanjang/selesai`, `/batal`.
  - [ ] Ekspor data daftar peminjaman & stok ke spreadsheet CSV/Excel (FR-22).

---

### 🏆 Fase 7 – Pengujian Penuh, Hardening Keamanan, Dokumentasi & Rilis
- [ ] **7.1 Eksekusi Uji Otomatis Lengkap**:
  - [ ] Baseline test: `T-5.1` s.d `T-5.6`.
  - [ ] Uji Invarian & Keamanan: `U-01` s.d `U-08` (konkurensi, idempotensi, invarian BR-01, rollback, XSS, responsivitas).
  - [ ] Uji Antarmuka Pengguna: `UI-01` s.d `UI-08`.
- [ ] **7.2 Pengujian Kinerja (NFR Benchmark)**:
  - [ ] Uji muat 5.000 data transaksi: render tabel daftar admin < 2 detik.
  - [ ] Uji respons transaksi peminjaman: POST `/api/kiosk/loans` < 1 detik.
  - [ ] Analisis dan indeks query anti N+1.
- [ ] **7.3 Penguatan Keamanan (Hardening)**:
  - [ ] `APP_DEBUG=false`, `APP_ENV=production`.
  - [ ] Hak akses minimal database dan pembatasan binding MySQL ke `127.0.0.1`.
  - [ ] Konfigurasi firewall port lokal.
- [ ] **7.4 Dokumentasi Operasional PHPBRO & Backup**:
  - [ ] Panduan setup dan konfigurasi PHPBRO (Adminer).
  - [ ] Skrip backup berkala harian (`mysqldump`) dan uji simulasi restore database.
- [ ] **7.5 Build Produksi Frontend**: `npm run build` dan pembersihan sisa berkas boilerplate.
- [ ] **7.6 UAT & Go-Live**: Uji coba bersama pengguna kantor, penandatanganan serah terima, dan peluncuran resmi.

---

## 📝 Catatan Pekerjaan Proyek (Work Log & Catatan Teknis)





### ⚠️ Catatan Penting & Pedoman Teknis Pengembang
1. **Integritas Database**:
   - Database kerja utama: `inventaris_db`. Database pengujian otomatis: `inventaris_test`.
   - Dilarang keras menyentuh basis data lain di server lokal (`inventaris_it_dev`, `solopos_membership`).
   - Dilarang menjalankan perintah `DROP DATABASE`. Operasi `migrate:fresh` hanya boleh dijalankan setelah memastikan nama database yang aktif adalah `inventaris_db`.
2. **Prinsip Perubahan Skema**:
   - Selama tabel belum pernah dirilis ke produksi kantor, perbaikan/penyesuaian skema dilakukan langsung dengan mengedit file migrasi aslinya, lalu menjalankan `migrate:fresh`. Hindari pembuatan migration baru berulang yang menumpuk.
3. **Logika Transaksi Stok (ACID & Konkurensi)**:
   - Nilai stok **TIDAK BOLEH** diubah dengan cara baca lalu tulis (`$item->tersedia -= $qty; $item->save()`).
   - Perubahan stok wajib menggunakan query atomic bersyarat langsung di SQL server:
     ```sql
     UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :qty
     ```
   - Seluruh mutasi stok wajib mencatat baris baru di tabel buku besar `stock_movements` dalam satu `DB::transaction()`.
   - Baris klaim pengembalian berstatus `menunggu` **tidak mengubah stok** fisik hingga admin melakukan verifikasi fisik.
4. **Prosedur Git & Rilis**:
   - AI dilarang melakukan `git add -A`, `git commit`, atau `git push` otomatis. Seluruh commit dan push dilakukan secara manual oleh pemilik repositori setelah peninjauan daftar perubahan file.


### 📅 Log Riwayat Pengerjaan
* **2026-10-23 – Fase 0 & 1 Selesai**:
  - Konfigurasi lingkungan pengembangan (PHP 8.2+, Laravel 13, MySQL 8.4, Node.js/Vue 3).
  - Skema basis data 11 tabel domain + sistem berhasil dibuat dengan aturan integritas ketat (seluruh FK memakai `RESTRICT`, tidak ada `CASCADE`).
  - Model Eloquent, seeder data baku (`DivisionSeeder`, `CategorySeeder`, `SettingSeeder`), perintah Artisan admin (`inventaris:buat-admin`, `inventaris:reset-password`), dan endpoint autentikasi Sanctum SPA selesai dan teruji.
* **2026-10-28 – Konsolidasi & Persiapan Fase 2**:
  - Penyatuan seluruh dokumen perencanaan `fase0.md` hingga `fase7.md` ke dalam satu dokumen terpadu `todo.md`.
  - Penegasan kembali standarisasi kolom stok: menggunakan `kondisi` pada tabel `items` (kuantitas unit rusak fisik) dan `delta_kondisi` pada buku besar `stock_movements`.
  - Persiapan eksekusi TDD untuk Fase 2: menulis rangkaian pengujian unit dan konkurensi (U-01 s.d. U-04) sebelum implementasi service logika transaksi.

