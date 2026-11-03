-- Partidas internas del HQ: no publican eventos ni abren inscripciones.
CREATE TABLE IF NOT EXISTS hq_operations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  operation_date DATE NOT NULL,
  status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_access (
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  PRIMARY KEY (operation_id, team),
  CONSTRAINT hq_access_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_missions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  title VARCHAR(180) NOT NULL,
  briefing TEXT NOT NULL,
  status ENUM('draft', 'active', 'completed', 'failed') NOT NULL DEFAULT 'draft',
  acknowledged_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY hq_missions_operation_team (operation_id, team, id),
  CONSTRAINT hq_missions_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hq_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  operation_id INT UNSIGNED NOT NULL,
  team ENUM('alpha', 'bravo') NOT NULL,
  sender ENUM('hq', 'alpha', 'bravo') NOT NULL,
  body VARCHAR(1500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY hq_messages_channel (operation_id, team, id),
  CONSTRAINT hq_messages_operation_fk FOREIGN KEY (operation_id) REFERENCES hq_operations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
