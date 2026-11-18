<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Category;
use App\Models\Location;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sisi pelapor: ajukan laporan ke regu mana pun, pantau, balas, dan batalkan.
 * Dipakai semua role (admin pun bisa melapor sebagai karyawan).
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'semua');
        $search = trim((string) $request->query('q'));
        $teamId = $request->integer('team_id') ?: null;

        $reports = Report::with(['team', 'category'])
            ->where('user_id', $request->user()->id)
            ->when($status === 'aktif', fn ($q) => $q->open())
            ->when($status === 'ditutup', fn ($q) => $q->whereIn('status', ['ditolak', 'dibatalkan']))
            ->when(! in_array($status, ['semua', 'aktif', 'ditutup'], true), fn ($q) => $q->where('status', $status))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('report_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->latest()
            ->paginate(config('halodek.per_page'))
            ->withQueryString();

        $teams = Team::active()->ordered()->get();

        return view('employee.reports.index', compact('reports', 'status', 'search', 'teamId', 'teams'));
    }

    public function create(Request $request): View
    {
        $teams = Team::active()->ordered()
            ->with(['categories' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($t) => $t->categories->isNotEmpty())
            ->values();

        $selectedTeam = $teams->firstWhere('slug', $request->query('regu'))
            ?? $teams->firstWhere('id', (int) old('team_id'));

        $locations = Location::orderBy('name')->get(['id', 'name', 'building', 'floor']);

        // Pemetaan regu => kategori untuk JavaScript wizard.
        $categoryMap = $teams->mapWithKeys(fn ($t) => [
            $t->id => $t->categories->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'custom' => $c->allow_custom,
                'keywords' => (string) $c->keywords, 'hint' => (string) $c->hint,
            ])->values(),
        ]);

        return view('employee.reports.create', compact('teams', 'selectedTeam', 'locations', 'categoryMap'));
    }

    /** Dropdown item inventaris dinamis (AJAX) sesuai regu + lokasi yang dipilih. */
    public function items(Request $request): JsonResponse
    {
        $team = Team::active()->find($request->integer('team_id'));
        if (! $team) {
            return response()->json([]);
        }

        return response()->json(
            $this->itemOptions($request->user(), $team, $request->integer('location_id') ?: null)
                ->map(fn ($a) => ['id' => $a->id, 'label' => $a->label().($a->location ? ' · '.$a->location->name : '')])
                ->values()
        );
    }

    /**
     * Laporan AKTIF yang kemungkinan sama (barang yang sama, atau kategori + lokasi yang sama),
     * agar pelapor cukup memantau laporan yang sudah ada. Hanya peringatan, tidak memblokir.
     * Laporan orang lain ditampilkan tanpa judul/deskripsi (hanya kategori, lokasi, status).
     */
    public function similar(Request $request): JsonResponse
    {
        $teamId = $request->integer('team_id');
        $category = Category::active()->where('team_id', $teamId)->find($request->integer('category_id'));
        $locationId = $request->integer('location_id') ?: null;
        $itemId = $request->integer('inventory_item_id') ?: null;

        if (! $category || (! $itemId && (! $locationId || $category->allow_custom))) {
            return response()->json([]);
        }

        $me = $request->user();
        $rows = Report::with(['category', 'location'])->open()->where('team_id', $teamId)
            ->where(function ($q) use ($itemId, $category, $locationId) {
                if ($itemId) {
                    $q->orWhere('inventory_item_id', $itemId);
                }
                if ($locationId && ! $category->allow_custom) {
                    $q->orWhere(fn ($w) => $w->where('category_id', $category->id)->where('location_id', $locationId));
                }
            })
            ->latest()->take(3)->get();

        return response()->json($rows->map(fn (Report $r) => [
            'number' => $r->report_number,
            'label' => $r->user_id === $me->id ? $r->title : $r->categoryDisplayName().($r->location ? ' · '.$r->location->name : ''),
            'status' => $r->statusLabel(),
            'age' => $r->created_at->diffForHumans(),
            'mine' => $r->user_id === $me->id,
            'url' => $r->user_id === $me->id ? route('employee.reports.show', $r) : $r->publicUrl(),
        ])->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $att = config('halodek.attachments');

        $data = $request->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')->where('is_active', true)],
            'category_id' => ['required', 'integer'],
            'custom_category' => ['nullable', 'string', 'max:150'],
            'priority' => ['required', Rule::in(array_keys(config('halodek.priorities')))],
            'location_id' => ['nullable', 'exists:locations,id'],
            'inventory_item_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:'.$att['max_files']],
            'photos.*' => ['file', 'mimes:'.implode(',', $att['mimes']), 'max:'.$att['max_kb']],
        ], [
            'team_id.required' => 'Pilih regu tujuan laporan.',
            'category_id.required' => 'Kategori laporan wajib dipilih.',
            'description.required' => 'Deskripsi laporan wajib diisi.',
            'photos.max' => 'Lampiran maksimal '.$att['max_files'].' foto.',
            'photos.*.mimes' => 'Lampiran harus berupa foto ('.implode(', ', $att['mimes']).').',
            'photos.*.max' => 'Ukuran tiap lampiran maksimal '.round($att['max_kb'] / 1024).' MB.',
        ]);

        $team = Team::active()->findOrFail($data['team_id']);

        $category = Category::active()->where('team_id', $team->id)->find($data['category_id']);
        if (! $category) {
            return back()->withInput()->withErrors(['category_id' => 'Kategori tidak sesuai dengan regu yang dipilih.']);
        }

        if ($category->allow_custom && blank($data['custom_category'] ?? null)) {
            return back()->withInput()->withErrors(['custom_category' => 'Sebutkan kategori laporan Anda.']);
        }

        // Item inventaris hanya valid bila memang termasuk pilihan yang tersedia bagi pelapor.
        if (! empty($data['inventory_item_id'])) {
            $valid = $this->itemOptions($user, $team, $data['location_id'] ?? null)->firstWhere('id', (int) $data['inventory_item_id']);
            if (! $valid) {
                return back()->withInput()->withErrors(['inventory_item_id' => 'Barang/ruangan yang dipilih tidak tersedia untuk Anda.']);
            }
        }

        // Judul boleh dikosongkan: dibuat otomatis "Kategori – Lokasi".
        if (blank($data['title'] ?? null)) {
            $data['title'] = $this->autoTitle($category, $data);
        }

        $report = $this->reports->create($user, $team, $category, $data, $request->file('photos', []));

        return redirect()
            ->route('employee.reports.show', $report)
            ->with('success', "Laporan {$report->report_number} berhasil dikirim ke {$team->name}.");
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['team', 'category', 'item', 'location', 'assignee', 'attachments']);

        $activities = $report->activities()
            ->with('user')
            ->where('is_internal', false)
            ->get();

        return view('employee.reports.show', compact('report', 'activities'));
    }

    public function comment(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);

        $att = config('halodek.attachments');
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.$att['max_files']],
            'files.*' => ['file', 'mimes:'.implode(',', $att['mimes']), 'max:'.$att['max_kb']],
        ]);

        $this->reports->comment($report, $request->user(), $data['message'], false, $request->file('files', []));

        return back()->with('success', 'Balasan terkirim.');
    }

    /** Pelapor: "Sudah beres". */
    public function confirm(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('confirm', $report);

        $this->reports->confirm($report, $request->user());

        return back()->with('success', 'Terima kasih, laporan ditandai sudah beres.');
    }

    /** Pelapor: "Belum beres, buka lagi". */
    public function reopen(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('reopen', $report);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], [
            'reason.required' => 'Ceritakan apa yang masih bermasalah.',
        ]);
        $this->reports->reopen($report, $request->user(), $data['reason']);

        return back()->with('success', 'Laporan dibuka kembali dan diteruskan ke '.($report->assignee->name ?? $report->team->name).'.');
    }

    public function cancel(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('cancel', $report);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->reports->cancel($report, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Laporan dibatalkan.');
    }

    private function autoTitle(Category $category, array $data): string
    {
        $name = $category->allow_custom && filled($data['custom_category'] ?? null) ? trim($data['custom_category']) : $category->name;
        $location = ! empty($data['location_id']) ? Location::find($data['location_id'])?->name : null;

        return \Illuminate\Support\Str::limit($name.($location ? ' – '.$location : ''), 200, '');
    }

    /**
     * Item yang boleh dilaporkan: dipegang pelapor, atau milik bersama
     * (tanpa pemegang) di lokasi yang dipilih — mis. AC ruang rapat.
     */
    private function itemOptions(User $user, Team $team, ?int $locationId)
    {
        return InventoryItem::with('location')
            ->where('team_id', $team->id)
            ->where('status', '!=', 'non-aktif')
            ->where(function ($q) use ($user, $locationId) {
                $q->where('holder_id', $user->id);
                if ($locationId) {
                    $q->orWhere(fn ($w) => $w->whereNull('holder_id')->where('location_id', $locationId));
                }
            })
            ->orderBy('code')
            ->limit(200)
            ->get();
    }
}
