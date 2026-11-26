<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Laporan Bulanan {{ $d['label'] }} · {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
    <script src="{{ asset('js/print.js') }}?v={{ filemtime(public_path('js/print.js')) }}" defer></script>
    <style>
        body{display:block;background:#fff;padding:24px;max-width:900px;margin:0 auto;color:#0F1B2D}
        .kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0}
        .kpi div{border:1px solid #DCE3EF;border-radius:12px;padding:12px 14px}
        .kpi b{display:block;font-size:22px;line-height:1.2}
        table.pt{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px}
        table.pt th,table.pt td{border-bottom:1px solid #E3E9F3;padding:7px 8px;text-align:left;vertical-align:top}
        table.pt th{background:#F3F6FC}
        .noprint{margin-bottom:14px}
        .print-note{display:none;margin:0;font-size:13px;color:#55627A}
        @media (max-width:760px){.print-note{display:block}}
        @media print{.noprint{display:none} body{padding:0} @page{margin:14mm}}
    </style>
</head>
<body data-autoprint>
    <div class="noprint" style="display:grid;gap:8px">
        <div><button type="button" class="btn btn-primary" data-print>Cetak / Simpan PDF</button></div>
        <p class="print-note">Halaman ini berukuran A4 untuk dicetak. Di HP, geser ke samping untuk melihat seluruhnya, atau unduh versi Excel dari halaman Laporan Bulanan.</p>
    </div>

    <div class="flex items-center justify-between" style="border-bottom:2px solid #263A86;padding-bottom:12px">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/halodek-icon.svg') }}" alt="" width="44" height="44" style="border-radius:11px">
            <div><img src="{{ asset('images/halodek-wordmark.svg') }}" alt="{{ config('halodek.name') }}" style="height:19px;width:auto;display:block;margin-bottom:4px"><div class="text-sm muted">{{ config('halodek.company') }}</div></div>
        </div>
        <div class="text-right"><div class="bold" style="font-size:16px">Laporan Bulanan</div><div class="text-sm muted">{{ $d['label'] }}</div></div>
    </div>
    <div class="text-sm muted" style="margin-top:8px">Cakupan: {{ $d['scope'] }} · Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>

    <div class="kpi">
        <div><span class="text-sm muted">Laporan masuk</span><b>{{ $d['total'] }}</b><span class="text-xs muted">Bulan lalu: {{ $d['prev_total'] }}</span></div>
        <div><span class="text-sm muted">Tingkat selesai</span><b>{{ $d['done_pct'] }}%</b><span class="text-xs muted">{{ $d['selesai'] }} dari {{ $d['total'] }}</span></div>
        <div><span class="text-sm muted">Rata-rata penyelesaian</span><b>{{ \App\Support\Num::workHours($d['avg_resolve']) }}</b><span class="text-xs muted">dari {{ $d['resolved_n'] }} laporan selesai</span></div>
        <div><span class="text-sm muted">Respons pertama</span><b>{{ \App\Support\Num::workHours($d['avg_respond']) }}</b><span class="text-xs muted">dari {{ $d['responded_n'] }} laporan ditangani</span></div>
        <div><span class="text-sm muted">Diambil tepat waktu</span><b>{{ $d['on_time_pct'] !== null ? $d['on_time_pct'].'%' : '-' }}</b><span class="text-xs muted">{{ $d['on_time'] }} dari {{ $d['responded_n'] }}</span></div>
        <div><span class="text-sm muted">Kepuasan pelapor</span><b>{{ $d['happy_pct'] !== null ? $d['happy_pct'].'%' : '-' }}</b><span class="text-xs muted">{{ $d['happy'] }} dari {{ $d['answered'] }} menjawab “sudah beres”</span></div>
        <div><span class="text-sm muted">Dibuka ulang</span><b>{{ $d['reopened'] }}</b><span class="text-xs muted">laporan belum beres</span></div>
    </div>
    <div class="text-xs muted">Durasi dalam jam kerja ({{ \App\Support\WorkHours::label() }}). Batas respons: {{ \App\Services\MonthlyReport::slaText() }} jam kerja.</div>

    <div class="text-sm">Menunggu <b>{{ $d['menunggu'] }}</b> · Diproses <b>{{ $d['diproses'] }}</b> · Selesai <b>{{ $d['selesai'] }}</b> · Ditolak <b>{{ $d['ditolak'] }}</b> · Dibatalkan <b>{{ $d['dibatalkan'] }}</b></div>

    <h3 style="margin-top:20px">Per Regu</h3>
    <table class="pt">
        <thead><tr><th>Regu</th><th>Total</th><th>Aktif</th><th>Selesai</th><th>Ditolak/Batal</th><th>Rata-rata selesai</th><th>Respons pertama</th><th>Tepat waktu</th><th>Sudah beres</th><th>Kategori terbanyak</th></tr></thead>
        <tbody>
        @foreach ($d['teams'] as $t)
            <tr>
                <td><b>{{ $t['team']->name }}</b></td><td>{{ $t['total'] }}</td><td>{{ $t['aktif'] }}</td><td>{{ $t['selesai'] }}</td><td>{{ $t['gagal'] }}</td>
                <td>{{ \App\Support\Num::workHours($t['avg_resolve']) }}</td>
                <td>{{ \App\Support\Num::workHours($t['avg_respond']) }}</td>
                <td>{{ $t['on_time_pct'] !== null ? $t['on_time_pct'].'%' : '-' }}</td>
                <td>{{ $t['happy_pct'] !== null ? $t['happy_pct'].'%' : '-' }}</td>
                <td>@forelse ($t['top'] as $c){{ $c['name'] }} ({{ $c['count'] }})@if (! $loop->last), @endif @empty - @endforelse</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
