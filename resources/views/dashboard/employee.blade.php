@extends('layouts.app')
@section('title', 'Beranda')
@section('subtitle', 'Halo, '.auth()->user()->name.'. Apa yang bisa kami bantu hari ini?')

@section('content')
    @include('partials.hero', ['metrics' => [
        ['value' => $stats['total'], 'label' => 'Total', 'href' => route('employee.reports.index')],
        ['value' => $stats['aktif'], 'label' => 'Berjalan', 'href' => route('employee.reports.index', ['status' => 'aktif'])],
        ['value' => $stats['selesai'], 'label' => 'Selesai', 'href' => route('employee.reports.index', ['status' => 'selesai'])],
        ['value' => $stats['lainnya'], 'label' => 'Ditolak / Batal', 'href' => route('employee.reports.index')],
    ]])

    <div class="card mt-5">
        <div class="card-head"><h2>Ajukan Laporan ke…</h2></div>
        <div class="card-body">
            @if ($teams->isEmpty())
                <x-empty icon="inbox" title="Belum ada regu aktif">Hubungi Superadmin untuk mengaktifkan regu penerima laporan.</x-empty>
            @else
                <div class="choice-grid">
                    @foreach ($teams as $team)
                        <a class="choice" href="{{ route('employee.reports.create', ['regu' => $team->slug]) }}" style="text-decoration:none">
                            <div class="choice-box">
                                <span class="ico" style="background: {{ $team->hex() }}"><x-icon :name="$team->icon" :size="22" /></span>
                                <span>
                                    <span class="strong" style="display:block">{{ $team->name }}</span>
                                    <span class="text-sm muted">{{ $team->description ?: $team->categories_count.' kategori layanan' }}</span>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="grid cols-main mt-5">
        <div class="card">
            <div class="card-head"><h2>Laporan Terbaru</h2><a href="{{ route('employee.reports.index') }}" class="text-sm strong">Lihat semua</a></div>
            @if ($recent->isEmpty())
                <x-empty icon="document" title="Belum ada laporan">Laporan yang Anda ajukan akan muncul di sini.</x-empty>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>No. Laporan</th><th>Judul</th><th>Regu</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($recent as $r)
                            <tr>
                                <td><a class="mono strong" href="{{ route('employee.reports.show', $r) }}">{{ $r->report_number }}</a><div class="text-xs muted">{{ $r->created_at->diffForHumans() }}</div></td>
                                <td style="max-width:260px"><div class="truncate">{{ $r->title }}</div><div class="text-xs muted">{{ $r->categoryDisplayName() }}</div></td>
                                <td><x-team-chip :team="$r->team" /></td>
                                <td><x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Pengumuman</h2><a href="{{ route('announcements.index') }}" class="text-sm strong">Lihat semua</a></div>
            <div class="card-body" style="display:grid;gap:14px">
                @forelse ($announcements as $a)
                    <div>
                        <div class="flex items-center gap-2 wrap">
                            @if ($a->is_pinned)<x-icon name="pin" :size="14" />@endif
                            <span class="strong">{{ $a->title }}</span>
                            
                        </div>
                        <p class="text-sm muted pre-line mt-1">{{ \Illuminate\Support\Str::limit($a->body, 180) }}</p>
                        <p class="text-xs faint mt-1">{{ $a->created_at->translatedFormat('d M Y') }} · {{ $a->user->name ?? 'Admin' }}</p>
                    </div>
                @empty
                    <p class="muted text-sm">Belum ada pengumuman.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
