<?php

namespace App\Http\Middleware;

use App\Services\Maintenance;
use App\Services\OverdueReminder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengganti Task Scheduler: setelah halaman terkirim ke pengguna, jalankan tugas latar
 * (pengingat lewat batas tiap 15 menit, pembersihan data sekali sehari). Masing-masing
 * dijaga kunci cache sehingga hanya berjalan sekali per interval untuk seluruh aplikasi.
 */
class RunBackgroundTasks
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(OverdueReminder::class)->tick();
        app(Maintenance::class)->tick();
    }
}
