# HaloDek

Layanan pengaduan dan pelaporan terpadu PT Petrokopindo Cipta Selaras. Karyawan mengajukan laporan ke empat regu (**IT**, **Sarana dan Prasarana**, **Kesekretariatan**, **Keamanan**), lalu regu menangani dan memantaunya sampai selesai. Dibangun dengan Laravel 10 (PHP + Blade), tanpa npm / Node / CDN.

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
| Superadmin | NIK + password (tab Admin) | Semua regu, pengguna, lokasi, audit |
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
- **Ekspor laporan:** format Excel (.xlsx). Ekspor pengguna dan inventaris tetap CSV karena dipakai ulang untuk impor.
- **PWA:** ikon HaloDek bisa dipasang di layar HP. Pemasangan penuh dan mode offline hanya aktif lewat **HTTPS** (atau localhost); lewat HTTP biasa tetap bisa dipakai sebagai shortcut.

## Jam kerja, batas respons, dan pengingat

- Semua durasi (rata-rata penyelesaian, respons pertama, ekspor) dihitung dalam **jam kerja Senin–Jumat 07.00–16.00 WIB**, bukan jam kalender. Ubah di `config/halodek.php` → `work_hours`.
- Hari libur: tambahkan di `.env`, mis. `HALODEK_LIBUR=2026-12-25,2027-01-01`, lalu `php artisan config:clear`.
- **Batas respons** (jam kerja) per prioritas: Mendesak 1, Tinggi 2, Sedang 4, Rendah 8 (`response_sla`). Laporan yang belum diambil melewati batas ditandai **Lewat batas** di dasbor, daftar, dan detail.
- **Pengingat WA ke Kepala Regu** (ke nomor di profilnya; jika kosong, ke `WA_TO`) dikirim sekali per laporan, hanya pada jam kerja.

Pengingat berjalan **otomatis dari dalam aplikasi**, tanpa Task Scheduler atau cron: setiap kali ada yang membuka HaloDek, sistem mengecek paling sering sekali per 15 menit. Selama HaloDek dipakai pada jam kerja, pengingat terkirim. Uji manual: `php artisan halodek:pengingat --dry-run`. Matikan dengan `HALODEK_PENGINGAT_OTOMATIS=false` di `.env`.

> Setelah memperbarui kode, selalu jalankan `php artisan migrate` (menambah kolom baru dan mengisi kata kunci kategori bawaan).

## Catatan keamanan

- Jangan pernah meng-commit file `.env` (sudah diabaikan oleh Git).
- Untuk server sungguhan: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, pakai HTTPS, arahkan web root ke folder `public`, dan jangan jalankan `DemoSeeder`.

## Pengujian

```bash
php vendor/bin/phpunit
```
