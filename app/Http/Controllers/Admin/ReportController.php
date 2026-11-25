<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $this->filters($request);

        $reports = $this->query($request)
            ->with(['team', 'category', 'assignee'])
            ->orderByRaw("CASE status WHEN 'menunggu' THEN 0 WHEN 'diproses' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'mendesak' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->latest()
            ->paginate(config('halodek.per_page'))
            ->withQueryString();

        // Hitungan per status (mengikuti filter lain, kecuali status).
        $statusCounts = $this->query($request, ignoreStatus: true)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $teams = $this->handledTeams();
        $staff = User::active()->admins()
            ->whereIn('team_id', $user->handledTeamIds() ?: [0])
            ->orderBy('name')->get(['id', 'name']);

        $overdueIds = $this->overdueIds($user);

        return view('admin.reports.index', compact('reports', 'filters', 'statusCounts', 'teams', 'staff', 'overdueIds'));
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['team', 'category', 'assignee', 'user', 'attachments']);

        $activities = $report->activities()->with('user')->get();

        // Calon penanggung jawab: anggota regu (Kepala Regu + Staf) dan Superadmin.
        $staff = $report->team->users()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']);
        $staff = $staff->merge(User::active()->where('role', User::ROLE_SUPERADMIN)->get(['id', 'name', 'role']))->unique('id')->values();

        $otherTeams = Team::active()->where('id', '!=', $report->team_id)->ordered()
            ->with(['categories' => fn ($q) => $q->where('is_active', true)])->get()
            ->filter(fn ($t) => $t->categories->isNotEmpty())->values();

        $history = $report->asset_id
            ? Report::where('asset_id', $report->asset_id)->where('id', '!=', $report->id)->latest()->take(5)->get(['id', 'report_number', 'title', 'status', 'created_at'])
            : collect();

        $assetUrl = $report->asset_id ? app(\App\Services\InventoryClient::class)->assetWebUrl($report->asset_id) : null;

        return view('admin.reports.show', compact('report', 'activities', 'staff', 'otherTeams', 'history', 'assetUrl'));
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);

        $att = config('halodek.attachments');
        $canAssign = $request->user()->can('assign', $report);

        $data = $request->validate([
            'status' => ['required', Rule::in(['menunggu', 'diproses', 'selesai', 'ditolak'])],
            'priority' => [$canAssign ? 'required' : 'nullable', Rule::in(array_keys(config('halodek.priorities')))],
            'assigned_to_id' => ['nullable', 'integer'],
            'follow_up_note' => ['nullable', 'string', 'max:3000'],
            'files' => ['nullable', 'array', 'max:'.$att['max_files']],
            'files.*' => ['file', 'mimes:'.implode(',', $att['mimes']), 'max:'.$att['max_kb']],
        ], [
            'status.required' => 'Status wajib dipilih.',
        ]);

        // Staf hanya boleh mengubah status, catatan, dan lampiran; prioritas & penugasan milik Kepala Regu.
        if (! $canAssign) {
            unset($data['priority'], $data['assigned_to_id']);
        }

        // Menyelesaikan / menolak wajib disertai catatan agar pelapor paham alasannya.
        if (in_array($data['status'], ['selesai', 'ditolak'], true)
            && $data['status'] !== $report->status
            && blank($data['follow_up_note'] ?? null)) {
            return back()->withInput()->withErrors([
                'follow_up_note' => $data['status'] === 'selesai'
                    ? 'Tuliskan hasil penyelesaian sebelum menandai laporan selesai.'
                    : 'Tuliskan alasan penolakan agar pelapor memahaminya.',
            ]);
        }

        if (! empty($data['assigned_to_id'])) {
            $valid = User::active()->whereKey($data['assigned_to_id'])
                ->where(fn ($q) => $q->where(fn ($w) => $w->where('team_id', $report->team_id)->whereIn('role', User::TEAM_ROLES))
                    ->orWhere('role', User::ROLE_SUPERADMIN))
                ->exists();
            if (! $valid) {
                return back()->withInput()->withErrors(['assigned_to_id' => 'Penanggung jawab harus anggota regu '.$report->team->name.'.']);
            }
        }

        $this->reports->followUp($report, $request->user(), $data, $request->file('files', []));

        return back()->with('success', 'Perubahan disimpan.');
    }

    /** Tombol cepat "Selesaikan" / "Tolak": satu catatan wajib (+ foto hasil opsional). */
    public function close(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('manage', $report);
        abort_unless($report->isOpen(), 422, 'Laporan ini sudah ditutup.');

        $att = config('halodek.attachments');
        $data = $request->validate([
            'status' => ['required', Rule::in(['selesai', 'ditolak'])],
            'close_note' => ['required', 'string', 'max:3000'],
            'files' => ['nullable', 'array', 'max:'.$att['max_files']],
            'files.*' => ['file', 'mimes:'.implode(',', $att['mimes']), 'max:'.$att['max_kb']],
        ], [
            'close_note.required' => $request->input('status') === 'ditolak'
                ? 'Tuliskan alasan penolakan agar pelapor memahaminya.'
                : 'Tuliskan hasil penyelesaian untuk pelapor.',
        ]);

        $this->reports->followUp($report, $request->user(), [
            'status' => $data['status'],
            'follow_up_note' => $data['close_note'],
        ], $request->file('files', []));

        return back()->with('success', $data['status'] === 'selesai' ? 'Laporan ditandai selesai. Pelapor sudah diberi tahu.' : 'Laporan ditolak. Pelapor sudah diberi tahu alasannya.');
    }

    /**
     * Aksi massal dari daftar laporan: ambil, tugaskan, selesaikan, atau tolak sekaligus.
     * Hak akses dicek per laporan; yang tidak berhak / sudah ditutup dilewati (bukan menggagalkan semuanya).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['ambil', 'tugaskan', 'selesai', 'ditolak'])],
            'assigned_to_id' => ['nullable', 'required_if:action,tugaskan', 'integer'],
            'bulk_note' => ['nullable', 'required_if:action,selesai,ditolak', 'string', 'max:3000'],
        ], [
            'ids.required' => 'Pilih minimal satu laporan.',
            'ids.max' => 'Maksimal 50 laporan sekaligus.',
            'assigned_to_id.required_if' => 'Pilih penanggung jawab.',
            'bulk_note.required_if' => 'Tuliskan catatan untuk pelapor.',
        ]);

        $assignee = $data['action'] === 'tugaskan' ? User::active()->find($data['assigned_to_id']) : null;
        if ($data['action'] === 'tugaskan' && ! $assignee) {
            return back()->withErrors(['assigned_to_id' => 'Penanggung jawab tidak ditemukan atau tidak aktif.']);
        }

        $reports = Report::visibleTo($user)->whereIn('id', $data['ids'])->get();
        $done = 0;
        foreach ($reports as $report) {
            $ok = match ($data['action']) {
                'ambil' => $user->can('claim', $report) && ! $report->assigned_to_id,
                'tugaskan' => $report->isOpen() && $user->can('assign', $report) && $this->canHandle($assignee, $report),
                default => $report->isOpen() && $user->can('manage', $report),
            };
            if (! $ok) {
                continue;
            }

            try {
                match ($data['action']) {
                    'ambil' => $this->reports->claim($report, $user),
                    'tugaskan' => $this->reports->followUp($report, $user, ['assigned_to_id' => $assignee->id]),
                    default => $this->reports->followUp($report, $user, ['status' => $data['action'], 'follow_up_note' => $data['bulk_note']]),
                };
            } catch (\App\Exceptions\ReportConflict) {
                continue; // keadaannya berubah di tengah jalan (mis. baru diambil orang lain): lewati
            }
            $done++;
        }

        $verb = ['ambil' => 'diambil', 'tugaskan' => 'ditugaskan ke '.($assignee->name ?? ''), 'selesai' => 'ditandai selesai', 'ditolak' => 'ditolak'][$data['action']];
        $skipped = count(array_unique($data['ids'])) - $done;

        if ($done === 0) {
            return back()->with('error', 'Tidak ada laporan yang bisa diproses. Laporan yang dipilih sudah ditutup, sudah ada penanggung jawabnya, atau di luar wewenang Anda.');
        }

        return back()->with('success', "{$done} laporan {$verb}.".($skipped > 0 ? " {$skipped} dilewati karena sudah ditutup atau di luar wewenang Anda." : ''));
    }

    private function canHandle(User $assignee, Report $report): bool
    {
        return $assignee->isSuperadmin() || ($assignee->team_id === $report->team_id && in_array($assignee->role, User::TEAM_ROLES, true));
    }

    /** Ambil laporan: jadi penanggung jawab dan mulai diproses. */
    public function take(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('claim', $report);

        $this->reports->claim($report, $request->user());

        return back()->with('success', 'Laporan Anda ambil dan mulai diproses.');
    }

    /** Lepas tugas: laporan kembali ke antrean regu. */
    public function release(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('release', $report);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->reports->release($report, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Laporan dilepas dan kembali menunggu diambil.');
    }

    public function transfer(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('assign', $report);

        $data = $request->validate([
            'team_id' => ['required', Rule::exists('teams', 'id')->where('is_active', true)],
            'category_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'team_id.required' => 'Pilih regu tujuan.',
            'category_id.required' => 'Pilih kategori di regu tujuan.',
            'reason.required' => 'Tuliskan alasan pengalihan.',
        ]);

        if ((int) $data['team_id'] === $report->team_id) {
            return back()->withErrors(['team_id' => 'Regu tujuan sama dengan regu saat ini.']);
        }

        $team = Team::active()->findOrFail($data['team_id']);
        $category = Category::active()->where('team_id', $team->id)->find($data['category_id']);
        if (! $category) {
            return back()->withInput()->withErrors(['category_id' => 'Kategori tidak sesuai dengan regu tujuan.']);
        }

        $this->reports->transfer($report, $request->user(), $team, $category, $data['reason']);

        // Admin non-super mungkin kehilangan akses setelah dialihkan.
        if (! $request->user()->handlesTeam($team->id)) {
            return redirect()->route('admin.reports.index')->with('success', "Laporan dialihkan ke {$team->name}.");
        }

        return back()->with('success', "Laporan dialihkan ke {$team->name}.");
    }

    public function comment(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);

        $att = config('halodek.attachments');
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'is_internal' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:'.$att['max_files']],
            'files.*' => ['file', 'mimes:'.implode(',', $att['mimes']), 'max:'.$att['max_kb']],
        ]);

        $this->reports->comment($report, $request->user(), $data['message'], $request->boolean('is_internal'), $request->file('files', []));

        return back()->with('success', $request->boolean('is_internal') ? 'Catatan internal disimpan.' : 'Balasan terkirim ke pelapor.');
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        Audit::log('report.delete', "Menghapus laporan {$report->report_number}.", $report);
        $report->delete();

        return redirect()->route('admin.reports.index')->with('success', "Laporan {$report->report_number} dihapus.");
    }

    public function print(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['team', 'category', 'assignee', 'user']);
        $activities = $report->activities()->with('user')->where('is_internal', false)->get();

        return view('admin.reports.print', compact('report', 'activities'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = $this->query($request)->with(['team', 'category', 'assignee'])->latest();

        $rows = [array_map(fn ($h) => ['v' => $h, 's' => 'head'], [
            'No. Laporan', 'Dibuat', 'Regu', 'Kategori', 'Judul', 'Pelapor', 'Lokasi', 'Kode Barang',
            'Prioritas', 'Status', 'Penanggung Jawab', 'Respons Pertama', 'Selesai', 'Durasi (jam kerja)', 'Dibuka Ulang', 'Catatan Penanganan',
        ])];
        foreach ($query->cursor() as $r) {
            $rows[] = [
                $r->report_number,
                $r->created_at->format('Y-m-d H:i'),
                $r->team->name ?? '',
                $r->categoryDisplayName(),
                $r->title,
                $r->reporter_name,
                $r->location_name ?? '',
                $r->asset_code ?? '',
                $r->priorityLabel(),
                $r->statusLabel(),
                $r->assignee->name ?? '',
                $r->first_response_at?->format('Y-m-d H:i') ?? '',
                $r->resolved_at?->format('Y-m-d H:i') ?? '',
                ($h = $r->resolutionHours()) !== null ? ['v' => $h, 's' => 'dec'] : '',
                $r->reopened_count ? ['v' => $r->reopened_count, 's' => 'int'] : '',
                (string) $r->follow_up_note,
            ];
        }

        $path = Xlsx::build([[
            'name' => 'Laporan',
            'widths' => [20, 17, 18, 24, 40, 22, 20, 16, 11, 12, 22, 17, 17, 12, 12, 50],
            'rows' => $rows,
        ]]);

        return response()->download($path, 'laporan-halodek-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ------------------------------------------------------------------ util

    /**
     * ID laporan lewat batas respons yang boleh dilihat pengguna. Hanya laporan di antrean
     * (belum ada penanggung jawab) yang dicek, jadi jumlahnya kecil.
     *
     * @return array<int,int>
     */
    private function overdueIds($user): array
    {
        return $this->overdueCache ??= Report::query()->visibleTo($user)->unassigned()
            ->get(['id', 'created_at', 'priority', 'status', 'assigned_to_id'])
            ->filter->isOverdue()->pluck('id')->all();
    }

    /** @var array<int,int>|null */
    private ?array $overdueCache = null;

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'status' => $request->query('status', 'semua'),
            'team_id' => $request->integer('team_id') ?: null,
            'priority' => $request->query('priority'),
            'assigned' => $request->query('assigned'), // me | none | <user id>
            'q' => trim((string) $request->query('q')),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'lewat' => $request->boolean('lewat'),
        ];
    }

    private function query(Request $request, bool $ignoreStatus = false): Builder
    {
        $user = $request->user();
        $f = $this->filters($request);

        return Report::query()
            ->visibleTo($user)
            ->when(! $ignoreStatus && $f['status'] === 'aktif', fn ($q) => $q->open())
            ->when(! $ignoreStatus && ! in_array($f['status'], ['semua', 'aktif'], true), fn ($q) => $q->where('status', $f['status']))
            ->when($f['team_id'], fn ($q) => $q->where('team_id', $f['team_id']))
            ->when($f['priority'], fn ($q) => $q->where('priority', $f['priority']))
            ->when($f['assigned'] === 'me', fn ($q) => $q->where('assigned_to_id', $user->id))
            ->when($f['assigned'] === 'none', fn ($q) => $q->whereNull('assigned_to_id'))
            ->when(is_numeric($f['assigned']), fn ($q) => $q->where('assigned_to_id', (int) $f['assigned']))
            ->when($f['from'], fn ($q) => $q->whereDate('created_at', '>=', $f['from']))
            ->when($f['to'], fn ($q) => $q->whereDate('created_at', '<=', $f['to']))
            ->when($f['lewat'], fn ($q) => $q->whereIn('id', $this->overdueIds($user) ?: [0]))
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('report_number', 'like', "%{$f['q']}%")
                ->orWhere('reporter_name', 'like', "%{$f['q']}%")
                ->orWhere('title', 'like', "%{$f['q']}%")));
    }
}
