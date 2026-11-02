<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak {{ $report->report_number }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}">
    <style>body{display:block;background:#fff;padding:24px;max-width:820px;margin:0 auto}table.kv td{padding:6px 10px;border-bottom:1px solid #eee;vertical-align:top}table.kv td:first-child{width:200px;color:#64748B}</style>
</head>
<body onload="window.print()">
    <div class="flex items-center justify-between" style="border-bottom:2px solid #2563EB;padding-bottom:12px;margin-bottom:16px">
        <div><div class="bold" style="font-size:22px;color:#1D4ED8;letter-spacing:-.02em">{{ config('halodek.name') }}</div><div class="text-sm muted">{{ config('halodek.company') }}</div></div>
        <div class="text-right"><div class="mono bold" style="font-size:16px">{{ $report->report_number }}</div><div class="text-sm muted">Dicetak {{ now()->translatedFormat('d M Y H:i') }}</div></div>
    </div>
    <h2>{{ $report->title }}</h2>
    <table class="kv w-full mt-3" style="border-collapse:collapse">
        <tr><td>Regu</td><td>{{ $report->team->name }}</td></tr>
        <tr><td>Kategori</td><td>{{ $report->categoryDisplayName() }}</td></tr>
        <tr><td>Pelapor</td><td>{{ $report->reporter_name }}{{ $report->user ? ' (NIK '.$report->user->nik.')' : '' }}</td></tr>
        <tr><td>Diajukan</td><td>{{ $report->created_at->translatedFormat('d M Y H:i') }}</td></tr>
        <tr><td>Prioritas / Status</td><td>{{ $report->priorityLabel() }} / {{ $report->statusLabel() }}</td></tr>
        <tr><td>Penanggung jawab</td><td>{{ $report->assignee->name ?? '–' }}</td></tr>
        <tr><td>Lokasi</td><td>{{ $report->location?->fullName() ?? '–' }}</td></tr>
        <tr><td>Inventaris</td><td>{{ $report->item?->label() ?? '–' }}</td></tr>
        <tr><td>Selesai</td><td>{{ $report->resolved_at?->translatedFormat('d M Y H:i') ?? '–' }}</td></tr>
    </table>
    <h3 class="mt-5">Uraian</h3><p class="pre-line mt-2">{{ $report->description }}</p>
    @if ($report->follow_up_note)<h3 class="mt-5">Catatan Tindak Lanjut</h3><p class="pre-line mt-2">{{ $report->follow_up_note }}</p>@endif
    <h3 class="mt-5">Riwayat</h3>
    <table class="kv w-full mt-2" style="border-collapse:collapse">
        @foreach ($activities as $a)<tr><td>{{ $a->created_at->translatedFormat('d M Y H:i') }}</td><td><strong>{{ $a->user->name ?? 'Sistem' }}</strong> — {{ $a->message }}</td></tr>@endforeach
    </table>
    <div class="flex justify-between mt-6" style="margin-top:56px"><div class="text-center" style="width:220px">Pelapor<br><br><br><br>(________________)</div><div class="text-center" style="width:220px">Petugas<br><br><br><br>(________________)</div></div>
</body>
</html>
