-- ============================================================================
-- HaloDek — KOSONGKAN DATA, sisakan 1 Superadmin + 1 Karyawan
-- ----------------------------------------------------------------------------
-- DIHAPUS : semua laporan (+ riwayat, lampiran, notifikasi), pengumuman, log audit,
--           inventaris (barang), lokasi, dan semua pengguna selain 2 akun di bawah.
-- DIPERTAHANKAN : struktur tabel, 4 regu bawaan + kategori laporannya,
--                 kategori inventaris, serta 2 akun yang NIK-nya Anda isi di bawah.
--
-- CARA PAKAI (phpMyAdmin / HeidiSQL / Laragon):
--   1. BACKUP database dulu (Export). Skrip ini tidak bisa di-undo.
--   2. Ubah dua NIK pada baris "SET @..." di bawah.
--   3. Jalankan seluruh isi file ini pada database "halodek".
--   4. Hapus juga foto lampiran lama di komputer:  rm -rf storage/app/lampiran
--      (Git Bash, dari folder halodek)
-- ============================================================================

SET @nik_superadmin = '00001';       -- NIK Superadmin yang dipertahankan
SET @nik_karyawan   = 'LJ2501024';   -- NIK Karyawan yang dipertahankan  <-- ganti sesuai kebutuhan

-- Pratinjau akun yang akan dipertahankan (harus tampil 2 baris sebelum lanjut).
SELECT id, nik, name, role FROM users WHERE nik IN (@nik_superadmin, @nik_karyawan);

-- Urutan: tabel anak dulu, baru induknya. Memakai DELETE (bukan TRUNCATE) agar tetap
-- jalan walau pengecekan foreign key aktif (default phpMyAdmin).
DELETE FROM report_attachments;
DELETE FROM report_activities;
DELETE FROM user_notifications;
DELETE FROM reports;
DELETE FROM inventory_items;
DELETE FROM locations;
DELETE FROM announcements;
DELETE FROM audit_logs;
DELETE FROM failed_jobs;

DELETE FROM users WHERE nik NOT IN (@nik_superadmin, @nik_karyawan);

-- Dua akun yang tersisa: Superadmin dan Karyawan tidak terikat regu.
UPDATE users SET team_id = NULL WHERE nik IN (@nik_superadmin, @nik_karyawan);
UPDATE users SET role = 'superadmin' WHERE nik = @nik_superadmin;
UPDATE users SET role = 'karyawan'   WHERE nik = @nik_karyawan;

-- Nomor urut ID mulai dari awal lagi.
ALTER TABLE report_attachments AUTO_INCREMENT = 1;
ALTER TABLE report_activities  AUTO_INCREMENT = 1;
ALTER TABLE user_notifications AUTO_INCREMENT = 1;
ALTER TABLE reports            AUTO_INCREMENT = 1;
ALTER TABLE inventory_items    AUTO_INCREMENT = 1;
ALTER TABLE locations          AUTO_INCREMENT = 1;
ALTER TABLE announcements      AUTO_INCREMENT = 1;
ALTER TABLE audit_logs         AUTO_INCREMENT = 1;
ALTER TABLE failed_jobs        AUTO_INCREMENT = 1;
ALTER TABLE users              AUTO_INCREMENT = 1;

-- Hasil akhir: users = 2 baris, tabel data lain = 0 baris.
SELECT 'users' AS tabel, COUNT(*) AS jumlah FROM users
UNION ALL SELECT 'reports', COUNT(*) FROM reports
UNION ALL SELECT 'report_activities', COUNT(*) FROM report_activities
UNION ALL SELECT 'report_attachments', COUNT(*) FROM report_attachments
UNION ALL SELECT 'announcements', COUNT(*) FROM announcements
UNION ALL SELECT 'inventory_items', COUNT(*) FROM inventory_items
UNION ALL SELECT 'locations', COUNT(*) FROM locations
UNION ALL SELECT 'teams (dipertahankan)', COUNT(*) FROM teams
UNION ALL SELECT 'categories (dipertahankan)', COUNT(*) FROM categories;
