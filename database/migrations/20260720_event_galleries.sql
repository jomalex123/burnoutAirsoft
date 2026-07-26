CREATE TABLE IF NOT EXISTS gallery_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY gallery_events_slug_unique (slug),
  UNIQUE KEY gallery_events_event_id_unique (event_id),
  KEY gallery_events_visible_index (is_visible),
  CONSTRAINT gallery_events_event_id_foreign
    FOREIGN KEY (event_id) REFERENCES events (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_event_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gallery_event_id BIGINT UNSIGNED NOT NULL,
  src TEXT NULL,
  original_name VARCHAR(255) DEFAULT NULL,
  image_mime VARCHAR(80) DEFAULT NULL,
  image_data LONGBLOB NULL,
  image_size INT UNSIGNED NOT NULL DEFAULT 0,
  image_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY gallery_event_images_event_index (gallery_event_id, id),
  CONSTRAINT gallery_event_images_event_id_foreign
    FOREIGN KEY (gallery_event_id) REFERENCES gallery_events (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
