@extends('layouts.app')
@section('title', 'Beranda')
@section('subtitle', 'Halo, '.auth()->user()->name.'. Apa yang bisa kami bantu hari ini?')

@section('content')
    @include('partials.hero', ['metrics' => [
        ['value' => $stats['total'], 'label' => 'Total', 'href' => route('employee.reports.index')],
        ['value' => $stats['aktif'], 'label' => 'Berjalan', 'href' => route('employee.reports.index', ['status' => 'aktif'])],
        ['value' => $stats['selesai'], 'label' => 'Selesai', 'href' => route('employee.reports.index', ['status' => 'selesai'])],
        ['value' => $stats['lainnya'], 'label' => 'Ditolak / Batal', 'href' => route('employee.reports.index', ['status' => 'ditutup'])],
    ]])

    <div class="card mt-5">
        <div class="card-head"><h2>Ajukan Laporan</h2></div>
        <div class="card-body">
            @if ($teams->isEmpty())
                <x-empty icon="inbox" title="Belum ada regu aktif">Hubungi Superadmin untuk mengaktifkan regu penerima laporan.</x-empty>
            @else
                <x-category-search :teams="$teams" mode="link" id="dash-cat-q" empty-text="Tidak ditemukan. Pilih regu di bawah, lalu kategori “Lainnya”." />
                <div class="team-pills">
                    <span class="text-sm muted">atau pilih regu:</span>
                    @foreach ($teams as $team)
                        <a class="team-pill" href="{{ route('employee.reports.create', ['regu' => $team->slug]) }}"><span class="dot" style="background: {{ $team->hex() }}"></span>{{ $team->name }}</a>
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
                <ul class="report-mini">
                    @foreach ($recent as $r)
                        <li>
                            <a href="{{ route('employee.reports.show', $r) }}">
                                <span class="rm-top"><span class="mono strong">{{ $r->report_number }}</span><x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge></span>
                                <span class="rm-title truncate">{{ $r->title }}</span>
                                <span class="rm-meta">{{ $r->team->name ?? '' }} · {{ $r->created_at->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
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
