<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('halodek.name') }}</title>
    @include('partials.head-icons')
    <link rel="stylesheet" href="{{ asset('css/halodek.css') }}?v={{ filemtime(public_path('css/halodek.css')) }}">
    @stack('head')
</head>
<body>
@php
    $me = auth()->user();
    $isAdmin = $me->isAdmin();
    $on = fn (string ...$patterns) => request()->routeIs(...$patterns);
    $act = fn (bool $cond) => $cond ? 'active' : '';
    $isDash = $on('dashboard');
    $mineTasks = $on('admin.reports.index') && request('assigned') === 'me';
    $queueView = $on('admin.reports.index') && request('assigned') === 'none';
    $canLocations = $me->isSuperadmin() || $me->isHead();

    $hour = (int) now()->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = \Illuminate\Support\Str::words($me->name, 1, '');
@endphp

<div class="backdrop"></div>

<header class="topnav-wrap">
    <div class="topnav">
        <a href="{{ route('dashboard') }}" class="lockup" aria-label="{{ config('halodek.name') }}">
            <span class="co-tile"><img src="{{ asset('images/logo-pcs.png') }}" alt="{{ config('halodek.company') }}"></span>
            <span class="lockup-sep"></span>
            <span class="lockup-mark"><x-icon name="halodek" :size="24" /></span>
            <span class="lockup-name">{{ config('halodek.name') }}</span>
        </a>

        <nav class="topnav-links" aria-label="Menu utama">
            <a href="{{ route('dashboard') }}" class="nav-link {{ $act($isDash) }}">Dashboard</a>

            @if ($isAdmin)
                <div class="dropdown nav-dd">
                    <button type="button" class="nav-link {{ $act($on('admin.reports.*', 'admin.teams.*')) }}" data-dropdown-toggle aria-expanded="false">
                        Kelola Laporan
                        @if ($pendingCount > 0)<span class="count">{{ $pendingCount }}</span>@endif
                        <x-icon name="chevron-down" :size="13" />
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item {{ $act($on('admin.reports.*') && ! $mineTasks && ! $queueView) }}" href="{{ route('admin.reports.index') }}">Semua Laporan</a>
                        <a class="dropdown-item {{ $act($queueView) }}" href="{{ route('admin.reports.index', ['assigned' => 'none', 'status' => 'aktif']) }}">Antrean Regu @if ($pendingCount > 0)<span class="count ml-auto">{{ $pendingCount }}</span>@endif</a>
                        <a class="dropdown-item {{ $act($mineTasks) }}" href="{{ route('admin.reports.index', ['assigned' => 'me', 'status' => 'aktif']) }}">Tugas Saya</a>
                        <a class="dropdown-item {{ $act($on('admin.teams.*')) }}" href="{{ route('admin.teams.index') }}">{{ $me->isSuperadmin() ? 'Regu & Kategori' : 'Regu Saya' }}</a>
                    </div>
                </div>

                @if ($canLocations)
                    <div class="dropdown nav-dd">
                        <button type="button" class="nav-link {{ $act($on('admin.inventory.*', 'admin.locations.*')) }}" data-dropdown-toggle aria-expanded="false">
                            Inventaris <x-icon name="chevron-down" :size="13" />
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item {{ $act($on('admin.inventory.*')) }}" href="{{ route('admin.inventory.index') }}">Inventaris Regu</a>
                            <a class="dropdown-item {{ $act($on('admin.locations.*')) }}" href="{{ route('admin.locations.index') }}">Lokasi &amp; Ruangan</a>
                        </div>
                    </div>
                @else
                    <a href="{{ route('admin.inventory.index') }}" class="nav-link {{ $act($on('admin.inventory.*')) }}">Inventaris</a>
                @endif
            @else
                <a href="{{ route('employee.reports.index') }}" class="nav-link {{ $act($on('employee.reports.index', 'employee.reports.show')) }}">Laporan Saya</a>
            @endif

            <a href="{{ route('announcements.index') }}" class="nav-link {{ $act($on('announcements.*')) }}">Pengumuman</a>

            @if ($me->isSuperadmin())
                <div class="dropdown nav-dd">
                    <button type="button" class="nav-link {{ $act($on('admin.users.*', 'admin.audit.*')) }}" data-dropdown-toggle aria-expanded="false">
                        Administrasi <x-icon name="chevron-down" :size="13" />
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item {{ $act($on('admin.users.*')) }}" href="{{ route('admin.users.index') }}">Manajemen Pengguna</a>
                        <a class="dropdown-item {{ $act($on('admin.audit.*')) }}" href="{{ route('admin.audit.index') }}">Audit Log</a>
                    </div>
                </div>
            @endif
        </nav>

        <div class="topnav-actions">
            <a href="{{ route('employee.reports.create') }}" class="btn btn-cta btn-sm btn-new"><x-icon name="plus" :size="15" /> Ajukan Laporan</a>

            <div class="dropdown bell">
                <button class="icon-btn" type="button" data-dropdown-toggle aria-label="Notifikasi" aria-expanded="false"><x-icon name="bell" :size="18" /></button>
                @if ($unreadCount > 0)<span class="pip">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
                <div class="dropdown-menu menu-notif">
                    <div class="flex items-center justify-between" style="padding:12px 14px;border-bottom:1px solid var(--line)">
                        <strong>Notifikasi</strong>
                        @if ($unreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-ghost btn-sm" data-no-lock>Tandai dibaca</button></form>
                        @endif
                    </div>
                    @forelse ($recentNotifications as $n)
                        <a href="{{ route('notifications.open', $n) }}" class="notif-item {{ $n->is_read ? '' : 'unread' }}">
                            <div class="strong text-sm">{{ $n->title }}</div>
                            <div class="text-xs muted">{{ \Illuminate\Support\Str::limit($n->message, 90) }}</div>
                            <div class="text-xs faint mt-1">{{ $n->created_at->diffForHumans() }}</div>
                        </a>
                    @empty
                        <div class="empty" style="padding:26px 14px">Belum ada notifikasi.</div>
                    @endforelse
                    <a href="{{ route('notifications.index') }}" class="dropdown-item" style="justify-content:center;color:var(--primary)">Lihat semua</a>
                </div>
            </div>

            <div class="dropdown">
                <button class="userbtn" type="button" data-dropdown-toggle aria-expanded="false" aria-label="Akun">
                    <span class="avatar sm">{{ $me->initials() }}</span>
                    <span class="userbtn-name">{{ $firstName }}</span>
                    <x-icon name="chevron-down" :size="13" />
                </button>
                <div class="dropdown-menu">
                    <div style="padding:12px 14px;border-bottom:1px solid var(--line)">
                        <div class="strong">{{ $me->name }}</div>
                        <div class="text-xs muted">NIK {{ $me->nik }} · {{ $me->roleLabel() }}</div>
                    </div>
                    @if ($isAdmin)
                        <a class="dropdown-item" href="{{ route('employee.reports.index') }}">Laporan Saya</a>
                    @endif
                    <a class="dropdown-item" href="{{ route('profile.edit') }}">Profil{{ $me->isKaryawan() ? '' : ' & Password' }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item" data-no-lock style="color:#B42318">Keluar</button></form>
                </div>
            </div>

        </div>
    </div>
</header>

<main class="shell">
    @unless ($isDash)
        <div class="page-head"><h1>@yield('title', 'Dashboard')</h1></div>
    @endunless

    @include('components.flash')
    @yield('content')
</main>

<footer class="footer">© {{ date('Y') }} IT {{ config('halodek.company') }}</footer>

{{-- ===================== DOCK BAWAH & MENU (MOBILE) ===================== --}}
<nav class="bottomnav" aria-label="Navigasi cepat">
    <a href="{{ route('dashboard') }}" class="bn-item {{ $act($isDash) }}"><span class="bn-ico"><x-icon name="home" :size="21" /></span>Beranda</a>
    @if ($isAdmin)
        <a href="{{ route('admin.reports.index') }}" class="bn-item {{ $act($on('admin.reports.*')) }}"><span class="bn-ico"><x-icon name="inbox" :size="21" />@if ($pendingCount > 0)<i class="bn-dot"></i>@endif</span>Laporan</a>
    @else
        <a href="{{ route('employee.reports.index') }}" class="bn-item {{ $act($on('employee.reports.index', 'employee.reports.show')) }}"><span class="bn-ico"><x-icon name="document" :size="21" /></span>Laporan</a>
    @endif
    <a href="{{ route('employee.reports.create') }}" class="bn-fab" aria-label="Ajukan Laporan"><span><x-icon name="plus" :size="26" /></span></a>
    <a href="{{ route('announcements.index') }}" class="bn-item {{ $act($on('announcements.*')) }}"><span class="bn-ico"><x-icon name="megaphone" :size="21" /></span>Info</a>
    <button type="button" class="bn-item" data-nav-toggle><span class="bn-ico"><x-icon name="menu" :size="21" /></span>Menu</button>
</nav>

<aside class="sheet" aria-label="Menu">
    <div class="sheet-grab"></div>
    <div class="sheet-user">
        <span class="avatar">{{ $me->initials() }}</span>
        <div class="grow"><div class="strong truncate">{{ $me->name }}</div><div class="text-xs muted">NIK {{ $me->nik }} · {{ $me->roleLabel() }}</div></div>
    </div>

    <div class="sheet-title">Menu</div>
    <div class="sheet-grid">
        <a class="tile {{ $act($isDash) }}" href="{{ route('dashboard') }}"><span class="tile-ico tone-blue"><x-icon name="home" :size="22" /></span>Dashboard</a>
        <a class="tile" href="{{ route('employee.reports.create') }}"><span class="tile-ico tone-green"><x-icon name="plus" :size="22" /></span>Ajukan</a>
        <a class="tile {{ $act($on('employee.reports.index', 'employee.reports.show')) }}" href="{{ route('employee.reports.index') }}"><span class="tile-ico tone-violet"><x-icon name="document" :size="22" /></span>Laporan Saya</a>
        <a class="tile {{ $act($on('announcements.*')) }}" href="{{ route('announcements.index') }}"><span class="tile-ico tone-amber"><x-icon name="megaphone" :size="22" /></span>Pengumuman</a>
        <a class="tile" href="{{ route('notifications.index') }}"><span class="tile-ico tone-red"><x-icon name="bell" :size="22" />@if ($unreadCount > 0)<i class="bn-dot"></i>@endif</span>Notifikasi</a>
        <a class="tile" href="{{ route('profile.edit') }}"><span class="tile-ico tone-gray"><x-icon name="user" :size="22" /></span>Profil</a>
    </div>

    @if ($isAdmin)
        <div class="sheet-title">Kelola</div>
        <div class="sheet-grid">
            <a class="tile {{ $act($on('admin.reports.*') && ! $mineTasks && ! $queueView) }}" href="{{ route('admin.reports.index') }}"><span class="tile-ico tone-blue"><x-icon name="inbox" :size="22" /></span>Semua Laporan</a>
            <a class="tile {{ $act($queueView) }}" href="{{ route('admin.reports.index', ['assigned' => 'none', 'status' => 'aktif']) }}"><span class="tile-ico tone-orange"><x-icon name="list" :size="22" />@if ($pendingCount > 0)<i class="bn-dot"></i>@endif</span>Antrean Regu</a>
            <a class="tile {{ $act($mineTasks) }}" href="{{ route('admin.reports.index', ['assigned' => 'me', 'status' => 'aktif']) }}"><span class="tile-ico tone-green"><x-icon name="check-circle" :size="22" /></span>Tugas Saya</a>
            <a class="tile {{ $act($on('admin.teams.*')) }}" href="{{ route('admin.teams.index') }}"><span class="tile-ico tone-violet"><x-icon name="users" :size="22" /></span>{{ $me->isSuperadmin() ? 'Regu' : 'Regu Saya' }}</a>
            <a class="tile {{ $act($on('admin.inventory.*')) }}" href="{{ route('admin.inventory.index') }}"><span class="tile-ico tone-amber"><x-icon name="laptop" :size="22" /></span>Inventaris</a>
            @if ($canLocations)
                <a class="tile {{ $act($on('admin.locations.*')) }}" href="{{ route('admin.locations.index') }}"><span class="tile-ico tone-red"><x-icon name="pin" :size="22" /></span>Lokasi</a>
            @endif
            @if ($me->isSuperadmin())
                <a class="tile {{ $act($on('admin.users.*')) }}" href="{{ route('admin.users.index') }}"><span class="tile-ico tone-violet"><x-icon name="user" :size="22" /></span>Pengguna</a>
                <a class="tile {{ $act($on('admin.audit.*')) }}" href="{{ route('admin.audit.index') }}"><span class="tile-ico tone-gray"><x-icon name="history" :size="22" /></span>Audit Log</a>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn btn-soft-danger btn-block" data-no-lock><x-icon name="logout" :size="17" /> Keluar</button></form>
</aside>

@stack('modals')

@if (old('_modal'))
    <script nonce="{{ $cspNonce ?? '' }}">window.HD_RESTORE = @json(['modal' => old('_modal'), 'action' => old('_action'), 'method' => old('_method')]);</script>
@endif
<script src="{{ asset('js/halodek.js') }}?v={{ filemtime(public_path('js/halodek.js')) }}"></script>
@stack('scripts')
</body>
</html>
