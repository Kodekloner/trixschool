-- Nigerian Online Examination localisation schema (CodeIgniter migration 128).
--
-- Run this script in phpMyAdmin for EACH school database after taking a
-- verified backup. Select the intended school database before importing it.
--
-- This script:
--   1. Adds the workflow-v2 columns to the existing Online Examination tables.
--   2. Adds stable identifiers to existing Kindergarten configuration records.
--   3. Creates the 18 additive workflow-v2 tables.
--   4. Preserves all legacy examinations and historical result records.
--
-- It is safe to run again after a complete or partially-complete import.
-- MySQL DDL commits implicitly, so run it during a maintenance window.
-- It intentionally does NOT update the CodeIgniter `migrations` table because
-- school databases may be on different earlier migration versions.
--
-- Compatible with MySQL 5.7+/8.0 and MariaDB versions that support the table
-- definitions below. No CREATE ROUTINE privilege is required.

SET @trix_noop := 0;
SET SESSION group_concat_max_len = 65535;

-- -------------------------------------------------------------------------
-- Conditional additions to existing tables.
-- Each generated ALTER contains only columns missing from the selected DB.
-- -------------------------------------------------------------------------

SET @trix_ddl := (
  SELECT IF(
    (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'onlineexam') = 0,
    'SET @trix_noop = 1',
    IF(
      COUNT(*) = 0,
      'SET @trix_noop = 1',
      CONCAT(
        'ALTER TABLE `onlineexam` ',
        GROUP_CONCAT(
          CONCAT('ADD COLUMN `', expected.`column_name`, '` ', expected.`definition`)
          ORDER BY expected.`ordinal` SEPARATOR ', '
        )
      )
    )
  )
  FROM (
    SELECT 1 AS `ordinal`, 'workflow_version' AS `column_name`, 'TINYINT(3) UNSIGNED DEFAULT 1' AS `definition`
    UNION ALL SELECT 2, 'term', 'VARCHAR(10) NULL'
    UNION ALL SELECT 3, 'class_id', 'INT(11) NULL'
    UNION ALL SELECT 4, 'subject_id', 'INT(11) NULL'
    UNION ALL SELECT 5, 'purpose', 'VARCHAR(32) NULL'
    UNION ALL SELECT 6, 'result_adapter', 'VARCHAR(32) DEFAULT ''unlinked_practice'''
    UNION ALL SELECT 7, 'target_component', 'VARCHAR(16) NULL'
    UNION ALL SELECT 8, 'result_type', 'VARCHAR(20) NULL'
    UNION ALL SELECT 9, 'target_max_score', 'DECIMAL(10,2) NULL'
    UNION ALL SELECT 10, 'revision', 'INT(11) UNSIGNED DEFAULT 1'
    UNION ALL SELECT 11, 'lifecycle_status', 'VARCHAR(24) DEFAULT ''legacy'''
    UNION ALL SELECT 12, 'feedback_status', 'VARCHAR(20) DEFAULT ''hidden'''
    UNION ALL SELECT 13, 'result_config_snapshot', 'LONGTEXT NULL'
    UNION ALL SELECT 14, 'frozen_at', 'DATETIME NULL'
    UNION ALL SELECT 15, 'published_at', 'DATETIME NULL'
    UNION ALL SELECT 16, 'created_by', 'INT(11) NULL'
    UNION ALL SELECT 17, 'lifecycle_checked_at', 'DATETIME NULL'
  ) AS expected
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = 'onlineexam'
   AND actual.`COLUMN_NAME` = expected.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
PREPARE trix_schema_stmt FROM @trix_ddl;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_ddl := (
  SELECT IF(
    (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'onlineexam_questions') = 0,
    'SET @trix_noop = 1',
    IF(
      COUNT(*) = 0,
      'SET @trix_noop = 1',
      CONCAT(
        'ALTER TABLE `onlineexam_questions` ',
        GROUP_CONCAT(
          CONCAT('ADD COLUMN `', expected.`column_name`, '` ', expected.`definition`)
          ORDER BY expected.`ordinal` SEPARATOR ', '
        )
      )
    )
  )
  FROM (
    SELECT 1 AS `ordinal`, 'paper_id' AS `column_name`, 'INT(11) UNSIGNED NULL' AS `definition`
    UNION ALL SELECT 2, 'paper_section_id', 'INT(11) UNSIGNED NULL'
    UNION ALL SELECT 3, 'display_order', 'INT(11) DEFAULT 0'
    UNION ALL SELECT 4, 'is_compulsory', 'TINYINT(1) DEFAULT 0'
    UNION ALL SELECT 5, 'marking_scheme', 'LONGTEXT NULL'
    UNION ALL SELECT 6, 'authoring_json', 'LONGTEXT NULL'
  ) AS expected
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = 'onlineexam_questions'
   AND actual.`COLUMN_NAME` = expected.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
PREPARE trix_schema_stmt FROM @trix_ddl;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_ddl := (
  SELECT IF(
    (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'onlineexam_students') = 0,
    'SET @trix_noop = 1',
    IF(
      COUNT(*) = 0,
      'SET @trix_noop = 1',
      CONCAT(
        'ALTER TABLE `onlineexam_students` ',
        GROUP_CONCAT(
          CONCAT('ADD COLUMN `', expected.`column_name`, '` ', expected.`definition`)
          ORDER BY expected.`ordinal` SEPARATOR ', '
        )
      )
    )
  )
  FROM (
    SELECT 1 AS `ordinal`, 'candidate_status' AS `column_name`, 'VARCHAR(20) DEFAULT ''assigned''' AS `definition`
    UNION ALL SELECT 2, 'assigned_at', 'DATETIME NULL'
    UNION ALL SELECT 3, 'excluded_at', 'DATETIME NULL'
    UNION ALL SELECT 4, 'excluded_by', 'INT(11) NULL'
    UNION ALL SELECT 5, 'exclusion_reason', 'TEXT NULL'
  ) AS expected
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = 'onlineexam_students'
   AND actual.`COLUMN_NAME` = expected.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
PREPARE trix_schema_stmt FROM @trix_ddl;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_ddl := (
  SELECT IF(
    (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'kindergarten_assessment_subjects') = 0,
    'SET @trix_noop = 1',
    IF(
      COUNT(*) = 0,
      'SET @trix_noop = 1',
      CONCAT(
        'ALTER TABLE `kindergarten_assessment_subjects` ',
        GROUP_CONCAT(
          CONCAT('ADD COLUMN `', expected.`column_name`, '` ', expected.`definition`)
          ORDER BY expected.`ordinal` SEPARATOR ', '
        )
      )
    )
  )
  FROM (
    SELECT 1 AS `ordinal`, 'stable_key' AS `column_name`, 'VARCHAR(64) NULL' AS `definition`
    UNION ALL SELECT 2, 'is_active', 'TINYINT(1) DEFAULT 1'
    UNION ALL SELECT 3, 'updated_at', 'DATETIME NULL'
  ) AS expected
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = 'kindergarten_assessment_subjects'
   AND actual.`COLUMN_NAME` = expected.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
PREPARE trix_schema_stmt FROM @trix_ddl;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_ddl := (
  SELECT IF(
    (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`TABLES`
      WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'kindergarten_assessment_concepts') = 0,
    'SET @trix_noop = 1',
    IF(
      COUNT(*) = 0,
      'SET @trix_noop = 1',
      CONCAT(
        'ALTER TABLE `kindergarten_assessment_concepts` ',
        GROUP_CONCAT(
          CONCAT('ADD COLUMN `', expected.`column_name`, '` ', expected.`definition`)
          ORDER BY expected.`ordinal` SEPARATOR ', '
        )
      )
    )
  )
  FROM (
    SELECT 1 AS `ordinal`, 'stable_key' AS `column_name`, 'VARCHAR(64) NULL' AS `definition`
    UNION ALL SELECT 2, 'is_active', 'TINYINT(1) DEFAULT 1'
    UNION ALL SELECT 3, 'updated_at', 'DATETIME NULL'
  ) AS expected
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = 'kindergarten_assessment_concepts'
   AND actual.`COLUMN_NAME` = expected.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
PREPARE trix_schema_stmt FROM @trix_ddl;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

-- Stable identifiers are backfilled without deleting or recreating concepts.
SET @trix_dml := IF(
  (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'kindergarten_assessment_subjects'
      AND `COLUMN_NAME` = 'stable_key') = 1,
  'UPDATE `kindergarten_assessment_subjects` SET `stable_key` = CONCAT(''subject-'', `id`) WHERE `stable_key` IS NULL OR `stable_key` = ''''',
  'SET @trix_noop = 1'
);
PREPARE trix_schema_stmt FROM @trix_dml;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_dml := IF(
  (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'kindergarten_assessment_concepts'
      AND `COLUMN_NAME` = 'stable_key') = 1,
  'UPDATE `kindergarten_assessment_concepts` SET `stable_key` = CONCAT(''concept-'', `id`) WHERE `stable_key` IS NULL OR `stable_key` = ''''',
  'SET @trix_noop = 1'
);
PREPARE trix_schema_stmt FROM @trix_dml;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

-- -------------------------------------------------------------------------
-- New workflow-v2 tables. No foreign keys are introduced because the legacy
-- schema mixes signed and unsigned identifiers; ownership is enforced in code.
-- -------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `onlineexam_class_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_class_section_unique` (`onlineexam_id`, `section_id`),
  KEY `onlineexam_class_sections_section_idx` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_papers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `paper_code` VARCHAR(50) DEFAULT NULL,
  `paper_type` VARCHAR(24) NOT NULL,
  `delivery_mode` VARCHAR(16) NOT NULL DEFAULT 'cbt',
  `instructions` LONGTEXT NULL,
  `starts_at` DATETIME DEFAULT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `raw_max_score` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `contribution_score` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `onlineexam_papers_exam_idx` (`onlineexam_id`),
  KEY `onlineexam_papers_order_idx` (`onlineexam_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_paper_sections` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `paper_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(191) NOT NULL,
  `instructions` LONGTEXT NULL,
  `answer_rule` VARCHAR(32) NOT NULL DEFAULT 'all',
  `answer_count` INT UNSIGNED DEFAULT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `onlineexam_paper_sections_paper_idx` (`paper_id`),
  KEY `onlineexam_paper_sections_order_idx` (`paper_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_question_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` INT NOT NULL,
  `definition_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `definition_json` LONGTEXT NOT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_question_definition_source_unique` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_revision_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `revision` INT UNSIGNED NOT NULL,
  `configuration_json` LONGTEXT NOT NULL,
  `checksum` CHAR(64) NOT NULL,
  `frozen_by` INT DEFAULT NULL,
  `frozen_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_revision_snapshot_unique` (`onlineexam_id`, `revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_question_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `paper_id` INT UNSIGNED NOT NULL,
  `paper_section_id` INT UNSIGNED DEFAULT NULL,
  `source_question_id` INT DEFAULT NULL,
  `revision` INT UNSIGNED NOT NULL,
  `question_type` VARCHAR(100) NOT NULL,
  `question_text` LONGTEXT NOT NULL,
  `options_json` LONGTEXT NULL,
  `correct_answer_json` LONGTEXT NULL,
  `response_schema_json` LONGTEXT NULL,
  `passage_group_key` VARCHAR(64) DEFAULT NULL,
  `passage_title` VARCHAR(191) DEFAULT NULL,
  `passage_text` LONGTEXT NULL,
  `marking_scheme` LONGTEXT NULL,
  `marks` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `neg_marks` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_compulsory` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 0,
  `checksum` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_snapshot_source_unique`
    (`onlineexam_id`, `revision`, `paper_id`, `source_question_id`),
  KEY `onlineexam_snapshot_paper_idx`
    (`paper_id`, `paper_section_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_candidate_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `onlineexam_student_id` INT NOT NULL,
  `revision` INT UNSIGNED NOT NULL,
  `attempt_no` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` VARCHAR(24) NOT NULL DEFAULT 'in_progress',
  `started_at` DATETIME NOT NULL,
  `deadline_at` DATETIME NOT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `last_saved_at` DATETIME DEFAULT NULL,
  `submission_key` VARCHAR(64) DEFAULT NULL,
  `extra_time_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `raw_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `raw_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `weighted_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `weighted_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `final_score` DECIMAL(10,2) DEFAULT NULL,
  `outcome_value` VARCHAR(100) DEFAULT NULL,
  `manual_marking_required` TINYINT(1) NOT NULL DEFAULT 0,
  `marking_status` VARCHAR(24) NOT NULL DEFAULT 'not_required',
  `voided_at` DATETIME DEFAULT NULL,
  `voided_by` INT DEFAULT NULL,
  `void_reason` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_candidate_attempt_no_unique`
    (`onlineexam_student_id`, `attempt_no`),
  UNIQUE KEY `onlineexam_submission_key_unique` (`submission_key`),
  KEY `onlineexam_candidate_attempt_exam_idx` (`onlineexam_id`, `status`),
  KEY `onlineexam_candidate_attempt_deadline_idx` (`deadline_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_attempt_papers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` BIGINT UNSIGNED NOT NULL,
  `paper_id` INT UNSIGNED NOT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME DEFAULT NULL,
  `deadline_at` DATETIME DEFAULT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `raw_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `raw_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `contribution_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `contribution_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `manual_score` DECIMAL(12,4) DEFAULT NULL,
  `manual_marking_status` VARCHAR(24) NOT NULL DEFAULT 'not_required',
  `marked_by` INT DEFAULT NULL,
  `marked_at` DATETIME DEFAULT NULL,
  `marking_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_attempt_paper_unique` (`attempt_id`, `paper_id`),
  KEY `onlineexam_attempt_papers_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_attempt_answers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_id` BIGINT UNSIGNED NOT NULL,
  `question_snapshot_id` BIGINT UNSIGNED NOT NULL,
  `response_json` LONGTEXT NULL,
  `response_hash` CHAR(64) DEFAULT NULL,
  `attachment_name` VARCHAR(255) DEFAULT NULL,
  `attachment_path` VARCHAR(500) DEFAULT NULL,
  `attachment_mime` VARCHAR(100) DEFAULT NULL,
  `attachment_size` INT UNSIGNED DEFAULT NULL,
  `is_answered` TINYINT(1) NOT NULL DEFAULT 0,
  `is_correct` TINYINT(1) DEFAULT NULL,
  `auto_mark` DECIMAL(10,2) DEFAULT NULL,
  `manual_mark` DECIMAL(10,2) DEFAULT NULL,
  `final_mark` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `marking_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `client_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `saved_at` DATETIME NOT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_attempt_answer_unique`
    (`attempt_id`, `question_snapshot_id`),
  KEY `onlineexam_attempt_answers_marking_idx`
    (`attempt_id`, `marking_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_accommodations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `onlineexam_student_id` INT NOT NULL,
  `extra_time_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `makeup_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `makeup_expires_at` DATETIME DEFAULT NULL,
  `notes` TEXT NULL,
  `authorized_by` INT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_accommodation_student_unique`
    (`onlineexam_id`, `onlineexam_student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_marking` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_answer_id` BIGINT UNSIGNED NOT NULL,
  `marking_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `marks` DECIMAL(10,2) NOT NULL,
  `rubric_json` LONGTEXT NULL,
  `remark` TEXT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
  `marked_by` INT NOT NULL,
  `marked_at` DATETIME NOT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_marking_version_unique`
    (`attempt_answer_id`, `marking_version`),
  KEY `onlineexam_marking_status_idx` (`status`, `marked_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_paper_marking` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attempt_paper_id` BIGINT UNSIGNED NOT NULL,
  `marking_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `raw_marks` DECIMAL(10,2) NOT NULL,
  `rubric_json` LONGTEXT NULL,
  `remark` TEXT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
  `marked_by` INT NOT NULL,
  `marked_at` DATETIME NOT NULL,
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_paper_marking_version_unique`
    (`attempt_paper_id`, `marking_version`),
  KEY `onlineexam_paper_marking_status_idx` (`status`, `marked_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_result_profiles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `adapter` VARCHAR(32) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `configuration_json` LONGTEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_result_profile_unique` (`onlineexam_id`, `adapter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_kindergarten_mappings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `paper_id` INT UNSIGNED DEFAULT NULL,
  `paper_section_id` INT UNSIGNED DEFAULT NULL,
  `assessment_id` INT NOT NULL,
  `assessment_subject_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `concept_id` INT NOT NULL,
  `concept_stable_key` VARCHAR(64) NOT NULL,
  `outcome_profile_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_kindergarten_concept_unique`
    (`onlineexam_id`, `concept_id`),
  KEY `onlineexam_kindergarten_mapping_idx`
    (`assessment_id`, `subject_id`, `concept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_result_target_locks` (
  `target_hash` CHAR(64) NOT NULL,
  `target_descriptor` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`target_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_result_sync` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `attempt_id` BIGINT UNSIGNED NOT NULL,
  `student_session_id` INT NOT NULL,
  `adapter` VARCHAR(32) NOT NULL,
  `target_table` VARCHAR(64) DEFAULT NULL,
  `target_record_id` INT DEFAULT NULL,
  `target_field` VARCHAR(64) DEFAULT NULL,
  `source_score` DECIMAL(12,4) DEFAULT NULL,
  `scaled_score` DECIMAL(10,2) DEFAULT NULL,
  `previous_value` VARCHAR(255) DEFAULT NULL,
  `applied_value` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `conflict_reason` TEXT NULL,
  `error_message` TEXT NULL,
  `reversed_at` DATETIME DEFAULT NULL,
  `reversed_by` INT DEFAULT NULL,
  `reversal_reason` TEXT NULL,
  `override_authorized_at` DATETIME DEFAULT NULL,
  `override_authorized_by` INT DEFAULT NULL,
  `override_reason` TEXT NULL,
  `source_fingerprint` CHAR(64) NOT NULL,
  `idempotency_key` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `synced_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_result_sync_idempotency_unique` (`idempotency_key`),
  KEY `onlineexam_result_sync_attempt_idx` (`attempt_id`, `status`),
  KEY `onlineexam_result_sync_target_idx`
    (`onlineexam_id`, `student_session_id`, `adapter`, `target_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_incidents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `attempt_id` BIGINT UNSIGNED DEFAULT NULL,
  `onlineexam_student_id` INT DEFAULT NULL,
  `incident_type` VARCHAR(50) NOT NULL,
  `severity` VARCHAR(16) NOT NULL DEFAULT 'info',
  `details` LONGTEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `reported_by` INT DEFAULT NULL,
  `resolved_by` INT DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `onlineexam_incidents_exam_idx` (`onlineexam_id`, `status`),
  KEY `onlineexam_incidents_attempt_idx` (`attempt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `onlineexam_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT DEFAULT NULL,
  `attempt_id` BIGINT UNSIGNED DEFAULT NULL,
  `actor_id` INT DEFAULT NULL,
  `actor_type` VARCHAR(20) DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(64) DEFAULT NULL,
  `before_json` LONGTEXT NULL,
  `after_json` LONGTEXT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `onlineexam_audit_exam_idx` (`onlineexam_id`, `created_at`),
  KEY `onlineexam_audit_attempt_idx` (`attempt_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------------------
-- Verification. Both result sets must be empty.
-- -------------------------------------------------------------------------

SELECT required.`table_name` AS `missing_table`
FROM (
  SELECT 'onlineexam_class_sections' AS `table_name`
  UNION ALL SELECT 'onlineexam_papers'
  UNION ALL SELECT 'onlineexam_paper_sections'
  UNION ALL SELECT 'onlineexam_question_definitions'
  UNION ALL SELECT 'onlineexam_revision_snapshots'
  UNION ALL SELECT 'onlineexam_question_snapshots'
  UNION ALL SELECT 'onlineexam_candidate_attempts'
  UNION ALL SELECT 'onlineexam_attempt_papers'
  UNION ALL SELECT 'onlineexam_attempt_answers'
  UNION ALL SELECT 'onlineexam_accommodations'
  UNION ALL SELECT 'onlineexam_marking'
  UNION ALL SELECT 'onlineexam_paper_marking'
  UNION ALL SELECT 'onlineexam_result_profiles'
  UNION ALL SELECT 'onlineexam_kindergarten_mappings'
  UNION ALL SELECT 'onlineexam_result_target_locks'
  UNION ALL SELECT 'onlineexam_result_sync'
  UNION ALL SELECT 'onlineexam_incidents'
  UNION ALL SELECT 'onlineexam_audit_log'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
WHERE actual.`TABLE_NAME` IS NULL;

SELECT
  required.`table_name`,
  required.`column_name` AS `missing_column`
FROM (
  SELECT 'onlineexam' AS `table_name`, 'workflow_version' AS `column_name`
  UNION ALL SELECT 'onlineexam', 'term'
  UNION ALL SELECT 'onlineexam', 'class_id'
  UNION ALL SELECT 'onlineexam', 'subject_id'
  UNION ALL SELECT 'onlineexam', 'purpose'
  UNION ALL SELECT 'onlineexam', 'result_adapter'
  UNION ALL SELECT 'onlineexam', 'target_component'
  UNION ALL SELECT 'onlineexam', 'result_type'
  UNION ALL SELECT 'onlineexam', 'target_max_score'
  UNION ALL SELECT 'onlineexam', 'revision'
  UNION ALL SELECT 'onlineexam', 'lifecycle_status'
  UNION ALL SELECT 'onlineexam', 'feedback_status'
  UNION ALL SELECT 'onlineexam', 'result_config_snapshot'
  UNION ALL SELECT 'onlineexam', 'frozen_at'
  UNION ALL SELECT 'onlineexam', 'published_at'
  UNION ALL SELECT 'onlineexam', 'created_by'
  UNION ALL SELECT 'onlineexam', 'lifecycle_checked_at'
  UNION ALL SELECT 'onlineexam_questions', 'paper_id'
  UNION ALL SELECT 'onlineexam_questions', 'paper_section_id'
  UNION ALL SELECT 'onlineexam_questions', 'display_order'
  UNION ALL SELECT 'onlineexam_questions', 'is_compulsory'
  UNION ALL SELECT 'onlineexam_questions', 'marking_scheme'
  UNION ALL SELECT 'onlineexam_questions', 'authoring_json'
  UNION ALL SELECT 'onlineexam_students', 'candidate_status'
  UNION ALL SELECT 'onlineexam_students', 'assigned_at'
  UNION ALL SELECT 'onlineexam_students', 'excluded_at'
  UNION ALL SELECT 'onlineexam_students', 'excluded_by'
  UNION ALL SELECT 'onlineexam_students', 'exclusion_reason'
  UNION ALL SELECT 'kindergarten_assessment_subjects', 'stable_key'
  UNION ALL SELECT 'kindergarten_assessment_subjects', 'is_active'
  UNION ALL SELECT 'kindergarten_assessment_subjects', 'updated_at'
  UNION ALL SELECT 'kindergarten_assessment_concepts', 'stable_key'
  UNION ALL SELECT 'kindergarten_assessment_concepts', 'is_active'
  UNION ALL SELECT 'kindergarten_assessment_concepts', 'updated_at'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

-- These final checks should each return zero when the Kindergarten tables are
-- present. On databases without those optional legacy tables they are skipped.
SET @trix_check := IF(
  (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'kindergarten_assessment_subjects'
      AND `COLUMN_NAME` = 'stable_key') = 1,
  'SELECT COUNT(*) AS `kindergarten_subjects_without_stable_key` FROM `kindergarten_assessment_subjects` WHERE `stable_key` IS NULL OR `stable_key` = ''''',
  'SET @trix_noop = 1'
);
PREPARE trix_schema_stmt FROM @trix_check;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SET @trix_check := IF(
  (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'kindergarten_assessment_concepts'
      AND `COLUMN_NAME` = 'stable_key') = 1,
  'SELECT COUNT(*) AS `kindergarten_concepts_without_stable_key` FROM `kindergarten_assessment_concepts` WHERE `stable_key` IS NULL OR `stable_key` = ''''',
  'SET @trix_noop = 1'
);
PREPARE trix_schema_stmt FROM @trix_check;
EXECUTE trix_schema_stmt;
DEALLOCATE PREPARE trix_schema_stmt;

SELECT 'Nigerian Online Examination schema migration completed.' AS `status`;
