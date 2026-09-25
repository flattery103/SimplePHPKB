CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 display_name VARCHAR(120) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT NOT NULL DEFAULT 1,
 session_version INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO categories (id,name) VALUES (1,'General');
CREATE TABLE IF NOT EXISTS articles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 draft_title VARCHAR(200) NOT NULL DEFAULT '',
 draft_category_id INT UNSIGNED NULL,
 draft_keywords VARCHAR(500) NOT NULL DEFAULT '',
 draft_html MEDIUMTEXT NOT NULL,
 lock_version INT UNSIGNED NOT NULL DEFAULT 1,
 published_revision_id INT UNSIGNED NULL,
 trashed_at DATETIME NULL,
 view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 created_by INT UNSIGNED NOT NULL,
 updated_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (draft_category_id) REFERENCES categories(id),
 FOREIGN KEY (created_by) REFERENCES admins(id),
 FOREIGN KEY (updated_by) REFERENCES admins(id),
 INDEX public_listing (trashed_at, published_revision_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS revisions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 article_id INT UNSIGNED NOT NULL,
 title VARCHAR(200) NOT NULL,
 category_id INT UNSIGNED NULL,
 category_name VARCHAR(100) NOT NULL,
 keywords VARCHAR(500) NOT NULL,
 html MEDIUMTEXT NOT NULL,
 plain_text MEDIUMTEXT NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (article_id) REFERENCES articles(id),
 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
 FOREIGN KEY (created_by) REFERENCES admins(id),
 INDEX article_history (article_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media (
 id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 article_id INT UNSIGNED NOT NULL,
 mime VARCHAR(30) NOT NULL,
 size_bytes INT UNSIGNED NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 created_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (article_id) REFERENCES articles(id),
 FOREIGN KEY (created_by) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS revision_media (
 revision_id INT UNSIGNED NOT NULL,
 media_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 PRIMARY KEY (revision_id,media_id),
 FOREIGN KEY (revision_id) REFERENCES revisions(id),
 FOREIGN KEY (media_id) REFERENCES media(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS search_terms (
 term VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin PRIMARY KEY,
 term_length SMALLINT UNSIGNED NOT NULL,
 INDEX by_length (term_length,term)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS search_postings (
 term VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 article_id INT UNSIGNED NOT NULL,
 weight SMALLINT UNSIGNED NOT NULL,
 PRIMARY KEY (term,article_id),
 INDEX by_article (article_id),
 FOREIGN KEY (article_id) REFERENCES articles(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS article_views (
 article_id INT UNSIGNED NOT NULL,
 session_hash CHAR(64) CHARACTER SET ascii NOT NULL,
 view_day DATE NOT NULL,
 PRIMARY KEY (article_id,session_hash,view_day),
 INDEX old_views (view_day),
 FOREIGN KEY (article_id) REFERENCES articles(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_attempts (
 bucket CHAR(64) CHARACTER SET ascii PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL,
 window_start DATETIME NOT NULL,
 INDEX old_attempts (window_start)
) ENGINE=InnoDB;
