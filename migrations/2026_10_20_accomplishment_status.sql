ALTER TABLE accomplishments
  ADD COLUMN IF NOT EXISTS status ENUM('planning','ongoing','on_hold','completed') NULL
  AFTER completion_percentage;
