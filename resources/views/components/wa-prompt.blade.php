@php $isRestored = old('_modal') === 'wa-modal'; @endphp
<div class="modal" id="wa-modal" role="dialog" aria-modal="true" aria-labelledby="wa-modal-title" @if ($show ?? false) data-auto-open @endif>
    <div class="modal-dialog sm">
        <form method="POST" action="{{ route('profile.whatsapp') }}">
            @csrf @method('PUT')
            <input type="hidden" name="_modal" value="wa-modal">
            <div class="modal-head">
                <h3 id="wa-modal-title" data-modal-title data-create-title="Dapatkan kabar laporan lewat WhatsApp">Dapatkan kabar laporan lewat WhatsApp</h3>
                <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button>
            </div>
            <div class="modal-body" style="display:grid;gap:14px">
                @php $when = \App\Support\WaRules::promptPhrase((string) auth()->user()?->role); @endphp
                <p class="muted" style="margin:0">Masukkan nomor WhatsApp Anda. Kami kirim kabar singkat otomatis{{ $when ? ' '.$when : '' }}, lengkap dengan link untuk memantau laporan. Boleh diisi, boleh tidak.</p>
                <x-input name="phone" id="wa-number" type="tel" inputmode="tel" label="Nomor WhatsApp" placeholder="0812 3456 7890" maxlength="20" autocomplete="tel" required data-phone hint="Boleh diawali 0 atau +62, otomatis diubah ke format 62. Bisa diubah kapan saja di menu Profil." />
                @if ($pwa ?? false)
                    {{-- Ajakan pasang aplikasi ikut di jendela ini agar tidak muncul dua jendela sekaligus. --}}
                    <div class="pwa-inline" data-pwa-inline hidden>
                        <img src="{{ asset('images/halodek-icon.svg') }}" alt="" width="38" height="38">
                        <div class="grow"><strong>Pasang HaloDek di HP / laptop</strong><span class="text-sm muted">Buka langsung dari layar utama, tanpa mengetik alamat.</span></div>
                        <button type="button" class="btn btn-secondary btn-sm" data-pwa-install-or-open>Pasang</button>
                    </div>
                @endif
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Nanti Saja</button>
                <button type="submit" class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Nomor</button>
            </div>
        </form>
    </div>
</div>
