-- SchoolLift migration 134: opt-in staff alerts and external-email permission.
--
-- Import this file separately into each complete SchoolLift tenant database.
-- Select the intended database and take a verified backup before importing.
-- Compatible with MySQL 5.7+/8.0 and compatible MariaDB releases.
-- This script is rerunnable and does not update the CodeIgniter migrations
-- ledger.

SELECT DATABASE() AS `selected_school_database`;

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


-- Verification: every result set below must be empty.
SELECT 'support_email_notifications' AS `missing_table`
WHERE NOT EXISTS (
  SELECT 1
  FROM `INFORMATION_SCHEMA`.`TABLES`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'support_email_notifications'
);

SELECT 'support_email_alert_deliveries' AS `missing_table`
WHERE NOT EXISTS (
  SELECT 1
  FROM `INFORMATION_SCHEMA`.`TABLES`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = 'support_email_alert_deliveries'
);

SELECT required.`column_name` AS `missing_column`
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

SELECT required.`requirement` AS `missing_index`
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

SELECT required.`column_name` AS `missing_alert_delivery_column`
FROM (
  SELECT 'id' AS `column_name`
  UNION ALL SELECT 'notification_id'
  UNION ALL SELECT 'incoming_email_id'
  UNION ALL SELECT 'support_ticket_id'
  UNION ALL SELECT 'school_domain'
  UNION ALL SELECT 'inbound_address'
  UNION ALL SELECT 'recipient_email'
  UNION ALL SELECT 'delivery_status'
  UNION ALL SELECT 'attempt_count'
  UNION ALL SELECT 'available_at'
  UNION ALL SELECT 'last_attempt_at'
  UNION ALL SELECT 'sent_at'
  UNION ALL SELECT 'provider_message_id'
  UNION ALL SELECT 'error_message'
  UNION ALL SELECT 'created_at'
  UNION ALL SELECT 'updated_at'
) AS required
LEFT JOIN `INFORMATION_SCHEMA`.`COLUMNS` AS actual
  ON actual.`TABLE_SCHEMA` = DATABASE()
 AND actual.`TABLE_NAME` = 'support_email_alert_deliveries'
 AND actual.`COLUMN_NAME` = required.`column_name`
WHERE actual.`COLUMN_NAME` IS NULL
ORDER BY required.`column_name`;

SELECT required.`requirement` AS `missing_alert_delivery_index`
FROM (
  SELECT 'PRIMARY(id)' AS `requirement`, 'id' AS `columns`, 0 AS `non_unique`
  UNION ALL SELECT 'UNIQUE(notification_id,incoming_email_id)', 'notification_id,incoming_email_id', 0
  UNION ALL SELECT 'INDEX(delivery_status,available_at)', 'delivery_status,available_at', 1
  UNION ALL SELECT 'INDEX(support_ticket_id)', 'support_ticket_id', 1
  UNION ALL SELECT 'INDEX(incoming_email_id)', 'incoming_email_id', 1
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
      AND `TABLE_NAME` = 'support_email_alert_deliveries'
    GROUP BY `INDEX_NAME`
  ) AS actual
  WHERE actual.`columns` = required.`columns`
    AND actual.`non_unique` = required.`non_unique`
)
ORDER BY required.`requirement`;

SELECT 'external_email' AS `missing_permission`
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

SELECT 'MIGRATION 134 FINISHED: empty missing_* result sets mean OK.' AS `status`;
