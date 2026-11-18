@extends('layouts.app')
@section('title', 'Pencarian')
@section('subtitle', $q !== '' ? 'Hasil untuk "'.$q.'"' : 'Cari nomor laporan, judul, atau pelapor')

@section('content')
    @php $isAdmin = auth()->user()->isAdmin(); @endphp
    <div class="card">
        <form method="GET" action="{{ route('search') }}" class="card-body flex gap-3 items-end" role="search">
            <div class="field grow">
                <label class="label" for="q-main">Kata kunci</label>
                <input class="input" id="q-main" type="search" name="q" value="{{ $q }}" maxlength="60" placeholder="{{ $isAdmin ? 'No. laporan, judul, nama atau NIK pelapor' : 'No. laporan atau judul' }}" autocomplete="off">
            </div>
            <button class="btn btn-primary"><x-icon name="search" :size="16" /> Cari</button>
        </form>
    </div>

    @if ($tooShort)
        <div class="alert alert-warning mt-4"><x-icon name="alert" :size="18" /><div>Ketik minimal 2 karakter.</div></div>
    @elseif ($q !== '')
        <div class="card mt-4">
            @if ($reports->isEmpty())
                <x-empty icon="search" title="Tidak ditemukan">Tidak ada laporan yang cocok dengan "{{ $q }}".</x-empty>
            @else
                <div class="card-head"><h2>{{ $reports->count() }}{{ $reports->count() === 30 ? '+' : '' }} laporan ditemukan</h2></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Laporan</th>@if ($isAdmin)<th>Pelapor</th>@endif<th>Regu</th><th>Status</th><th>Diajukan</th></tr></thead>
                        <tbody>
                        @foreach ($reports as $r)
                            @php $href = $isAdmin ? route('admin.reports.show', $r) : route('employee.reports.show', $r); @endphp
                            <tr>
                                <td><a class="mono strong" href="{{ $href }}">{{ $r->report_number }}</a><div class="truncate text-sm" style="max-width:360px">{{ $r->title }}</div></td>
                                @if ($isAdmin)<td>{{ $r->reporter_name }}</td>@endif
                                <td><x-team-chip :team="$r->team" /></td>
                                <td><x-badge :tone="$r->statusTone()">{{ $r->statusLabel() }}</x-badge></td>
                                <td class="text-sm muted nowrap">{{ $r->created_at->translatedFormat('d M Y') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
@endsection
