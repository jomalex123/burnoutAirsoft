SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE registration_attendees
  MODIFY COLUMN document_key VARCHAR(40) GENERATED ALWAYS AS (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(document), ' ', ''), '-', ''), 0x09, ''), 0x0A, ''), 0x0B, ''), 0x0C, ''), 0x0D, '')) STORED,
  ADD COLUMN weapon_role ENUM('Fusilero', 'Tirador Selecto', 'Apoyo', 'Francotirador', 'Escudo') NULL DEFAULT NULL AFTER document;
