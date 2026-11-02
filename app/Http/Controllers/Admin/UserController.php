<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCsvImport;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manajemen pengguna (khusus Superadmin).
 * Password awal = NIK dan wajib diganti saat login pertama.
 */
class UserController extends Controller
{
    use HandlesCsvImport;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $role = $request->query('role', 'semua');
        $state = $request->query('state', 'semua');
        $teamId = $request->integer('team_id') ?: null;

        $users = User::with('team')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('department', 'like', "%{$search}%")))
            ->when($role !== 'semua', fn ($q) => $q->where('role', $role))
            ->when($state === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($state === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roleCounts = User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role');
        $teams = Team::ordered()->get();

        return view('admin.users.index', compact('users', 'search', 'role', 'state', 'teamId', 'roleCounts', 'teams'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $needsPassword = $data['role'] !== User::ROLE_KARYAWAN;
        $temp = $needsPassword ? User::temporaryPassword() : null;

        $user = User::create($data + [
            'password' => $temp ?? User::unusablePassword(), // di-hash otomatis lewat cast
            'must_change_password' => $needsPassword,
            'is_active' => true,
        ]);

        Audit::log('user.create', "Menambah pengguna {$user->name} ({$user->nik}) sebagai {$user->roleLabel()}.", $user);

        $response = back()->with('success', "Pengguna {$user->name} dibuat.".($needsPassword ? '' : ' Karyawan masuk cukup dengan NIK.'));

        return $needsPassword ? $response->with('tempPassword', ['name' => $user->name, 'nik' => $user->nik, 'password' => $temp]) : $response;
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $data['is_active'] = $request->boolean('is_active');

        if ($user->id === $request->user()->id && ($data['role'] !== $user->role || ! $data['is_active'])) {
            return back()->with('error', 'Anda tidak dapat mengubah role atau menonaktifkan akun Anda sendiri.');
        }

        if ($this->wouldRemoveLastSuperadmin($user, $data['role'], $data['is_active'])) {
            return back()->with('error', 'Harus ada minimal satu Superadmin aktif.');
        }

        // Pengelola yang masih memegang laporan aktif tidak boleh dipindah regu / diturunkan dulu.
        $leavingTeam = $user->team_id && ($data['team_id'] ?? null) !== $user->team_id;
        if (($leavingTeam || $data['role'] === User::ROLE_KARYAWAN) && $user->assignedReports()->whereIn('status', Report::OPEN_STATUSES)->exists()) {
            return back()->with('error', "{$user->name} masih memegang laporan aktif. Tugaskan ulang atau lepas laporannya dulu sebelum mengubah regu/role.");
        }

        // Naik dari Karyawan ke pengelola: wajib punya password sementara acak.
        // Turun jadi Karyawan: password lama dibuang agar tidak bisa dipakai lagi.
        $temp = null;
        if ($user->isKaryawan() && $data['role'] !== User::ROLE_KARYAWAN) {
            $temp = User::temporaryPassword();
            $data['password'] = $temp;
            $data['must_change_password'] = true;
        } elseif (! $user->isKaryawan() && $data['role'] === User::ROLE_KARYAWAN) {
            $data['password'] = User::unusablePassword();
            $data['must_change_password'] = false;
        }

        $user->update($data);
        $user->forgetHandledTeams();

        Audit::log('user.update', "Mengubah pengguna {$user->name} ({$user->nik}).", $user);

        $response = back()->with('success', 'Data pengguna berhasil diperbarui.');

        return $temp ? $response->with('tempPassword', ['name' => $user->name, 'nik' => $user->nik, 'password' => $temp]) : $response;
    }

    /** Atur ulang password pengelola menjadi password sementara acak (ditampilkan sekali). */
    public function resetPassword(User $user): RedirectResponse
    {
        if ($user->isKaryawan()) {
            return back()->with('error', 'Karyawan masuk cukup dengan NIK, tidak memakai password.');
        }

        $temp = User::temporaryPassword();
        $user->update(['password' => $temp, 'must_change_password' => true]);
        Audit::log('user.reset_password', "Mereset password {$user->name} ({$user->nik}).", $user);

        return back()->with('success', "Password {$user->name} diatur ulang.")
            ->with('tempPassword', ['name' => $user->name, 'nik' => $user->nik, 'password' => $temp]);
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if ($user->is_active && $this->wouldRemoveLastSuperadmin($user, $user->role, false)) {
            return back()->with('error', 'Harus ada minimal satu Superadmin aktif.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        Audit::log('user.toggle', ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." pengguna {$user->name}.", $user);

        return back()->with('success', $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if ($this->wouldRemoveLastSuperadmin($user, 'x', false)) {
            return back()->with('error', 'Harus ada minimal satu Superadmin aktif.');
        }

        Audit::log('user.delete', "Menghapus pengguna {$user->name} ({$user->nik}).", $user);
        $user->delete(); // laporan tetap ada (nama pelapor tersimpan sebagai snapshot)

        return back()->with('success', 'Pengguna dihapus. Riwayat laporannya tetap tersimpan.');
    }

    public function template(): StreamedResponse
    {
        return Csv::download('template-import-pengguna.csv', ['nik', 'name', 'role', 'email', 'phone', 'department', 'position', 'regu'], [
            ['00010', 'Andi Wijaya', 'karyawan', 'andi@contoh.co.id', '08123456789', 'Finance', 'Staf', ''],
            ['00011', 'Rina Marlina', 'kepala_regu', '', '', 'Keamanan', 'Kepala Regu Keamanan', 'keamanan'],
            ['00012', 'Joko Susilo', 'staf', '', '', 'Keamanan', 'Petugas Keamanan', 'keamanan'],
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = (function () {
            foreach (User::with('team')->orderBy('name')->cursor() as $u) {
                yield [$u->nik, $u->name, $u->role, $u->email, $u->phone, $u->department, $u->position, $u->team->slug ?? '', $u->is_active ? 'aktif' : 'nonaktif'];
            }
        })();

        return Csv::download('pengguna-halodek-'.now()->format('Ymd').'.csv', ['nik', 'name', 'role', 'email', 'phone', 'department', 'position', 'regu', 'status'], $rows);
    }

    public function import(Request $request)
    {
        $teams = Team::all()->keyBy(fn ($t) => mb_strtolower($t->slug));
        $roles = array_keys(config('halodek.roles'));

        $admins = 0;
        $result = $this->runImport($request, ['nik', 'name'], function (array $d) use ($teams, $roles, &$admins) {
            $d['role'] = ($d['role'] ?? '') ?: 'karyawan';
            $d['email'] = ($d['email'] ?? '') ?: null;

            $v = Validator::make($d, [
                'nik' => ['required', 'string', 'max:50', 'unique:users,nik'],
                'name' => ['required', 'string', 'max:255'],
                'role' => ['required', Rule::in($roles)],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:30'],
                'department' => ['nullable', 'string', 'max:100'],
                'position' => ['nullable', 'string', 'max:100'],
            ])->validate();

            $team = null;
            $slug = trim((string) ($d['regu'] ?? ''));
            if ($slug !== '') {
                $team = $teams[mb_strtolower($slug)] ?? null;
                if (! $team) {
                    throw ValidationException::withMessages(['regu' => "Regu \"{$slug}\" tidak ditemukan."]);
                }
            }

            if (in_array($v['role'], User::TEAM_ROLES, true) && ! $team) {
                throw ValidationException::withMessages(['regu' => 'Kepala Regu dan Staf wajib memiliki regu.']);
            }

            if ($v['role'] === User::ROLE_KEPALA_REGU && User::where('team_id', $team->id)->where('role', User::ROLE_KEPALA_REGU)->exists()) {
                throw ValidationException::withMessages(['regu' => "Regu {$team->name} sudah memiliki Kepala Regu."]);
            }

            User::create(array_map(fn ($x) => $x === '' ? null : $x, $v) + [
                'team_id' => in_array($v['role'], User::TEAM_ROLES, true) ? $team->id : null,
                // Password acak tak diketahui; pengelola mendapat password sementara lewat "Atur ulang password".
                'password' => User::unusablePassword(),
                'must_change_password' => $v['role'] !== User::ROLE_KARYAWAN,
                'is_active' => true,
            ]);

            if ($v['role'] !== User::ROLE_KARYAWAN) {
                $admins++;
            }
        });

        Audit::log('user.import', "Impor pengguna: {$result['ok']} berhasil.");

        $response = $this->importResponse($result, 'admin.users.index', 'pengguna');

        return $admins > 0
            ? $response->with('warning', "{$admins} akun pengelola dibuat tanpa password yang diketahui. Buka tiap akun lalu pilih \"Atur ulang password\" untuk mendapatkan password sementara.")
            : $response;
    }

    // ------------------------------------------------------------------ util

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:50', Rule::unique('users', 'nik')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(config('halodek.roles')))],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ], [
            'nik.required' => 'NIK wajib diisi.',
            'nik.unique' => 'NIK ini sudah terdaftar.',
            'name.required' => 'Nama wajib diisi.',
            'role.required' => 'Role wajib dipilih.',
        ]);

        // Kepala Regu & Staf wajib punya regu; Superadmin & Karyawan tidak terikat regu.
        if (in_array($data['role'], User::TEAM_ROLES, true)) {
            if (empty($data['team_id'])) {
                throw ValidationException::withMessages(['team_id' => 'Kepala Regu dan Staf wajib memilih regu.']);
            }
        } else {
            $data['team_id'] = null;
        }

        // Satu regu hanya punya satu Kepala Regu.
        if ($data['role'] === User::ROLE_KEPALA_REGU) {
            $other = User::where('team_id', $data['team_id'])->where('role', User::ROLE_KEPALA_REGU)->where('id', '!=', $user?->id)->first();
            if ($other) {
                throw ValidationException::withMessages(['team_id' => "Regu ini sudah dipimpin {$other->name}. Jadikan dia Staf dulu, atau gunakan \"Jadikan Kepala Regu\" di halaman regu."]);
            }
        }

        return $data;
    }

    private function wouldRemoveLastSuperadmin(User $user, string $newRole, bool $newActive): bool
    {
        if (! $user->isSuperadmin() || ($newRole === User::ROLE_SUPERADMIN && $newActive)) {
            return false;
        }

        return User::active()->where('role', User::ROLE_SUPERADMIN)->where('id', '!=', $user->id)->doesntExist();
    }
}
