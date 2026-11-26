@extends('layouts.app')
@section('title', 'WhatsApp')
@section('subtitle', 'Koneksi Fonnte dan siapa menerima kabar lewat WhatsApp')

@php
    use App\Support\WaRules;
    $driver = old('driver', $cfg['driver'] ?? 'off');
    $driver = array_key_exists($driver, \App\Support\WhatsAppSettings::DRIVERS) ? $driver : 'off';
    $heads = old('head_priorities', $cfg['head_priorities'] ?? []);
    $roleLabels = config('halodek.roles');
    $ruleOld = fn ($role, $event) => (string) old("rules.$role.$event", $rules[$role][$event] ?? '0');
    $tone = ['ok' => 'green', 'warn' => 'amber', 'error' => 'red', 'info' => 'gray'];
    $word = ['ok' => 'Terhubung', 'warn' => 'Perhatian', 'error' => 'Masalah', 'info' => 'Info'];
@endphp

@section('content')
@if ($tokenUnreadable)
    <div class="alert alert-error"><x-icon name="alert" :size="18" /><div>Token tersimpan tidak bisa dibaca karena APP_KEY berubah. Isi ulang token lalu simpan.</div></div>
@elseif (! $fromApp)
    <div class="alert alert-info"><x-icon name="question" :size="18" /><div>Belum pernah disimpan di sini, jadi sementara memakai isian .env. Simpan sekali agar pengaturan di halaman ini yang dipakai.</div></div>
@endif

{{-- Perangkat & pesan uji --}}
<section class="card">
    <div class="card-head">
        <h2>Perangkat Fonnte</h2>
        <a class="btn btn-secondary btn-sm" href="{{ route('admin.wa.edit', ['cek' => 1]) }}"><x-icon name="refresh" :size="15" /> Cek Koneksi</a>
    </div>
    <div class="card-body wa-device">
        <div class="wa-device-col">
            <span class="label">Status perangkat</span>
            <div class="wa-device-status">
                @if ($device)
                    <x-badge :tone="$tone[$device['state']] ?? 'gray'">{{ $word[$device['state']] ?? $device['state'] }}</x-badge>
                    <p>{{ $device['detail'] }}</p>
                @else
                    <x-badge tone="gray">Belum dicek</x-badge>
                    <p class="muted">Tekan Cek Koneksi untuk melihat apakah HP pengirim tersambung.</p>
                @endif
            </div>
        </div>
        <form method="POST" action="{{ route('admin.status.wa-test') }}" class="wa-device-col">@csrf
            <label class="label" for="wa-test-to">Kirim pesan uji ke</label>
            @if ($canTest)
                <div class="wa-inline">
                    <input type="tel" id="wa-test-to" name="to" class="input input-sm" inputmode="tel" maxlength="20" required
                           value="{{ old('to', $testNumber ? \App\Support\Phone::pretty($testNumber) : '') }}" placeholder="0812-3456-7890">
                    <button class="btn btn-secondary btn-sm btn-h40"><x-icon name="chat" :size="15" /> Kirim</button>
                </div>
            @else
                <p class="wa-device-note muted">Aktifkan WhatsApp (Aktif atau Mode uji) lalu simpan, baru pesan uji bisa dikirim.</p>
            @endif
        </form>
    </div>
</section>

<form method="POST" action="{{ route('admin.wa.update') }}" class="card mt-4" autocomplete="off">
    @csrf @method('PUT')
    <div class="card-head"><h2>Pengaturan</h2></div>
    <div class="card-body form-sections">

        {{-- 1. Koneksi --}}
        <div class="form-section wa-narrow">
            <h4>Koneksi</h4>
            <div class="field">
                <span class="label">Status</span>
                <div class="seg" role="radiogroup" aria-label="Status WhatsApp">
                    @foreach (\App\Support\WhatsAppSettings::DRIVERS as $key => $label)
                        <label><input type="radio" name="driver" value="{{ $key }}" @checked($driver === $key)><span>{{ $label }}</span></label>
                    @endforeach
                </div>
                <span class="hint">Mode uji mencatat pesan di log tanpa mengirimnya.</span>
                @error('driver')<span class="error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="label" for="token">Token Fonnte</label>
                @if ($hasToken && ! $tokenUnreadable)
                    <div class="wa-token-state">
                        <span><x-icon name="lock" :size="14" /> Token tersimpan (berakhiran {{ $tokenTail }})</span>
                        <label class="check"><input type="checkbox" name="clear_token" value="1" @checked(old('clear_token'))> Hapus</label>
                    </div>
                @endif
                <input type="password" name="token" id="token" class="input {{ $errors->has('token') ? 'is-invalid' : '' }}" maxlength="200" autocomplete="new-password" spellcheck="false"
                       placeholder="{{ $hasToken && ! $tokenUnreadable ? 'Isi hanya untuk mengganti token' : 'Tempel token' }}">
                <span class="hint">Dari <a href="https://fonnte.com" target="_blank" rel="noopener">fonnte.com</a> › Device › Token. Disimpan terenkripsi.</span>
                @error('token')<span class="error">{{ $message }}</span>@enderror
            </div>
        </div>

        {{-- 2. Penerima --}}
        <div class="form-section">
            <h4>Siapa menerima kabar apa</h4>
            <p class="hint wa-swipe">Geser tabel ke samping untuk melihat semua peran.</p>
            <div class="table-wrap wa-matrix-wrap">
                <table class="wa-matrix">
                    <colgroup><col class="c-kabar">@foreach ($rules as $_)<col>@endforeach</colgroup>
                    <thead><tr>
                        <th scope="col">Kabar</th>
                        @foreach ($rules as $role => $_)
                            @php $st = $roleStats[$role] ?? ['total' => 0, 'wa' => 0]; @endphp
                            <th scope="col">{{ $roleLabels[$role] ?? $role }}
                                <small class="{{ $st['total'] > 0 && $st['wa'] === 0 ? 'warn-text' : '' }}" title="Yang sudah mengisi No. WhatsApp">{{ $st['wa'] }}/{{ $st['total'] }} punya WA</small></th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach (WaRules::EVENTS as $event => [$label, $hint])
                            <tr>
                                <th scope="row">{{ $label }}<small>{{ $hint }}</small></th>
                                @foreach ($rules as $role => $_)
                                    <td>
                                        @if (! WaRules::applies($role, $event))
                                            <span class="na" title="Tidak berlaku untuk peran ini">-</span>
                                        @elseif (WaRules::leveled($event))
                                            @php $v = $ruleOld($role, $event); @endphp
                                            <select name="rules[{{ $role }}][{{ $event }}]" class="select select-sm" aria-label="{{ $label }} untuk {{ $roleLabels[$role] ?? $role }}">
                                                <option value="off" @selected($v === 'off')>Tidak</option>
                                                <option value="penting" @selected($v === 'penting')>Penting</option>
                                                <option value="semua" @selected($v === 'semua')>Semua</option>
                                            </select>
                                        @else
                                            <input type="hidden" name="rules[{{ $role }}][{{ $event }}]" value="0">
                                            <input type="checkbox" name="rules[{{ $role }}][{{ $event }}]" value="1" @checked($ruleOld($role, $event) === '1') aria-label="{{ $label }} untuk {{ $roleLabels[$role] ?? $role }}">
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="hint" style="margin:0">Dikirim ke No. WhatsApp di profil. Kepala Regu dan Staf hanya menerima kabar regunya. Tiap orang bisa mematikan kabar untuk dirinya di Profil.</p>
        </div>

        {{-- 3. Lainnya --}}
        <div class="form-section">
            <h4>Lainnya</h4>
            <div class="wa-other">
                <div class="field">
                    <span class="label">Prioritas penting</span>
                    <div class="wa-mode">
                        @foreach (config('halodek.priorities') as $key => $p)
                            <label class="check"><input type="checkbox" name="head_priorities[]" value="{{ $key }}" @checked(in_array($key, (array) $heads, true))> {{ $p['label'] }}</label>
                        @endforeach
                    </div>
                    <span class="hint">Dipakai pilihan "Penting" pada Laporan baru.</span>
                    @error('head_priorities')<span class="error">{{ $message }}</span>@enderror
                </div>
                <x-input name="digest_at" type="time" label="Jam ringkasan harian" :value="$cfg['digest_at'] ?? '16:00'" class="wa-time" hint="Dikirim pada hari kerja setelah jam ini." />
            </div>
        </div>
    </div>
    <div class="card-foot form-actions">
        <button class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan</button>
    </div>
</form>
@endsection
