@extends('layouts.app')
@section('title', 'Profil & Password')
@section('subtitle', 'Kelola data kontak dan keamanan akun Anda')

@section('content')
    @if ($user->must_change_password && ! $user->isKaryawan())
        <div class="alert alert-warning"><x-icon name="lock" :size="18" /><div><strong>Ganti password bawaan Anda.</strong> Anda baru dapat memakai aplikasi setelah membuat password baru.</div></div>
    @endif

    <div class="grid {{ $user->isKaryawan() ? '' : 'cols-2' }}" style="align-items:start;{{ $user->isKaryawan() ? 'max-width:720px' : '' }}">
        <div class="card">
            <div class="card-head"><h2>Data Diri</h2></div>
            <form method="POST" action="{{ route('profile.update') }}" class="card-body" style="display:grid;gap:16px">
                @csrf @method('PUT')
                <div class="flex items-center gap-4">
                    <div class="avatar" style="width:56px;height:56px;font-size:18px">{{ $user->initials() }}</div>
                    <div><div class="strong text-lg">{{ $user->name }}</div><div class="text-sm muted">{{ $user->roleLabel() }}</div></div>
                </div>
                {{-- Data kepegawaian hanya ditampilkan (diatur Superadmin), bukan kolom isian yang tidak bisa diubah. --}}
                <dl class="profile-facts">
                    <div><dt>NIK</dt><dd class="mono">{{ $user->nik }}</dd></div>
                    <div><dt>Divisi</dt><dd>{{ $user->division?->name ?? '-' }}</dd></div>
                    <div><dt>Bidang</dt><dd>{{ $user->bidang?->name ?? $user->department ?? '-' }}</dd></div>
                    <div><dt>Jabatan</dt><dd>{{ $user->jabatan?->name ?? $user->position ?? '-' }}</dd></div>
                    @if ($user->isAdmin() && ! $user->isSuperadmin() && ! $user->isManajer())
                        <div><dt>Regu yang Anda tangani</dt><dd>@if ($user->team)<x-team-chip :team="$user->team" />@else-@endif</dd></div>
                    @endif
                </dl>
                <p class="hint" style="margin:-4px 0 0">Data di atas diatur Superadmin. Hubungi Superadmin bila ada yang salah.</p>
                <x-input name="phone" type="tel" inputmode="tel" label="No. WhatsApp" :value="$user->phone" placeholder="0812 3456 7890" maxlength="20" autocomplete="tel" data-phone hint="Otomatis berformat 62. Dipakai untuk kabar lewat WhatsApp. Kosongkan jika tidak mau menerima sama sekali." />
                @php $waChoices = \App\Support\WaRules::active() ? \App\Support\WaRules::personalChoices((string) $user->role) : []; $waOff = (array) ($user->wa_off ?? []); @endphp
                @if (! \App\Support\WaRules::active())
                    <p class="hint" style="margin:-6px 0 0">Kabar lewat WhatsApp sedang dimatikan Superadmin. Nomor tetap disimpan untuk nanti.</p>
                @elseif ($waChoices)
                    <div class="field">
                        <span class="label">Kabar WhatsApp untuk saya</span>
                        <input type="hidden" name="wa_prefs" value="1">
                        <div class="wa-prefs">
                            @foreach ($waChoices as $key => $label)
                                <label class="check"><input type="checkbox" name="wa_on[]" value="{{ $key }}" @checked(! in_array($key, (array) $waOff, true))> {{ $label }}</label>
                            @endforeach
                        </div>
                        <span class="hint">{{ $user->waNumber() ? 'Lonceng di aplikasi tetap muncul walau WhatsApp-nya dimatikan.' : 'Isi No. WhatsApp di atas agar kabar terkirim.' }}</span>
                    </div>
                @endif
                <div><button class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Profil</button></div>
            </form>
        </div>

        @if (! $user->isKaryawan())
        <div class="card">
            <div class="card-head"><h2>Ganti Password</h2></div>
            <form method="POST" action="{{ route('profile.password') }}" class="card-body" style="display:grid;gap:16px">
                @csrf @method('PUT')
                <x-input name="current_password" type="password" label="Password saat ini" required autocomplete="current-password" />
                <x-input name="password" type="password" label="Password baru" required autocomplete="new-password" hint="Minimal 6 karakter, bebas huruf, angka, atau simbol." />
                <x-input name="password_confirmation" type="password" label="Ulangi password baru" required autocomplete="new-password" />
                <div><button class="btn btn-primary"><x-icon name="lock" :size="16" /> Perbarui Password</button></div>
            </form>
        </div>
        @endif
    </div>
@endsection
