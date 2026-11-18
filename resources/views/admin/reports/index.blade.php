@extends('layouts.app')
@section('title', request('assigned') === 'me' ? 'Tugas Saya' : (request('assigned') === 'none' ? 'Menunggu Diambil' : 'Kelola Laporan'))
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
        <a class="btn btn-secondary btn-sm" href="{{ route('admin.reports.export', request()->query()) }}"><x-icon name="download" :size="16" /> Ekspor Excel</a>
    </div>

    @if ($overdueIds || $f['lewat'])
        <div class="card-body" style="padding-bottom:0">
            <div class="alert {{ $f['lewat'] ? 'alert-info' : 'alert-error' }} overdue-alert" style="margin:0">
                <x-icon name="clock" :size="18" />
                <div class="grow">
                    @if ($f['lewat'])Menampilkan laporan yang <strong>lewat batas respons</strong> saja.
                    @else<strong>{{ count($overdueIds) }} laporan lewat batas respons</strong> dan belum diambil.@endif
                </div>
                @if ($f['lewat'])<a class="btn btn-sm btn-secondary" href="{{ route('admin.reports.index', $keep(['lewat' => null])) }}">Tampilkan semua</a>
                @else<a class="btn btn-sm btn-danger" href="{{ route('admin.reports.index', $keep(['lewat' => 1, 'status' => 'aktif'])) }}">Tampilkan</a>@endif
            </div>
        </div>
    @endif

    <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
        <input type="hidden" name="status" value="{{ $f['status'] }}">
        @if ($f['lewat'])<input type="hidden" name="lewat" value="1">@endif
        <div class="field"><span class="label">Cari</span>
            @if ($f['q'] !== '')
                <a class="btn btn-secondary search-chip" href="{{ route('admin.reports.index', $keep(['q' => null])) }}" title="Hapus pencarian">“{{ \Illuminate\Support\Str::limit($f['q'], 24) }}” <x-icon name="x" :size="14" /></a>
            @else
                <button type="button" class="btn btn-secondary" data-search-open><x-icon name="search" :size="16" /> Cari</button>
            @endif
        </div>
        @if ($f['q'] !== '')<input type="hidden" name="q" value="{{ $f['q'] }}">@endif
        @php $activeFilters = collect([$f['team_id'], $f['priority'], $f['assigned'], $f['from'], $f['to']])->filter()->count(); @endphp
        <div class="field filter-toggle-wrap"><span class="label">&nbsp;</span>
            <button type="button" class="btn btn-secondary filter-toggle" data-filter-toggle aria-expanded="false"><x-icon name="filter" :size="16" /> Filter @if ($activeFilters)<span class="count">{{ $activeFilters }}</span>@endif</button></div>
        @if ($teams->count() > 1)
            <div class="field filter-more"><label class="label" for="team_id">Regu</label>
                <select class="select" id="team_id" name="team_id"><option value="">Semua</option>@foreach ($teams as $t)<option value="{{ $t->id }}" @selected($f['team_id'] == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        @endif
        <div class="field filter-more"><label class="label" for="priority">Prioritas</label>
            <select class="select" id="priority" name="priority"><option value="">Semua</option>@foreach (config('halodek.priorities') as $k => $p)<option value="{{ $k }}" @selected($f['priority'] === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
        <div class="field filter-more"><label class="label" for="assigned">Penanggung jawab</label>
            <select class="select" id="assigned" name="assigned"><option value="">Semua</option><option value="me" @selected($f['assigned'] === 'me')>Saya sendiri</option><option value="none" @selected($f['assigned'] === 'none')>Belum ada penanggung jawab</option>
                @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($f['assigned'] == $s->id && is_numeric($f['assigned']))>{{ $s->name }}</option>@endforeach</select></div>
        <div class="field filter-more"><label class="label" for="from">Dari</label><input class="input" type="date" id="from" name="from" value="{{ $f['from'] }}"></div>
        <div class="field filter-more"><label class="label" for="to">Sampai</label><input class="input" type="date" id="to" name="to" value="{{ $f['to'] }}"></div>
        @if (collect(request()->only(['q', 'team_id', 'priority', 'assigned', 'from', 'to']))->filter()->isNotEmpty())
            <a class="btn btn-ghost filter-more" href="{{ route('admin.reports.index', ['status' => $f['status']]) }}">Reset filter</a>
        @endif
    </form>

    @if ($reports->isEmpty())
        <x-empty icon="inbox" title="Tidak ada laporan">Tidak ada laporan yang cocok dengan filter saat ini.</x-empty>
    @else
        <div class="table-wrap mt-4">
            <table class="table report-table">
                <thead><tr><th class="bulk-col"><input type="checkbox" class="bulk-check" data-bulk-all aria-label="Pilih semua laporan aktif di halaman ini"></th><th>Laporan</th><th>Pelapor</th><th>Regu / Kategori</th><th>Prioritas</th><th>Status</th><th>Penanggung Jawab</th><th></th></tr></thead>
                <tbody>
                @foreach ($reports as $r)
                    <tr>
                        <td class="bulk-col">@if ($r->isOpen())<input type="checkbox" class="bulk-check" name="ids[]" value="{{ $r->id }}" form="bulk-form" data-bulk-item aria-label="Pilih {{ $r->report_number }}">@endif</td>
                        <td style="max-width:280px"><a class="mono strong" href="{{ route('admin.reports.show', $r) }}">{{ $r->report_number }}</a>
                            <div class="truncate">{{ $r->title }}</div><div class="text-xs muted">{{ $r->created_at->translatedFormat('d M Y H:i') }}</div></td>
                        <td>{{ $r->reporter_name }}</td>
                        <td><x-team-chip :team="$r->team" /><div class="text-xs muted mt-1">{{ $r->categoryDisplayName() }}</div></td>
                        <td><x-badge :tone="$r->priorityTone()" plain>{{ $r->priorityLabel() }}</x-badge></td>
                        <td>
                            <x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge>
                            @if (in_array($r->id, $overdueIds, true))
                                <div class="mt-1"><span class="badge tone-red plain overdue-badge">Lewat batas</span></div>
                            @endif
                            @if ($r->reopened_count && $r->isOpen())
                                <div class="text-xs mt-1" style="color:#A33305">Dibuka ulang pelapor</div>
                            @endif
                            @if ($r->resolved_at)
                                <div class="text-xs muted mt-1">{{ $r->resolved_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td>@if ($r->assignee)<span class="flex items-center gap-2"><span class="avatar sm">{{ $r->assignee->initials() }}</span><span class="text-sm">{{ \Illuminate\Support\Str::words($r->assignee->name, 2, '') }}</span></span>@elseif ($r->isOpen())<span class="badge tone-amber plain">Belum ada</span>@else<span class="faint text-sm">–</span>@endif</td>
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

{{-- ===== Bilah aksi massal (muncul saat ada laporan dipilih) ===== --}}
@php $canAssignAny = ! auth()->user()->isStaf(); @endphp
<form method="POST" action="{{ route('admin.reports.bulk') }}" id="bulk-form" class="bulk-bar" hidden
      data-confirm="Terapkan aksi ini ke semua laporan yang dipilih?" data-confirm-title="Aksi massal" data-confirm-ok="Terapkan" data-confirm-tone="primary">
    @csrf
    <span class="strong bulk-count" id="bulk-count">0 dipilih</span>
    <select class="select" name="action" id="bulk-action" required aria-label="Aksi">
        <option value="">— Pilih aksi —</option>
        <option value="ambil">Ambil (saya kerjakan)</option>
        @if ($canAssignAny)<option value="tugaskan">Tugaskan ke…</option>@endif
        <option value="selesai">Tandai selesai</option>
        <option value="ditolak">Tolak</option>
    </select>
    @if ($canAssignAny)
        <select class="select" name="assigned_to_id" id="bulk-assignee" hidden aria-label="Penanggung jawab">
            <option value="">— Penanggung jawab —</option>
            @foreach ($staff as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
        </select>
    @endif
    <input class="input" name="bulk_note" id="bulk-note" maxlength="3000" hidden placeholder="Catatan untuk pelapor (wajib)" aria-label="Catatan untuk pelapor">
    <button class="btn btn-primary">Terapkan</button>
    <button type="button" class="btn btn-ghost" data-bulk-clear>Batal</button>
</form>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var bar = document.getElementById('bulk-form');
    if (!bar) { return; }
    var all = document.querySelector('[data-bulk-all]');
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]'));
    var count = document.getElementById('bulk-count');
    var action = document.getElementById('bulk-action');
    var assignee = document.getElementById('bulk-assignee');
    var note = document.getElementById('bulk-note');
    if (!items.length && all) { all.disabled = true; }

    function sync() {
        var n = items.filter(function (i) { return i.checked; }).length;
        bar.hidden = n === 0;
        document.body.classList.toggle('has-bulk', n > 0);
        count.textContent = n + ' dipilih';
        if (all) { all.checked = n > 0 && n === items.length; all.indeterminate = n > 0 && n < items.length; }
    }
    function mode() {
        var v = action.value;
        if (assignee) { assignee.hidden = v !== 'tugaskan'; assignee.required = v === 'tugaskan'; }
        note.hidden = !(v === 'selesai' || v === 'ditolak');
        note.required = !note.hidden;
        note.placeholder = v === 'ditolak' ? 'Alasan penolakan untuk pelapor (wajib)' : 'Hasil penyelesaian untuk pelapor (wajib)';
    }
    if (all) { all.addEventListener('change', function () { items.forEach(function (i) { i.checked = all.checked; }); sync(); }); }
    items.forEach(function (i) { i.addEventListener('change', sync); });
    action.addEventListener('change', mode);
    bar.querySelector('[data-bulk-clear]').addEventListener('click', function () { items.forEach(function (i) { i.checked = false; }); sync(); });
    sync(); mode();
})();
</script>
@endpush
