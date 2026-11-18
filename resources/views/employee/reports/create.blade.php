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
            <div class="step"><span class="n">1</span> Apa masalahnya?</div>
            @if ($teams->isNotEmpty())
                <div class="cat-search">
                    <label class="sr-only" for="cat-q">Cari jenis masalah</label>
                    <div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="cat-q" type="search" autocomplete="off" maxlength="60" placeholder="Ketik masalahnya, mis. wifi, AC, printer, kunci"></div>
                    <div class="cat-results" id="cat-results" hidden></div>
                </div>
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
    var oldCat = @json(old('category_id'));
    var oldAsset = @json(old('inventory_item_id'));
    var assetsUrl = @json(route('employee.reports.items'));
    var similarUrl = @json(route('employee.reports.similar'));

    var form = document.getElementById('report-form');
    var card = document.getElementById('detail-card');
    var cat = document.getElementById('category_id');
    var customWrap = document.getElementById('custom-wrap');
    var custom = document.getElementById('custom_category');
    var loc = document.getElementById('location_id');
    var assetWrap = document.getElementById('asset-wrap');
    var asset = document.getElementById('inventory_item_id');
    var current = {};
    var title = document.getElementById('title');
    var titleHint = document.getElementById('title-hint');
    var desc = document.getElementById('description');
    var defaultDesc = desc ? desc.getAttribute('placeholder') : '';
    var teamsInfo = @json($teams->mapWithKeys(fn ($t) => [$t->id => ['name' => $t->name, 'color' => $t->hex()]]));

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

    // ===== Cari jenis masalah: pilih kategori, regu ikut terpilih =====
    var q = document.getElementById('cat-q');
    var results = document.getElementById('cat-results');
    var index = [];
    Object.keys(map).forEach(function (tid) {
        (map[tid] || []).forEach(function (c) {
            if (c.custom) { return; }
            index.push({ team: tid, id: c.id, name: c.name, hay: norm(c.name + ' ' + c.keywords + ' ' + (teamsInfo[tid] ? teamsInfo[tid].name : '')) });
        });
    });
    function norm(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim(); }
    function esc(t) { var d = document.createElement('div'); d.textContent = String(t == null ? '' : t); return d.innerHTML.replace(/"/g, '&quot;'); }
    function search() {
        var words = norm(q.value).split(' ').filter(Boolean);
        if (!words.length) { results.hidden = true; results.innerHTML = ''; return; }
        var hits = index.map(function (it) {
            var score = 0;
            for (var i = 0; i < words.length; i++) {
                var w = words[i];
                if ((' ' + it.hay + ' ').indexOf(' ' + w) === -1) { return null; }
                score += norm(it.name).indexOf(w) === 0 ? 3 : (norm(it.name).indexOf(w) > -1 ? 2 : 1);
            }
            return { it: it, score: score };
        }).filter(Boolean).sort(function (a, b) { return b.score - a.score; }).slice(0, 6);

        if (!hits.length) {
            results.innerHTML = '<p class="cat-empty">Tidak ditemukan. Pilih regu di bawah, lalu kategori “Lainnya”.</p>';
        } else {
            results.innerHTML = hits.map(function (h) {
                var t = teamsInfo[h.it.team] || { name: '', color: '#64748B' };
                return '<button type="button" class="cat-hit" data-team="' + h.it.team + '" data-cat="' + h.it.id + '">'
                    + '<span class="ico" style="background:' + t.color + '">' + (h.it.name.charAt(0) || '?') + '</span>'
                    + '<span><span class="strong">' + esc(h.it.name) + '</span><span class="meta">Regu ' + esc(t.name) + '</span></span></button>';
            }).join('');
        }
        results.hidden = false;
    }
    if (q) {
        q.addEventListener('input', search);
        q.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); var first = results.querySelector('.cat-hit'); if (first) { first.click(); } }
        });
        results.addEventListener('click', function (e) {
            var b = e.target.closest('.cat-hit');
            if (!b) { return; }
            var radio = form.querySelector('input[name=team_id][value="' + b.dataset.team + '"]');
            if (radio) { radio.checked = true; }
            oldCat = b.dataset.cat; asset.value = '';
            renderCategories();
            q.value = ''; results.hidden = true; results.innerHTML = '';
            card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(function () { title.focus({ preventScroll: true }); }, 400);
        });
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
                + '&location_id=' + encodeURIComponent(loc.value || '') + '&inventory_item_id=' + encodeURIComponent(asset.value || '');
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
