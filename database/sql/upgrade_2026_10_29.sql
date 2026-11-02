-- =====================================================================
-- HaloDek — UPGRADE DATABASE 2026-10-29
-- Dari skema lama (admin_it / admin_sarpras / admin_regu, SLA, rating, aset)
-- ke skema baru (Kepala Regu / Staf, 4 regu, inventaris per regu).
--
-- Target  : MySQL 8 / MariaDB 10.5+.
-- Cara    : 1) BACKUP database dulu (mysqldump).
--           2) mysql -u USER -p halodek < database/sql/upgrade_2026_10_29.sql
--           3) php artisan optimize:clear
-- Catatan : - Jalankan SEKALI saja pada database dari migration LAMA.
--           - Database baru/kosong cukup `php artisan migrate --seed`; tidak perlu file ini.
--           - Tabel `migrations` tidak berubah (nama file migration sama), jadi
--             `php artisan migrate` tidak akan menjalankan ulang apa pun.
--           - DDL di MySQL tidak bisa di-rollback; rollback = restore backup.
-- =====================================================================

SET NAMES utf8mb4;
SET @now = NOW();

-- ---------------------------------------------------------------------
-- 1. REGU: samakan 2 regu lama, tambah Kesekretariatan & Keamanan
-- ---------------------------------------------------------------------

UPDATE teams SET name='IT', icon='server', color='blue', description='Komputer, jaringan, aplikasi, email, dan akses sistem', is_system=1, is_active=1, sort_order=1, updated_at=@now WHERE slug='it';
UPDATE teams SET name='Sarana dan Prasarana', icon='building', color='amber', description='Gedung, kendaraan, kelistrikan, furniture, kebersihan, AC', is_system=1, is_active=1, sort_order=2, updated_at=@now WHERE slug='sarpras';
INSERT INTO teams (name, slug, code, description, icon, color, is_active, is_system, sort_order, created_at, updated_at)
SELECT 'Kesekretariatan', 'kesekretariatan', 'KS', 'Surat menyurat, arsip, perlengkapan kantor, ruang rapat, dan perjalanan dinas', 'document', 'violet', 1, 1, 3, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE slug='kesekretariatan') AND NOT EXISTS (SELECT 1 FROM teams WHERE code='KS');
INSERT INTO teams (name, slug, code, description, icon, color, is_active, is_system, sort_order, created_at, updated_at)
SELECT 'Keamanan', 'keamanan', 'KM', 'Keamanan area, kehilangan barang, akses gedung, CCTV, dan keselamatan', 'shield', 'rose', 1, 1, 4, @now, @now FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM teams WHERE slug='keamanan') AND NOT EXISTS (SELECT 1 FROM teams WHERE code='KM');

-- Kategori laporan untuk regu baru (regu lama dibiarkan apa adanya)
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Surat Menyurat', 0, 1, 1, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Arsip & Dokumen', 0, 1, 2, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Perlengkapan Kantor (ATK)', 0, 1, 3, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Ruang Rapat & Acara', 0, 1, 4, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Perjalanan Dinas', 0, 1, 5, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Ekspedisi & Kurir', 0, 1, 6, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Lainnya', 1, 1, 999, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Keamanan Area', 0, 1, 1, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Kehilangan Barang', 0, 1, 2, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Akses Gedung & Parkir', 0, 1, 3, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'CCTV', 0, 1, 4, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Kecelakaan / Near Miss', 0, 1, 5, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'APAR & Evakuasi', 0, 1, 6, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO categories (team_id, name, allow_custom, is_active, sort_order, created_at, updated_at) SELECT id, 'Lainnya', 1, 1, 999, @now, @now FROM teams WHERE slug='keamanan';

-- ---------------------------------------------------------------------
-- 2. TABEL INVENTARIS BARU
-- ---------------------------------------------------------------------
CREATE TABLE inventory_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY inventory_categories_team_id_name_unique (team_id, name),
  CONSTRAINT inventory_categories_team_id_foreign FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_id BIGINT UNSIGNED NOT NULL,
  inventory_category_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'tersedia',
  holder_id BIGINT UNSIGNED NULL,
  location_id BIGINT UNSIGNED NULL,
  acquired_at DATE NULL,
  due_date DATE NULL,
  note TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY inventory_items_team_id_code_unique (team_id, code),
  KEY inventory_items_status_index (status),
  KEY inventory_items_due_date_index (due_date),
  KEY inventory_items_inventory_category_id_foreign (inventory_category_id),
  KEY inventory_items_holder_id_foreign (holder_id),
  KEY inventory_items_location_id_foreign (location_id),
  CONSTRAINT inventory_items_team_id_foreign FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE,
  CONSTRAINT inventory_items_inventory_category_id_foreign FOREIGN KEY (inventory_category_id) REFERENCES inventory_categories (id),
  CONSTRAINT inventory_items_holder_id_foreign FOREIGN KEY (holder_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT inventory_items_location_id_foreign FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategori inventaris bawaan setiap regu
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Laptop & PC', 1, @now, @now FROM teams WHERE slug='it';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Printer & Scanner', 2, @now, @now FROM teams WHERE slug='it';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Jaringan', 3, @now, @now FROM teams WHERE slug='it';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Software', 4, @now, @now FROM teams WHERE slug='it';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Akses & Lisensi', 5, @now, @now FROM teams WHERE slug='it';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'AC', 1, @now, @now FROM teams WHERE slug='sarpras';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Kendaraan', 2, @now, @now FROM teams WHERE slug='sarpras';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Ruangan', 3, @now, @now FROM teams WHERE slug='sarpras';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Furniture', 4, @now, @now FROM teams WHERE slug='sarpras';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Kelistrikan', 5, @now, @now FROM teams WHERE slug='sarpras';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Perlengkapan Kantor', 1, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Arsip', 2, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Ruang Rapat', 3, @now, @now FROM teams WHERE slug='kesekretariatan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'APAR & Pemadam', 1, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'CCTV', 2, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Perlengkapan Jaga', 3, @now, @now FROM teams WHERE slug='keamanan';
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at) SELECT id, 'Akses & Gerbang', 4, @now, @now FROM teams WHERE slug='keamanan';

-- ---------------------------------------------------------------------
-- 3. PINDAHKAN DATA INVENTARIS LAMA
-- ---------------------------------------------------------------------
-- 3a. Aset: tiap "jenis" lama menjadi kategori inventaris regunya. ID dipertahankan
--     agar reports.asset_id tetap menunjuk item yang sama.
INSERT IGNORE INTO inventory_categories (team_id, name, sort_order, created_at, updated_at)
SELECT DISTINCT team_id, LEFT(type, 100), 50, @now, @now FROM assets;

INSERT INTO inventory_items (id, team_id, inventory_category_id, code, name, status, holder_id, location_id, acquired_at, due_date, note, created_at, updated_at)
SELECT a.id, a.team_id, c.id, a.code, LEFT(a.brand, 150), a.status, a.assigned_user_id, a.location_id, a.purchase_date,
       CASE WHEN a.warranty_date IS NULL THEN a.next_service_date
            WHEN a.next_service_date IS NULL THEN a.warranty_date
            ELSE LEAST(a.warranty_date, a.next_service_date) END,
       NULLIF(CONCAT_WS(' | ', a.note,
            IF(a.warranty_date IS NULL, NULL, CONCAT('Garansi s/d ', a.warranty_date)),
            IF(a.next_service_date IS NULL, NULL, CONCAT('Servis berikutnya ', a.next_service_date))), ''),
       a.created_at, a.updated_at
FROM assets a
JOIN inventory_categories c ON c.team_id = a.team_id AND c.name = LEFT(a.type, 100);

-- 3b. Software & lisensi akses (milik regu IT) -> kategori "Software" / "Akses & Lisensi".
INSERT INTO inventory_items (team_id, inventory_category_id, code, name, status, due_date, note, created_at, updated_at)
SELECT t.id, c.id, CONCAT('SW-', s.id), LEFT(s.name, 150), 'digunakan', s.expiry_date,
       NULLIF(CONCAT_WS(' | ', CONCAT('Lisensi terpakai ', s.used_license, ' dari ', s.total_license),
            IF(s.installed_on IS NULL OR s.installed_on = '', NULL, CONCAT('Terpasang di: ', s.installed_on)), s.note), ''),
       s.created_at, s.updated_at
FROM software s
JOIN teams t ON t.slug = 'it'
JOIN inventory_categories c ON c.team_id = t.id AND c.name = 'Software';

INSERT INTO inventory_items (team_id, inventory_category_id, code, name, status, acquired_at, due_date, note, created_at, updated_at)
SELECT t.id, c.id, CONCAT('AKS-', l.id), LEFT(CONCAT(l.system_name, ' - ', l.holder_name), 150), 'digunakan', l.granted_date, l.expiry_date,
       NULLIF(CONCAT_WS(' | ', IF(l.account_id IS NULL OR l.account_id = '', NULL, CONCAT('Akun: ', l.account_id)), l.note), ''),
       l.created_at, l.updated_at
FROM access_licenses l
JOIN teams t ON t.slug = 'it'
JOIN inventory_categories c ON c.team_id = t.id AND c.name = 'Akses & Lisensi';

-- ---------------------------------------------------------------------
-- 4. PENGGUNA: satu regu per orang (users.team_id) + role baru
-- ---------------------------------------------------------------------
ALTER TABLE users
  ADD COLUMN team_id BIGINT UNSIGNED NULL AFTER role,
  ADD CONSTRAINT users_team_id_foreign FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE SET NULL;

-- Admin IT / Admin Sarpras -> Staf di regunya.
UPDATE users u JOIN teams t ON t.slug = 'it'      SET u.team_id = t.id, u.role = 'staf' WHERE u.role = 'admin_it';
UPDATE users u JOIN teams t ON t.slug = 'sarpras' SET u.team_id = t.id, u.role = 'staf' WHERE u.role = 'admin_sarpras';

-- Admin Regu -> Staf di regu pertama yang ia ikuti (id regu terkecil).
UPDATE users u
JOIN (SELECT user_id, MIN(team_id) AS team_id FROM team_user GROUP BY user_id) m ON m.user_id = u.id
SET u.team_id = m.team_id, u.role = 'staf'
WHERE u.role = 'admin_regu';

-- Admin Regu tanpa regu tidak bisa jadi Staf -> kembali Karyawan (login cukup NIK).
UPDATE users SET role = 'karyawan' WHERE role = 'admin_regu';

-- Regu yang belum punya Kepala Regu: Staf dengan id terkecil dijadikan Kepala Regu.
-- (Periksa & sesuaikan di menu Manajemen Pengguna / halaman Regu setelah upgrade.)
UPDATE users u
JOIN (SELECT s.team_id, MIN(s.id) AS id
      FROM users s
      WHERE s.role = 'staf' AND s.team_id IS NOT NULL
        AND s.team_id NOT IN (SELECT h.team_id FROM (SELECT team_id FROM users WHERE role = 'kepala_regu' AND team_id IS NOT NULL) h)
      GROUP BY s.team_id) p ON p.id = u.id
SET u.role = 'kepala_regu';

-- ---------------------------------------------------------------------
-- 5. LAPORAN: aset -> item inventaris; buang SLA & rating
-- ---------------------------------------------------------------------
ALTER TABLE reports DROP FOREIGN KEY reports_asset_id_foreign;
ALTER TABLE reports DROP INDEX reports_asset_id_foreign;
ALTER TABLE reports CHANGE asset_id inventory_item_id BIGINT UNSIGNED NULL;
ALTER TABLE reports ADD CONSTRAINT reports_inventory_item_id_foreign FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL;

ALTER TABLE reports DROP INDEX reports_due_at_index;
ALTER TABLE reports DROP COLUMN due_at, DROP COLUMN rating, DROP COLUMN rating_comment;

DELETE FROM report_activities WHERE type = 'rating';

-- ---------------------------------------------------------------------
-- 6. BUANG SLA DARI REGU & KATEGORI, HAPUS TABEL LAMA
-- ---------------------------------------------------------------------
ALTER TABLE teams DROP COLUMN sla_hours;
ALTER TABLE categories DROP COLUMN sla_hours;

DROP TABLE team_user;
DROP TABLE access_licenses;
DROP TABLE software;
DROP TABLE assets;

-- Selesai. Jalankan: php artisan optimize:clear
