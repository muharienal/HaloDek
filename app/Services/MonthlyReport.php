<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Ringkasan bulanan: laporan yang DIBUAT pada bulan tsb, dibatasi regu yang boleh dilihat pengguna.
 * Semua agregasi dikerjakan di database.
 */
class MonthlyReport
{
    /** @return array<string,mixed> */
    public function build(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $scope = fn () => Report::query()->visibleTo($user)->whereBetween('created_at', [$start, $end]);

        // Durasi dalam JAM KERJA (kolom menit yang dihitung saat laporan direspons/diselesaikan).
        $resolveH = '(resolve_work_minutes / 60.0)';
        $respondH = '(respond_work_minutes / 60.0)';

        $sum = fn (string $status) => "SUM(CASE WHEN status = '$status' THEN 1 ELSE 0 END)";

        // Batas respons per prioritas (menit jam kerja) -> "diambil tepat waktu".
        $sla = collect(config('halodek.response_sla'))->map(fn ($h, $p) => "WHEN '".addslashes($p)."' THEN ".((int) round($h * 60)))->implode(' ');
        $onTime = "SUM(CASE WHEN respond_work_minutes IS NOT NULL AND respond_work_minutes <= (CASE priority $sla ELSE 240 END) THEN 1 ELSE 0 END)";

        $select = "count(*) as total, {$sum('menunggu')} as menunggu, {$sum('diproses')} as diproses, {$sum('selesai')} as selesai, "
            ."{$sum('ditolak')} as ditolak, {$sum('dibatalkan')} as dibatalkan, AVG($resolveH) as avg_resolve, AVG($respondH) as avg_respond, "
            ."COUNT(resolve_work_minutes) as resolved_n, COUNT(respond_work_minutes) as responded_n, $onTime as on_time, "
            ."SUM(CASE WHEN satisfied = 1 THEN 1 ELSE 0 END) as happy, COUNT(satisfied) as answered, "
            .'SUM(CASE WHEN reopened_count > 0 THEN 1 ELSE 0 END) as reopened';

        $all = $scope()->selectRaw($select)->first();
        $perTeam = $scope()->selectRaw("team_id, $select")->groupBy('team_id')->get()->keyBy('team_id');

        // Kategori per regu (diurutkan dari terbanyak).
        $catRows = $scope()->selectRaw('team_id, category_id, count(*) as c')->groupBy('team_id', 'category_id')->orderByDesc('c')->get();
        $catNames = Category::whereIn('id', $catRows->pluck('category_id')->unique())->pluck('name', 'id');
        $catsByTeam = $catRows->groupBy('team_id')->map(fn ($rows) => $rows->map(fn ($r) => [
            'name' => $catNames[$r->category_id] ?? '-',
            'count' => (int) $r->c,
        ])->values());

        $teams = Team::whereIn('id', $user->handledTeamIds() ?: [0])->ordered()->get();
        $rows = $teams->map(function (Team $t) use ($perTeam, $catsByTeam) {
            $r = $perTeam[$t->id] ?? null;
            $cats = $catsByTeam[$t->id] ?? collect();

            return [
                'team' => $t,
                'total' => (int) ($r->total ?? 0),
                'aktif' => (int) ($r->menunggu ?? 0) + (int) ($r->diproses ?? 0),
                'selesai' => (int) ($r->selesai ?? 0),
                'gagal' => (int) ($r->ditolak ?? 0) + (int) ($r->dibatalkan ?? 0),
                'avg_resolve' => $r && $r->avg_resolve !== null ? round((float) $r->avg_resolve, 1) : null,
                'avg_respond' => $r && $r->avg_respond !== null ? round((float) $r->avg_respond, 1) : null,
                ...self::quality($r),
                'top' => $cats->take(3)->all(),
                'categories' => $cats->all(),
            ];
        });

        $prevTotal = Report::query()->visibleTo($user)
            ->whereBetween('created_at', [$start->copy()->subMonthNoOverflow()->startOfMonth(), $start->copy()->subMonthNoOverflow()->endOfMonth()])->count();

        $total = (int) ($all->total ?? 0);

        return [
            'month' => $start,
            'label' => $start->translatedFormat('F Y'),
            'total' => $total,
            'menunggu' => (int) ($all->menunggu ?? 0),
            'diproses' => (int) ($all->diproses ?? 0),
            'selesai' => (int) ($all->selesai ?? 0),
            'ditolak' => (int) ($all->ditolak ?? 0),
            'dibatalkan' => (int) ($all->dibatalkan ?? 0),
            'avg_resolve' => $all && $all->avg_resolve !== null ? round((float) $all->avg_resolve, 1) : null,
            'avg_respond' => $all && $all->avg_respond !== null ? round((float) $all->avg_respond, 1) : null,
            'done_pct' => $total ? (int) round(((int) $all->selesai) / $total * 100) : 0,
            ...self::quality($all),
            'prev_total' => $prevTotal,
            'teams' => $rows,
            'scope' => $user->isSuperadmin() ? 'Semua regu' : $teams->map(fn ($t) => 'Regu '.$t->name)->implode(', '),
        ];
    }

    /**
     * Mutu layanan dari baris agregat:
     * - on_time_pct  : % laporan yang sudah ditangani dan diambil sebelum batas respons.
     * - happy_pct    : % pelapor yang menjawab "sudah beres" dari yang menjawab (👍 atau buka ulang).
     * - reopened     : jumlah laporan yang pernah dibuka ulang pelapor.
     *
     * @return array<string, int|null>
     */
    private static function quality($r): array
    {
        $responded = (int) ($r->responded_n ?? 0);
        $answered = (int) ($r->answered ?? 0);

        return [
            'resolved_n' => (int) ($r->resolved_n ?? 0),
            'responded_n' => $responded,
            'on_time' => (int) ($r->on_time ?? 0),
            'on_time_pct' => $responded ? (int) round(((int) $r->on_time) / $responded * 100) : null,
            'happy' => (int) ($r->happy ?? 0),
            'answered' => $answered,
            'happy_pct' => $answered ? (int) round(((int) $r->happy) / $answered * 100) : null,
            'reopened' => (int) ($r->reopened ?? 0),
        ];
    }

    /** Daftar pilihan bulan (12 bulan terakhir) => [ 'YYYY-MM' => 'Oktober 2026' ]. */
    public static function monthOptions(): array
    {
        $out = [];
        $m = now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $out[$m->format('Y-m')] = $m->translatedFormat('F Y');
            $m = $m->copy()->subMonthNoOverflow();
        }

        return $out;
    }

    public static function parse(?string $value): Carbon
    {
        if ($value && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value, $m) && (int) $m[1] >= 2020 && (int) $m[1] <= 2100) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    public static function slaText(): string
    {
        $labels = collect(config('halodek.priorities'))->map(fn ($p) => $p['label']);

        return collect(config('halodek.response_sla'))->map(fn ($h, $p) => ($labels[$p] ?? $p).' '.$h)->implode(' · ');
    }

    /** @return array<int, array{name:string, widths:array<int,float>, rows:array<int, array<int, mixed>>}> */
    public function sheets(array $d): array
    {
        $h = fn (string $t) => ['v' => $t, 's' => 'head'];
        $dec = fn ($v) => $v === null ? '–' : ['v' => (float) $v, 's' => 'dec'];

        $summary = [
            [['v' => 'Laporan Bulanan '.config('halodek.name'), 's' => 'title']],
            [config('halodek.company')],
            [['v' => 'Periode', 's' => 'bold'], $d['label']],
            [['v' => 'Cakupan', 's' => 'bold'], $d['scope']],
            [],
            [$h('Ringkasan'), $h('Nilai')],
            ['Total laporan masuk', $d['total']],
            ['Menunggu', $d['menunggu']],
            ['Diproses', $d['diproses']],
            ['Selesai', $d['selesai']],
            ['Ditolak', $d['ditolak']],
            ['Dibatalkan', $d['dibatalkan']],
            ['Tingkat selesai (%)', $d['done_pct']],
            ['Rata-rata waktu penyelesaian (jam kerja)', $dec($d['avg_resolve'])],
            ['  dihitung dari laporan yang sudah selesai', $d['resolved_n']],
            ['Rata-rata respons pertama (jam kerja)', $dec($d['avg_respond'])],
            ['  dihitung dari laporan yang sudah ditangani', $d['responded_n']],
            ['Diambil tepat waktu (%)', $d['on_time_pct'] ?? '–'],
            ['Kepuasan pelapor: sudah beres (%)', $d['happy_pct'] ?? '–'],
            ['  pelapor yang menjawab', $d['answered']],
            ['Laporan dibuka ulang pelapor', $d['reopened']],
            ['Total laporan bulan sebelumnya', $d['prev_total']],
            [],
            ['Jam kerja', \App\Support\WorkHours::label().' WIB'],
            ['Batas respons (jam kerja)', self::slaText()],
            [['v' => 'Dibuat '.now()->translatedFormat('d F Y H:i').' WIB', 's' => 'bold']],
        ];

        $perTeam = [[$h('Regu'), $h('Total'), $h('Aktif'), $h('Selesai'), $h('Ditolak/Dibatalkan'), $h('Rata-rata penyelesaian (jam kerja)'), $h('Rata-rata respons (jam kerja)'), $h('Tepat waktu (%)'), $h('Sudah beres (%)'), $h('Dibuka ulang'), $h('Kategori terbanyak')]];
        $cats = [[$h('Regu'), $h('Kategori'), $h('Jumlah'), $h('Persen dari regu (%)')]];
        foreach ($d['teams'] as $t) {
            $top = $t['top'][0] ?? null;
            $perTeam[] = [$t['team']->name, $t['total'], $t['aktif'], $t['selesai'], $t['gagal'], $dec($t['avg_resolve']), $dec($t['avg_respond']),
                $t['on_time_pct'] ?? '–', $t['happy_pct'] ?? '–', $t['reopened'],
                $top ? $top['name'].' ('.$top['count'].')' : '–'];
            foreach ($t['categories'] as $c) {
                $cats[] = [$t['team']->name, $c['name'], $c['count'], $t['total'] ? (int) round($c['count'] / $t['total'] * 100) : 0];
            }
        }

        return [
            ['name' => 'Ringkasan', 'widths' => [40, 30], 'rows' => $summary],
            ['name' => 'Per Regu', 'widths' => [24, 9, 9, 9, 20, 26, 24, 16, 16, 13, 34], 'rows' => $perTeam],
            ['name' => 'Kategori', 'widths' => [24, 36, 10, 20], 'rows' => $cats],
        ];
    }
}
