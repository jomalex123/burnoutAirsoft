-- Conserva las misiones eliminadas como historial.
ALTER TABLE hq_missions MODIFY COLUMN status ENUM('draft', 'active', 'completed', 'failed', 'deleted') NOT NULL DEFAULT 'draft';
