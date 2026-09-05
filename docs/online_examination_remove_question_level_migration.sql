-- SchoolLift: remove retired Question Bank difficulty/Level (migration 136).
-- Select one school database in phpMyAdmin, then import or run this entire file.
-- Repeat separately for each school database. Safe to rerun on MySQL 5.7+/8.0
-- and compatible MariaDB releases.
--
-- Deployment: take and verify a backup; put question/exam authoring in
-- maintenance; deploy the matching application changes and run this SQL before
-- reopening authoring. The old code queries questions.level and the new code
-- no longer supplies its formerly required value when creating/importing rows.
--
-- This permanently removes ONLY questions.level and its retired difficulty
-- labels. Questions, options, answers, assignments, attempts, marks, results,
-- academic/class levels, and frozen examination history are preserved.
-- DDL commits implicitly. Removed labels require the backup to recover.
-- This file intentionally does not advance the CodeIgniter migrations ledger;
-- it does not prove that other numbered migrations have been installed.

SELECT DATABASE() AS `selected_school_database`;

-- Fail before DDL if a school database with the Question Bank is not selected.
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

-- This result set must be empty after a successful import.
SELECT `TABLE_NAME`, `COLUMN_NAME` AS `retired_question_column_still_present`
FROM `INFORMATION_SCHEMA`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'questions'
  AND `COLUMN_NAME` = 'level';

SELECT 'Migration 136 finished: an empty retired_question_column_still_present result set confirms removal.' AS `status`;
