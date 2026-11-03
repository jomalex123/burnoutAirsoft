-- Aplicar después de 2026_10_30_add_hq.sql.
-- Las operaciones antiguas sin evento se conservan, pero no aparecen en los selectores.
ALTER TABLE hq_operations
  ADD COLUMN event_id INT UNSIGNED DEFAULT NULL AFTER id,
  ADD UNIQUE KEY hq_operations_event_unique (event_id),
  ADD CONSTRAINT hq_operations_event_fk FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;
