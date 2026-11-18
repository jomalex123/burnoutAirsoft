SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE registration_attendees
  ADD COLUMN tactical_role ENUM('TL', 'Ingeniero') NULL DEFAULT NULL AFTER weapon_role;
