<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 — Sedang Dalam Pemeliharaan</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}">
</head>
<body>
<div class="err-wrap">
    <div>
        <div class="err-code">503</div>
        <h1 class="text-xl mt-3">Sedang Dalam Pemeliharaan</h1>
        <p class="muted mt-2" style="max-width:420px">{{ (isset($exception) && $exception->getMessage() && ! in_array(503, [404, 419, 429, 500, 503])) ? $exception->getMessage() : 'Sistem sedang diperbarui. Silakan kembali beberapa saat lagi.' }}</p>
        <div class="flex gap-3 justify-center mt-5">
            <a href="{{ url('/') }}" class="btn btn-primary">Ke Beranda</a>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
