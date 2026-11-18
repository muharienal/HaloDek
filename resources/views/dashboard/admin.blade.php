@extends('layouts.app')
@section('title', 'Dashboard')
@section('subtitle', $teams->pluck('name')->take(4)->implode(' · ').($teams->count() > 4 ? ' +'.($teams->count() - 4) : ''))

@section('content')
    @if ($teams->isEmpty())
        <div class="alert alert-warning">Anda belum tergabung di regu mana pun. Hubungi Superadmin agar dimasukkan ke regu.</div>
    @endif

    @if ($stats['overdue'] > 0)
        <div class="alert alert-error overdue-alert">
            <x-icon name="clock" :size="18" />
            <div class="grow"><strong>{{ $stats['overdue'] }} laporan lewat batas respons</strong> dan belum diambil siapa pun.
                <span class="muted">Batas dihitung dalam jam kerja ({{ \App\Support\WorkHours::label() }}).</span></div>
            <a class="btn btn-sm btn-danger" href="{{ route('admin.reports.index', ['status' => 'aktif', 'lewat' => 1]) }}">Lihat</a>
        </div>
    @endif

    {{-- ===== 4 ANGKA UTAMA ===== --}}
    @include('partials.hero', ['metrics' => [
        ['value' => $stats['unassigned'], 'label' => 'Menunggu Diambil', 'href' => route('admin.reports.index', ['assigned' => 'none', 'status' => 'aktif']), 'warn' => $stats['unassigned'] > 0],
        ['value' => $stats['mine'], 'label' => 'Tugas Saya', 'href' => route('admin.reports.index', ['assigned' => 'me', 'status' => 'aktif'])],
        ['value' => $stats['diproses'], 'label' => 'Sedang Diproses', 'href' => route('admin.reports.index', ['status' => 'diproses'])],
        ['value' => $stats['avg_hours'] !== null ? $stats['avg_hours'].' jam' : '–', 'label' => 'Rata-rata Selesai', 'href' => route('admin.monthly.index')],
    ]])

    {{-- ===== SATU TABEL: yang perlu ditangani sekarang ===== --}}
    <div class="card mt-5">
        <div class="card-head"><h2>Perlu Ditangani</h2><a href="{{ route('admin.reports.index', ['status' => 'aktif']) }}" class="text-sm strong">Lihat semua</a></div>
        @if ($todo->isEmpty())
            <x-empty icon="check-circle" title="Semua beres">Tidak ada laporan yang menunggu diambil atau tugas aktif untuk Anda.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Laporan</th><th>Regu</th><th>Prioritas</th><th>Keadaan</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($todo as $r)
                        <tr>
                            <td><a class="mono strong" href="{{ route('admin.reports.show', $r) }}">{{ $r->report_number }}</a> <span class="text-xs muted">· {{ $r->created_at->diffForHumans() }}</span>
                                <div class="truncate text-sm" style="max-width:360px">{{ $r->title }}</div></td>
                            <td><x-team-chip :team="$r->team" /></td>
                            <td><x-badge :tone="$r->priorityTone()" plain>{{ $r->priorityLabel() }}</x-badge></td>
                            <td>
                                @if ($r->assigned_to_id === auth()->id())
                                    <span class="badge tone-blue plain">Tugas saya</span>
                                @else
                                    <span class="badge tone-amber plain">Menunggu diambil</span>
                                @endif
                                @if ($overdue->contains('id', $r->id))<span class="badge tone-red plain overdue-badge">Lewat batas</span>@endif
                            </td>
                            <td class="text-right nowrap">
                                @if (! $r->assigned_to_id)
                                    @can('claim', $r)
                                        <form method="POST" action="{{ route('admin.reports.take', $r) }}" style="display:inline">@csrf<button class="btn btn-primary btn-sm">Ambil</button></form>
                                    @endcan
                                @else
                                    <a class="btn btn-secondary btn-sm" href="{{ route('admin.reports.show', $r) }}">Buka</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ===== GRAFIK ===== --}}
    <div class="grid cols-main mt-5">
        <div class="card">
            <div class="card-head"><h2>Tren Laporan 14 Hari</h2></div>
            <div class="card-body">
                <x-chart.line :labels="$trend['labels']" :series="[
                    ['name' => 'Laporan masuk', 'color' => '#1F6FEB', 'data' => $trend['created']],
                    ['name' => 'Laporan selesai', 'color' => '#12B76A', 'data' => $trend['resolved']],
                ]" />
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Kategori Terbanyak</h2></div>
            <div class="card-body">
                @php
                    $palette = ['#1F6FEB', '#12B76A', '#FF7A2F', '#14A39A', '#7C3AED', '#E5484D'];
                    $donut = $byCategory->values()->map(fn ($c, $i) => ['name' => $c['name'], 'value' => $c['total'], 'color' => $palette[$i % 6]])->all();
                @endphp
                @if ($byCategory->isEmpty())
                    <x-empty icon="chart-bar" title="Belum ada data" />
                @else
                    <x-chart.donut :items="$donut" />
                @endif
            </div>
        </div>
    </div>

    @php $hasLeft = $byTeam->count() > 1 || $workload->isNotEmpty(); @endphp
    <div class="grid {{ $hasLeft ? 'cols-main' : '' }} mt-5">
        @if ($hasLeft)
        <div style="display:grid;gap:20px;align-content:start">
    @if ($byTeam->count() > 1)
        <div class="card">
            <div class="card-head"><h2>Ringkasan per Regu</h2></div>
            <div class="card-body">
                <div class="grid cols-2">
                    @foreach ($byTeam as $row)
                        <a href="{{ route('admin.reports.index', ['team_id' => $row['team']->id]) }}" class="flex items-center gap-3" style="padding:12px;border:1px solid var(--line);border-radius:12px;color:inherit;text-decoration:none">
                            <span class="avatar" style="background: {{ $row['team']->hex() }}"><x-icon :name="$row['team']->icon" :size="16" /></span>
                            <span class="grow"><span class="strong" style="display:block">{{ $row['team']->name }}</span><span class="text-xs muted">{{ $row['total'] }} laporan total</span></span>
                            <span class="badge tone-amber plain">{{ $row['open'] }} aktif</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

            @if ($workload->isNotEmpty())
                <div class="card">
                    <div class="card-head"><h2>Beban Kerja Regu</h2></div>
                    <div class="card-body">
                        @php $maxLoad = max(1, $workload->max('open')); @endphp
                        @foreach ($workload as $w)
                            <div class="bar-row" style="grid-template-columns: 120px 1fr 30px">
                                <span class="truncate" title="{{ $w['user']->name }}">{{ $w['user']->name }}</span>
                                <div class="bar-track"><div class="bar-fill" style="width: {{ $w['open'] / $maxLoad * 100 }}%"></div></div>
                                <strong>{{ $w['open'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        @endif
        <div style="display:grid;gap:20px;align-content:start">
            <div class="card">
                <div class="card-head"><h2>Ringkasan Inventaris</h2><a href="{{ route('admin.inventory.index') }}" class="text-sm strong">Kelola</a></div>
                <div class="card-body">
                    <div class="flex gap-4 wrap mb-3">
                        <div><div class="text-xl bold">{{ $inventory['total'] }}</div><div class="text-xs muted">Total item</div></div>
                        <div><div class="text-xl bold" style="color:var(--danger)">{{ $inventory['broken'] }}</div><div class="text-xs muted">Rusak / perbaikan</div></div>
                        <div><div class="text-xl bold" style="color:#B45309">{{ $inventory['due'] }}</div><div class="text-xs muted">Jatuh tempo ≤30 hari</div></div>
                    </div>
                    @php $maxCat = max(1, $inventory['by_category']->max() ?? 1); @endphp
                    @forelse ($inventory['by_category'] as $name => $count)
                        <div class="bar-row" style="grid-template-columns: 110px 1fr 30px">
                            <span class="truncate">{{ $name }}</span>
                            <div class="bar-track"><div class="bar-fill" style="width: {{ $count / $maxCat * 100 }}%"></div></div>
                            <strong>{{ $count }}</strong>
                        </div>
                    @empty
                        <p class="muted text-sm">Belum ada item inventaris.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Pengumuman</h2><a href="{{ route('announcements.index') }}" class="text-sm strong">Lihat semua</a></div>
                <div class="card-body" style="display:grid;gap:12px">
                    @forelse ($announcements as $a)
                        <div>
                            <div class="flex items-center gap-2 wrap">@if ($a->is_pinned)<x-icon name="pin" :size="14" />@endif<span class="strong">{{ $a->title }}</span></div>
                            <p class="text-sm muted mt-1">{{ \Illuminate\Support\Str::limit($a->body, 120) }}</p>
                        </div>
                    @empty
                        <p class="muted text-sm">Belum ada pengumuman.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
