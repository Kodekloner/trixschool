<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

function biometric_contract_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$service = new ReflectionClass('Biometric_attendance_service');
$required = array(
    'getSettings', 'updateSettings', 'dashboard', 'listDevices', 'saveDevice', 'setDeviceActive', 'deleteDevice',
    'listMappings', 'saveMapping', 'setMappingActive', 'setMappingPilot', 'deleteMapping', 'previewBulkMappings', 'bulkSeedMappings', 'listIntegrations',
    'createIntegrationToken', 'rotateIntegrationToken', 'setIntegrationActive', 'deleteIntegration', 'getPunchStateMappings',
    'savePunchStateMappings', 'authenticateToken', 'ingestBatch', 'ingestEvent',
    'listEvents', 'deleteEvent', 'listDays', 'deleteAttendanceDay', 'listExceptions', 'resolveException', 'listAudit',
    'listScannerStations', 'saveScannerStation', 'setScannerStationActive', 'deleteScannerStation', 'listQrCredentials',
    'issueQrCredential', 'qrEncryptionReady', 'getActiveQrCredential', 'getQrCredentialToken',
    'revokeQrCredential', 'deleteQrCredential', 'lookupQrCredential', 'scanQrCredential',
    'markManualOverride', 'purgeSimulationData', 'runRetentionCleanup',
    'notificationQueueSummary', 'listNotificationQueue', 'processNotificationQueue',
    'retryFailedNotifications', 'recentEventsAfter',
    'pollGateway', 'completeGatewayCommand', 'getGatewayStatus',
    'listGatewayAgents', 'queueGatewayCommand', 'listGatewayCommands', 'deleteGatewayCommand',
    'listDirectoryRuns', 'setDirectorySyncMode', 'approveDirectoryDeletion',
    'directorySnapshot', 'recordDirectoryResult'
);
foreach ($required as $method) {
    biometric_contract_assert($service->hasMethod($method) && $service->getMethod($method)->isPublic(), 'Missing public service method: ' . $method);
}

$migration = file_get_contents(__DIR__ . '/../application/migrations/130_add_biometric_attendance.php');
foreach (array(
    'biometric_settings', 'biometric_integrations', 'biometric_devices',
    'biometric_punch_state_mappings', 'biometric_identity_mappings',
    'biometric_gateway_batches', 'biometric_gateway_cursors', 'biometric_events',
    'biometric_attendance_days', 'biometric_exceptions',
    'biometric_reconciliation_actions', 'biometric_scanner_stations',
    'biometric_qr_credentials', 'biometric_audit_logs'
) as $table) {
    biometric_contract_assert(strpos($migration, "CREATE TABLE `{$table}`") !== false, 'Migration 130 is missing table: ' . $table);
}
biometric_contract_assert(strpos($migration, "'SIM-GATE-001'") !== false, 'Migration must seed one bidirectional Test Terminal.');
biometric_contract_assert(strpos($migration, "'disabled'") !== false, 'Migration must default to disabled mode.');
biometric_contract_assert(strpos($migration, 'biometric_template') === false, 'Schema must never persist biometric templates.');
biometric_contract_assert(strpos($migration, '`term` VARCHAR(225)') !== false, 'Biometric day term must accommodate legacy term values.');
biometric_contract_assert(strpos($migration, 'idx_student_attendance_biometric_day') !== false, 'Student biometric day link must be indexed.');
biometric_contract_assert(strpos($migration, 'idx_staff_attendance_biometric_day') !== false, 'Staff biometric day link must be indexed.');
biometric_contract_assert(strpos($migration, '`token_ciphertext` TEXT NOT NULL') !== false, 'Issued QR credentials must always retain encrypted reprint data.');

$routes = file_get_contents(__DIR__ . '/../application/config/routes.php');
biometric_contract_assert(strpos($routes, "api/biometric/v2/events") !== false, 'V2 event route is missing.');
biometric_contract_assert(strpos($routes, "api/biometric/v2/health") !== false, 'V2 health route is missing.');
biometric_contract_assert(strpos($routes, "api/biometric/v2/gateway/poll") !== false, 'Gateway poll route is missing.');
biometric_contract_assert(strpos($routes, "api/biometric/v2/gateway/result") !== false, 'Gateway result route is missing.');

$controlMigration = file_get_contents(__DIR__ . '/../application/migrations/132_add_biometric_gateway_control.php');
foreach (array('biometric_gateway_agents', 'biometric_gateway_commands') as $table) {
    biometric_contract_assert(strpos($controlMigration, "CREATE TABLE `{$table}`") !== false, 'Migration 132 is missing table: ' . $table);
}
biometric_contract_assert(strpos($controlMigration, 'UNIQUE KEY `uq_biometric_gateway_agent_id` (`gateway_id`)') !== false, 'Migration 132 must keep gateway IDs globally owned by one integration.');
$consolidatedSql = file_get_contents(__DIR__ . '/../docs/all_school_database_migrations.sql');
biometric_contract_assert(strpos($consolidatedSql, 'UNIQUE KEY `uq_biometric_gateway_agent_id` (`gateway_id`)') !== false, 'Consolidated SQL must match migration 132 gateway ownership.');
$hardeningMigration = file_get_contents(__DIR__ . '/../application/migrations/140_harden_biometric_operations.php');
foreach (array('biometric_notification_queue', 'live_pilot_enabled', 'live_pilot', 'last_retention_run_at') as $item) {
    biometric_contract_assert(strpos($hardeningMigration, $item) !== false, 'Migration 140 is missing: ' . $item);
    biometric_contract_assert(strpos($consolidatedSql, $item) !== false, 'Consolidated SQL is missing migration 140 item: ' . $item);
}
foreach (array('connection_test', 'sync_now', 'directory_sync', 'retry_failed') as $command) {
    biometric_contract_assert(strpos($service->getFileName() ? file_get_contents($service->getFileName()) : '', "'{$command}'") !== false, 'Gateway command allowlist is missing: ' . $command);
}
$migrationConfig = file_get_contents(__DIR__ . '/../application/config/migration.php');
biometric_contract_assert(
    preg_match('/migration_version[\'\"]?\]\s*=\s*(\d+)\s*;/', $migrationConfig, $migrationMatch) === 1
        && (int) $migrationMatch[1] >= 144,
    'Migration target must include biometric directory migration 144 or later.'
);

$controller = file_get_contents(__DIR__ . '/../application/controllers/api/Biometric_v2.php');
biometric_contract_assert(strpos($controller, "'results' => array()") !== false, 'API errors must retain the results[] contract.');
biometric_contract_assert(strpos($controller, 'authenticateToken') !== false, 'V2 API must authenticate bearer tokens.');
biometric_contract_assert(strpos($controller, 'gatewayJsonRequest') !== false, 'Gateway control endpoints must share authenticated bounded JSON handling.');
biometric_contract_assert(strpos($controller, 'migrations through 144') !== false, 'API readiness errors must identify the current biometric migration level.');

$modelSource = file_get_contents(__DIR__ . '/../application/models/Biometric_attendance_model.php');
biometric_contract_assert(strpos($modelSource, "'biometric_gateway_agents'") !== false, 'Biometric readiness must require the gateway agent table.');
biometric_contract_assert(strpos($modelSource, "'biometric_gateway_commands'") !== false, 'Biometric readiness must require the gateway command table.');
biometric_contract_assert(strpos($modelSource, "'biometric_notification_queue'") !== false, 'Biometric readiness must require the notification queue table.');
biometric_contract_assert(strpos($modelSource, "'notification_type'") !== false, 'Biometric readiness must require typed notification queue rows.');
biometric_contract_assert(strpos($modelSource, "'is_whatsapp'") !== false, 'Biometric readiness must require per-template WhatsApp switches.');

$notificationMigration = file_get_contents(__DIR__ . '/../application/migrations/143_add_biometric_notification_templates.php');
foreach (array(
    'biometric_attendance_in', 'biometric_attendance_out', 'biometric_fees_due',
    'notification_type', 'is_whatsapp', 'display_whatsapp',
    'uq_biometric_notification_event_type_channel',
) as $notificationRule) {
    biometric_contract_assert(strpos($notificationMigration, $notificationRule) !== false, 'Migration 143 is missing: ' . $notificationRule);
    biometric_contract_assert(strpos($consolidatedSql, $notificationRule) !== false, 'Consolidated SQL is missing migration 143 item: ' . $notificationRule);
}

$adminView = file_get_contents(__DIR__ . '/../application/views/admin/biometricattendance/index.php');
$readyPosition = strpos($adminView, '$(function () {');
$readyClosePosition = strrpos($adminView, "    });\n})(jQuery);");
$mappingSelectPosition = strpos($adminView, "configureSubjectSelect('#bio-mapping-type', '#bio-mapping-subject')");
$credentialSelectPosition = strpos($adminView, "configureSubjectSelect('#bio-credential-type', '#bio-credential-subject')");
$hashTabPosition = strpos($adminView, ".tab('show')");
biometric_contract_assert($readyPosition !== false, 'Biometric page behavior must wait for footer libraries to load.');
biometric_contract_assert($readyClosePosition !== false && $readyClosePosition > $readyPosition, 'Biometric DOM-ready callback must close before its jQuery wrapper.');
biometric_contract_assert($mappingSelectPosition !== false && $mappingSelectPosition > $readyPosition && $mappingSelectPosition < $readyClosePosition, 'Identity mapping Select2 must initialize inside DOM ready.');
biometric_contract_assert($credentialSelectPosition !== false && $credentialSelectPosition > $readyPosition && $credentialSelectPosition < $readyClosePosition, 'QR credential Select2 must initialize inside DOM ready.');
biometric_contract_assert($hashTabPosition !== false && $hashTabPosition > $readyPosition && $hashTabPosition < $readyClosePosition, 'Biometric hash tab activation must wait for Bootstrap inside DOM ready.');
foreach (array(
    'Raw state', 'Live refresh every 5 seconds', 'Map matching identity and retry',
    'Process notification queue now', 'Run retention cleanup', 'Add pilot',
    'Admission No.', 'Close mapping preview', 'DELETE_TEST_EVENT',
    'DELETE_TEST_SESSION', '<th>Class</th><th>Arm</th>', 'bio-mapping-toolbar', 'CSV'
) as $operationalControl) {
    biometric_contract_assert(strpos($adminView, $operationalControl) !== false, 'Biometric operations UI is missing: ' . $operationalControl);
}
biometric_contract_assert(
    strpos($adminView, 'name="external_person_code"') === false,
    'Identity Mapping must derive Admission No./Employee ID instead of accepting a manually typed device code.'
);

$adminController = file_get_contents(__DIR__ . '/../application/controllers/admin/Biometricattendance.php');
foreach (array(
    'hasCompletedShadowSession', "ein.source = 'gateway'", "eout.source = 'gateway'",
    'student_pilot_ready', 'staff_pilot_ready', 'live_scope_authorized',
    'eventfeed', 'runretention', 'processnotifications', 'retrynotifications', 'togglestation',
    'deleteevent', 'deleteday', 'deletemapping', 'deletedevice', 'deleteintegration',
    'deletegatewaycommand', 'deletestation', 'deletecredential', 'closemappingpreview',
    "'class_name'", "'arm_name'", 'csvSafeValue',
) as $operationalRule) {
    biometric_contract_assert(strpos($adminController, $operationalRule) !== false, 'Biometric operations controller is missing: ' . $operationalRule);
}

$cronController = file_get_contents(__DIR__ . '/../application/controllers/Cron.php');
biometric_contract_assert(strpos($cronController, 'processNotificationQueue') !== false, 'Cron must process guardian attendance notifications.');
biometric_contract_assert(strpos($cronController, 'Retention already ran today.') !== false, 'Scheduled retention must be bounded to one daily run.');

$serviceSource = file_get_contents(__DIR__ . '/../application/libraries/Biometric_attendance_service.php');
biometric_contract_assert(strpos($serviceSource, 'PERIOD_ATTENDANCE_UNSUPPORTED') !== false, 'Period-wise schools must be guarded from daily student attendance projection.');
biometric_contract_assert(strpos($serviceSource, 'attendanceTypeExists') !== false, 'Settings must validate configured attendance type IDs.');
biometric_contract_assert(strpos($serviceSource, 'BIOMETRIC_QR_ENCRYPTION_KEY') !== false, 'QR issuance must depend on a server-side encryption key.');
biometric_contract_assert(strpos($serviceSource, 'pilot_excluded') !== false, 'Live pilot mode must exclude people who are not allowlisted.');
biometric_contract_assert(strpos($serviceSource, 'runRetentionCleanup') !== false, 'Configured retention must have an executable cleanup service.');
biometric_contract_assert(strpos($serviceSource, 'live_scope_authorized') !== false, 'Widening Live projection must require a fresh authorization.');
biometric_contract_assert(strpos($serviceSource, 'biometric_notification_queue') !== false, 'Live guardian alerts must use a durable queue.');
biometric_contract_assert(strpos($serviceSource, 'studentFeeBalance') !== false, 'Biometric fee reminders must read the Fees module balance.');
biometric_contract_assert(strpos($serviceSource, 'biometric_fees_due') !== false, 'Biometric fee reminders must be a separate notification type.');
biometric_contract_assert(strpos($serviceSource, 'notificationTemplate') !== false, 'Biometric guardian messages must use editable notification templates.');
biometric_contract_assert(strpos($serviceSource, 'map_and_retry') !== false, 'Unknown people must support an auditable mapping-and-retry reconciliation.');
biometric_contract_assert(strpos($serviceSource, 'identityCodeUsageCount') !== false, 'Identity mappings must derive a unique admission number or employee ID.');
biometric_contract_assert(strpos($serviceSource, "operating_mode'] === 'live'") !== false, 'Live event deletion must be blocked.');
biometric_contract_assert(
    strpos($serviceSource, "update('biometric_events', array('device_id' => null))") !== false,
    'Deleting a disabled terminal must retain events while detaching their device row reference.'
);
biometric_contract_assert(
    strpos($serviceSource, "update('biometric_scanner_stations', array(") !== false,
    'Deleting a disabled terminal must disable and detach linked scanner configuration.'
);

$gisGoLiveSql = file_get_contents(__DIR__ . '/../docs/gis-biometric-go-live-2026-09-28.sql');
foreach (array(
    "DATABASE() <> 'trixschool_gis'", "`mode` = 'live'", '`project_staff` = 0',
    '`live_pilot_enabled` = 0', "`external_person_code` = 'GIS369'",
    "'exception.ignore'", '`biometric_reconciliation_actions`', 'START TRANSACTION', 'COMMIT;'
) as $gisGoLiveRule) {
    biometric_contract_assert(
        strpos($gisGoLiveSql, $gisGoLiveRule) !== false,
        'GIS go-live SQL is missing safety rule: ' . $gisGoLiveRule
    );
}
biometric_contract_assert(
    stripos($gisGoLiveSql, 'DELETE FROM') === false && stripos($gisGoLiveSql, 'TRUNCATE TABLE') === false,
    'GIS go-live SQL must preserve commissioning events, sessions, exceptions, and audit history.'
);
biometric_contract_assert(
    strpos($gisGoLiveSql, 'UPDATE `migrations`') === false
        && strpos($gisGoLiveSql, 'INSERT INTO `migrations`') === false,
    'The GIS operational go-live must not change the numbered migration ledger.'
);

$studentAttendanceModel = file_get_contents(__DIR__ . '/../application/models/Stuattendence_model.php');
$staffAttendanceModel = file_get_contents(__DIR__ . '/../application/models/Staffattendancemodel.php');
biometric_contract_assert(strpos($studentAttendanceModel, "markManualOverride('student_attendences'") !== false, 'Manual student attendance edits must lock a linked biometric day.');
biometric_contract_assert(strpos($staffAttendanceModel, "markManualOverride('staff_attendance'") !== false, 'Manual staff attendance edits must lock a linked biometric day.');

echo "biometric backend contract tests passed\n";
