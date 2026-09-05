<?php

function migration_135_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function migration_135_source($relative_path)
{
    $source = file_get_contents(__DIR__ . '/../' . $relative_path);
    migration_135_assert($source !== false, 'Unable to read ' . $relative_path . '.');
    return $source;
}

$config = migration_135_source('application/config/migration.php');
preg_match('/\$config\[\'migration_version\'\]\s*=\s*(\d+)\s*;/', $config, $version_match);
migration_135_assert(isset($version_match[1]) && (int) $version_match[1] >= 135, 'CodeIgniter must include migration 135.');

$migration = migration_135_source('application/migrations/135_compact_online_examination.php');
$standalone = migration_135_source('docs/online_examination_compact_cbt_migration.sql');
$consolidated = migration_135_source('docs/all_school_database_migrations.sql');

$required_schema_markers = array(
    'onlineexam_holiday_mappings',
    'score_origin',
    'source_onlineexam_id',
    'source_attempt_id',
    'source_sync_id',
    'previous_metadata_json',
    'applied_metadata_json',
    'standard_component',
);
foreach ($required_schema_markers as $marker) {
    migration_135_assert(strpos($migration, $marker) !== false, 'Migration class is missing ' . $marker . '.');
    migration_135_assert(strpos($standalone, $marker) !== false, 'Standalone school migration is missing ' . $marker . '.');
    migration_135_assert(strpos($consolidated, $marker) !== false, 'Consolidated school migration is missing ' . $marker . '.');
}

migration_135_assert(stripos($standalone, 'information_schema') !== false, 'The standalone SQL must inspect schema state before altering a school database.');
migration_135_assert(strpos($standalone, 'PREPARE') !== false, 'The standalone SQL must use guarded, repeatable DDL.');
migration_135_assert(strpos($consolidated, 'Migration 135:') !== false, 'The consolidated school migration must include a clearly isolated migration-135 block.');
migration_135_assert(strpos($migration, "'legacy_read_only'") !== false, 'Migration 135 must retire existing internal-only destinations without deleting history.');
migration_135_assert(strpos($standalone, "SET `result_adapter` = 'legacy_read_only'") !== false, 'Standalone migration must retire existing internal-only destinations.');
migration_135_assert(strpos($consolidated, "SET `result_adapter` = 'legacy_read_only'") !== false, 'Consolidated migration must retire existing internal-only destinations.');

$migration_128 = strpos($consolidated, 'Migration 128');
$migration_129 = strpos($consolidated, 'Migration 129');
migration_135_assert($migration_128 !== false && $migration_129 !== false && $migration_129 > $migration_128, 'Unable to isolate the migration-128 verification block.');
$migration_128_block = substr($consolidated, $migration_128, $migration_129 - $migration_128);
migration_135_assert(strpos($migration_128_block, 'onlineexam_holiday_mappings') === false, 'Migration 128 must not report a migration-135 table as missing.');

echo "onlineexam migration 135 contract tests passed" . PHP_EOL;
