SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE registration_attendees
  MODIFY COLUMN tactical_role ENUM('TL', 'Ingeniero', 'Radio Operator', 'Segundo TL', 'Medico') NULL DEFAULT NULL;
