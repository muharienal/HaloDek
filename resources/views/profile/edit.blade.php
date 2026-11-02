@extends('layouts.app')
@section('title', 'Profil & Password')
@section('subtitle', 'Kelola data kontak dan keamanan akun Anda')

@section('content')
    @if ($user->must_change_password && ! $user->isKaryawan())
        <div class="alert alert-warning"><x-icon name="lock" :size="18" /><div><strong>Ganti password bawaan Anda.</strong> Anda baru dapat memakai aplikasi setelah membuat password baru.</div></div>
    @endif

    <div class="grid cols-2" style="align-items:start">
        <div class="card">
            <div class="card-head"><h2>Data Diri</h2></div>
            <form method="POST" action="{{ route('profile.update') }}" class="card-body" style="display:grid;gap:16px">
                @csrf @method('PUT')
                <div class="flex items-center gap-4">
                    <div class="avatar" style="width:56px;height:56px;font-size:18px">{{ $user->initials() }}</div>
                    <div><div class="strong text-lg">{{ $user->name }}</div><div class="text-sm muted">{{ $user->roleLabel() }}</div></div>
                </div>
                <div class="form-grid">
                    <div class="field"><span class="label">NIK</span><input class="input" value="{{ $user->nik }}" disabled></div>
                    <div class="field"><span class="label">Departemen</span><input class="input" value="{{ $user->department ?: '–' }}" disabled></div>
                </div>
                @if ($user->isAdmin() && ! $user->isSuperadmin())
                    <div class="field"><span class="label">Regu yang Anda tangani</span>
                        <div class="flex gap-2 wrap">@if ($user->team)<x-team-chip :team="$user->team" />@else<span class="muted">–</span>@endif</div></div>
                @endif
                <x-input name="email" type="email" label="Email" :value="$user->email" />
                <x-input name="phone" label="No. HP / WhatsApp" :value="$user->phone" />
                <div><button class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Profil</button></div>
            </form>
        </div>

        @if ($user->isKaryawan())
        <div class="card">
            <div class="card-head"><h2>Cara Masuk</h2></div>
            <div class="card-body"><p class="muted">Akun karyawan masuk ke {{ config('halodek.name') }} hanya dengan <strong>NIK</strong> — tidak perlu password.</p></div>
        </div>
        @else
        <div class="card">
            <div class="card-head"><h2>Ganti Password</h2></div>
            <form method="POST" action="{{ route('profile.password') }}" class="card-body" style="display:grid;gap:16px">
                @csrf @method('PUT')
                <x-input name="current_password" type="password" label="Password saat ini" required autocomplete="current-password" />
                <x-input name="password" type="password" label="Password baru" required autocomplete="new-password" hint="Minimal 10 karakter: huruf besar, huruf kecil, dan angka." />
                <x-input name="password_confirmation" type="password" label="Ulangi password baru" required autocomplete="new-password" />
                <div><button class="btn btn-primary"><x-icon name="lock" :size="16" /> Perbarui Password</button></div>
            </form>
        </div>
        @endif
    </div>
@endsection
