# HaloDek

Layanan pengaduan dan pelaporan terpadu PT Petrokopindo Cipta Selaras. Karyawan mengajukan laporan ke empat regu (**IT**, **Sarana dan Prasarana**, **Kesekretariatan**, **Keamanan**), lalu regu menangani dan memantaunya sampai selesai. Dibangun dengan Laravel 10 (PHP + Blade), tanpa npm / Node / CDN.

Data aset, lokasi, dan stok dikelola di aplikasi terpisah **Inventaris Umum & IT**. HaloDek mengambil lokasi dan barang dari sana lewat API (lihat [Terhubung ke Inventaris](#terhubung-ke-inventaris-umum--it)).

## Instalasi di Windows

**Yang dibutuhkan:** [Laragon Full](https://laragon.org/download/) (sudah berisi PHP, MySQL, dan Composer) dan [Git](https://git-scm.com/download/win).

1. Jalankan Laragon, klik **Start All**.
2. Buka **Terminal** Laragon (atau Git Bash), lalu unduh proyek:
   ```bash
   cd C:/laragon/www
   git clone https://github.com/muharienal/HaloDek.git
   cd HaloDek
   ```
3. Pasang dependensi dan siapkan pengaturan:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
4. Buat database kosong bernama `halodek` (Laragon → **Database** → HeidiSQL → buat database baru, atau lewat phpMyAdmin). Pengaturan bawaan di `.env` memakai user `root` tanpa password, sesuai Laragon.
5. Buat tabel dan akun awal:
   ```bash
   php artisan migrate --seed
   ```
6. Jalankan:
   ```bash
   php artisan serve
   ```
   Buka **http://localhost:8000**.

**Login awal (mode lokal):** tab **Admin**, NIK `00001`, password `00001` (Superadmin). Segera ganti password di menu profil.

Ingin data contoh untuk mencoba? Jalankan `php artisan db:seed --class=DemoSeeder` (hanya untuk mode lokal; semua akun demo memakai password = NIK, karyawan cukup NIK).

### Agar bisa dibuka dari komputer lain di jaringan yang sama
```bash
php artisan serve --host=0.0.0.0 --port=8000
```
Ubah `APP_URL` di `.env` ke `http://IP-KOMPUTER-ANDA:8000` (cek IP dengan `ipconfig`), jalankan `php artisan config:clear`, dan izinkan port 8000 di firewall Windows.

## Peran

| Peran | Masuk dengan | Kemampuan |
|---|---|---|
| Superadmin | NIK + password (tab Admin) | Semua regu, pengguna, log aktivitas |
| Manajer | NIK + password (tab Admin) | Memantau laporan semua regu, laporan bulanan, ekspor. Tidak menangani laporan |
| Kepala Regu | NIK + password (tab Admin) | Mengelola satu regu, menugaskan atau menangani sendiri, pengumuman |
| Staf | NIK + password (tab Admin) | Mengambil dan mengerjakan laporan regunya |
| Karyawan | NIK saja (tab Karyawan) | Mengajukan dan memantau laporan sendiri |

## Notifikasi WhatsApp (opsional)

Laporan baru dari regu mana pun bisa dikirim ke WhatsApp manager lewat Fonnte atau Wablas, berisi ringkasan dan link pantau. Isi di `.env`, lalu `php artisan config:clear`:

```
WA_DRIVER=fonnte        # off | log | fonnte | wablas
WA_TO=628123456789      # nomor penerima, pisahkan dengan koma
WA_TOKEN=               # token dari Fonnte / Wablas
```

Karyawan juga bisa mengisi nomor WhatsApp sendiri (popup saat login, atau menu Profil). Nomor itu menerima kabar singkat dan link pantau saat laporannya **diproses**, **selesai**, atau **ditolak**. Memakai pengaturan `WA_*` yang sama. Hanya satu nomor per pengguna (kolom No. WhatsApp), otomatis diformat `62…`; selama belum diisi, header menampilkan lencana "Isi nomor WA".

## Fitur lain

- **Laporan Bulanan:** menu Kelola Laporan → Laporan Bulanan. Ringkasan per regu (jumlah, waktu rata-rata, kategori terbanyak); unduh Excel atau cetak/simpan PDF.
- **Pencarian:** ikon kaca pembesar di bilah atas (pintasan tombol `/`). Hasil mengikuti hak akses. Di halaman daftar laporan, kolom yang sama langsung menyaring daftar itu.
- **Tutup cepat:** di detail laporan ada tombol **Selesaikan** dan **Tolak** (satu catatan wajib). Ubah status manual tetap ada untuk kasus khusus.
- **Sudah beres?** Setelah laporan selesai, pelapor menjawab 👍/👎. Pilih *Belum beres* membuka kembali laporan ke penanggung jawabnya (maks. 7 hari, atur `reopen_days` di `config/halodek.php`).
- **Form lapor ringkas:** pelapor bisa mengetik masalahnya (mis. "wifi", "AC") untuk langsung memilih kategori dan regu. Judul boleh kosong (otomatis "Kategori – Lokasi"). Kata kunci dan petunjuk deskripsi tiap kategori diatur Kepala Regu di menu Regu.
- **Aksi massal:** centang beberapa laporan aktif di Kelola Laporan, lalu ambil, tugaskan (Kepala Regu), selesaikan, atau tolak sekaligus (maks. 50).
- **Hubungi via WA:** tombol di detail laporan membuka chat WhatsApp ke pelapor dengan pesan pembuka terisi.
- **Laporan serupa:** saat melapor dengan kategori + lokasi (atau barang) yang sama dengan laporan yang masih aktif, pelapor diberi tahu dan bisa memantau laporan itu.
- **Template catatan cepat:** di popup Selesaikan/Tolak ada pilihan catatan siap pakai. Kepala Regu mengaturnya di menu Regu → Template Catatan (satu per baris).
- **Laporan bulanan** juga menampilkan persentase laporan yang diambil tepat waktu, kepuasan pelapor (jawaban "sudah beres"), dan jumlah laporan yang dibuka ulang.
- **QR di barang:** stiker QR dicetak dari aplikasi Inventaris dan mengarah ke `HALODEK/q/{id}`. Karyawan memindai → masuk (NIK) → form lapor sudah terisi regu, kategori, lokasi, dan barang. Kepala Regu/Staf regu pemilik barang diarahkan ke halaman aset di Inventaris.
- **Ekspor laporan:** format Excel (.xlsx). Ekspor pengguna tetap CSV karena dipakai ulang untuk impor.
- **PWA:** ikon HaloDek bisa dipasang di layar HP. Pemasangan penuh dan mode offline hanya aktif lewat **HTTPS** (atau localhost); lewat HTTP biasa tetap bisa dipakai sebagai shortcut.

## Terhubung ke Inventaris Umum & IT

Peran di kedua aplikasi sama: Superadmin, Manajer, Kepala Regu, Staf. Akun tidak memakai email. Data **karyawan** (yang masuk HaloDek dengan NIK) diambil otomatis oleh Inventaris lewat `GET /api/v1/employees` sebagai daftar pemegang aset. Token yang dipakai sama: `INVENTARIS_API_TOKEN`.

Isi di `.env` HaloDek, lalu `php artisan config:clear`:

```
INVENTARIS_URL=http://localhost:8082           # alamat aplikasi Inventaris
INVENTARIS_API_TOKEN=isi-token-acak-min-16-karakter   # SAMA dengan INVENTARIS_API_TOKEN di .env Inventaris
```

- **Form lapor:** pilihan lokasi dan barang diambil dari Inventaris. Barang yang muncul: yang dipegang pelapor (dicocokkan lewat **NIK**), barang bersama di lokasi terpilih, atau barang yang baru dipindai QR-nya. Regu dicocokkan ke unit Inventaris lewat kode yang sama (`it`, `sarpras`, `kesekretariatan`, `keamanan`).
- **Salinan data:** kode/nama barang dan nama lokasi disalin ke laporan, jadi laporan lama tetap terbaca walau barang dihapus atau Inventaris sedang mati.
- **Kabar balik:** laporan yang terkait barang otomatis dikirim ke Inventaris saat dibuat dan setiap ganti status, sehingga muncul di riwayat aset.
- **Jika Inventaris mati / belum dipasang:** HaloDek tetap jalan. Form lapor hanya tanpa pilihan lokasi/barang. Kegagalan dicatat di `storage/logs`.
- **Pindah dari HaloDek lama (versi yang masih punya menu Inventaris):** jalankan `php artisan migrate` di HaloDek (tabel inventaris lama **tidak dihapus**), lalu di aplikasi Inventaris jalankan `php artisan inventaris:impor-halodek`. ID barang dan lokasi dipertahankan sehingga laporan lama tetap tersambung. Setelah yakin data lengkap di Inventaris, tabel `inventory_items`, `inventory_categories`, dan `locations` di database HaloDek boleh dibiarkan atau dihapus manual.

## Jam kerja, batas respons, dan pengingat

- Semua durasi (rata-rata penyelesaian, respons pertama, ekspor) dihitung dalam **jam kerja Senin–Jumat 07.00–16.00 WIB**, bukan jam kalender. Ubah di `config/halodek.php` → `work_hours`.
- Hari libur: tambahkan di `.env`, mis. `HALODEK_LIBUR=2026-12-25,2027-01-01`, lalu `php artisan config:clear`.
- **Batas respons** (jam kerja) per prioritas: Mendesak 1, Tinggi 2, Sedang 4, Rendah 8 (`response_sla`). Laporan yang belum diambil melewati batas ditandai **Lewat batas** di dasbor, daftar, dan detail.
- **Pengingat WA ke Kepala Regu** (ke nomor di profilnya; jika kosong, ke `WA_TO`) dikirim sekali per laporan, hanya pada jam kerja.

Pengingat berjalan **otomatis dari dalam aplikasi**, tanpa Task Scheduler atau cron: setiap kali ada yang membuka HaloDek, sistem mengecek paling sering sekali per 15 menit. Selama HaloDek dipakai pada jam kerja, pengingat terkirim. Uji manual: `php artisan halodek:pengingat --dry-run`. Matikan dengan `HALODEK_PENGINGAT_OTOMATIS=false` di `.env`.

**Pembersihan otomatis** berjalan dengan cara yang sama, sekali sehari: notifikasi yang sudah dibaca > 90 hari (belum dibaca > 180 hari), log audit > 365 hari, foto milik laporan yang sudah dihapus, dan berkas foto yatim. Atur masa simpan di `config/halodek.php` → `retention`. Cek manual: `php artisan halodek:bersihkan --dry-run`. Matikan dengan `HALODEK_PEMBERSIHAN_OTOMATIS=false`.

> Setelah memperbarui kode, selalu jalankan `php artisan migrate` (menambah kolom baru dan mengisi kata kunci kategori bawaan).

## Catatan keamanan

- Jangan pernah meng-commit file `.env` (sudah diabaikan oleh Git).
- Untuk server sungguhan: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, pakai HTTPS, arahkan web root ke folder `public`, dan jangan jalankan `DemoSeeder`.

## Pengujian

```bash
php vendor/bin/phpunit
```
