{{--
    Pasang HaloDek sebagai aplikasi (PWA) di HP Android/iPhone atau laptop.
    - Bila browser mendukung pemasangan langsung: tombol "Pasang Sekarang".
    - Bila tidak (mis. alamat masih http di jaringan kantor): langkah manual sesuai perangkat.
    $auto: dibuka otomatis sekali tiap login, selama aplikasi belum dipasang di perangkat ini.
--}}
<div class="modal" id="pwa-modal" role="dialog" aria-modal="true" aria-labelledby="pwa-modal-title" @if ($auto ?? false) data-pwa-auto @endif>
    <div class="modal-dialog sm">
        <div class="modal-head">
            <h3 id="pwa-modal-title">Pasang HaloDek sebagai aplikasi</h3>
            <button type="button" class="btn btn-ghost btn-icon" data-modal-close aria-label="Tutup"><x-icon name="x" :size="18" /></button>
        </div>
        <div class="modal-body" style="display:grid;gap:14px">
            <div class="pwa-intro">
                <img src="{{ asset('images/halodek-icon.svg') }}" alt="" width="52" height="52">
                <p class="muted" style="margin:0">Buka HaloDek langsung dari layar utama HP atau desktop laptop, tanpa mengetik alamat. Tampil penuh seperti aplikasi biasa.</p>
            </div>
            @include('partials.pwa-steps')
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-secondary" data-modal-close>Nanti Saja</button>
            <button type="button" class="btn btn-primary" data-pwa-done><x-icon name="check-circle" :size="16" /> Sudah Terpasang</button>
            <button type="button" class="btn btn-primary" data-pwa-install hidden><x-icon name="download" :size="16" /> Pasang Sekarang</button>
        </div>
    </div>
</div>
