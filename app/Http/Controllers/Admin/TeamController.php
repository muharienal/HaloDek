<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Regu / bidang penerima laporan. Ada empat regu bawaan (IT, Sarana dan Prasarana,
 * Kesekretariatan, Keamanan); Superadmin dapat menambah regu lain tanpa mengubah kode.
 * Setiap regu punya satu Kepala Regu dan beberapa Staf.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $teams = Team::query()
            ->when(! $user->isSuperadmin(), fn ($q) => $q->whereIn('id', $user->handledTeamIds() ?: [0]))
            ->with('head')
            ->withCount([
                'users', 'categories', 'inventoryItems', 'reports',
                'reports as open_reports_count' => fn ($q) => $q->whereIn('status', ['menunggu', 'diproses']),
            ])
            ->ordered()
            ->get();

        return view('admin.teams.index', compact('teams'));
    }

    public function show(Request $request, Team $team): View
    {
        $this->authorizeTeam($team->id);

        $team->load(['categories' => fn ($q) => $q->withCount('reports')]);

        // Kepala Regu tampil lebih dulu, lalu Staf; beban kerja = laporan aktif yang ia pegang.
        $members = $team->users()
            ->withCount(['assignedReports as open_tasks_count' => fn ($q) => $q->whereIn('status', ['menunggu', 'diproses'])])
            ->orderByRaw("CASE role WHEN 'kepala_regu' THEN 0 ELSE 1 END")->orderBy('name')->get();

        // Calon anggota: pengguna aktif yang belum punya regu (karyawan) atau pindahan dari regu lain.
        $candidates = $request->user()->isSuperadmin()
            ? User::active()->where('role', '!=', User::ROLE_SUPERADMIN)
                ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', '!=', $team->id))
                ->orderBy('name')->limit(1500)->get(['id', 'name', 'nik', 'role', 'team_id'])
            : collect();

        $stats = [
            'open' => $team->reports()->open()->count(),
            'total' => $team->reports()->count(),
            'unassigned' => $team->reports()->unassigned()->count(),
            'avg_hours' => $this->avgHours($team),
        ];

        return view('admin.teams.show', compact('team', 'members', 'candidates', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['sort_order'] = (int) Team::max('sort_order') + 1;

        $team = Team::create($data);

        // Setiap regu baru langsung punya kategori "Lainnya" agar bisa menerima laporan.
        $team->categories()->create(['name' => 'Lainnya', 'allow_custom' => true, 'sort_order' => 999]);

        Audit::log('team.create', "Membuat regu {$team->name} ({$team->code}).", $team);

        return redirect()->route('admin.teams.show', $team)
            ->with('success', "Regu {$team->name} dibuat. Tambahkan kategori dan anggota regu.");
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $data = $this->validated($request, $team);

        if ($team->is_system) {
            $data['is_active'] = true; // regu bawaan tidak boleh dimatikan
            $data['code'] = $team->code; // kode tiket regu bawaan tetap
        }

        $team->update($data);
        Audit::log('team.update', "Mengubah regu {$team->name}.", $team);

        return back()->with('success', 'Regu berhasil diperbarui.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        if ($team->is_system) {
            return back()->with('error', 'Regu bawaan tidak dapat dihapus.');
        }

        if ($team->reports()->withTrashed()->exists()) {
            return back()->with('error', 'Regu sudah memiliki riwayat laporan. Nonaktifkan saja agar data tetap utuh.');
        }

        Audit::log('team.delete', "Menghapus regu {$team->name}.", $team);
        $team->delete();

        return redirect()->route('admin.teams.index')->with('success', 'Regu berhasil dihapus.');
    }

    /** Tambah / pindahkan pengguna ke regu ini sebagai Kepala Regu atau Staf. */
    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'role' => ['required', Rule::in(User::TEAM_ROLES)],
        ], ['user_id.required' => 'Pilih pengguna.']);

        $user = User::findOrFail($data['user_id']);

        if ($user->isSuperadmin()) {
            return back()->with('error', 'Superadmin tidak terikat ke regu mana pun.');
        }

        if ($data['role'] === User::ROLE_KEPALA_REGU && ($current = $this->currentHead($team)) && $current->id !== $user->id) {
            return back()->with('error', "Regu {$team->name} sudah dipimpin {$current->name}. Gunakan \"Jadikan Kepala Regu\" pada anggota yang ada untuk mengganti kepala.");
        }

        $temp = null;
        $attrs = ['role' => $data['role'], 'team_id' => $team->id];
        if ($user->isKaryawan()) {
            // Karyawan yang dinaikkan jadi pengelola wajib punya password: buat sementara & acak.
            $temp = User::temporaryPassword();
            $attrs += ['password' => $temp, 'must_change_password' => true];
        }
        $user->update($attrs);

        Audit::log('team.members', "Menetapkan {$user->name} sebagai ".config("halodek.roles.{$data['role']}")." regu {$team->name}.", $team);

        $response = back()->with('success', "{$user->name} kini ".config("halodek.roles.{$data['role']}")." regu {$team->name}.");

        return $temp ? $response->with('tempPassword', ['name' => $user->name, 'nik' => $user->nik, 'password' => $temp]) : $response;
    }

    /** Ganti Kepala Regu: kepala lama otomatis menjadi Staf. */
    public function setHead(Team $team, User $user): RedirectResponse
    {
        if ($user->team_id !== $team->id || ! in_array($user->role, User::TEAM_ROLES, true)) {
            return back()->with('error', 'Pengguna bukan anggota regu ini.');
        }

        DB::transaction(function () use ($team, $user) {
            User::where('team_id', $team->id)->where('role', User::ROLE_KEPALA_REGU)->where('id', '!=', $user->id)
                ->update(['role' => User::ROLE_STAF]);
            $user->update(['role' => User::ROLE_KEPALA_REGU]);
        });

        Audit::log('team.members', "Menetapkan {$user->name} sebagai Kepala Regu {$team->name}.", $team);

        return back()->with('success', "{$user->name} kini Kepala Regu {$team->name}.");
    }

    /** Keluarkan anggota dari regu: ia kembali menjadi Karyawan (masuk cukup dengan NIK). */
    public function removeMember(Team $team, User $user): RedirectResponse
    {
        if ($user->team_id !== $team->id) {
            return back()->with('error', 'Pengguna bukan anggota regu ini.');
        }

        if ($user->assignedReports()->whereIn('status', Report::OPEN_STATUSES)->exists()) {
            return back()->with('error', "{$user->name} masih memegang laporan aktif. Tugaskan ulang atau lepas laporannya dulu.");
        }

        $user->update(['role' => User::ROLE_KARYAWAN, 'team_id' => null, 'password' => User::unusablePassword(), 'must_change_password' => false]);
        Audit::log('team.members', "Mengeluarkan {$user->name} dari regu {$team->name}.", $team);

        return back()->with('success', "{$user->name} dikeluarkan dari regu dan kembali menjadi Karyawan.");
    }

    private function currentHead(Team $team): ?User
    {
        return User::where('team_id', $team->id)->where('role', User::ROLE_KEPALA_REGU)->first();
    }

    /** Rata-rata lama penyelesaian (jam) 90 hari terakhir. */
    private function avgHours(Team $team): ?float
    {
        $rows = $team->reports()->whereNotNull('resolved_at')->where('resolved_at', '>=', now()->subDays(90))->get(['created_at', 'resolved_at']);

        return $rows->isEmpty() ? null : round($rows->avg(fn ($r) => $r->created_at->diffInMinutes($r->resolved_at) / 60), 1);
    }

    private function validated(Request $request, ?Team $team = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('teams', 'name')->ignore($team?->id)],
            'code' => ['required', 'string', 'min:2', 'max:6', 'alpha_num', Rule::unique('teams', 'code')->ignore($team?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['required', Rule::in(config('halodek.team_icons'))],
            'color' => ['required', Rule::in(array_keys(config('halodek.team_colors')))],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama regu wajib diisi.',
            'name.unique' => 'Nama regu sudah ada.',
            'code.required' => 'Kode regu wajib diisi.',
            'code.unique' => 'Kode regu sudah dipakai regu lain.',
            'code.alpha_num' => 'Kode regu hanya boleh huruf dan angka.',
        ]);

        $data['code'] = Str::upper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'regu';
        $slug = $base;
        $i = 2;
        while (Team::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
