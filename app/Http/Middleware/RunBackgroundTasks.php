<?php

namespace App\Http\Middleware;

use App\Services\Maintenance;
use App\Services\OverdueReminder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
        // Permintaan API (dari Inventaris) dibalas secepatnya: tanpa tugas latar, supaya kedua
        // aplikasi tidak saling menunggu bila berjalan di satu Apache (mod_php menahan koneksi
        // sampai tugas selesai).
        if ($request->is('api/*')) {
            return;
        }

        foreach ([OverdueReminder::class, Maintenance::class, \App\Services\DailyDigest::class] as $task) {
            try {
                app($task)->tick();
            } catch (Throwable $e) {
                Log::warning('Tugas latar gagal', ['task' => class_basename($task), 'error' => $e->getMessage()]);
            }
        }
    }
}
