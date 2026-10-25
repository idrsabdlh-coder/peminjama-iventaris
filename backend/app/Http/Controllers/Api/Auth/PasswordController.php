<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)],
        ], [
            'password_lama.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 10 karakter.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['password_lama'], $user->password)) {
            throw ValidationException::withMessages(['password_lama' => 'Password lama salah.']);
        }

        $user->password = $data['password'];
        $user->save();

        // Hentikan semua sesi lain akun ini; sesi yang sedang dipakai dipertahankan.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'aksi' => 'ganti_password',
            'entitas' => 'user',
            'entitas_id' => $user->id,
        ]);

        return response()->json(['message' => 'Password berhasil diganti.']);
    }
}