<?php

/**
 * Destructive biometric migration regression test for cloned SQL dumps.
 *
 * This test creates and drops only randomly named databases on an isolated
 * local MySQL server. It never writes to the supplied dump files.
 *
 * Run:
 *   BIOMETRIC_DUMP_MIGRATION_TEST_SOCKET=/absolute/path/mysql.sock \
 *     php tests/biometric_migration_140_dump_test.php
 *
 * Optional:
 *   BIOMETRIC_DUMP_MIGRATION_TEST_LIMIT=1   Test only the first dump.
 *   BIOMETRIC_DUMP_MIGRATION_TEST_PATTERN=*personal* Filter dump basenames.
 *   BIOMETRIC_DUMP_MIGRATION_TEST_DIR=/path Use another dump directory.
 *   BIOMETRIC_DUMP_MIGRATION_MYSQL=/path    Use a specific mysql client.
 */

$socket = getenv('BIOMETRIC_DUMP_MIGRATION_TEST_SOCKET');
if (!$socket) {
    echo "SKIP biometric dump migration tests: set BIOMETRIC_DUMP_MIGRATION_TEST_SOCKET to an isolated test server." . PHP_EOL;
    exit(0);
}
if ($socket[0] !== '/' || !file_exists($socket)) {
    throw new RuntimeException('The isolated test-server socket must be an existing absolute path.');
}

$root = dirname(__DIR__);
$dumpDirectory = getenv('BIOMETRIC_DUMP_MIGRATION_TEST_DIR') ?: $root . '/database/migration-audit-dumps';
$dumpDirectory = realpath($dumpDirectory);
if ($dumpDirectory === false || !is_dir($dumpDirectory)) {
    throw new RuntimeException('The biometric migration dump directory does not exist.');
}
$mysql = getenv('BIOMETRIC_DUMP_MIGRATION_MYSQL') ?: '/usr/bin/mysql';
if (!is_file($mysql) || !is_executable($mysql)) {
    throw new RuntimeException('Set BIOMETRIC_DUMP_MIGRATION_MYSQL to an executable mysql client.');
}

function biometricDumpAssert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** Run mysql without a shell so paths, passwords, and dump names are not reinterpreted. */
function biometricDumpMysql($mysql, $socket, $database, $query = null, $stdinPath = null, $stdinText = null, $disableForeignKeys = false)
{
    $command = array(
        $mysql, '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket,
        '--user=root', '--default-character-set=utf8mb4', '--batch', '--skip-column-names',
    );
    if ($database !== null) {
        $command[] = $database;
    }
    if ($disableForeignKeys) {
        // Some historical phpMyAdmin dumps rebuild their own foreign keys but
        // contain known orphan rows. Preserve those rows in the disposable
        // clone so this test can assess the additive biometric migrations;
        // the orphan remains a separate database-integrity finding.
        $command[] = '--init-command=SET SESSION FOREIGN_KEY_CHECKS=0';
    }
    if ($query !== null) {
        $command[] = '--execute=' . $query;
    }
    $stdin = $stdinPath !== null ? array('file', $stdinPath, 'r') : array('pipe', 'r');
    $pipes = array();
    $process = proc_open($command, array(
        0 => $stdin,
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    ), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the mysql client.');
    }
    if ($stdinPath === null) {
        if ($stdinText !== null) {
            fwrite($pipes[0], $stdinText);
        }
        fclose($pipes[0]);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('mysql failed: ' . trim($stderr));
    }
    return trim($stdout);
}

function biometricDumpSnapshot($mysql, $socket, $database)
{
    $tables = array('student_attendences', 'staff_attendance', 'id_card', 'staff_id_card');
    $snapshot = array();
    foreach ($tables as $table) {
        $snapshot[$table] = biometricDumpMysql(
            $mysql,
            $socket,
            $database,
            "SELECT CONCAT(COUNT(*), ':', COALESCE(SUM(id),0), ':', COALESCE(MAX(id),0)) FROM `{$table}`"
        );
    }
    foreach (array('id_card', 'staff_id_card') as $table) {
        $hasLayout = biometricDumpMysql(
            $mysql,
            $socket,
            $database,
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='layout_json'"
        ) === '1';
        if ($hasLayout) {
            $snapshot[$table . '.layout_json'] = biometricDumpMysql(
                $mysql,
                $socket,
                $database,
                "SELECT CONCAT(COUNT(*), ':', COALESCE(BIT_XOR(CRC32(CONCAT(CAST(id AS CHAR), '|', COALESCE(layout_json,'')))),0)) FROM `{$table}`"
            );
        }
    }
    return $snapshot;
}

$bundle = file_get_contents($root . '/docs/all_school_database_migrations.sql');
biometricDumpAssert($bundle !== false, 'Could not read the consolidated migration SQL.');
$generatedStartMarker = '-- BEGIN GENERATED MIGRATIONS 130-132';
$generatedEndMarker = '-- END GENERATED MIGRATIONS 130-132';
$generatedStart = strpos($bundle, $generatedStartMarker);
$generatedEnd = strpos($bundle, $generatedEndMarker, $generatedStart === false ? 0 : $generatedStart);
$hardeningStart = strpos($bundle, '-- Migration 140:');
$hardeningEnd = strpos($bundle, '-- Migration 141:', $hardeningStart === false ? 0 : $hardeningStart);
biometricDumpAssert(
    $generatedStart !== false && $generatedEnd !== false && $hardeningStart !== false && $hardeningEnd !== false,
    'Could not isolate migrations 130-132 and 140.'
);
$generatedEnd += strlen($generatedEndMarker);
$biometricSql = substr($bundle, $generatedStart, $generatedEnd - $generatedStart)
    . "\n\n" . substr($bundle, $hardeningStart, $hardeningEnd - $hardeningStart);

$dumps = glob($dumpDirectory . '/*.sql');
sort($dumps, SORT_NATURAL | SORT_FLAG_CASE);
$pattern = trim((string) getenv('BIOMETRIC_DUMP_MIGRATION_TEST_PATTERN'));
if ($pattern !== '') {
    $dumps = array_values(array_filter($dumps, function ($dump) use ($pattern) {
        return fnmatch($pattern, basename($dump), FNM_CASEFOLD);
    }));
}
biometricDumpAssert(!empty($dumps), 'No SQL dumps were found.');
$limit = (int) getenv('BIOMETRIC_DUMP_MIGRATION_TEST_LIMIT');
if ($limit > 0) {
    $dumps = array_slice($dumps, 0, $limit);
}

$tested = 0;
foreach ($dumps as $dump) {
    $database = 'biometric_dump_test_' . bin2hex(random_bytes(6));
    biometricDumpMysql($mysql, $socket, null, 'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        biometricDumpMysql($mysql, $socket, $database, null, $dump, null, true);
        $staffRoleOrphans = (int) biometricDumpMysql(
            $mysql,
            $socket,
            $database,
            'SELECT COUNT(*) FROM staff_roles sr LEFT JOIN staff s ON s.id=sr.staff_id WHERE s.id IS NULL'
        );
        if ($staffRoleOrphans > 0) {
            echo 'NOTICE: ' . basename($dump) . ' contains ' . $staffRoleOrphans
                . ' pre-existing orphan staff-role row(s); biometric migration testing continues on the preserved clone.' . PHP_EOL;
        }
        $examSubjectOrphans = (int) biometricDumpMysql(
            $mysql,
            $socket,
            $database,
            'SELECT COUNT(*) FROM exam_group_class_batch_exam_subjects es LEFT JOIN subjects s ON s.id=es.subject_id WHERE s.id IS NULL'
        );
        if ($examSubjectOrphans > 0) {
            echo 'NOTICE: ' . basename($dump) . ' contains ' . $examSubjectOrphans
                . ' pre-existing orphan exam-subject row(s); biometric migration testing continues on the preserved clone.' . PHP_EOL;
        }
        $before = biometricDumpSnapshot($mysql, $socket, $database);
        biometricDumpMysql($mysql, $socket, $database, null, null, $biometricSql);
        biometricDumpMysql($mysql, $socket, $database, null, null, $biometricSql);
        $after = biometricDumpSnapshot($mysql, $socket, $database);
        biometricDumpAssert($after === $before, basename($dump) . ' lost or changed historical attendance/card data.');

        $schema = biometricDumpMysql($mysql, $socket, $database, "
            SELECT CONCAT(
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='biometric_settings'
                AND COLUMN_NAME IN ('live_pilot_enabled','notify_student_in','notify_student_out','notify_email','notify_sms','notify_whatsapp','live_acknowledged_by','live_acknowledged_at','last_retention_run_at')), ':',
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='biometric_identity_mappings' AND COLUMN_NAME='live_pilot'), ':',
              (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='biometric_notification_queue'
                AND COLUMN_NAME IN ('id','event_id','channel','status','attempt_count','next_attempt_at','last_error','created_at','updated_at','delivered_at')), ':',
              (SELECT COUNT(DISTINCT INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='biometric_notification_queue'
                AND INDEX_NAME IN ('uq_biometric_notification_event_channel','idx_biometric_notification_delivery')), ':',
              (SELECT COUNT(*) FROM biometric_settings WHERE id=1)
            )
        ");
        biometricDumpAssert($schema === '9:1:10:2:1', basename($dump) . ' did not receive the complete migration-140 schema. Got ' . $schema);
        $tested++;
        echo 'PASS: ' . basename($dump) . PHP_EOL;
    } finally {
        biometricDumpMysql($mysql, $socket, null, 'DROP DATABASE IF EXISTS `' . $database . '`');
    }
}

echo 'Biometric migrations 130-132 and 140 passed twice on ' . $tested . ' cloned school dump(s).' . PHP_EOL;
