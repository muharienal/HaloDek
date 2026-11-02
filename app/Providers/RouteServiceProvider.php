<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Halaman tujuan default setelah login. Route "/" akan mengarahkan
     * sesuai role (lihat routes/web.php).
     */
    public const HOME = '/';

    public function boot(): void
    {
        // Pembatasan percobaan login: 5x/menit per NIK + IP, dan 20x/menit per IP
        // (karyawan masuk hanya dengan NIK, jadi tebak-tebak NIK massal harus dibatasi).
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by(strtolower((string) $request->input('nik')).'|'.$request->ip()),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
                // Serangan terdistribusi ke satu NIK dari banyak IP.
                Limit::perHour(40)->by('nik-hour|'.strtolower((string) $request->input('nik'))),
            ];
        });

        $user = fn (Request $r) => $r->user()?->id ?: $r->ip();

        // Aksi tulis umum oleh pengguna yang sudah login (cegah skrip/flood).
        RateLimiter::for('writes', fn (Request $r) => Limit::perMinute(90)->by('w|'.$user($r)));
        // Membuat laporan / membalas / mengunggah berkas.
        RateLimiter::for('submit', fn (Request $r) => [Limit::perMinute(10)->by('s|'.$user($r)), Limit::perDay(100)->by('sd|'.$user($r))]);
        // Ganti password.
        RateLimiter::for('password', fn (Request $r) => Limit::perMinute(5)->by('p|'.$user($r)));
        // Ekspor & impor massal.
        RateLimiter::for('bulk', fn (Request $r) => Limit::perMinute(6)->by('b|'.$user($r)));

        $this->routes(function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
        });
    }
}
