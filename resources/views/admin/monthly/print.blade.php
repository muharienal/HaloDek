<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Laporan Bulanan {{ $d['label'] }} · {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
    <style>
        body{display:block;background:#fff;padding:24px;max-width:900px;margin:0 auto;color:#0F1B2D}
        .kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:16px 0}
        .kpi div{border:1px solid #DCE3EF;border-radius:12px;padding:12px 14px}
        .kpi b{display:block;font-size:22px;line-height:1.2}
        table.pt{width:100%;border-collapse:collapse;margin-top:8px;font-size:13px}
        table.pt th,table.pt td{border-bottom:1px solid #E3E9F3;padding:7px 8px;text-align:left;vertical-align:top}
        table.pt th{background:#F3F6FC}
        .noprint{margin-bottom:14px}
        @media print{.noprint{display:none} body{padding:0} @page{margin:14mm}}
    </style>
</head>
<body onload="window.print()">
    <div class="noprint"><button class="btn btn-primary" onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>

    <div class="flex items-center justify-between" style="border-bottom:2px solid #263A86;padding-bottom:12px">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/halodek-icon.svg') }}" alt="" width="44" height="44" style="border-radius:11px">
            <div><img src="{{ asset('images/halodek-wordmark.svg') }}" alt="{{ config('halodek.name') }}" style="height:16px;width:auto;display:block;margin-bottom:4px"><div class="text-sm muted">{{ config('halodek.company') }}</div></div>
        </div>
        <div class="text-right"><div class="bold" style="font-size:16px">Laporan Bulanan</div><div class="text-sm muted">{{ $d['label'] }}</div></div>
    </div>
    <div class="text-sm muted" style="margin-top:8px">Cakupan: {{ $d['scope'] }} · Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div>

    <div class="kpi">
        <div><span class="text-sm muted">Laporan masuk</span><b>{{ $d['total'] }}</b><span class="text-xs muted">Bulan lalu: {{ $d['prev_total'] }}</span></div>
        <div><span class="text-sm muted">Tingkat selesai</span><b>{{ $d['done_pct'] }}%</b><span class="text-xs muted">{{ $d['selesai'] }} dari {{ $d['total'] }}</span></div>
        <div><span class="text-sm muted">Rata-rata penyelesaian</span><b>{{ $d['avg_resolve'] !== null ? $d['avg_resolve'].' jam kerja' : '–' }}</b></div>
        <div><span class="text-sm muted">Respons pertama</span><b>{{ $d['avg_respond'] !== null ? $d['avg_respond'].' jam kerja' : '–' }}</b></div>
    </div>

    <div class="text-sm">Menunggu <b>{{ $d['menunggu'] }}</b> · Diproses <b>{{ $d['diproses'] }}</b> · Selesai <b>{{ $d['selesai'] }}</b> · Ditolak <b>{{ $d['ditolak'] }}</b> · Dibatalkan <b>{{ $d['dibatalkan'] }}</b></div>

    <h3 style="margin-top:20px">Per Regu</h3>
    <table class="pt">
        <thead><tr><th>Regu</th><th>Total</th><th>Aktif</th><th>Selesai</th><th>Ditolak/Batal</th><th>Rata-rata selesai</th><th>Respons pertama</th><th>Kategori terbanyak</th></tr></thead>
        <tbody>
        @foreach ($d['teams'] as $t)
            <tr>
                <td><b>{{ $t['team']->name }}</b></td><td>{{ $t['total'] }}</td><td>{{ $t['aktif'] }}</td><td>{{ $t['selesai'] }}</td><td>{{ $t['gagal'] }}</td>
                <td>{{ $t['avg_resolve'] !== null ? $t['avg_resolve'].' jam kerja' : '–' }}</td>
                <td>{{ $t['avg_respond'] !== null ? $t['avg_respond'].' jam kerja' : '–' }}</td>
                <td>@forelse ($t['top'] as $c){{ $c['name'] }} ({{ $c['count'] }})@if (! $loop->last), @endif @empty – @endforelse</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
