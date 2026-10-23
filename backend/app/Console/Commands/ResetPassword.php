<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetPassword extends Command
{
    protected $signature = 'inventaris:reset-password {email : Email admin}';

    protected $description = 'Reset password admin lewat konsol. Semua sesi akun itu dihentikan.';

    public function handle(): int
    {
        $user = User::where('email', Str::lower((string) $this->argument('email')))->first();

        if ($user === null) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password baru (minimal 10 karakter)');
        $ulang = (string) $this->secret('Ulangi password baru');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $ulang],
            ['password' => ['required', 'confirmed', Password::min(10)]]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLog::create([
            'user_id' => null,
            'aksi' => 'reset_password',
            'entitas' => 'user',
            'entitas_id' => $user->id,
            'detail' => ['lewat' => 'artisan'],
        ]);

        $this->info("Password {$user->email} diganti.");

        return self::SUCCESS;
    }
}