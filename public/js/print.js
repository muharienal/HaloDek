/* HaloDek — halaman cetak. Pengganti onclick/onload inline (diblokir Content Security Policy). */
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-print]')) { window.print(); }
    });
    window.addEventListener('load', function () {
        if (document.body.hasAttribute('data-autoprint')) {
            // beri waktu gambar/QR selesai digambar sebelum dialog cetak muncul
            setTimeout(function () { window.print(); }, 400);
        }
    });
})();
