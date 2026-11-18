@extends('layouts.app')
@section('title', 'Laporan Saya')
@section('subtitle', 'Pantau status dan penanganan laporan yang Anda ajukan')

@section('content')
    <div class="card">
        <div class="card-head" style="flex-wrap:wrap">
            <div class="tabs">
                @foreach (['semua' => 'Semua', 'aktif' => 'Aktif', 'menunggu' => 'Menunggu', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditolak / Batal'] as $key => $label)
                    <a class="tab {{ $status === $key ? 'active' : '' }}" href="{{ route('employee.reports.index', array_filter(['status' => $key, 'q' => $search, 'team_id' => $teamId])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
            <input type="hidden" name="status" value="{{ $status }}">
            @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
            <div class="field"><span class="label">Cari</span>
                @if ($search !== '')
                    <a class="btn btn-secondary search-chip" href="{{ route('employee.reports.index', array_filter(['status' => $status, 'team_id' => $teamId])) }}" title="Hapus pencarian">“{{ \Illuminate\Support\Str::limit($search, 24) }}” <x-icon name="x" :size="14" /></a>
                @else
                    <button type="button" class="btn btn-secondary" data-search-open><x-icon name="search" :size="16" /> Cari</button>
                @endif
            </div>
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
