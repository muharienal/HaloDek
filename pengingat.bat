@echo off
REM ==========================================================================
REM  HaloDek - pengingat laporan lewat batas respons.
REM  Jadwalkan file ini di Windows Task Scheduler setiap 15 menit (lihat README).
REM  Jika "php" tidak dikenali, ganti dengan path lengkap, mis.:
REM    C:\laragon\bin\php\php-8.3.xx-Win32-vs16-x64\php.exe
REM ==========================================================================
cd /d "%~dp0"
php artisan halodek:pengingat >> storage\logs\pengingat.log 2>&1