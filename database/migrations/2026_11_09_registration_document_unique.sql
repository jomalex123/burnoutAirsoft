-- Crear copia previa e inspeccionar duplicados antes de aplicar.
-- Si el ultimo ALTER falla por duplicados, resolverlos manualmente y repetir solo ese ALTER.
ALTER TABLE registrations ADD UNIQUE KEY registrations_id_event_unique (id, event_id);
ALTER TABLE registration_attendees ADD COLUMN event_id INT UNSIGNED NULL;
UPDATE registration_attendees a INNER JOIN registrations r ON r.id = a.registration_id SET a.event_id = r.event_id;
ALTER TABLE registration_attendees
  MODIFY event_id INT UNSIGNED NOT NULL,
  ADD COLUMN document_key VARCHAR(40) GENERATED ALWAYS AS (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(document), ' ', ''), '-', ''), CHAR(9), ''), CHAR(10), ''), CHAR(11), ''), CHAR(12), ''), CHAR(13), '')) STORED,
  ADD UNIQUE KEY registration_attendees_event_document_unique (event_id, document_key),
  ADD CONSTRAINT registration_attendees_registration_event_foreign
    FOREIGN KEY (registration_id, event_id) REFERENCES registrations (id, event_id)
    ON UPDATE CASCADE ON DELETE CASCADE;
