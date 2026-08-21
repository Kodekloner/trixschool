<?php

/**
 * Regenerates the migration 130/131 section of the all-school SQL bundle.
 * Run from the repository root with PHP 7.4+.
 */

$root = dirname(__DIR__);
$bundlePath = $root . '/docs/all_school_database_migrations.sql';
$bundle = file_get_contents($bundlePath);
if ($bundle === false) {
    fwrite(STDERR, "Could not read consolidated SQL.\n");
    exit(1);
}

function migrationTables($path)
{
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }
    preg_match_all('/\$this->createTable\(\'([^\']+)\', "\n(.*?)\n        "\);/s', $source, $matches, PREG_SET_ORDER);
    if (!$matches) {
        throw new RuntimeException('No CREATE TABLE statements found in ' . $path);
    }
    $tables = array();
    foreach ($matches as $match) {
        $sql = preg_replace('/^ {12}/m', '', $match[2]);
        $sql = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', trim($sql));
        $tables[$match[1]] = $sql . ';';
    }
    return $tables;
}

function addColumnSql($table, $column, $definition)
{
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $table . '_' . $column);
    $dynamicDefinition = str_replace("'", "''", $definition);
    return "SET @trix_schema_sql := IF(\n"
        . "  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}') = 0,\n"
        . "  'ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$dynamicDefinition}',\n"
        . "  'SET @trix_noop = 1'\n"
        . ");\nPREPARE trix_{$safe}_stmt FROM @trix_schema_sql;\nEXECUTE trix_{$safe}_stmt;\nDEALLOCATE PREPARE trix_{$safe}_stmt;";
}

function addIndexSql($table, $index, $columns)
{
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $table . '_' . $index);
    return "SET @trix_schema_sql := IF(\n"
        . "  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND INDEX_NAME = '{$index}') = 0,\n"
        . "  'ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})',\n"
        . "  'SET @trix_noop = 1'\n"
        . ");\nPREPARE trix_{$safe}_stmt FROM @trix_schema_sql;\nEXECUTE trix_{$safe}_stmt;\nDEALLOCATE PREPARE trix_{$safe}_stmt;";
}

$m130 = migrationTables($root . '/application/migrations/130_add_biometric_attendance.php');
$m131 = migrationTables($root . '/application/migrations/131_add_id_card_design_studio.php');

$start = '-- BEGIN GENERATED MIGRATIONS 130-131';
$end = '-- END GENERATED MIGRATIONS 130-131';
if (strpos($bundle, $start) !== false) {
    $bundle = preg_replace('/\n?' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '\n?/s', "\n", $bundle);
}

$bundle = preg_replace(
    '/-- SchoolLift consolidated tenant-database migrations \(126 through \d+\)\./',
    '-- SchoolLift consolidated tenant-database migrations (126 through 131).',
    $bundle,
    1
);
$bundle = preg_replace(
    '/-- Generated for deployment to every school database on \d{4}-\d{2}-\d{2}\./',
    '-- Generated for deployment to every school database on 2026-08-12.',
    $bundle,
    1
);
$bundle = preg_replace(
    '/(?:--   130_add_biometric_attendance\.php\n|--   131_add_id_card_design_studio\.php\n)+/',
    '',
    $bundle
);
$bundle = str_replace(
    "--   129_add_monnify_payments.php\n",
    "--   129_add_monnify_payments.php\n--   130_add_biometric_attendance.php\n--   131_add_id_card_design_studio.php\n",
    $bundle
);

$section = array();
$section[] = $start;
$section[] = '-- ========================================================================';
$section[] = '-- Migration 130: production biometric attendance (single terminal)';
$section[] = '-- ========================================================================';
$section[] = '';
$section[] = '-- Migration 130/131 prerequisites. Every result set must be empty.';
$section[] = "SELECT required.`table_name`, required.`column_name` AS `missing_prerequisite`\nFROM (\n"
    . "  SELECT 'student_attendences' AS `table_name`, 'id' AS `column_name`\n"
    . "  UNION ALL SELECT 'staff_attendance', 'id'\n"
    . "  UNION ALL SELECT 'id_card', 'id'\n"
    . "  UNION ALL SELECT 'staff_id_card', 'id'\n"
    . "  UNION ALL SELECT 'sch_settings', 'session_id'\n"
    . "  UNION ALL SELECT 'attendence_type', 'id'\n"
    . "  UNION ALL SELECT 'staff_attendance_type', 'id'\n"
    . ") AS required\nLEFT JOIN INFORMATION_SCHEMA.COLUMNS AS actual\n"
    . "  ON actual.TABLE_SCHEMA = DATABASE()\n AND actual.TABLE_NAME = required.table_name\n AND actual.COLUMN_NAME = required.column_name\n"
    . "WHERE actual.COLUMN_NAME IS NULL\nORDER BY required.table_name, required.column_name;";
$section[] = "SET @trix_bio_preflight_missing := (\n  SELECT COUNT(*)\n  FROM (\n"
    . "    SELECT 'student_attendences' AS `table_name`, 'id' AS `column_name`\n"
    . "    UNION ALL SELECT 'staff_attendance', 'id'\n"
    . "    UNION ALL SELECT 'id_card', 'id'\n"
    . "    UNION ALL SELECT 'staff_id_card', 'id'\n"
    . "    UNION ALL SELECT 'sch_settings', 'session_id'\n"
    . "    UNION ALL SELECT 'attendence_type', 'id'\n"
    . "    UNION ALL SELECT 'staff_attendance_type', 'id'\n"
    . "  ) AS required\n  LEFT JOIN INFORMATION_SCHEMA.COLUMNS AS actual\n"
    . "    ON actual.TABLE_SCHEMA = DATABASE()\n   AND actual.TABLE_NAME = required.table_name\n   AND actual.COLUMN_NAME = required.column_name\n"
    . "  WHERE actual.COLUMN_NAME IS NULL\n);\n"
    . "SET @trix_schema_sql := IF(DATABASE() IS NOT NULL AND @trix_bio_preflight_missing = 0,\n"
    . "  'SET @trix_bio_preflight_ok = 1',\n"
    . "  'SELECT * FROM `SCHOOLLIFT_BIOMETRIC_ID_STUDIO_PREFLIGHT_FAILED`');\n"
    . "PREPARE trix_bio_preflight_stmt FROM @trix_schema_sql;\nEXECUTE trix_bio_preflight_stmt;\nDEALLOCATE PREPARE trix_bio_preflight_stmt;";
$section[] = implode("\n\n", $m130);
$section[] = addColumnSql('biometric_attendance_days', 'academic_session_id', 'INT NULL');
$section[] = addColumnSql('biometric_attendance_days', 'term', 'VARCHAR(225) NULL');
$section[] = addColumnSql('biometric_attendance_days', 'manual_locked', 'TINYINT(1) NOT NULL DEFAULT 0');
$section[] = addColumnSql('biometric_attendance_days', 'official_table', 'VARCHAR(40) NULL');
$section[] = addColumnSql('biometric_attendance_days', 'official_attendance_id', 'INT NULL');
$section[] = addColumnSql('biometric_attendance_days', 'projection_status', "VARCHAR(24) NOT NULL DEFAULT 'not_applicable'");
$section[] = addColumnSql('biometric_events', 'payload_hash', 'CHAR(64) NULL');
$section[] = addColumnSql('biometric_events', 'metadata_json', 'TEXT NULL');
$section[] = addColumnSql('biometric_events', 'projection_status', "VARCHAR(24) NOT NULL DEFAULT 'not_applicable'");
$section[] = addColumnSql('student_attendences', 'attendance_source', 'VARCHAR(24) NULL');
$section[] = addColumnSql('student_attendences', 'biometric_day_id', 'BIGINT UNSIGNED NULL');
$section[] = addColumnSql('staff_attendance', 'attendance_source', 'VARCHAR(24) NULL');
$section[] = addColumnSql('staff_attendance', 'biometric_day_id', 'BIGINT UNSIGNED NULL');
$section[] = addIndexSql('student_attendences', 'idx_student_attendance_biometric_day', '`biometric_day_id`');
$section[] = addIndexSql('staff_attendance', 'idx_staff_attendance_biometric_day', '`biometric_day_id`');
$section[] = "INSERT INTO `biometric_settings` (`id`, `mode`, `timezone`, `created_at`, `updated_at`)\n"
    . "SELECT 1, 'disabled', 'Africa/Lagos', UTC_TIMESTAMP(), UTC_TIMESTAMP()\n"
    . "WHERE NOT EXISTS (SELECT 1 FROM `biometric_settings` WHERE `id` = 1);";
$section[] = "INSERT INTO `biometric_devices`\n"
    . "  (`integration_id`, `serial_number`, `name`, `location`, `device_type`, `direction_mode`, `is_virtual`, `is_active`, `created_at`, `updated_at`)\n"
    . "SELECT NULL, 'SIM-GATE-001', 'Simulation Gate', 'Browser Test Terminal', 'biometric', 'bidirectional', 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()\n"
    . "WHERE NOT EXISTS (SELECT 1 FROM `biometric_devices` WHERE `serial_number` = 'SIM-GATE-001');";
$section[] = "SET @trix_bio_permission_group_new_id := (SELECT COALESCE(MAX(`id`), 0) + 1 FROM `permission_group`);\n"
    . "INSERT INTO `permission_group` (`id`, `name`, `short_code`, `is_active`, `system`, `created_at`)\n"
    . "SELECT @trix_bio_permission_group_new_id, 'Attendance', 'student_attendance', 1, 0, UTC_TIMESTAMP()\n"
    . "WHERE NOT EXISTS (SELECT 1 FROM `permission_group` WHERE `short_code` = 'student_attendance');\n"
    . "SET @trix_bio_permission_group_id := (SELECT `id` FROM `permission_group` WHERE `short_code` = 'student_attendance' ORDER BY `id` LIMIT 1);\n"
    . "SET @trix_bio_permission_new_id := (SELECT COALESCE(MAX(`id`), 0) + 1 FROM `permission_category`);\n"
    . "INSERT INTO `permission_category` (`id`, `perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)\n"
    . "SELECT @trix_bio_permission_new_id, @trix_bio_permission_group_id, 'Biometric Attendance', 'biometric_attendance', 1, 1, 1, 1, UTC_TIMESTAMP()\n"
    . "WHERE NOT EXISTS (SELECT 1 FROM `permission_category` WHERE `short_code` = 'biometric_attendance');\n"
    . "SET @trix_bio_permission_id := (SELECT `id` FROM `permission_category` WHERE `short_code` = 'biometric_attendance' ORDER BY `id` LIMIT 1);\n"
    . "SET @trix_bio_role_permission_next_id := (SELECT COALESCE(MAX(`id`), 0) FROM `roles_permissions`);\n"
    . "INSERT INTO `roles_permissions` (`id`, `role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)\n"
    . "SELECT (@trix_bio_role_permission_next_id := @trix_bio_role_permission_next_id + 1), r.`id`, @trix_bio_permission_id, 1, 1, 1, 1, UTC_TIMESTAMP()\n"
    . "FROM `roles` r\nWHERE r.`name` IN ('Admin', 'Super Admin')\n"
    . "  AND NOT EXISTS (SELECT 1 FROM `roles_permissions` rp WHERE rp.`role_id` = r.`id` AND rp.`perm_cat_id` = @trix_bio_permission_id);";

$section[] = '-- ========================================================================';
$section[] = '-- Migration 131: versioned ID Card Design Studio';
$section[] = '-- ========================================================================';
$section[] = "SET @trix_id_card_had_dimensions := (\n  SELECT IF(COUNT(*) = 2, 1, 0) FROM INFORMATION_SCHEMA.COLUMNS\n"
    . "  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'id_card' AND COLUMN_NAME IN ('card_width', 'card_height')\n);\n"
    . "SET @trix_staff_id_card_had_dimensions := (\n  SELECT IF(COUNT(*) = 2, 1, 0) FROM INFORMATION_SCHEMA.COLUMNS\n"
    . "  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'staff_id_card' AND COLUMN_NAME IN ('card_width', 'card_height')\n);";
foreach (array('id_card', 'staff_id_card') as $legacyTable) {
    $section[] = addColumnSql($legacyTable, 'card_unit', "VARCHAR(10) NOT NULL DEFAULT 'mm'");
    $section[] = addColumnSql($legacyTable, 'card_width', 'DECIMAL(10,2) NOT NULL DEFAULT 85.60');
    $section[] = addColumnSql($legacyTable, 'card_height', 'DECIMAL(10,2) NOT NULL DEFAULT 53.98');
    $section[] = addColumnSql($legacyTable, 'photo_style', "VARCHAR(20) NOT NULL DEFAULT 'round'");
    $section[] = addColumnSql($legacyTable, 'layout_json', 'LONGTEXT NULL');
}
$section[] = "UPDATE `id_card` SET `card_width` = 53.98, `card_height` = 85.60, `card_unit` = 'mm'\n"
    . "WHERE @trix_id_card_had_dimensions = 0 AND `enable_vertical_card` = 1;\n"
    . "UPDATE `staff_id_card` SET `card_width` = 53.98, `card_height` = 85.60, `card_unit` = 'mm'\n"
    . "WHERE @trix_staff_id_card_had_dimensions = 0 AND `enable_vertical_card` = 1;";
$section[] = implode("\n\n", $m131);

$allNewTables = array_merge(array_keys($m130), array_keys($m131));
$union = array();
foreach ($allNewTables as $i => $table) {
    $union[] = ($i ? 'UNION ALL ' : '') . "SELECT '{$table}' AS `table_name`";
}
$section[] = '-- Migration 130/131 verification. Every result set below must be empty.';
$section[] = "SELECT required.`table_name` AS `missing_migration_table`\nFROM (\n  "
    . implode("\n  ", $union) . "\n) AS required\nLEFT JOIN INFORMATION_SCHEMA.TABLES actual\n"
    . "  ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = required.table_name\n"
    . "WHERE actual.TABLE_NAME IS NULL\nORDER BY required.table_name;";
$section[] = "SELECT required.`table_name`, required.`column_name` AS `missing_migration_column`\nFROM (\n"
    . "  SELECT 'student_attendences' AS `table_name`, 'attendance_source' AS `column_name`\n"
    . "  UNION ALL SELECT 'student_attendences', 'biometric_day_id'\n"
    . "  UNION ALL SELECT 'staff_attendance', 'attendance_source'\n"
    . "  UNION ALL SELECT 'staff_attendance', 'biometric_day_id'\n"
    . "  UNION ALL SELECT 'id_card', 'card_unit'\n  UNION ALL SELECT 'id_card', 'card_width'\n  UNION ALL SELECT 'id_card', 'card_height'\n  UNION ALL SELECT 'id_card', 'photo_style'\n  UNION ALL SELECT 'id_card', 'layout_json'\n"
    . "  UNION ALL SELECT 'staff_id_card', 'card_unit'\n  UNION ALL SELECT 'staff_id_card', 'card_width'\n  UNION ALL SELECT 'staff_id_card', 'card_height'\n  UNION ALL SELECT 'staff_id_card', 'photo_style'\n  UNION ALL SELECT 'staff_id_card', 'layout_json'\n"
    . ") AS required\nLEFT JOIN INFORMATION_SCHEMA.COLUMNS actual\n"
    . "  ON actual.TABLE_SCHEMA = DATABASE() AND actual.TABLE_NAME = required.table_name AND actual.COLUMN_NAME = required.column_name\n"
    . "WHERE actual.COLUMN_NAME IS NULL\nORDER BY required.table_name, required.column_name;";
$section[] = "SELECT 'biometric_settings' AS `missing_seed`, 1 AS `expected_id`\nWHERE NOT EXISTS (SELECT 1 FROM `biometric_settings` WHERE `id` = 1)\n"
    . "UNION ALL SELECT 'SIM-GATE-001', 1 WHERE NOT EXISTS (SELECT 1 FROM `biometric_devices` WHERE `serial_number` = 'SIM-GATE-001')\n"
    . "UNION ALL SELECT 'biometric_attendance_permission', 1 WHERE NOT EXISTS (SELECT 1 FROM `permission_category` WHERE `short_code` = 'biometric_attendance');";
$section[] = $end;

$marker = '-- Consolidated verification';
$position = strpos($bundle, $marker);
if ($position === false) {
    fwrite(STDERR, "Consolidated verification marker was not found.\n");
    exit(1);
}
$dividerPosition = strrpos(substr($bundle, 0, $position), '-- ========================================================================');
if ($dividerPosition !== false) {
    $position = $dividerPosition;
}
$generated = implode("\n\n", $section) . "\n\n";
$prefix = rtrim(substr($bundle, 0, $position)) . "\n\n";
$suffix = ltrim(substr($bundle, $position));
$bundle = $prefix . $generated . $suffix;

if (file_put_contents($bundlePath, $bundle) === false) {
    fwrite(STDERR, "Could not update consolidated SQL.\n");
    exit(1);
}
echo "Updated {$bundlePath} with migrations 130 and 131.\n";
