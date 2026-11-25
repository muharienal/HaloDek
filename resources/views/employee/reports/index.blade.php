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
            <div class="field grow" style="min-width:220px"><span class="label">Cari</span>
                @if ($search !== '')
                    <input type="hidden" name="q" value="{{ $search }}">
                    <div class="search-trigger is-filled"><x-icon name="search" :size="16" /><button type="button" data-search-open class="search-trigger-text">“{{ \Illuminate\Support\Str::limit($search, 40) }}”</button>
                        <a href="{{ route('employee.reports.index', array_filter(['status' => $status, 'team_id' => $teamId])) }}" class="search-clear" title="Hapus pencarian" aria-label="Hapus pencarian"><x-icon name="x" :size="14" /></a></div>
                @else
                    <button type="button" class="search-trigger" data-search-open><x-icon name="search" :size="16" /> <span>No. laporan atau judul…</span></button>
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
                            <td class="text-right"><a class="btn btn-ghost btn-sm" href="{{ route('employee.reports.show', $r) }}">Detail <x-icon name="chevron-right" :size="14" /></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        {{ $reports->links() }}
    </div>
@endsection
