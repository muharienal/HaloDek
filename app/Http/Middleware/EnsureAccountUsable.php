<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dijalankan untuk semua halaman setelah login:
 *  - akun yang dinonaktifkan langsung dikeluarkan,
 *  - admin dengan password bawaan wajib menggantinya dulu (karyawan masuk dengan NIK, tanpa password).
 */
class EnsureAccountUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['nik' => 'Akun Anda dinonaktifkan. Hubungi Superadmin.']);
        }

        if ($user && ! $user->isKaryawan() && $user->must_change_password && ! $request->routeIs('profile.*', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Demi keamanan, silakan ganti password bawaan Anda terlebih dahulu.');
        }

        return $next($request);
    }
}
