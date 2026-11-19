@extends('layouts.app')
@section('title', 'Ajukan Laporan')
@section('subtitle', 'Pilih regu yang sesuai, lalu jelaskan masalah atau kebutuhan Anda')

@section('content')
@php $att = config('halodek.attachments'); @endphp
@if ($scanned)
    <div class="alert alert-info scan-banner mb-4"><x-icon name="qr" :size="20" />
        <div><strong>Melaporkan barang {{ $scanned['code'] }}</strong> · {{ $scanned['name'] }}@if (! empty($scanned['location']['name'])) · {{ $scanned['location']['name'] }}@endif
            <div class="text-sm muted">Regu, lokasi, dan barang sudah terisi dari QR. Tinggal jelaskan masalahnya.</div></div>
    </div>
@endif
<form method="POST" action="{{ route('employee.reports.store') }}" enctype="multipart/form-data" id="report-form" style="max-width:860px;margin:0 auto">
    @csrf

    {{-- ===== LANGKAH 1: PILIH REGU ===== --}}
    <div class="card">
        <div class="card-body">
            <div class="step"><span class="n">1</span> Apa masalahnya?</div>
            @if ($teams->isNotEmpty())
                <x-category-search :teams="$teams" mode="pick" />
                <div class="or-line">atau pilih regu</div>
            @endif
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
                <div class="choice-grid prio">
                    @foreach (config('halodek.priorities') as $key => $p)
                        <label class="choice">
                            <input type="radio" name="priority" value="{{ $key }}" @checked(old('priority', 'sedang') === $key)>
                            <span class="choice-box">
                                <span class="strong"><span class="pdot tone-{{ $p['tone'] }}"></span>{{ $p['label'] }}</span>
                                <span class="text-xs muted">{{ $p['hint'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('priority')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="form-grid">
                {{-- Lokasi dari aplikasi Inventaris; disembunyikan bila Inventaris tidak terhubung. --}}
                <div class="field" @if (empty($locations)) hidden @endif>
                    <label class="label" for="location_id">Lokasi / ruangan</label>
                    <select class="select" name="location_id" id="location_id">
                        <option value="">— Tidak spesifik —</option>
                        @foreach ($locations as $loc)<option value="{{ $loc['id'] }}" @selected(old('location_id', request('lokasi')) == $loc['id'])>{{ $loc['label'] }}</option>@endforeach
                    </select>
                    @error('location_id')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field" id="asset-wrap" hidden>
                    <label class="label" for="asset_id">Barang terkait</label>
                    <select class="select" name="asset_id" id="asset_id"><option value="">— Tidak terkait barang tertentu —</option></select>
                    @error('asset_id')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="alert alert-warning similar-box" id="similar" hidden role="status" aria-live="polite"></div>

            <div class="field">
                <label class="label" for="title">Judul <span class="muted" style="font-weight:400">(opsional)</span></label>
                <input class="input {{ $errors->has('title') ? 'is-invalid' : '' }}" name="title" id="title" maxlength="200" value="{{ old('title') }}" placeholder="Kosongkan untuk judul otomatis">
                <span class="hint" id="title-hint">Jika dikosongkan, judul dibuat otomatis dari kategori dan lokasi.</span>
                @error('title')<span class="error">{{ $message }}</span>@enderror
            </div>

            <x-textarea name="description" label="Deskripsi" required maxlength="3000" rows="6" counter placeholder="Ceritakan masalahnya: apa yang terjadi, di mana, dan sejak kapan." />

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
    var oldCat = @json(old('category_id', request('kategori')));
    var oldAsset = @json(old('asset_id', $scanned['id'] ?? null));
    var assetsUrl = @json(route('employee.reports.items'));
    var similarUrl = @json(route('employee.reports.similar'));

    var form = document.getElementById('report-form');
    var card = document.getElementById('detail-card');
    var cat = document.getElementById('category_id');
    var customWrap = document.getElementById('custom-wrap');
    var custom = document.getElementById('custom_category');
    var loc = document.getElementById('location_id');
    var assetWrap = document.getElementById('asset-wrap');
    var asset = document.getElementById('asset_id');
    var current = {};
    var title = document.getElementById('title');
    var titleHint = document.getElementById('title-hint');
    var desc = document.getElementById('description');
    var defaultDesc = desc ? desc.getAttribute('placeholder') : '';

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
        checkSimilar();
    }

    function toggleCustom() {
        var c = current[cat.value];
        var need = !!(c && c.custom);
        customWrap.hidden = !need;
        custom.required = need;
        if (desc) { desc.setAttribute('placeholder', (c && c.hint) ? c.hint : defaultDesc); }
        autoTitle();
    }

    // Pratinjau judul otomatis: "Kategori – Lokasi" (sama seperti di server).
    function autoTitle() {
        var c = current[cat.value];
        var name = c ? ((c.custom && custom.value.trim()) ? custom.value.trim() : c.name) : '';
        var l = loc.options[loc.selectedIndex];
        var where = loc.value && l ? l.textContent.replace(/\s*\(.*\)\s*$/, '').trim() : '';
        var auto = name ? name + (where ? ' – ' + where : '') : '';
        title.setAttribute('placeholder', auto ? auto : 'Kosongkan untuk judul otomatis');
        titleHint.textContent = auto ? 'Jika dikosongkan, judulnya: “' + auto + '”.' : 'Jika dikosongkan, judul dibuat otomatis dari kategori dan lokasi.';
    }

    // ===== Cari jenis masalah (komponen x-category-search): regu + kategori ikut terpilih =====
    form.addEventListener('hd:category-pick', function (e) {
        var radio = form.querySelector('input[name=team_id][value="' + e.detail.team + '"]');
        if (radio) { radio.checked = true; }
        oldCat = e.detail.cat; asset.value = '';
        renderCategories();
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(function () { title.focus({ preventScroll: true }); }, 400);
    });
    function esc(t) { var d = document.createElement('div'); d.textContent = String(t == null ? '' : t); return d.innerHTML.replace(/"/g, '&quot;'); }

    function loadAssets() {
        var id = teamId();
        if (!id) { return; }
        var url = assetsUrl + '?team_id=' + encodeURIComponent(id) + '&location_id=' + encodeURIComponent(loc.value || '');
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (items) {
                var keep = asset.value || oldAsset;
                asset.innerHTML = '<option value="">— Tidak terkait barang tertentu —</option>';
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

    // ===== Peringatan laporan serupa yang masih aktif =====
    var similar = document.getElementById('similar');
    var simTimer = null, simSeq = 0;
    function checkSimilar() {
        clearTimeout(simTimer);
        simTimer = setTimeout(function () {
            var id = teamId();
            if (!id || !cat.value || (!loc.value && !asset.value)) { similar.hidden = true; return; }
            var seq = ++simSeq;
            var url = similarUrl + '?team_id=' + encodeURIComponent(id) + '&category_id=' + encodeURIComponent(cat.value)
                + '&location_id=' + encodeURIComponent(loc.value || '') + '&asset_id=' + encodeURIComponent(asset.value || '');
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (rows) {
                    if (seq !== simSeq) { return; }
                    if (!rows.length) { similar.hidden = true; similar.innerHTML = ''; return; }
                    similar.innerHTML = '<div style="flex:1;min-width:0"><strong>Sudah ada laporan aktif untuk ini.</strong> '
                        + 'Jika masalahnya sama, cukup pantau statusnya. Jika berbeda, lanjutkan mengisi.'
                        + '<ul class="similar-list">' + rows.map(function (r) {
                            return '<li><span class="mono strong">' + esc(r.number) + '</span> · ' + esc(r.label) + ' · <span class="strong">' + esc(r.status) + '</span> <span class="muted">(' + esc(r.age) + ')</span>'
                                + (r.mine ? ' <span class="muted">laporan Anda</span>' : '')
                                + ' <a href="' + esc(r.url) + '" target="_blank" rel="noopener">Lihat status</a></li>';
                        }).join('') + '</ul></div>';
                    similar.hidden = false;
                })
                .catch(function () { similar.hidden = true; });
        }, 250);
    }

    form.addEventListener('change', function (e) {
        if (e.target.name === 'team_id') { oldCat = null; asset.value = ''; renderCategories(); card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        if (e.target === cat) { toggleCustom(); checkSimilar(); }
        if (e.target === loc) { asset.value = ''; loadAssets(); autoTitle(); checkSimilar(); }
        if (e.target === asset) { checkSimilar(); }
    });
    custom.addEventListener('input', autoTitle);

    renderCategories();
})();
</script>
@endpush
