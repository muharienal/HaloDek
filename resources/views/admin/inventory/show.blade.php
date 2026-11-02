@extends('layouts.app')
@section('title', $item->name)
@section('subtitle', $item->code.' · '.$item->category->name.' · '.$item->team->name)

@section('content')
<div class="mb-4"><a href="{{ route('admin.inventory.index', ['team_id' => $item->team_id, 'category' => $item->inventory_category_id]) }}" class="btn btn-ghost btn-sm"><x-icon name="arrow-left" :size="16" /> Kembali ke inventaris</a></div>

<div class="grid cols-main" style="align-items:start">
    <div class="card">
        <div class="card-head"><h2>Laporan Terkait</h2></div>
        @if ($reports->isEmpty())
            <x-empty icon="clipboard" title="Belum ada laporan">Belum ada pengaduan yang menyebut item ini.</x-empty>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>No. Laporan</th><th>Judul</th><th>Status</th><th>Tanggal</th></tr></thead>
                <tbody>@foreach ($reports as $r)
                    <tr>
                        <td class="mono"><a href="{{ route('admin.reports.show', $r) }}">{{ $r->report_number }}</a></td>
                        <td class="strong">{{ $r->title }}</td>
                        <td><x-badge :tone="config('halodek.statuses.'.$r->status.'.tone', 'gray')">{{ config('halodek.statuses.'.$r->status.'.label') }}</x-badge></td>
                        <td class="nowrap text-sm muted">{{ $r->created_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @endforeach</tbody></table></div>
            {{ $reports->links() }}
        @endif
    </div>

    <div class="card">
        <div class="card-head"><h2>Detail Item</h2></div>
        <div class="card-body" style="display:grid;gap:14px">
            <div><div class="text-xs muted">Status</div><x-badge :tone="$item->statusTone()">{{ $item->statusLabel() }}</x-badge></div>
            <div><div class="text-xs muted">Regu</div><x-team-chip :team="$item->team" /></div>
            <div><div class="text-xs muted">Pemegang</div><div>{{ $item->holder ? $item->holder->name.' · '.$item->holder->nik : '–' }}</div></div>
            <div><div class="text-xs muted">Lokasi</div><div>{{ $item->location?->fullName() ?? '–' }}</div></div>
            <div><div class="text-xs muted">Tanggal perolehan</div><div>{{ $item->acquired_at?->translatedFormat('d M Y') ?? '–' }}</div></div>
            <div><div class="text-xs muted">Jatuh tempo</div><div class="{{ $item->dueState() === 'lewat' ? 'danger-text strong' : '' }}">{{ $item->due_date?->translatedFormat('d M Y') ?? '–' }}</div></div>
            @if ($item->note)<div><div class="text-xs muted">Catatan</div><div class="pre-line">{{ $item->note }}</div></div>@endif
        </div>
    </div>
</div>
@endsection
