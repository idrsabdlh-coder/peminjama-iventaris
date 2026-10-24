# DESIGN.md — Sistem Peminjaman & Inventaris Barang Kantor

Dokumen ini adalah acuan desain (design system + spesifikasi halaman) yang diturunkan dari 5 screenshot. Semua halaman wajib mengikuti dokumen ini agar tampilan konsisten.

---

## 1. Ringkasan Produk

| Item | Isi |
|---|---|
| Nama | Peminjaman Barang Kantor / Inventaris Kantor |
| Tujuan | Karyawan dan anak magang meminjam & mengembalikan barang kantor secara mandiri; petugas memantau stok dan laporan |
| Dua mode | **Kiosk** (layar sentuh bersama, tanpa login) dan **Panel Administrasi** (login petugas) |
| Bahasa antarmuka | Indonesia, sentence case |
| Zona waktu | WIB (Asia/Jakarta) |
| Privasi | Data dipisah per ID Peminjam ("Privasi Terisolasi per ID Peminjam") |

---

## 2. Desain Token

### 2.1 Warna

| Token | Hex | Pemakaian |
|---|---|---|
| `--primary-600` | `#1D4ED8` | Tombol utama, tab aktif, link, ikon aktif |
| `--primary-500` | `#2563EB` | Tombol "Tambah Barang Baru", "Unduh PDF Rekap" |
| `--indigo-700` | `#4338CA` | Ujung kanan gradien banner hero |
| `--hero-gradient` | `linear-gradient(100deg, #1347E5 0%, #1D5BFF 45%, #4338CA 100%)` | Banner hero kiosk |
| `--success-600` | `#059669` | "Terminal Mandiri (Kiosk)", tombol "Cari Peminjam Saya", "Unduh PDF Stok", status Sudah Kembali, "unit siap pinjam" |
| `--success-50` | `#ECFDF5` | Latar ikon hijau |
| `--warning-600` | `#D97706` | Tombol "Unduh PDF Belum Kembali" |
| `--warning-50` | `#FEF3C7` | Latar ikon peringatan, border kartu Belum Kembali (`#FDE68A`) |
| `--danger-400` | `#F87171` | Teks tombol "Keluar" |
| `--danger-bg` | `#2A1119` | Latar tombol "Keluar" di sidebar |
| `--sidebar-bg` | `#050B1F` | Sidebar admin (hampir hitam, kebiruan) |
| `--sidebar-active` | `#131A2E` | Item menu aktif sidebar |
| `--sidebar-accent` | `#3B82F6` | Garis vertikal kiri item aktif |
| `--bg-page` | `#F8FAFC` | Latar halaman |
| `--bg-card` | `#FFFFFF` | Kartu, header, footer |
| `--border` | `#E2E8F0` | Border kartu, input, divider |
| `--text-900` | `#0F172A` | Judul |
| `--text-700` | `#334155` | Teks isi, tombol sekunder |
| `--text-500` | `#64748B` | Subjudul, deskripsi |
| `--text-400` | `#94A3B8` | Placeholder, footer, ikon netral, empty state |

Aturan warna:
- Warna status selalu dipasangkan dengan ikon dan teks, tidak boleh hanya warna.
- Biru = aksi utama, hijau = selesai/tersedia/kembali, oranye = perhatian/belum kembali, merah = keluar/hapus.

### 2.2 Tipografi

| Peran | Font | Catatan |
|---|---|---|
| Utama | **Plus Jakarta Sans** (cadangan: Inter, system-ui) | Judul tebal dengan letter-spacing rapat |
| Angka waktu | **JetBrains Mono** (cadangan: ui-monospace) | Hanya untuk jam di header dan placeholder kolom cari di kiosk |

Skala tipe:

| Level | Ukuran / Bobot | Contoh |
|---|---|---|
| Hero | 36px / 800, tracking -0.02em | "Perlu Alat atau Perangkat Kantor?" |
| Judul halaman | 28–30px / 800 | "Data Peminjaman", "Kelola Barang" |
| Judul kartu | 20px / 700 | "Formulir Pengembalian Mandiri" |
| Angka statistik | 36px / 800 | "0" pada kartu statistik |
| Label kartu | 16px / 600 | "Sedang dipinjam" |
| Isi | 14–16px / 400–500 | Deskripsi, input |
| Header tabel | 14px / 700 | "Peminjam", "Divisi" |
| Keterangan | 12–13px / 400 | Footer, teks bantu |

### 2.3 Spasi, radius, bayangan

- Skala spasi 4px: 4, 8, 12, 16, 24, 32, 40.
- Lebar konten maksimum kiosk: **1540px** (padding samping 40px). Kartu formulir pengembalian: **1120px**, rata tengah.
- Radius: kartu besar 20px, hero 24px, tombol dan input 12px, chip dan pill 8px, ikon kotak 12px.
- Bayangan: kartu statistik memakai `0 1px 3px rgba(15,23,42,.08)`; hero dan tombol utama memakai bayangan biru lembut `0 10px 24px rgba(29,78,216,.25)`.
- Border 1px `--border` untuk semua kartu putih.

### 2.4 Ikon

Gaya garis (outline) 1.75px, set **Lucide**. Pemetaan ikon:

| Fungsi | Ikon Lucide |
|---|---|
| Logo / katalog / barang | `package` / `box` |
| Pengembalian | `refresh-cw` |
| Jam | `clock` |
| Login petugas, admin | `shield` |
| Data peminjaman | `layers` |
| Laporan & PDF | `file-text` |
| Pengujian | `check-circle` |
| Cari | `search` |
| Tambah | `plus-circle` / `plus` |
| Terlambat / peringatan | `alert-triangle` |
| Kembali / selesai | `check-circle` |
| Filter cepat | `zap` |
| Filter | `filter` |
| Unduh | `download` |
| Cetak | `printer` |
| Layar kiosk | `monitor` |
| Keluar | `log-out` |
| Kalender | `calendar` |

---

## 3. Layout Global

### 3.1 Mode Kiosk (publik, tanpa login)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ [logo] Peminjaman Barang Kantor   [1 Katalog & Pinjam | 2 Pengembalian]   │
│        ● Terminal Mandiri (Kiosk)  [🕒 Kamis, 22 Okt 2026, 18.10.26 WIB] 🛡 Login │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│                         konten halaman (bg #F8FAFC)                       │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│ Sistem Peminjaman & Inventaris Kantor © 2026   Layar Sentuh … Privasi …  │
└──────────────────────────────────────────────────────────────────────────┘
```

Header (tinggi 92px, putih, border bawah):
- Kiri: logo kotak biru 50×50 (radius 12) berisi ikon `package` putih. Di sebelahnya judul "Peminjaman Barang Kantor" (20px/700) dan baris kecil: titik hijau + "Terminal Mandiri (Kiosk)" (hijau, 600) lalu ikon `monitor` + "Layar Komputer Kantor" (abu).
- Tengah: **segmented tab** dalam wadah abu muda (`#F1F5F9`, radius 16, padding 6). Tab aktif berlatar putih dengan bayangan tipis dan teks biru; tab tidak aktif teks abu. Dua tab: `1. Katalog & Pinjam` dan `2. Pengembalian Mandiri`. Tab fokus keyboard mendapat outline hitam 2px (terlihat di screenshot 2).
- Kanan: pill jam (border, radius 8, font mono 12px) menampilkan "Kamis, 22 Okt 2026, 18.10.37 WIB", **update tiap detik**. Di sebelahnya link ghost "Login Petugas" dengan ikon `shield`, abu.

Footer (tinggi 64px, putih, border atas): kiri "Sistem Peminjaman & Inventaris Kantor © 2026"; kanan "Layar Sentuh Komputer Bersama · Privasi Terisolasi per ID Peminjam". Teks 12–13px, `--text-400`.

### 3.2 Mode Admin (login petugas)

```
┌─────────────┬──────────────────────────────────────────────┐
│ Sidebar     │ Judul halaman (28px/800)                      │
│ 320px       │ Subjudul abu                                  │
│ #050B1F     ├──────────────────────────────────────────────┤
│             │ konten (bg #F8FAFC, padding 40px)             │
│ [Menu]      │                                              │
│ …           │                                              │
│ [Profil]    │                                              │
│ [Kiosk][Keluar]                                             │
└─────────────┴──────────────────────────────────────────────┘
```

Sidebar (lebar 320px, tinggi penuh, tetap saat scroll):
- Atas: logo 50×50 biru dengan glow, "Inventaris Kantor" (putih 700) dan "Panel administrasi" (abu kebiruan, 13px).
- Menu (4 item, tinggi 50px, radius 12, ikon + label, jarak 5px): **Data Peminjaman**, **Kelola Barang**, **Laporan & PDF**, **Pengujian**. Item aktif: latar `--sidebar-active`, teks putih, garis biru vertikal 3px di tepi kiri. Item biasa: teks `#CBD5E1`.
- Bawah: kartu profil (border `#1E293B`, radius 16, padding 16) berisi avatar bulat inisial "A" (latar `#14213D`, teks biru muda), nama "Administrator", email "admin@kantor.id" (abu). Di bawahnya dua tombol sejajar: **Kiosk** (netral gelap, ikon `monitor`) untuk kembali ke mode kiosk, dan **Keluar** (latar merah gelap, teks merah muda, ikon `log-out`).

Header halaman: putih, tinggi ±120px, border bawah. Judul dan subjudul rata kiri.

---

## 4. Spesifikasi Halaman

### 4.1 Kiosk — Katalog & Pinjam (`/`)

1. **Hero** (radius 24, gradien biru ke indigo, padding 40, tinggi ±295px)
   - Judul putih 36px/800: "Perlu Alat atau Perangkat Kantor?"
   - Paragraf putih 20px, maks. 60% lebar: "Pilih barang di bawah ini, atau langsung isi formulir peminjaman sebagai karyawan atau anak magang."
   - Tombol putih (radius 12, tinggi 65, teks biru 700, ikon `plus-circle`): **Isi Formulir Peminjaman**.
   - Dekorasi: ikon `package` besar transparan (putih 15%) terpotong di kanan bawah. Satu-satunya elemen dekoratif di halaman.
2. **Bar pencarian**: input lebar 560px, ikon `search`, placeholder "Cari nama atau kode barang…". Di kanan, rata kanan: "{n} barang, **{m} unit siap pinjam**" (angka unit hijau 600). Di bawahnya divider 1px.
3. **Grid katalog**: kartu barang (lihat 5.5), 4 kolom di layar ≥1280px, 3 kolom ≥1024px, 2 kolom ≥640px, 1 kolom di bawahnya.
4. **Empty state** (kartu putih, border, radius 24, tinggi ±215px, isi rata tengah): ikon `package` abu 48px, "Barang tidak ditemukan" (20px/700), "Coba kata kunci lain." (abu). Saat database memang kosong, teks diganti: "Belum ada barang terdaftar." dan "Hubungi petugas untuk menambah barang."

**Alur Isi Formulir Peminjaman** (modal atau halaman penuh, dibuka dari tombol hero atau dari kartu barang):
- Field: Nama peminjam, ID/NIM, Divisi, Status (Karyawan / Anak magang), Barang (bisa lebih dari satu) + Qty, Tanggal pinjam (default hari ini), Batas kembali, Keperluan (opsional).
- Tombol utama biru "Simpan Peminjaman". Setelah sukses, tampilkan toast "Peminjaman tersimpan" dan kembali ke katalog dengan stok yang sudah berkurang.

### 4.2 Kiosk — Pengembalian Mandiri (`/kembali`)

- Kartu putih tunggal, lebar 1120px, rata tengah, jarak 40px dari header.
- Kepala kartu: kotak ikon hijau muda 50×50 dengan `refresh-cw` hijau; judul "Formulir Pengembalian Mandiri"; subjudul "Masukkan nama atau divisi Anda untuk melihat dan memverifikasi pengembalian barang yang sedang dipinjam."
- Baris cari: input font mono dengan ikon `search`, placeholder "Ketik nama peminjam atau divisi (misal: Budi / IT)…", disusul tombol hijau **Cari Peminjaman Saya** (ikon `search`).
- Hasil muncul di bawah kartu sebagai daftar pinjaman aktif. Tiap baris menampilkan nama barang, qty, tgl pinjam, batas kembali, chip status, dan tombol **Kembalikan**. Tekan tombol membuka dialog konfirmasi "Kembalikan {barang} ×{qty}?" dengan pilihan kondisi (Baik / Rusak ringan / Rusak).
- Jika tidak ditemukan: "Tidak ada pinjaman aktif atas nama itu. Periksa ejaan nama atau divisi."
- Privasi: hasil hanya menampilkan pinjaman milik ID yang cocok, tanpa menampilkan data peminjam lain secara massal.

### 4.3 Admin — Data Peminjaman (`/admin/peminjaman`)

- **4 kartu statistik** (grid 4 kolom, jarak 20px, putih, radius 20, tinggi ±210, padding 24). Tiap kartu: label (kiri atas), kotak ikon 46×46 (kanan atas), angka besar, keterangan kecil abu.

  | Kartu | Ikon (warna) | Keterangan |
  |---|---|---|
  | Sedang dipinjam | `clock` biru, latar biru muda | "transaksi aktif belum kembali" |
  | Terlambat | `alert-triangle` abu (saat 0) / merah (saat >0) | "tidak ada keterlambatan" / "{n} melewati batas" |
  | Sudah kembali | `check-circle` hijau, latar hijau muda | "riwayat peminjaman selesai" |
  | Stok tersedia | `package` indigo, latar indigo muda | angka "0 / 0 unit" + progress bar + "0% siap dipinjam" |

- **Panel filter** (putih, radius 20, padding 20):
  - Baris 1: input cari lebar penuh "Cari peminjam, divisi, atau nama barang…"; tombol outline **Filter Cepat: Belum Dikembalikan** (ikon `zap` oranye); link bergaris bawah **Reset Filter** (biru).
  - Baris 2: empat field berlabel kecil 600 — Status Peminjaman (select: Semua Status, Sedang Dipinjam, Terlambat, Sudah Kembali), Filter Barang Tertentu (select: Semua Barang), Dari Tanggal Pinjam (date), Sampai Tanggal Pinjam (date).
- **Tabel**: kolom Peminjam, Divisi, Barang & Qty, Tgl Pinjam, Batas Kembali, Tgl Kembali, Status, Aksi. Header putih, teks 700, border bawah. Baris tinggi 64px, hover `#F8FAFC`. Kolom status memakai chip (5.3). Kolom aksi memuat tombol "Tandai Kembali" dan ikon detail.
- **Empty state tabel**: teks abu rata tengah "Tidak ada transaksi peminjaman yang cocok dengan filter."

### 4.4 Admin — Kelola Barang (`/admin/barang`)

- Tombol biru solid **+ Tambah Barang Baru** rata kanan di atas panel filter (radius 12, tinggi 50).
- **Panel filter** satu baris: input "Cari nama atau kategori…", select "Semua Kategori", dan di kanan "Total: **{n}** barang terdaftar".
- **Tabel**: Nama Barang, Kategori, Total Stok, Tersedia, Dipinjam, Kondisi, Keterangan, Aksi (Edit, Hapus).
- **Empty state**: "Tidak ada barang yang ditemukan." Tambahkan tautan "Tambah barang pertama" yang membuka formulir.
- **Formulir tambah/ubah barang** (modal): Kode barang (otomatis, bisa diubah), Nama barang, Kategori, Total stok, Kondisi (Baik / Rusak ringan / Rusak), Keterangan. Validasi: total stok tidak boleh lebih kecil dari jumlah yang sedang dipinjam.
- Hapus barang diblokir bila masih ada pinjaman aktif, dengan pesan "Barang ini masih dipinjam, selesaikan pengembaliannya dulu."

### 4.5 Admin — Laporan & PDF (`/admin/laporan`)

- **3 kartu laporan** (grid 3 kolom): setiap kartu berisi kotak ikon 50×50, judul 700, deskripsi 2 baris, dan tombol penuh lebar bertinggi 45.

  | Kartu | Warna | Tombol |
  |---|---|---|
  | Rekapitulasi Peminjaman (PDF) | Biru (`file-text`) | "Unduh PDF Rekap ({n} Data)" |
  | Barang Belum Kembali (PDF) | Oranye, border kartu kuning `#FDE68A` (`alert-triangle`) | "Unduh PDF Belum Kembali" |
  | Daftar Stok & Aset (PDF) | Hijau (`package`) | "Unduh PDF Stok Barang" |

- **Panel filter**: judul "Sesuaikan filter sebelum unduh dokumen" dengan ikon `filter` biru; field Status Peminjaman, Barang Tertentu, Periode Mulai, Periode Selesai.
- **Pratinjau** (kartu putih): bar atas "Pratinjau Data yang Akan Masuk ke PDF ({n} Baris)" dan link biru **Cetak PDF Sekarang** (ikon `printer`). Tabel: No, Peminjam, ID/NIM, Divisi, Barang & Qty, Tgl Pinjam, Batas Kembali, Tgl Kembali, Status. Empty state: "Tidak ada transaksi yang cocok untuk dicetak."
- Tombol "Unduh PDF Rekap" dinonaktifkan (abu, `disabled`) bila 0 data, dengan tooltip "Tidak ada data untuk diunduh."

### 4.6 Admin — Pengujian (`/admin/pengujian`)

Halaman ini tidak ada di screenshot; rancangannya mengikuti pola yang sama. Fungsinya menjalankan pengecekan sistem: uji alur pinjam, kembali, dan hitung stok dengan data contoh. Tampilkan daftar kasus uji dalam kartu, tombol "Jalankan Pengujian", dan hasil Lulus (hijau) atau Gagal (merah) per kasus. Data uji ditandai jelas dan tidak mencemari data asli.

---

## 5. Komponen

### 5.1 Tombol

| Varian | Gaya |
|---|---|
| Primary | Latar `--primary-500`, teks putih 700, radius 12, tinggi 50, ikon kiri 18px |
| On-hero | Latar putih, teks `--primary-600`, bayangan lembut |
| Success | Latar `--success-600`, teks putih |
| Warning | Latar `--warning-600`, teks putih |
| Outline | Putih, border `--border`, teks `--text-700` |
| Ghost | Tanpa latar, teks abu (Login Petugas) |
| Link | Teks biru, garis bawah (Reset Filter) |
| Disabled | Latar `#E2E8F0`, teks `#94A3B8`, kursor `not-allowed` |

Target sentuh minimum **48×48px** di mode kiosk. Hover menggelapkan satu tingkat; fokus keyboard memakai outline 2px `#0F172A` dengan offset 2px.

### 5.2 Input dan select

Tinggi 50px (kiosk) atau 40px (tabel/filter admin), radius 12, border `--border`, latar putih, placeholder `--text-400`. Fokus: border `--primary-500` + ring 3px biru 20%. Label di atas input, 13px/600. Pesan galat di bawah input, merah `#DC2626`, 13px.

### 5.3 Chip status

| Status | Latar | Teks | Ikon |
|---|---|---|---|
| Sedang dipinjam | `#DBEAFE` | `#1D4ED8` | `clock` |
| Terlambat | `#FEE2E2` | `#B91C1C` | `alert-triangle` |
| Sudah kembali | `#D1FAE5` | `#047857` | `check-circle` |

Kondisi barang: Baik (hijau), Rusak ringan (kuning), Rusak (merah).

### 5.4 Tabel

Wadah putih, radius 20, border `--border`, overflow-x auto di layar sempit. Header tanpa latar abu pekat (putih atau `#F8FAFC` di pratinjau PDF), teks `--text-900` 700. Baris dipisah garis `#F1F5F9`. Angka rata kanan, tanggal format `dd MMM yyyy`.

### 5.5 Kartu barang (katalog kiosk)

Putih, radius 20, border, padding 20. Isi: kotak ikon/kategori, nama barang (16px/700), kode barang (mono 12px, abu), kategori, indikator stok ("Tersedia 3 / 5") dengan progress bar tipis, tombol primary **Pinjam**. Bila stok 0: tombol nonaktif bertuliskan "Stok habis" dan kartu diredam (opacity 60%).

### 5.6 Notifikasi

Toast kanan atas, radius 12, durasi 4 detik, ikon + satu kalimat. Contoh: "Peminjaman tersimpan", "Barang dikembalikan", "Gagal menyimpan. Periksa koneksi lalu coba lagi."

---

## 6. Perilaku dan Aturan Bisnis

1. **Stok**: `tersedia = total − sum(qty pinjaman aktif)`. Peminjaman ditolak jika qty melebihi tersedia.
2. **Terlambat**: pinjaman aktif dengan `batas_kembali < hari ini`, dihitung otomatis. Kartu "Terlambat" dan chip ikut berubah.
3. **Jam**: header kiosk menampilkan jam berjalan zona WIB, diperbarui tiap 1 detik.
4. **Pencarian katalog**: instan (debounce 250ms), mencocokkan nama dan kode barang.
5. **Filter Cepat: Belum Dikembalikan** menyetel status = Sedang Dipinjam + Terlambat; **Reset Filter** mengosongkan semua filter.
6. **Sesi kiosk**: setelah 60 detik tanpa aktivitas, kembali ke halaman Katalog dan menghapus hasil pencarian pengembalian (menjaga privasi di layar bersama).
7. **Login Petugas**: halaman login terpisah (email + kata sandi). Tombol **Kiosk** di sidebar kembali ke mode kiosk tanpa mengeluarkan sesi, tombol **Keluar** mengakhiri sesi.
8. **PDF**: dibuat di server (Dompdf) dengan kop "Inventaris Kantor", tanggal cetak, nama pencetak, dan nomor halaman. Isi mengikuti filter yang sedang aktif.

---

## 7. Salinan Teks (UI copy)

Gunakan kalimat aktif, sentence case, dan kata kerja yang konsisten.

| Konteks | Teks |
|---|---|
| Aksi pinjam | "Pinjam", "Isi Formulir Peminjaman", "Simpan Peminjaman" |
| Aksi kembali | "Kembalikan", "Tandai Kembali" |
| Kosong (katalog) | "Barang tidak ditemukan" / "Coba kata kunci lain." |
| Kosong (tabel) | "Tidak ada transaksi peminjaman yang cocok dengan filter." |
| Galat umum | "Gagal menyimpan. Periksa koneksi lalu coba lagi." |
| Konfirmasi hapus | "Hapus {nama barang}? Tindakan ini tidak bisa dibatalkan." |

---

## 8. Aksesibilitas dan Responsif

- Kontras teks minimal 4.5:1 (putih di atas biru/hijau/oranye sudah memenuhi; jangan memakai teks abu muda untuk informasi penting).
- Fokus keyboard selalu terlihat; urutan tab mengikuti urutan visual.
- Semua ikon tombol punya `aria-label`; tab kiosk memakai `role="tablist"`.
- Hormati `prefers-reduced-motion`. Animasi hanya untuk respons aksi (modal, toast).
- Breakpoint: `sm 640`, `md 768`, `lg 1024`, `xl 1280`. Di bawah `lg`, sidebar admin berubah menjadi drawer dengan tombol menu; kartu statistik menjadi 2 kolom lalu 1 kolom; tabel scroll horizontal.
- Kiosk dioptimalkan untuk layar sentuh: tanpa interaksi hover-only, jarak antar target sentuh minimal 8px.

---

## 9. Saran Teknis (sesuai lingkungan Laragon)

| Lapisan | Pilihan |
|---|---|
| Backend | Laravel 11 (PHP 8.2+), MySQL dari Laragon |
| Frontend | Blade + Tailwind CSS + Alpine.js (ringan, cocok untuk kiosk) |
| PDF | barryvdh/laravel-dompdf |
| Ikon | Lucide (`lucide` via CDN/npm) |
| Font | Plus Jakarta Sans & JetBrains Mono (Google Fonts) |

Tabel utama: `users`, `items` (kode, nama, kategori, total_stok, kondisi, keterangan), `loans` (peminjam, id_peminjam, divisi, tipe, tgl_pinjam, batas_kembali, tgl_kembali, status), `loan_items` (loan_id, item_id, qty, kondisi_kembali).

Contoh konfigurasi Tailwind:

```js
// tailwind.config.js
export default {
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
      },
      colors: {
        sidebar: { DEFAULT: '#050B1F', active: '#131A2E' },
        page: '#F8FAFC',
      },
      borderRadius: { xl2: '20px', hero: '24px' },
    },
  },
};
```

---

## 10. Checklist Konsistensi

- [ ] Semua halaman memakai token warna di bagian 2.1, tanpa hex acak.
- [ ] Radius: 20 untuk kartu, 12 untuk kontrol, 24 untuk hero.
- [ ] Setiap daftar punya empty state dengan teks arahan.
- [ ] Setiap status punya ikon, warna, dan teks.
- [ ] Target sentuh kiosk minimal 48px.
- [ ] Tombol unduh dinonaktifkan saat data 0.
- [ ] Footer kiosk dan identitas sidebar tidak berubah antarhalaman.
