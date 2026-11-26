{{-- Langkah pasang manual; yang tampil hanya yang sesuai perangkat (diatur JS). Disembunyikan bila tombol "Pasang Sekarang" tersedia. --}}
<div class="pwa-steps" data-pwa-manual>
    <p class="pwa-note" data-pwa-insecure hidden>Pemasangan sekali klik akan tersedia setelah alamat HaloDek memakai HTTPS. Sementara ini, cukup ikuti 3 langkah di bawah sekali saja.@if (auth()->user()?->isSuperadmin()) <span class="pwa-admin">Admin: cara mengaktifkan HTTPS ada di README bagian "Mengaktifkan HTTPS".</span>@endif</p>
    <ol data-pwa-os="android" hidden>
        <li>Buka alamat ini di <strong>Google Chrome</strong>.</li>
        <li>Ketuk menu <strong>⋮</strong> di kanan atas.</li>
        <li>Pilih <strong>Instal aplikasi</strong> atau <strong>Tambahkan ke layar utama</strong>, lalu <strong>Tambahkan</strong>.</li>
    </ol>
    <ol data-pwa-os="ios" hidden>
        <li>Buka alamat ini di <strong>Safari</strong>.</li>
        <li>Ketuk tombol <strong>Bagikan</strong> (kotak dengan panah ke atas).</li>
        <li>Pilih <strong>Tambah ke Layar Utama</strong>, lalu <strong>Tambah</strong>.</li>
    </ol>
    <ol data-pwa-os="desktop" hidden>
        <li>Buka alamat ini di <strong>Google Chrome</strong> atau <strong>Microsoft Edge</strong>.</li>
        <li>Klik ikon <strong>Pasang</strong> di ujung kanan kolom alamat bila ada. Bila tidak ada, buka menu <strong>⋮</strong> &gt; <strong>Transmisikan, simpan, dan bagikan</strong> &gt; <strong>Buat pintasan</strong> (Edge: menu <strong>⋯</strong> &gt; <strong>Aplikasi</strong> &gt; <strong>Instal situs ini sebagai aplikasi</strong>).</li>
        <li>Centang <strong>Buka sebagai jendela</strong>, lalu klik <strong>Buat</strong>. Ikonnya muncul di desktop dan menu Start.</li>
    </ol>
</div>
<p class="hint" data-pwa-native hidden style="margin:0">Klik <strong>Pasang Sekarang</strong>, lalu setujui di jendela yang muncul.</p>
