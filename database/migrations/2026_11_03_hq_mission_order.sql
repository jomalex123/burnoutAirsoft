ALTER TABLE hq_missions ADD COLUMN sort_order INT NOT NULL DEFAULT 0;
UPDATE hq_missions SET sort_order = -CAST(id AS SIGNED);
