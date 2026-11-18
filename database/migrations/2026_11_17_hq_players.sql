ALTER TABLE registrations
  ADD COLUMN hq_team ENUM('alpha', 'bravo') NOT NULL DEFAULT 'alpha' AFTER team_name;
