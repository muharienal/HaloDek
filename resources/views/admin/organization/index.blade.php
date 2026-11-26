@extends('layouts.app')
@section('title', 'Struktur Organisasi')
@section('subtitle', 'Divisi › Bidang › Regu, jabatan, dan peran. Daftar ini menjadi pilihan di data pengguna.')

@php
    $tabs = ['divisi' => ['Divisi', $divisions->count()], 'bidang' => ['Bidang', $departments->count()], 'jabatan' => ['Jabatan', $positions->count()], 'peran' => ['Peran', count(config('halodek.roles'))]];
    $addLabel = ['divisi' => 'Tambah Divisi', 'bidang' => 'Tambah Bidang', 'jabatan' => 'Tambah Jabatan'][$tab] ?? null;
@endphp

@section('content')
<div class="card">
    <div class="card-head" style="flex-wrap:wrap">
        <div class="tabs">
            @foreach ($tabs as $k => [$label, $n])
                <a class="tab {{ $tab === $k ? 'active' : '' }}" href="{{ route('admin.org.index', ['tab' => $k]) }}">{{ $label }} <span class="n">{{ $n }}</span></a>
            @endforeach
        </div>
        @if ($addLabel)
            @if ($tab === 'bidang' && $divisions->isEmpty())
                <span class="text-sm muted">Tambah divisi dulu sebelum bidang.</span>
            @else
                <button class="btn btn-primary btn-sm" data-modal-open="org-{{ $tab }}"><x-icon name="plus" :size="16" /> {{ $addLabel }}</button>
            @endif
        @endif
    </div>

    {{-- ================= DIVISI ================= --}}
    @if ($tab === 'divisi')
        @if ($divisions->isEmpty())
            <x-empty icon="building" title="Belum ada divisi">Contoh: "SDM, Umum &amp; K3". Setelah itu tambahkan bidang di bawahnya.</x-empty>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Divisi</th><th>Bidang di dalamnya</th><th class="num">Pengguna</th><th class="num">Urutan</th><th></th></tr></thead>
                <tbody>@foreach ($divisions as $d)
                    <tr>
                        <td class="strong">{{ $d->name }}</td>
                        <td class="text-sm">{{ $departments->where('division_id', $d->id)->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td class="num">{{ $d->users_count }}</td>
                        <td class="num muted">{{ $d->sort_order }}</td>
                        <td><div class="actions">
                            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah {{ $d->name }}" title="Ubah" data-modal-open="org-divisi" data-method="PUT" data-action="{{ route('admin.org.update', ['divisi', $d->id]) }}" data-fill="{{ json_encode(['name' => $d->name, 'sort_order' => $d->sort_order]) }}"><x-icon name="edit" :size="15" /></button>
                            @include('admin.organization._delete', ['type' => 'divisi', 'row' => $d, 'label' => 'divisi'])
                        </div></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    @endif

    {{-- ================= BIDANG ================= --}}
    @if ($tab === 'bidang')
        @if ($teamsWithout->isNotEmpty() && $departments->isNotEmpty())
            <div class="card-body" style="padding-bottom:0">
                <div class="alert alert-info" style="margin:0"><x-icon name="question" :size="18" /><div>Regu yang belum masuk bidang: <strong>{{ $teamsWithout->pluck('name')->implode(', ') }}</strong>. Atur lewat <a href="{{ route('admin.teams.index') }}">Regu</a> › buka regu › <em>Ubah Regu</em> › Bidang.</div></div>
            </div>
        @endif
        @if ($departments->isEmpty())
            <x-empty icon="building" title="Belum ada bidang">Contoh: "Umum &amp; IT" di Divisi "SDM, Umum &amp; K3". Regu (IT, Keamanan, …) lalu dimasukkan ke bidang.</x-empty>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Bidang</th><th>Divisi</th><th>Regu</th><th class="num">Pengguna</th><th class="num">Urutan</th><th></th></tr></thead>
                <tbody>@foreach ($departments->sortBy(fn ($b) => sprintf('%05d %s %05d %s', $b->division?->sort_order ?? 99999, $b->division?->name, $b->sort_order, $b->name)) as $b)
                    <tr>
                        <td class="strong">{{ $b->name }}</td>
                        <td class="text-sm">{!! $b->division ? e($b->division->name) : '<span class="txt-orange">Belum dipilih</span>' !!}</td>
                        <td class="text-sm">@forelse ($b->teams as $t)<x-team-chip :team="$t" />@if (! $loop->last)<br>@endif @empty <span class="faint">-</span>@endforelse</td>
                        <td class="num">{{ $b->users_count }}</td>
                        <td class="num muted">{{ $b->sort_order }}</td>
                        <td><div class="actions">
                            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah {{ $b->name }}" title="Ubah" data-modal-open="org-bidang" data-method="PUT" data-action="{{ route('admin.org.update', ['bidang', $b->id]) }}" data-fill="{{ json_encode(['name' => $b->name, 'division_id' => $b->division_id, 'sort_order' => $b->sort_order]) }}"><x-icon name="edit" :size="15" /></button>
                            @include('admin.organization._delete', ['type' => 'bidang', 'row' => $b, 'label' => 'bidang'])
                        </div></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    @endif

    {{-- ================= JABATAN ================= --}}
    @if ($tab === 'jabatan')
        @if ($positions->isEmpty())
            <x-empty icon="user" title="Belum ada jabatan">Contoh: "Kepala Regu IT", "Staf Keuangan".</x-empty>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Jabatan</th><th class="num">Pengguna</th><th class="num">Urutan</th><th></th></tr></thead>
                <tbody>@foreach ($positions as $p)
                    <tr>
                        <td class="strong">{{ $p->name }}</td>
                        <td class="num">{{ $p->users_count }}</td>
                        <td class="num muted">{{ $p->sort_order }}</td>
                        <td><div class="actions">
                            <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah {{ $p->name }}" title="Ubah" data-modal-open="org-jabatan" data-method="PUT" data-action="{{ route('admin.org.update', ['jabatan', $p->id]) }}" data-fill="{{ json_encode(['name' => $p->name, 'sort_order' => $p->sort_order]) }}"><x-icon name="edit" :size="15" /></button>
                            @include('admin.organization._delete', ['type' => 'jabatan', 'row' => $p, 'label' => 'jabatan'])
                        </div></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    @endif

    {{-- ================= PERAN ================= --}}
    @if ($tab === 'peran')
        <div class="card-body" style="padding-bottom:0"><p class="hint" style="margin:0">Peran menentukan <strong>hak akses</strong> di aplikasi, jadi jumlah dan hak aksesnya tetap. Yang bisa diubah: nama yang tampil dan penjelasannya.</p></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Peran</th><th>Penjelasan</th><th class="num">Pengguna</th><th></th></tr></thead>
            <tbody>@foreach (config('halodek.roles') as $key => $label)
                <tr>
                    <td class="strong nowrap">{{ $label }}</td>
                    <td class="text-sm">{{ \App\Support\Roles::description($key) }}</td>
                    <td class="num">{{ $roleCounts[$key] ?? 0 }}</td>
                    <td><div class="actions"><button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah {{ $label }}" title="Ubah" data-modal-open="org-peran" data-method="PUT" data-action="{{ route('admin.org.role', $key) }}" data-fill="{{ json_encode(['label' => $label, 'description' => \App\Support\Roles::description($key)]) }}"><x-icon name="edit" :size="15" /></button></div></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</div>
@endsection

@push('modals')
    <x-form-modal id="org-divisi" create-title="Tambah Divisi" edit-title="Ubah Divisi" :action="route('admin.org.store', 'divisi')">
        <div style="display:grid;gap:16px">
            <x-input name="name" label="Nama divisi" required maxlength="100" placeholder="Mis. SDM, Umum & K3" />
            <x-input name="sort_order" label="Urutan" type="number" min="0" max="9999" hint="Angka kecil tampil lebih dulu di daftar pilihan." />
        </div>
    </x-form-modal>
    <x-form-modal id="org-bidang" create-title="Tambah Bidang" edit-title="Ubah Bidang" :action="route('admin.org.store', 'bidang')">
        <div style="display:grid;gap:16px">
            <x-select name="division_id" label="Divisi" required>
                <option value="">- Pilih divisi -</option>
                @foreach ($divisions as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </x-select>
            <x-input name="name" label="Nama bidang" required maxlength="100" placeholder="Mis. Umum & IT" />
            <x-input name="sort_order" label="Urutan" type="number" min="0" max="9999" hint="Angka kecil tampil lebih dulu di daftar pilihan." />
            <p class="hint" style="margin:0">Regu dimasukkan ke bidang dari halaman <a href="{{ route('admin.teams.index') }}">Regu</a> › Ubah Regu.</p>
        </div>
    </x-form-modal>
    <x-form-modal id="org-jabatan" create-title="Tambah Jabatan" edit-title="Ubah Jabatan" :action="route('admin.org.store', 'jabatan')">
        <div style="display:grid;gap:16px">
            <x-input name="name" label="Nama jabatan" required maxlength="100" placeholder="Mis. Kepala Regu IT" />
            <x-input name="sort_order" label="Urutan" type="number" min="0" max="9999" hint="Angka kecil tampil lebih dulu di daftar pilihan." />
        </div>
    </x-form-modal>
    <x-form-modal id="org-peran" create-title="Ubah Peran" edit-title="Ubah Peran" :action="route('admin.org.index')">
        <div style="display:grid;gap:16px">
            <x-input name="label" label="Nama peran" required maxlength="50" />
            <x-textarea name="description" label="Penjelasan" rows="3" maxlength="255" hint="Tampil di form pengguna sebagai penjelasan pilihan peran." />
        </div>
    </x-form-modal>
@endpush
