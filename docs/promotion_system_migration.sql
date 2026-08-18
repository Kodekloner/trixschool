-- Advisory promotion system (application migration 133).
-- Run this once for EACH school database if CodeIgniter migrations are not
-- being used. Select and back up the intended tenant database first.
--
-- This script is rerunnable. It does not read, insert, update, or delete any
-- `student_session` row; actual class promotion remains a manual operation.
-- Supported targets: MySQL 5.7+/8.0 and compatible MariaDB releases.

SELECT DATABASE() AS `selected_school_database`;

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

-- Application code treats this as an append-only audit stream. A `clear`
-- action is a new row; earlier set/clear history is never updated in place.
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

-- Reuse the established Exam Setting permission group.
INSERT INTO `permission_group`
    (`name`, `short_code`, `is_active`, `system`, `created_at`)
SELECT 'Exam Setting', 'exam_setting', 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permission_group` WHERE `short_code` = 'exam_setting'
);

SET @promotion_exam_group_id := (
    SELECT `id`
    FROM `permission_group`
    WHERE `short_code` = 'exam_setting'
    ORDER BY `id`
    LIMIT 1
);

INSERT INTO `permission_category`
    (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`,
     `enable_edit`, `enable_delete`, `created_at`)
SELECT @promotion_exam_group_id, 'Manage Promotion Criteria',
       'manage_promotion_criteria', 1, 1, 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `permission_category`
    WHERE `short_code` = 'manage_promotion_criteria'
);

UPDATE `permission_category`
SET `perm_group_id` = @promotion_exam_group_id,
    `name` = 'Manage Promotion Criteria',
    `enable_view` = 1,
    `enable_add` = 1,
    `enable_edit` = 1,
    `enable_delete` = 0
WHERE `short_code` = 'manage_promotion_criteria';

INSERT INTO `permission_category`
    (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`,
     `enable_edit`, `enable_delete`, `created_at`)
SELECT @promotion_exam_group_id, 'Override Promotion Note',
       'override_promotion_note', 1, 1, 1, 0, NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `permission_category`
    WHERE `short_code` = 'override_promotion_note'
);

UPDATE `permission_category`
SET `perm_group_id` = @promotion_exam_group_id,
    `name` = 'Override Promotion Note',
    `enable_view` = 1,
    `enable_add` = 1,
    `enable_edit` = 1,
    `enable_delete` = 0
WHERE `short_code` = 'override_promotion_note';

SET @manage_promotion_permission_id := (
    SELECT `id`
    FROM `permission_category`
    WHERE `short_code` = 'manage_promotion_criteria'
    ORDER BY `id`
    LIMIT 1
);

SET @override_promotion_permission_id := (
    SELECT `id`
    FROM `permission_category`
    WHERE `short_code` = 'override_promotion_note'
    ORDER BY `id`
    LIMIT 1
);

-- Criteria management defaults to senior roles only.
INSERT INTO `roles_permissions`
    (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`,
     `can_delete`, `created_at`)
SELECT `roles`.`id`, @manage_promotion_permission_id, 1, 1, 1, 0, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Admin', 'Head Teacher', 'Super Admin')
  AND NOT EXISTS (
      SELECT 1
      FROM `roles_permissions`
      WHERE `roles_permissions`.`role_id` = `roles`.`id`
        AND `roles_permissions`.`perm_cat_id` = @manage_promotion_permission_id
  );

UPDATE `roles_permissions`
INNER JOIN `roles`
    ON `roles`.`id` = `roles_permissions`.`role_id`
SET `roles_permissions`.`can_view` = 1,
    `roles_permissions`.`can_add` = 1,
    `roles_permissions`.`can_edit` = 1,
    `roles_permissions`.`can_delete` = 0
WHERE `roles_permissions`.`perm_cat_id` = @manage_promotion_permission_id
  AND `roles`.`name` IN ('Admin', 'Head Teacher', 'Super Admin');

-- Teachers may override only within the class scope enforced by application
-- authorization. Senior roles receive the same operation permission globally.
INSERT INTO `roles_permissions`
    (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`,
     `can_delete`, `created_at`)
SELECT `roles`.`id`, @override_promotion_permission_id, 1, 1, 1, 0, NOW()
FROM `roles`
WHERE `roles`.`name` IN ('Teacher', 'Admin', 'Head Teacher', 'Super Admin')
  AND NOT EXISTS (
      SELECT 1
      FROM `roles_permissions`
      WHERE `roles_permissions`.`role_id` = `roles`.`id`
        AND `roles_permissions`.`perm_cat_id` = @override_promotion_permission_id
  );

UPDATE `roles_permissions`
INNER JOIN `roles`
    ON `roles`.`id` = `roles_permissions`.`role_id`
SET `roles_permissions`.`can_view` = 1,
    `roles_permissions`.`can_add` = 1,
    `roles_permissions`.`can_edit` = 1,
    `roles_permissions`.`can_delete` = 0
WHERE `roles_permissions`.`perm_cat_id` = @override_promotion_permission_id
  AND `roles`.`name` IN ('Teacher', 'Admin', 'Head Teacher', 'Super Admin');

-- Verification: every result set below must be empty.
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

SELECT required.`table_name`, required.`column_name` AS `missing_promotion_column`
FROM (
    SELECT 'promotion_criteria' AS `table_name`, 'id' AS `column_name`
    UNION ALL SELECT 'promotion_criteria', 'session_id'
    UNION ALL SELECT 'promotion_criteria', 'name'
    UNION ALL SELECT 'promotion_criteria', 'minimum_average'
    UNION ALL SELECT 'promotion_criteria', 'is_active'
    UNION ALL SELECT 'promotion_criteria', 'created_by'
    UNION ALL SELECT 'promotion_criteria', 'updated_by'
    UNION ALL SELECT 'promotion_criteria', 'created_at'
    UNION ALL SELECT 'promotion_criteria', 'updated_at'
    UNION ALL SELECT 'promotion_criteria_classes', 'id'
    UNION ALL SELECT 'promotion_criteria_classes', 'criteria_id'
    UNION ALL SELECT 'promotion_criteria_classes', 'session_id'
    UNION ALL SELECT 'promotion_criteria_classes', 'class_id'
    UNION ALL SELECT 'promotion_criteria_classes', 'promoted_to_class_id'
    UNION ALL SELECT 'promotion_criteria_classes', 'promoted_to_label'
    UNION ALL SELECT 'promotion_criteria_classes', 'created_at'
    UNION ALL SELECT 'promotion_criteria_classes', 'updated_at'
    UNION ALL SELECT 'promotion_criteria_subjects', 'id'
    UNION ALL SELECT 'promotion_criteria_subjects', 'criteria_id'
    UNION ALL SELECT 'promotion_criteria_subjects', 'subject_id'
    UNION ALL SELECT 'promotion_criteria_subjects', 'minimum_average'
    UNION ALL SELECT 'promotion_criteria_subjects', 'created_at'
    UNION ALL SELECT 'promotion_criteria_subjects', 'updated_at'
    UNION ALL SELECT 'promotion_note_overrides', 'id'
    UNION ALL SELECT 'promotion_note_overrides', 'student_id'
    UNION ALL SELECT 'promotion_note_overrides', 'session_id'
    UNION ALL SELECT 'promotion_note_overrides', 'class_id'
    UNION ALL SELECT 'promotion_note_overrides', 'section_id'
    UNION ALL SELECT 'promotion_note_overrides', 'action'
    UNION ALL SELECT 'promotion_note_overrides', 'decision'
    UNION ALL SELECT 'promotion_note_overrides', 'target_class_id'
    UNION ALL SELECT 'promotion_note_overrides', 'target_label'
    UNION ALL SELECT 'promotion_note_overrides', 'reason'
    UNION ALL SELECT 'promotion_note_overrides', 'automatic_decision'
    UNION ALL SELECT 'promotion_note_overrides', 'automatic_note'
    UNION ALL SELECT 'promotion_note_overrides', 'created_by'
    UNION ALL SELECT 'promotion_note_overrides', 'created_at'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
    ON actual.`TABLE_SCHEMA` = DATABASE()
   AND actual.`TABLE_NAME` = required.`table_name`
   AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`table_name`, required.`column_name`;

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

SELECT 'PROMOTION MIGRATION FINISHED: empty missing_* result sets mean OK.'
       AS `status`;
