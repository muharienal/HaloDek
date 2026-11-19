@extends('layouts.app')
@section('title', 'Laporan Bulanan')
@section('subtitle', $d['scope'])

@section('content')
    <div class="card">
        <form method="GET" action="{{ route('admin.monthly.index') }}" class="card-body flex gap-3 wrap items-end" data-autosubmit>
            <div class="field" style="min-width:200px">
                <label class="label" for="bulan">Bulan</label>
                <select class="input" name="bulan" id="bulan">
                    @foreach ($months as $val => $label)<option value="{{ $val }}" @selected($val === $selected)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <noscript><button class="btn btn-secondary">Tampilkan</button></noscript>
            <div class="flex gap-2 wrap" style="margin-left:auto">
                <a class="btn btn-secondary" href="{{ route('admin.monthly.print', ['bulan' => $selected]) }}" target="_blank" rel="noopener"><x-icon name="document" :size="16" /> Cetak / PDF</a>
                <a class="btn btn-primary" href="{{ route('admin.monthly.download', ['bulan' => $selected]) }}"><x-icon name="download" :size="16" /> Unduh Excel</a>
            </div>
        </form>
    </div>

    @if ($d['total'] === 0)
        <div class="card mt-4"><x-empty icon="document" title="Belum ada laporan">Tidak ada laporan yang masuk pada {{ $d['label'] }}.</x-empty></div>
    @else
        <div class="grid cols-4 mt-4">
            <div class="card stat"><span class="lbl">Laporan Masuk</span><span class="num">{{ $d['total'] }}</span>
                <span class="hint faint">Bulan lalu: {{ $d['prev_total'] }}</span></div>
            <div class="card stat"><span class="lbl">Tingkat Selesai</span><span class="num">{{ $d['done_pct'] }}%</span>
                <div class="progress mt-1"><span style="width: {{ $d['done_pct'] }}%"></span></div></div>
            <div class="card stat"><span class="lbl">Rata-rata Penyelesaian</span><span class="num">{{ $d['avg_resolve'] !== null ? $d['avg_resolve'].' jam' : '–' }}</span>
                <span class="hint faint">dari {{ $d['resolved_n'] }} laporan yang sudah selesai</span></div>
            <div class="card stat"><span class="lbl">Respons Pertama</span><span class="num">{{ $d['avg_respond'] !== null ? $d['avg_respond'].' jam' : '–' }}</span>
                <span class="hint faint">dari {{ $d['responded_n'] }} laporan yang sudah ditangani</span></div>
        </div>

        <div class="grid cols-3 mt-4">
            <div class="card stat"><span class="lbl">Diambil Tepat Waktu</span><span class="num">{{ $d['on_time_pct'] !== null ? $d['on_time_pct'].'%' : '–' }}</span>
                <span class="hint faint">{{ $d['on_time'] }} dari {{ $d['responded_n'] }} yang sudah ditangani</span></div>
            <div class="card stat"><span class="lbl">Kepuasan Pelapor</span><span class="num">{{ $d['happy_pct'] !== null ? $d['happy_pct'].'%' : '–' }}</span>
                <span class="hint faint">{{ $d['happy'] }} dari {{ $d['answered'] }} pelapor menjawab “sudah beres”</span></div>
            <div class="card stat"><span class="lbl">Dibuka Ulang</span><span class="num">{{ $d['reopened'] }}</span>
                <span class="hint faint">laporan yang ternyata belum beres</span></div>
        </div>
        <p class="text-xs muted mt-2" style="padding:0 4px">Semua durasi dalam jam kerja ({{ \App\Support\WorkHours::label() }}). Respons pertama dihitung dari semua laporan yang sudah ditangani (termasuk yang masih diproses), sedangkan penyelesaian hanya dari laporan yang sudah selesai, sehingga angkanya bisa lebih besar. Batas respons: {{ \App\Services\MonthlyReport::slaText() }} jam kerja.</p>

        <div class="card mt-4">
            <div class="card-head"><h2>Status Laporan {{ $d['label'] }}</h2></div>
            <div class="card-body flex gap-2 wrap">
                <x-badge tone="amber">Menunggu {{ $d['menunggu'] }}</x-badge>
                <x-badge tone="blue">Diproses {{ $d['diproses'] }}</x-badge>
                <x-badge tone="green">Selesai {{ $d['selesai'] }}</x-badge>
                <x-badge tone="red">Ditolak {{ $d['ditolak'] }}</x-badge>
                <x-badge tone="gray">Dibatalkan {{ $d['dibatalkan'] }}</x-badge>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-head"><h2>Per Regu</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Regu</th><th>Total</th><th>Aktif</th><th>Selesai</th><th>Ditolak / Batal</th><th>Rata-rata selesai</th><th>Respons pertama</th><th>Tepat waktu</th><th>Sudah beres</th><th>Kategori terbanyak</th></tr></thead>
                    <tbody>
                    @foreach ($d['teams'] as $t)
                        <tr>
                            <td><x-team-chip :team="$t['team']" /></td>
                            <td class="strong">{{ $t['total'] }}</td>
                            <td>{{ $t['aktif'] }}</td>
                            <td>{{ $t['selesai'] }}</td>
                            <td>{{ $t['gagal'] }}</td>
                            <td>{{ $t['avg_resolve'] !== null ? $t['avg_resolve'].' jam kerja' : '–' }}</td>
                            <td>{{ $t['avg_respond'] !== null ? $t['avg_respond'].' jam kerja' : '–' }}</td>
                            <td>{{ $t['on_time_pct'] !== null ? $t['on_time_pct'].'%' : '–' }}</td>
                            <td>{{ $t['happy_pct'] !== null ? $t['happy_pct'].'%' : '–' }}@if ($t['reopened'])<div class="text-xs muted">{{ $t['reopened'] }} dibuka ulang</div>@endif</td>
                            <td>
                                @forelse ($t['top'] as $c)<div class="text-sm">{{ $c['name'] }} <span class="muted">· {{ $c['count'] }}</span></div>@empty<span class="muted">–</span>@endforelse
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
