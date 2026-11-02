@extends('layouts.app')
@section('title', 'Manajemen Pengguna')
@section('subtitle', 'Akun karyawan dan admin, role, serta keanggotaan regu')

@section('content')
<div class="card">
    <div class="card-head" style="flex-wrap:wrap">
        <div class="tabs">
            <a class="tab {{ $role === 'semua' ? 'active' : '' }}" href="{{ route('admin.users.index', array_filter(['q' => $search, 'state' => $state])) }}">Semua <span class="n">{{ $roleCounts->sum() }}</span></a>
            @foreach (config('halodek.roles') as $k => $label)
                <a class="tab {{ $role === $k ? 'active' : '' }}" href="{{ route('admin.users.index', array_filter(['role' => $k, 'q' => $search, 'state' => $state])) }}">{{ $label }} <span class="n">{{ $roleCounts[$k] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="flex gap-2 wrap">
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.users.export') }}"><x-icon name="download" :size="16" /> Ekspor</a>
            <button class="btn btn-secondary btn-sm" data-modal-open="import-modal"><x-icon name="upload" :size="16" /> Impor CSV</button>
            <button class="btn btn-primary btn-sm" data-modal-open="user-modal"><x-icon name="plus" :size="16" /> Tambah Pengguna</button>
        </div>
    </div>

    <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
        <input type="hidden" name="role" value="{{ $role }}">
        <div class="field grow" style="min-width:220px"><label class="label" for="q">Cari</label><div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="q" name="q" value="{{ $search }}" placeholder="Nama, NIK, email, departemen…" data-no-auto></div></div>
        <div class="field"><label class="label" for="state">Status</label><select class="select" id="state" name="state"><option value="semua">Semua</option><option value="aktif" @selected($state === 'aktif')>Aktif</option><option value="nonaktif" @selected($state === 'nonaktif')>Nonaktif</option></select></div>
        <div class="field"><label class="label" for="tid">Regu</label><select class="select" id="tid" name="team_id"><option value="">Semua</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected($teamId == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
    </form>

    @if ($users->isEmpty())
        <x-empty icon="users" title="Tidak ada pengguna" />
    @else
        <div class="table-wrap mt-4"><table class="table">
            <thead><tr><th>Pengguna</th><th>NIK</th><th>Role</th><th>Regu</th><th>Login terakhir</th><th></th></tr></thead>
            <tbody>@foreach ($users as $u)
                <tr style="{{ $u->is_active ? '' : 'opacity:.6' }}">
                    <td><div class="flex items-center gap-3"><span class="avatar sm">{{ $u->initials() }}</span>
                        <div><div class="strong">{{ $u->name }} @unless ($u->is_active)<span class="badge tone-red plain">Nonaktif</span>@endunless</div><div class="text-xs muted">{{ collect([$u->position, $u->department])->filter()->implode(' · ') ?: ($u->email ?: '–') }}</div></div></div></td>
                    <td class="mono">{{ $u->nik }}</td>
                    <td><x-badge :tone="match($u->role) { 'superadmin' => 'violet', 'karyawan' => 'gray', default => 'blue' }" plain>{{ $u->roleLabel() }}</x-badge></td>
                    <td>@if ($u->team)<x-team-chip :team="$u->team" />@else<span class="faint">–</span>@endif</td>
                    <td class="text-sm muted">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Belum pernah' }}@if ($u->must_change_password && ! $u->isKaryawan())<div class="text-xs" style="color:#B45309">Password awal</div>@endif</td>
                    <td><div class="actions">
                        <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah" data-modal-open="user-modal" data-title="Ubah Pengguna" data-action="{{ route('admin.users.update', $u) }}"
                                data-fill="{{ json_encode(['nik' => $u->nik, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'department' => $u->department, 'position' => $u->position, 'role' => $u->role, 'team_id' => $u->team_id, 'is_active' => $u->is_active]) }}"><x-icon name="edit" :size="15" /></button>
                        <form method="POST" action="{{ route('admin.users.reset-password', $u) }}" data-confirm="Reset password {{ $u->name }} menjadi NIK-nya? Ia wajib menggantinya saat login berikutnya." data-confirm-title="Reset password" data-confirm-ok="Reset" data-confirm-tone="primary">@csrf
                            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Reset password" title="Reset password"><x-icon name="key" :size="15" /></button></form>
                        @if ($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.toggle', $u) }}" data-confirm="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $u->name }}?" data-confirm-title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun" data-confirm-ok="Ya" data-confirm-tone="primary">@csrf @method('PATCH')
                                <button class="btn btn-secondary btn-sm btn-icon" aria-label="Aktif/nonaktif" title="{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><x-icon name="power" :size="15" /></button></form>
                            @unless ($u->is_active)<form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-confirm="Hapus pengguna {{ $u->name }}? Riwayat laporannya tetap tersimpan. " data-confirm-title="Hapus pengguna">@csrf @method('DELETE')
                                <button class="btn btn-soft-danger btn-sm btn-icon" aria-label="Hapus"><x-icon name="trash" :size="15" /></button></form>@endunless
                        @endif
                    </div></td>
                </tr>
            @endforeach</tbody></table></div>
    @endif
    {{ $users->links() }}
</div>
@endsection

@push('modals')
    <x-form-modal id="user-modal" create-title="Tambah Pengguna" edit-title="Ubah Pengguna" :action="route('admin.users.store')" size="lg">
        <div style="display:grid;gap:16px">
            <div class="form-grid"><x-input name="nik" label="NIK" required maxlength="50" /><x-input name="name" label="Nama lengkap" required maxlength="255" /></div>
            <div class="form-grid"><x-input name="email" type="email" label="Email" maxlength="255" /><x-input name="phone" label="No. HP" maxlength="30" /></div>
            <div class="form-grid"><x-input name="department" label="Departemen" maxlength="100" /><x-input name="position" label="Jabatan" maxlength="100" /></div>
            <x-select name="role" label="Role" required id="user-role">
                @foreach (config('halodek.roles') as $k => $label)<option value="{{ $k }}" @selected(old('role', 'karyawan') === $k)>{{ $label }}</option>@endforeach
            </x-select>
            <div class="field" id="user-teams">
                <label class="label" for="user-team-id">Regu</label>
                <select class="select" id="user-team-id" name="team_id">
                    <option value="">— Pilih regu —</option>
                    @foreach ($teams as $t)<option value="{{ $t->id }}" @selected(old('team_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <p class="hint">Satu regu hanya memiliki satu Kepala Regu.</p>
                @error('team_id')<span class="error">{{ $message }}</span>@enderror
            </div>
            <label class="check" data-show-on-edit hidden><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Akun aktif</label>
            <p class="hint" data-hide-on-edit>Password awal = NIK dan wajib diganti saat login pertama.</p>
        </div>
    </x-form-modal>
    <x-import-modal :action="route('admin.users.import')" :template="route('admin.users.template')" title="Impor Pengguna dari CSV" columns="nik, name, role, email, phone, department, position, regu" />
@endpush

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var role = document.getElementById('user-role'), box = document.getElementById('user-teams');
    function sync() { box.hidden = (role.value === 'karyawan' || role.value === 'superadmin'); }
    role.addEventListener('change', sync);
    document.addEventListener('hu:prepared', sync);
    sync();
})();
</script>
@endpush
