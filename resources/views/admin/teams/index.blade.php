@extends('layouts.app')
@section('title', auth()->user()->isSuperadmin() ? 'Regu & Kategori' : 'Regu Saya')
@section('subtitle', 'Regu penerima pengaduan, anggota, dan kategori layanannya')

@section('content')
@php $isSuper = auth()->user()->isSuperadmin(); @endphp

@if ($isSuper)
    <div class="alert alert-info">
        <x-icon name="question" :size="18" />
        <div><strong>Butuh bidang lain?</strong> Tambahkan regu baru. Regu langsung muncul sebagai tujuan pengaduan bagi karyawan, dengan Kepala Regu, staf, kategori layanan, dan inventarisnya sendiri.</div>
    </div>
    <div class="flex justify-end mb-4"><button class="btn btn-primary" data-modal-open="team-modal"><x-icon name="plus" :size="16" /> Tambah Regu</button></div>
@endif

<div class="grid cols-3">
    @forelse ($teams as $team)
        <a href="{{ route('admin.teams.show', $team) }}" class="card" style="padding:20px;color:inherit;text-decoration:none;{{ $team->is_active ? '' : 'opacity:.6' }}">
            <div class="flex items-start gap-3">
                <span class="avatar" style="width:46px;height:46px;border-radius:12px;background: {{ $team->hex() }}"><x-icon :name="$team->icon" :size="22" /></span>
                <div class="grow">
                    <div class="flex items-center gap-2 wrap"><span class="strong text-lg">{{ $team->name }}</span>
                        <span class="badge tone-gray plain mono">{{ $team->code }}</span>
                        @unless ($team->is_active)<span class="badge tone-red plain">Nonaktif</span>@endunless</div>
                    <p class="text-sm muted mt-1">{{ $team->description ?: 'Tanpa deskripsi' }}</p>
                </div>
            </div>
            <div class="grid cols-4 mt-4" style="gap:8px;text-align:center">
                <div><div class="bold text-lg">{{ $team->open_reports_count }}</div><div class="text-xs muted">Aktif</div></div>
                <div><div class="bold text-lg">{{ $team->reports_count }}</div><div class="text-xs muted">Laporan</div></div>
                <div><div class="bold text-lg">{{ $team->users_count }}</div><div class="text-xs muted">Anggota</div></div>
                <div><div class="bold text-lg">{{ $team->categories_count }}</div><div class="text-xs muted">Kategori</div></div>
            </div>
            <div class="text-xs muted mt-3">Kepala: {{ $team->head?->name ?? 'belum ditetapkan' }} · {{ $team->inventory_items_count }} item inventaris</div>
        </a>
    @empty
        <div class="card col-span-2" style="grid-column:1/-1"><x-empty icon="users" title="Belum ada regu">Anda belum tergabung di regu mana pun.</x-empty></div>
    @endforelse
</div>
@endsection

@if (auth()->user()->isSuperadmin())
    @push('modals')
        @include('admin.teams._form-modal', ['id' => 'team-modal', 'action' => route('admin.teams.store')])
    @endpush
@endif
