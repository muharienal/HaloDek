@extends('layouts.app')
@section('title', 'Lokasi & Ruangan')
@section('subtitle', 'Daftar ruangan dan area yang dipakai untuk pelaporan serta penempatan aset')

@section('content')
<div class="card">
    <div class="card-head" style="flex-wrap:wrap">
        <form method="GET" class="flex gap-2 grow" style="max-width:420px"><div class="input-icon grow"><x-icon name="search" :size="16" /><input class="input" name="q" value="{{ $search }}" placeholder="Cari lokasi…"></div><button class="btn btn-secondary">Cari</button></form>
        <div class="flex gap-2">
            <button class="btn btn-secondary btn-sm" data-modal-open="import-modal"><x-icon name="upload" :size="16" /> Impor CSV</button>
            <button class="btn btn-primary btn-sm" data-modal-open="location-modal"><x-icon name="plus" :size="16" /> Tambah Lokasi</button>
        </div>
    </div>
    @if ($locations->isEmpty())
        <x-empty icon="pin" title="Belum ada lokasi" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Nama</th><th>Gedung / Lantai</th><th>Penanggung jawab</th><th>Inventaris</th><th>Laporan</th><th></th></tr></thead>
            <tbody>@foreach ($locations as $l)
                <tr>
                    <td class="strong">{{ $l->name }}@if ($l->note)<div class="text-xs muted">{{ \Illuminate\Support\Str::limit($l->note, 70) }}</div>@endif</td>
                    <td>{{ collect([$l->building, $l->floor ? 'Lt. '.$l->floor : null])->filter()->implode(' · ') ?: '–' }}</td>
                    <td>{{ $l->pic_name ?: '–' }}</td><td>{{ $l->items_count }}</td><td>{{ $l->reports_count }}</td>
                    <td><div class="actions">
                        <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah" data-modal-open="location-modal" data-title="Ubah Lokasi" data-action="{{ route('admin.locations.update', $l) }}"
                                data-fill="{{ json_encode(['name' => $l->name, 'building' => $l->building, 'floor' => $l->floor, 'pic_name' => $l->pic_name, 'note' => $l->note]) }}"><x-icon name="edit" :size="15" /></button>
                        <form method="POST" action="{{ route('admin.locations.destroy', $l) }}" data-confirm="Hapus lokasi {{ $l->name }}?" data-confirm-title="Hapus lokasi">@csrf @method('DELETE')
                            <button class="btn btn-soft-danger btn-sm btn-icon" aria-label="Hapus"><x-icon name="trash" :size="15" /></button></form>
                    </div></td>
                </tr>
            @endforeach</tbody></table></div>
    @endif
    {{ $locations->links() }}
</div>
@endsection

@push('modals')
    <x-form-modal id="location-modal" create-title="Tambah Lokasi" edit-title="Ubah Lokasi" :action="route('admin.locations.store')">
        <div style="display:grid;gap:16px">
            <x-input name="name" label="Nama ruangan / area" required maxlength="150" placeholder="Ruang Rapat Utama" />
            <div class="form-grid"><x-input name="building" label="Gedung" maxlength="100" /><x-input name="floor" label="Lantai" maxlength="30" /></div>
            <x-input name="pic_name" label="Penanggung jawab ruangan" maxlength="150" />
            <x-textarea name="note" label="Catatan" rows="2" maxlength="1000" />
        </div>
    </x-form-modal>
    <x-import-modal :action="route('admin.locations.import')" :template="route('admin.locations.template')" title="Impor Lokasi dari CSV" columns="name, building, floor, pic_name, note" />
@endpush
