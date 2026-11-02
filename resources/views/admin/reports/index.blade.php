@extends('layouts.app')
@section('title', request('assigned') === 'me' ? 'Tugas Saya' : (request('assigned') === 'none' ? 'Antrean Regu' : 'Kelola Laporan'))
@section('subtitle', 'Ambil, tugaskan, dan pantau laporan dari regu yang Anda kelola')

@section('content')
@php
    $f = $filters;
    $keep = fn (array $extra = []) => array_filter(array_merge(request()->except('page'), $extra), fn ($v) => $v !== null && $v !== '');
    $totalAll = $statusCounts->sum();
    $aktif = ($statusCounts['menunggu'] ?? 0) + ($statusCounts['diproses'] ?? 0);
@endphp

<div class="card">
    <div class="card-head" style="flex-wrap:wrap">
        <div class="tabs">
            <a class="tab {{ $f['status'] === 'semua' ? 'active' : '' }}" href="{{ route('admin.reports.index', $keep(['status' => 'semua'])) }}">Semua <span class="n">{{ $totalAll }}</span></a>
            <a class="tab {{ $f['status'] === 'aktif' ? 'active' : '' }}" href="{{ route('admin.reports.index', $keep(['status' => 'aktif'])) }}">Aktif <span class="n">{{ $aktif }}</span></a>
            @foreach (['menunggu', 'diproses', 'selesai', 'ditolak', 'dibatalkan'] as $s)
                <a class="tab {{ $f['status'] === $s ? 'active' : '' }}" href="{{ route('admin.reports.index', $keep(['status' => $s])) }}">{{ config("halodek.statuses.$s.label") }} <span class="n">{{ $statusCounts[$s] ?? 0 }}</span></a>
            @endforeach
        </div>
        <a class="btn btn-secondary btn-sm" href="{{ route('admin.reports.export', request()->query()) }}"><x-icon name="download" :size="16" /> Ekspor CSV</a>
    </div>

    <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
        <input type="hidden" name="status" value="{{ $f['status'] }}">
        <div class="field grow" style="min-width:200px"><label class="label" for="q">Cari</label>
            <div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="q" name="q" value="{{ $f['q'] }}" placeholder="No. laporan, pelapor, judul…" data-no-auto></div></div>
        @if ($teams->count() > 1)
            <div class="field"><label class="label" for="team_id">Regu</label>
                <select class="select" id="team_id" name="team_id"><option value="">Semua</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected($f['team_id'] == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        @endif
        <div class="field"><label class="label" for="priority">Prioritas</label>
            <select class="select" id="priority" name="priority"><option value="">Semua</option>@foreach (config('halodek.priorities') as $k => $p)<option value="{{ $k }}" @selected($f['priority'] === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
        <div class="field"><label class="label" for="assigned">Penanggung jawab</label>
            <select class="select" id="assigned" name="assigned"><option value="">Semua</option><option value="me" @selected($f['assigned'] === 'me')>Ditugaskan ke saya</option><option value="none" @selected($f['assigned'] === 'none')>Belum ditugaskan (antrean)</option>
                @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($f['assigned'] == $s->id && is_numeric($f['assigned']))>{{ $s->name }}</option>@endforeach</select></div>
        <div class="field"><label class="label" for="from">Dari</label><input class="input" type="date" id="from" name="from" value="{{ $f['from'] }}"></div>
        <div class="field"><label class="label" for="to">Sampai</label><input class="input" type="date" id="to" name="to" value="{{ $f['to'] }}"></div>
        @if (collect(request()->only(['q', 'team_id', 'priority', 'assigned', 'from', 'to']))->filter()->isNotEmpty())
            <a class="btn btn-ghost" href="{{ route('admin.reports.index', ['status' => $f['status']]) }}">Reset filter</a>
        @endif
    </form>

    @if ($reports->isEmpty())
        <x-empty icon="inbox" title="Tidak ada laporan">Tidak ada laporan yang cocok dengan filter saat ini.</x-empty>
    @else
        <div class="table-wrap mt-4">
            <table class="table">
                <thead><tr><th>Laporan</th><th>Pelapor</th><th>Regu / Kategori</th><th>Prioritas</th><th>Status</th><th>Penanggung Jawab</th><th></th></tr></thead>
                <tbody>
                @foreach ($reports as $r)
                    <tr>
                        <td style="max-width:280px"><a class="mono strong" href="{{ route('admin.reports.show', $r) }}">{{ $r->report_number }}</a>
                            <div class="truncate">{{ $r->title }}</div><div class="text-xs muted">{{ $r->created_at->translatedFormat('d M Y H:i') }}</div></td>
                        <td>{{ $r->reporter_name }}</td>
                        <td><x-team-chip :team="$r->team" /><div class="text-xs muted mt-1">{{ $r->categoryDisplayName() }}</div></td>
                        <td><x-badge :tone="$r->priorityTone()" plain>{{ $r->priorityLabel() }}</x-badge></td>
                        <td><x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge>@if ($r->resolved_at)<div class="text-xs muted mt-1">{{ $r->resolved_at->diffForHumans() }}</div>@endif</td>
                        <td>@if ($r->assignee)<span class="flex items-center gap-2"><span class="avatar sm">{{ $r->assignee->initials() }}</span><span class="text-sm">{{ \Illuminate\Support\Str::words($r->assignee->name, 2, '') }}</span></span>@elseif ($r->isOpen())<span class="badge tone-amber plain">Antrean</span>@else<span class="faint text-sm">–</span>@endif</td>
                        <td class="text-right nowrap">
                            @can('claim', $r)
@unless ($r->assigned_to_id)
                                <form method="POST" action="{{ route('admin.reports.take', $r) }}" style="display:inline">@csrf
                                    <button class="btn btn-primary btn-sm" title="Ambil untuk dikerjakan">Ambil</button></form>
                            @endunless
                            @endcan
                            <a class="btn btn-secondary btn-sm" href="{{ route('admin.reports.show', $r) }}">Buka</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
    {{ $reports->links() }}
</div>
@endsection
