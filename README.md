# HaloDek — Layanan Pengaduan & Pelaporan Terpadu

Aplikasi web **Laravel 10** (pure PHP/Blade, **tanpa npm / Node / Vite / CDN**) untuk pengaduan karyawan ke empat regu:
**IT**, **Sarana dan Prasarana**, **Kesekretariatan**, dan **Keamanan** (Superadmin dapat menambah regu lain).

## Kebutuhan
PHP 8.1+ (pdo_mysql, mbstring, fileinfo, openssl, ctype, tokenizer, xml), MySQL 8 / MariaDB 10.5+, Composer 2 (bila `vendor/` tidak ada).

## Instalasi baru
```
cp .env.example .env
php artisan key:generate
# buat database "halodek", sesuaikan DB_* di .env
php artisan migrate --seed
php artisan serve
```

## Upgrade dari versi lama (database sudah berisi data)
1. Backup: `mysqldump -u USER -p halodek > backup.sql`
2. Timpa file project dengan isi paket ini, hapus file lama yang tercantum di `HAPUS-FILE-LAMA.txt`.
3. Jalankan **sekali**: `mysql -u USER -p halodek < database/sql/upgrade_2026_10_29.sql`
4. `php artisan optimize:clear`

Isi upgrade: tambah regu Kesekretariatan & Keamanan; `users.team_id`; role lama → baru (Admin IT / Admin Sarpras / Admin Regu → **Staf**,
satu Staf per regu tanpa kepala dijadikan **Kepala Regu**; Admin Regu tanpa regu → Karyawan); aset, software, dan lisensi akses → **item inventaris**
(`reports.asset_id` → `inventory_item_id`); kolom SLA/rating dihapus; tabel `team_user`, `assets`, `software`, `access_licenses` dihapus.
Setelah upgrade, cek **Manajemen Pengguna** / halaman **Regu** untuk memastikan Kepala Regu setiap regu sudah benar.

## Peran
| Peran | Login | Kemampuan |
|---|---|---|
| Superadmin | NIK + password | Semua regu, pengguna, lokasi, audit |
| Kepala Regu | NIK + password | Satu regu: tugaskan staf **atau tangani sendiri**, prioritas, alihkan regu, kategori layanan & kategori inventaris, hapus item, pengumuman |
| Staf | NIK + password | Satu regu: **ambil** laporan antrean atau kerjakan yang ditugaskan, ubah status + catatan, lepas tugas, kelola item inventaris |
| Karyawan | NIK saja | Ajukan & pantau laporan sendiri |

## Alur laporan
1. Karyawan mengajukan laporan ke sebuah regu → masuk **Antrean Regu** (belum ditugaskan); Kepala Regu & staf diberi tahu.
2. **Kepala Regu** menugaskan seorang staf, atau mengambilnya sendiri.
3. **Staf** menunggu penugasan, atau langsung **Ambil** laporan yang masih di antrean (tidak bisa mengambil milik orang lain).
4. Penanggung jawab mengubah status (Menunggu → Diproses → Selesai/Ditolak) dengan catatan; staf bisa **Lepas Tugas** → kembali ke antrean.
5. Dashboard menampilkan **rata-rata penyelesaian** (tanpa SLA dan tanpa rating).

## Inventaris per regu
Tiap regu punya kategori sendiri (mis. Laptop, AC, Ruangan, APAR) dan item di dalamnya (kode, nama, status, pemegang, lokasi, tanggal perolehan, jatuh tempo, catatan).
Kategori & hapus item: Kepala Regu/Superadmin. Tambah/ubah item: anggota regu. Impor/ekspor CSV tersedia. Karyawan dapat mengaitkan laporan ke item.

## Sesi berakhir (419)
Halaman login dikirim dengan `Cache-Control: no-store` sehingga token tidak basi dari cache browser; bila token tetap kedaluwarsa,
pengguna diarahkan kembali ke login dengan pesan ramah (bukan halaman 419), dan halaman login memuat ulang token otomatis saat tab kembali aktif.

## Akun demo (APP_ENV=local, `php artisan db:seed --class=DemoSeeder`)
Password = NIK. Superadmin 00001; Kepala Regu 00002 (IT), 00003 (Sarpras), 00006 (Kesekretariatan), 00015 (Keamanan); Staf 00007, 00011–00014, 00016, 00017; Karyawan 00004, 00005, 00008–00010.

## Keamanan

- **Login terpisah tegas.** Tab *Karyawan* hanya menerima akun karyawan (NIK saja). Tab *Admin* hanya menerima Kepala Regu, Staf, Superadmin dengan NIK + password. Akun karyawan tidak bisa masuk lewat tab Admin dengan password apa pun, dan sebaliknya. Pesan galat sengaja generik dan waktu respons disamakan agar NIK tidak bisa ditebak.
- **Tanpa password tebakan.** Pengelola baru / hasil reset mendapat password sementara acak yang tampil satu kali dan wajib diganti saat login pertama. Akun karyawan memakai password acak yang tidak diketahui siapa pun. Password baru minimal 10 karakter (huruf besar, kecil, angka). Mengganti password mengeluarkan perangkat lain.
- **Header keamanan.** CSP dengan nonce per permintaan, `X-Frame-Options: DENY`, `nosniff`, Referrer-Policy, Permissions-Policy, HSTS (saat HTTPS). Sesi dienkripsi; cookie "ingat saya" 14 hari.
- **Pembatasan laju** untuk login, penulisan data, kirim laporan, ganti password, impor/ekspor.
- **Lampiran** hanya foto (jpg/png/webp, maks. 5 foto), disimpan di storage privat, hanya bisa dibuka pihak berwenang; tipe selain foto selalu diunduh.
- **Checklist produksi:** `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HTTPS, web root = folder `public`, jangan jalankan `DemoSeeder` (hanya untuk lokal). Password Superadmin awal dicetak sekali oleh seeder di luar mode lokal.
- **Database lama:** akun pengelola yang password-nya masih sama dengan NIK harus di-reset (Pengguna → Atur ulang password).

## Notifikasi WhatsApp (opsional)

Setiap laporan **baru** (semua regu) dikirim ke nomor manager berisi nomor tiket, regu, judul, dan link ke laporannya. Perubahan status tidak dikirim. Atur di `.env`:

```
WA_DRIVER=log        # off | log | fonnte | wablas   (mulai dari "log": pesan hanya ditulis ke storage/logs)
WA_TO=628123456789   # nomor manager, pisahkan koma bila lebih dari satu
WA_TOKEN=            # token dari Fonnte / Wablas
WA_SECRET=           # Wablas saja, jika akun memakai secret key
WABLAS_URL=          # Wablas saja, server sesuai akun Anda
APP_URL=http://192.168.x.x:8000   # alamat yang bisa dibuka dari HP manager
```

Lalu `php artisan config:clear`. Gunakan nomor pengirim khusus (bukan nomor pribadi). Link di pesan membuka halaman pantau **hanya-baca tanpa login** (`/p/{id}/{token}`, token 16 karakter turunan `APP_KEY`: tidak bisa ditebak atau diubah; tanpa NIK, lampiran, maupun komentar). Siapa pun yang memegang tautan itu bisa melihat laporannya, jadi jangan disebar. Gagal kirim hanya dicatat di log dan tidak mengganggu laporan.

## Pengujian
```
php vendor/bin/phpunit      # SQLite in-memory
```

© 2026 IT PT Petrokopindo Cipta Selaras.
