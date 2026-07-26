CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) DEFAULT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY admin_users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  success TINYINT(1) NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY admin_login_audit_username_index (username),
  KEY admin_login_audit_created_at_index (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope VARCHAR(80) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  blocked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY rate_limits_scope_identifier_unique (scope, identifier_hash),
  KEY rate_limits_blocked_until_index (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_date DATE NOT NULL,
  title VARCHAR(180) NOT NULL,
  time_slot ENUM('M', 'T', 'N') NOT NULL,
  max_attendees INT UNSIGNED NOT NULL DEFAULT 40,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY events_event_date_index (event_date),
  KEY events_time_slot_index (time_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  team_name VARCHAR(120) DEFAULT NULL,
  accepted_rules TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registrations_event_id_index (event_id),
  KEY registrations_email_index (email),
  CONSTRAINT registrations_event_id_foreign
    FOREIGN KEY (event_id) REFERENCES events (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_attendees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  document VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registration_attendees_registration_id_index (registration_id),
  CONSTRAINT registration_attendees_registration_id_foreign
    FOREIGN KEY (registration_id) REFERENCES registrations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registration_email_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY registration_email_notifications_registration_id_index (registration_id),
  KEY registration_email_notifications_status_index (status),
  CONSTRAINT registration_email_notifications_registration_id_foreign
    FOREIGN KEY (registration_id) REFERENCES registrations (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  src TEXT NOT NULL,
  alt VARCHAR(190) NOT NULL,
  description TEXT DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  image_data MEDIUMBLOB NULL,
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

CREATE TABLE IF NOT EXISTS instagram_cache_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instagram_id VARCHAR(80) NOT NULL,
  caption TEXT DEFAULT NULL,
  media_type VARCHAR(40) DEFAULT NULL,
  permalink VARCHAR(500) NOT NULL,
  published_at DATETIME DEFAULT NULL,
  image_mime VARCHAR(80) NOT NULL DEFAULT 'image/jpeg',
  image_data MEDIUMBLOB NOT NULL,
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
