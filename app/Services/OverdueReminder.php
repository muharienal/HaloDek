<?php

namespace App\Services;

use App\Models\Report;
use App\Support\WorkHours;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengingat laporan yang lewat batas respons ke Kepala Regu (notifikasi + WhatsApp).
 *
 * Berjalan OTOMATIS dari dalam aplikasi: setiap ada yang membuka HaloDek, sistem mengecek
 * paling sering sekali per 15 menit (tanpa Task Scheduler / cron). Tiap laporan diingatkan sekali,
 * hanya pada jam kerja.
 */
class OverdueReminder
{
    private const TICK_KEY = 'halodek:pengingat:terakhir';

    public function __construct(private NotificationService $notify, private WhatsAppNotifier $wa) {}

    /** Dipanggil setelah respons halaman terkirim; aman dipanggil di setiap request. */
    public function tick(): void
    {
        if (! config('halodek.auto_reminder', true)) {
            return;
        }

        // Cache::add hanya berhasil bila kunci belum ada -> paling banyak sekali per interval, juga saat banyak pengguna bersamaan.
        if (! Cache::add(self::TICK_KEY, now()->toIso8601String(), now()->addMinutes((int) config('halodek.reminder_every_minutes', 15)))) {
            return;
        }

        try {
            $this->run();
        } catch (Throwable $e) {
            Log::warning('Pengingat lewat batas gagal: '.$e->getMessage());
        }
    }

    /**
     * @return array<int, array{team:string, count:int, sent:int}> ringkasan per regu
     */
    public function run(bool $dryRun = false): array
    {
        if (! $this->isWorkTime()) {
            return [];
        }

        $due = Report::with(['team', 'category'])->unassigned()->whereNull('reminded_at')->oldest()->get()->filter->isOverdue();
        $summary = [];

        foreach ($due->groupBy('team_id') as $reports) {
            $team = $reports->first()->team;
            $sent = 0;

            if (! $dryRun) {
                // Tandai dulu agar tidak terkirim ganda bila ada proses lain berjalan bersamaan.
                Report::whereIn('id', $reports->pluck('id'))->whereNull('reminded_at')->update(['reminded_at' => now()]);
                foreach ($reports as $r) {
                    $this->notify->toHead($team, 'Lewat batas respons '.$r->report_number, "\"{$r->title}\" belum diambil siapa pun. Mohon ambil atau tugaskan.", $r);
                }
                $sent = $this->wa->overdueReminder($team, $reports->values());
            }

            $summary[] = ['team' => $team->name, 'count' => $reports->count(), 'sent' => $sent];
        }

        return $summary;
    }

    public function isWorkTime(): bool
    {
        $cfg = config('halodek.work_hours');
        $t = now()->format('H:i');

        return WorkHours::isWorkday(now()) && $t >= $cfg['start'] && $t < $cfg['end'];
    }
}
