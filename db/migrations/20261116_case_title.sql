-- PROPOSAL: team approval required. Select the intended database explicitly.
-- No USE, DROP, data rewrite or automatic production application.
-- Nullable preserves legacy records; new application writes require a title.
ALTER TABLE `case` ADD COLUMN `case_title` VARCHAR(160) NULL AFTER `doctor_id`;
