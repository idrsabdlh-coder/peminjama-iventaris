# Fase 3 – Frontend Kiosk (Layar Sentuh Peminjam)

Dokumen ini merupakan panduan pelaksanaan **Fase 3** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Membangun antarmuka frontend **mode Kiosk** berbasis layar sentuh (tablet/PC kiosk) menggunakan Vue 3, Vite, dan Tailwind CSS:
1. Peminjam dapat mencari barang, mengajukan peminjaman langsung, dan mengecek status peminjaman secara mandiri tanpa memerlukan akun/login.
2. Peminjam dapat mengajukan klaim pengembalian barang mandiri dan mencetak struk.
3. Menegakkan prinsip antarmuka: tombol sentuh besar (minimal 44 px), satu tugas per layar, proteksi kebocoran kode pinjam di URL, dan otomatis reset (*idle timeout*) setelah 60 detik.

---

## 📋 FR yang Dicakup
- **FR-01**: Tampilan katalog barang, pencarian nama, filter kategori, dan indikator stok habis.
- **FR-02**: Antarmuka form peminjaman langsung, pemilihan jumlah barang, dan cetak struk kode `PJM-XXXXXX`.
- **FR-04**: Antarmuka cek peminjaman berdasarkan kode, kartu peminjaman, dan penanganan status kunci lockout.
- **FR-05**: Antarmuka klaim pengembalian parsial/penuh dengan laporan kondisi fisik, penerbitan bukti kode `KMB-XXXXXX`.
- **FR-21**: Otomatis reset antarmuka kiosk ke halaman beranda setelah 60 detik tanpa aktivitas (*idle reset*).

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Fondasi Frontend & Konfigurasi Kiosk
- [ ] Setup Vue 3 + Vite di folder `frontend/` dengan Tailwind CSS.
- [ ] Konfigurasi `frontend/vite.config.js`: setup reverse proxy meneruskan `/api` dan `/sanctum` ke backend backend port 8000.
- [ ] Konfigurasi Axios client (`frontend/src/api/client.js`):
  - `baseURL: '/api'`
  - `headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' }`
- [ ] Atur Vue Router (`frontend/src/router/index.js`) untuk alur rute publik mode Kiosk.

### 2. Komponen Dasar & Token Desain Kiosk
- [ ] **`TombolBesar.vue`**: Tombol sentuh ramah jari dengan target sentuh tinggi minimal `h-14` (minimal 44 px), teks tebal, dan efek aktif/klik yang jelas.
- [ ] **`KartuBarang.vue`**: Menampilkan nama barang, kategori, ketersediaan stok; tombol "Pilih" dinonaktifkan otomatis bila stok 0.
- [ ] **`BadgeStatus.vue`**: Menampilkan status baku dengan warna token desain PRD (Dipesan: Ungu, Dipinjam: Biru, Terlambat: Merah, Menunggu Verifikasi: Kuning, Selesai: Hijau, Dibatalkan: Abu gelap).
- [ ] **`BadgeLencana.vue`**: Lencana pendukung (misal: "Sebagian", "Ada Catatan", "Ada Kerusakan").
- [ ] **`InputJumlah.vue`**: Kontrol penambah/pengurang kuantitas (`-` dan `+`) yang dibatasi minimal 1 dan maksimal stok tersedia saat ini.
- [ ] **`InputKode.vue`**: Input kode peminjaman yang otomatis mengubah huruf menjadi kapital (*uppercase*).
- [ ] **`FormField.vue`**: Pembungkus input dengan label jelas dan penampil pesan galat validasi server (422).
- [ ] **`ModalKonfirmasi.vue`**: Dialog konfirmasi sebelum submit transaksi krusial.
- [ ] **`NotifikasiToast.vue`**: Notifikasi *toast* pesan sukses/gagal yang mudah terbaca.
- [ ] **`StatusKosong.vue` & `Memuat.vue`**: Komponen visual saat data kosong atau sedang dalam proses memuat.
- [ ] **`StrukCetak.vue`**: Format cetak struk kode peminjaman dengan CSS `@media print` yang otomatis menyembunyikan tombol navigasi saat dicetak ke printer struk/kertas.

### 3. Layar-Layar Alur Kiosk
- [ ] **`/` (Beranda)**:
  Layar pembuka dengan 3 tombol sentuh besar utama:
  1. *Lihat Katalog Barang*
  2. *Pinjam Barang Sekarang*
  3. *Cek Peminjaman / Kembalikan*
- [ ] **`/katalog` (Katalog Barang)**:
  Pencarian instan berdasarkan nama, tombol pill filter kategori, dan grid daftar `KartuBarang`. Barang dengan stok 0 tetap tampil dengan label "Habis" namun tidak dapat dimasukkan ke keranjang.
- [ ] **`/pinjam` (Form Peminjaman)**:
  - Pemilihan barang dan kuantitas unit.
  - Pemilihan divisi dari dropdown `GET /api/kiosk/divisions`.
  - Input nama peminjam, tipe peminjam (`karyawan` / `magang`), dan nomor telepon (opsional).
  - Pemilihan batas tanggal pengembalian (default 7 hari ke depan).
  - Setiap kali form dibuka, buat UUID acak baru sebagai `idempotency_key`.
  - Tombol Kirim otomatis dinonaktifkan saat request sedang berlangsung untuk mencegah klik ganda.
- [ ] **`/pinjam/selesai` (Struk & Kode Peminjaman)**:
  Menampilkan kode `PJM-XXXXXX` dengan ukuran huruf sangat besar dan kontras tinggi, ringkasan barang, batas tanggal kembali, instruksi pencatatan/pengambilan barang, tombol *Cetak Struk*, dan tombol *Selesai*.
- [ ] **`/cek` (Lookup Peminjaman)**:
  Satu kolom input `InputKode` dan tombol *Cek*. Jika kode salah berulang 5 kali berturut-turut, kunci form dan tampilkan pesan lockout countdown 5 menit.
- [ ] **`/cek/kartu` (Kartu Peminjaman)**:
  Menampilkan identitas peminjam, `BadgeStatus`, rincian unit yang masih dipinjam, batas pengembalian, status hari terlambat, dan tombol aksi *Kembalikan Barang*.
  > [!IMPORTANT]
  > Nomor telepon peminjam **tidak pernah ditampilkan** pada layar ini demi menjaga privasi peminjam lain (BR-18).
- [ ] **`/klaim` (Form Klaim Pengembalian)**:
  Peminjam memilih jumlah unit yang dikembalikan per barang, mengisi kondisi fisik yang dilaporkan (baik, rusak, hilang), dan catatan opsional.
- [ ] **`/klaim/selesai` (Bukti Klaim Pengembalian)**:
  Menampilkan kode klaim `KMB-XXXXXX` dan pesan instruksi: *"Serahkan fisik barang ke petugas IT untuk diverifikasi"*.

### 4. Penanganan Idle Reset 60 Detik (FR-21)
- [ ] Buat composable `useIdleReset.js` yang memantau interaksi pengguna (`touchstart`, `mousemove`, `keydown`).
- [ ] Jika tidak ada aktivitas selama 60 detik di layar mana pun pada kiosk:
  - Bersihkan seluruh state form, keranjang belanja, dan kode pinjam di memori.
  - Alihkan (*redirect*) layar kembali ke beranda (`/`).

### 5. Keamanan Kode Pinjam di Kiosk (BR-18)
- [ ] Kode pinjam **tidak boleh dicantumkan pada URL query string** (misal: dilarang `/cek?kode=PJM-123456`).
- [ ] Lookup dikirim via HTTP POST body JSON ke `/api/kiosk/loans/lookup`.
- [ ] Kode pinjam hanya disimpan dalam state memori sementara selama transaksi aktif.

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 3)
Fase 3 dinyatakan **SELESAI** jika:
- [ ] **UI-01**: Peminjam dapat menyelesaikan seluruh alur peminjaman di layar sentuh tanpa hambatan dan tanpa keyboard fisik selain untuk mengisi teks input.
- [ ] **UI-02**: Penekanan tombol Kirim berkali-kali secara cepat (*spam click*) hanya memicu tepat 1 request transaksi ke server.
- [ ] **UI-03**: Kode peminjaman terbukti tidak muncul pada URL bar browser, riwayat kunjungan browser, maupun access log server.
- [ ] **Lockout Kode**: Memasukkan kode salah 5 kali berturut-turut memicu penguncian form lookup selama 5 menit.
- [ ] **Kecepatan Proses (G4)**: Peminjam dapat menyelesaikan pengisian form hingga kode pinjam terbit dalam waktu kurang dari 2 menit.
- [ ] **UI-06**: Struk peminjaman berhasil dicetak secara bersih tanpa elemen navigasi dan header layar.
- [ ] **UI-07**: Tampilan status *loading* dan *empty state* muncul secara proporsional.
