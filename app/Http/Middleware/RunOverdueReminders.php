<?php

namespace App\Http\Middleware;

use App\Services\OverdueReminder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menggantikan Task Scheduler: setelah halaman terkirim ke pengguna, cek pengingat lewat batas
 * (paling sering sekali per 15 menit untuk seluruh aplikasi). Halaman tidak ikut menunggu.
 */
class RunOverdueReminders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(OverdueReminder::class)->tick();
    }
}
