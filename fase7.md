# Fase 7 – Pengujian Penuh, Hardening Keamanan, Dokumentasi & Rilis

Dokumen ini merupakan panduan pelaksanaan **Fase 7 (Fase Terakhir)** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Memastikan sistem aplikasi **100% benar, aman, tahan beban, terdokumentasi, dan siap operasional kantor**:
1. Menjalankan seluruh rangkaian skenario uji otomatis (*baseline test*, konkurensi, invarian, keamanan XSS, responsivitas UI).
2. Verifikasi target kinerja sistem (halaman daftar < 2 detik pada 5.000 data transaksi, pemrosesan transaksi < 1 detik).
3. Penguatan keamanan (*security hardening*): `APP_DEBUG=false`, sanitasi input, pembatasan port firewall, dan hak akses database minimal.
4. Penyusunan dokumentasi setup PHPBRO (Adminer) dan prosedur backup/restore database terjadwal.
5. Uji Penerimaan Pengguna (*User Acceptance Testing* / UAT) dan rilis final ke PC kantor.

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Eksekusi Seluruh Rangkaian Skenario Uji Otomatis
- [ ] **Skenario Baseline PRD (T-5.1 s.d. T-5.6)**:
  - `T-5.1`: Buka rute panel admin tanpa login -> dialihkan/ditolak `401 Unauthorized`.
  - `T-5.2`: Buat peminjaman, lalu coba meminjam melebihi stok yang ada -> stok berkurang tepat, peminjaman kedua ditolak server.
  - `T-5.3`: Klaim pengembalian parsial dan verifikasi (baik, rusak, hilang) -> stok berubah sesuai BR-05, hilang mengurangi total, verifikasi ulang klaim yang sama ditolak (409).
  - `T-5.4`: Cek status peminjaman yang melewati batas jatuh tempo -> status `Terlambat` dan jumlah hari dihitung server.
  - `T-5.5`: Hapus barang yang sedang dipinjam (ditolak 409), lalu hapus barang yang bebas pinjaman (berhasil soft-delete).
  - `T-5.6`: Kiosk: masukkan kode salah berulang 5 kali -> form lookup terkunci selama 5 menit tanpa membocorkan data.
- [ ] **Skenario Invarian & Keamanan (U-01 s.d. U-08)**:
  - `U-01 (Konkurensi)`: Dua request serentak pada barang berstok 1 -> tepat 1 berhasil, 1 gagal 409 (G2).
  - `U-02 (Idempotensi)`: Kirim form peminjaman dua kali dengan kunci UUID yang sama -> hanya 1 transaksi terbentuk.
  - `U-03 (Pemeriksaan Invarian BR-01)`: Jalankan simulasi 1.000 transaksi acak -> `php artisan inventaris:periksa-invarian` melaporkan **0 pelanggaran** (G1).
  - `U-04 (Atomic Rollback)`: Transaksi gagal di tengah proses tidak meninggalkan data parsial.
  - `U-05 (Hash Password)`: Seluruh password tersimpan dalam bentuk hash bcrypt/argon2id.
  - `U-06 (Uji XSS)`: Masukkan karakter payload `<script>alert(1)</script>` pada semua kolom input -> ditampilkan sebagai teks biasa (escaped), tidak dieksekusi browser.
  - `U-07 (Cetak Bersih)`: Cetak struk dan laporan PDF -> bersih tanpa elemen navigasi.
  - `U-08 (Responsivitas Device)`: Antarmuka kiosk dibuka di tablet dan panel admin di ponsel -> tata letak rapi, tanpa elemen terpotong.
- [ ] **Skenario UI (UI-01 s.d. UI-08)**:
  - Seluruh kriteria penerimaan antarmuka kiosk dan admin telah diverifikasi lolos uji.

### 2. Pengujian Kinerja (Performance Benchmark NFR)
- [ ] Generate data pengujian sebanyak 5.000 transaksi peminjaman di database.
- [ ] Uji kecepatan halaman:
  - Membuka halaman daftar transaksi peminjaman admin dengan 5.000 data memakan waktu **kurang dari 2 detik**.
  - Waktu respons pengajuan peminjaman (POST `/api/kiosk/loans`) diproses server dalam waktu **kurang dari 1 detik**.
- [ ] Optimasi query database: pastikan indeks tabel (`batas_kembali`, `tipe_peminjam`, `kategori_id`, `idempotency_key`) aktif dan tidak terjadi *N+1 query issue*.

### 3. Penguatan Keamanan (*Hardening*)
- [ ] Set `APP_DEBUG=false` dan `APP_ENV=production` pada file `.env` produksi.
- [ ] Konfigurasi akun database aplikasi: jangan menggunakan user `root` tanpa password; buat user khusus dengan hak akses terbatas ke database `inventaris_db`.
- [ ] Pastikan MySQL 8.4 di PHPBRO hanya mendengarkan (*listen*) pada antarmuka lokal `127.0.0.1` (port 3306 tidak terbuka ke jaringan LAN kantor).
- [ ] Atur Windows Firewall pada PC server: hanya buka port HTTP aplikasi ke jaringan lokal kantor.
- [ ] Pastikan file rahasia (`.env`, berkas dump database SQL, kredensial) tidak masuk ke pelacakan Git.

### 4. Dokumentasi Operasional di PHPBRO (Adminer)
- [ ] Buat dokumen panduan instalasi dan konfigurasi di lingkungan **PHPBRO (menggunakan Adminer)**.
- [ ] Prosedur backup database berkala:
  - Buat skrip otomatis `mysqldump` terjadwal harian ke folder backup terproteksi.
  - Jalankan **simulasi uji pulih (restore)** database minimal 1 kali untuk membuktikan berkas backup dapat dipulihkan dengan sempurna saat terjadi insiden.
- [ ] Dokumentasikan kontak penanggung jawab sistem (Admin Utama dan Admin Pengganti).

### 5. Build Produksi & Pembersihan Berkas
- [ ] Jalankan kompilasi frontend untuk lingkungan produksi:
  ```powershell
  cd frontend
  npm run build
  ```
- [ ] Bersihkan sisa bawaan template backend yang tidak dipakai (`backend/resources/views`, `backend/resources/js`, dll).

### 6. Uji Penerimaan Pengguna (UAT) & Rilis Kantor
- [ ] Lakukan sesi User Acceptance Testing (UAT) bersama calon pengguna:
  - Perwakilan peminjam mencoba alur mandiri kiosk (katalog -> pinjam -> dapat struk -> cek kartu -> klaim kembali).
  - Petugas inventaris IT mencoba verifikasi klaim, pemantauan dasbor, dan cetak laporan PDF.
- [ ] Dapatkan persetujuan (*sign-off*) dari pihak kantor.
- [ ] Sistem resmi mulai beroperasi penuh (*Go-Live*).

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 7 – Rilis Final)
Proyek Sistem Peminjaman & Inventaris Barang Tim IT dinyatakan **SELESAI LENGKAP** jika:
- [ ] Seluruh skenario uji otomatis di PRD bagian 6.11 (T-5.1 s.d T-5.6, U-01 s.d U-08, UI-01 s.d UI-08) **lolos 100%**.
- [ ] Target kinerja NFR tercapai (< 2 detik untuk daftar 5.000 transaksi, < 1 detik transaksi peminjaman).
- [ ] Audit keamanan lolos (`APP_DEBUG=false`, sanitasi XSS terbukti, port terlindungi).
- [ ] UAT disetujui oleh pengguna dan penanggung jawab tim IT.
- [ ] Prosedur backup harian dan uji pulih database di PHPBRO berhasil dijalankan sekali.
- [ ] Aplikasi resmi berjalan di PC operasional kantor dan siap menggantikan buku catatan manual.
