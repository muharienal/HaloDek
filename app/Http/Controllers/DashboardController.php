<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return $user->isAdmin() ? $this->admin($user) : $this->employee($user);
    }

    // ------------------------------------------------------------ karyawan

    private function employee(User $user): View
    {
        $counts = Report::where('user_id', $user->id)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $stats = [
            'total' => $counts->sum(),
            'aktif' => ($counts['menunggu'] ?? 0) + ($counts['diproses'] ?? 0),
            'selesai' => $counts['selesai'] ?? 0,
            'lainnya' => ($counts['ditolak'] ?? 0) + ($counts['dibatalkan'] ?? 0),
        ];

        $teams = Team::active()->ordered()->with(['categories' => fn ($q) => $q->where('is_active', true)])->get()
            ->filter(fn ($t) => $t->categories->isNotEmpty())->values();

        $recent = Report::with(['team', 'category'])->where('user_id', $user->id)->latest()->take(5)->get();

        $announcements = $this->announcementsFor($user);

        return view('dashboard.employee', compact('stats', 'teams', 'recent', 'announcements'));
    }

    // --------------------------------------------------------------- admin

    private function admin(User $user): View
    {
        $teamIds = $user->handledTeamIds() ?: [0];
        $teams = Team::whereIn('id', $teamIds)->ordered()->get();
        $scope = fn () => Report::query()->whereIn('team_id', $teamIds);

        $counts = $scope()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $stats = [
            'total' => $counts->sum(),
            'menunggu' => $counts['menunggu'] ?? 0,
            'diproses' => $counts['diproses'] ?? 0,
            'selesai' => $counts['selesai'] ?? 0,
            'ditolak' => $counts['ditolak'] ?? 0,
            'dibatalkan' => $counts['dibatalkan'] ?? 0,
            'unassigned' => $scope()->unassigned()->count(),
            'mine' => Report::open()->where('assigned_to_id', $user->id)->count(),
        ];

        // Rata-rata durasi (JAM KERJA) dihitung di database dari kolom yang sudah disimpan saat selesai.
        $avg = $scope()->whereNotNull('resolve_work_minutes')->where('resolved_at', '>=', now()->subDays(90))->avg('resolve_work_minutes');
        $stats['avg_hours'] = $avg === null ? null : round((float) $avg / 60, 1);

        // Lewat batas respons: hanya laporan di antrean (jumlahnya kecil), dicek di PHP karena batasnya memakai jam kerja.
        $overdue = $scope()->with(['team', 'category'])->unassigned()->oldest()->get()->filter->isOverdue()->values();
        $stats['overdue'] = $overdue->count();

        $trend = $this->trend($scope, 14);
        $byCategory = $this->byCategory($scope);
        $perTeam = $scope()->selectRaw('team_id, status, count(*) as c')->groupBy('team_id', 'status')->get()->groupBy('team_id');
        $byTeam = $teams->map(fn ($t) => [
            'team' => $t,
            'total' => (int) ($perTeam[$t->id] ?? collect())->sum('c'),
            'open' => (int) ($perTeam[$t->id] ?? collect())->whereIn('status', Report::OPEN_STATUSES)->sum('c'),
        ]);

        // Satu daftar "Perlu Ditangani": tugas saya + laporan yang menunggu diambil.
        // Urutan: lewat batas dulu, lalu prioritas tertinggi, lalu yang paling lama.
        $rank = ['mendesak' => 0, 'tinggi' => 1, 'sedang' => 2, 'rendah' => 3];
        $overdueIds = $overdue->pluck('id')->all();
        $todo = Report::with(['team', 'category'])->open()
            ->where(fn ($q) => $q->where('assigned_to_id', $user->id)->orWhere(fn ($w) => $w->whereIn('team_id', $teamIds)->whereNull('assigned_to_id')))
            ->oldest()->take(200)->get()
            ->sortBy(fn ($r) => [in_array($r->id, $overdueIds, true) ? 0 : 1, $rank[$r->priority] ?? 9, $r->created_at->timestamp])
            ->take(8)->values();

        $recent = $scope()->with(['team', 'category', 'assignee'])->latest()->take(6)->get();

        $workload = $user->isStaf() ? collect() : $this->workload($user, $teamIds);

        $topAssets = $this->topAssets($teamIds);

        $announcements = $this->announcementsFor($user);

        return view('dashboard.admin', compact(
            'teams', 'stats', 'overdue', 'todo', 'trend', 'byCategory', 'byTeam', 'recent',
            'workload', 'topAssets', 'announcements'
        ));
    }

    /** @return array{labels:array<int,string>, created:array<int,int>, resolved:array<int,int>} */
    private function trend(callable $scope, int $days): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $created = $scope()->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');
        $resolved = $scope()->where('resolved_at', '>=', $from)->selectRaw('DATE(resolved_at) as d, count(*) as c')->groupBy('d')->pluck('c', 'd');

        $labels = $c = $r = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $labels[] = $day->translatedFormat('d M');
            $c[] = (int) ($created[$key] ?? 0);
            $r[] = (int) ($resolved[$key] ?? 0);
        }

        return ['labels' => $labels, 'created' => $c, 'resolved' => $r];
    }

    /** @return Collection<int, array{name:string,total:int}> */
    private function byCategory(callable $scope): Collection
    {
        $rows = $scope()->selectRaw('category_id, count(*) as c')->groupBy('category_id')->orderByDesc('c')->take(6)->pluck('c', 'category_id');
        $names = Category::with('team')->whereIn('id', $rows->keys())->get()->keyBy('id');

        return $rows->map(fn ($c, $id) => [
            'name' => $names[$id]->name ?? '-',
            'team' => $names[$id]->team->name ?? '',
            'total' => (int) $c,
        ])->values();
    }

    /** Beban kerja anggota regu: jumlah laporan aktif yang dipegang masing-masing. */
    private function workload(User $user, array $teamIds): Collection
    {
        return User::active()->whereIn('team_id', $teamIds)->whereIn('role', User::TEAM_ROLES)
            ->withCount(['assignedReports as open_tasks' => fn ($q) => $q->whereIn('status', Report::OPEN_STATUSES)])
            ->orderByDesc('open_tasks')->orderBy('name')->take(8)->get()
            ->map(fn ($u) => ['user' => $u, 'open' => (int) $u->open_tasks]);
    }

    /**
     * Barang yang paling sering dilaporkan 90 hari terakhir (dari salinan data barang di laporan),
     * sebagai petunjuk barang yang perlu diganti/diservis. Data aset lengkap ada di aplikasi Inventaris.
     */
    private function topAssets(array $teamIds): Collection
    {
        return Report::whereIn('team_id', $teamIds)->whereNotNull('asset_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('asset_id, MAX(asset_code) as code, MAX(asset_name) as name, COUNT(*) as total, SUM(CASE WHEN status IN (\'menunggu\', \'diproses\') THEN 1 ELSE 0 END) as open_count')
            ->groupBy('asset_id')->orderByDesc('total')->take(5)->get();
    }

    private function announcementsFor(User $user): Collection
    {
        return Announcement::with('user')
            ->current()
            ->latestFirst()
            ->take(4)
            ->get();
    }
}
