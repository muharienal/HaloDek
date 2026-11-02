@extends('layouts.app')
@section('title', 'Ajukan Laporan')
@section('subtitle', 'Pilih regu yang sesuai, lalu jelaskan masalah atau kebutuhan Anda')

@section('content')
@php $att = config('halodek.attachments'); @endphp
<form method="POST" action="{{ route('employee.reports.store') }}" enctype="multipart/form-data" id="report-form" style="max-width:860px;margin:0 auto">
    @csrf

    {{-- ===== LANGKAH 1: PILIH REGU ===== --}}
    <div class="card">
        <div class="card-body">
            <div class="step"><span class="n">1</span> Pilih regu tujuan</div>
            @if ($teams->isEmpty())
                <x-empty icon="inbox" title="Belum ada regu aktif">Hubungi Superadmin untuk mengaktifkan regu penerima laporan.</x-empty>
            @else
                <div class="choice-grid two">
                    @foreach ($teams as $team)
                        <label class="choice">
                            <input type="radio" name="team_id" value="{{ $team->id }}" required @checked(old('team_id', $selectedTeam?->id) == $team->id)>
                            <span class="choice-box">
                                <span class="ico" style="background: {{ $team->hex() }}"><x-icon :name="$team->icon" :size="22" /></span>
                                <span>
                                    <span class="strong" style="display:block">{{ $team->name }}</span>
                                    <span class="text-sm muted">{{ $team->description }}</span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('team_id')<p class="error mt-2">{{ $message }}</p>@enderror
            @endif
        </div>
    </div>

    {{-- ===== LANGKAH 2: DETAIL ===== --}}
    <div class="card mt-5" id="detail-card" hidden>
        <div class="card-body" style="display:grid;gap:18px">
            <div class="step" style="margin-bottom:0"><span class="n">2</span> Detail laporan</div>

            <div class="form-grid">
                <div class="field">
                    <label class="label" for="category_id">Kategori <span class="req">*</span></label>
                    <select class="select {{ $errors->has('category_id') ? 'is-invalid' : '' }}" name="category_id" id="category_id" required></select>
                    @error('category_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field" id="custom-wrap" hidden>
                    <label class="label" for="custom_category">Sebutkan kategori <span class="req">*</span></label>
                    <input class="input {{ $errors->has('custom_category') ? 'is-invalid' : '' }}" name="custom_category" id="custom_category" maxlength="150" value="{{ old('custom_category') }}" placeholder="Mis. Perbaikan pagar">
                    @error('custom_category')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <span class="label">Tingkat urgensi <span class="req">*</span></span>
                <div class="flex gap-2 wrap">
                    @foreach (config('halodek.priorities') as $key => $p)
                        <label class="tab" style="cursor:pointer">
                            <input type="radio" name="priority" value="{{ $key }}" class="sr-only" @checked(old('priority', 'sedang') === $key)>
                            <span class="badge plain tone-{{ $p['tone'] }}">{{ $p['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="label" for="location_id">Lokasi / ruangan</label>
                    <select class="select" name="location_id" id="location_id">
                        <option value="">— Tidak spesifik —</option>
                        @foreach ($locations as $loc)<option value="{{ $loc->id }}" @selected(old('location_id') == $loc->id)>{{ $loc->name }}{{ $loc->building ? ' ('.$loc->building.')' : '' }}</option>@endforeach
                    </select>
                    @error('location_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field" id="asset-wrap" hidden>
                    <label class="label" for="inventory_item_id">Barang / ruangan terkait</label>
                    <select class="select" name="inventory_item_id" id="inventory_item_id"><option value="">— Tidak terkait barang/ruangan —</option></select>
                    @error('inventory_item_id')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="field">
                <label class="label" for="title">Judul <span class="req">*</span></label>
                <input class="input {{ $errors->has('title') ? 'is-invalid' : '' }}" name="title" id="title" maxlength="200" required value="{{ old('title') }}" placeholder="Ringkas dan jelas, mis. Laptop sering hang saat membuka Excel">
                @error('title')<span class="error">{{ $message }}</span>@enderror
            </div>

            <x-textarea name="description" label="Deskripsi" required maxlength="3000" rows="6" counter placeholder="Ceritakan secara rinci…" />

            <div class="field">
                <span class="label">Lampiran (opsional)</span>
                <label class="drop" for="photos" style="display:block">
                    <x-icon name="upload" :size="24" />
                    <p class="strong mt-2">Klik untuk memilih foto</p>
                    <p class="hint">Maks. {{ $att['max_files'] }} foto · JPG, PNG, WEBP</p>
                    <input type="file" class="sr-only" name="photos[]" id="photos" multiple accept="image/jpeg,image/png,image/webp" data-file-input data-max-files="{{ $att['max_files'] }}" data-max-kb="{{ $att['max_kb'] }}" data-file-list="photo-list" data-file-error="photo-err">
                </label>
                <div class="file-list" id="photo-list"></div>
                <p class="error" id="photo-err" hidden></p>
                @error('photos')<span class="error">{{ $message }}</span>@enderror
                @error('photos.*')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="card-foot flex justify-end gap-3">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">Batal</a>
            <button class="btn btn-primary"><x-icon name="arrow-right" :size="16" /> Kirim Laporan</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var map = @json($categoryMap);
    var oldCat = @json(old('category_id'));
    var oldAsset = @json(old('inventory_item_id'));
    var assetsUrl = @json(route('employee.reports.items'));

    var form = document.getElementById('report-form');
    var card = document.getElementById('detail-card');
    var cat = document.getElementById('category_id');
    var customWrap = document.getElementById('custom-wrap');
    var custom = document.getElementById('custom_category');
    var loc = document.getElementById('location_id');
    var assetWrap = document.getElementById('asset-wrap');
    var asset = document.getElementById('inventory_item_id');
    var current = {};

    function teamId() { var r = form.querySelector('input[name=team_id]:checked'); return r ? r.value : null; }

    function renderCategories() {
        var id = teamId();
        card.hidden = !id;
        if (!id) { return; }
        var list = map[id] || [];
        cat.innerHTML = '<option value="">— Pilih kategori —</option>';
        current = {};
        list.forEach(function (c) {
            current[c.id] = c;
            var o = document.createElement('option');
            o.value = c.id; o.textContent = c.name;
            if (String(oldCat) === String(c.id)) { o.selected = true; }
            cat.appendChild(o);
        });
        toggleCustom();
        loadAssets();
    }

    function toggleCustom() {
        var c = current[cat.value];
        var need = !!(c && c.custom);
        customWrap.hidden = !need;
        custom.required = need;
    }

    function loadAssets() {
        var id = teamId();
        if (!id) { return; }
        var url = assetsUrl + '?team_id=' + encodeURIComponent(id) + '&location_id=' + encodeURIComponent(loc.value || '');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (items) {
                var keep = asset.value || oldAsset;
                asset.innerHTML = '<option value="">— Tidak terkait barang/ruangan —</option>';
                items.forEach(function (a) {
                    var o = document.createElement('option');
                    o.value = a.id; o.textContent = a.label;
                    if (String(keep) === String(a.id)) { o.selected = true; }
                    asset.appendChild(o);
                });
                assetWrap.hidden = items.length === 0;
                oldAsset = null;
            })
            .catch(function () { assetWrap.hidden = true; });
    }

    form.addEventListener('change', function (e) {
        if (e.target.name === 'team_id') { oldCat = null; asset.value = ''; renderCategories(); card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        if (e.target === cat) { toggleCustom(); }
        if (e.target === loc) { asset.value = ''; loadAssets(); }
        if (e.target.name === 'priority') { highlight(); }
    });

    function highlight() {
        form.querySelectorAll('input[name=priority]').forEach(function (r) {
            var lab = r.closest('label');
            lab.classList.toggle('picked', r.checked);
        });
    }

    renderCategories();
    highlight();
})();
</script>
@endpush
