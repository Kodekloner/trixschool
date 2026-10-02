-- SchoolLift migration 143: editable biometric guardian notifications.
-- Select the intended school database and take a verified backup first.
-- Rerunnable on MySQL 5.7+/8.0 and compatible MariaDB releases.
-- This file intentionally does not update the CodeIgniter migrations ledger.

SELECT DATABASE() AS `selected_school_database`;

SET @has_column := (
  SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'notification_setting'
    AND `COLUMN_NAME` = 'is_whatsapp'
);
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `notification_setting` ADD COLUMN `is_whatsapp` VARCHAR(10) NOT NULL DEFAULT ''0'' AFTER `is_sms`',
  'SELECT 1 AS notification_setting_is_whatsapp_present');
PREPARE migration_statement FROM @ddl;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_column := (
  SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'notification_setting'
    AND `COLUMN_NAME` = 'display_whatsapp'
);
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `notification_setting` ADD COLUMN `display_whatsapp` INT NOT NULL DEFAULT 0 AFTER `display_sms`',
  'SELECT 1 AS notification_setting_display_whatsapp_present');
PREPARE migration_statement FROM @ddl;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_column := (
  SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'biometric_notification_queue'
    AND `COLUMN_NAME` = 'notification_type'
);
SET @ddl := IF(@has_column = 0,
  'ALTER TABLE `biometric_notification_queue` ADD COLUMN `notification_type` VARCHAR(40) NOT NULL DEFAULT ''attendance_in'' AFTER `event_id`',
  'SELECT 1 AS biometric_notification_type_present');
PREPARE migration_statement FROM @ddl;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Classify notification rows created by migration 140 before templates existed.
UPDATE `biometric_notification_queue` AS queue_row
INNER JOIN `biometric_events` AS event_row ON event_row.`id` = queue_row.`event_id`
SET queue_row.`notification_type` = CASE
  WHEN event_row.`direction` = 'OUT' THEN 'biometric_attendance_out'
  ELSE 'biometric_attendance_in'
END
WHERE queue_row.`notification_type` IN ('', 'attendance_in', 'attendance_out');

SET @has_index := (
  SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'biometric_notification_queue'
    AND `INDEX_NAME` = 'uq_biometric_notification_event_channel'
);
SET @ddl := IF(@has_index > 0,
  'ALTER TABLE `biometric_notification_queue` DROP INDEX `uq_biometric_notification_event_channel`',
  'SELECT 1 AS old_biometric_notification_index_absent');
PREPARE migration_statement FROM @ddl;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @has_index := (
  SELECT COUNT(*) FROM `INFORMATION_SCHEMA`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'biometric_notification_queue'
    AND `INDEX_NAME` = 'uq_biometric_notification_event_type_channel'
);
SET @ddl := IF(@has_index = 0,
  'ALTER TABLE `biometric_notification_queue` ADD UNIQUE KEY `uq_biometric_notification_event_type_channel` (`event_id`, `notification_type`, `channel`)',
  'SELECT 1 AS biometric_notification_type_index_present');
PREPARE migration_statement FROM @ddl;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Attendance templates preserve the existing biometric master channel state.
INSERT INTO `notification_setting`
  (`type`, `is_mail`, `is_sms`, `is_whatsapp`, `is_notification`,
   `display_notification`, `display_sms`, `display_whatsapp`, `subject`,
   `template_id`, `template`, `variables`)
SELECT
  'biometric_attendance_in', CAST(defaults.`notify_email` AS CHAR),
  CAST(defaults.`notify_sms` AS CHAR), CAST(defaults.`notify_whatsapp` AS CHAR),
  0, 0, 1, 1, 'Student check-in - {{school_name}}', '',
  'Dear {{guardian_name}}, {{student_name}} ({{admission_no}}) checked in at {{attendance_time}} on {{attendance_date}} at {{school_name}}.',
  '{{guardian_name}} {{student_name}} {{admission_no}} {{attendance_direction}} {{attendance_action}} {{attendance_time}} {{attendance_date}} {{school_name}}'
FROM (
  SELECT COALESCE(MAX(`notify_email`), 0) AS `notify_email`,
         COALESCE(MAX(`notify_sms`), 0) AS `notify_sms`,
         COALESCE(MAX(`notify_whatsapp`), 0) AS `notify_whatsapp`
  FROM `biometric_settings`
) AS defaults
WHERE NOT EXISTS (
  SELECT 1 FROM `notification_setting` WHERE `type` = 'biometric_attendance_in'
);

INSERT INTO `notification_setting`
  (`type`, `is_mail`, `is_sms`, `is_whatsapp`, `is_notification`,
   `display_notification`, `display_sms`, `display_whatsapp`, `subject`,
   `template_id`, `template`, `variables`)
SELECT
  'biometric_attendance_out', CAST(defaults.`notify_email` AS CHAR),
  CAST(defaults.`notify_sms` AS CHAR), CAST(defaults.`notify_whatsapp` AS CHAR),
  0, 0, 1, 1, 'Student checkout - {{school_name}}', '',
  'Dear {{guardian_name}}, {{student_name}} ({{admission_no}}) checked out at {{attendance_time}} on {{attendance_date}} at {{school_name}}.',
  '{{guardian_name}} {{student_name}} {{admission_no}} {{attendance_direction}} {{attendance_action}} {{attendance_time}} {{attendance_date}} {{school_name}}'
FROM (
  SELECT COALESCE(MAX(`notify_email`), 0) AS `notify_email`,
         COALESCE(MAX(`notify_sms`), 0) AS `notify_sms`,
         COALESCE(MAX(`notify_whatsapp`), 0) AS `notify_whatsapp`
  FROM `biometric_settings`
) AS defaults
WHERE NOT EXISTS (
  SELECT 1 FROM `notification_setting` WHERE `type` = 'biometric_attendance_out'
);

-- The fee reminder starts disabled so each school explicitly opts in.
INSERT INTO `notification_setting`
  (`type`, `is_mail`, `is_sms`, `is_whatsapp`, `is_notification`,
   `display_notification`, `display_sms`, `display_whatsapp`, `subject`,
   `template_id`, `template`, `variables`)
SELECT
  'biometric_fees_due', '0', '0', '0', 0, 0, 1, 1,
  'Outstanding fees for {{student_name}} - {{school_name}}', '',
  'Dear {{guardian_name}}, our fees record shows an outstanding balance of {{currency_symbol}}{{outstanding_amount}} across {{fee_item_count}} fee item(s) for {{student_name}} ({{admission_no}}) in {{session_name}}. Please ignore this message if payment has just been made or contact {{school_name}} for clarification.',
  '{{guardian_name}} {{student_name}} {{admission_no}} {{outstanding_amount}} {{currency_symbol}} {{fee_item_count}} {{session_name}} {{school_name}}'
WHERE NOT EXISTS (
  SELECT 1 FROM `notification_setting` WHERE `type` = 'biometric_fees_due'
);

-- Verification: both result sets must be empty.
SELECT required.`table_name`, required.`column_name` AS `missing_column`
FROM (
  SELECT 'notification_setting' AS `table_name`, 'is_whatsapp' AS `column_name`
  UNION ALL SELECT 'notification_setting', 'display_whatsapp'
  UNION ALL SELECT 'biometric_notification_queue', 'notification_type'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = required.`table_name`
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL;

SELECT required.`type` AS `missing_notification_template`
FROM (
  SELECT 'biometric_attendance_in' AS `type`
  UNION ALL SELECT 'biometric_attendance_out'
  UNION ALL SELECT 'biometric_fees_due'
) AS required
LEFT JOIN `notification_setting` AS actual ON actual.`type` = required.`type`
WHERE actual.`id` IS NULL;

SELECT 'OK: migration 143 biometric notification templates are installed.' AS `migration_status`;
