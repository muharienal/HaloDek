@extends('layouts.app')
@section('title', auth()->user()->isSuperadmin() ? 'Regu & Kategori' : 'Regu Saya')

@section('content')
@php
    $isSuper = auth()->user()->isSuperadmin();
    // Dikelompokkan per Bidang (Struktur Organisasi). Regu tanpa bidang di kelompok terakhir.
    $groups = $teams->groupBy(fn ($t) => $t->department_id ?? 0)->sortBy(fn ($g, $k) => $k === 0 ? PHP_INT_MAX : ($g->first()->department->sort_order ?? 0));
    $grouped = $teams->contains(fn ($t) => $t->department_id);
@endphp

<div class="list-toolbar">
    <p class="muted text-sm" style="margin:0">{{ $isSuper ? 'Pilih regu untuk mengatur anggota, kategori layanan, dan template catatan.' : 'Regu yang Anda tangani.' }}</p>
    @if ($isSuper)<button class="btn btn-primary" data-modal-open="team-modal"><x-icon name="plus" :size="16" /> Tambah Regu</button>@endif
</div>

@forelse ($groups as $key => $group)
    @if ($grouped)
        <h2 class="group-title">
            @if ($key === 0)
                Belum masuk bidang
                @if ($isSuper)<span class="text-sm muted" style="font-weight:400"> · atur lewat Kelola regu › Ubah Regu › Bidang</span>@endif
            @else
                Bidang {{ $group->first()->department->name }}
                @if ($group->first()->department->division)<span class="text-sm muted" style="font-weight:400"> · Divisi {{ $group->first()->department->division->name }}</span>@endif
            @endif
        </h2>
    @endif
    <div class="team-grid">
        @foreach ($group as $team)
            <a href="{{ route('admin.teams.show', $team) }}" class="team-card {{ $team->is_active ? '' : 'is-off' }}">
                <div class="team-card-head">
                    <span class="team-ico" style="background: {{ $team->hex() }}"><x-icon :name="$team->icon" :size="22" /></span>
                    <div style="min-width:0">
                        <div class="team-name">Regu {{ $team->name }}</div>
                        <div class="text-xs muted">Kode laporan {{ $team->code }}@unless ($team->is_active) · <span class="txt-red bold">Nonaktif</span>@endunless</div>
                    </div>
                </div>
                @if ($team->description)<p class="team-desc">{{ $team->description }}</p>@endif

                <dl class="team-stats">
                    <div><dt>Laporan aktif</dt><dd class="{{ $team->open_reports_count ? 'txt-orange' : '' }}">{{ $team->open_reports_count }}</dd></div>
                    <div><dt>Total laporan</dt><dd>{{ $team->reports_count }}</dd></div>
                    <div><dt>Anggota</dt><dd>{{ $team->users_count }}</dd></div>
                    <div><dt>Kategori</dt><dd>{{ $team->categories_count }}</dd></div>
                </dl>

                <div class="team-foot">
                    @if ($team->head)
                        <span class="text-sm"><span class="muted">Kepala Regu:</span> {{ $team->head->name }}</span>
                    @else
                        <span class="note-flag is-orange"><x-icon name="alert" :size="14" /> Belum ada Kepala Regu</span>
                    @endif
                    <span class="team-go">{{ $isSuper ? 'Kelola' : 'Buka' }} <x-icon name="chevron-right" :size="15" /></span>
                </div>
            </a>
        @endforeach
    </div>
@empty
    <div class="card"><x-empty icon="users" title="Belum ada regu">{{ $isSuper ? 'Tambahkan regu penerima laporan, mis. IT atau Keamanan.' : 'Anda belum tergabung di regu mana pun. Hubungi Superadmin.' }}</x-empty></div>
@endforelse
@endsection

@if (auth()->user()->isSuperadmin())
    @push('modals')
        @include('admin.teams._form-modal', ['id' => 'team-modal', 'action' => route('admin.teams.store')])
    @endpush
@endif
