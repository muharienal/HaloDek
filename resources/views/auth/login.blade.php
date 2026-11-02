<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
    <noscript><style>.auth-card [data-admin-only][hidden]{display:flex!important}.auth-tabs{display:none}</style></noscript>
</head>
<body>
@php
    $mode = old('mode') === 'admin' || $errors->has('password') ? 'admin' : 'karyawan';
@endphp
<div class="auth-bg">
    <aside class="auth-hero">
        <div class="auth-brand">
            <span class="co-tile"><img src="{{ asset('images/logo-pcs.png') }}" alt="{{ config('halodek.company') }}"></span>
            <span class="auth-sep"></span>
            <span class="logo"><x-icon name="halodek" :size="30" /></span>
            <span class="auth-name">{{ config('halodek.name') }}</span>
        </div>
        <h1 class="auth-title">Satu pintu untuk semua kendala kerja Anda.</h1>
        <p class="auth-desc">Layanan pengaduan dan pelaporan terpadu Bidang Umum dan IT. Sampaikan kendala Anda, pantau progresnya, sampai tuntas.</p>
    </aside>

    <main class="auth-panel">
        <section class="auth-card">
            <h2 class="auth-h">Selamat datang</h2>
            <p class="auth-sub muted">Masuk untuk melanjutkan ke {{ config('halodek.name') }}.</p>

            <div class="auth-tabs" role="tablist" aria-label="Jenis akun">
                <button type="button" class="auth-tab {{ $mode === 'karyawan' ? 'on' : '' }}" data-mode="karyawan" role="tab">Karyawan</button>
                <button type="button" class="auth-tab {{ $mode === 'admin' ? 'on' : '' }}" data-mode="admin" role="tab">Admin</button>
            </div>

            @if (session('warning'))<div class="alert alert-warning mt-4" style="margin-bottom:0">{{ session('warning') }}</div>@endif

            <form method="POST" action="{{ route('login') }}" id="login-form" class="auth-form" autocomplete="on">
                @csrf
                <input type="hidden" name="mode" id="mode" value="{{ $mode }}">

                <div class="field">
                    <label class="label" for="nik">NIK</label>
                    <div class="input-icon">
                        <x-icon name="user" :size="18" />
                        <input type="text" id="nik" name="nik" value="{{ old('nik') }}" class="input {{ $errors->has('nik') ? 'is-invalid' : '' }}" placeholder="Masukkan NIK Anda" required autofocus autocomplete="username" inputmode="text">
                    </div>
                    @error('nik')<span class="error">{{ $message }}</span>@enderror
                </div>

                <div class="field" data-admin-only @if ($mode !== 'admin') hidden @endif>
                    <label class="label" for="password">Password</label>
                    <div class="input-icon input-wrap">
                        <x-icon name="lock" :size="18" />
                        <input type="password" id="password" name="password" class="input {{ $errors->has('password') ? 'is-invalid' : '' }}" placeholder="Masukkan password" autocomplete="current-password">
                        <button type="button" class="input-eye" id="toggle-pw" aria-label="Tampilkan password"><x-icon name="eye" :size="18" /></button>
                    </div>
                    @error('password')<span class="error">{{ $message }}</span>@enderror
                </div>

                <label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya</label>

                <button type="submit" class="btn btn-cta btn-block btn-lg">Masuk <x-icon name="arrow-right" :size="18" /></button>
            </form>
        </section>
        <p class="auth-copy">© {{ date('Y') }} IT {{ config('halodek.company') }}</p>
    </main>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
    var tabs = document.querySelectorAll('.auth-tab'),
        mode = document.getElementById('mode'),
        pw = document.getElementById('password'),
        adminOnly = document.querySelectorAll('[data-admin-only]'),
        nik = document.getElementById('nik'),
        form = document.getElementById('login-form');

    function apply(m) {
        var admin = m === 'admin';
        mode.value = m;
        tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.mode === m); });
        adminOnly.forEach(function (el) { el.hidden = !admin; });
        pw.required = admin;
        if (!admin) { pw.value = ''; pw.type = 'password'; }
    }
    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            apply(t.dataset.mode);
            (t.dataset.mode === 'admin' && nik.value ? pw : nik).focus();
        });
    });
    apply(mode.value === 'admin' ? 'admin' : 'karyawan');

    document.getElementById('toggle-pw').addEventListener('click', function () {
        pw.type = pw.type === 'password' ? 'text' : 'password';
    });

    // Cegah 419: halaman dari cache (tombol Back) atau yang terlalu lama terbuka dimuat ulang agar token CSRF-nya baru.
    var loadedAt = Date.now();
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { location.reload(); return; }
        form.querySelector('button[type=submit]').disabled = false;
    });
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && Date.now() - loadedAt > 15 * 60 * 1000) { location.reload(); }
    });
    form.addEventListener('submit', function (e) {
        var b = form.querySelector('button[type=submit]');
        if (b.disabled) { e.preventDefault(); return; }
        setTimeout(function () { b.disabled = true; }, 0);
    });
})();
</script>
</body>
</html>
