<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BuatAdmin extends Command
{
    protected $signature = 'inventaris:buat-admin';

    protected $description = 'Buat akun admin baru. Password dimasukkan lewat prompt.';

    public function handle(): int
    {
        $nama = trim((string) $this->ask('Nama admin'));
        $email = Str::lower(trim((string) $this->ask('Email admin')));
        $password = (string) $this->secret('Password (minimal 10 karakter)');
        $ulang = (string) $this->secret('Ulangi password');

        $validator = Validator::make(
            ['nama' => $nama, 'email' => $email, 'password' => $password, 'password_confirmation' => $ulang],
            [
                'nama' => ['required', 'max:100'],
                'email' => ['required', 'email', 'max:190', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(10)],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        // Dibungkus transaksi database agar proses users & audit_logs aman
        $user = DB::transaction(function () use ($nama, $email, $password) {
            $user = User::create([
                'name' => $nama, 
                'email' => $email, 
                'password' => Hash::make($password)
            ]);

            AuditLog::create([
                'user_id' => null,
                'aksi' => 'buat_admin',
                'entitas' => 'user',
                'entitas_id' => $user->id,
                'detail' => ['email' => $user->email, 'lewat' => 'artisan'],
            ]);

            return $user;
        });

        $this->info("Admin dibuat: {$user->email}");

        return self::SUCCESS;
    }
}