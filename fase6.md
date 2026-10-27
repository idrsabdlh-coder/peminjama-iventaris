# Fase 6 – Fitur Lanjutan (Pemesanan, Perpanjangan, Pembatalan, Koreksi & Stok Rusak)

Dokumen ini merupakan panduan pelaksanaan **Fase 6** yang diturunkan secara langsung dari **[Prd (1).md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/Prd%20(1).md)** dan **[fase.md](file:///C:/Users/HP/Documents/magang/Peminjaman-iventaris/fase.md)**.

---

## 🎯 Tujuan Fase
Melengkapi **seluruh cakupan fungsional PRD v5.0** (fitur prioritas Should dan Could):
1. Mengaktifkan fitur pemesanan barang (*booking* masa depan) dan pembatalannya.
2. Mengaktifkan alur perpanjangan masa pinjam mandiri di kiosk dan perpanjangan diskresi oleh admin.
3. Mengaktifkan fitur koreksi data transaksi peminjaman dengan pencatatan audit log terpisah pada tabel `loan_corrections`.
4. Mengimplementasikan siklus pengelolaan stok rusak: penandaan saat opname fisik, penyelesaian perbaikan bengkel, dan pemusnahan unit (*afkir*).
5. Ekspor data tabular ke format CSV/Excel.

---

## 📋 FR yang Dicakup
- **FR-03**: Pemesanan barang untuk tanggal masa depan dan pembatalan pemesanan sebelum hari peminjaman.
- **FR-07**: Perpanjangan durasi peminjaman mandiri oleh peminjam di kiosk (berdasarkan kuota dan izin barang).
- **FR-08**: Perpanjangan durasi peminjaman oleh admin via panel (kebijakan diskresi dengan alasan wajib).
- **FR-09**: Pembatalan peminjaman oleh admin sebelum adanya klaim pengembalian.
- **FR-10**: Koreksi data dan kuantitas peminjaman oleh admin dengan audit trail riwayat perubahan.
- **FR-13 (Bagian S)**: Siklus bengkel stok rusak (tandai rusak fisik, perbaikan selesai, dan afkir).
- **FR-22**: Ekspor data daftar peminjaman dan stok barang ke file spreadsheet CSV/Excel.

---

## 📦 Rincian Pekerjaan (Tasks)

### 1. Engine Pemesanan Barang (Booking) & Pembatalan (FR-03, FR-09)
- [ ] **Alur Pemesanan (Booking)**:
  - Form peminjaman menerima `tanggal_pinjam > hari_ini` (maksimal `hari_ini + maks_booking_hari` / 7 hari).
  - Stok barang `tersedia` **langsung ditahan** pada saat transaksi dibuat (`tersedia -= n`, mutasi buku besar `PINJAM`).
  - Status peminjaman otomatis diturunkan menjadi **`Dipesan`**.
  - Ketika tanggal kalender telah mencapai `tanggal_pinjam`, status otomatis berubah menjadi **`Dipinjam`** tanpa perlu manipulasi data manual.
  - **Aturan Bisnis BR-26**: Pemesanan barang di masa depan **tidak dapat diperpanjang**.
- [ ] **Service `PembatalanService`**:
  - **Pembatalan oleh Peminjam**: HANYA diizinkan bila `hari_ini < tanggal_pinjam` (belum masuk masa pinjam).
  - **Pembatalan oleh Admin**: HANYA diizinkan bila peminjaman tersebut **belum memiliki klaim pengembalian** fisik. Kolom `alasan_batal` wajib diisi.
  - Mengembalikan stok unit yang ditahan: `tersedia += n`.
  - Menulis baris mutasi `BATAL_PINJAM` pada tabel `stock_movements`.
  - Mengisi `dibatalkan_pada` dan `dibatalkan_oleh`. Status peminjaman otomatis menjadi **`Dibatalkan`**.

### 2. Service Perpanjangan Masa Peminjaman (FR-07, FR-08)
- [ ] **Perpanjangan Mandiri oleh Peminjam (Kiosk)**:
  - Validasi syarat:
    1. Masih memiliki unit pinjaman aktif (`O > 0`).
    2. Belum melewati batas kembali / tidak terlambat (`hari_ini <= batas_kembali`).
    3. Jumlah perpanjangan yang sudah dilakukan belum mencapai batas `maks_perpanjangan` (maksimal 2 kali).
    4. Seluruh barang dalam peminjaman tersebut memiliki flag `boleh_diperpanjang = 1` (BR-11, BR-12, D-12).
  - Peminjam memilih tambahan hari (1 sampai `maks_hari_per_perpanjangan` / 7 hari).
  - `batas_kembali_baru = batas_kembali_lama + tambahan_hari`. Kolom `batas_kembali_awal` tetap utuh.
  - Catat riwayat di `loan_extensions` dengan `diajukan_oleh = 'peminjam'`.
- [ ] **Perpanjangan oleh Admin (Panel Admin)**:
  - Berlaku sebagai wewenang diskresi: diizinkan untuk peminjaman yang sudah berstatus `Terlambat` dan tanpa batasan kuota perpanjangan.
  - Kolom `alasan` **wajib diisi**.
  - Catat riwayat di `loan_extensions` dengan `diajukan_oleh = 'admin'` dan `user_id = admin.id`.

### 3. Service Koreksi Peminjaman (FR-10)
- [ ] **Service `KoreksiService`**:
  - Mengizinkan admin memperbaiki kesalahan input data peminjam (nama, divisi, nomor telepon, batas tanggal kembali, kuantitas unit).
  - **Aturan Bisnis BR-16**: Koreksi kuantitas barang **DITOLAK** jika barang tersebut sudah memiliki pengajuan klaim pengembalian.
  - Jika kuantitas barang diubah:
    - Hitung selisih: `selisih = qty_baru - qty_lama`.
    - Perbarui stok: `tersedia = tersedia - selisih`.
    - Catat baris mutasi buku besar `KOREKSI_PINJAM` pada `stock_movements`.
  - Catat setiap perubahan kolom pada tabel **`loan_corrections`** (kolom, nilai lama, nilai baru, alasan koreksi wajib, `user_id`, waktu UTC).

### 4. Manajemen Siklus Stok Rusak, Perbaikan & Afkir (FR-13 Bagian S)
- [ ] **Layar `/admin/stok-rusak`**:
  - Menampilkan daftar inventaris yang memiliki kuantitas `rusak > 0`.
- [ ] **Aksi Tandai Rusak (Hasil Hitung Fisik / Opname)**:
  - Ditemukan barang rusak di rak sebelum dipinjam.
  - Endpoint: `POST /api/admin/items/{id}/mark-damaged`.
  - Efek stok: `tersedia -= n`, `rusak += n`. Catat mutasi `TANDAI_RUSAK`.
- [ ] **Aksi Selesai Diperbaiki**:
  - Barang di bengkel servis telah diperbaiki dan siap kembali ke rak.
  - Endpoint: `POST /api/admin/items/{id}/repair-complete`.
  - Efek stok: `rusak -= n`, `tersedia += n`. Catat mutasi `PERBAIKAN_SELESAI`.
- [ ] **Aksi Afkir (Pemusnahan Barang Rusak Total)**:
  - Barang rusak parah yang tidak dapat diperbaiki lagi dibuang dari aset kantor.
  - Endpoint: `POST /api/admin/items/{id}/write-off`. Kolom `alasan` wajib diisi.
  - Efek stok: `rusak -= n`, `total -= n`. Catat mutasi `AFKIR`.

### 5. Layar Kiosk Lanjutan & Ekspor Data
- [ ] Tambahkan layar kiosk:
  - `/perpanjang`: Form pemilihan durasi tambahan hari dan pratinjau tanggal jatuh tempo baru.
  - `/perpanjang/selesai`: Bukti konfirmasi perpanjangan berhasil.
  - `/batal`: Dialog konfirmasi pembatalan pemesanan barang.
- [ ] **Fitur Ekspor CSV/Excel (FR-22)**:
  - Tombol *Ekspor CSV* pada daftar peminjaman dan daftar stok barang di panel admin.
  - Menghasilkan file `.csv` terformat rapi sesuai filter aktif.

---

## 🛠️ Gerbang Penyelesaian (Gate Criteria Fase 6)
Fase 6 dinyatakan **SELESAI** jika:
- [ ] **Konsistensi Pembatalan**: Pembatalan pemesanan berhasil mengembalikan kuantitas `tersedia` secara akurat dan tercatat di buku besar `stock_movements`.
- [ ] **Proteksi Perpanjangan**: Transaksi pemesanan (booking) terbukti ditolak saat mencoba diperpanjang (BR-26); peminjam yang telah mencapai kuota 2 kali perpanjangan ditolak oleh sistem.
- [ ] **Integritas Koreksi**: Koreksi kuantitas barang yang telah memiliki klaim pengembalian ditolak oleh server (BR-16); riwayat koreksi tercatat lengkap di `loan_corrections`.
- [ ] **Konsistensi Siklus Rusak**: Aksi afkir berhasil mengurangi `total` dan `rusak` secara simultan; mutasi `AFKIR`, `TANDAI_RUSAK`, dan `PERBAIKAN_SELESAI` tercatat di buku besar.
- [ ] **U-03 Diulang (Invarian Acak Lanjutan)**: Pengujian 1.000 transaksi acak yang mengikutsertakan seluruh operasi lanjutan (booking, perpanjangan, pembatalan, koreksi, perbaikan, afkir) tetap menghasilkan **0 pelanggaran invarian BR-01**.
- [ ] **Ekspor CSV**: File ekspor CSV/Excel dapat diunduh dan dibuka dengan struktur data yang lengkap dan valid.
