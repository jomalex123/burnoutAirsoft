CREATE TABLE IF NOT EXISTS instagram_cache_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instagram_id VARCHAR(80) NOT NULL,
  caption TEXT DEFAULT NULL,
  media_type VARCHAR(40) DEFAULT NULL,
  permalink VARCHAR(500) NOT NULL,
  published_at DATETIME DEFAULT NULL,
  image_mime VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
  image_data LONGBLOB NOT NULL,
  image_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_hash CHAR(64) DEFAULT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY instagram_cache_items_instagram_id_unique (instagram_id),
  KEY instagram_cache_items_visible_published_index (is_visible, published_at),
  KEY instagram_cache_items_fetched_at_index (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS instagram_cache_state (
  state_key VARCHAR(80) NOT NULL,
  state_value MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (state_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
