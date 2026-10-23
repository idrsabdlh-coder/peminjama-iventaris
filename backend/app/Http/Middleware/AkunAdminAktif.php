<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AkunAdminAktif
{
    public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if ($user && ! $user->aktif) {
        return response()->json(['message' => 'Akun tidak aktif.'], 403);
    }

    return $next($request);
}
}