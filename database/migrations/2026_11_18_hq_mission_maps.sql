-- Mapas del catalogo de misiones; independiente del mapa temporal de cada partida.
CREATE TABLE IF NOT EXISTS hq_mission_maps (
  catalog_id INT UNSIGNED NOT NULL,
  annotations MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (catalog_id),
  CONSTRAINT hq_mission_maps_catalog_fk FOREIGN KEY (catalog_id)
    REFERENCES hq_mission_catalog(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
