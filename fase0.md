# Phase 0 – Persiapan

## 🎯 Tujuan
Menyiapkan lingkungan pengembangan dan keputusan awal sebelum menulis kode sehingga semua anggota tim memiliki dasar yang konsisten.

## ✅ Daftar Pekerjaan (Tasks)
| No | Kategori | Deskripsi | Status |
|----|----------|-----------|--------|
| 1 | Infrastruktur | **Verifikasi koneksi MySQL** (versi 8.4) ke database `iventaris_db` pada server lokal (PHPBRPO). | ☐ Belum dimulai |
| 2 | Infrastruktur | **Pastikan Laravel 10** (atau versi yang dipilih) ter‑install via Composer. | ☐ Belum dimulai |
| 3 | Infrastruktur | **Install Node.js & Vue 3** untuk frontend, jalankan `npm install`. | ☐ Belum dimulai |
| 4 | Keputusan | **Pilih cara menjalankan aplikasi**: Herd, PHPBRPO, atau `php artisan serve`. Catat pilihan di `README.md`. | ☐ Belum dimulai |
| 5 | Dokumentasi | Buat **panduan setup** (install dependencies, copy `.env.example` → `.env`, generate `APP_KEY`, `php artisan migrate --seed`). | ☐ Belum dimulai |
| 6 | Repository | **Pastikan remote GitHub** ter‑hubung, buat branch `dev` dan `main`. Dokumentasikan URL remote di `README.md`. | ☐ Belum dimulai |
| 7 | Keamanan | **Hapus kredensial** (username/password) dari repo; gunakan variabel lingkungan. | ☐ Belum dimulai |
| 8 | Backup | **Backup database** awal (mysqldump) dan simpan di `storage/backups/`. | ☐ Belum dimulai |

## 📋 Keluaran (Deliverables)
- File `README.md` yang memuat langkah‑langkah **setup lingkungan** dan pilihan cara menjalankan aplikasi.
- File `.env.example` yang berisi **konfigurasi default** (host, port, database, user, password kosong).
- Dokumentasi keputusan (stack, remote repo, backup) disimpan dalam folder `docs/` atau di bagian *Catatan* `README.md`.

## 🛠️ Gerbang Penyelesaian (Gate Criteria)
- `php artisan migrate:status` berhasil terhubung ke MySQL tanpa error.
- Semua anggota tim dapat **clone repo**, menjalankan `composer install && npm install && php artisan serve` (atau perintah yang dipilih) dan melihat halaman *welcome* Laravel.
- `git fetch` berjalan tanpa konflik.
- Dokumentasi setup telah **ditinjau dan disetujui** oleh pemilik repo.

---

> **Catatan:** Setelah fase 0 selesai, Anda dapat melanjutkan ke **Fase 1** (konfigurasi env, migrasi awal, model, seeder, dan auth) yang sudah terdefinisi di `fase1.md`.
