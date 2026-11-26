<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Team;
use App\Support\WorkHours;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ringkasan harian lewat WhatsApp (penerima: aturan "Ringkasan harian" di Administrasi › WhatsApp).
 * Dikirim sekali per hari kerja setelah jam ringkasan (Administrasi › WhatsApp, bawaan 16:00), dipicu saat
 * aplikasi dibuka (tanpa Task Scheduler) atau lewat `php artisan halodek:ringkasan-harian`.
 */
class DailyDigest
{
    public const SENT_KEY = 'halodek:ringkasan:terkirim:';
    private const LOCK_KEY = 'halodek:ringkasan:proses:';

    public function __construct(private WhatsAppNotifier $wa)
    {
    }

    public function due(?CarbonInterface $now = null): bool
    {
        $now ??= now();
        $cfg = config('services.whatsapp');

        // Pemeriksaan murah dulu; daftar penerima (query) paling akhir.
        return in_array($cfg['driver'] ?? 'off', ['fonnte', 'log'], true)
            && WorkHours::isWorkday($now)
            && $now->format('H:i') >= $this->sendTime()
            && ! Cache::has(self::SENT_KEY.$now->toDateString())
            && $this->recipients() !== [];
    }

    /**
     * Penerima ringkasan: '*' = rekap semua regu (Superadmin, Manajer),
     * id regu = rekap regu itu untuk Kepala Regunya.
     *
     * @return array<string|int, array<int, string>>
     */
    public function recipients(): array
    {
        $all = [];
        $teams = [];
        foreach ($this->wa->usersFor('ringkasan', null) as $u) {
            $all[] = $u->waNumber();
        }
        $all = array_values(array_unique(array_filter($all)));

        if (\App\Support\WaRules::roleWants(\App\Models\User::ROLE_KEPALA_REGU, 'ringkasan')) {
            $heads = \App\Models\User::active()->where('role', \App\Models\User::ROLE_KEPALA_REGU)->whereNotNull('team_id')->whereNotNull('phone')->get()
                ->filter(fn ($u) => $u->wantsWa('ringkasan') && $u->waNumber());
            foreach ($heads->groupBy('team_id') as $teamId => $group) {
                $nums = array_values(array_diff($group->map->waNumber()->unique()->all(), $all));
                if ($nums) {
                    $teams[$teamId] = $nums;
                }
            }
        }

        return array_filter(['*' => $all] + $teams);
    }

    /** Dipanggil setelah respons halaman terkirim (RunBackgroundTasks). */
    public function tick(): void
    {
        if (! $this->due()) {
            return;
        }

        $date = now()->toDateString();
        // Kunci 10 menit: hanya satu proses yang mengirim; bila gagal total, dicoba lagi setelahnya.
        if (! Cache::add(self::LOCK_KEY.$date, 1, now()->addMinutes(10))) {
            return;
        }

        try {
            $this->send(now());
        } catch (Throwable $e) {
            Log::warning('Ringkasan harian gagal: '.$e->getMessage());
        }
    }

    /** @return int jumlah nomor yang berhasil dikirimi (0 = tidak ada yang dikirim) */
    public function send(CarbonInterface $day): int
    {
        $sent = 0;
        $anything = false;
        foreach ($this->recipients() as $scope => $numbers) {
            $team = $scope === '*' ? null : Team::find($scope);
            if ($scope !== '*' && ! $team) {
                continue;
            }
            $message = $this->message($day, $team);
            if ($message === null) {
                continue;
            }
            $anything = true;
            $sent += $this->wa->sendNow($numbers, $message);
        }

        if (! $anything) {
            Cache::put(self::SENT_KEY.$day->toDateString(), 'kosong', now()->addDays(3));
        } elseif ($sent > 0) {
            Cache::put(self::SENT_KEY.$day->toDateString(), now()->toIso8601String(), now()->addDays(3));
        }

        return $sent;
    }

    /** Teks ringkasan; null bila tidak ada apa pun untuk dilaporkan hari itu. */
    public function message(CarbonInterface $day, ?Team $only = null): ?string
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $scope = fn ($q) => $only ? $q->where('team_id', $only->id) : $q;

        $teams = $only ? collect([$only]) : Team::orderBy('name')->get(['id', 'name']);
        $created = $scope(Report::whereBetween('created_at', [$from, $to]))->selectRaw('team_id, count(*) as n')->groupBy('team_id')->pluck('n', 'team_id');
        $done = $scope(Report::where('status', 'selesai')->whereBetween('resolved_at', [$from, $to]))->count();
        $rejected = $scope(Report::where('status', 'ditolak')->whereBetween('rejected_at', [$from, $to]))->count();
        $open = $scope(Report::open())->with('team')->get();
        $waiting = $open->whereNull('assigned_to_id');
        $overdue = $waiting->filter->isOverdue();
        $urgent = $open->whereIn('priority', ['tinggi', 'mendesak']);

        $newTotal = (int) $created->sum();
        if ($newTotal === 0 && $open->isEmpty() && $done === 0 && $rejected === 0) {
            return null;
        }

        $lines = [
            '*Ringkasan HaloDek*'.($only ? ' · Regu '.$only->name : ''),
            $day->translatedFormat('l, d F Y'),
            '',
            '*Hari ini*',
            'Laporan baru: '.$newTotal,
        ];
        foreach ($only ? [] : $teams as $t) {
            if ($n = (int) ($created[$t->id] ?? 0)) {
                $lines[] = '  • Regu '.$t->name.': '.$n;
            }
        }
        $lines[] = 'Selesai: '.$done;
        $lines[] = 'Ditolak: '.$rejected;
        $lines[] = '';
        $lines[] = '*Masih terbuka: '.$open->count().'*';
        $lines[] = 'Belum diambil: '.$waiting->count().($overdue->isNotEmpty() ? ' ('.$overdue->count().' lewat batas respons)' : '');
        $lines[] = 'Sedang diproses: '.($open->count() - $waiting->count());
        if ($urgent->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '*Prioritas tinggi/mendesak yang belum selesai*';
            foreach ($urgent->sortByDesc(fn ($r) => $r->priority === 'mendesak')->take(5) as $r) {
                $lines[] = '• '.$r->report_number.' · '.\Illuminate\Support\Str::limit($r->title, 60).($only ? '' : ' (Regu '.($r->team->name ?? '-').')');
            }
            if ($urgent->count() > 5) {
                $lines[] = '…dan '.($urgent->count() - 5).' lainnya.';
            }
        }
        $lines[] = '';
        $lines[] = 'Detail: '.rtrim((string) config('app.url'), '/').'/admin/laporan';

        return implode("\n", $lines);
    }

    public function sendTime(): string
    {
        $t = (string) config('services.whatsapp.digest_at', '16:00');

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '16:00';
    }

    public function lastSent(): ?string
    {
        foreach ([0, 1, 2, 3, 4, 5, 6] as $back) {
            $d = now()->subDays($back)->toDateString();
            if ($v = Cache::get(self::SENT_KEY.$d)) {
                return $d.($v === 'kosong' ? ' (tidak ada yang perlu dilaporkan)' : '');
            }
        }

        return null;
    }
}
