# HaloDek

## Instalasi di Windows

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
## Pengujian

```bash
php vendor/bin/phpunit
```
