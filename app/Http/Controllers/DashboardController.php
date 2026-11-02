<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
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

        $teams = Team::active()->ordered()->withCount(['categories' => fn ($q) => $q->where('is_active', true)])->get()
            ->filter(fn ($t) => $t->categories_count > 0);

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

        $resolved = $scope()->whereNotNull('resolved_at')->where('resolved_at', '>=', now()->subDays(90))->get(['created_at', 'resolved_at']);
        $stats['avg_hours'] = $resolved->isEmpty() ? null : round($resolved->avg(fn ($r) => $r->created_at->diffInMinutes($r->resolved_at) / 60), 1);

        $trend = $this->trend($scope, 14);
        $byCategory = $this->byCategory($scope);
        $byTeam = $teams->map(fn ($t) => [
            'team' => $t,
            'total' => $scope()->where('team_id', $t->id)->count(),
            'open' => $scope()->where('team_id', $t->id)->open()->count(),
        ]);

        // Antrean: laporan aktif yang belum ada penanggung jawab, yang mendesak & terlama lebih dulu.
        $queue = $scope()->with(['team', 'category'])->unassigned()
            ->orderByRaw("CASE priority WHEN 'mendesak' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->oldest()->take(5)->get();

        // Tugas saya (Kepala Regu / Staf): laporan aktif yang dipegang sendiri.
        $myTasks = Report::with(['team', 'category'])->open()->where('assigned_to_id', $user->id)
            ->orderByRaw("CASE priority WHEN 'mendesak' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->oldest()->take(5)->get();

        $recent = $scope()->with(['team', 'category', 'assignee'])->latest()->take(6)->get();

        $workload = $user->isStaf() ? collect() : $this->workload($user, $teamIds);

        $inventory = $this->inventory($teamIds);

        $announcements = $this->announcementsFor($user);

        return view('dashboard.admin', compact(
            'teams', 'stats', 'trend', 'byCategory', 'byTeam', 'queue', 'myTasks', 'recent',
            'workload', 'inventory', 'announcements'
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

    /** Ringkasan inventaris regu yang ditangani. */
    private function inventory(array $teamIds): array
    {
        $items = InventoryItem::whereIn('team_id', $teamIds);
        $cats = InventoryCategory::whereIn('team_id', $teamIds)->withCount('items')->orderByDesc('items_count')->take(6)->get();

        return [
            'by_category' => $cats->pluck('items_count', 'name'),
            'total' => (clone $items)->count(),
            'broken' => (clone $items)->whereIn('status', ['rusak', 'perbaikan'])->count(),
            'due' => (clone $items)->dueSoon()->count(),
        ];
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
