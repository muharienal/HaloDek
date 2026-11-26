<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Struktur Organisasi (Superadmin): Divisi -> Bidang -> Regu, Jabatan, dan nama Peran.
 * Semua isian pengguna memakai daftar ini sebagai pilihan.
 */
class OrganizationController extends Controller
{
    /** jenis di URL => [model, label] */
    private const TYPES = [
        'divisi' => [Division::class, 'Divisi'],
        'bidang' => [Department::class, 'Bidang'],
        'jabatan' => [Position::class, 'Jabatan'],
    ];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['divisi', 'bidang', 'jabatan', 'peran'], true) ? $request->query('tab') : 'divisi';

        return view('admin.organization.index', [
            'tab' => $tab,
            'divisions' => Division::ordered()->withCount(['departments', 'users'])->get(),
            'departments' => Department::ordered()->with(['division', 'teams' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])->withCount('users')->get(),
            'positions' => Position::ordered()->withCount('users')->get(),
            'roleCounts' => User::selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role'),
            'teamsWithout' => Team::whereNull('department_id')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        [$model, $label] = $this->type($type);
        $row = $model::create($this->validated($request, $type));
        Audit::log('org.create', "Menambah {$label} {$row->name}.");

        return redirect()->route('admin.org.index', ['tab' => $type])->with('success', "{$label} \"{$row->name}\" ditambahkan.");
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        [$model, $label] = $this->type($type);
        $row = $model::findOrFail($id);
        $data = $this->validated($request, $type, $row);

        DB::transaction(function () use ($row, $data, $type) {
            $row->update($data);
            // Nama berubah -> salinan nama di data pengguna ikut diperbarui.
            if ($type === 'bidang') {
                User::where('department_id', $row->id)->update(['department' => $row->name] + ($row->division_id ? ['division_id' => $row->division_id] : []));
            } elseif ($type === 'jabatan') {
                User::where('position_id', $row->id)->update(['position' => $row->name]);
            }
        });
        Audit::log('org.update', "Mengubah {$label} {$row->name}.");

        return redirect()->route('admin.org.index', ['tab' => $type])->with('success', "{$label} \"{$row->name}\" diperbarui.");
    }

    public function destroy(string $type, int $id): RedirectResponse
    {
        [$model, $label] = $this->type($type);
        $row = $model::findOrFail($id);

        $used = match ($type) {
            'divisi' => array_filter(['bidang' => $row->departments()->count(), 'pengguna' => $row->users()->count()]),
            'bidang' => array_filter(['regu' => $row->teams()->count(), 'pengguna' => $row->users()->count()]),
            default => array_filter(['pengguna' => $row->users()->count()]),
        };
        if ($used) {
            $what = collect($used)->map(fn ($n, $k) => "{$n} {$k}")->implode(' dan ');

            return back()->with('error', "{$label} \"{$row->name}\" masih dipakai {$what}. Pindahkan dulu ke {$label} lain, lalu hapus.");
        }

        $row->delete();
        Audit::log('org.delete', "Menghapus {$label} {$row->name}.");

        return redirect()->route('admin.org.index', ['tab' => $type])->with('success', "{$label} \"{$row->name}\" dihapus.");
    }

    /** Nama & penjelasan peran. Hak akses tiap peran tetap (diatur aplikasi). */
    public function updateRole(Request $request, string $role): RedirectResponse
    {
        abort_unless(array_key_exists($role, config('halodek.roles')), 404);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
        ], ['label.required' => 'Nama peran wajib diisi.']);

        $taken = collect(config('halodek.roles'))->except($role)->map(fn ($l) => mb_strtolower($l))->contains(mb_strtolower($data['label']));
        if ($taken) {
            return back()->withInput()->with('error', "Nama \"{$data['label']}\" sudah dipakai peran lain.");
        }

        DB::table('role_settings')->updateOrInsert(['role' => $role], $data + ['updated_at' => now(), 'created_at' => now()]);
        Roles::forget();
        Roles::boot();
        Audit::log('org.role', "Mengubah nama peran {$role} menjadi {$data['label']}.");

        return redirect()->route('admin.org.index', ['tab' => 'peran'])->with('success', "Peran \"{$data['label']}\" diperbarui.");
    }

    /** @return array{0: class-string<Model>, 1: string} */
    private function type(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function validated(Request $request, string $type, ?Model $row = null): array
    {
        $table = (new (self::TYPES[$type][0]))->getTable();
        $label = self::TYPES[$type][1];

        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique($table, 'name')->ignore($row?->id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
        if ($type === 'bidang') {
            $rules['division_id'] = ['required', 'integer', 'exists:divisions,id'];
        }

        $data = $request->validate($rules, [
            'name.required' => "Nama {$label} wajib diisi.",
            'name.unique' => "{$label} dengan nama ini sudah ada.",
            'division_id.required' => 'Pilih divisi tempat bidang ini berada.',
        ]);
        $data['name'] = trim($data['name']);
        // Nama tidak boleh kembar walau beda huruf besar/kecil ("Umum & IT" vs "umum & it").
        $dup = DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->when($row, fn ($q) => $q->where('id', '!=', $row->id))->exists();
        if ($dup) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => "{$label} dengan nama ini sudah ada."]);
        }
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
