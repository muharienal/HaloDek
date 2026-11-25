<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiriman Terlalu Besar · {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
</head>
<body>
<div class="err-wrap">
    <div>
        <div class="err-code">413</div>
        <h1 class="text-xl mt-3">Kiriman terlalu besar</h1>
        <p class="muted mt-2" style="max-width:420px">Ukuran foto atau berkas melebihi batas server ({{ ini_get('post_max_size') }}). Kembali ke formulir, kurangi jumlah atau ukuran foto, lalu kirim ulang.</p>
        <div class="flex gap-3 justify-center mt-5">
            <a href="{{ url('/') }}" class="btn btn-primary">Ke Beranda</a>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
