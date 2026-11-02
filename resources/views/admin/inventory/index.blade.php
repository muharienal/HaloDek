@extends('layouts.app')
@section('title', 'Inventaris')
@section('subtitle', 'Barang, ruangan, dan item milik '.$team->name)

@section('content')
@php $q = fn (array $extra = []) => array_filter(['team_id' => $team->id, 'category' => $category?->id, 'status' => $status !== 'semua' ? $status : null, 'q' => $search] + $extra, fn ($v) => $v !== null && $v !== ''); @endphp

@if ($teams->count() > 1)
    <div class="tabs mb-4">
        @foreach ($teams as $t)
            <a class="tab {{ $t->id === $team->id ? 'active' : '' }}" href="{{ route('admin.inventory.index', ['team_id' => $t->id]) }}">{{ $t->name }}</a>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-head" style="flex-wrap:wrap">
        <div class="tabs">
            <a class="tab {{ ! $category ? 'active' : '' }}" href="{{ route('admin.inventory.index', ['team_id' => $team->id]) }}">Semua <span class="n">{{ $totalItems }}</span></a>
            @foreach ($categories as $c)
                <a class="tab {{ $category?->id === $c->id ? 'active' : '' }}" href="{{ route('admin.inventory.index', ['team_id' => $team->id, 'category' => $c->id]) }}">{{ $c->name }} <span class="n">{{ $c->items_count }}</span></a>
            @endforeach
            @if ($canLead)
                <button type="button" class="tab" data-modal-open="inv-category-modal" data-title="Tambah Kategori" data-action="{{ route('admin.inventory.categories.store') }}"><x-icon name="plus" :size="14" /> Kategori</button>
            @endif
        </div>
        <div class="flex gap-2 wrap">
            <a class="btn btn-secondary btn-sm" href="{{ route('admin.inventory.export', ['team_id' => $team->id]) }}"><x-icon name="download" :size="16" /> Ekspor</a>
            <button class="btn btn-secondary btn-sm" data-modal-open="import-modal"><x-icon name="upload" :size="16" /> Impor CSV</button>
            <button class="btn btn-primary btn-sm" data-modal-open="inv-item-modal" @if ($category) data-fill="{{ json_encode(['inventory_category_id' => $category->id, 'status' => 'tersedia', 'team_id' => $team->id]) }}" @endif><x-icon name="plus" :size="16" /> Tambah Item</button>
        </div>
    </div>

    @if ($category && $canLead)
        <div class="card-body flex items-center gap-3 wrap" style="padding-bottom:0">
            <div class="grow text-sm muted"><strong class="strong">{{ $category->name }}</strong>@if ($category->description) — {{ $category->description }}@endif</div>
            <button class="btn btn-secondary btn-sm" data-modal-open="inv-category-modal" data-title="Ubah Kategori" data-action="{{ route('admin.inventory.categories.update', $category) }}"
                    data-fill="{{ json_encode(['name' => $category->name, 'description' => $category->description]) }}"><x-icon name="edit" :size="15" /> Ubah Kategori</button>
            <form method="POST" action="{{ route('admin.inventory.categories.destroy', $category) }}" data-confirm="Hapus kategori &quot;{{ $category->name }}&quot;? Hanya bisa bila kategori kosong." data-confirm-title="Hapus kategori">
                @csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm"><x-icon name="trash" :size="15" /> Hapus Kategori</button></form>
        </div>
    @endif

    <form method="GET" class="card-body filters" style="padding-bottom:0" data-autosubmit>
        <input type="hidden" name="team_id" value="{{ $team->id }}">
        @if ($category)<input type="hidden" name="category" value="{{ $category->id }}">@endif
        <div class="field grow" style="min-width:220px"><label class="label" for="q">Cari</label><div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="q" name="q" value="{{ $search }}" placeholder="Kode, nama, pemegang, lokasi…" data-no-auto></div></div>
        <div class="field"><label class="label" for="status">Status</label>
            <select class="select" id="status" name="status">
                <option value="semua">Semua</option>
                @foreach (config('halodek.item_statuses') as $k => $s)<option value="{{ $k }}" @selected($status === $k)>{{ $s['label'] }}</option>@endforeach
                <option value="jatuh-tempo" @selected($status === 'jatuh-tempo')>Jatuh tempo ≤ 30 hari</option>
            </select></div>
    </form>

    @if ($items->isEmpty())
        <x-empty icon="tag" title="Belum ada item">{{ $categories->isEmpty() ? 'Buat kategori terlebih dahulu (mis. Laptop, AC, Ruangan), lalu tambahkan itemnya.' : 'Tidak ada item yang cocok dengan filter ini.' }}</x-empty>
    @else
        <div class="table-wrap mt-4"><table class="table">
            <thead><tr><th>Kode</th><th>Nama</th><th>Kategori</th><th>Status</th><th>Pemegang</th><th>Lokasi</th><th>Jatuh tempo</th><th></th></tr></thead>
            <tbody>@foreach ($items as $i)
                <tr>
                    <td class="mono"><a href="{{ route('admin.inventory.items.show', $i) }}">{{ $i->code }}</a></td>
                    <td class="strong">{{ $i->name }}</td>
                    <td>{{ $i->category->name }}</td>
                    <td><x-badge :tone="$i->statusTone()">{{ $i->statusLabel() }}</x-badge></td>
                    <td class="text-sm">{{ $i->holder->name ?? '–' }}</td>
                    <td class="text-sm">{{ $i->location->name ?? '–' }}</td>
                    <td class="nowrap text-sm {{ $i->dueState() === 'lewat' ? 'danger-text strong' : ($i->dueState() === 'segera' ? 'warn-text' : 'muted') }}">{{ $i->due_date?->translatedFormat('d M Y') ?? '–' }}</td>
                    <td><div class="actions">
                        <button class="btn btn-secondary btn-sm btn-icon" aria-label="Ubah" data-modal-open="inv-item-modal" data-title="Ubah Item" data-action="{{ route('admin.inventory.items.update', $i) }}"
                                data-fill="{{ json_encode(['team_id' => $i->team_id, 'inventory_category_id' => $i->inventory_category_id, 'code' => $i->code, 'name' => $i->name, 'status' => $i->status, 'holder_nik' => $i->holder->nik ?? '', 'location_id' => $i->location_id, 'acquired_at' => $i->acquired_at?->toDateString(), 'due_date' => $i->due_date?->toDateString(), 'note' => $i->note]) }}"><x-icon name="edit" :size="15" /></button>
                        @if ($canLead)
                            <form method="POST" action="{{ route('admin.inventory.items.destroy', $i) }}" data-confirm="Hapus {{ $i->code }} — {{ $i->name }}?" data-confirm-title="Hapus item">
                                @csrf @method('DELETE')<button class="btn btn-soft-danger btn-sm btn-icon" aria-label="Hapus"><x-icon name="trash" :size="15" /></button></form>
                        @endif
                    </div></td>
                </tr>
            @endforeach</tbody></table></div>
    @endif
    {{ $items->links() }}
</div>
@endsection

@push('modals')
<datalist id="holders">@foreach ($holders as $h)<option value="{{ $h->nik }}">{{ $h->name }}</option>@endforeach</datalist>

<x-form-modal id="inv-item-modal" create-title="Tambah Item" edit-title="Ubah Item" :action="route('admin.inventory.items.store')" size="lg">
    <div style="display:grid;gap:16px">
        <input type="hidden" name="team_id" value="{{ $team->id }}">
        <div class="form-grid">
            <x-select name="inventory_category_id" label="Kategori" required>
                <option value="">— Pilih kategori —</option>
                @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('inventory_category_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </x-select>
            <x-input name="code" label="Kode" required maxlength="50" placeholder="AST-0147" />
        </div>
        <x-input name="name" label="Nama" required maxlength="150" placeholder="Lenovo ThinkPad E14 / Ruang Rapat Utama" />
        <div class="form-grid">
            <x-select name="status" label="Status" required>
                @foreach (config('halodek.item_statuses') as $k => $s)<option value="{{ $k }}" @selected(old('status', 'tersedia') === $k)>{{ $s['label'] }}</option>@endforeach
            </x-select>
            <x-select name="location_id" label="Lokasi">
                <option value="">— Tidak ditentukan —</option>
                @foreach ($locations as $l)<option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->name }}</option>@endforeach
            </x-select>
        </div>
        <x-input name="holder_nik" label="Pemegang (NIK)" list="holders" maxlength="50" placeholder="Ketik NIK atau nama…" />
        <div class="form-grid">
            <x-input name="acquired_at" type="date" label="Tanggal perolehan" />
            <x-input name="due_date" type="date" label="Jatuh tempo (garansi / servis / izin)" />
        </div>
        <x-textarea name="note" label="Catatan" rows="2" maxlength="1000" />
    </div>
</x-form-modal>

@if ($canLead)
    <x-form-modal id="inv-category-modal" create-title="Tambah Kategori" edit-title="Ubah Kategori" :action="route('admin.inventory.categories.store')">
        <div style="display:grid;gap:16px">
            <input type="hidden" name="team_id" value="{{ $team->id }}">
            <x-input name="name" label="Nama kategori" required maxlength="100" placeholder="Laptop, AC, Ruangan, Lisensi…" />
            <x-input name="description" label="Keterangan" maxlength="255" />
        </div>
    </x-form-modal>
@endif

<x-import-modal :action="route('admin.inventory.import', ['team_id' => $team->id])" :template="route('admin.inventory.template')" title="Impor Inventaris dari CSV" columns="category, code, name, status, holder_nik, location, acquired_at, due_date, note" />
@endpush
