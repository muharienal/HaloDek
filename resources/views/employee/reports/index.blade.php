@extends('layouts.app')
@section('title', 'Laporan Saya')
@section('subtitle', 'Pantau status dan tindak lanjut laporan yang Anda ajukan')

@section('content')
    <div class="card">
        <div class="card-head" style="flex-wrap:wrap">
            <div class="tabs">
                @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditolak' => 'Ditolak', 'dibatalkan' => 'Dibatalkan'] as $key => $label)
                    <a class="tab {{ $status === $key ? 'active' : '' }}" href="{{ route('employee.reports.index', array_filter(['status' => $key, 'q' => $search, 'team_id' => $teamId])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="field grow"><label class="label" for="q">Cari</label>
                <div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="q" name="q" value="{{ $search }}" placeholder="No. laporan atau judul…" data-no-auto></div></div>
            <div class="field"><label class="label" for="team_id">Regu</label>
                <select class="select" id="team_id" name="team_id"><option value="">Semua regu</option>
                    @foreach ($teams as $t)<option value="{{ $t->id }}" @selected($teamId == $t->id)>{{ $t->name }}</option>@endforeach</select></div>
        </form>

        @if ($reports->isEmpty())
            <x-empty icon="document" title="Tidak ada laporan">Belum ada laporan yang cocok. Ajukan laporan baru bila Anda membutuhkan bantuan.</x-empty>
        @else
            <div class="table-wrap mt-4">
                <table class="table">
                    <thead><tr><th>No. Laporan</th><th>Judul</th><th>Regu</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($reports as $r)
                        <tr>
                            <td><a class="mono strong" href="{{ route('employee.reports.show', $r) }}">{{ $r->report_number }}</a><div class="text-xs muted">{{ $r->created_at->translatedFormat('d M Y H:i') }}</div></td>
                            <td style="max-width:300px"><div class="truncate strong">{{ $r->title }}</div><div class="text-xs muted">{{ $r->categoryDisplayName() }}</div></td>
                            <td><x-team-chip :team="$r->team" /></td>
                            <td><x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge></td>
                            <td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('employee.reports.show', $r) }}">Detail</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        {{ $reports->links() }}
    </div>
@endsection
