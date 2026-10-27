# Fase 5 – Laporan, Jejak Audit & Pengaturan (Puncak MVP)

Dokumen ini merupakan panduan pelaksanaan **Fase 5** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Menuntaskan seluruh fitur prioritas **Must (M)** yang tersisa untuk mencapai **Milestone MVP (Minimum Viable Product)**:
1. Pembuatan dokumen laporan PDF resmi di sisi server dengan filter data yang akurat.
2. Menyediakan antarmuka jejak audit (*audit logs*) untuk transparansi dan akuntabilitas tindakan penting admin.
3. Antarmuka pengaturan parameter kebijakan operasional dan manajemen akun administrator.
4. **Pencapaian Titik MVP**: Sistem telah lengkap dan siap menggantikan buku catatan manual kantor untuk seluruh alur utama (pinjam, klaim, verifikasi pengembalian, dan pelaporan pimpinan).

---

## 📋 FR yang Dicakup
- **FR-17**: Pembuatan 3 jenis laporan PDF resmi di server (rekap peminjaman, belum dikembalikan, stok dan kondisi barang).
- **FR-18 (UI)**: Halaman penampil jejak audit (*audit logs*) yang bersifat hanya-baca (*read-only*).
- **FR-19**: Antarmuka konfigurasi 8 parameter kebijakan operasional sistem dengan validasi rentang nilai.
- **FR-20 (UI)**: Halaman manajemen akun administrator (tambah akun, nonaktifkan, ubah password).

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Engine Pembuatan Laporan PDF di Sisi Server (FR-17)
- [ ] Pasang pustaka PDF server-side di backend (misal: `barryvdh/laravel-dompdf`).
- [ ] Buat `App\Services\LaporanService` dan view template Blade PDF:
  - Template disiapkan dengan ukuran kertas A4, margin standar, kop dokumen tim IT kantor, nomor dokumen, tanggal & waktu cetak (`Asia/Jakarta`), dan nama admin pembuat laporan.
- [ ] Implementasikan 3 jenis laporan PDF:
  1. **Laporan Rekapitulasi Peminjaman (`rekap`)**:
     - Parameter filter: rentang tanggal pinjam (`dari`, `sampai`), status, divisi, tipe peminjam.
     - Berisi ringkasan total transaksi, rincian daftar peminjaman, jumlah unit dipinjam, dan status penyelesaian.
  2. **Laporan Barang Belum Dikembalikan (`belum-kembali`)**:
     - Menampilkan seluruh peminjaman yang berstatus `Dipinjam` atau `Terlambat`.
     - Dilengkapi informasi kontak peminjam, tanggal jatuh tempo, dan jumlah hari keterlambatan.
  3. **Laporan Stok & Kondisi Barang (`stok`)**:
     - Menampilkan posisi inventaris terkini per kategori barang: total unit fisik, unit tersedia di rak, unit sedang dipinjam, dan unit rusak.
- [ ] Controller `Admin\LaporanController@show`:
  - Rute: `GET /api/admin/reports/{jenis}.pdf`.
  - Mengambil filter yang sama persis dengan tabel daftar peminjaman admin, men-generate file stream PDF untuk langsung diunduh oleh browser.

### 2. Antarmuka Laporan Panel Admin
- [ ] **`/admin/laporan` (Halaman Laporan)**:
  - Pilihan kartu untuk 3 jenis laporan.
  - Filter interaktif: rentang tanggal, kategori, divisi, status transaksi.
  - Tombol aksi *Pratinjau* dan *Unduh PDF*.

### 3. Antarmuka Jejak Audit (FR-18 UI)
- [ ] **`/admin/audit` (Halaman Jejak Audit)**:
  - Mengambil data dari `GET /api/admin/audit-logs`.
  - Tabel bersifat **hanya-baca (read-only)** tanpa tombol edit atau hapus.
  - Kolom tabel: Waktu kejadian (`Asia/Jakarta`), Nama Administrator (`user.name`), Aksi (`LOGIN`, `VERIFIKASI_KLAIM`, `PENYESUAIAN_STOK`, `UBAH_PENGATURAN`, dll), Entitas, ID Entitas, Rincian Perubahan (JSON payload terformat rapi).
  - Filter pencarian berdasarkan entitas dan rentang tanggal kejadian.
  - Pagination server-side.

### 4. Antarmuka Pengaturan Parameter Kebijakan (FR-19)
- [ ] **`/admin/pengaturan` (Halaman Pengaturan)**:
  - Mengambil konfigurasi dari `GET /api/admin/settings`.
  - Form input interaktif untuk 8 parameter kebijakan operasional dengan validasi batas nilai:
    1. `maks_durasi_awal_hari`: Batas maksimal durasi peminjaman awal (default 7 hari, rentang 1–30).
    2. `maks_perpanjangan`: Kuota maksimal perpanjangan mandiri oleh peminjam (default 2 kali, rentang 0–5).
    3. `maks_hari_per_perpanjangan`: Durasi tambahan per perpanjangan (default 7 hari, rentang 1–14).
    4. `maks_booking_hari`: Batas maksimal tanggal pemesanan ke depan (default 7 hari, rentang 1–30).
    5. `sesi_idle_menit`: Batas idle sesi login admin sebelum logout otomatis (default 15 menit, rentang 5–60).
    6. `kiosk_idle_detik`: Batas idle layar kiosk sebelum reset ke beranda (default 60 detik, rentang 30–300).
    7. `kiosk_lookup_maks_gagal`: Batas kegagalan input kode sebelum terkunci (default 5 kali, rentang 3–10).
    8. `kiosk_lookup_kunci_menit`: Durasi lockout kiosk setelah gagal berulang (default 5 menit, rentang 1–60).
  - Submit ke `PUT /api/admin/settings`. Nilai baru langsung berlaku secara instan pada transaksi peminjaman berikutnya tanpa perlu restart aplikasi.
  - Setiap perubahan pengaturan otomatis mencatat 1 baris jejak di `audit_logs`.

### 5. Antarmuka Manajemen Akun Admin (FR-20 UI)
- [ ] **`/admin/akun` (Halaman Akun Administrator)**:
  - Mengambil daftar akun admin dari `GET /api/admin/users`.
  - Modal *Tambah Admin*: Input nama, email unik, dan password baru yang kuat.
  - Aksi *Nonaktifkan / Aktifkan*: Mengubah status `aktif` (0 atau 1). Akun yang dinonaktifkan dilarang login dan sesinya langsung dibatalkan (tidak dihapus fisik karena dirujuk oleh foreign key transaksi dan audit).
  - Fitur *Ganti Password*: Form ganti password untuk admin yang sedang login dengan verifikasi password lama.

### 6. Desain Cetak Bersih (UI-06)
- [ ] Konfigurasi aturan Tailwind `@media print`:
  - Elemen navigasi samping (`Sidebar`), navigasi atas (`Navbar`), tombol aksi cetak, dan elemen pembungkus di-hide otomatis (`display: none`).
  - Halaman tercetak bersih berisi kop resmi, tabel data, dan tanda tangan penanggung jawab.

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 5 – Titik MVP)
Fase 5 dinyatakan **SELESAI** jika:
- [ ] **G5 (Laporan Andal)**: Ketiga dokumen PDF (rekapitulasi, belum kembali, stok & kondisi) berhasil diunduh dan datanya cocok 100% dengan database dan filter yang dipilih.
- [ ] **Integritas Pengaturan**: Nilai pengaturan baru yang disimpan melalui UI langsung diterapkan pada validasi form transaksi berikutnya.
- [ ] **Audit Trail Lengkap**: Setiap tindakan penting (login, verifikasi fisik, penyesuaian stok, perubahan pengaturan, manajemen user) menghasilkan baris data baru di tabel `audit_logs`.
- [ ] **UI-06**: Hasil cetak laporan dan struk fisik bersih dari seluruh elemen navigasi web.

> [!NOTE]
> **PENCAPAIAN TONGGAK MVP**:
> Setelah Fase 5 tuntas, sistem inti sudah utuh dan dapat mulai dioperasikan di kantor untuk menggantikan buku catatan manual. Fitur-fitur tambahan (pemesanan masa depan, perpanjangan mandiri, pembatalan, koreksi, dan bengkel stok rusak) akan dikerjakan pada Fase 6.
