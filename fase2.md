# Fase 2 – Inti Stok & Mesin Transaksi

Dokumen ini merupakan panduan pelaksanaan **Fase 2** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Membangun dan memvalidasi **jantung logika bisnis aplikasi**:
Stok barang hanya boleh berubah melalui transaksi tervalidasi di sisi server (ACID) yang mencatat mutasi ke buku besar `stock_movements`. Menegakkan aturan invarian matematis stok (**BR-01**) dan ketahanan terhadap *race condition* (konkurensi).

> [!CAUTION]
> Ini adalah fase paling kritis dan berisiko tinggi dalam keseluruhan proyek. Jangan melanjutkan ke Fase 3 sebelum seluruh skenario uji konkurensi (U-01) dan invarian stok (U-03) lolos 100%.

---

## 📋 FR yang Dicakup
- **FR-01 (API)**: Cek katalog & ketersediaan stok barang aktif.
- **FR-02 (API)**: Form peminjaman langsung hari ini, penahanan stok, pembuatan kode unik `PJM-XXXXXX`.
- **FR-04 (API)**: Lookup cek peminjaman berdasarkan kode dengan proteksi pembatasan kegagalan (*lockout*).
- **FR-05 (API)**: Klaim pengembalian parsial/penuh oleh peminjam, pembuatan kode `KMB-XXXXXX`.
- **FR-06 (API)**: Verifikasi klaim pengembalian oleh admin (baik, rusak, hilang, ditolak) & mutasi stok akhir.
- **FR-18**: Pencatatan buku besar mutasi stok (`stock_movements`) dan audit log.

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Helper & Support Engine
- [ ] **`App\Support\Tanggal`**:
  Helper tanggal sentral `Tanggal::hariIni()` yang selalu mengembalikan tanggal kalender dalam zona waktu `Asia/Jakarta` (YYYY-MM-DD), memastikan perhitungan keterlambatan tidak bias oleh jam server.
- [ ] **`App\Support\KodeAcak`**:
  Generator kode acak kriptografis (`random_int`) dengan 31 karakter alfabet (tanpa karakter ambigu: `I`, `L`, `O`, `0`, `1`). Format: `PJM-XXXXXX` (peminjaman) dan `KMB-XXXXXX` (klaim).

### 2. Service `PeminjamanService`
- [ ] Penanganan kunci idempotensi: Periksa `idempotency_key` di tabel `loans`. Jika sudah ada, kembalikan data transaksi yang telah dibuat tanpa memproses ulang.
- [ ] Urutan penguncian baris (*lock ordering*): Mengunci baris `items` dengan `lockForUpdate()` dengan urutan `id` menaik (`ORDER BY id ASC`) untuk mencegah *deadlock*.
- [ ] Pengurangan stok bersyarat:
  Eksekusi query atomic:
  `UPDATE items SET tersedia = tersedia - :qty WHERE id = :id AND tersedia >= :qty`
  Jika `affected rows == 0`, batalkan dan lempar eksepsi `409 Conflict` (Stok barang tidak mencukupi).
- [ ] Simpan `loans`, `loan_items`, dan tulis baris mutasi `PINJAM` ke `stock_movements` dalam satu `DB::transaction()`.

### 3. Service `KlaimService`
- [ ] Validasi transaksi peminjaman: Kunci baris `loans`, tolak jika transaksi berstatus dibatalkan atau tanggal pinjam di masa depan.
- [ ] Hitung sisa unit yang belum dikembalikan per `loan_items` (dipinjam dikurangi unit yang sudah diverifikasi baik/rusak/hilang dan unit yang sedang menunggu verifikasi).
- [ ] Validasi bahwa jumlah barang yang diklaim tidak melebihi sisa unit (BR-04).
- [ ] Pembekuan keterlambatan: Simpan nilai `hari_terlambat = MAX(0, hari_ini - batas_kembali)` langsung pada `return_claims.hari_terlambat` (BR-10). Selama menunggu verifikasi admin, keterlambatan dibekukan.
- [ ] Simpan `return_claims` dan `return_claim_lines` dengan status `menunggu`.
- [ ] **Sesuai BR-03**: Klaim pengembalian **TIDAK mengubah stok** (`items.total`, `items.tersedia`, `items.rusak` tetap).

### 4. Service `VerifikasiService`
- [ ] Kunci baris `return_claims` dengan kondisi `status = 'menunggu'`. Jika status sudah bukan `menunggu`, tolak dengan `409 Conflict` (BR-07, mencegah verifikasi ganda).
- [ ] Kunci baris `items` terkait dengan urutan ID menaik.
- [ ] Validasi pembagian 4 hasil fisik per baris klaim:
  `qty_baik + qty_rusak + qty_hilang + qty_ditolak == qty_klaim`
- [ ] Validasi catatan admin: Wajib diisi bila ada barang yang ditolak atau kondisi hasil berbeda dari yang dilaporkan peminjam (BR-06).
- [ ] Terapkan efek stok per barang:
  - `qty_baik > 0`: `tersedia += qty_baik`, catat mutasi `VERIFIKASI_BAIK` (`delta_tersedia = +n`).
  - `qty_rusak > 0`: `rusak += qty_rusak`, catat mutasi `VERIFIKASI_RUSAK` (`delta_rusak = +n`).
  - `qty_hilang > 0`: `total -= qty_hilang`, catat mutasi `VERIFIKASI_HILANG` (`delta_total = -n`).
  - `qty_ditolak > 0`: Unit kembali menjadi tanggung jawab peminjam (kembali berstatus `Dipinjam` atau `Terlambat`), tidak ada mutasi stok.
- [ ] Update status klaim menjadi `'diverifikasi'`, catat `diverifikasi_oleh` dan `diverifikasi_pada`.
- [ ] Evaluasi invarian BR-01 sebelum commit transaksi.

### 5. Engine Status Peminjaman (`App\Services\StatusPeminjaman`)
- [ ] Status tidak disimpan di kolom database secara statis, melainkan diturunkan dari data relasi:
  - **Dipesan**: `tanggal_pinjam > hari_ini`.
  - **Dibatalkan**: `dibatalkan_pada IS NOT NULL`.
  - **Selesai**: Seluruh unit barang pada peminjaman telah diverifikasi (baik, rusak, atau hilang).
  - **Menunggu Verifikasi**: Seluruh sisa unit fisik sedang diajukan dalam klaim berstatus `menunggu`.
  - **Terlambat**: Masih ada unit yang dipegang peminjam (`sisa > 0`) dan `hari_ini > batas_kembali`.
  - **Dipinjam**: Masih ada unit yang dipegang peminjam dan `hari_ini <= batas_kembali`.

### 6. Perintah Pemeriksa Invarian (`inventaris:periksa-invarian`)
- [ ] Buat Artisan command `php artisan inventaris:periksa-invarian`.
- [ ] Perintah menghitung rumus BR-01 untuk setiap barang:
  `tersedia + rusak + dipinjam + menunggu_verifikasi == total`
- [ ] Verifikasi konsistensi buku besar: Jumlah kumulatif `delta_total`, `delta_tersedia`, dan `delta_rusak` pada `stock_movements` harus sama persis dengan angka di tabel `items`.
- [ ] Melaporkan jumlah pelanggaran (wajib 0 pelanggaran).

### 7. Endpoint Kiosk & Admin Terkait
- [ ] `GET /api/kiosk/items`: Menampilkan katalog barang aktif dan jumlah `tersedia`.
- [ ] `POST /api/kiosk/loans`: Pembuatan peminjaman langsung (FR-02).
- [ ] `POST /api/kiosk/loans/lookup`: Cek status peminjaman lewat kode `PJM-XXXXXX` (dengan proteksi *rate limit* & *lockout* setelah 5 kegagalan).
- [ ] `POST /api/kiosk/return-claims`: Pengajuan klaim pengembalian mandiri (FR-05).
- [ ] `GET /api/admin/return-claims?status=menunggu`: Daftar antrean klaim yang belum diverifikasi.
- [ ] `POST /api/admin/return-claims/{id}/verify`: Eksekusi verifikasi klaim oleh admin (FR-06).

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 2)
Fase 2 dinyatakan **SELESAI** dan diizinkan lanjut ke Fase 3 bila:
- [ ] **U-01 (Konkurensi Stok)**: Dua proses serentak meminjam 1 unit barang yang hanya tersisa stok 1 -> Tepat 1 transaksi berhasil, 1 transaksi ditolak `409 Conflict`.
- [ ] **U-02 (Idempotensi)**: Form peminjaman dikirim dua kali dengan `idempotency_key` yang sama -> Hanya 1 transaksi dan 1 kode peminjaman yang terbentuk.
- [ ] **U-03 (Uji Invarian Acak)**: Simulasi 1.000 operasi transaksi acak dijalankan, perintah `php artisan inventaris:periksa-invarian` melaporkan **0 pelanggaran**.
- [ ] **U-04 (Ketahanan Rollback)**: Transaksi yang sengaja digagalkan di tengah proses tidak meninggalkan data parsial pada database.
- [ ] **Skenario T-5.2, T-5.3, T-5.6**: Lolos pengujian otomatis.
- [ ] **Contoh Perhitungan Matematis Terbukti**:
  Barang dengan `total = 10`. Dipinjam 3 unit -> `tersedia = 7`. Klaim pengembalian 2 unit -> `tersedia = 7` (tetap). Verifikasi 1 baik, 1 rusak, 1 hilang -> hasil akhir: `tersedia = 8`, `rusak = 1`, `total = 9`.
