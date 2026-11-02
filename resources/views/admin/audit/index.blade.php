@extends('layouts.app')
@section('title', 'Audit Log')
@section('subtitle', 'Jejak perubahan penting yang dilakukan admin')

@section('content')
<div class="card">
    <form method="GET" class="card-body filters" data-autosubmit>
        <div class="field grow"><label class="label" for="q">Cari</label><div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="q" name="q" value="{{ $search }}" placeholder="Deskripsi atau nama pelaku…" data-no-auto></div></div>
        <div class="field"><label class="label" for="action">Modul</label><select class="select" id="action" name="action"><option value="">Semua</option>@foreach ($groups as $k => $label)<option value="{{ $k }}" @selected($action === $k)>{{ $label }}</option>@endforeach</select></div>
    </form>
    @if ($logs->isEmpty())
        <x-empty icon="history" title="Belum ada catatan" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Keterangan</th><th>IP</th></tr></thead>
            <tbody>@foreach ($logs as $l)
                <tr><td class="nowrap text-sm">{{ $l->created_at->translatedFormat('d M Y H:i:s') }}</td>
                    <td>{{ $l->user->name ?? 'Sistem' }}</td>
                    <td><span class="badge tone-gray plain mono">{{ $l->action }}</span></td>
                    <td>{{ $l->description }}</td><td class="mono muted">{{ $l->ip_address }}</td></tr>
            @endforeach</tbody></table></div>
    @endif
    {{ $logs->links() }}
</div>
@endsection
