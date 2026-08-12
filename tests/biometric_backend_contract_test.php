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
    'getSettings', 'updateSettings', 'dashboard', 'listDevices', 'saveDevice', 'setDeviceActive',
    'listMappings', 'saveMapping', 'setMappingActive', 'previewBulkMappings', 'bulkSeedMappings', 'listIntegrations',
    'createIntegrationToken', 'rotateIntegrationToken', 'setIntegrationActive', 'getPunchStateMappings',
    'savePunchStateMappings', 'authenticateToken', 'ingestBatch', 'ingestEvent',
    'listEvents', 'listDays', 'listExceptions', 'resolveException', 'listAudit',
    'listScannerStations', 'saveScannerStation', 'listQrCredentials',
    'issueQrCredential', 'qrEncryptionReady', 'getActiveQrCredential', 'getQrCredentialToken',
    'revokeQrCredential', 'lookupQrCredential', 'scanQrCredential',
    'markManualOverride', 'purgeSimulationData'
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

$controller = file_get_contents(__DIR__ . '/../application/controllers/api/Biometric_v2.php');
biometric_contract_assert(strpos($controller, "'results' => array()") !== false, 'API errors must retain the results[] contract.');
biometric_contract_assert(strpos($controller, 'authenticateToken') !== false, 'V2 API must authenticate bearer tokens.');

$serviceSource = file_get_contents(__DIR__ . '/../application/libraries/Biometric_attendance_service.php');
biometric_contract_assert(strpos($serviceSource, 'PERIOD_ATTENDANCE_UNSUPPORTED') !== false, 'Period-wise schools must be guarded from daily student attendance projection.');
biometric_contract_assert(strpos($serviceSource, 'attendanceTypeExists') !== false, 'Settings must validate configured attendance type IDs.');
biometric_contract_assert(strpos($serviceSource, 'BIOMETRIC_QR_ENCRYPTION_KEY') !== false, 'QR issuance must depend on a server-side encryption key.');

$studentAttendanceModel = file_get_contents(__DIR__ . '/../application/models/Stuattendence_model.php');
$staffAttendanceModel = file_get_contents(__DIR__ . '/../application/models/Staffattendancemodel.php');
biometric_contract_assert(strpos($studentAttendanceModel, "markManualOverride('student_attendences'") !== false, 'Manual student attendance edits must lock a linked biometric day.');
biometric_contract_assert(strpos($staffAttendanceModel, "markManualOverride('staff_attendance'") !== false, 'Manual staff attendance edits must lock a linked biometric day.');

echo "biometric backend contract tests passed\n";
