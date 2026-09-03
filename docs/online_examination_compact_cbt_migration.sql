-- SchoolLift Online Examination compact-CBT migration (application migration 135).
-- Run this file once per school database after taking and verifying a backup.
-- It is safe to run repeatedly on MySQL 5.7+/8.0 and compatible MariaDB.
-- Existing assessment, attempt, result, audit, and frozen-revision data is not deleted.

SELECT DATABASE() AS `selected_school_database`;

-- The current application schema (migration 128) must already be installed.
SELECT required.`table_name`, required.`column_name` AS `missing_prerequisite`
FROM (
  SELECT 'onlineexam' AS `table_name`, 'id' AS `column_name`
  UNION ALL SELECT 'onlineexam', 'result_adapter'
  UNION ALL SELECT 'onlineexam', 'lifecycle_status'
  UNION ALL SELECT 'onlineexam', 'is_active'
  UNION ALL SELECT 'onlineexam_result_sync', 'id'
  UNION ALL SELECT 'onlineexam_result_sync', 'previous_value'
  UNION ALL SELECT 'onlineexam_result_sync', 'applied_value'
  UNION ALL SELECT 'holiday_assessment_scores', 'id'
  UNION ALL SELECT 'holiday_assessment_scores', 'max_score'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

SET @onlineexam_135_missing := (
  SELECT COUNT(*)
  FROM (
    SELECT 'onlineexam' AS `table_name`, 'id' AS `column_name`
    UNION ALL SELECT 'onlineexam', 'result_adapter'
    UNION ALL SELECT 'onlineexam', 'lifecycle_status'
    UNION ALL SELECT 'onlineexam', 'is_active'
    UNION ALL SELECT 'onlineexam_result_sync', 'id'
    UNION ALL SELECT 'onlineexam_result_sync', 'previous_value'
    UNION ALL SELECT 'onlineexam_result_sync', 'applied_value'
    UNION ALL SELECT 'holiday_assessment_scores', 'id'
    UNION ALL SELECT 'holiday_assessment_scores', 'max_score'
  ) AS required
  LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
   AND actual.`COLUMN_NAME` = required.`column_name`
  WHERE actual.`COLUMN_NAME` IS NULL
);
SET @onlineexam_135_preflight_sql := IF(
  DATABASE() IS NOT NULL AND @onlineexam_135_missing = 0,
  'SET @onlineexam_135_preflight_ok = 1',
  'SELECT * FROM `SCHOOLLIFT_ONLINEEXAM_135_PREFLIGHT_FAILED_REVIEW_MISSING_PREREQUISITES`'
);
PREPARE onlineexam_135_stmt FROM @onlineexam_135_preflight_sql;
EXECUTE onlineexam_135_stmt;
DEALLOCATE PREPARE onlineexam_135_stmt;

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
      SELECT `INDEX_NAME`, MIN(`NON_UNIQUE`) AS `non_unique`,
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

-- Verification: every result set below must be empty.
SELECT 'onlineexam_holiday_mappings' AS `missing_onlineexam_135_table`
WHERE NOT EXISTS (
  SELECT 1 FROM `INFORMATION_SCHEMA`.`TABLES`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'onlineexam_holiday_mappings'
);

SELECT required.`table_name`, required.`column_name` AS `missing_onlineexam_135_column`
FROM (
  SELECT 'onlineexam_holiday_mappings' AS `table_name`, 'id' AS `column_name`
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'onlineexam_id'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'section_id'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'setting_id'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'setting_subject_id'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'max_score'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'created_at'
  UNION ALL SELECT 'onlineexam_holiday_mappings', 'updated_at'
  UNION ALL SELECT 'holiday_assessment_scores', 'score_origin'
  UNION ALL SELECT 'holiday_assessment_scores', 'source_onlineexam_id'
  UNION ALL SELECT 'holiday_assessment_scores', 'source_attempt_id'
  UNION ALL SELECT 'holiday_assessment_scores', 'source_sync_id'
  UNION ALL SELECT 'holiday_assessment_scores', 'updated_at'
  UNION ALL SELECT 'onlineexam_result_sync', 'previous_metadata_json'
  UNION ALL SELECT 'onlineexam_result_sync', 'applied_metadata_json'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

SELECT 'onlineexam.result_adapter default' AS `incorrect_onlineexam_135_default`
WHERE COALESCE((
  SELECT `COLUMN_DEFAULT`
  FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'onlineexam'
    AND `COLUMN_NAME` = 'result_adapter'
), '') <> 'standard_component';

SELECT 'holiday_assessment_scores(source_onlineexam_id,source_attempt_id)' AS `missing_onlineexam_135_index`
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT `INDEX_NAME`, GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = 'source_onlineexam_id,source_attempt_id'
);

SELECT 'holiday_assessment_scores UNIQUE(source_sync_id)' AS `missing_onlineexam_135_index`
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT `INDEX_NAME`, MIN(`NON_UNIQUE`) AS `non_unique`,
           GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'holiday_assessment_scores'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = 'source_sync_id'
    AND actual.`non_unique` = 0
);

SELECT 'onlineexam_holiday_mappings UNIQUE(onlineexam_id,section_id)' AS `missing_onlineexam_135_index`
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT MIN(`NON_UNIQUE`) AS `non_unique`,
           GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_holiday_mappings'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = 'onlineexam_id,section_id'
    AND actual.`non_unique` = 0
);

SELECT 'onlineexam_holiday_mappings INDEX(setting_id,setting_subject_id)' AS `missing_onlineexam_135_index`
WHERE NOT EXISTS (
  SELECT 1
  FROM (
    SELECT GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',') AS `columns`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_holiday_mappings'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = 'setting_id,setting_subject_id'
);

SELECT 'IMPORT FINISHED: empty missing/incorrect result sets mean migration 135 is ready.' AS `status`;
