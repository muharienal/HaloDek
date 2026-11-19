<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pembersihan rutin agar database & disk tidak membengkak. Berjalan OTOMATIS sekali sehari
 * dari dalam aplikasi (saat ada yang membuka HaloDek), tanpa Task Scheduler.
 *
 * - Notifikasi yang sudah dibaca > 90 hari, dan yang belum dibaca > 180 hari.
 * - Log audit > 365 hari.
 * - Foto milik laporan yang sudah dihapus.
 * - Berkas foto yatim (tidak tercatat di database) yang berumur > 1 hari.
 */
class Maintenance
{
    private const TICK_KEY = 'halodek:pembersihan:terakhir';

    public function tick(): void
    {
        if (! config('halodek.auto_maintenance', true)) {
            return;
        }
        if (! Cache::add(self::TICK_KEY, now()->toIso8601String(), now()->addDay())) {
            return;
        }

        try {
            $result = $this->run();
            if (array_sum($result) > 0) {
                Log::info('Pembersihan HaloDek', $result);
            }
        } catch (Throwable $e) {
            Log::warning('Pembersihan HaloDek gagal: '.$e->getMessage());
        }
    }

    /** @return array{notifications:int, audit_logs:int, deleted_report_files:int, orphan_files:int} */
    public function run(bool $dryRun = false): array
    {
        $cfg = config('halodek.retention');

        $notif = UserNotification::query()->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('is_read', true)->where('created_at', '<', now()->subDays($cfg['notifications_read_days'])))
            ->orWhere('created_at', '<', now()->subDays($cfg['notifications_unread_days'])));
        $audit = AuditLog::query()->where('created_at', '<', now()->subDays($cfg['audit_days']));

        // Lampiran yang laporannya sudah dihapus (data lama sebelum perbaikan ini).
        $trashed = ReportAttachment::query()->whereIn('report_id', Report::onlyTrashed()->select('id'));

        $result = [
            'notifications' => (clone $notif)->count(),
            'audit_logs' => (clone $audit)->count(),
            'deleted_report_files' => (clone $trashed)->count(),
            'orphan_files' => 0,
        ];

        if (! $dryRun) {
            $notif->delete();
            $audit->delete();
            $trashed->get()->each->delete(); // lewat model agar berkasnya ikut terhapus
        }

        $result['orphan_files'] = $this->orphanFiles($dryRun);

        return $result;
    }

    /** Hapus berkas di folder lampiran yang tidak tercatat di database (mis. sisa unggahan gagal). */
    private function orphanFiles(bool $dryRun): int
    {
        $disk = Storage::disk('local');
        if (! $disk->exists('lampiran')) {
            return 0;
        }

        $known = array_flip(ReportAttachment::query()->pluck('path')->all());
        $cutoff = now()->subDay()->getTimestamp(); // jangan sentuh unggahan yang mungkin sedang diproses
        $count = 0;

        foreach ($disk->allFiles('lampiran') as $path) {
            if (isset($known[$path]) || $disk->lastModified($path) > $cutoff) {
                continue;
            }
            $count++;
            if (! $dryRun) {
                $disk->delete($path);
            }
        }

        return $count;
    }
}
