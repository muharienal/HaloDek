@extends('layouts.app')
@section('title', $team->name)
@section('subtitle', $team->description ?: 'Pengaturan regu')

@section('content')
@php $isSuper = auth()->user()->isSuperadmin(); $canLead = auth()->user()->leadsTeam($team->id); @endphp
<div class="flex items-center justify-between wrap gap-3 mb-4">
    <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Semua regu</a>
    @if ($isSuper)
        <div class="flex gap-2">
            <button class="btn btn-secondary btn-sm" data-modal-open="team-modal" data-title="Ubah Regu" data-action="{{ route('admin.teams.update', $team) }}"
                    data-fill="{{ json_encode(['name' => $team->name, 'code' => $team->code, 'description' => $team->description, 'icon' => $team->icon, 'color' => $team->color, 'is_active' => $team->is_active]) }}"><x-icon name="edit" :size="16" /> Ubah Regu</button>
            @unless ($team->is_system)
                <form method="POST" action="{{ route('admin.teams.destroy', $team) }}" data-confirm="Hapus regu {{ $team->name }}? Kategorinya ikut terhapus. Regu yang sudah memiliki laporan tidak dapat dihapus." data-confirm-title="Hapus regu">
                    @csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm"><x-icon name="trash" :size="16" /> Hapus</button></form>
            @endunless
        </div>
    @endif
</div>

<div class="grid cols-4">
    <div class="card stat"><span class="lbl">Laporan aktif</span><span class="num">{{ $stats['open'] }}</span></div>
    <div class="card stat"><span class="lbl">Total laporan</span><span class="num">{{ $stats['total'] }}</span></div>
    <div class="card stat"><span class="lbl">Antrean (belum ditugaskan)</span><span class="num">{{ $stats['unassigned'] }}</span></div>
    <div class="card stat"><span class="lbl">Rata-rata penyelesaian</span><span class="num">{{ $stats['avg_hours'] !== null ? $stats['avg_hours'] : '–' }}<span class="text-sm muted"> jam</span></span></div>
</div>

<div class="grid cols-main mt-5" style="align-items:start">
    {{-- ===== KATEGORI ===== --}}
    <div class="card">
        <div class="card-head"><h2>Kategori Layanan</h2>
            @if ($canLead)<button class="btn btn-primary btn-sm" data-modal-open="category-modal"><x-icon name="plus" :size="16" /> Tambah Kategori</button>@endif</div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Kategori</th><th>Laporan</th><th></th></tr></thead>
                <tbody>
                @forelse ($team->categories as $c)
                    <tr>
                        <td class="strong">{{ $c->name }} @if ($c->allow_custom)<span class="badge tone-violet plain" title="Pelapor wajib menyebut kategori sendiri">Isi bebas</span>@endif @unless ($c->is_active)<span class="badge tone-gray plain">Nonaktif</span>@endunless</td>
                        <td>{{ $c->reports_count }}</td>
                        <td>@if ($canLead)<div class="actions">
                            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah" data-modal-open="category-modal" data-title="Ubah Kategori" data-action="{{ route('admin.categories.update', $c) }}"
                                    data-fill="{{ json_encode(['name' => $c->name, 'allow_custom' => $c->allow_custom, 'is_active' => $c->is_active]) }}"><x-icon name="edit" :size="15" /></button>
                            <form method="POST" action="{{ route('admin.categories.destroy', $c) }}" data-confirm="Hapus kategori &quot;{{ $c->name }}&quot;?" data-confirm-title="Hapus kategori">
                                @csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm btn-icon" aria-label="Hapus"><x-icon name="trash" :size="15" /></button></form>
                        </div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-empty icon="tag" title="Belum ada kategori">Tambahkan kategori agar regu ini dapat menerima laporan.</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ===== ANGGOTA ===== --}}
    <div class="card">
        <div class="card-head"><h2>Kepala Regu &amp; Staf</h2>
            @if ($isSuper)<button class="btn btn-secondary btn-sm" data-modal-open="member-modal"><x-icon name="plus" :size="16" /> Tambah</button>@endif</div>
        <div class="card-body" style="display:grid;gap:12px">
            @forelse ($members as $u)
                <div class="flex items-center gap-3">
                    <span class="avatar sm">{{ $u->initials() }}</span>
                    <div class="grow"><div class="strong text-sm">{{ $u->name }} @unless ($u->is_active)<span class="badge tone-red plain">Nonaktif</span>@endunless</div>
                        <div class="text-xs muted">{{ $u->roleLabel() }} · {{ $u->open_tasks_count }} tugas aktif</div></div>
                    @if ($isSuper)
                        @unless ($u->isHead())
                            <form method="POST" action="{{ route('admin.teams.members.head', [$team, $u]) }}" data-confirm="Jadikan {{ $u->name }} Kepala Regu? Kepala saat ini menjadi Staf." data-confirm-title="Ganti Kepala Regu" data-confirm-ok="Jadikan Kepala" data-confirm-tone="primary">
                                @csrf @method('PATCH')<button class="btn btn-secondary btn-sm">Jadikan Kepala</button></form>
                        @endunless
                        <form method="POST" action="{{ route('admin.teams.members.remove', [$team, $u]) }}" data-confirm="Keluarkan {{ $u->name }} dari regu {{ $team->name }}? Role kembali menjadi Karyawan." data-confirm-title="Keluarkan anggota" data-confirm-ok="Keluarkan">
                            @csrf @method('DELETE')<button class="btn btn-ghost btn-icon btn-sm" aria-label="Keluarkan"><x-icon name="x" :size="15" /></button></form>
                    @endif
                </div>
            @empty
                <p class="muted text-sm">Belum ada anggota. Tambahkan Kepala Regu dan staf agar laporan dapat ditangani.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('modals')
    <x-form-modal id="category-modal" create-title="Tambah Kategori" edit-title="Ubah Kategori" :action="route('admin.categories.store', $team)">
        <div style="display:grid;gap:16px">
            <x-input name="name" label="Nama kategori" required maxlength="100" />
            <label class="check"><input type="hidden" name="allow_custom" value="0"><input type="checkbox" name="allow_custom" value="1" @checked(old('allow_custom'))> Pelapor wajib menyebut kategori sendiri (untuk “Lainnya”)</label>
            <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Aktif (tampil di form pelaporan)</label>
        </div>
    </x-form-modal>

    @if ($isSuper)
        @include('admin.teams._form-modal', ['id' => 'team-modal', 'action' => route('admin.teams.store')])

        <div class="modal" id="member-modal" role="dialog" aria-modal="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('admin.teams.members.add', $team) }}">
                    @csrf
                    <div class="modal-head"><h3>Tambah Anggota ke {{ $team->name }}</h3><button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button></div>
                    <div class="modal-body" style="display:grid;gap:16px">
                        <x-select name="user_id" label="Pengguna" required>
                            <option value="">— Pilih pengguna —</option>
                            @foreach ($candidates as $c)<option value="{{ $c->id }}">{{ $c->name }} — NIK {{ $c->nik }} ({{ config('halodek.roles.'.$c->role) }})</option>@endforeach
                        </x-select>
                        <x-select name="role" label="Sebagai" required>
                            <option value="staf">Staf</option>
                            <option value="kepala_regu">Kepala Regu</option>
                        </x-select>
                    </div>
                    <div class="modal-foot"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary">Tambahkan</button></div>
                </form>
            </div>
        </div>
    @endif
@endpush
