-- SchoolLift demo: reset all Online Examination demonstration data.
-- Date: 2026-09-08
-- Validated against: database/migration-audit-dumps/schoollift_demo.sql
--
-- PURPOSE
--   Remove every legacy and Nigerian-workflow Online Examination assessment,
--   paper, question-bank item, candidate assignment, attempt, answer, marking,
--   incident, snapshot, audit and synchronization row from schoollift_demo.
--
-- IMPORTANT
--   1. Take and verify a full database backup before running this file.
--   2. In phpMyAdmin, select the `schoollift_demo` database first.
--   3. Import/run this entire file in one request. It relies on one connection
--      for user variables, temporary tables and the transaction.
--   4. Use a maintenance window: nobody should create, take, mark or alter an
--      Online Examination while this script is running.
--   5. Read the final `reset_status`. Only `COMPLETED` means rows were removed.
--
-- RESULT SAFETY
--   Online Examination may have posted values into the existing `score`,
--   `britishresult`, `kindergarten_result` or `holiday_assessment_scores`
--   tables. Before deleting its synchronization ledger, this script restores
--   the value which the first successful Online Examination post replaced.
--
--   If a currently posted value no longer matches the last value recorded in
--   the ledger, the whole reset is blocked and no data is deleted. This avoids
--   overwriting a later manual correction.
--
--   An empty `score` row referenced by the ledger is removed only when all CA
--   and Exam values are zero after restoration. Other academic results,
--   report-card publication, grading, comments, affective/psychomotor data,
--   Holiday settings and Kindergarten configuration are preserved.
--
-- NOTES
--   * Question Bank (`questions`, `question_answers`, `question_options` and
--     `onlineexam_question_definitions`) is intentionally cleared as part of
--     the requested fresh demonstration.
--   * Online Examination notification templates and shared application logs
--     are configuration/shared records and are deliberately preserved.
--   * Database rows that contain uploaded-answer paths are removed, but SQL
--     cannot delete the corresponding physical files from the web server.
--   * DELETE is used instead of TRUNCATE, foreign-key checks remain enabled,
--     and AUTO_INCREMENT counters are not reset.
--   * The file is rerunnable. A second successful run removes zero rows.

SET @onlineexam_reset_expected_database := 'schoollift_demo';

-- -------------------------------------------------------------------------
-- Preflight: the database name, required tables, columns and transaction-safe
-- storage engines must match the audited SchoolLift demo schema.
-- -------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_required_tables`;
DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_required_columns`;

CREATE TEMPORARY TABLE `tmp_onlineexam_reset_required_tables` (
  `table_name` varchar(64) NOT NULL,
  PRIMARY KEY (`table_name`)
) ENGINE=InnoDB;

INSERT INTO `tmp_onlineexam_reset_required_tables` (`table_name`) VALUES
  ('britishresult'),
  ('holiday_assessment_scores'),
  ('kindergarten_result'),
  ('onlineexam'),
  ('onlineexam_accommodations'),
  ('onlineexam_attempts'),
  ('onlineexam_attempt_answers'),
  ('onlineexam_attempt_papers'),
  ('onlineexam_audit_log'),
  ('onlineexam_candidate_attempts'),
  ('onlineexam_candidate_paper_history'),
  ('onlineexam_candidate_paper_windows'),
  ('onlineexam_class_sections'),
  ('onlineexam_holiday_mappings'),
  ('onlineexam_incidents'),
  ('onlineexam_kindergarten_mappings'),
  ('onlineexam_marking'),
  ('onlineexam_papers'),
  ('onlineexam_paper_marking'),
  ('onlineexam_paper_sections'),
  ('onlineexam_questions'),
  ('onlineexam_question_definitions'),
  ('onlineexam_question_snapshots'),
  ('onlineexam_result_profiles'),
  ('onlineexam_result_sync'),
  ('onlineexam_result_target_locks'),
  ('onlineexam_revision_snapshots'),
  ('onlineexam_students'),
  ('onlineexam_student_results'),
  ('question_answers'),
  ('question_options'),
  ('questions'),
  ('score');

CREATE TEMPORARY TABLE `tmp_onlineexam_reset_required_columns` (
  `table_name` varchar(64) NOT NULL,
  `column_name` varchar(64) NOT NULL,
  PRIMARY KEY (`table_name`, `column_name`)
) ENGINE=InnoDB;

INSERT INTO `tmp_onlineexam_reset_required_columns`
  (`table_name`, `column_name`) VALUES
  ('onlineexam_result_sync', 'id'),
  ('onlineexam_result_sync', 'onlineexam_id'),
  ('onlineexam_result_sync', 'attempt_id'),
  ('onlineexam_result_sync', 'target_table'),
  ('onlineexam_result_sync', 'target_record_id'),
  ('onlineexam_result_sync', 'target_field'),
  ('onlineexam_result_sync', 'previous_value'),
  ('onlineexam_result_sync', 'previous_metadata_json'),
  ('onlineexam_result_sync', 'applied_value'),
  ('onlineexam_result_sync', 'status'),
  ('score', 'ID'),
  ('score', 'exam'),
  ('score', 'ca1'),
  ('score', 'ca2'),
  ('score', 'ca3'),
  ('score', 'ca4'),
  ('score', 'ca5'),
  ('score', 'ca6'),
  ('score', 'ca7'),
  ('score', 'ca8'),
  ('score', 'ca9'),
  ('score', 'ca10'),
  ('britishresult', 'ID'),
  ('britishresult', 'Remark'),
  ('britishresult', 'AdditionalComments'),
  ('kindergarten_result', 'id'),
  ('kindergarten_result', 'result_label_index'),
  ('kindergarten_result', 'updated_at'),
  ('holiday_assessment_scores', 'id'),
  ('holiday_assessment_scores', 'score'),
  ('holiday_assessment_scores', 'max_score'),
  ('holiday_assessment_scores', 'score_origin'),
  ('holiday_assessment_scores', 'source_onlineexam_id'),
  ('holiday_assessment_scores', 'source_attempt_id'),
  ('holiday_assessment_scores', 'source_sync_id'),
  ('holiday_assessment_scores', 'updated_at');

SELECT required.`table_name` AS `missing_required_table`
FROM `tmp_onlineexam_reset_required_tables` AS required
LEFT JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
WHERE actual.`TABLE_NAME` IS NULL
ORDER BY required.`table_name`;

SELECT required.`table_name`, required.`column_name` AS `missing_required_column`
FROM `tmp_onlineexam_reset_required_columns` AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

SELECT required.`table_name` AS `non_transactional_required_table`,
       actual.`ENGINE` AS `current_engine`
FROM `tmp_onlineexam_reset_required_tables` AS required
JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
WHERE actual.`ENGINE` <> 'InnoDB'
ORDER BY required.`table_name`;

SET @onlineexam_reset_database_ok := IF(
  CAST(DATABASE() AS BINARY) =
    CAST(@onlineexam_reset_expected_database AS BINARY),
  1,
  0
);

SET @onlineexam_reset_required_table_count :=
  (SELECT COUNT(*) FROM `tmp_onlineexam_reset_required_tables`);
SET @onlineexam_reset_found_table_count := (
  SELECT COUNT(*)
  FROM `tmp_onlineexam_reset_required_tables` AS required
  JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
);
SET @onlineexam_reset_innodb_table_count := (
  SELECT COUNT(*)
  FROM `tmp_onlineexam_reset_required_tables` AS required
  JOIN `INFORMATION_SCHEMA`.`TABLES` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
   AND actual.`ENGINE` = 'InnoDB'
);
SET @onlineexam_reset_required_column_count :=
  (SELECT COUNT(*) FROM `tmp_onlineexam_reset_required_columns`);
SET @onlineexam_reset_found_column_count := (
  SELECT COUNT(*)
  FROM `tmp_onlineexam_reset_required_columns` AS required
  JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
   AND actual.`COLUMN_NAME` = required.`column_name`
);
SET @onlineexam_reset_slots_exist := IF(
  EXISTS (
    SELECT 1
    FROM `INFORMATION_SCHEMA`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_academic_slots'
  ),
  1,
  0
);
SET @onlineexam_reset_slots_engine_ok := IF(
  @onlineexam_reset_slots_exist = 0
  OR EXISTS (
    SELECT 1
    FROM `INFORMATION_SCHEMA`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'onlineexam_academic_slots'
      AND `ENGINE` = 'InnoDB'
  ),
  1,
  0
);
SET @onlineexam_reset_schema_ok := IF(
  @onlineexam_reset_found_table_count = @onlineexam_reset_required_table_count
  AND @onlineexam_reset_innodb_table_count = @onlineexam_reset_required_table_count
  AND @onlineexam_reset_found_column_count = @onlineexam_reset_required_column_count
  AND @onlineexam_reset_slots_engine_ok = 1,
  1,
  0
);

SELECT
  DATABASE() AS `selected_database`,
  @onlineexam_reset_expected_database AS `required_database`,
  IF(@onlineexam_reset_database_ok = 1, 'OK', 'WRONG DATABASE') AS `database_check`,
  IF(@onlineexam_reset_schema_ok = 1, 'OK', 'SCHEMA NOT READY') AS `schema_check`,
  IF(@onlineexam_reset_slots_exist = 1, 'installed', 'not installed') AS `migration_138_slot_table`;

-- -------------------------------------------------------------------------
-- Capture every active successful result post. One destination can have
-- several corrections; restore the value before the first post and verify
-- the value written by the last post.
-- -------------------------------------------------------------------------

DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_posted_targets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_blockers`;

CREATE TEMPORARY TABLE `tmp_onlineexam_reset_posted_targets` (
  `target_table` varchar(64) NOT NULL,
  `target_record_id` int NOT NULL,
  `target_field` varchar(64) NOT NULL,
  `first_sync_id` bigint UNSIGNED NOT NULL,
  `last_sync_id` bigint UNSIGNED NOT NULL,
  `first_previous_value` varchar(255) DEFAULT NULL,
  `first_previous_metadata_json` longtext,
  `last_applied_value` varchar(255) DEFAULT NULL,
  `last_onlineexam_id` int NOT NULL,
  `last_attempt_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`target_table`, `target_record_id`, `target_field`),
  KEY `tmp_onlineexam_reset_last_sync_idx` (`last_sync_id`)
) ENGINE=InnoDB;

CREATE TEMPORARY TABLE `tmp_onlineexam_reset_blockers` (
  `sync_id` bigint UNSIGNED DEFAULT NULL,
  `target_table` varchar(64) DEFAULT NULL,
  `target_record_id` int DEFAULT NULL,
  `target_field` varchar(64) DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  KEY `tmp_onlineexam_reset_blocker_sync_idx` (`sync_id`)
) ENGINE=InnoDB;

START TRANSACTION;

SET @onlineexam_reset_exam_rows_before := (SELECT COUNT(*) FROM `onlineexam`);
SET @onlineexam_reset_question_rows_before := (SELECT COUNT(*) FROM `questions`);
SET @onlineexam_reset_sync_rows_before := (SELECT COUNT(*) FROM `onlineexam_result_sync`);
SET @onlineexam_reset_posted_rows_before := (
  SELECT COUNT(*) FROM `onlineexam_result_sync` WHERE `status` = 'posted'
);
SET @onlineexam_reset_score_rows_before := (SELECT COUNT(*) FROM `score`);
SET @onlineexam_reset_british_rows_before := (SELECT COUNT(*) FROM `britishresult`);
SET @onlineexam_reset_kindergarten_rows_before := (SELECT COUNT(*) FROM `kindergarten_result`);
SET @onlineexam_reset_holiday_rows_before := (SELECT COUNT(*) FROM `holiday_assessment_scores`);

INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  `id`, `target_table`, `target_record_id`, `target_field`,
  'A posted ledger row does not contain a complete result destination.'
FROM `onlineexam_result_sync`
WHERE `status` = 'posted'
  AND (
    `target_table` IS NULL OR TRIM(`target_table`) = ''
    OR `target_record_id` IS NULL OR `target_record_id` <= 0
    OR `target_field` IS NULL OR TRIM(`target_field`) = ''
  );

INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  `id`, `target_table`, `target_record_id`, `target_field`,
  'This posted destination is not one of the supported Online Examination result adapters.'
FROM `onlineexam_result_sync`
WHERE `status` = 'posted'
  AND `target_table` IS NOT NULL
  AND `target_record_id` IS NOT NULL
  AND `target_field` IS NOT NULL
  AND NOT (
    (`target_table` = 'score'
      AND `target_field` IN ('ca1','ca2','ca3','ca4','ca5','ca6','ca7','ca8','ca9','ca10','exam'))
    OR (`target_table` = 'britishresult' AND `target_field` = 'Remark')
    OR (`target_table` = 'kindergarten_result' AND `target_field` LIKE 'concept:%')
    OR (`target_table` = 'holiday_assessment_scores' AND `target_field` = 'score')
  );

INSERT INTO `tmp_onlineexam_reset_posted_targets` (
  `target_table`, `target_record_id`, `target_field`,
  `first_sync_id`, `last_sync_id`,
  `first_previous_value`, `first_previous_metadata_json`,
  `last_applied_value`, `last_onlineexam_id`, `last_attempt_id`
)
SELECT
  grouped.`target_table`,
  grouped.`target_record_id`,
  grouped.`target_field`,
  grouped.`first_sync_id`,
  grouped.`last_sync_id`,
  first_sync.`previous_value`,
  first_sync.`previous_metadata_json`,
  last_sync.`applied_value`,
  last_sync.`onlineexam_id`,
  last_sync.`attempt_id`
FROM (
  SELECT
    `target_table`, `target_record_id`, `target_field`,
    MIN(`id`) AS `first_sync_id`,
    MAX(`id`) AS `last_sync_id`
  FROM `onlineexam_result_sync`
  WHERE `status` = 'posted'
    AND `target_record_id` IS NOT NULL
    AND `target_record_id` > 0
    AND (
      (`target_table` = 'score'
        AND `target_field` IN ('ca1','ca2','ca3','ca4','ca5','ca6','ca7','ca8','ca9','ca10','exam'))
      OR (`target_table` = 'britishresult' AND `target_field` = 'Remark')
      OR (`target_table` = 'kindergarten_result' AND `target_field` LIKE 'concept:%')
      OR (`target_table` = 'holiday_assessment_scores' AND `target_field` = 'score')
    )
  GROUP BY `target_table`, `target_record_id`, `target_field`
) AS grouped
JOIN `onlineexam_result_sync` AS first_sync
  ON first_sync.`id` = grouped.`first_sync_id`
JOIN `onlineexam_result_sync` AS last_sync
  ON last_sync.`id` = grouped.`last_sync_id`;

-- Standard numeric/alphabetic component validation.
INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  targets.`last_sync_id`, targets.`target_table`, targets.`target_record_id`,
  targets.`target_field`,
  CASE
    WHEN scores.`ID` IS NULL THEN 'The score destination row no longer exists.'
    WHEN targets.`last_applied_value` IS NULL
      OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
      THEN 'The last applied standard score is invalid in the synchronization ledger.'
    WHEN targets.`first_previous_value` IS NOT NULL
      AND TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
      THEN 'The original standard score is invalid in the synchronization ledger.'
    ELSE 'The standard score was changed after Online Examination posted it.'
  END
FROM `tmp_onlineexam_reset_posted_targets` AS targets
LEFT JOIN `score` AS scores
  ON scores.`ID` = targets.`target_record_id`
WHERE targets.`target_table` = 'score'
  AND (
    scores.`ID` IS NULL
    OR targets.`last_applied_value` IS NULL
    OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
    OR (
      targets.`first_previous_value` IS NOT NULL
      AND TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
    )
    OR ABS(
      CASE targets.`target_field`
        WHEN 'ca1' THEN scores.`ca1`
        WHEN 'ca2' THEN scores.`ca2`
        WHEN 'ca3' THEN scores.`ca3`
        WHEN 'ca4' THEN scores.`ca4`
        WHEN 'ca5' THEN scores.`ca5`
        WHEN 'ca6' THEN scores.`ca6`
        WHEN 'ca7' THEN scores.`ca7`
        WHEN 'ca8' THEN scores.`ca8`
        WHEN 'ca9' THEN scores.`ca9`
        WHEN 'ca10' THEN scores.`ca10`
        WHEN 'exam' THEN scores.`exam`
      END - CAST(targets.`last_applied_value` AS DECIMAL(20,4))
    ) > 0.005
  );

-- British outcome validation. AdditionalComments is not compared because the
-- Online Examination adapter never owns or updates that field.
INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  targets.`last_sync_id`, targets.`target_table`, targets.`target_record_id`,
  targets.`target_field`,
  IF(results.`ID` IS NULL,
    'The British result destination row no longer exists.',
    'The British outcome was changed after Online Examination posted it.')
FROM `tmp_onlineexam_reset_posted_targets` AS targets
LEFT JOIN `britishresult` AS results
  ON results.`ID` = targets.`target_record_id`
WHERE targets.`target_table` = 'britishresult'
  AND (
    results.`ID` IS NULL
    OR NOT (
      CAST(results.`Remark` AS BINARY)
      <=> CAST(targets.`last_applied_value` AS BINARY)
    )
  );

-- Kindergarten concept validation.
INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  targets.`last_sync_id`, targets.`target_table`, targets.`target_record_id`,
  targets.`target_field`,
  CASE
    WHEN results.`id` IS NULL THEN 'The Kindergarten result destination row no longer exists.'
    WHEN targets.`last_applied_value` IS NULL
      OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
      THEN 'The last applied Kindergarten label is invalid in the synchronization ledger.'
    WHEN targets.`first_previous_value` IS NOT NULL
      AND TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
      THEN 'The original Kindergarten label is invalid in the synchronization ledger.'
    ELSE 'The Kindergarten outcome was changed after Online Examination posted it.'
  END
FROM `tmp_onlineexam_reset_posted_targets` AS targets
LEFT JOIN `kindergarten_result` AS results
  ON results.`id` = targets.`target_record_id`
WHERE targets.`target_table` = 'kindergarten_result'
  AND (
    results.`id` IS NULL
    OR targets.`last_applied_value` IS NULL
    OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
    OR (
      targets.`first_previous_value` IS NOT NULL
      AND TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
    )
    OR ABS(
      results.`result_label_index`
      - CAST(targets.`last_applied_value` AS DECIMAL(20,4))
    ) > 0.005
  );

-- Holiday score validation includes its explicit ownership columns and the
-- metadata needed to reconstruct the pre-post row.
INSERT INTO `tmp_onlineexam_reset_blockers`
  (`sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`)
SELECT
  targets.`last_sync_id`, targets.`target_table`, targets.`target_record_id`,
  targets.`target_field`,
  CASE
    WHEN scores.`id` IS NULL THEN 'The Holiday score destination row no longer exists.'
    WHEN targets.`last_applied_value` IS NULL
      OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
      THEN 'The last applied Holiday score is invalid in the synchronization ledger.'
    WHEN scores.`score_origin` <> 'onlineexam'
      OR NOT (scores.`source_onlineexam_id` <=> targets.`last_onlineexam_id`)
      OR NOT (scores.`source_attempt_id` <=> targets.`last_attempt_id`)
      OR NOT (scores.`source_sync_id` <=> targets.`last_sync_id`)
      THEN 'The Holiday score ownership information was changed after posting.'
    WHEN targets.`first_previous_metadata_json` IS NULL
      OR JSON_VALID(targets.`first_previous_metadata_json`) = 0
      OR JSON_CONTAINS_PATH(
        targets.`first_previous_metadata_json`, 'one', '$.record_existed'
      ) = 0
      THEN 'The Holiday score does not have complete reversal metadata.'
    WHEN JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.record_existed'
    )) = 'true'
      AND (
        targets.`first_previous_value` IS NULL
        OR TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
        OR JSON_CONTAINS_PATH(
          targets.`first_previous_metadata_json`, 'all',
          '$.max_score', '$.score_origin', '$.source_onlineexam_id',
          '$.source_attempt_id', '$.source_sync_id', '$.updated_at'
        ) = 0
        OR JSON_UNQUOTE(JSON_EXTRACT(
          targets.`first_previous_metadata_json`, '$.max_score'
        )) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
        OR NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
          targets.`first_previous_metadata_json`, '$.score_origin'
        )), 'null') IS NULL
      )
      THEN 'The original Holiday score metadata is incomplete or invalid.'
    WHEN JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.record_existed'
    )) NOT IN ('true', 'false')
      THEN 'The Holiday score reversal metadata has an invalid record_existed value.'
    ELSE 'The Holiday score was changed after Online Examination posted it.'
  END
FROM `tmp_onlineexam_reset_posted_targets` AS targets
LEFT JOIN `holiday_assessment_scores` AS scores
  ON scores.`id` = targets.`target_record_id`
WHERE targets.`target_table` = 'holiday_assessment_scores'
  AND (
    scores.`id` IS NULL
    OR targets.`last_applied_value` IS NULL
    OR TRIM(targets.`last_applied_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
    OR ABS(
      scores.`score` - CAST(targets.`last_applied_value` AS DECIMAL(20,4))
    ) > 0.005
    OR scores.`score_origin` <> 'onlineexam'
    OR NOT (scores.`source_onlineexam_id` <=> targets.`last_onlineexam_id`)
    OR NOT (scores.`source_attempt_id` <=> targets.`last_attempt_id`)
    OR NOT (scores.`source_sync_id` <=> targets.`last_sync_id`)
    OR targets.`first_previous_metadata_json` IS NULL
    OR JSON_VALID(targets.`first_previous_metadata_json`) = 0
    OR JSON_CONTAINS_PATH(
      targets.`first_previous_metadata_json`, 'one', '$.record_existed'
    ) = 0
    OR JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.record_existed'
    )) NOT IN ('true', 'false')
    OR (
      JSON_UNQUOTE(JSON_EXTRACT(
        targets.`first_previous_metadata_json`, '$.record_existed'
      )) = 'true'
      AND (
        targets.`first_previous_value` IS NULL
        OR TRIM(targets.`first_previous_value`) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
        OR JSON_CONTAINS_PATH(
          targets.`first_previous_metadata_json`, 'all',
          '$.max_score', '$.score_origin', '$.source_onlineexam_id',
          '$.source_attempt_id', '$.source_sync_id', '$.updated_at'
        ) = 0
        OR JSON_UNQUOTE(JSON_EXTRACT(
          targets.`first_previous_metadata_json`, '$.max_score'
        )) NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
        OR NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
          targets.`first_previous_metadata_json`, '$.score_origin'
        )), 'null') IS NULL
      )
    )
  );

SET @onlineexam_reset_blocker_count :=
  (SELECT COUNT(*) FROM `tmp_onlineexam_reset_blockers`);
SET @onlineexam_reset_allowed := IF(
  @onlineexam_reset_database_ok = 1
  AND @onlineexam_reset_schema_ok = 1
  AND @onlineexam_reset_blocker_count = 0,
  1,
  0
);

SELECT
  @onlineexam_reset_exam_rows_before AS `assessments_found`,
  @onlineexam_reset_question_rows_before AS `question_bank_items_found`,
  @onlineexam_reset_sync_rows_before AS `synchronization_rows_found`,
  @onlineexam_reset_posted_rows_before AS `successful_posts_to_reverse`,
  @onlineexam_reset_blocker_count AS `safety_blockers`,
  IF(@onlineexam_reset_allowed = 1, 'READY', 'BLOCKED') AS `reset_preflight`;

SELECT
  `sync_id`, `target_table`, `target_record_id`, `target_field`, `reason`
FROM `tmp_onlineexam_reset_blockers`
ORDER BY `sync_id`, `target_table`, `target_record_id`, `target_field`;

-- -------------------------------------------------------------------------
-- Restore official results. Every statement is gated by the preflight flag.
-- -------------------------------------------------------------------------

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca1'
SET scores.`ca1` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca2'
SET scores.`ca2` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca3'
SET scores.`ca3` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca4'
SET scores.`ca4` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca5'
SET scores.`ca5` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca6'
SET scores.`ca6` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca7'
SET scores.`ca7` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca8'
SET scores.`ca8` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca9'
SET scores.`ca9` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'ca10'
SET scores.`ca10` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

UPDATE `score` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'score'
 AND targets.`target_record_id` = scores.`ID`
 AND targets.`target_field` = 'exam'
SET scores.`exam` = CAST(COALESCE(targets.`first_previous_value`, '0') AS DECIMAL(20,4))
WHERE @onlineexam_reset_allowed = 1;

-- Remove only ledger-referenced score rows that contain no result after the
-- original values have been restored. This prevents an empty Online Exam-
-- created row from causing a false manual-score conflict in the next demo.
DELETE scores
FROM `score` AS scores
JOIN (
  SELECT DISTINCT `target_record_id`
  FROM `tmp_onlineexam_reset_posted_targets`
  WHERE `target_table` = 'score'
) AS targets
  ON targets.`target_record_id` = scores.`ID`
WHERE @onlineexam_reset_allowed = 1
  AND ABS(scores.`exam`) <= 0.000001
  AND ABS(scores.`ca1`) <= 0.000001
  AND ABS(scores.`ca2`) <= 0.000001
  AND ABS(scores.`ca3`) <= 0.000001
  AND ABS(scores.`ca4`) <= 0.000001
  AND ABS(scores.`ca5`) <= 0.000001
  AND ABS(scores.`ca6`) <= 0.000001
  AND ABS(scores.`ca7`) <= 0.000001
  AND ABS(scores.`ca8`) <= 0.000001
  AND ABS(scores.`ca9`) <= 0.000001
  AND ABS(scores.`ca10`) <= 0.000001;

UPDATE `britishresult` AS results
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'britishresult'
 AND targets.`target_record_id` = results.`ID`
 AND targets.`target_field` = 'Remark'
SET results.`Remark` = targets.`first_previous_value`
WHERE @onlineexam_reset_allowed = 1;

DELETE results
FROM `britishresult` AS results
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'britishresult'
 AND targets.`target_record_id` = results.`ID`
 AND targets.`target_field` = 'Remark'
WHERE @onlineexam_reset_allowed = 1
  AND targets.`first_previous_value` IS NULL
  AND (results.`AdditionalComments` IS NULL OR TRIM(results.`AdditionalComments`) = '');

UPDATE `kindergarten_result` AS results
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'kindergarten_result'
 AND targets.`target_record_id` = results.`id`
SET results.`result_label_index` = CAST(targets.`first_previous_value` AS UNSIGNED),
    results.`updated_at` = CURRENT_TIMESTAMP
WHERE @onlineexam_reset_allowed = 1
  AND targets.`first_previous_value` IS NOT NULL;

DELETE results
FROM `kindergarten_result` AS results
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'kindergarten_result'
 AND targets.`target_record_id` = results.`id`
WHERE @onlineexam_reset_allowed = 1
  AND targets.`first_previous_value` IS NULL;

UPDATE `holiday_assessment_scores` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'holiday_assessment_scores'
 AND targets.`target_record_id` = scores.`id`
SET scores.`score` = CAST(targets.`first_previous_value` AS DECIMAL(20,4)),
    scores.`max_score` = CAST(JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.max_score'
    )) AS UNSIGNED),
    scores.`score_origin` = JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.score_origin'
    )),
    scores.`source_onlineexam_id` = CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.source_onlineexam_id'
    )), 'null') AS UNSIGNED),
    scores.`source_attempt_id` = CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.source_attempt_id'
    )), 'null') AS UNSIGNED),
    scores.`source_sync_id` = CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.source_sync_id'
    )), 'null') AS UNSIGNED),
    scores.`updated_at` = NULLIF(JSON_UNQUOTE(JSON_EXTRACT(
      targets.`first_previous_metadata_json`, '$.updated_at'
    )), 'null')
WHERE @onlineexam_reset_allowed = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(
    targets.`first_previous_metadata_json`, '$.record_existed'
  )) = 'true';

DELETE scores
FROM `holiday_assessment_scores` AS scores
JOIN `tmp_onlineexam_reset_posted_targets` AS targets
  ON targets.`target_table` = 'holiday_assessment_scores'
 AND targets.`target_record_id` = scores.`id`
WHERE @onlineexam_reset_allowed = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(
    targets.`first_previous_metadata_json`, '$.record_existed'
  )) = 'false';

-- -------------------------------------------------------------------------
-- Delete Online Examination rows in child-to-parent order. Foreign-key checks
-- stay enabled so an unaccounted dependency stops the import safely.
-- -------------------------------------------------------------------------

DELETE FROM `onlineexam_marking`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_paper_marking`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_student_results`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_attempt_answers`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_attempt_papers`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_candidate_paper_history`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_incidents`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_result_sync`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_candidate_attempts`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_attempts`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_accommodations`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_candidate_paper_windows`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_kindergarten_mappings`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_holiday_mappings`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_question_snapshots`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_questions`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_paper_sections`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_papers`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_result_profiles`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_revision_snapshots`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_audit_log`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_class_sections`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_students`
WHERE @onlineexam_reset_allowed = 1;

-- Migration 138 is additive and might not yet be installed on the supplied
-- database. Delete its slot rows only when the table exists.
SET @onlineexam_reset_slots_delete_sql := IF(
  @onlineexam_reset_slots_exist = 1,
  'DELETE FROM `onlineexam_academic_slots` WHERE @onlineexam_reset_allowed = 1',
  'SET @onlineexam_reset_slots_delete_skipped = 1'
);
PREPARE onlineexam_reset_slots_delete_stmt
  FROM @onlineexam_reset_slots_delete_sql;
EXECUTE onlineexam_reset_slots_delete_stmt;
DEALLOCATE PREPARE onlineexam_reset_slots_delete_stmt;

DELETE FROM `onlineexam_result_target_locks`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam`
WHERE @onlineexam_reset_allowed = 1;

-- The Question Bank belongs to Online Examination and is intentionally reset.
DELETE FROM `question_answers`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `question_options`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `onlineexam_question_definitions`
WHERE @onlineexam_reset_allowed = 1;
DELETE FROM `questions`
WHERE @onlineexam_reset_allowed = 1;

SET @onlineexam_reset_remaining_module_rows :=
    (SELECT COUNT(*) FROM `onlineexam`)
  + (SELECT COUNT(*) FROM `onlineexam_accommodations`)
  + (SELECT COUNT(*) FROM `onlineexam_attempts`)
  + (SELECT COUNT(*) FROM `onlineexam_attempt_answers`)
  + (SELECT COUNT(*) FROM `onlineexam_attempt_papers`)
  + (SELECT COUNT(*) FROM `onlineexam_audit_log`)
  + (SELECT COUNT(*) FROM `onlineexam_candidate_attempts`)
  + (SELECT COUNT(*) FROM `onlineexam_candidate_paper_history`)
  + (SELECT COUNT(*) FROM `onlineexam_candidate_paper_windows`)
  + (SELECT COUNT(*) FROM `onlineexam_class_sections`)
  + (SELECT COUNT(*) FROM `onlineexam_holiday_mappings`)
  + (SELECT COUNT(*) FROM `onlineexam_incidents`)
  + (SELECT COUNT(*) FROM `onlineexam_kindergarten_mappings`)
  + (SELECT COUNT(*) FROM `onlineexam_marking`)
  + (SELECT COUNT(*) FROM `onlineexam_papers`)
  + (SELECT COUNT(*) FROM `onlineexam_paper_marking`)
  + (SELECT COUNT(*) FROM `onlineexam_paper_sections`)
  + (SELECT COUNT(*) FROM `onlineexam_questions`)
  + (SELECT COUNT(*) FROM `onlineexam_question_definitions`)
  + (SELECT COUNT(*) FROM `onlineexam_question_snapshots`)
  + (SELECT COUNT(*) FROM `onlineexam_result_profiles`)
  + (SELECT COUNT(*) FROM `onlineexam_result_sync`)
  + (SELECT COUNT(*) FROM `onlineexam_result_target_locks`)
  + (SELECT COUNT(*) FROM `onlineexam_revision_snapshots`)
  + (SELECT COUNT(*) FROM `onlineexam_students`)
  + (SELECT COUNT(*) FROM `onlineexam_student_results`)
  + (SELECT COUNT(*) FROM `question_answers`)
  + (SELECT COUNT(*) FROM `question_options`)
  + (SELECT COUNT(*) FROM `questions`);

SET @onlineexam_reset_slots_remaining := 0;
SET @onlineexam_reset_slots_count_sql := IF(
  @onlineexam_reset_slots_exist = 1,
  'SELECT COUNT(*) INTO @onlineexam_reset_slots_remaining FROM `onlineexam_academic_slots`',
  'SET @onlineexam_reset_slots_remaining = 0'
);
PREPARE onlineexam_reset_slots_count_stmt
  FROM @onlineexam_reset_slots_count_sql;
EXECUTE onlineexam_reset_slots_count_stmt;
DEALLOCATE PREPARE onlineexam_reset_slots_count_stmt;

SET @onlineexam_reset_remaining_rows :=
  @onlineexam_reset_remaining_module_rows
  + @onlineexam_reset_slots_remaining;
SET @onlineexam_reset_commit_ok := IF(
  @onlineexam_reset_allowed = 1
  AND @onlineexam_reset_remaining_rows = 0,
  1,
  0
);

-- Choose COMMIT or ROLLBACK without requiring a stored procedure, which keeps
-- this file straightforward to import in phpMyAdmin.
SET @onlineexam_reset_finish_sql := IF(
  @onlineexam_reset_commit_ok = 1,
  'COMMIT',
  'ROLLBACK'
);
PREPARE onlineexam_reset_finish_stmt FROM @onlineexam_reset_finish_sql;
EXECUTE onlineexam_reset_finish_stmt;
DEALLOCATE PREPARE onlineexam_reset_finish_stmt;

SET @onlineexam_reset_score_rows_after := (SELECT COUNT(*) FROM `score`);
SET @onlineexam_reset_british_rows_after := (SELECT COUNT(*) FROM `britishresult`);
SET @onlineexam_reset_kindergarten_rows_after := (SELECT COUNT(*) FROM `kindergarten_result`);
SET @onlineexam_reset_holiday_rows_after := (SELECT COUNT(*) FROM `holiday_assessment_scores`);

SELECT
  CASE
    WHEN @onlineexam_reset_database_ok = 0 THEN 'BLOCKED: WRONG DATABASE'
    WHEN @onlineexam_reset_schema_ok = 0 THEN 'BLOCKED: SCHEMA NOT READY'
    WHEN @onlineexam_reset_blocker_count > 0 THEN 'BLOCKED: RESULT SAFETY CONFLICT'
    WHEN @onlineexam_reset_commit_ok = 0 THEN 'ROLLED BACK: ROWS REMAINED'
    ELSE 'COMPLETED'
  END AS `reset_status`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_exam_rows_before, 0) AS `assessments_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_question_rows_before, 0) AS `question_bank_items_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_sync_rows_before, 0) AS `synchronization_rows_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_score_rows_before - @onlineexam_reset_score_rows_after, 0)
    AS `empty_score_rows_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_british_rows_before - @onlineexam_reset_british_rows_after, 0)
    AS `onlineexam_created_british_rows_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_kindergarten_rows_before - @onlineexam_reset_kindergarten_rows_after, 0)
    AS `onlineexam_created_kindergarten_rows_removed`,
  IF(@onlineexam_reset_commit_ok = 1,
    @onlineexam_reset_holiday_rows_before - @onlineexam_reset_holiday_rows_after, 0)
    AS `onlineexam_created_holiday_rows_removed`,
  @onlineexam_reset_blocker_count AS `safety_blockers`;

SELECT
  (SELECT COUNT(*) FROM `onlineexam`) AS `onlineexam_remaining`,
  (SELECT COUNT(*) FROM `onlineexam_papers`) AS `papers_remaining`,
  (SELECT COUNT(*) FROM `onlineexam_students`) AS `candidate_assignments_remaining`,
  (SELECT COUNT(*) FROM `onlineexam_candidate_attempts`) AS `attempts_remaining`,
  (SELECT COUNT(*) FROM `onlineexam_result_sync`) AS `result_sync_remaining`,
  (SELECT COUNT(*) FROM `questions`) AS `question_bank_remaining`,
  @onlineexam_reset_slots_remaining AS `academic_slots_remaining`;

DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_posted_targets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_blockers`;
DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_required_columns`;
DROP TEMPORARY TABLE IF EXISTS `tmp_onlineexam_reset_required_tables`;
