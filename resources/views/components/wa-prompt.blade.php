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
                <p class="muted" style="margin:0">Masukkan nomor WhatsApp Anda. Kami kirim kabar singkat otomatis saat laporan Anda <strong>diproses</strong> atau <strong>selesai</strong>, lengkap dengan link untuk memantau laporan. Boleh diisi, boleh tidak.</p>
                <x-input name="phone" id="wa-number" type="tel" inputmode="tel" label="Nomor WhatsApp" placeholder="0812 3456 7890" maxlength="20" autocomplete="tel" required data-phone hint="Boleh diawali 0 atau +62, otomatis diubah ke format 62. Bisa diubah kapan saja di menu Profil." />
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" data-modal-close>Nanti saja</button>
                <button type="submit" class="btn btn-primary"><x-icon name="save" :size="16" /> Simpan Nomor</button>
            </div>
        </form>
    </div>
</div>
