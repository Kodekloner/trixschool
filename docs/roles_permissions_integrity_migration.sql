-- Role-permission integrity migration for existing school databases.
--
-- Run this once in phpMyAdmin for EACH school database that uses this codebase.
-- The script is safe to run again after it has completed successfully.
--
-- What it does:
--   1. Consolidates duplicate (role_id, perm_cat_id) rows, preserving every grant.
--   2. Removes rows whose role or permission category no longer exists.
--   3. Removes rows that grant no permission.
--   4. Adds a unique index to prevent the same duplicate problem recurring.
--
-- Back up the selected school database before running this maintenance script.

DROP TEMPORARY TABLE IF EXISTS `tmp_roles_permissions_clean`;

CREATE TEMPORARY TABLE `tmp_roles_permissions_clean` (
  `id` int NOT NULL,
  `role_id` int NOT NULL,
  `perm_cat_id` int NOT NULL,
  `can_view` int NOT NULL DEFAULT 0,
  `can_add` int NOT NULL DEFAULT 0,
  `can_edit` int NOT NULL DEFAULT 0,
  `can_delete` int NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tmp_role_permission` (`role_id`, `perm_cat_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_roles_permissions_clean`
  (`id`, `role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
SELECT
  MIN(`roles_permissions`.`id`) AS `id`,
  `roles_permissions`.`role_id`,
  `roles_permissions`.`perm_cat_id`,
  MAX(IFNULL(`roles_permissions`.`can_view`, 0)) AS `can_view`,
  MAX(IFNULL(`roles_permissions`.`can_add`, 0)) AS `can_add`,
  MAX(IFNULL(`roles_permissions`.`can_edit`, 0)) AS `can_edit`,
  MAX(IFNULL(`roles_permissions`.`can_delete`, 0)) AS `can_delete`,
  MIN(`roles_permissions`.`created_at`) AS `created_at`
FROM `roles_permissions`
INNER JOIN `roles`
  ON `roles`.`id` = `roles_permissions`.`role_id`
INNER JOIN `permission_category`
  ON `permission_category`.`id` = `roles_permissions`.`perm_cat_id`
GROUP BY
  `roles_permissions`.`role_id`,
  `roles_permissions`.`perm_cat_id`
HAVING
  MAX(IFNULL(`roles_permissions`.`can_view`, 0)) = 1
  OR MAX(IFNULL(`roles_permissions`.`can_add`, 0)) = 1
  OR MAX(IFNULL(`roles_permissions`.`can_edit`, 0)) = 1
  OR MAX(IFNULL(`roles_permissions`.`can_delete`, 0)) = 1;

START TRANSACTION;

DELETE FROM `roles_permissions`;

INSERT INTO `roles_permissions`
  (`id`, `role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
SELECT
  `id`,
  `role_id`,
  `perm_cat_id`,
  `can_view`,
  `can_add`,
  `can_edit`,
  `can_delete`,
  COALESCE(`created_at`, CURRENT_TIMESTAMP)
FROM `tmp_roles_permissions_clean`;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_roles_permissions_clean`;

-- Add the protection only when no equivalent two-column unique index exists.
-- The existing index may have a different name on an individual school DB.
SET @roles_permissions_unique_index_exists := (
  SELECT COUNT(*)
  FROM (
    SELECT `INDEX_NAME`
    FROM `INFORMATION_SCHEMA`.`STATISTICS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'roles_permissions'
    GROUP BY `INDEX_NAME`
    HAVING MAX(`NON_UNIQUE`) = 0
      AND COUNT(*) = 2
      AND GROUP_CONCAT(
        `COLUMN_NAME`
        ORDER BY `SEQ_IN_INDEX`
        SEPARATOR ','
      ) = 'role_id,perm_cat_id'
  ) AS `matching_role_permission_unique_indexes`
);

SET @roles_permissions_unique_index_sql := IF(
  @roles_permissions_unique_index_exists = 0,
  'ALTER TABLE `roles_permissions` ADD UNIQUE KEY `uq_roles_permissions_role_perm_cat` (`role_id`, `perm_cat_id`)',
  'SELECT ''Unique role-permission index already exists'' AS `status`'
);

PREPARE roles_permissions_unique_index_stmt
FROM @roles_permissions_unique_index_sql;
EXECUTE roles_permissions_unique_index_stmt;
DEALLOCATE PREPARE roles_permissions_unique_index_stmt;

-- Verification: both result values should be 0.
SELECT COUNT(*) AS `duplicate_role_permission_pairs`
FROM (
  SELECT `role_id`, `perm_cat_id`
  FROM `roles_permissions`
  GROUP BY `role_id`, `perm_cat_id`
  HAVING COUNT(*) > 1
) AS `duplicate_permissions`;

SELECT COUNT(*) AS `orphan_role_permission_rows`
FROM `roles_permissions`
LEFT JOIN `roles`
  ON `roles`.`id` = `roles_permissions`.`role_id`
LEFT JOIN `permission_category`
  ON `permission_category`.`id` = `roles_permissions`.`perm_cat_id`
WHERE `roles`.`id` IS NULL
  OR `permission_category`.`id` IS NULL;
