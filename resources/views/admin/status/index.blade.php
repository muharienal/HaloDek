@extends('layouts.app')
@section('title', 'Status Sistem')
@section('subtitle', 'Periksa kesehatan HaloDek tanpa membuka terminal')

@php
    $tone = ['ok' => 'green', 'warn' => 'amber', 'error' => 'red', 'info' => 'gray'];
    $word = ['ok' => 'Baik', 'warn' => 'Perhatian', 'error' => 'Masalah', 'info' => 'Info'];
@endphp

@section('content')
@if ($errors_count > 0)
    <div class="alert alert-error"><x-icon name="alert" :size="18" /><div><strong>{{ $errors_count }} masalah perlu ditangani.</strong> Lihat baris berlabel Masalah di bawah. Sebagian fitur mungkin tidak berjalan sampai diperbaiki.</div></div>
@elseif ($warn_count > 0)
    <div class="alert alert-warning"><x-icon name="alert" :size="18" /><div>Sistem berjalan, dengan {{ $warn_count }} hal yang perlu diperhatikan.</div></div>
@else
    <div class="alert alert-success"><x-icon name="check-circle" :size="18" /><div>Semua pemeriksaan baik.</div></div>
@endif

<div class="status-grid">
    @foreach ($sections as $title => $rows)
        <section class="card">
            <div class="card-head"><h2>{{ $title }}</h2>
                @if ($title === 'Koneksi ke aplikasi lain')
                    <div class="flex gap-2" style="flex-wrap:wrap">
                        <a class="btn btn-secondary btn-sm" href="{{ route('admin.wa.edit') }}">Atur WhatsApp</a>
                        @if ($canTestWa)
                            <form method="POST" action="{{ route('admin.status.wa-test') }}" data-confirm="Kirim pesan uji WhatsApp ke nomor di profil Anda?">@csrf
                                <button class="btn btn-secondary btn-sm">Kirim WA Uji</button></form>
                        @endif
                    </div>
                @endif
            </div>
            <ul class="status-list">
                @foreach ($rows as $r)
                    <li>
                        <span class="status-label">{{ $r['label'] }}</span>
                        <x-badge :tone="$tone[$r['state']] ?? 'gray'">{{ $word[$r['state']] ?? $r['state'] }}</x-badge>
                        <p class="status-detail">{{ $r['detail'] }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>

<section class="card mt-4">
    <div class="card-head"><h2>Error terakhir</h2><span class="text-sm muted">dari storage/logs</span></div>
    @if (empty($recent))
        <x-empty icon="check-circle" title="Tidak ada error tercatat">Log tidak mencatat error belakangan ini.</x-empty>
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Waktu</th><th>Pesan</th></tr></thead>
            <tbody>@foreach ($recent as $e)
                <tr><td class="nowrap text-sm">{{ \Illuminate\Support\Carbon::parse($e['time'])->translatedFormat('d M Y H:i') }}</td>
                    <td class="text-sm mono-wrap">{{ $e['message'] }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <p class="hint card-foot" style="margin:0">Pesan yang sama berulang? Kirim baris teratas ke tim IT. Error lama tetap tampil di sini sampai log dibersihkan.</p>
    @endif
</section>
@endsection
