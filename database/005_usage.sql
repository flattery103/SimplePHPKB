CREATE TABLE IF NOT EXISTS usage_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO usage_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS site_visits (
 session_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 view_day DATE NOT NULL,
 PRIMARY KEY (session_hash,view_day),
 INDEX old_visits (view_day)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_daily (
 view_day DATE PRIMARY KEY,
 visit_count BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS article_daily (
 article_id INT UNSIGNED NOT NULL,
 view_day DATE NOT NULL,
 view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY (article_id,view_day),
 INDEX by_day (view_day,article_id),
 FOREIGN KEY (article_id) REFERENCES articles(id)
) ENGINE=InnoDB;
