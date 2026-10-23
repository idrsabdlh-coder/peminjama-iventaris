<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAKS_GAGAL = 5;     // usulan, belum dari tabel settings
    private const KUNCI_DETIK = 300;  // usulan

    public function store(LoginRequest $request): JsonResponse
    {
        $kunci = Str::lower($request->string('email')->toString()) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_GAGAL)) {
            $detik = RateLimiter::availableIn($kunci);

            return response()->json([
                'message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($detik / 60) . ' menit.',
                'retry_after' => $detik,
            ], 429);
        }

        // Akun nonaktif mendapat pesan yang sama dengan password salah
        $berhasil = Auth::guard('web')->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'aktif' => 1,
        ]);

        if (! $berhasil) {
            RateLimiter::hit($kunci, self::KUNCI_DETIK);

            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        RateLimiter::clear($kunci);
        $request->session()->regenerate();

        $user = Auth::guard('web')->user();
        $user->forceFill(['login_terakhir' => now()])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'aksi' => 'login',
            'entitas' => 'user',
            'entitas_id' => $user->id,
        ]);

        return response()->json([
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}