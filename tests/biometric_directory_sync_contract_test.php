<?php

function biometric_directory_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/application/migrations/144_add_biometric_directory_sync.php');
$directSql = file_get_contents($root . '/docs/biometric-directory-sync-migration-144.sql');
$combinedSql = file_get_contents($root . '/docs/all_school_database_migrations.sql');
$service = file_get_contents($root . '/application/libraries/Biometric_attendance_service.php');
$routes = file_get_contents($root . '/application/config/routes.php');
$api = file_get_contents($root . '/application/controllers/api/Biometric_v2.php');
$admin = file_get_contents($root . '/application/controllers/admin/Biometricattendance.php');
$view = file_get_contents($root . '/application/views/admin/biometricattendance/index.php');
$gateway = file_get_contents($root . '/tools/biometric-gateway/src/DirectorySynchronizer.php');
$gatewayControl = file_get_contents($root . '/tools/biometric-gateway/src/GatewayControl.php');
$gatewayRunner = file_get_contents($root . '/tools/biometric-gateway/src/GatewayRunner.php');

foreach (array(
    'directory_sync_mode', 'directory_delete_approval_hash',
    'biometric_directory_links', 'biometric_directory_runs',
    'uq_biometric_directory_subject', 'uq_biometric_directory_run',
) as $schemaRule) {
    biometric_directory_assert(strpos($migration, $schemaRule) !== false, 'Migration 144 is missing: ' . $schemaRule);
    biometric_directory_assert(strpos($directSql, $schemaRule) !== false, 'Standalone migration 144 is missing: ' . $schemaRule);
    biometric_directory_assert(strpos($combinedSql, $schemaRule) !== false, 'Consolidated SQL is missing migration 144 item: ' . $schemaRule);
}

foreach (array('api/biometric/v2/directory', 'api/biometric/v2/directory/result') as $route) {
    biometric_directory_assert(strpos($routes, $route) !== false, 'Directory API route is missing: ' . $route);
}
foreach (array('directorySnapshot', 'recordDirectoryResult') as $method) {
    biometric_directory_assert(strpos($api, $method) !== false, 'Directory API action is missing: ' . $method);
}

foreach (array(
    "where('student_session.session_id', \$sessionId)",
    'directory_role.is_superadmin = 1',
    "'person_key' => \$personKey",
    "'desired_hash' => hash('sha256'",
    "'reason' => 'The previously synchronized SchoolLift person is disabled",
    'upsertDirectoryIdentityMapping',
    "'sync_status' => 'deleted'",
) as $directoryRule) {
    biometric_directory_assert(strpos($service, $directoryRule) !== false, 'Server directory contract is missing: ' . $directoryRule);
}

foreach (array('directorymode', 'approvedirectorydeletion', "hasPrivilege('biometric_attendance', 'can_delete')") as $control) {
    biometric_directory_assert(strpos($admin, $control) !== false, 'Admin directory control is missing: ' . $control);
}
foreach (array('Automatic ZKBio roster', 'Preview calculates changes', 'APPROVE_DIRECTORY_DELETE', 'directory_sync') as $control) {
    biometric_directory_assert(strpos($view, $control) !== false, 'Directory UI is missing: ' . $control);
}

foreach (array(
    'validateSnapshot', 'reconcileDepartments', 'reconcilePositions', 'reconcilePerson',
    'deleteEmployee', 'requiresApproval', 'maximum_delete_count', 'maximum_delete_percent',
    "'adopted'", "'unchanged'", 'resyncEmployees',
) as $gatewayRule) {
    biometric_directory_assert(strpos($gateway, $gatewayRule) !== false, 'Gateway reconciliation is missing: ' . $gatewayRule);
}
biometric_directory_assert(strpos($gatewayControl, "'directory_sync'") !== false, 'Gateway command allowlist must include directory_sync.');
biometric_directory_assert(strpos($gatewayRunner, 'directorySynchronizer->run') !== false, 'Scheduled gateway runs must invoke directory reconciliation.');

echo "biometric directory synchronization contract tests passed\n";
