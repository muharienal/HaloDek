<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 — Sesi Berakhir</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}">
</head>
<body>
<div class="err-wrap">
    <div>
        <div class="err-code">419</div>
        <h1 class="text-xl mt-3">Sesi Berakhir</h1>
        <p class="muted mt-2" style="max-width:420px">{{ (isset($exception) && $exception->getMessage() && ! in_array(419, [404, 419, 429, 500, 503])) ? $exception->getMessage() : 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.' }}</p>
        <div class="flex gap-3 justify-center mt-5">
            <a href="{{ url('/') }}" class="btn btn-primary">Ke Beranda</a>
            <a href="{{ url()->previous() === url()->current() ? url('/') : url()->previous() }}" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>
