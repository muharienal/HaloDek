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

Notifikasi dikirim lewat **Fonnte**. Semua diatur Superadmin di **Administrasi → WhatsApp**, tanpa mengubah `.env`:

1. Di fonnte.com buka **Device**, tambahkan nomor pengirim, pindai QR sampai *connect*, lalu salin **Token**.
2. Di HaloDek: tempel token, pilih status **Aktif**, atur siapa menerima apa, simpan.
3. Kirim pesan uji ke nomor Anda.

Token disimpan terenkripsi dengan `APP_KEY` dan tidak pernah ditampilkan lagi (bila `APP_KEY` diganti, isi ulang token). Setelah halaman itu disimpan sekali, isinya mengalahkan baris `WA_*` di `.env` (baris itu boleh dihapus).

**Siapa menerima apa** diatur per peran (Superadmin, Manajer, Kepala Regu, Staf Regu, Karyawan) di menu yang sama. Pesan dikirim ke No. WhatsApp di profil tiap pengguna, dan tiap pengguna bisa mematikan kabar tertentu untuk dirinya di **Profil › Kabar WhatsApp untuk saya**.

| Kabar | Bisa untuk | Bawaan |
|---|---|---|
| Laporan baru (Tidak / Penting / Semua) | Superadmin & Manajer: semua regu; Kepala Regu & Staf: regunya | Kepala Regu: prioritas penting |
| Ditugaskan ke saya (penugasan massal digabung satu pesan) | Superadmin, Manajer, Kepala Regu, Staf | aktif |
| Banding & dibuka lagi | Kepala Regu, Manajer, Superadmin; Staf bila ia penanggung jawabnya | Kepala Regu & Staf |
| Lewat batas respons | Superadmin, Manajer, Kepala Regu, Staf | Kepala Regu |
| Ringkasan harian (Kepala Regu: rekap regunya) | Superadmin, Manajer, Kepala Regu | Manajer |
| Status laporan saya (diproses/selesai/ditolak) | semua peran | aktif |
| Balasan baru | semua peran | mati |

- Satu pesan ke banyak penerima dikirim dalam **satu** panggilan Fonnte (batas tunggu 5 detik), jadi aplikasi tidak melambat walau penerimanya banyak.
- Laporan berprioritas penting, banding, dan pengingat lewat batas yang tidak punya penerima sama sekali tetap dikirim ke Manajer (atau Superadmin bila belum ada Manajer ber-No. WA) agar tidak terlewat.
- Ringkasan manual: `php artisan halodek:ringkasan-harian` (tambah `--uji` untuk melihat isinya saja).
- Status perangkat Fonnte juga tampil di **Administrasi → Status Sistem**.

Setiap pengguna mengisi nomor WhatsApp sendiri (popup saat login, atau menu Profil); Superadmin juga bisa mengisinya di Manajemen Pengguna. Hanya satu nomor per pengguna (kolom No. WhatsApp), otomatis diformat `62…`; selama belum diisi, header menampilkan lencana "Isi nomor WA".

## Fitur lain

- **Laporan Bulanan:** menu Kelola Laporan → Laporan Bulanan. Ringkasan per regu (jumlah, waktu rata-rata, kategori terbanyak); unduh Excel atau cetak/simpan PDF.
- **Pencarian:** ikon kaca pembesar di bilah atas (pintasan tombol `/`). Hasil mengikuti hak akses. Di halaman daftar laporan, kolom yang sama langsung menyaring daftar itu.
- **Tutup cepat:** di detail laporan ada tombol **Selesaikan** dan **Tolak** (satu catatan wajib). Ubah status manual tetap ada untuk kasus khusus.
- **Struktur Organisasi** (Superadmin, menu Administrasi): daftar **Divisi** (mis. "SDM, Umum & K3") → **Bidang** (mis. "Umum & IT") → **Regu** (IT, Keamanan, …), daftar **Jabatan**, serta nama & penjelasan **Peran**. Semua bisa ditambah, diubah, dan dihapus (hapus ditolak bila masih dipakai). Data pengguna memilih dari daftar ini (dropdown): Divisi → Bidang → Jabatan, lalu Peran dan Regu. Memilih regu otomatis mengisi bidang & divisinya. Hak akses peran tetap; yang bisa diubah hanya nama dan penjelasannya. Impor CSV pengguna memakai kolom `divisi, bidang, jabatan` dengan nama persis seperti di daftar. Inventaris mengambil data diri semua pegawai dari HaloDek; di Inventaris hanya diatur siapa yang boleh masuk.
- **Laporan ganda:** saat melapor barang yang masih punya laporan aktif, pelapor diminta memastikan dulu (bisa langsung memantau laporan yang ada). Petugas bisa menutup laporan ganda dengan **Gabungkan (Laporan Ganda)** di detail laporan: laporan ditutup, ditautkan ke laporan utama, dan pelapor diberi tahu (tanpa banding).
- **Banding:** laporan yang ditolak bisa diajukan banding sekali oleh pelapor (dengan alasan, paling lama `reopen_days` hari setelah ditolak). Laporan kembali **Menunggu** dan Kepala Regu diberi tahu. Penolakan setelah banding bersifat final.
- **Status Sistem** (Superadmin, menu Administrasi): cek koneksi dan tabel database (termasuk tabel rusak), migrasi yang belum dijalankan, koneksi Inventaris, perangkat Fonnte, folder penyimpanan, sisa disk, tugas otomatis, dan error terakhir dari log.
- **Konsistensi tugas:** pengguna yang masih memegang laporan aktif tidak bisa dinonaktifkan, dihapus, dipindah regu, atau diubah perannya sebelum laporannya dialihkan. Regu dengan laporan aktif tidak bisa dinonaktifkan. Status *Menunggu* selalu berarti belum ada penanggung jawab. Laporan yang dibatalkan pelapor tidak bisa diubah lagi. Bila dua petugas menekan *Ambil* bersamaan, yang kedua mendapat pesan "baru saja diambil".
- **Sudah beres?** Setelah laporan selesai, pelapor menjawab 👍/👎. Pilih *Belum beres* membuka kembali laporan ke penanggung jawabnya (maks. 7 hari, atur `reopen_days` di `config/halodek.php`).
- **Form lapor ringkas:** pelapor bisa mengetik masalahnya (mis. "wifi", "AC") untuk langsung memilih kategori dan regu. Tidak perlu mengisi judul: judul dibuat otomatis "Kategori - Lokasi"; pelapor cukup menulis deskripsi. Kata kunci dan petunjuk deskripsi tiap kategori diatur Kepala Regu di menu Regu.
- **Aksi massal:** centang beberapa laporan aktif di Kelola Laporan, lalu ambil, tugaskan (Kepala Regu), selesaikan, atau tolak sekaligus (maks. 50).
- **Hubungi via WA:** tombol di detail laporan membuka chat WhatsApp ke pelapor dengan pesan pembuka terisi.
- **Laporan serupa:** saat melapor dengan kategori + lokasi (atau barang) yang sama dengan laporan yang masih aktif, pelapor diberi tahu dan bisa memantau laporan itu.
- **Template catatan cepat:** di popup Selesaikan/Tolak ada pilihan catatan siap pakai. Kepala Regu mengaturnya di menu Regu → Template Catatan (satu per baris).
- **Laporan bulanan** juga menampilkan persentase laporan yang diambil tepat waktu, kepuasan pelapor (jawaban "sudah beres"), dan jumlah laporan yang dibuka ulang.
- **QR di barang:** stiker QR dicetak dari aplikasi Inventaris dan membuka halaman cek aset Inventaris; tombol *Laporkan kendala* di sana membuka `{alamat HaloDek}/q/{id}`: karyawan masuk (NIK) → form lapor sudah terisi regu, kategori, lokasi, dan barang. Stiker lama yang langsung ke `{alamat HaloDek}/q/{id}` tetap berfungsi.
- **Riwayat perbaikan aset:** laporan yang terkait barang dikirim otomatis ke Inventaris beserta petugas, catatan penyelesaian, dan tanggal selesai, lalu tercatat sebagai riwayat perbaikan aset. Setelah memperbarui kedua aplikasi, kirim ulang laporan lama sekali saja: `php artisan halodek:kirim-ulang-inventaris`.
- **Ekspor laporan:** format Excel (.xlsx). Ekspor pengguna tetap CSV karena dipakai ulang untuk impor.

## Pasang sebagai aplikasi (HP & laptop)

HaloDek bisa dipasang seperti aplikasi: ikonnya ada di layar utama HP Android/iPhone atau di desktop/menu Start laptop, dan terbuka di jendela sendiri tanpa kolom alamat.
- Setiap kali login, muncul ajakan **Pasang sebagai aplikasi** selama aplikasi belum dipasang di perangkat itu. Untuk karyawan yang belum mengisi No. WhatsApp, ajakan ini ikut di jendela isi nomor WA (tidak muncul dua jendela). Bila sudah dipasang (atau pengguna menekan **Sudah Terpasang**), ajakan otomatis tidak muncul lagi.
- Tombol **Pasang Aplikasi** di header selalu tersedia (kecuali saat sudah dibuka sebagai aplikasi).
- Browser yang mendukung pemasangan langsung menampilkan tombol **Pasang Sekarang**. Selain itu muncul langkah singkat sesuai perangkat (Chrome Android, Safari iPhone, Chrome/Edge laptop).

**Penting, HTTPS:** pemasangan penuh (tombol Pasang Sekarang, ikon aplikasi asli, halaman offline) hanya diizinkan browser lewat **HTTPS** atau `localhost`. Lewat alamat `http://nama-komputer:port` di jaringan kantor, browser hanya bisa membuat **pintasan** (Android: "Tambahkan ke layar utama"; Chrome laptop: "Buat pintasan" + "Buka sebagai jendela"), yang tetap membuka aplikasi tanpa mengetik alamat. Agar terpasang penuh di semua HP, aktifkan HTTPS (lihat bagian berikut).

### Mengaktifkan HTTPS (agar "Pasang Aplikasi" cukup sekali klik)

Browser (Chrome, Edge, Safari) hanya mengizinkan pemasangan sekali klik lewat HTTPS. Ini aturan browser, tidak bisa diakali dari aplikasi. Langkah untuk Laragon di jaringan kantor (cukup sekali, berlaku untuk HaloDek dan Inventaris):

1. **Buat sertifikat.** Unduh `mkcert-v1.4.4-windows-amd64.exe` dari github.com/FiloSottile/mkcert/releases, ganti nama menjadi `mkcert.exe`, taruh di `C:\laragon\etc\ssl\`. Buka **CMD sebagai Administrator** di folder itu, lalu jalankan (ganti `192.168.1.10` dengan IP laptop server; sebaiknya IP-nya dibuat tetap):
   ```
   mkcert -install
   mkcert -cert-file pcs.pem -key-file pcs-key.pem laptop-3rt83m6j 192.168.1.10 localhost 127.0.0.1
   type "%LOCALAPPDATA%\mkcert\rootCA.pem" >> C:\laragon\etc\ssl\cacert.pem
   ```
   Baris ketiga membuat PHP mempercayai sertifikat ini, agar HaloDek dan Inventaris tetap bisa saling terhubung lewat https.
2. **Aktifkan di Apache.** Laragon > Apache > SSL > **Enabled**. Salin `deploy/laragon-https.conf.example` ke `C:\laragon\etc\apache2\sites-enabled\pcs-https.conf` (sesuaikan folder bila bukan `D:\halodek` / `D:\inventaris`), lalu **Reload** Apache. Izinkan port 8443 dan 8444 di Windows Firewall.
3. **Ubah .env lalu `php artisan optimize:clear`** di kedua aplikasi:
   - HaloDek: `APP_URL=https://192.168.1.10:8443`, `INVENTARIS_URL=https://192.168.1.10:8444`
   - Inventaris: `APP_URL=https://192.168.1.10:8444`, `QR_URL=https://192.168.1.10:8444`, `HALODEK_URL=https://192.168.1.10:8443`
   Setelah itu cetak ulang stiker QR.
4. **Percayakan sertifikat di tiap perangkat (sekali saja).** Salin `rootCA.pem` (dari `%LOCALAPPDATA%\mkcert\`):
   - HP Android: Setelan > Keamanan > Enkripsi & kredensial > Instal sertifikat > **Sertifikat CA**, pilih `rootCA.pem`.
   - iPhone: buka berkasnya, pasang profilnya, lalu Setelan > Umum > Info > Setelan Kepercayaan Sertifikat > aktifkan.
   - Laptop lain: klik dua kali berkas (ubah nama jadi `rootCA.crt`) > Install Certificate > Local Machine > **Trusted Root Certification Authorities**.

Setelah itu buka `https://192.168.1.10:8443`: tombol **Pasang Sekarang** muncul, satu klik langsung terpasang. Di laptop, aplikasinya langsung terbuka di jendela sendiri. Di HP, ikonnya langsung ada di layar utama.

Tanpa langkah 4 per perangkat: pakai domain kantor (mis. `halodek.perusahaan.co.id`) dengan sertifikat resmi (mis. Let's Encrypt). Sertifikat resmi langsung dipercaya semua HP tanpa memasang apa pun.

## Terhubung ke Inventaris Umum & IT

Peran di kedua aplikasi sama: Superadmin, Manajer, Kepala Regu, Staf. Akun tidak memakai email. Data **karyawan** (yang masuk HaloDek dengan NIK) diambil otomatis oleh Inventaris lewat `GET /api/v1/employees` sebagai daftar pemegang aset, beserta struktur Divisi & Bidang lewat `GET /api/v1/organization` (aset bisa dipegang satu karyawan, satu bidang, atau satu divisi). Di form lapor, barang milik bidang/divisi pelapor ikut muncul di pilihan barang. Token yang dipakai sama: `INVENTARIS_API_TOKEN`.

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

- Semua durasi (rata-rata penyelesaian, respons pertama, ekspor) dihitung dalam **jam kerja Senin-Jumat 07.00-16.00 WIB**, bukan jam kalender. Ubah di `config/halodek.php` → `work_hours`.
- Hari libur: tambahkan di `.env`, mis. `HALODEK_LIBUR=2026-12-25,2027-01-01`, lalu `php artisan config:clear`.
- **Batas respons** (jam kerja) per prioritas: Mendesak 1, Tinggi 2, Sedang 4, Rendah 8 (`response_sla`). Laporan yang belum diambil melewati batas ditandai **Lewat batas** di dasbor, daftar, dan detail.
- **Pengingat WA ke Kepala Regu** (sesuai aturan "Lewat batas respons"; tanpa penerima, ke Manajer) dikirim sekali per laporan, hanya pada jam kerja.

Pengingat berjalan **otomatis dari dalam aplikasi**, tanpa Task Scheduler atau cron: setiap kali ada yang membuka HaloDek, sistem mengecek paling sering sekali per 15 menit. Selama HaloDek dipakai pada jam kerja, pengingat terkirim. Uji manual: `php artisan halodek:pengingat --dry-run`. Matikan dengan `HALODEK_PENGINGAT_OTOMATIS=false` di `.env`.

**Pembersihan otomatis** berjalan dengan cara yang sama, sekali sehari: notifikasi yang sudah dibaca > 90 hari (belum dibaca > 180 hari), log audit > 365 hari, foto milik laporan yang sudah dihapus, dan berkas foto yatim. Atur masa simpan di `config/halodek.php` → `retention`. Cek manual: `php artisan halodek:bersihkan --dry-run`. Matikan dengan `HALODEK_PEMBERSIHAN_OTOMATIS=false`.

> Setelah memperbarui kode, selalu jalankan `php artisan migrate` (menambah kolom baru dan mengisi kata kunci kategori bawaan).

## Bila muncul error 500 atau halaman lambat

Buka **Administrasi → Status Sistem** lebih dulu: halaman itu menunjukkan tabel rusak, koneksi yang putus, dan error terakhir. Bila halaman itu sendiri tidak bisa dibuka:

1. Lihat penyebabnya di log (Terminal Laragon):
   ```bash
   grep "\.ERROR" storage/logs/laravel.log | tail -n 3 | cut -c1-400
   ```
2. `SQLSTATE[HY000] [2002] ... actively refused` berarti MySQL mati. Klik **Start All** di Laragon (atau start MySQL saja).
3. Foto gagal diunggah: batas PHP Laragon bawaan 2 MB per berkas dan 8 MB per kiriman. Form sudah mengecilkan foto otomatis; bila perlu, naikkan `upload_max_filesize` dan `post_max_size` di *Menu Laragon › PHP › php.ini*, lalu restart Apache.
4. Setelah mengubah `.env`, jalankan `php artisan optimize:clear`.
5. `Table '...' doesn't exist in engine`: tabel rusak (biasanya karena MySQL mati mendadak). Periksa dengan `mysqlcheck -u root halodek`, lalu pulihkan dari backup atau buat ulang tabelnya. Selalu **Stop All** di Laragon sebelum mematikan laptop.

## Tabel inventaris lama

Versi lama HaloDek menyimpan inventaris sendiri (`locations`, `inventory_categories`, `inventory_items`). Setelah datanya dipindah ke Inventaris (`php artisan inventaris:impor-halodek` di folder Inventaris), hapus tabel lama itu:

```bash
php artisan halodek:hapus-tabel-lama
```

Isinya dicadangkan dulu ke `storage/app/backup/inventaris-lama-*.json`.

## Catatan keamanan

- Jangan pernah meng-commit file `.env` (sudah diabaikan oleh Git).
- Untuk server sungguhan: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, pakai HTTPS, arahkan web root ke folder `public`, dan jangan jalankan `DemoSeeder`.

## Pengujian

```bash
php vendor/bin/phpunit
```

## Data contoh (uji coba)

Kosongkan semua data lalu isi data contoh yang lengkap (pengguna & struktur organisasi, aset, stok, laporan dengan berbagai status, riwayat 2 bulan). Akun contoh memakai **password = NIK** (karyawan HaloDek cukup NIK), jadi **jangan dipakai sebagai data sungguhan**.

Urutannya: Inventaris dulu, lalu HaloDek (agar laporan contoh menunjuk aset yang benar dan riwayatnya terkirim ke Inventaris). Kedua aplikasi harus menyala.

```bash
# di folder Inventaris
php artisan inventaris:data-contoh
# di folder HaloDek
php artisan halodek:data-contoh
```

Perintah meminta Anda mengetik `HAPUS` sebagai konfirmasi. WhatsApp dibiarkan Mati dan akun contoh tanpa No. WhatsApp.
