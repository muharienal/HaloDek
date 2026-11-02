# HaloDek - Layanan Pengaduan & Pelaporan Terpadu Bidang Umum & IT

## Instalasi baru
```
cp .env.example .env
php artisan key:generate
# buat database "halodek", sesuaikan DB_* di .env
php artisan migrate --seed
php artisan serve
```

© 2026 IT PT Petrokopindo Cipta Selaras.
