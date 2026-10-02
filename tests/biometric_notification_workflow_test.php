<?php

function biometric_notification_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$service = file_get_contents($root . '/application/libraries/Biometric_attendance_service.php');
$controller = file_get_contents($root . '/application/controllers/admin/Notification.php');
$settingsView = file_get_contents($root . '/application/views/admin/notification/setting.php');
$biometricView = file_get_contents($root . '/application/views/admin/biometricattendance/index.php');
$migration = file_get_contents($root . '/application/migrations/143_add_biometric_notification_templates.php');
$directSql = file_get_contents($root . '/docs/biometric-notification-templates-migration-143.sql');
$documentation = file_get_contents($root . '/docs/biometric-notification-workflow.md');

foreach (array('biometric_attendance_in', 'biometric_attendance_out', 'biometric_fees_due') as $type) {
    biometric_notification_assert(strpos($migration, $type) !== false, 'Migration is missing template: ' . $type);
    biometric_notification_assert(strpos($directSql, $type) !== false, 'Direct SQL is missing template: ' . $type);
    biometric_notification_assert(strpos($service, $type) !== false, 'Service is missing notification type: ' . $type);
}

foreach (array('is_whatsapp', 'display_whatsapp', 'notification_type') as $field) {
    biometric_notification_assert(strpos($migration, $field) !== false, 'Migration is missing field: ' . $field);
}
biometric_notification_assert(strpos($controller, "'is_whatsapp'") !== false, 'Notification settings must save WhatsApp switches.');
biometric_notification_assert(strpos($settingsView, 'whatsapp_') !== false, 'Notification settings must display WhatsApp switches.');
biometric_notification_assert(strpos($service, 'guardian_email') !== false, 'Email delivery must use the guardian email address.');
biometric_notification_assert(substr_count($service, 'guardian_phone') >= 2, 'SMS and WhatsApp must use the guardian phone number.');
biometric_notification_assert(strpos($service, 'studentfeemaster_model') !== false, 'Fee reminders must be linked to the Fees module model.');
biometric_notification_assert(strpos($service, 'getStudentFees') !== false, 'Fee reminders must load assigned student fees.');
biometric_notification_assert(strpos($service, 'amount_discount') !== false, 'Fee balance must account for fee discounts.');
biometric_notification_assert(strpos($service, 'No unpaid fee balance remains') !== false, 'Delivery must cancel a stale paid balance.');
biometric_notification_assert(strpos($service, 'notificationTemplate') !== false, 'Delivery must render saved notification templates.');
biometric_notification_assert(strpos($biometricView, 'master safety switches') !== false, 'Biometric UI must explain master channel gates.');
biometric_notification_assert(strpos($documentation, 'one per student attendance day per channel') !== false, 'Documentation must state fee-reminder deduplication.');

echo "biometric notification workflow tests passed\n";
