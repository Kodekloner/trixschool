-- SchoolLift migration 144: SchoolLift-to-ZKBio directory synchronization.
-- Select the intended school database and take a verified backup first.
-- Rerunnable on MySQL 5.7+/8.0 and compatible MariaDB releases.
-- This file intentionally does not update the CodeIgniter migrations ledger.

SELECT DATABASE() AS `selected_school_database`;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'directory_sync_mode');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `directory_sync_mode` VARCHAR(12) NOT NULL DEFAULT ''off'' AFTER `last_error`',
  'SELECT 1 AS directory_sync_mode_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'directory_sync_interval_seconds');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `directory_sync_interval_seconds` SMALLINT UNSIGNED NOT NULL DEFAULT 300 AFTER `directory_sync_mode`',
  'SELECT 1 AS directory_sync_interval_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'directory_delete_approval_hash');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `directory_delete_approval_hash` CHAR(64) NULL AFTER `directory_sync_interval_seconds`',
  'SELECT 1 AS directory_delete_approval_hash_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'directory_delete_approved_at');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `directory_delete_approved_at` DATETIME NULL AFTER `directory_delete_approval_hash`',
  'SELECT 1 AS directory_delete_approved_at_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'last_directory_sync_at');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `last_directory_sync_at` DATETIME NULL AFTER `directory_delete_approved_at`',
  'SELECT 1 AS last_directory_sync_at_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'last_directory_sync_status');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `last_directory_sync_status` VARCHAR(24) NULL AFTER `last_directory_sync_at`',
  'SELECT 1 AS last_directory_sync_status_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

SET @has_column := (SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'biometric_integrations'
    AND `COLUMN_NAME` = 'last_directory_sync_error');
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_integrations` ADD COLUMN `last_directory_sync_error` VARCHAR(500) NULL AFTER `last_directory_sync_status`',
  'SELECT 1 AS last_directory_sync_error_present');
PREPARE migration_statement FROM @ddl; EXECUTE migration_statement; DEALLOCATE PREPARE migration_statement;

CREATE TABLE IF NOT EXISTS `biometric_directory_links` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `integration_id` INT UNSIGNED NOT NULL,
  `subject_type` VARCHAR(12) NOT NULL,
  `subject_key` INT NOT NULL,
  `subject_id` INT DEFAULT NULL,
  `external_person_code` VARCHAR(100) NOT NULL,
  `provider_person_id` VARCHAR(64) DEFAULT NULL,
  `desired_hash` CHAR(64) DEFAULT NULL,
  `applied_hash` CHAR(64) DEFAULT NULL,
  `sync_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `last_error` VARCHAR(500) DEFAULT NULL,
  `last_seen_snapshot` CHAR(64) DEFAULT NULL,
  `last_synced_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_biometric_directory_subject` (`integration_id`, `subject_type`, `subject_key`),
  UNIQUE KEY `uq_biometric_directory_code` (`integration_id`, `external_person_code`),
  KEY `idx_biometric_directory_status` (`integration_id`, `sync_status`),
  KEY `idx_biometric_directory_provider` (`integration_id`, `provider_person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `biometric_directory_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `integration_id` INT UNSIGNED NOT NULL,
  `gateway_id` VARCHAR(128) NOT NULL,
  `snapshot_id` CHAR(32) NOT NULL,
  `snapshot_hash` CHAR(64) NOT NULL,
  `mode` VARCHAR(12) NOT NULL,
  `status` VARCHAR(24) NOT NULL,
  `desired_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `adopted_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `deleted_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `unchanged_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `conflict_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `requires_delete_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `error_summary` VARCHAR(500) DEFAULT NULL,
  `started_at` DATETIME NOT NULL,
  `completed_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_biometric_directory_run` (`integration_id`, `gateway_id`, `snapshot_id`),
  KEY `idx_biometric_directory_run_status` (`integration_id`, `status`, `completed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verification: this result set must be empty.
SELECT required.`object_type`, required.`object_name` AS `missing_object`
FROM (
  SELECT 'column' AS `object_type`, 'directory_sync_mode' AS `object_name`
  UNION ALL SELECT 'column', 'directory_sync_interval_seconds'
  UNION ALL SELECT 'column', 'directory_delete_approval_hash'
  UNION ALL SELECT 'column', 'directory_delete_approved_at'
  UNION ALL SELECT 'column', 'last_directory_sync_at'
  UNION ALL SELECT 'column', 'last_directory_sync_status'
  UNION ALL SELECT 'column', 'last_directory_sync_error'
  UNION ALL SELECT 'table', 'biometric_directory_links'
  UNION ALL SELECT 'table', 'biometric_directory_runs'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS column_object
  ON required.`object_type` = 'column'
 AND column_object.`TABLE_SCHEMA` = DATABASE()
 AND column_object.`TABLE_NAME` = 'biometric_integrations'
 AND column_object.`COLUMN_NAME` = required.`object_name`
LEFT JOIN `INFORMATION_SCHEMA`.`TABLES` AS table_object
  ON required.`object_type` = 'table'
 AND table_object.`TABLE_SCHEMA` = DATABASE()
 AND table_object.`TABLE_NAME` = required.`object_name`
WHERE (required.`object_type` = 'column' AND column_object.`COLUMN_NAME` IS NULL)
   OR (required.`object_type` = 'table' AND table_object.`TABLE_NAME` IS NULL);

SELECT 'OK: migration 144 biometric directory synchronization is installed.' AS `migration_status`;
