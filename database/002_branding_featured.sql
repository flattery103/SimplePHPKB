CREATE TABLE IF NOT EXISTS site_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 title VARCHAR(120) NULL,
 subtitle VARCHAR(200) NULL,
 logo_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
 logo_mime VARCHAR(30) NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO site_settings (id) VALUES (1);
SET @kb_featured_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='articles' AND COLUMN_NAME='featured');
SET @kb_featured_sql = IF(@kb_featured_exists=0, 'ALTER TABLE articles ADD COLUMN featured TINYINT UNSIGNED NOT NULL DEFAULT 0', 'DO 0');
PREPARE kb_featured_migration FROM @kb_featured_sql;
EXECUTE kb_featured_migration;
DEALLOCATE PREPARE kb_featured_migration;
