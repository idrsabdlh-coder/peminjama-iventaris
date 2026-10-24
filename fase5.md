# Phase 5 – Artisan Commands

| Command | Path | Deskripsi |
|---------|------|-----------|
| `inventaris:buat-admin` | `app/Console/Commands/BuatAdmin.php` | Membuat atau memperbarui user admin (email, nama) tanpa meminta password lewat input. |
| `inventaris:reset-password {email}` | `app/Console/Commands/ResetPassword.php` | Reset password menjadi string acak dan mengirim email (atau hanya menampilkan di console). |

**Contoh pembuatan command:**
```powershell
php artisan make:command BuatAdmin --command=inventaris:buat-admin
php artisan make:command ResetPassword --command=inventaris:reset-password
```

Setelah selesai, daftarkan di `app/Console/Kernel.php`.

**Uji command:**
```powershell
php artisan inventaris:buat-admin
php artisan inventaris:reset-password admin@example.com
```
Periksa DB `users` untuk memastikan perubahan.