/*!
 * HaloDek — JavaScript ringan (vanilla, tanpa dependensi, tanpa build step)
 */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    var HU = window.HU = window.HU || {};

    /* ---------- sidebar mobile ---------- */
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-nav-toggle]')) { document.body.classList.toggle('nav-open'); }
        else if (e.target.closest('.backdrop')) { document.body.classList.remove('nav-open'); }
    });

    /* ---------- dropdown ---------- */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown-toggle]');
        var openOne = null;
        if (toggle) {
            openOne = toggle.closest('.dropdown');
            openOne.classList.toggle('open');
            toggle.setAttribute('aria-expanded', openOne.classList.contains('open'));
        }
        $$('.dropdown.open').forEach(function (d) { if (d !== openOne) { d.classList.remove('open'); } });
    });

    /* ---------- modal ---------- */
    function openModal(modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        var first = $('input:not([type=hidden]):not([disabled]), select, textarea', modal);
        if (first) { setTimeout(function () { first.focus(); }, 60); }
    }
    function closeModal(modal) {
        modal.classList.remove('open');
        if (!$('.modal.open')) { document.body.style.overflow = ''; }
    }
    HU.openModal = openModal;
    HU.closeModal = closeModal;

    function setMethod(form, method) {
        var el = $('input[name="_method"]', form);
        if (!method) { if (el) { el.remove(); } return; }
        if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = '_method'; form.appendChild(el); }
        el.value = method;
    }

    function setValue(form, name, value) {
        var fields = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
        Array.prototype.forEach.call(fields, function (el) {
            if (el.type === 'hidden' && form.querySelector('input[type=checkbox][name="' + name + '"]')) {
                el.value = '0'; // pasangan hidden untuk checkbox: nilai default saat tidak dicentang
            } else if (el.type === 'checkbox') {
                if (Array.isArray(value)) { el.checked = value.map(String).indexOf(el.value) !== -1; }
                else { el.checked = value === true || value === 1 || value === '1'; }
            } else if (el.type === 'radio') {
                el.checked = String(el.value) === String(value);
            } else if (el.tagName === 'SELECT' && el.multiple && Array.isArray(value)) {
                Array.prototype.forEach.call(el.options, function (o) { o.selected = value.map(String).indexOf(o.value) !== -1; });
            } else {
                el.value = value === null || value === undefined ? '' : value;
            }
        });
    }

    /** Siapkan form modal untuk mode "tambah" (reset) atau "ubah" (isi dari data-fill). */
    function prepareModal(modal, trigger) {
        var form = $('form[data-modal-form]', modal);
        if (!form) { return; }
        var title = $('[data-modal-title]', modal);
        var fill = trigger && trigger.getAttribute('data-fill');

        if (fill) {
            form.reset();
            var data = JSON.parse(fill);
            Object.keys(data).forEach(function (k) { setValue(form, k, data[k]); });
            form.action = trigger.getAttribute('data-action');
            setMethod(form, trigger.getAttribute('data-method') || 'PATCH');
            if (title) { title.textContent = trigger.getAttribute('data-title') || title.getAttribute('data-edit-title') || title.textContent; }
            $$('[data-hide-on-edit]', form).forEach(function (el) { el.hidden = true; });
            $$('[data-show-on-edit]', form).forEach(function (el) { el.hidden = false; });
        } else {
            form.reset();
            $$('.error', form).forEach(function (el) { el.remove(); });
            $$('.is-invalid', form).forEach(function (el) { el.classList.remove('is-invalid'); });
            form.action = form.getAttribute('data-create-action');
            setMethod(form, null);
            if (title) { title.textContent = title.getAttribute('data-create-title') || title.textContent; }
            $$('[data-hide-on-edit]', form).forEach(function (el) { el.hidden = false; });
            $$('[data-show-on-edit]', form).forEach(function (el) { el.hidden = true; });
        }
        var act = $('input[name="_action"]', form);
        if (act) { act.value = form.action; }
        form.dispatchEvent(new CustomEvent('hu:prepared', { bubbles: true }));
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            var modal = document.getElementById(opener.getAttribute('data-modal-open'));
            if (modal) { prepareModal(modal, opener); openModal(modal); }
            return;
        }
        var closer = e.target.closest('[data-modal-close]');
        if (closer) { closeModal(closer.closest('.modal')); return; }
        if (e.target.classList && e.target.classList.contains('modal')) { closeModal(e.target); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = $$('.modal.open').pop();
            if (m) { closeModal(m); }
            document.body.classList.remove('nav-open');
            $$('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
        }
    });

    /* Buka kembali modal yang gagal validasi (data dari server lewat window.HD_RESTORE). */
    document.addEventListener('DOMContentLoaded', function () {
        var r = window.HD_RESTORE;
        if (!r || !r.modal) { return; }
        var modal = document.getElementById(r.modal);
        if (!modal) { return; }
        var form = $('form[data-modal-form]', modal);
        if (form && r.action) {
            form.action = r.action;
            setMethod(form, r.method || null);
            var title = $('[data-modal-title]', modal);
            if (title && r.method && title.getAttribute('data-edit-title')) { title.textContent = title.getAttribute('data-edit-title'); }
            if (r.method) {
                $$('[data-hide-on-edit]', form).forEach(function (el) { el.hidden = true; });
                $$('[data-show-on-edit]', form).forEach(function (el) { el.hidden = false; });
            }
            var act = $('input[name="_action"]', form);
            if (act) { act.value = r.action; }
            form.dispatchEvent(new CustomEvent('hu:prepared', { bubbles: true }));
        }
        openModal(modal);
    });

    /* Modal yang dibuka otomatis oleh server (mis. pengingat nomor WhatsApp). */
    document.addEventListener('DOMContentLoaded', function () {
        var m = $('.modal[data-auto-open]');
        if (m && !$('.modal.open')) { setTimeout(function () { openModal(m); }, 350); }
    });

    /* ---------- konfirmasi (modal kustom) ---------- */
    var confirmModal;
    function ensureConfirm() {
        if (confirmModal) { return confirmModal; }
        confirmModal = document.createElement('div');
        confirmModal.className = 'modal';
        confirmModal.style.zIndex = 80;
        confirmModal.innerHTML =
            '<div class="modal-dialog sm" role="alertdialog" aria-modal="true">' +
            '<div class="modal-body"><h3 class="mb-2" data-c-title>Konfirmasi</h3><p class="muted" data-c-msg></p></div>' +
            '<div class="modal-foot"><button type="button" class="btn btn-secondary" data-c-no>Batal</button>' +
            '<button type="button" class="btn btn-danger" data-c-yes>Ya, lanjutkan</button></div></div>';
        document.body.appendChild(confirmModal);
        return confirmModal;
    }
    function askConfirm(message, title, okLabel, tone) {
        return new Promise(function (resolve) {
            var m = ensureConfirm();
            $('[data-c-title]', m).textContent = title || 'Konfirmasi';
            $('[data-c-msg]', m).textContent = message;
            var yes = $('[data-c-yes]', m), no = $('[data-c-no]', m);
            yes.textContent = okLabel || 'Ya, lanjutkan';
            yes.className = 'btn ' + (tone === 'primary' ? 'btn-primary' : 'btn-danger');
            function done(v) { yes.onclick = no.onclick = null; closeModal(m); resolve(v); }
            yes.onclick = function () { done(true); };
            no.onclick = function () { done(false); };
            openModal(m);
            yes.focus();
        });
    }
    HU.confirm = askConfirm;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.getAttribute && form.getAttribute('data-confirm');
        if (!msg || form.__confirmed) { return; }
        e.preventDefault();
        askConfirm(msg, form.getAttribute('data-confirm-title'), form.getAttribute('data-confirm-ok'), form.getAttribute('data-confirm-tone')).then(function (ok) {
            if (ok) { form.__confirmed = true; HTMLFormElement.prototype.submit.call(form); }
        });
    }, true);

    /* ---------- cegah kirim ganda ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || form.hasAttribute('data-no-lock')) { return; }
        setTimeout(function () {
            $$('button[type=submit], input[type=submit]', form).forEach(function (b) { b.disabled = true; b.classList.add('disabled'); });
        }, 0);
    });
    window.addEventListener('pageshow', function () {
        $$('button[type=submit].disabled').forEach(function (b) { b.disabled = false; b.classList.remove('disabled'); });
    });

    /* ---------- nomor HP -> format 62xxxxxxxxxx ---------- */
    function phone62(v) {
        var d = String(v || '').replace(/\D+/g, '');
        if (!d) { return ''; }
        d = d.replace(/^620/, '62');
        if (d.charAt(0) === '0') { d = '62' + d.slice(1); } else if (d.charAt(0) === '8') { d = '62' + d; }
        return d;
    }
    document.addEventListener('focusout', function (e) {
        var i = e.target;
        if (i && i.matches && i.matches('input[data-phone]')) { i.value = phone62(i.value); }
    });
    document.addEventListener('submit', function (e) {
        $$('input[data-phone]', e.target).forEach(function (i) { i.value = phone62(i.value); });
    }, true);

    /* ---------- PWA: service worker (hanya HTTPS atau localhost) ---------- */
    (function () {
        var meta = document.querySelector('meta[name="halodek-sw"]');
        var secure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
        if (!meta || !secure || !('serviceWorker' in navigator)) { return; }
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(meta.content).catch(function () { /* abaikan: aplikasi tetap jalan biasa */ });
        });
    })();

    /* ---------- pencarian: fokus saat dibuka, pintasan "/" ---------- */
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-search-toggle]');
        if (t) { setTimeout(function () { var i = document.getElementById('q-nav'); if (i && t.closest('.dropdown').classList.contains('open')) { i.focus(); } }, 40); }
    });
    // Tombol "Cari" di halaman daftar membuka kolom cari yang sama di header.
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-search-open]')) { return; }
        var btn = $('[data-search-toggle]');
        if (btn && !btn.closest('.dropdown').classList.contains('open')) { e.stopPropagation(); btn.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) { return; }
        var a = document.activeElement, tag = a && a.tagName;
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (a && a.isContentEditable)) { return; }
        var btn = $('[data-search-toggle]');
        if (btn && btn.offsetParent !== null) { e.preventDefault(); btn.click(); }
    });

    /* ---------- alert (toast) ---------- */
    var TOAST_MS = { success: 5000, info: 5000, warning: 8000, error: 9000 };
    var TOAST_TITLE = { success: 'Berhasil', error: 'Gagal', warning: 'Perhatian', info: 'Info' };
    var ICON_OK = '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.2 11 14.7l4.5-5.4"/>';
    var ICON_WARN = '<path d="M12 4 3.5 19h17Z"/><path d="M12 10v4M12 16.8v.1"/>';

    function toastBox() {
        var box = document.getElementById('toasts');
        if (!box) {
            box = document.createElement('div'); box.id = 'toasts'; box.className = 'toasts'; box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }
        return box;
    }
    function armToast(t) {
        if (t.__armed) { return; }
        t.__armed = true;
        var type = t.getAttribute('data-toast') || 'info';
        var left = TOAST_MS[type] || 6000, started = 0, timer = null;
        t.style.setProperty('--t', left + 'ms');
        function close() {
            clearTimeout(timer);
            t.classList.add('out');
            setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 220);
        }
        function run() { started = Date.now(); timer = setTimeout(close, left); t.classList.remove('paused'); }
        function pause() { clearTimeout(timer); left -= Date.now() - started; t.classList.add('paused'); }
        t.addEventListener('mouseenter', pause);
        t.addEventListener('mouseleave', run);
        t.querySelector('.toast-x').addEventListener('click', close);
        run();
    }
    HU.toast = function (message, type) {
        type = TOAST_MS[type] ? type : 'info';
        var box = toastBox();
        var t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');
        t.setAttribute('data-toast', type);
        t.innerHTML = '<span class="toast-ic"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (type === 'success' ? ICON_OK : ICON_WARN) + '</svg></span>' +
            '<div class="toast-body"><strong></strong><span></span></div><button class="toast-x" type="button" aria-label="Tutup">&times;</button><i class="toast-bar"></i>';
        t.querySelector('strong').textContent = TOAST_TITLE[type];
        t.querySelector('.toast-body span').textContent = message;
        while (box.children.length >= 4) { box.removeChild(box.firstChild); }
        box.appendChild(t);
        armToast(t);
    };
    document.addEventListener('DOMContentLoaded', function () {
        $$('.toast[data-toast]').forEach(armToast);
    });
    document.addEventListener('click', function (e) {
        var c = e.target.closest('.alert .close');
        if (c) { c.closest('.alert').remove(); }
    });

    /* ---------- tampil/sembunyi password ---------- */
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-toggle-password]');
        if (!t) { return; }
        var input = document.getElementById(t.getAttribute('data-toggle-password'));
        if (input) { input.type = input.type === 'password' ? 'text' : 'password'; }
    });

    /* ---------- submit otomatis saat filter berubah ---------- */
    document.addEventListener('change', function (e) {
        if (e.target.closest('[data-autosubmit]') && !e.target.hasAttribute('data-no-auto')) {
            e.target.closest('form').submit();
        }
    });

    /* ---------- input foto: kompres otomatis (>1 MB -> <=1 MB), batas jumlah/ukuran, thumbnail ---------- */
    var TARGET_BYTES = 1024 * 1024, MAX_SIDE = 1920;

    function loadBitmap(file) {
        if (window.createImageBitmap) {
            return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(file); });
        }
        return new Promise(function (resolve, reject) {
            var img = new Image(), url = URL.createObjectURL(file);
            img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('gagal dibaca')); };
            img.src = url;
        });
    }
    function toBlob(canvas, q) {
        return new Promise(function (resolve) { canvas.toBlob(resolve, 'image/jpeg', q); });
    }
    /* Foto > 1 MB diperkecil dimensinya lalu kualitasnya diturunkan bertahap sampai <= 1 MB. */
    function compressImage(file) {
        if (file.size <= TARGET_BYTES || !/^image\/(jpeg|png|webp)$/.test(file.type)) { return Promise.resolve(file); }
        return loadBitmap(file).then(function (bmp) {
            var w = bmp.width, h = bmp.height, scale = Math.min(1, MAX_SIDE / Math.max(w, h));
            var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d');
            var quality = 0.85, round = 0;
            function render() {
                canvas.width = Math.max(1, Math.round(w * scale)); canvas.height = Math.max(1, Math.round(h * scale));
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
                return toBlob(canvas, quality).then(function (blob) {
                    if (!blob) { return file; }
                    if (blob.size <= TARGET_BYTES || round >= 12) {
                        if (bmp.close) { bmp.close(); }
                        if (blob.size >= file.size) { return file; }
                        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                    }
                    round++;
                    if (quality > 0.55) { quality -= 0.1; } else { scale *= 0.85; }
                    return render();
                });
            }
            return render();
        }).catch(function () { return file; });
    }

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('input[type=file][data-file-input]')) { return; }
        var max = parseInt(input.getAttribute('data-max-files') || '3', 10);
        var maxKb = parseInt(input.getAttribute('data-max-kb') || '3072', 10);
        var list = document.getElementById(input.getAttribute('data-file-list'));
        var msg = document.getElementById(input.getAttribute('data-file-error'));
        var form = input.closest('form');
        var files = Array.prototype.slice.call(input.files);

        function show(text) { if (msg) { msg.textContent = text; msg.hidden = !text; } if (text) { HU.toast(text, 'error'); } }
        function reject(text) { input.value = ''; show(text); renderThumbs(input, list, []); }

        if (files.length > max) { return reject('Maksimal ' + max + ' foto.'); }
        for (var i = 0; i < files.length; i++) {
            if (files[i].type && files[i].type.indexOf('image/') !== 0) { return reject('"' + files[i].name + '" bukan foto.'); }
        }
        show('');
        if (!files.some(function (f) { return f.size > TARGET_BYTES; })) {
            for (var j = 0; j < files.length; j++) {
                if (files[j].size > maxKb * 1024) { return reject('"' + files[j].name + '" melebihi ' + Math.round(maxKb / 1024) + ' MB.'); }
            }
            return renderThumbs(input, list, files);
        }

        // Ada foto besar (mis. langsung dari kamera HP): kompres dulu, kunci tombol kirim selama prosesnya.
        var buttons = form ? Array.prototype.slice.call(form.querySelectorAll('button:not([type=button]), input[type=submit]')) : [];
        buttons.forEach(function (b) { b.disabled = true; });
        show('Memproses foto…');
        if (msg) { msg.classList.add('muted'); }
        Promise.all(files.map(compressImage)).then(function (done) {
            if (msg) { msg.classList.remove('muted'); }
            var tooBig = done.filter(function (f) { return f.size > maxKb * 1024; })[0];
            if (tooBig) { return reject('"' + tooBig.name + '" terlalu besar dan tidak bisa dikompres.'); }
            var dt = new DataTransfer();
            done.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
            show('');
            renderThumbs(input, list, done);
        }).then(function () {
            buttons.forEach(function (b) { b.disabled = false; });
        }, function () {
            buttons.forEach(function (b) { b.disabled = false; });
            reject('Foto gagal diproses. Coba pilih ulang.');
        });
    });

    /* Thumbnail foto terpilih: klik = perbesar, × = buang satu foto. */
    function renderThumbs(input, list, files) {
        if (!list) { return; }
        (list._urls || []).forEach(function (u) { URL.revokeObjectURL(u); });
        list._urls = [];
        list.innerHTML = '';
        files.forEach(function (f, i) {
            var url = URL.createObjectURL(f);
            list._urls.push(url);
            var box = document.createElement('div'); box.className = 'thumb';
            var a = document.createElement('a'); a.href = url; a.setAttribute('data-lightbox', list.id || 'upl'); a.setAttribute('data-caption', f.name); a.title = 'Klik untuk memperbesar';
            var img = document.createElement('img'); img.src = url; img.alt = f.name; a.appendChild(img);
            var x = document.createElement('button'); x.type = 'button'; x.className = 'thumb-x'; x.setAttribute('aria-label', 'Buang foto'); x.textContent = '×';
            x.addEventListener('click', function () {
                var dt = new DataTransfer();
                files.forEach(function (g, j) { if (j !== i) { dt.items.add(g); } });
                input.files = dt.files;
                renderThumbs(input, list, Array.prototype.slice.call(dt.files));
            });
            box.appendChild(a); box.appendChild(x); list.appendChild(box);
        });
    }

    /* ---------- lightbox foto (popup) ---------- */
    (function () {
        var box, img, cap, group = [], idx = 0;
        function build() {
            if (box) { return; }
            box = document.createElement('div'); box.className = 'lb'; box.hidden = true; box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
            box.innerHTML = '<button type="button" class="lb-btn lb-close" aria-label="Tutup">×</button><button type="button" class="lb-btn lb-prev" aria-label="Sebelumnya">‹</button><figure class="lb-fig"><img alt=""><figcaption></figcaption></figure><button type="button" class="lb-btn lb-next" aria-label="Berikutnya">›</button>';
            document.body.appendChild(box);
            img = box.querySelector('img'); cap = box.querySelector('figcaption');
            box.addEventListener('click', function (e) {
                if (e.target.closest('.lb-close') || e.target === box || e.target.classList.contains('lb-fig')) { close(); }
                else if (e.target.closest('.lb-prev')) { go(-1); }
                else if (e.target.closest('.lb-next')) { go(1); }
            });
        }
        function show() {
            var a = group[idx];
            img.src = a.href; cap.textContent = (a.getAttribute('data-caption') || '') + (group.length > 1 ? '  (' + (idx + 1) + '/' + group.length + ')' : '');
            box.classList.toggle('single', group.length < 2);
        }
        function go(d) { idx = (idx + d + group.length) % group.length; show(); }
        function close() { box.hidden = true; img.removeAttribute('src'); document.body.classList.remove('lb-open'); }
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[data-lightbox]');
            if (!a) { return; }
            e.preventDefault(); build();
            var name = a.getAttribute('data-lightbox');
            group = $$('a[data-lightbox]').filter(function (x) { return x.getAttribute('data-lightbox') === name; });
            idx = Math.max(0, group.indexOf(a));
            box.hidden = false; document.body.classList.add('lb-open'); show();
            box.querySelector('.lb-close').focus();
        });
        document.addEventListener('keydown', function (e) {
            if (!box || box.hidden) { return; }
            if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowLeft' && group.length > 1) { go(-1); } else if (e.key === 'ArrowRight' && group.length > 1) { go(1); }
        });
    })();
    ['dragenter', 'dragover'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { var d = e.target.closest && e.target.closest('.drop'); if (d) { e.preventDefault(); d.classList.add('drag'); } });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { var d = e.target.closest && e.target.closest('.drop'); if (d) { d.classList.remove('drag'); } });
    });

    /* ---------- penghitung karakter ---------- */
    function bindCounters() {
        $$('textarea[data-counter]').forEach(function (ta) {
            var out = document.getElementById(ta.getAttribute('data-counter'));
            if (!out) { return; }
            var max = ta.getAttribute('maxlength');
            var update = function () { out.textContent = ta.value.length + (max ? ' / ' + max : ''); };
            ta.addEventListener('input', update); update();
        });
    }
    document.addEventListener('DOMContentLoaded', bindCounters);

    /* ---------- salin teks ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b || !navigator.clipboard) { return; }
        navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function () {
            var old = b.textContent; b.textContent = 'Tersalin ✓'; setTimeout(function () { b.textContent = old; }, 1500); HU.toast('Teks berhasil disalin.', 'success');
        }, function () { HU.toast('Gagal menyalin teks. Salin secara manual.', 'error'); });
    });

    /* ---------- filter dilipat di HP (tombol "Filter"); status buka diingat per sesi ---------- */
    (function () {
        var KEY = 'hd-filters-open';
        function remember(v) { try { sessionStorage.setItem(KEY, v ? '1' : '0'); } catch (e) { /* abaikan */ } }
        function recalled() { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
        document.addEventListener('DOMContentLoaded', function () {
            $$('[data-filter-toggle]').forEach(function (b) {
                var form = b.closest('form');
                if (form && recalled()) { form.classList.add('open'); b.setAttribute('aria-expanded', 'true'); }
            });
        });
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-filter-toggle]');
            if (!b) { return; }
            var form = b.closest('form');
            var open = form.classList.toggle('open');
            b.setAttribute('aria-expanded', open ? 'true' : 'false');
            remember(open);
        });
    })();

    /* ---------- tabel -> kartu di layar kecil: salin judul kolom ke tiap sel ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        $$('table.table').forEach(function (t) {
            var heads = $$('thead th', t).map(function (th) { return th.textContent.trim(); });
            $$('tbody tr', t).forEach(function (tr) {
                $$('td', tr).forEach(function (td, i) { td.setAttribute('data-label', heads[i] || ''); });
            });
        });
    });
})();
