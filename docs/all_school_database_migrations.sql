-- SchoolLift consolidated tenant-database migrations (126 through 136).
-- Updated for deployment to every school database on 2026-09-05.
--
-- IMPORTANT:
--   * Select exactly one school database before importing this file.
--   * Take and verify a backup before running it in production.
--   * Run during a maintenance window because MySQL DDL commits implicitly.
--   * Repeat the import separately for each school database.
--   * This bundle is rerunnable. Migration 136 permanently removes only the
--     retired questions.level difficulty labels; questions and results remain.
--   * Keep question/exam authoring in maintenance while deploying the matching
--     application code and this SQL, then reopen it after both are installed.
--   * It is a delta for a complete SchoolLift schema at level 125 or later;
--     it is not an installer for an empty database.
--   * It intentionally does NOT update the CodeIgniter `migrations` table.
--     Audit and repair that ledger before using the /migrate endpoint; an
--     empty ledger can make CodeIgniter incorrectly start again at version 0.
--
-- Included application migrations:
--   126_add_incoming_emails.php
--   127_add_support_tickets.php
--   128_localize_online_examination.php
--   129_add_monnify_payments.php
--   130_add_biometric_attendance.php
--   131_add_id_card_design_studio.php
--   132_add_biometric_gateway_control.php
--   133_add_promotion_system.php
--   134_add_external_email_notifications.php
--   135_compact_online_examination.php
--   136_remove_question_level.php
--
-- Supported targets: MySQL 5.7+/8.0 and compatible MariaDB releases.
-- This is a schema/permission migration bundle, not a full database dump.
-- The separate roles-permission integrity and cumulative-result repair SQL
-- files are intentionally excluded because they are optional data maintenance,
-- not numbered application migrations.

SELECT DATABASE() AS `selected_school_database`;

-- Stop before any DDL if the selected database is not a complete level-125
-- SchoolLift tenant. The first result set names every missing prerequisite.
SELECT required.`table_name`, required.`column_name` AS `missing_prerequisite`
FROM (
  SELECT 'permission_group' AS `table_name`, 'id' AS `column_name`
  UNION ALL SELECT 'permission_group', 'name'
  UNION ALL SELECT 'permission_group', 'short_code'
  UNION ALL SELECT 'permission_group', 'is_active'
  UNION ALL SELECT 'permission_group', 'system'
  UNION ALL SELECT 'permission_group', 'created_at'
  UNION ALL SELECT 'permission_category', 'id'
  UNION ALL SELECT 'permission_category', 'perm_group_id'
  UNION ALL SELECT 'permission_category', 'name'
  UNION ALL SELECT 'permission_category', 'short_code'
  UNION ALL SELECT 'permission_category', 'enable_view'
  UNION ALL SELECT 'permission_category', 'enable_add'
  UNION ALL SELECT 'permission_category', 'enable_edit'
  UNION ALL SELECT 'permission_category', 'enable_delete'
  UNION ALL SELECT 'permission_category', 'created_at'
  UNION ALL SELECT 'roles', 'id'
  UNION ALL SELECT 'roles', 'name'
  UNION ALL SELECT 'roles_permissions', 'role_id'
  UNION ALL SELECT 'roles_permissions', 'perm_cat_id'
  UNION ALL SELECT 'roles_permissions', 'can_view'
  UNION ALL SELECT 'roles_permissions', 'can_add'
  UNION ALL SELECT 'roles_permissions', 'can_edit'
  UNION ALL SELECT 'roles_permissions', 'can_delete'
  UNION ALL SELECT 'roles_permissions', 'created_at'
  UNION ALL SELECT 'onlineexam', 'id'
  UNION ALL SELECT 'onlineexam_questions', 'id'
  UNION ALL SELECT 'onlineexam_students', 'id'
  UNION ALL SELECT 'holiday_assessment_scores', 'id'
  UNION ALL SELECT 'holiday_assessment_scores', 'max_score'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

SET @trix_preflight_missing := (
  SELECT COUNT(*)
  FROM (
    SELECT 'permission_group' AS `table_name`, 'id' AS `column_name`
    UNION ALL SELECT 'permission_group', 'name'
    UNION ALL SELECT 'permission_group', 'short_code'
    UNION ALL SELECT 'permission_group', 'is_active'
    UNION ALL SELECT 'permission_group', 'system'
    UNION ALL SELECT 'permission_group', 'created_at'
    UNION ALL SELECT 'permission_category', 'id'
    UNION ALL SELECT 'permission_category', 'perm_group_id'
    UNION ALL SELECT 'permission_category', 'name'
    UNION ALL SELECT 'permission_category', 'short_code'
    UNION ALL SELECT 'permission_category', 'enable_view'
    UNION ALL SELECT 'permission_category', 'enable_add'
    UNION ALL SELECT 'permission_category', 'enable_edit'
    UNION ALL SELECT 'permission_category', 'enable_delete'
    UNION ALL SELECT 'permission_category', 'created_at'
    UNION ALL SELECT 'roles', 'id'
    UNION ALL SELECT 'roles', 'name'
    UNION ALL SELECT 'roles_permissions', 'role_id'
    UNION ALL SELECT 'roles_permissions', 'perm_cat_id'
    UNION ALL SELECT 'roles_permissions', 'can_view'
    UNION ALL SELECT 'roles_permissions', 'can_add'
    UNION ALL SELECT 'roles_permissions', 'can_edit'
    UNION ALL SELECT 'roles_permissions', 'can_delete'
    UNION ALL SELECT 'roles_permissions', 'created_at'
    UNION ALL SELECT 'onlineexam', 'id'
    UNION ALL SELECT 'onlineexam_questions', 'id'
    UNION ALL SELECT 'onlineexam_students', 'id'
    UNION ALL SELECT 'holiday_assessment_scores', 'id'
    UNION ALL SELECT 'holiday_assessment_scores', 'max_score'
  ) AS required
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
   AND actual.`COLUMN_NAME` = required.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);

SET @trix_preflight_sql := IF(
  DATABASE() IS NOT NULL AND @trix_preflight_missing = 0,
  'SET @trix_preflight_ok = 1',
  'SELECT * FROM `SCHOOLLIFT_MIGRATION_PREFLIGHT_FAILED_REVIEW_MISSING_PREREQUISITES`'
);
PREPARE trix_preflight_stmt FROM @trix_preflight_sql;
EXECUTE trix_preflight_stmt;
DEALLOCATE PREPARE trix_preflight_stmt;

-- ========================================================================
-- Migration 126: store inbound AWS SNS/SES email notifications
-- ========================================================================

CREATE TABLE IF NOT EXISTS `incoming_emails` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sns_type` varchar(50) DEFAULT NULL,
  `sns_message_id` varchar(255) DEFAULT NULL,
  `sns_topic_arn` varchar(255) DEFAULT NULL,
  `ses_notification_type` varchar(50) DEFAULT NULL,
  `ses_message_id` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `destinations_json` longtext,
  `recipients_json` longtext,
  `headers_json` longtext,
  `mail_timestamp` datetime DEFAULT NULL,
  `receipt_timestamp` datetime DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `s3_bucket` varchar(255) DEFAULT NULL,
  `s3_object_key` text,
  `spam_status` varchar(50) DEFAULT NULL,
  `virus_status` varchar(50) DEFAULT NULL,
  `dkim_status` varchar(50) DEFAULT NULL,
  `spf_status` varchar(50) DEFAULT NULL,
  `dmarc_status` varchar(50) DEFAULT NULL,
  `body_text` longtext,
  `body_html` longtext,
  `attachment_count` int(11) NOT NULL DEFAULT 0,
  `attachment_names_json` longtext,
  `raw_content` longtext,
  `raw_payload` longtext,
  `status` varchar(50) NOT NULL DEFAULT 'received',
  `error_message` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sns_message_id` (`sns_message_id`),
  KEY `ses_message_id` (`ses_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;


-- ========================================================================
-- Migration 127: support tickets for inbound email
-- ========================================================================

-- Support ticket migration for AWS SES inbound email.
-- Run this once per school database in phpMyAdmin if you are not using CodeIgniter migrations.

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(50) NOT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'email',
  `requester_name` varchar(191) DEFAULT NULL,
  `requester_email` varchar(191) NOT NULL,
  `requester_phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `priority` varchar(30) NOT NULL DEFAULT 'normal',
  `assigned_staff_id` int DEFAULT NULL,
  `incoming_email_id` int unsigned DEFAULT NULL,
  `last_incoming_email_id` int unsigned DEFAULT NULL,
  `message_id` varchar(255) DEFAULT NULL,
  `last_outgoing_message_id` varchar(255) DEFAULT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `last_customer_message_at` datetime DEFAULT NULL,
  `last_staff_message_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `status` (`status`),
  KEY `requester_email` (`requester_email`),
  KEY `last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- A pre-existing table may have been created by an interrupted/manual import.
-- Add the required unique constraint when no equivalent single-column unique
-- index exists. If duplicate ticket numbers exist, ALTER TABLE stops safely;
-- this bundle never deletes or merges those records automatically.
SET @support_ticket_unique_exists := (
  SELECT COUNT(*)
  FROM (
    SELECT `INDEX_NAME`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'support_tickets'
    GROUP BY `INDEX_NAME`
    HAVING MIN(`NON_UNIQUE`) = 0
       AND GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') = 'ticket_number'
  ) AS equivalent_unique_indexes
);
SET @trix_support_ddl := IF(
  @support_ticket_unique_exists = 0,
  'ALTER TABLE `support_tickets` ADD UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`)',
  'SET @trix_noop = 1'
);
PREPARE trix_support_stmt FROM @trix_support_ddl;
EXECUTE trix_support_stmt;
DEALLOCATE PREPARE trix_support_stmt;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` int unsigned NOT NULL,
  `incoming_email_id` int unsigned DEFAULT NULL,
  `direction` varchar(20) NOT NULL,
  `sender_type` varchar(20) NOT NULL,
  `sender_staff_id` int DEFAULT NULL,
  `sender_name` varchar(191) DEFAULT NULL,
  `sender_email` varchar(191) DEFAULT NULL,
  `recipients_json` longtext,
  `subject` varchar(255) DEFAULT NULL,
  `body_text` longtext,
  `body_html` longtext,
  `message_id` varchar(255) DEFAULT NULL,
  `in_reply_to` varchar(255) DEFAULT NULL,
  `references_header` text,
  `attachment_count` int NOT NULL DEFAULT 0,
  `attachment_names_json` longtext,
  `delivery_status` varchar(50) NOT NULL DEFAULT 'received',
  `error_message` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_id` (`support_ticket_id`),
  KEY `incoming_email_id` (`incoming_email_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `permission_group` (`name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT 'Front Office', 'front_office', 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_group` WHERE `short_code` = 'front_office'
);

SET @support_front_office_group_id := (
    SELECT `id` FROM `permission_group` WHERE `short_code` = 'front_office' LIMIT 1
);

INSERT INTO `permission_category` (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)
SELECT @support_front_office_group_id, 'Support Tickets', 'support_ticket', 1, 1, 1, 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_category` WHERE `short_code` = 'support_ticket'
);

SET @support_ticket_perm_id := (
    SELECT `id` FROM `permission_category` WHERE `short_code` = 'support_ticket' LIMIT 1
);

INSERT INTO `roles_permissions` (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
SELECT `roles`.`id`, @support_ticket_perm_id, 1, 1, 1, 1, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Admin', 'Super Admin')
AND NOT EXISTS (
    SELECT 1
    FROM `roles_permissions`
    WHERE `roles_permissions`.`role_id` = `roles`.`id`
    AND `roles_permissions`.`perm_cat_id` = @support_ticket_perm_id
);

-- ========================================================================
-- Migration 128: localized Online Examination workflow
-- ========================================================================

-- Localized Online Examination schema (CodeIgniter migration 128).
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
    UNION ALL SELECT 6, 'result_adapter', 'VARCHAR(32) DEFAULT ''standard_component'''
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
  AND (
    required.`table_name` NOT IN (
      'kindergarten_assessment_subjects',
      'kindergarten_assessment_concepts'
    )
    OR EXISTS (
      SELECT 1
      FROM `INFORMATION_SCHEMA`.`TABLES` AS optional_table
      WHERE optional_table.`TABLE_SCHEMA` = DATABASE()
        AND optional_table.`TABLE_NAME` = required.`table_name`
    )
  )
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

SELECT 'Migration 128 statements finished; any missing_* rows above require review.' AS `status`;

-- ========================================================================
-- Migration 129: Monnify payment ledger
-- ========================================================================

CREATE TABLE IF NOT EXISTS `monnify_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payment_reference` varchar(100) NOT NULL,
  `transaction_reference` varchar(150) DEFAULT NULL,
  `payment_context` varchar(30) NOT NULL,
  `context_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'NGN',
  `customer_email` varchar(191) NOT NULL,
  `customer_name` varchar(191) NOT NULL,
  `context_data` longtext NOT NULL,
  `gateway_mode` tinyint(1) NOT NULL DEFAULT '0',
  `gateway_response` longtext,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `processing_started_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_monnify_payment_reference` (`payment_reference`),
  UNIQUE KEY `uq_monnify_transaction_reference` (`transaction_reference`),
  KEY `idx_monnify_status` (`status`),
  KEY `idx_monnify_context` (`payment_context`,`context_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- BEGIN GENERATED MIGRATIONS 130-132

-- ========================================================================

-- Migration 130: production biometric attendance (single terminal)

-- ========================================================================



-- Migration 130-132 prerequisites. Every result set must be empty.

SELECT required.`table_name`, required.`column_name` AS `missing_prerequisite`
FROM (
  SELECT 'student_attendences' AS `table_name`, 'id' AS `column_name`
  UNION ALL SELECT 'staff_attendance', 'id'
  UNION ALL SELECT 'id_card', 'id'
  UNION ALL SELECT 'staff_id_card', 'id'
  UNION ALL SELECT 'sch_settings', 'session_id'
  UNION ALL SELECT 'attendence_type', 'id'
  UNION ALL SELECT 'staff_attendance_type', 'id'
) AS required
LEFT JOIN INFORMATION_SCHEMA.COLUMNS AS actual
  ON actual.TABLE_SCHEMA = DATABASE()
 AND actual.TABLE_NAME = required.table_name
 AND actual.COLUMN_NAME = required.column_name
WHERE actual.COLUMN_NAME IS NULL
ORDER BY required.table_name, required.column_name;

SET @trix_bio_preflight_missing := (
  SELECT COUNT(*)
  FROM (
    SELECT 'student_attendences' AS `table_name`, 'id' AS `column_name`
    UNION ALL SELECT 'staff_attendance', 'id'
    UNION ALL SELECT 'id_card', 'id'
    UNION ALL SELECT 'staff_id_card', 'id'
    UNION ALL SELECT 'sch_settings', 'session_id'
    UNION ALL SELECT 'attendence_type', 'id'
    UNION ALL SELECT 'staff_attendance_type', 'id'
  ) AS required
  LEFT JOIN INFORMATION_SCHEMA.COLUMNS AS actual
    ON actual.TABLE_SCHEMA = DATABASE()
   AND actual.TABLE_NAME = required.table_name
   AND actual.COLUMN_NAME = required.column_name
  WHERE actual.COLUMN_NAME IS NULL
);
SET @trix_schema_sql := IF(DATABASE() IS NOT NULL AND @trix_bio_preflight_missing = 0,
  'SET @trix_bio_preflight_ok = 1',
  'SELECT * FROM `SCHOOLLIFT_BIOMETRIC_ID_STUDIO_PREFLIGHT_FAILED`');
PREPARE trix_bio_preflight_stmt FROM @trix_schema_sql;
EXECUTE trix_bio_preflight_stmt;
DEALLOCATE PREPARE trix_bio_preflight_stmt;

CREATE TABLE IF NOT EXISTS `biometric_settings` (
    `id` TINYINT UNSIGNED NOT NULL,
    `mode` VARCHAR(20) NOT NULL DEFAULT 'disabled',
    `timezone` VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos',
    `student_late_after` TIME NOT NULL DEFAULT '08:00:00',
    `staff_late_after` TIME NOT NULL DEFAULT '08:00:00',
    `student_present_type_id` INT NOT NULL DEFAULT 1,
    `student_late_type_id` INT NOT NULL DEFAULT 3,
    `staff_present_type_id` INT NOT NULL DEFAULT 1,
    `staff_late_type_id` INT NOT NULL DEFAULT 2,
    `project_students` TINYINT(1) NOT NULL DEFAULT 1,
    `project_staff` TINYINT(1) NOT NULL DEFAULT 1,
    `retention_days` SMALLINT UNSIGNED NOT NULL DEFAULT 730,
    `max_event_age_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_integrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `provider` VARCHAR(40) NOT NULL DEFAULT 'zkbio_time',
    `endpoint_url` VARCHAR(500) DEFAULT NULL,
    `token_prefix` VARCHAR(32) DEFAULT NULL,
    `token_hash` CHAR(64) DEFAULT NULL,
    `token_version` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_seen_at` DATETIME DEFAULT NULL,
    `last_cursor` VARCHAR(191) DEFAULT NULL,
    `last_error` VARCHAR(500) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_integration_token_prefix` (`token_prefix`),
    KEY `idx_biometric_integration_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_devices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_id` INT UNSIGNED DEFAULT NULL,
    `serial_number` VARCHAR(100) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(191) DEFAULT NULL,
    `device_type` VARCHAR(24) NOT NULL DEFAULT 'biometric',
    `direction_mode` VARCHAR(20) NOT NULL DEFAULT 'bidirectional',
    `firmware_version` VARCHAR(100) DEFAULT NULL,
    `is_virtual` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_seen_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_device_serial` (`serial_number`),
    KEY `idx_biometric_device_integration` (`integration_id`),
    KEY `idx_biometric_device_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_punch_state_mappings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_id` INT UNSIGNED NOT NULL,
    `raw_punch_state` VARCHAR(32) NOT NULL,
    `direction` VARCHAR(8) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_punch_mapping` (`integration_id`, `raw_punch_state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_identity_mappings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_type` VARCHAR(12) NOT NULL,
    `subject_id` INT NOT NULL,
    `external_person_code` VARCHAR(100) NOT NULL,
    `valid_from` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_mapping_code` (`external_person_code`),
    UNIQUE KEY `uq_biometric_mapping_subject` (`subject_type`, `subject_id`),
    KEY `idx_biometric_mapping_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_gateway_batches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_id` INT UNSIGNED NOT NULL,
    `batch_id` VARCHAR(100) NOT NULL,
    `gateway_version` VARCHAR(40) DEFAULT NULL,
    `provider_cursor` VARCHAR(191) DEFAULT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `event_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `accepted_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `quarantined_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `rejected_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
    `results_json` LONGTEXT NULL,
    `committed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_gateway_batch` (`integration_id`, `batch_id`),
    KEY `idx_biometric_batch_created` (`integration_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_gateway_cursors` (
    `integration_id` INT UNSIGNED NOT NULL,
    `provider_cursor` VARCHAR(191) DEFAULT NULL,
    `batch_id` VARCHAR(100) DEFAULT NULL,
    `committed_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`integration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_attendance_days` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_type` VARCHAR(12) NOT NULL,
    `subject_id` INT NOT NULL,
    `attendance_date` DATE NOT NULL,
    `record_scope` VARCHAR(16) NOT NULL,
    `academic_session_id` INT DEFAULT NULL,
    `term` VARCHAR(225) DEFAULT NULL,
    `first_in_at` DATETIME DEFAULT NULL,
    `last_out_at` DATETIME DEFAULT NULL,
    `duration_minutes` INT UNSIGNED DEFAULT NULL,
    `attendance_status` VARCHAR(24) NOT NULL DEFAULT 'incomplete',
    `attendance_type_id` INT DEFAULT NULL,
    `missing_checkout` TINYINT(1) NOT NULL DEFAULT 0,
    `manual_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `official_table` VARCHAR(40) DEFAULT NULL,
    `official_attendance_id` INT DEFAULT NULL,
    `projection_status` VARCHAR(24) NOT NULL DEFAULT 'not_applicable',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_attendance_day` (`subject_type`, `subject_id`, `attendance_date`, `record_scope`),
    KEY `idx_biometric_day_date` (`attendance_date`, `record_scope`),
    KEY `idx_biometric_day_missing` (`missing_checkout`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `batch_id` BIGINT UNSIGNED DEFAULT NULL,
    `integration_id` INT UNSIGNED DEFAULT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `attendance_day_id` BIGINT UNSIGNED DEFAULT NULL,
    `dedup_key` CHAR(64) NOT NULL,
    `external_event_id` VARCHAR(191) NOT NULL,
    `device_serial` VARCHAR(100) NOT NULL,
    `person_code` VARCHAR(100) NOT NULL,
    `subject_type` VARCHAR(12) DEFAULT NULL,
    `subject_id` INT DEFAULT NULL,
    `raw_punch_state` VARCHAR(32) DEFAULT NULL,
    `direction` VARCHAR(8) DEFAULT NULL,
    `verification_method` VARCHAR(24) NOT NULL DEFAULT 'unknown',
    `source` VARCHAR(16) NOT NULL,
    `operating_mode` VARCHAR(16) NOT NULL,
    `occurred_at_utc` DATETIME NOT NULL,
    `occurred_at_local` DATETIME NOT NULL,
    `attendance_date` DATE NOT NULL,
    `received_at` DATETIME NOT NULL,
    `processing_status` VARCHAR(24) NOT NULL,
    `projection_status` VARCHAR(24) NOT NULL DEFAULT 'not_applicable',
    `failure_code` VARCHAR(64) DEFAULT NULL,
    `failure_message` VARCHAR(500) DEFAULT NULL,
    `payload_hash` CHAR(64) NOT NULL,
    `metadata_json` TEXT NULL,
    `processed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_event_dedup` (`dedup_key`),
    KEY `idx_biometric_event_external` (`integration_id`, `external_event_id`),
    KEY `idx_biometric_event_date` (`attendance_date`, `operating_mode`),
    KEY `idx_biometric_event_status` (`processing_status`),
    KEY `idx_biometric_event_subject` (`subject_type`, `subject_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_exceptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED DEFAULT NULL,
    `attendance_day_id` BIGINT UNSIGNED DEFAULT NULL,
    `exception_code` VARCHAR(64) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'open',
    `resolution_action` VARCHAR(32) DEFAULT NULL,
    `resolution_note` TEXT NULL,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_biometric_exception_status` (`status`, `created_at`),
    KEY `idx_biometric_exception_event` (`event_id`),
    KEY `idx_biometric_exception_day` (`attendance_day_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_reconciliation_actions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exception_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(32) NOT NULL,
    `details_json` TEXT NULL,
    `actor_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_biometric_reconciliation_exception` (`exception_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_scanner_stations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `station_uuid` CHAR(32) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(191) DEFAULT NULL,
    `device_id` INT UNSIGNED DEFAULT NULL,
    `direction_mode` VARCHAR(20) NOT NULL DEFAULT 'bidirectional',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_seen_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_scanner_uuid` (`station_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_qr_credentials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `credential_uuid` CHAR(32) NOT NULL,
    `subject_type` VARCHAR(12) NOT NULL,
    `subject_id` INT NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `token_ciphertext` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `issued_by` INT DEFAULT NULL,
    `issued_at` DATETIME NOT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `revoked_by` INT DEFAULT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `revoke_reason` VARCHAR(255) DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_qr_uuid` (`credential_uuid`),
    UNIQUE KEY `uq_biometric_qr_hash` (`token_hash`),
    KEY `idx_biometric_qr_subject` (`subject_type`, `subject_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `actor_id` INT DEFAULT NULL,
    `action` VARCHAR(64) NOT NULL,
    `entity_type` VARCHAR(40) DEFAULT NULL,
    `entity_id` VARCHAR(64) DEFAULT NULL,
    `before_json` TEXT NULL,
    `after_json` TEXT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_biometric_audit_created` (`created_at`),
    KEY `idx_biometric_audit_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'academic_session_id') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `academic_session_id` INT NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_academic_session_id_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_academic_session_id_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_academic_session_id_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'term') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `term` VARCHAR(225) NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_term_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_term_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_term_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'manual_locked') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `manual_locked` TINYINT(1) NOT NULL DEFAULT 0',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_manual_locked_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_manual_locked_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_manual_locked_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'official_table') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `official_table` VARCHAR(40) NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_official_table_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_official_table_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_official_table_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'official_attendance_id') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `official_attendance_id` INT NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_official_attendance_id_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_official_attendance_id_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_official_attendance_id_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_attendance_days' AND COLUMN_NAME = 'projection_status') = 0,
  'ALTER TABLE `biometric_attendance_days` ADD COLUMN `projection_status` VARCHAR(24) NOT NULL DEFAULT ''not_applicable''',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_attendance_days_projection_status_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_attendance_days_projection_status_stmt;
DEALLOCATE PREPARE trix_biometric_attendance_days_projection_status_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_events' AND COLUMN_NAME = 'payload_hash') = 0,
  'ALTER TABLE `biometric_events` ADD COLUMN `payload_hash` CHAR(64) NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_events_payload_hash_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_events_payload_hash_stmt;
DEALLOCATE PREPARE trix_biometric_events_payload_hash_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_events' AND COLUMN_NAME = 'metadata_json') = 0,
  'ALTER TABLE `biometric_events` ADD COLUMN `metadata_json` TEXT NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_events_metadata_json_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_events_metadata_json_stmt;
DEALLOCATE PREPARE trix_biometric_events_metadata_json_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'biometric_events' AND COLUMN_NAME = 'projection_status') = 0,
  'ALTER TABLE `biometric_events` ADD COLUMN `projection_status` VARCHAR(24) NOT NULL DEFAULT ''not_applicable''',
  'SET @trix_noop = 1'
);
PREPARE trix_biometric_events_projection_status_stmt FROM @trix_schema_sql;
EXECUTE trix_biometric_events_projection_status_stmt;
DEALLOCATE PREPARE trix_biometric_events_projection_status_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_attendences' AND COLUMN_NAME = 'attendance_source') = 0,
  'ALTER TABLE `student_attendences` ADD COLUMN `attendance_source` VARCHAR(24) NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_student_attendences_attendance_source_stmt FROM @trix_schema_sql;
EXECUTE trix_student_attendences_attendance_source_stmt;
DEALLOCATE PREPARE trix_student_attendences_attendance_source_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_attendences' AND COLUMN_NAME = 'biometric_day_id') = 0,
  'ALTER TABLE `student_attendences` ADD COLUMN `biometric_day_id` BIGINT UNSIGNED NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_student_attendences_biometric_day_id_stmt FROM @trix_schema_sql;
EXECUTE trix_student_attendences_biometric_day_id_stmt;
DEALLOCATE PREPARE trix_student_attendences_biometric_day_id_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_attendance' AND COLUMN_NAME = 'attendance_source') = 0,
  'ALTER TABLE `staff_attendance` ADD COLUMN `attendance_source` VARCHAR(24) NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_attendance_attendance_source_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_attendance_attendance_source_stmt;
DEALLOCATE PREPARE trix_staff_attendance_attendance_source_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_attendance' AND COLUMN_NAME = 'biometric_day_id') = 0,
  'ALTER TABLE `staff_attendance` ADD COLUMN `biometric_day_id` BIGINT UNSIGNED NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_attendance_biometric_day_id_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_attendance_biometric_day_id_stmt;
DEALLOCATE PREPARE trix_staff_attendance_biometric_day_id_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_attendences' AND INDEX_NAME = 'idx_student_attendance_biometric_day') = 0,
  'ALTER TABLE `student_attendences` ADD INDEX `idx_student_attendance_biometric_day` (`biometric_day_id`)',
  'SET @trix_noop = 1'
);
PREPARE trix_student_attendences_idx_student_attendance_biometric_day_stmt FROM @trix_schema_sql;
EXECUTE trix_student_attendences_idx_student_attendance_biometric_day_stmt;
DEALLOCATE PREPARE trix_student_attendences_idx_student_attendance_biometric_day_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_attendance' AND INDEX_NAME = 'idx_staff_attendance_biometric_day') = 0,
  'ALTER TABLE `staff_attendance` ADD INDEX `idx_staff_attendance_biometric_day` (`biometric_day_id`)',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_attendance_idx_staff_attendance_biometric_day_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_attendance_idx_staff_attendance_biometric_day_stmt;
DEALLOCATE PREPARE trix_staff_attendance_idx_staff_attendance_biometric_day_stmt;

INSERT INTO `biometric_settings` (`id`, `mode`, `timezone`, `created_at`, `updated_at`)
SELECT 1, 'disabled', 'Africa/Lagos', UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `biometric_settings` WHERE `id` = 1);

INSERT INTO `biometric_devices`
  (`integration_id`, `serial_number`, `name`, `location`, `device_type`, `direction_mode`, `is_virtual`, `is_active`, `created_at`, `updated_at`)
SELECT NULL, 'SIM-GATE-001', 'Simulation Gate', 'Browser Test Terminal', 'biometric', 'bidirectional', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `biometric_devices` WHERE `serial_number` = 'SIM-GATE-001');

SET @trix_bio_permission_group_new_id := (SELECT COALESCE(MAX(`id`), 0) + 1 FROM `permission_group`);
INSERT INTO `permission_group` (`id`, `name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT @trix_bio_permission_group_new_id, 'Attendance', 'student_attendance', 1, 0, UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `permission_group` WHERE `short_code` = 'student_attendance');
SET @trix_bio_permission_group_id := (SELECT `id` FROM `permission_group` WHERE `short_code` = 'student_attendance' ORDER BY `id` LIMIT 1);
SET @trix_bio_permission_new_id := (SELECT COALESCE(MAX(`id`), 0) + 1 FROM `permission_category`);
INSERT INTO `permission_category` (`id`, `perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)
SELECT @trix_bio_permission_new_id, @trix_bio_permission_group_id, 'Biometric Attendance', 'biometric_attendance', 1, 1, 1, 1, UTC_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `permission_category` WHERE `short_code` = 'biometric_attendance');
SET @trix_bio_permission_id := (SELECT `id` FROM `permission_category` WHERE `short_code` = 'biometric_attendance' ORDER BY `id` LIMIT 1);
SET @trix_bio_role_permission_next_id := (SELECT COALESCE(MAX(`id`), 0) FROM `roles_permissions`);
INSERT INTO `roles_permissions` (`id`, `role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
SELECT (@trix_bio_role_permission_next_id := @trix_bio_role_permission_next_id + 1), r.`id`, @trix_bio_permission_id, 1, 1, 1, 1, UTC_TIMESTAMP()
FROM `roles` r
WHERE r.`name` IN ('Admin', 'Super Admin')
  AND NOT EXISTS (SELECT 1 FROM `roles_permissions` rp WHERE rp.`role_id` = r.`id` AND rp.`perm_cat_id` = @trix_bio_permission_id);

-- ========================================================================

-- Migration 131: versioned ID Card Design Studio

-- ========================================================================

SET @trix_id_card_had_dimensions := (
  SELECT IF(COUNT(*) = 2, 1, 0) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME IN ('card_width', 'card_height')
);
SET @trix_staff_id_card_had_dimensions := (
  SELECT IF(COUNT(*) = 2, 1, 0) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME IN ('card_width', 'card_height')
);

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME = 'card_unit') = 0,
  'ALTER TABLE `id_card` ADD COLUMN `card_unit` VARCHAR(10) NOT NULL DEFAULT ''mm''',
  'SET @trix_noop = 1'
);
PREPARE trix_id_card_card_unit_stmt FROM @trix_schema_sql;
EXECUTE trix_id_card_card_unit_stmt;
DEALLOCATE PREPARE trix_id_card_card_unit_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME = 'card_width') = 0,
  'ALTER TABLE `id_card` ADD COLUMN `card_width` DECIMAL(10,2) NOT NULL DEFAULT 85.60',
  'SET @trix_noop = 1'
);
PREPARE trix_id_card_card_width_stmt FROM @trix_schema_sql;
EXECUTE trix_id_card_card_width_stmt;
DEALLOCATE PREPARE trix_id_card_card_width_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME = 'card_height') = 0,
  'ALTER TABLE `id_card` ADD COLUMN `card_height` DECIMAL(10,2) NOT NULL DEFAULT 53.98',
  'SET @trix_noop = 1'
);
PREPARE trix_id_card_card_height_stmt FROM @trix_schema_sql;
EXECUTE trix_id_card_card_height_stmt;
DEALLOCATE PREPARE trix_id_card_card_height_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME = 'photo_style') = 0,
  'ALTER TABLE `id_card` ADD COLUMN `photo_style` VARCHAR(20) NOT NULL DEFAULT ''round''',
  'SET @trix_noop = 1'
);
PREPARE trix_id_card_photo_style_stmt FROM @trix_schema_sql;
EXECUTE trix_id_card_photo_style_stmt;
DEALLOCATE PREPARE trix_id_card_photo_style_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME = 'layout_json') = 0,
  'ALTER TABLE `id_card` ADD COLUMN `layout_json` LONGTEXT NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_id_card_layout_json_stmt FROM @trix_schema_sql;
EXECUTE trix_id_card_layout_json_stmt;
DEALLOCATE PREPARE trix_id_card_layout_json_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME = 'card_unit') = 0,
  'ALTER TABLE `staff_id_card` ADD COLUMN `card_unit` VARCHAR(10) NOT NULL DEFAULT ''mm''',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_id_card_card_unit_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_id_card_card_unit_stmt;
DEALLOCATE PREPARE trix_staff_id_card_card_unit_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME = 'card_width') = 0,
  'ALTER TABLE `staff_id_card` ADD COLUMN `card_width` DECIMAL(10,2) NOT NULL DEFAULT 85.60',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_id_card_card_width_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_id_card_card_width_stmt;
DEALLOCATE PREPARE trix_staff_id_card_card_width_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME = 'card_height') = 0,
  'ALTER TABLE `staff_id_card` ADD COLUMN `card_height` DECIMAL(10,2) NOT NULL DEFAULT 53.98',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_id_card_card_height_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_id_card_card_height_stmt;
DEALLOCATE PREPARE trix_staff_id_card_card_height_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME = 'photo_style') = 0,
  'ALTER TABLE `staff_id_card` ADD COLUMN `photo_style` VARCHAR(20) NOT NULL DEFAULT ''round''',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_id_card_photo_style_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_id_card_photo_style_stmt;
DEALLOCATE PREPARE trix_staff_id_card_photo_style_stmt;

SET @trix_schema_sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME = 'layout_json') = 0,
  'ALTER TABLE `staff_id_card` ADD COLUMN `layout_json` LONGTEXT NULL',
  'SET @trix_noop = 1'
);
PREPARE trix_staff_id_card_layout_json_stmt FROM @trix_schema_sql;
EXECUTE trix_staff_id_card_layout_json_stmt;
DEALLOCATE PREPARE trix_staff_id_card_layout_json_stmt;

UPDATE `id_card` SET `card_width` = 53.98, `card_height` = 85.60, `card_unit` = 'mm'
WHERE @trix_id_card_had_dimensions = 0 AND `enable_vertical_card` = 1;
UPDATE `staff_id_card` SET `card_width` = 53.98, `card_height` = 85.60, `card_unit` = 'mm'
WHERE @trix_staff_id_card_had_dimensions = 0 AND `enable_vertical_card` = 1;

CREATE TABLE IF NOT EXISTS `id_card_designs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject_type` VARCHAR(16) NOT NULL,
    `legacy_template_id` INT NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `width_mm` DECIMAL(8,3) NOT NULL DEFAULT 85.600,
    `height_mm` DECIMAL(8,3) NOT NULL DEFAULT 53.980,
    `orientation` VARCHAR(16) NOT NULL DEFAULT 'landscape',
    `draft_version_id` BIGINT UNSIGNED DEFAULT NULL,
    `published_version_id` BIGINT UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id_card_design_legacy_unique` (`subject_type`, `legacy_template_id`),
    KEY `id_card_design_status_idx` (`subject_type`, `is_active`),
    KEY `id_card_design_published_idx` (`published_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `id_card_design_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `design_id` BIGINT UNSIGNED NOT NULL,
    `version_no` INT UNSIGNED NOT NULL,
    `state` VARCHAR(16) NOT NULL DEFAULT 'draft',
    `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `front_json` LONGTEXT NOT NULL,
    `back_json` LONGTEXT NOT NULL,
    `print_settings_json` LONGTEXT NOT NULL,
    `checksum` CHAR(64) NOT NULL,
    `created_by` INT DEFAULT NULL,
    `published_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `published_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id_card_design_version_unique` (`design_id`, `version_no`),
    KEY `id_card_design_version_state_idx` (`design_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `id_card_design_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `design_id` BIGINT UNSIGNED NOT NULL,
    `storage_key` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(191) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `byte_size` INT UNSIGNED NOT NULL,
    `pixel_width` INT UNSIGNED DEFAULT NULL,
    `pixel_height` INT UNSIGNED DEFAULT NULL,
    `sha256` CHAR(64) NOT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `deleted_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_card_design_asset_design_idx` (`design_id`, `deleted_at`),
    KEY `id_card_design_asset_hash_idx` (`sha256`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `id_card_design_audit` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `design_id` BIGINT UNSIGNED NOT NULL,
    `version_id` BIGINT UNSIGNED DEFAULT NULL,
    `staff_id` INT DEFAULT NULL,
    `action` VARCHAR(40) NOT NULL,
    `summary_json` LONGTEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `id_card_design_audit_design_idx` (`design_id`, `created_at`),
    KEY `id_card_design_audit_staff_idx` (`staff_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration 132: outbound Windows connector heartbeat and fixed web actions.
-- The website cannot supply command text or arguments. The authenticated
-- connector may claim only the fixed command types enforced by application
-- code, then return a bounded and redacted result.

CREATE TABLE IF NOT EXISTS `biometric_gateway_agents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `integration_id` INT UNSIGNED NOT NULL,
    `gateway_id` VARCHAR(128) NOT NULL,
    `gateway_version` VARCHAR(40) DEFAULT NULL,
    `status_json` TEXT NULL,
    `provider_reachable` TINYINT(1) DEFAULT NULL,
    `last_sync_ok` TINYINT(1) DEFAULT NULL,
    `last_sync_at` DATETIME DEFAULT NULL,
    `queue_pending` INT UNSIGNED NOT NULL DEFAULT 0,
    `queue_retry` INT UNSIGNED NOT NULL DEFAULT 0,
    `queue_dead` INT UNSIGNED NOT NULL DEFAULT 0,
    `provider_cursor` VARCHAR(191) DEFAULT NULL,
    `last_error` VARCHAR(500) DEFAULT NULL,
    `first_seen_at` DATETIME NOT NULL,
    `last_heartbeat_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_gateway_agent_id` (`gateway_id`),
    KEY `idx_biometric_gateway_agent_heartbeat` (`integration_id`, `last_heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_gateway_commands` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `command_uuid` CHAR(32) NOT NULL,
    `integration_id` INT UNSIGNED NOT NULL,
    `gateway_id` VARCHAR(128) DEFAULT NULL,
    `command_type` VARCHAR(32) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
    `result_json` TEXT NULL,
    `queued_by` INT DEFAULT NULL,
    `queued_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `claimed_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_biometric_gateway_command_uuid` (`command_uuid`),
    KEY `idx_biometric_gateway_command_claim` (`integration_id`, `status`, `expires_at`, `id`),
    KEY `idx_biometric_gateway_command_gateway` (`integration_id`, `gateway_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration 130-132 verification. Every result set below must be empty.

SELECT required.`table_name` AS `missing_migration_table`
FROM (
  SELECT 'biometric_settings' AS `table_name`
  UNION ALL SELECT 'biometric_integrations' AS `table_name`
  UNION ALL SELECT 'biometric_devices' AS `table_name`
  UNION ALL SELECT 'biometric_punch_state_mappings' AS `table_name`
  UNION ALL SELECT 'biometric_identity_mappings' AS `table_name`
  UNION ALL SELECT 'biometric_gateway_batches' AS `table_name`
  UNION ALL SELECT 'biometric_gateway_cursors' AS `table_name`
  UNION ALL SELECT 'biometric_attendance_days' AS `table_name`
  UNION ALL SELECT 'biometric_events' AS `table_name`
  UNION ALL SELECT 'biometric_exceptions' AS `table_name`
  UNION ALL SELECT 'biometric_reconciliation_actions' AS `table_name`
  UNION ALL SELECT 'biometric_scanner_stations' AS `table_name`
  UNION ALL SELECT 'biometric_qr_credentials' AS `table_name`
  UNION ALL SELECT 'biometric_audit_logs' AS `table_name`
  UNION ALL SELECT 'biometric_gateway_agents' AS `table_name`
  UNION ALL SELECT 'biometric_gateway_commands' AS `table_name`
  UNION ALL SELECT 'id_card_designs' AS `table_name`
  UNION ALL SELECT 'id_card_design_versions' AS `table_name`
  UNION ALL SELECT 'id_card_design_assets' AS `table_name`
  UNION ALL SELECT 'id_card_design_audit' AS `table_name`
) AS required
LEFT JOIN INFORMATION_SCHEMA.TABLES actual
  ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = required.table_name
WHERE actual.TABLE_NAME IS NULL
ORDER BY required.table_name;

SELECT required.`table_name`, required.`column_name` AS `missing_migration_column`
FROM (
  SELECT 'student_attendences' AS `table_name`, 'attendance_source' AS `column_name`
  UNION ALL SELECT 'student_attendences', 'biometric_day_id'
  UNION ALL SELECT 'staff_attendance', 'attendance_source'
  UNION ALL SELECT 'staff_attendance', 'biometric_day_id'
  UNION ALL SELECT 'id_card', 'card_unit'
  UNION ALL SELECT 'id_card', 'card_width'
  UNION ALL SELECT 'id_card', 'card_height'
  UNION ALL SELECT 'id_card', 'photo_style'
  UNION ALL SELECT 'id_card', 'layout_json'
  UNION ALL SELECT 'staff_id_card', 'card_unit'
  UNION ALL SELECT 'staff_id_card', 'card_width'
  UNION ALL SELECT 'staff_id_card', 'card_height'
  UNION ALL SELECT 'staff_id_card', 'photo_style'
  UNION ALL SELECT 'staff_id_card', 'layout_json'
) AS required
LEFT JOIN INFORMATION_SCHEMA.COLUMNS actual
  ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = required.table_name AND actual.COLUMN_NAME = required.column_name
WHERE actual.COLUMN_NAME IS NULL
ORDER BY required.table_name, required.column_name;

SELECT 'biometric_settings' AS `missing_seed`, 1 AS `expected_id`
WHERE NOT EXISTS (SELECT 1 FROM `biometric_settings` WHERE `id` = 1)
UNION ALL SELECT 'SIM-GATE-001', 1 WHERE NOT EXISTS (SELECT 1 FROM `biometric_devices` WHERE `serial_number` = 'SIM-GATE-001')
UNION ALL SELECT 'biometric_attendance_permission', 1 WHERE NOT EXISTS (SELECT 1 FROM `permission_category` WHERE `short_code` = 'biometric_attendance');

-- END GENERATED MIGRATIONS 130-132

-- ========================================================================
-- Migration 133: advisory promotion criteria and promotion-note overrides.
-- This migration never changes `student_session`; actual class movement
-- remains in the existing manual Promote Students workflow.
-- ========================================================================

CREATE TABLE IF NOT EXISTS `promotion_criteria` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id` INT NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `minimum_average` DECIMAL(5,2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `promotion_criteria_session_idx` (`session_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promotion_criteria_classes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `criteria_id` BIGINT UNSIGNED NOT NULL,
    `session_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `promoted_to_class_id` INT DEFAULT NULL,
    `promoted_to_label` VARCHAR(191) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promotion_criteria_class_unique` (`session_id`, `class_id`),
    KEY `promotion_criteria_class_criteria_idx` (`criteria_id`),
    KEY `promotion_criteria_class_target_idx` (`promoted_to_class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promotion_criteria_subjects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `criteria_id` BIGINT UNSIGNED NOT NULL,
    `subject_id` INT NOT NULL,
    `minimum_average` DECIMAL(5,2) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `promotion_criteria_subject_unique` (`criteria_id`, `subject_id`),
    KEY `promotion_criteria_subject_subject_idx` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promotion_note_overrides` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT NOT NULL,
    `session_id` INT NOT NULL,
    `class_id` INT NOT NULL,
    `section_id` INT NOT NULL,
    `action` VARCHAR(10) NOT NULL,
    `decision` VARCHAR(20) DEFAULT NULL,
    `target_class_id` INT DEFAULT NULL,
    `target_label` VARCHAR(191) DEFAULT NULL,
    `reason` TEXT NOT NULL,
    `automatic_decision` VARCHAR(20) NOT NULL,
    `automatic_note` VARCHAR(255) NOT NULL,
    `created_by` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `promotion_override_scope_idx` (`student_id`, `session_id`, `class_id`, `section_id`, `id`),
    KEY `promotion_override_review_idx` (`session_id`, `class_id`, `section_id`, `id`),
    KEY `promotion_override_actor_idx` (`created_by`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `permission_group`
    (`name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT 'Exam Setting', 'exam_setting', 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_group` WHERE `short_code` = 'exam_setting'
);

SET @trix_promotion_exam_group_id := (
    SELECT `id`
    FROM `permission_group`
    WHERE `short_code` = 'exam_setting'
    ORDER BY `id`
    LIMIT 1
);

INSERT INTO `permission_category`
    (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`,
     `enable_edit`, `enable_delete`, `created_at`)
SELECT @trix_promotion_exam_group_id, 'Manage Promotion Criteria',
       'manage_promotion_criteria', 1, 1, 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_category`
    WHERE `short_code` = 'manage_promotion_criteria'
);

UPDATE `permission_category`
SET `perm_group_id` = @trix_promotion_exam_group_id,
    `name` = 'Manage Promotion Criteria',
    `enable_view` = 1,
    `enable_add` = 1,
    `enable_edit` = 1,
    `enable_delete` = 0
WHERE `short_code` = 'manage_promotion_criteria';

INSERT INTO `permission_category`
    (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`,
     `enable_edit`, `enable_delete`, `created_at`)
SELECT @trix_promotion_exam_group_id, 'Override Promotion Note',
       'override_promotion_note', 1, 1, 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_category`
    WHERE `short_code` = 'override_promotion_note'
);

UPDATE `permission_category`
SET `perm_group_id` = @trix_promotion_exam_group_id,
    `name` = 'Override Promotion Note',
    `enable_view` = 1,
    `enable_add` = 1,
    `enable_edit` = 1,
    `enable_delete` = 0
WHERE `short_code` = 'override_promotion_note';

SET @trix_manage_promotion_permission_id := (
    SELECT `id` FROM `permission_category`
    WHERE `short_code` = 'manage_promotion_criteria'
    ORDER BY `id` LIMIT 1
);

SET @trix_override_promotion_permission_id := (
    SELECT `id` FROM `permission_category`
    WHERE `short_code` = 'override_promotion_note'
    ORDER BY `id` LIMIT 1
);

INSERT INTO `roles_permissions`
    (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`,
     `can_delete`, `created_at`)
SELECT `roles`.`id`, @trix_manage_promotion_permission_id, 1, 1, 1, 0, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Admin', 'Head Teacher', 'Super Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `roles_permissions`
      WHERE `roles_permissions`.`role_id` = `roles`.`id`
        AND `roles_permissions`.`perm_cat_id` = @trix_manage_promotion_permission_id
  );

UPDATE `roles_permissions`
INNER JOIN `roles` ON `roles`.`id` = `roles_permissions`.`role_id`
SET `roles_permissions`.`can_view` = 1,
    `roles_permissions`.`can_add` = 1,
    `roles_permissions`.`can_edit` = 1,
    `roles_permissions`.`can_delete` = 0
WHERE `roles_permissions`.`perm_cat_id` = @trix_manage_promotion_permission_id
  AND `roles`.`name` IN ('Admin', 'Head Teacher', 'Super Admin');

INSERT INTO `roles_permissions`
    (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`,
     `can_delete`, `created_at`)
SELECT `roles`.`id`, @trix_override_promotion_permission_id, 1, 1, 1, 0, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Teacher', 'Admin', 'Head Teacher', 'Super Admin')
  AND NOT EXISTS (
      SELECT 1 FROM `roles_permissions`
      WHERE `roles_permissions`.`role_id` = `roles`.`id`
        AND `roles_permissions`.`perm_cat_id` = @trix_override_promotion_permission_id
  );

UPDATE `roles_permissions`
INNER JOIN `roles` ON `roles`.`id` = `roles_permissions`.`role_id`
SET `roles_permissions`.`can_view` = 1,
    `roles_permissions`.`can_add` = 1,
    `roles_permissions`.`can_edit` = 1,
    `roles_permissions`.`can_delete` = 0
WHERE `roles_permissions`.`perm_cat_id` = @trix_override_promotion_permission_id
  AND `roles`.`name` IN ('Teacher', 'Admin', 'Head Teacher', 'Super Admin');

-- Migration 133 verification. Every result set below must be empty.
SELECT required.`table_name` AS `missing_promotion_table`
FROM (
    SELECT 'promotion_criteria' AS `table_name`
    UNION ALL SELECT 'promotion_criteria_classes'
    UNION ALL SELECT 'promotion_criteria_subjects'
    UNION ALL SELECT 'promotion_note_overrides'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
WHERE actual.`TABLE_NAME` IS NULL
ORDER BY required.`table_name`;

SELECT required.`permission_code` AS `missing_promotion_permission`
FROM (
    SELECT 'manage_promotion_criteria' AS `permission_code`
    UNION ALL SELECT 'override_promotion_note'
) AS required
LEFT JOIN `permission_category` AS actual
    ON actual.`short_code` = required.`permission_code`
   AND actual.`enable_view` = 1
   AND actual.`enable_add` = 1
   AND actual.`enable_edit` = 1
   AND actual.`enable_delete` = 0
WHERE actual.`id` IS NULL;

SELECT expected.`role_name`, expected.`permission_code`
       AS `missing_default_promotion_grant`
FROM (
    SELECT 'Admin' AS `role_name`,
           'manage_promotion_criteria' AS `permission_code`
    UNION ALL SELECT 'Head Teacher', 'manage_promotion_criteria'
    UNION ALL SELECT 'Super Admin', 'manage_promotion_criteria'
    UNION ALL SELECT 'Teacher', 'override_promotion_note'
    UNION ALL SELECT 'Admin', 'override_promotion_note'
    UNION ALL SELECT 'Head Teacher', 'override_promotion_note'
    UNION ALL SELECT 'Super Admin', 'override_promotion_note'
) AS expected
LEFT JOIN `roles` AS role_row
    ON role_row.`name` = expected.`role_name`
LEFT JOIN `permission_category` AS permission_row
    ON permission_row.`short_code` = expected.`permission_code`
LEFT JOIN `roles_permissions` AS grant_row
    ON grant_row.`role_id` = role_row.`id`
   AND grant_row.`perm_cat_id` = permission_row.`id`
   AND grant_row.`can_view` = 1
   AND grant_row.`can_add` = 1
   AND grant_row.`can_edit` = 1
   AND grant_row.`can_delete` = 0
WHERE grant_row.`id` IS NULL
ORDER BY expected.`permission_code`, expected.`role_name`;

-- ========================================================================
-- Migration 134: opt-in staff alerts and external-email permission.
-- ========================================================================

CREATE TABLE IF NOT EXISTS `support_email_notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `email` varchar(191) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_notified_at` datetime DEFAULT NULL,
  `last_error` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_email_notifications_staff_unique` (`staff_id`),
  KEY `support_email_notifications_email_idx` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `support_email_alert_deliveries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int unsigned NOT NULL,
  `incoming_email_id` int unsigned NOT NULL,
  `support_ticket_id` int unsigned NOT NULL,
  `school_domain` varchar(253) NOT NULL,
  `inbound_address` varchar(191) NOT NULL,
  `recipient_email` varchar(191) NOT NULL,
  `delivery_status` varchar(30) NOT NULL DEFAULT 'pending',
  `attempt_count` smallint unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL,
  `last_attempt_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `provider_message_id` varchar(255) DEFAULT NULL,
  `error_message` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_email_alert_delivery_unique` (`notification_id`, `incoming_email_id`),
  KEY `support_email_alert_queue_idx` (`delivery_status`, `available_at`),
  KEY `support_email_alert_ticket_idx` (`support_ticket_id`),
  KEY `support_email_alert_incoming_idx` (`incoming_email_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add the required unique staff constraint when a partial/manual table exists.
SET @support_notification_staff_unique_exists := (
  SELECT COUNT(*)
  FROM (
    SELECT `INDEX_NAME`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'support_email_notifications'
    GROUP BY `INDEX_NAME`
    HAVING MIN(`NON_UNIQUE`) = 0
       AND GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') = 'staff_id'
  ) AS equivalent_unique_indexes
);
SET @support_notification_staff_ddl := IF(
  @support_notification_staff_unique_exists = 0,
  'ALTER TABLE `support_email_notifications` ADD UNIQUE KEY `support_email_notifications_staff_unique` (`staff_id`)',
  'SET @support_notification_noop = 1'
);
PREPARE support_notification_staff_stmt FROM @support_notification_staff_ddl;
EXECUTE support_notification_staff_stmt;
DEALLOCATE PREPARE support_notification_staff_stmt;

-- Add an email lookup index when no equivalent ordered index exists.
SET @support_notification_email_index_exists := (
  SELECT COUNT(*)
  FROM (
    SELECT `INDEX_NAME`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'support_email_notifications'
    GROUP BY `INDEX_NAME`
    HAVING GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') = 'email'
  ) AS equivalent_email_indexes
);
SET @support_notification_email_ddl := IF(
  @support_notification_email_index_exists = 0,
  'ALTER TABLE `support_email_notifications` ADD KEY `support_email_notifications_email_idx` (`email`)',
  'SET @support_notification_noop = 1'
);
PREPARE support_notification_email_stmt FROM @support_notification_email_ddl;
EXECUTE support_notification_email_stmt;
DEALLOCATE PREPARE support_notification_email_stmt;

INSERT INTO `permission_group`
  (`name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT 'Communicate', 'communicate', 1, 0, NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `permission_group` WHERE `short_code` = 'communicate'
);

SET @external_email_communicate_group_id := (
  SELECT `id`
  FROM `permission_group`
  WHERE `short_code` = 'communicate'
  ORDER BY `id`
  LIMIT 1
);

INSERT INTO `permission_category`
  (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`,
   `enable_edit`, `enable_delete`, `created_at`)
SELECT @external_email_communicate_group_id, 'Send External Email',
       'external_email', 1, 1, 0, 0, NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM `permission_category`
  WHERE `short_code` = 'external_email'
);

UPDATE `permission_category`
SET `perm_group_id` = @external_email_communicate_group_id,
    `name` = 'Send External Email',
    `enable_view` = 1,
    `enable_add` = 1,
    `enable_edit` = 0,
    `enable_delete` = 0
WHERE `short_code` = 'external_email';

SET @external_email_permission_id := (
  SELECT `id`
  FROM `permission_category`
  WHERE `short_code` = 'external_email'
  ORDER BY `id`
  LIMIT 1
);

INSERT INTO `roles_permissions`
  (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`,
   `can_delete`, `created_at`)
SELECT `roles`.`id`, @external_email_permission_id, 1, 1, 0, 0, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Admin', 'Super Admin')
  AND NOT EXISTS (
    SELECT 1
    FROM `roles_permissions`
    WHERE `roles_permissions`.`role_id` = `roles`.`id`
      AND `roles_permissions`.`perm_cat_id` = @external_email_permission_id
  );

UPDATE `roles_permissions`
INNER JOIN `roles`
  ON `roles`.`id` = `roles_permissions`.`role_id`
SET `roles_permissions`.`can_view` = 1,
    `roles_permissions`.`can_add` = 1,
    `roles_permissions`.`can_edit` = 0,
    `roles_permissions`.`can_delete` = 0
WHERE `roles_permissions`.`perm_cat_id` = @external_email_permission_id
  AND `roles`.`name` IN ('Admin', 'Super Admin');

-- Migration 134 verification. Every result set below must be empty.
SELECT 'support_email_notifications' AS `missing_external_email_notification_table`
WHERE NOT EXISTS (
  SELECT 1
  FROM `INFORMATION_SCHEMA`.`TABLES`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'support_email_notifications'
);

SELECT 'support_email_alert_deliveries' AS `missing_external_email_notification_table`
WHERE NOT EXISTS (
  SELECT 1
  FROM `INFORMATION_SCHEMA`.`TABLES`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'support_email_alert_deliveries'
);

SELECT required.`column_name` AS `missing_external_email_notification_column`
FROM (
  SELECT 'id' AS `column_name`
  UNION ALL SELECT 'staff_id'
  UNION ALL SELECT 'email'
  UNION ALL SELECT 'is_active'
  UNION ALL SELECT 'last_notified_at'
  UNION ALL SELECT 'last_error'
  UNION ALL SELECT 'created_at'
  UNION ALL SELECT 'updated_at'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = 'support_email_notifications'
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`column_name`;

SELECT required.`requirement` AS `missing_external_email_notification_index`
FROM (
  SELECT 'PRIMARY(id)' AS `requirement`, 'id' AS `columns`, 0 AS `non_unique`
  UNION ALL SELECT 'UNIQUE(staff_id)', 'staff_id', 0
  UNION ALL SELECT 'INDEX(email)', 'email', 1
) AS required
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT
      `INDEX_NAME`,
      MIN(`NON_UNIQUE`) AS `non_unique`,
      GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'support_email_notifications'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = required.`columns`
    AND actual.`non_unique` = required.`non_unique`
)
ORDER BY required.`requirement`;

SELECT 'external_email' AS `missing_external_email_permission`
WHERE NOT EXISTS (
  SELECT 1
  FROM `permission_category`
  INNER JOIN `permission_group`
    ON `permission_group`.`id` = `permission_category`.`perm_group_id`
  WHERE `permission_category`.`short_code` = 'external_email'
    AND `permission_category`.`name` = 'Send External Email'
    AND `permission_category`.`enable_view` = 1
    AND `permission_category`.`enable_add` = 1
    AND `permission_category`.`enable_edit` = 0
    AND `permission_category`.`enable_delete` = 0
    AND `permission_group`.`short_code` = 'communicate'
);

SELECT expected.`role_name` AS `missing_default_external_email_grant`
FROM (
  SELECT 'Admin' AS `role_name`
  UNION ALL SELECT 'Super Admin'
) AS expected
INNER JOIN `roles` AS role_row
  ON role_row.`name` = expected.`role_name`
LEFT JOIN `roles_permissions` AS grant_row
  ON grant_row.`role_id` = role_row.`id`
 AND grant_row.`perm_cat_id` = @external_email_permission_id
 AND grant_row.`can_view` = 1
 AND grant_row.`can_add` = 1
 AND grant_row.`can_edit` = 0
 AND grant_row.`can_delete` = 0
WHERE grant_row.`role_id` IS NULL
ORDER BY expected.`role_name`;

-- ========================================================================
-- Migration 135: compact CBT Online Examination and Holiday result posting
-- ========================================================================

CREATE TABLE IF NOT EXISTS `onlineexam_holiday_mappings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `onlineexam_id` INT NOT NULL,
  `section_id` INT NOT NULL,
  `setting_id` INT NOT NULL,
  `setting_subject_id` INT NOT NULL,
  `max_score` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `onlineexam_holiday_mapping_section_unique` (`onlineexam_id`, `section_id`),
  KEY `onlineexam_holiday_mapping_setting_idx` (`setting_id`, `setting_subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
      AND `COLUMN_NAME` = 'score_origin'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD COLUMN `score_origin` VARCHAR(24) NOT NULL DEFAULT ''manual'' AFTER `max_score`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
      AND `COLUMN_NAME` = 'source_onlineexam_id'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD COLUMN `source_onlineexam_id` INT NULL AFTER `score_origin`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
      AND `COLUMN_NAME` = 'source_attempt_id'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD COLUMN `source_attempt_id` BIGINT UNSIGNED NULL AFTER `source_onlineexam_id`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
      AND `COLUMN_NAME` = 'source_sync_id'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD COLUMN `source_sync_id` BIGINT UNSIGNED NULL AFTER `source_attempt_id`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
      AND `COLUMN_NAME` = 'updated_at'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD COLUMN `updated_at` DATETIME NULL AFTER `source_sync_id`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

UPDATE `holiday_assessment_scores`
SET `score_origin` = 'manual'
WHERE `score_origin` IS NULL OR `score_origin` = '';

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1
    FROM (
      SELECT `INDEX_NAME`,
             GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
      FROM `INFORMATION_SCHEMA`.`STATISTICS`
      WHERE `TABLE_SCHEMA` = DATABASE()
        AND `TABLE_NAME` = 'holiday_assessment_scores'
      GROUP BY `INDEX_NAME`
    ) AS existing_index
    WHERE existing_index.`columns` = 'source_onlineexam_id,source_attempt_id'
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD KEY `holiday_assessment_scores_source_idx` (`source_onlineexam_id`, `source_attempt_id`)',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1
    FROM (
      SELECT `INDEX_NAME`, MIN(`NON_UNIQUE`) AS `non_unique`,
             GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
      FROM `INFORMATION_SCHEMA`.`STATISTICS`
      WHERE `TABLE_SCHEMA` = DATABASE()
        AND `TABLE_NAME` = 'holiday_assessment_scores'
      GROUP BY `INDEX_NAME`
    ) AS existing_index
    WHERE existing_index.`columns` = 'source_sync_id'
      AND existing_index.`non_unique` = 0
  ),
  'ALTER TABLE `holiday_assessment_scores` ADD UNIQUE KEY `holiday_assessment_scores_source_sync_unique` (`source_sync_id`)',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_result_sync'
      AND `COLUMN_NAME` = 'previous_metadata_json'
  ),
  'ALTER TABLE `onlineexam_result_sync` ADD COLUMN `previous_metadata_json` LONGTEXT NULL AFTER `previous_value`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

SET @onlineexam_135_sql := IF(
  NOT EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_result_sync'
      AND `COLUMN_NAME` = 'applied_metadata_json'
  ),
  'ALTER TABLE `onlineexam_result_sync` ADD COLUMN `applied_metadata_json` LONGTEXT NULL AFTER `applied_value`',
  'SELECT 1'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

ALTER TABLE `onlineexam`
  MODIFY `result_adapter` VARCHAR(32) NOT NULL DEFAULT 'standard_component';

-- Retire the removed internal-only destination without deleting its history.
UPDATE `onlineexam`
SET `result_adapter` = 'legacy_read_only',
    `lifecycle_status` = 'legacy',
    `is_active` = 0
WHERE `result_adapter` = 'unlinked_practice';

-- ========================================================================
-- Migration 136: remove retired Question Bank difficulty/Level
-- Only questions.level and its labels are removed. Options, answers, attempts,
-- results, academic/class levels, and frozen examination history are preserved.
-- Restore removed difficulty labels from the pre-upgrade backup if needed.
-- ========================================================================

SET @onlineexam_136_preflight_sql := IF(
  DATABASE() IS NOT NULL AND EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'questions'
      AND `COLUMN_NAME` = 'id'
  ),
  'SELECT 1 AS question_bank_ready',
  'SELECT * FROM `SCHOOLLIFT_136_SELECT_A_SCHOOL_DATABASE_WITH_QUESTIONS`'
);
PREPARE onlineexam_136_stmt FROM @onlineexam_136_preflight_sql;
EXECUTE onlineexam_136_stmt;
DEALLOCATE PREPARE onlineexam_136_stmt;

SET @onlineexam_136_sql := IF(
  EXISTS (
    SELECT 1 FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'questions'
      AND `COLUMN_NAME` = 'level'
  ),
  'ALTER TABLE `questions` DROP COLUMN `level`',
  'SELECT 1 AS question_level_already_removed'
);
PREPARE onlineexam_136_stmt FROM @onlineexam_136_sql;
EXECUTE onlineexam_136_stmt;
DEALLOCATE PREPARE onlineexam_136_stmt;

-- ========================================================================
-- Consolidated verification
-- Every result set below must be empty.
-- ========================================================================

SELECT `TABLE_NAME`, `COLUMN_NAME` AS `retired_question_column_still_present`
FROM `INFORMATION_SCHEMA`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'questions'
  AND `COLUMN_NAME` = 'level';

SELECT required.`table_name` AS `missing_migration_table`
FROM (
  SELECT 'incoming_emails' AS `table_name`
  UNION ALL SELECT 'support_tickets'
  UNION ALL SELECT 'support_messages'
  UNION ALL SELECT 'onlineexam_class_sections'
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
  UNION ALL SELECT 'onlineexam_holiday_mappings'
  UNION ALL SELECT 'onlineexam_result_target_locks'
  UNION ALL SELECT 'onlineexam_result_sync'
  UNION ALL SELECT 'onlineexam_incidents'
  UNION ALL SELECT 'onlineexam_audit_log'
  UNION ALL SELECT 'monnify_payments'
  UNION ALL SELECT 'promotion_criteria'
  UNION ALL SELECT 'promotion_criteria_classes'
  UNION ALL SELECT 'promotion_criteria_subjects'
  UNION ALL SELECT 'promotion_note_overrides'
  UNION ALL SELECT 'support_email_notifications'
  UNION ALL SELECT 'support_email_alert_deliveries'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
WHERE actual.`TABLE_NAME` IS NULL
ORDER BY required.`table_name`;

-- Detect any partially-created migration table, regardless of extra custom
-- columns a tenant may legitimately have added.
SELECT
  required.`table_name` AS `table_with_missing_columns`,
  required.`expected_count` - (
    SELECT COUNT(DISTINCT actual.`COLUMN_NAME`)
    FROM `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    WHERE actual.`TABLE_SCHEMA` = DATABASE()
      AND actual.`TABLE_NAME` = required.`table_name`
      AND FIND_IN_SET(actual.`COLUMN_NAME`, required.`expected_columns`) > 0
  ) AS `missing_column_count`,
  required.`expected_columns` AS `required_column_set`
FROM (
  SELECT 'incoming_emails' AS `table_name`, 'id,sns_type,sns_message_id,sns_topic_arn,ses_notification_type,ses_message_id,source,subject,destinations_json,recipients_json,headers_json,mail_timestamp,receipt_timestamp,action_type,s3_bucket,s3_object_key,spam_status,virus_status,dkim_status,spf_status,dmarc_status,body_text,body_html,attachment_count,attachment_names_json,raw_content,raw_payload,status,error_message,created_at,updated_at' AS `expected_columns`, 31 AS `expected_count`
  UNION ALL SELECT 'support_tickets' AS `table_name`, 'id,ticket_number,source,requester_name,requester_email,requester_phone,subject,status,priority,assigned_staff_id,incoming_email_id,last_incoming_email_id,message_id,last_outgoing_message_id,last_message_at,last_customer_message_at,last_staff_message_at,opened_at,closed_at,created_at,updated_at' AS `expected_columns`, 21 AS `expected_count`
  UNION ALL SELECT 'support_messages' AS `table_name`, 'id,support_ticket_id,incoming_email_id,direction,sender_type,sender_staff_id,sender_name,sender_email,recipients_json,subject,body_text,body_html,message_id,in_reply_to,references_header,attachment_count,attachment_names_json,delivery_status,error_message,created_at,updated_at' AS `expected_columns`, 21 AS `expected_count`
  UNION ALL SELECT 'onlineexam_class_sections' AS `table_name`, 'id,onlineexam_id,section_id,created_at' AS `expected_columns`, 4 AS `expected_count`
  UNION ALL SELECT 'onlineexam_papers' AS `table_name`, 'id,onlineexam_id,title,paper_code,paper_type,delivery_mode,instructions,starts_at,ends_at,duration_minutes,raw_max_score,contribution_score,display_order,is_active,created_at,updated_at' AS `expected_columns`, 16 AS `expected_count`
  UNION ALL SELECT 'onlineexam_paper_sections' AS `table_name`, 'id,paper_id,title,instructions,answer_rule,answer_count,display_order,created_at,updated_at' AS `expected_columns`, 9 AS `expected_count`
  UNION ALL SELECT 'onlineexam_question_definitions' AS `table_name`, 'id,question_id,definition_version,definition_json,created_by,created_at,updated_at' AS `expected_columns`, 7 AS `expected_count`
  UNION ALL SELECT 'onlineexam_revision_snapshots' AS `table_name`, 'id,onlineexam_id,revision,configuration_json,checksum,frozen_by,frozen_at' AS `expected_columns`, 7 AS `expected_count`
  UNION ALL SELECT 'onlineexam_question_snapshots' AS `table_name`, 'id,onlineexam_id,paper_id,paper_section_id,source_question_id,revision,question_type,question_text,options_json,correct_answer_json,response_schema_json,passage_group_key,passage_title,passage_text,marking_scheme,marks,neg_marks,is_compulsory,display_order,checksum,created_at' AS `expected_columns`, 21 AS `expected_count`
  UNION ALL SELECT 'onlineexam_candidate_attempts' AS `table_name`, 'id,onlineexam_id,onlineexam_student_id,revision,attempt_no,status,started_at,deadline_at,submitted_at,last_saved_at,submission_key,extra_time_minutes,raw_score,raw_max_score,weighted_score,weighted_max_score,final_score,outcome_value,manual_marking_required,marking_status,voided_at,voided_by,void_reason,created_at,updated_at' AS `expected_columns`, 25 AS `expected_count`
  UNION ALL SELECT 'onlineexam_attempt_papers' AS `table_name`, 'id,attempt_id,paper_id,status,started_at,deadline_at,submitted_at,raw_score,raw_max_score,contribution_score,contribution_max_score,manual_score,manual_marking_status,marked_by,marked_at,marking_notes,created_at,updated_at' AS `expected_columns`, 18 AS `expected_count`
  UNION ALL SELECT 'onlineexam_attempt_answers' AS `table_name`, 'id,attempt_id,question_snapshot_id,response_json,response_hash,attachment_name,attachment_path,attachment_mime,attachment_size,is_answered,is_correct,auto_mark,manual_mark,final_mark,marking_status,version,client_sequence,saved_at,submitted_at,created_at,updated_at' AS `expected_columns`, 21 AS `expected_count`
  UNION ALL SELECT 'onlineexam_accommodations' AS `table_name`, 'id,onlineexam_id,onlineexam_student_id,extra_time_minutes,makeup_attempts,makeup_expires_at,notes,authorized_by,created_at,updated_at' AS `expected_columns`, 10 AS `expected_count`
  UNION ALL SELECT 'onlineexam_marking' AS `table_name`, 'id,attempt_answer_id,marking_version,marks,rubric_json,remark,status,marked_by,marked_at,reviewed_by,reviewed_at,created_at' AS `expected_columns`, 12 AS `expected_count`
  UNION ALL SELECT 'onlineexam_paper_marking' AS `table_name`, 'id,attempt_paper_id,marking_version,raw_marks,rubric_json,remark,status,marked_by,marked_at,reviewed_by,reviewed_at,created_at' AS `expected_columns`, 12 AS `expected_count`
  UNION ALL SELECT 'onlineexam_result_profiles' AS `table_name`, 'id,onlineexam_id,adapter,name,configuration_json,is_active,created_by,created_at,updated_at' AS `expected_columns`, 9 AS `expected_count`
  UNION ALL SELECT 'onlineexam_kindergarten_mappings' AS `table_name`, 'id,onlineexam_id,paper_id,paper_section_id,assessment_id,assessment_subject_id,subject_id,concept_id,concept_stable_key,outcome_profile_json,created_at,updated_at' AS `expected_columns`, 12 AS `expected_count`
  UNION ALL SELECT 'onlineexam_holiday_mappings' AS `table_name`, 'id,onlineexam_id,section_id,setting_id,setting_subject_id,max_score,created_at,updated_at' AS `expected_columns`, 8 AS `expected_count`
  UNION ALL SELECT 'onlineexam_result_target_locks' AS `table_name`, 'target_hash,target_descriptor,created_at,updated_at' AS `expected_columns`, 4 AS `expected_count`
  UNION ALL SELECT 'onlineexam_result_sync' AS `table_name`, 'id,onlineexam_id,attempt_id,student_session_id,adapter,target_table,target_record_id,target_field,source_score,scaled_score,previous_value,previous_metadata_json,applied_value,applied_metadata_json,status,conflict_reason,error_message,reversed_at,reversed_by,reversal_reason,override_authorized_at,override_authorized_by,override_reason,source_fingerprint,idempotency_key,created_at,synced_at,updated_at' AS `expected_columns`, 28 AS `expected_count`
  UNION ALL SELECT 'onlineexam_incidents' AS `table_name`, 'id,onlineexam_id,attempt_id,onlineexam_student_id,incident_type,severity,details,status,reported_by,resolved_by,resolved_at,created_at,updated_at' AS `expected_columns`, 13 AS `expected_count`
  UNION ALL SELECT 'onlineexam_audit_log' AS `table_name`, 'id,onlineexam_id,attempt_id,actor_id,actor_type,action,entity_type,entity_id,before_json,after_json,ip_address,created_at' AS `expected_columns`, 12 AS `expected_count`
  UNION ALL SELECT 'holiday_assessment_scores' AS `table_name`, 'id,student_id,class_id,section_id,subject_id,session_id,term,score,max_score,score_origin,source_onlineexam_id,source_attempt_id,source_sync_id,updated_at' AS `expected_columns`, 14 AS `expected_count`
  UNION ALL SELECT 'monnify_payments' AS `table_name`, 'id,payment_reference,transaction_reference,payment_context,context_id,amount,currency,customer_email,customer_name,context_data,gateway_mode,gateway_response,status,processing_started_at,paid_at,processed_at,created_at,updated_at' AS `expected_columns`, 18 AS `expected_count`
  UNION ALL SELECT 'promotion_criteria' AS `table_name`, 'id,session_id,name,minimum_average,is_active,created_by,updated_by,created_at,updated_at' AS `expected_columns`, 9 AS `expected_count`
  UNION ALL SELECT 'promotion_criteria_classes' AS `table_name`, 'id,criteria_id,session_id,class_id,promoted_to_class_id,promoted_to_label,created_at,updated_at' AS `expected_columns`, 8 AS `expected_count`
  UNION ALL SELECT 'promotion_criteria_subjects' AS `table_name`, 'id,criteria_id,subject_id,minimum_average,created_at,updated_at' AS `expected_columns`, 6 AS `expected_count`
  UNION ALL SELECT 'promotion_note_overrides' AS `table_name`, 'id,student_id,session_id,class_id,section_id,action,decision,target_class_id,target_label,reason,automatic_decision,automatic_note,created_by,created_at' AS `expected_columns`, 14 AS `expected_count`
  UNION ALL SELECT 'support_email_notifications' AS `table_name`, 'id,staff_id,email,is_active,last_notified_at,last_error,created_at,updated_at' AS `expected_columns`, 8 AS `expected_count`
  UNION ALL SELECT 'support_email_alert_deliveries' AS `table_name`, 'id,notification_id,incoming_email_id,support_ticket_id,school_domain,inbound_address,recipient_email,delivery_status,attempt_count,available_at,last_attempt_at,sent_at,provider_message_id,error_message,created_at,updated_at' AS `expected_columns`, 16 AS `expected_count`
) AS required
WHERE (
  SELECT COUNT(DISTINCT actual.`COLUMN_NAME`)
  FROM `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  WHERE actual.`TABLE_SCHEMA` = DATABASE()
    AND actual.`TABLE_NAME` = required.`table_name`
    AND FIND_IN_SET(actual.`COLUMN_NAME`, required.`expected_columns`) > 0
) < required.`expected_count`
ORDER BY required.`table_name`;

-- Index names may differ on a manually maintained tenant. Verify equivalent
-- ordered columns and uniqueness instead of requiring a particular name.
SELECT
  required.`table_name`,
  required.`requirement` AS `missing_index`
FROM (
  SELECT 'incoming_emails' AS `table_name`, 'PRIMARY(id)' AS `requirement`, 'id' AS `columns`, 0 AS `non_unique`
  UNION ALL SELECT 'incoming_emails', 'INDEX(sns_message_id)', 'sns_message_id', 1
  UNION ALL SELECT 'incoming_emails', 'INDEX(ses_message_id)', 'ses_message_id', 1
  UNION ALL SELECT 'support_tickets', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'support_tickets', 'UNIQUE(ticket_number)', 'ticket_number', 0
  UNION ALL SELECT 'support_tickets', 'INDEX(status)', 'status', 1
  UNION ALL SELECT 'support_tickets', 'INDEX(requester_email)', 'requester_email', 1
  UNION ALL SELECT 'support_tickets', 'INDEX(last_message_at)', 'last_message_at', 1
  UNION ALL SELECT 'support_messages', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'support_messages', 'INDEX(support_ticket_id)', 'support_ticket_id', 1
  UNION ALL SELECT 'support_messages', 'INDEX(incoming_email_id)', 'incoming_email_id', 1
  UNION ALL SELECT 'support_messages', 'INDEX(message_id)', 'message_id', 1
  UNION ALL SELECT 'onlineexam_class_sections', 'UNIQUE(onlineexam_id,section_id)', 'onlineexam_id,section_id', 0
  UNION ALL SELECT 'onlineexam_question_definitions', 'UNIQUE(question_id)', 'question_id', 0
  UNION ALL SELECT 'onlineexam_revision_snapshots', 'UNIQUE(onlineexam_id,revision)', 'onlineexam_id,revision', 0
  UNION ALL SELECT 'onlineexam_question_snapshots', 'UNIQUE(onlineexam_id,revision,paper_id,source_question_id)', 'onlineexam_id,revision,paper_id,source_question_id', 0
  UNION ALL SELECT 'onlineexam_candidate_attempts', 'UNIQUE(onlineexam_student_id,attempt_no)', 'onlineexam_student_id,attempt_no', 0
  UNION ALL SELECT 'onlineexam_candidate_attempts', 'UNIQUE(submission_key)', 'submission_key', 0
  UNION ALL SELECT 'onlineexam_attempt_papers', 'UNIQUE(attempt_id,paper_id)', 'attempt_id,paper_id', 0
  UNION ALL SELECT 'onlineexam_attempt_answers', 'UNIQUE(attempt_id,question_snapshot_id)', 'attempt_id,question_snapshot_id', 0
  UNION ALL SELECT 'onlineexam_accommodations', 'UNIQUE(onlineexam_id,onlineexam_student_id)', 'onlineexam_id,onlineexam_student_id', 0
  UNION ALL SELECT 'onlineexam_marking', 'UNIQUE(attempt_answer_id,marking_version)', 'attempt_answer_id,marking_version', 0
  UNION ALL SELECT 'onlineexam_paper_marking', 'UNIQUE(attempt_paper_id,marking_version)', 'attempt_paper_id,marking_version', 0
  UNION ALL SELECT 'onlineexam_result_profiles', 'UNIQUE(onlineexam_id,adapter)', 'onlineexam_id,adapter', 0
  UNION ALL SELECT 'onlineexam_kindergarten_mappings', 'UNIQUE(onlineexam_id,concept_id)', 'onlineexam_id,concept_id', 0
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'UNIQUE(onlineexam_id,section_id)', 'onlineexam_id,section_id', 0
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'INDEX(setting_id,setting_subject_id)', 'setting_id,setting_subject_id', 1
  UNION ALL SELECT 'onlineexam_result_target_locks', 'PRIMARY(target_hash)', 'target_hash', 0
  UNION ALL SELECT 'onlineexam_result_sync', 'UNIQUE(idempotency_key)', 'idempotency_key', 0
  UNION ALL SELECT 'holiday_assessment_scores', 'INDEX(source_onlineexam_id,source_attempt_id)', 'source_onlineexam_id,source_attempt_id', 1
  UNION ALL SELECT 'holiday_assessment_scores', 'UNIQUE(source_sync_id)', 'source_sync_id', 0
  UNION ALL SELECT 'monnify_payments', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'monnify_payments', 'UNIQUE(payment_reference)', 'payment_reference', 0
  UNION ALL SELECT 'monnify_payments', 'UNIQUE(transaction_reference)', 'transaction_reference', 0
  UNION ALL SELECT 'monnify_payments', 'INDEX(status)', 'status', 1
  UNION ALL SELECT 'monnify_payments', 'INDEX(payment_context,context_id)', 'payment_context,context_id', 1
  UNION ALL SELECT 'promotion_criteria', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'promotion_criteria', 'INDEX(session_id,is_active)', 'session_id,is_active', 1
  UNION ALL SELECT 'promotion_criteria_classes', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'promotion_criteria_classes', 'UNIQUE(session_id,class_id)', 'session_id,class_id', 0
  UNION ALL SELECT 'promotion_criteria_classes', 'INDEX(criteria_id)', 'criteria_id', 1
  UNION ALL SELECT 'promotion_criteria_classes', 'INDEX(promoted_to_class_id)', 'promoted_to_class_id', 1
  UNION ALL SELECT 'promotion_criteria_subjects', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'promotion_criteria_subjects', 'UNIQUE(criteria_id,subject_id)', 'criteria_id,subject_id', 0
  UNION ALL SELECT 'promotion_criteria_subjects', 'INDEX(subject_id)', 'subject_id', 1
  UNION ALL SELECT 'promotion_note_overrides', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'promotion_note_overrides', 'INDEX(student_id,session_id,class_id,section_id,id)', 'student_id,session_id,class_id,section_id,id', 1
  UNION ALL SELECT 'promotion_note_overrides', 'INDEX(session_id,class_id,section_id,id)', 'session_id,class_id,section_id,id', 1
  UNION ALL SELECT 'promotion_note_overrides', 'INDEX(created_by,created_at)', 'created_by,created_at', 1
  UNION ALL SELECT 'support_email_notifications', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'support_email_notifications', 'UNIQUE(staff_id)', 'staff_id', 0
  UNION ALL SELECT 'support_email_notifications', 'INDEX(email)', 'email', 1
  UNION ALL SELECT 'support_email_alert_deliveries', 'PRIMARY(id)', 'id', 0
  UNION ALL SELECT 'support_email_alert_deliveries', 'UNIQUE(notification_id,incoming_email_id)', 'notification_id,incoming_email_id', 0
  UNION ALL SELECT 'support_email_alert_deliveries', 'INDEX(delivery_status,available_at)', 'delivery_status,available_at', 1
  UNION ALL SELECT 'support_email_alert_deliveries', 'INDEX(support_ticket_id)', 'support_ticket_id', 1
  UNION ALL SELECT 'support_email_alert_deliveries', 'INDEX(incoming_email_id)', 'incoming_email_id', 1
) AS required
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT
      `TABLE_NAME`,
      `INDEX_NAME`,
      MIN(`NON_UNIQUE`) AS `non_unique`,
      GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
    GROUP BY `TABLE_NAME`, `INDEX_NAME`
  ) AS actual
  WHERE actual.`TABLE_NAME` = required.`table_name`
    AND actual.`columns` = required.`columns`
    AND actual.`non_unique` = required.`non_unique`
)
ORDER BY required.`table_name`, required.`requirement`;

SELECT 'onlineexam.result_adapter default must be standard_component' AS `incorrect_migration_column_default`
WHERE COALESCE((
  SELECT `COLUMN_DEFAULT`
  FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'onlineexam'
    AND `COLUMN_NAME` = 'result_adapter'
), '') <> 'standard_component';

SELECT `TABLE_NAME`, `COLUMN_NAME` AS `retired_question_column_still_present`
FROM `INFORMATION_SCHEMA`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'questions'
  AND `COLUMN_NAME` = 'level';

SELECT 'IMPORT FINISHED: empty verification result sets mean OK; any returned row requires review.' AS `status`;
