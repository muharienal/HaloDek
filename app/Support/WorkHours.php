<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hitungan JAM KERJA (bukan jam kalender), sesuai config('halodek.work_hours'):
 * bawaan Senin-Jumat 07:00-16:00 WIB, tanpa hari libur yang didaftarkan.
 * Dipakai untuk durasi respons/penyelesaian dan batas respons laporan.
 */
class WorkHours
{
    /** Batas aman perulangan hari (±10 tahun) agar data rusak tidak membuat loop panjang. */
    private const MAX_DAYS = 3660;

    /** Menit kerja di antara dua waktu (0 bila $to <= $from). */
    public static function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Carbon::instance($from)->setTimezone(config('app.timezone'));
        $to = Carbon::instance($to)->setTimezone(config('app.timezone'));
        if ($to->lte($from)) {
            return 0;
        }

        $total = 0;
        $day = $from->copy()->startOfDay();
        for ($i = 0; $i < self::MAX_DAYS && $day->lte($to); $i++, $day->addDay()) {
            if (! self::isWorkday($day)) {
                continue;
            }
            [$open, $close] = self::window($day);
            $start = $from->gt($open) ? $from : $open;
            $end = $to->lt($close) ? $to : $close;
            if ($end->gt($start)) {
                $total += (int) floor($start->diffInSeconds($end) / 60);
            }
        }

        return $total;
    }

    /** Waktu ketika $hours jam kerja sudah berlalu sejak $from (untuk batas respons). */
    public static function addHours(CarbonInterface $from, float $hours): Carbon
    {
        $left = (int) round($hours * 60);
        $cursor = Carbon::instance($from)->setTimezone(config('app.timezone'));
        $day = $cursor->copy()->startOfDay();

        for ($i = 0; $i < self::MAX_DAYS; $i++, $day->addDay()) {
            if (! self::isWorkday($day)) {
                continue;
            }
            [$open, $close] = self::window($day);
            $start = $cursor->gt($open) ? $cursor->copy() : $open;
            if ($start->gte($close)) {
                continue;
            }
            $available = (int) floor($start->diffInSeconds($close) / 60);
            if ($left <= $available) {
                return $start->addMinutes($left);
            }
            $left -= $available;
        }

        return $cursor->copy()->addHours((int) ceil($hours)); // tidak terjadi pada konfigurasi wajar
    }

    public static function isWorkday(CarbonInterface $day): bool
    {
        $cfg = config('halodek.work_hours');

        return in_array($day->dayOfWeekIso, $cfg['days'], true)
            && ! in_array($day->toDateString(), $cfg['holidays'] ?? [], true);
    }

    /** Durasi untuk tampilan: "kurang dari 1 jam kerja" atau "6.5 jam kerja". */
    public static function human(?int $minutes): string
    {
        if ($minutes === null) {
            return '-';
        }
        if ($minutes < 60) {
            return 'kurang dari 1 jam kerja';
        }
        $h = round($minutes / 60, 1);

        return ($h == (int) $h ? (int) $h : $h).' jam kerja';
    }

    /** Teks singkat untuk tampilan, mis. "Sen-Jum 07.00-16.00". */
    public static function label(): string
    {
        $cfg = config('halodek.work_hours');
        $names = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $days = $cfg['days'];
        $range = count($days) > 1 && max($days) - min($days) === count($days) - 1
            ? $names[min($days)].'-'.$names[max($days)]
            : implode(', ', array_map(fn ($d) => $names[$d], $days));

        return $range.' '.str_replace(':', '.', $cfg['start']).'-'.str_replace(':', '.', $cfg['end']);
    }

    /** @return array{0:Carbon,1:Carbon} */
    private static function window(CarbonInterface $day): array
    {
        $cfg = config('halodek.work_hours');
        [$sh, $sm] = array_map('intval', explode(':', $cfg['start']));
        [$eh, $em] = array_map('intval', explode(':', $cfg['end']));

        return [Carbon::instance($day)->setTime($sh, $sm), Carbon::instance($day)->setTime($eh, $em)];
    }
}
