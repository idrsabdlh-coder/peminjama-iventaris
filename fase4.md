# Fase 4 – Panel Admin (Manajemen Data, Verifikasi & Dasbor)

Dokumen ini merupakan panduan pelaksanaan **Fase 4** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Membangun antarmuka dan alur kerja **Panel Admin** yang aman dan komprehensif:
1. Administrator dapat mengelola master data (barang, kategori, divisi) dan melakukan penyesuaian stok manual dengan alasan yang dapat dipertanggungjawabkan.
2. Petugas inventaris dapat memproses verifikasi fisik pengembalian barang (*checking*) secara akurat dengan validasi selisih kuantitas nol sebelum penyimpanan.
3. Memantau kesehatan inventaris dan transaksi peminjaman aktif melalui dasbor ringkasan angka statistik (*real-time* berkala).

---

## 📋 FR yang Dicakup
- **FR-06 (UI)**: Antarmuka antrean verifikasi dan form input hasil cek fisik barang (baik, rusak, hilang, ditolak).
- **FR-11 (UI)**: Halaman login admin dengan penanganan sesi Sanctum SPA, pesan pembatasan laju (*rate limit*), dan *logout*.
- **FR-12**: Manajemen barang (tambah, edit, soft-delete yang terlindungi).
- **FR-13 (Bagian M)**: Penyesuaian stok (*stock adjustment*) oleh admin disertai alasan wajib.
- **FR-14**: Manajemen kategori dan divisi (tambah, edit nama, aktifkan/nonaktifkan; tanpa hapus fisik).
- **FR-15**: Tabel daftar peminjaman dengan filter komprehensif, pencarian, paginasi, dan halaman detail lini masa peminjaman.
- **FR-16**: Dasbor statistik (barang dipinjam, keterlambatan, antrean verifikasi, stok rusak) dengan auto-refresh tiap 30 detik.

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Layout & Navigasi Panel Admin
- [ ] **`LayoutAdmin.vue`**:
  - Kerangka utama admin yang membungkus seluruh halaman di bawah `/admin`.
  - **`Sidebar.vue`**: Menu navigasi samping (Dasbor, Peminjaman, Antrean Verifikasi, Barang, Kategori, Divisi). Pada layar lebar (desktop) tampil permanen di kiri; pada tablet/smartphone (lebar < 768 px) berubah menjadi menu laci geser (*drawer*).
  - **`Navbar.vue`**: Bar navigasi atas menampilkan nama admin yang sedang aktif, tombol ganti password, dan tombol Logout.
- [ ] Komponen pendukung panel admin:
  - **`TabelData.vue`**: Tabel serbaguna dengan header pengurutan, pagination server-side, dan mode kartu pada layar kecil.
  - **`FilterBar.vue`**: Baris kontrol pencarian dan filter dropdown.
  - **`KartuAngka.vue`**: Widget kartu ringkasan metrik statistik dasbor dengan ikon dan angka kontras.
  - **`LiniMasa.vue`**: Visualisasi kronologis kejadian transaksi (waktu peminjaman, perpanjangan, pengajuan klaim, verifikasi).

### 2. Autentikasi & Proteksi Akses Frontend
- [ ] **`/admin/login` (Halaman Login Admin)**:
  - Form input email dan password.
  - Mengambil cookie CSRF ke `/sanctum/csrf-cookie` sebelum mengirim request login.
  - Menampilkan pesan error validasi (422) atau peringatan laju percobaan (429).
  - Setelah berhasil, simpan data admin di Pinia/Vue store dan arahkan ke `/admin`.
- [ ] Router Navigation Guard (`frontend/src/router/index.js`):
  - Memeriksa sesi admin aktif setiap membuka rute `/admin/*`.
  - Jika belum login atau sesi berakhir, arahkan ke `/admin/login`.
- [ ] Axios Interceptor:
  - Tangkap respons `401 Unauthorized` atau `419 Authentication Timeout/CSRF Expired`.
  - Tampilkan toast: *"Sesi Anda telah berakhir, silakan masuk kembali"* dan redirect ke `/admin/login`.

### 3. Layar Dasbor & Antrean Verifikasi
- [ ] **`/admin` (Dasbor)**:
  - Memuat data dari `GET /api/admin/summary`.
  - 4 Kartu Angka utama: *Sedang Dipinjam*, *Terlambat*, *Menunggu Verifikasi*, *Stok Rusak*.
  - Tabel ringkas peminjaman yang telah melewati batas kembali (Terlambat).
  - Setup polling interval yang memperbarui data setiap 30 detik secara otomatis.
- [ ] **`/admin/verifikasi` (Antrean Verifikasi Klaim)**:
  - Memuat klaim berstatus `menunggu` dari `GET /api/admin/return-claims?status=menunggu`.
  - Urutkan dari klaim paling lama (FIFO).
  - Tombol aksi menuju halaman form verifikasi spesifik.
- [ ] **`/admin/verifikasi/:id` (Form Verifikasi Klaim Fisik)**:
  - Tampilkan data peminjam, kode `KMB-XXXXXX`, tanggal pengajuan, dan hari terlambat yang dibekukan.
  - Tabel rincian barang: untuk setiap baris barang yang diklaim, sediakan 4 input hasil pemeriksaan fisik:
    1. `qty_baik`
    2. `qty_rusak`
    3. `qty_hilang`
    4. `qty_ditolak`
  - Validasi selisih kuantitas secara interaktif: Tampilkan indikator selisih `qty_klaim - (baik + rusak + hilang + ditolak)`.
  - **Tombol Simpan HANYA AKTIF bila selisih = 0**.
  - Kolom `catatan_admin` menjadi **wajib diisi** jika terdapat barang yang dilaporkan rusak/hilang atau jika ada barang yang ditolak.
  - Modal konfirmasi final sebelum mengirim `POST /api/admin/return-claims/{id}/verify`.

### 4. Manajemen Master Data (Barang, Kategori, Divisi)
- [ ] **`/admin/barang`**:
  - Daftar barang aktif dengan pagination, pencarian nama, dan filter kategori.
  - Modal/Form Tambah & Edit Barang: Nama barang, deskripsi, kategori, total stok awal, dan toggle `boleh_diperpanjang`.
  - Aksi **Penyesuaian Stok (FR-13 Bagian M)**:
    Modal input untuk menambah atau mengurangi `total` stok barang secara manual dengan **alasan wajib diisi**. Request dikirim ke `POST /api/admin/items/{id}/stock-adjustments`.
  - Aksi **Hapus Barang (Soft-Delete)**:
    - Tombol Hapus dengan `ModalKonfirmasi`.
    - Jika barang masih memiliki unit yang berstatus dipinjam atau sedang menunggu verifikasi, server menolak dengan `409 Conflict`.
    - Jika bersih, server mengisi timestamp `dihapus_pada` (data transaksi masa lalu tetap aman).
- [ ] **`/admin/kategori` & `/admin/divisi` (FR-14)**:
  - Daftar tabel divisi dan kategori.
  - Tambah entitas baru dan ubah nama.
  - Aksi Nonaktifkan: Mengubah kolom `aktif = 0` (tidak ada fitur hapus fisik / hard delete). Kategori/divisi nonaktif tidak akan muncul di form kiosk, tetapi riwayat transaksi lama tetap terbaca utuh.

### 5. Manajemen Daftar & Detail Peminjaman (FR-15)
- [ ] **`/admin/peminjaman`**:
  - Filter lengkap: Status (`Dipesan`, `Dipinjam`, `Terlambat`, `Menunggu Verifikasi`, `Selesai`, `Dibatalkan`), kategori, barang, divisi, tipe peminjam, rentang tanggal pinjam, teks pencarian kode/nama.
  - BadgeStatus seragam di setiap baris data.
- [ ] **`/admin/peminjaman/:id`**:
  - Rincian lengkap peminjam (nama, divisi, tipe, telepon), kode transaksi `PJM-XXXXXX`.
  - Rincian unit barang: kuantitas awal, unit kembali baik, unit rusak, unit hilang, unit yang masih dipegang.
  - Komponen `LiniMasa`: jejak riwayat peminjaman dari awal dibuat, perpanjangan, hingga klaim pengembalian.

### 6. Halaman Status & Galat
- [ ] Siapkan tampilan halaman standar `/403` (Akses ditolak), `/404` (Halaman tidak ditemukan), dan `/419` (Sesi kedaluwarsa).

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 4)
Fase 4 dinyatakan **SELESAI** jika:
- [ ] **T-5.3 (Alur Verifikasi UI)**: Melakukan klaim di kiosk lalu verifikasi lewat UI admin berhasil memperbarui stok dengan benar; percobaan verifikasi ulang pada klaim yang sama ditolak (409).
- [ ] **T-5.4 (Keterlambatan)**: Status `Terlambat` dan jumlah hari keterlambatan terbukti dihitung otomatis oleh server saat batas kembali terlewati.
- [ ] **T-5.5 (Soft-Delete Terproteksi)**: Menghapus barang yang sedang dipinjam ditolak oleh server; menghapus barang yang tidak dipinjam sukses melakukan soft-delete.
- [ ] **UI-04 (Konsistensi Status)**: Komponen `BadgeStatus` terbukti menampilkan label dan warna yang identik di kartu kiosk, tabel admin, dan halaman detail transaksi.
- [ ] **UI-05 (Responsivitas)**: Panel admin tampil sempurna tanpa elemen terpotong pada resolusi 360 px (mode kartu) dan 768 px (tablet).
- [ ] **UI-08 (Sesi Idle)**: Membiarkan sesi admin idle selama 15 menit memicu pengalihan ke halaman login dengan pesan yang informatif.
- [ ] **Akurasi Metrik Dasbor**: Angka total barang dipinjam, terlambat, dan antrean klaim pada dasbor cocok 100% dengan data pada tabel.
