{{--
    Kolom "Apa masalahnya?": ketik "wifi", "AC", dst. -> pilih kategori, regu ikut terpilih.
    mode="link" : hasil berupa tautan ke form lapor (dipakai di dasbor).
    mode="pick" : hasil memicu event "hd:category-pick" (dipakai di form lapor).
    $teams: koleksi Team dengan relasi categories (aktif) sudah dimuat.
--}}
@props(['teams', 'mode' => 'link', 'id' => 'cat-q', 'emptyText' => 'Tidak ditemukan. Pilih regu di bawah, lalu kategori “Lainnya”.'])
@php
    $index = $teams->flatMap(fn ($t) => $t->categories->where('allow_custom', false)->map(fn ($c) => [
        't' => $t->id, 's' => $t->slug, 'tn' => $t->name, 'tc' => $t->hex(),
        'id' => $c->id, 'n' => $c->name, 'k' => (string) $c->keywords,
    ]))->values();
@endphp
<div class="cat-search" data-cat-search data-mode="{{ $mode }}" data-create-url="{{ route('employee.reports.create') }}" data-empty="{{ $emptyText }}">
    <script type="application/json" data-cat-index>@json($index)</script>
    <label class="sr-only" for="{{ $id }}">Cari jenis masalah</label>
    <div class="input-icon"><x-icon name="search" :size="16" /><input class="input" id="{{ $id }}" type="search" autocomplete="off" maxlength="60" placeholder="Ketik masalahnya, mis. wifi, AC, printer, kunci" data-cat-q></div>
    <div class="cat-results" data-cat-results hidden></div>
</div>
