-- SchoolLift Online Examination: candidate paper review (migration 137).
-- Select one school database in phpMyAdmin, then run/import the whole file.
-- Apply after the existing Online Examination migrations through 136.
-- Additive and rerunnable: preserves questions, answers, result values and history.
-- This file does not advance the CodeIgniter migrations ledger.
SELECT DATABASE() AS selected_school_database;

SET @onlineexam_137_ready := DATABASE() IS NOT NULL AND EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam_attempt_papers'
) AND EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam'
);
SET @onlineexam_137_sql := IF(@onlineexam_137_ready,
  'SELECT 1 AS onlineexam_review_schema_ready',
  'SELECT * FROM SCHOOLLIFT_137_INSTALL_EXISTING_ONLINE_EXAM_MIGRATIONS_FIRST');
PREPARE onlineexam_137_stmt FROM @onlineexam_137_sql;
EXECUTE onlineexam_137_stmt;
DEALLOCATE PREPARE onlineexam_137_stmt;

SET @onlineexam_137_sql := IF(EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam' AND COLUMN_NAME = 'deleted_at'
), 'SELECT 1 AS deleted_at_already_present',
  'ALTER TABLE onlineexam ADD COLUMN deleted_at DATETIME NULL');
PREPARE onlineexam_137_stmt FROM @onlineexam_137_sql;
EXECUTE onlineexam_137_stmt;
DEALLOCATE PREPARE onlineexam_137_stmt;

SET @onlineexam_137_sql := IF(EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam' AND COLUMN_NAME = 'deleted_by'
), 'SELECT 1 AS deleted_by_already_present',
  'ALTER TABLE onlineexam ADD COLUMN deleted_by INT NULL');
PREPARE onlineexam_137_stmt FROM @onlineexam_137_sql;
EXECUTE onlineexam_137_stmt;
DEALLOCATE PREPARE onlineexam_137_stmt;

SET @onlineexam_137_sql := IF(EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam_attempt_papers' AND COLUMN_NAME = 'completion_source'
), 'SELECT 1 AS completion_source_already_present',
  'ALTER TABLE onlineexam_attempt_papers ADD COLUMN completion_source VARCHAR(24) NULL');
PREPARE onlineexam_137_stmt FROM @onlineexam_137_sql;
EXECUTE onlineexam_137_stmt;
DEALLOCATE PREPARE onlineexam_137_stmt;

-- Change only the default for future assignments; retain current question rules.
SET @onlineexam_137_sql := IF(EXISTS (
  SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'onlineexam_questions' AND COLUMN_NAME = 'is_compulsory'
), 'ALTER TABLE onlineexam_questions ALTER COLUMN is_compulsory SET DEFAULT 1',
  'SELECT 1 AS compulsory_default_not_applicable');
PREPARE onlineexam_137_stmt FROM @onlineexam_137_sql;
EXECUTE onlineexam_137_stmt;
DEALLOCATE PREPARE onlineexam_137_stmt;

CREATE TABLE IF NOT EXISTS onlineexam_candidate_paper_windows (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  onlineexam_id INT NOT NULL,
  onlineexam_student_id INT NOT NULL,
  paper_id INT UNSIGNED NOT NULL,
  revision INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  reason TEXT NOT NULL,
  scheduled_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY onlineexam_candidate_paper_window_unique (onlineexam_id,onlineexam_student_id,paper_id),
  KEY onlineexam_candidate_paper_window_end_idx (onlineexam_id,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onlineexam_candidate_paper_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  onlineexam_id INT NOT NULL,
  onlineexam_student_id INT NOT NULL,
  attempt_id BIGINT UNSIGNED NOT NULL,
  paper_id INT UNSIGNED NOT NULL,
  action VARCHAR(32) NOT NULL,
  reason TEXT NOT NULL,
  before_json LONGTEXT NOT NULL,
  after_json LONGTEXT NOT NULL,
  actor_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY onlineexam_candidate_paper_history_idx (attempt_id,paper_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Align only editable drafts with their selected result-component maximum.
-- Published snapshots and any revision with candidate work are unchanged.
UPDATE onlineexam_papers p
JOIN onlineexam e ON e.id = p.onlineexam_id
SET p.raw_max_score = e.target_max_score
WHERE e.workflow_version = 2 AND e.lifecycle_status = 'draft'
  AND e.frozen_at IS NULL AND e.target_max_score > 0
  AND NOT EXISTS (
    SELECT 1 FROM onlineexam_candidate_attempts a
    WHERE a.onlineexam_id = e.id AND a.revision = e.revision
  );

-- A successful migration returns all five rows below.
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND ((TABLE_NAME = 'onlineexam' AND COLUMN_NAME IN ('deleted_at','deleted_by'))
 OR (TABLE_NAME = 'onlineexam_attempt_papers' AND COLUMN_NAME = 'completion_source')
 OR (TABLE_NAME IN ('onlineexam_candidate_paper_windows','onlineexam_candidate_paper_history') AND COLUMN_NAME = 'id'))
ORDER BY TABLE_NAME, COLUMN_NAME;
