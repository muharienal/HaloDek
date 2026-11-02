<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return view('admin.reports.index', compact('reports', 'filters', 'statusCounts', 'teams', 'staff'));
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);

        $report->load(['team', 'category', 'item.location', 'item.category', 'location', 'assignee', 'user', 'attachments']);

        $activities = $report->activities()->with('user')->get();

        // Calon penanggung jawab: anggota regu (Kepala Regu + Staf) dan Superadmin.
        $staff = $report->team->users()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'role']);
        $staff = $staff->merge(User::active()->where('role', User::ROLE_SUPERADMIN)->get(['id', 'name', 'role']))->unique('id')->values();

        $otherTeams = Team::active()->where('id', '!=', $report->team_id)->ordered()
            ->with(['categories' => fn ($q) => $q->where('is_active', true)])->get()
            ->filter(fn ($t) => $t->categories->isNotEmpty())->values();

        $history = $report->inventory_item_id
            ? Report::where('inventory_item_id', $report->inventory_item_id)->where('id', '!=', $report->id)->latest()->take(5)->get(['id', 'report_number', 'title', 'status', 'created_at'])
            : collect();

        return view('admin.reports.show', compact('report', 'activities', 'staff', 'otherTeams', 'history'));
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

        return back()->with('success', 'Tindak lanjut berhasil disimpan.');
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

        return back()->with('success', 'Tugas dilepas. Laporan kembali ke antrean regu.');
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

        $report->load(['team', 'category', 'item', 'location', 'assignee', 'user']);
        $activities = $report->activities()->with('user')->where('is_internal', false)->get();

        return view('admin.reports.print', compact('report', 'activities'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request)->with(['team', 'category', 'assignee', 'location', 'item'])->latest();

        $rows = (function () use ($query) {
            foreach ($query->cursor() as $r) {
                yield [
                    $r->report_number,
                    $r->created_at->format('Y-m-d H:i'),
                    $r->team->name ?? '',
                    $r->categoryDisplayName(),
                    $r->title,
                    $r->reporter_name,
                    $r->location->name ?? '',
                    $r->item->code ?? '',
                    $r->priorityLabel(),
                    $r->statusLabel(),
                    $r->assignee->name ?? '',
                    $r->first_response_at?->format('Y-m-d H:i'),
                    $r->resolved_at?->format('Y-m-d H:i'),
                    $r->resolutionHours(),
                    $r->follow_up_note,
                ];
            }
        })();

        return Csv::download('laporan-halodek-'.now()->format('Ymd-His').'.csv', [
            'No. Laporan', 'Dibuat', 'Regu', 'Kategori', 'Judul', 'Pelapor', 'Lokasi', 'Kode Inventaris',
            'Prioritas', 'Status', 'Penanggung Jawab', 'Respons Pertama', 'Selesai', 'Durasi (jam)', 'Catatan Tindak Lanjut',
        ], $rows);
    }

    // ------------------------------------------------------------------ util

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
            ->when($f['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('report_number', 'like', "%{$f['q']}%")
                ->orWhere('reporter_name', 'like', "%{$f['q']}%")
                ->orWhere('title', 'like', "%{$f['q']}%")));
    }
}
