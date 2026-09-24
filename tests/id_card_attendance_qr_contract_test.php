<?php

$root = dirname(__DIR__);
$files = array(
    'student controller' => 'application/controllers/admin/Studentidcard.php',
    'student generator' => 'application/controllers/admin/Generateidcard.php',
    'staff generator' => 'application/controllers/admin/Generatestaffidcard.php',
    'template model' => 'application/models/Student_id_card_model.php',
    'generator model' => 'application/models/Generateidcard_model.php',
    'create form' => 'application/views/admin/certificate/createidcard.php',
    'edit form' => 'application/views/admin/certificate/studentidcardedit.php',
    'legacy single card' => 'application/views/admin/certificate/studentidcard.php',
    'legacy batch cards' => 'application/views/admin/certificate/generatemultiple.php',
    'legacy preview' => 'application/views/admin/certificate/studentidcardpreview.php',
    'generation page' => 'application/views/admin/certificate/generateidcard.php',
    'studio runtime' => 'backend/idcard-studio/idcard-runtime.js',
    'shared renderer' => 'backend/idcard-studio/idcard-renderer.js',
    'legacy renderer' => 'backend/idcard-studio/idcard-legacy-qr.js',
);
$sources = array();
$assertions = 0;

function attendance_qr_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

foreach ($files as $label => $path) {
    $sources[$label] = file_get_contents($root . '/' . $path);
    attendance_qr_assert($sources[$label] !== false, ucfirst($label) . ' must be readable.');
}

foreach (array('create form', 'edit form') as $form) {
    attendance_qr_assert(
        strpos($sources[$form], 'name="is_active_attendance_qr"') !== false
            && strpos($sources[$form], 'Attendance QR') !== false,
        ucfirst($form) . ' must expose the attendance QR material switch.'
    );
}

attendance_qr_assert(
    substr_count($sources['student controller'], "'enable_attendance_qr' => \$attendance_qr") === 2,
    'Student template create and edit must persist the QR toggle.'
);
attendance_qr_assert(
    strpos($sources['template model'], "['_options']['attendance_qr']") !== false
        && strpos($sources['generator model'], "['_options']['attendance_qr']") !== false,
    'The legacy toggle must use existing layout metadata and be hydrated for generation.'
);

foreach (array('legacy single card', 'legacy batch cards', 'legacy preview') as $view) {
    attendance_qr_assert(
        strpos($sources[$view], 'data-attendance-qr=') !== false
            && strpos($sources[$view], 'qrcode-1.0.0.min.js') !== false
            && strpos($sources[$view], 'idcard-legacy-qr.js') !== false,
        ucfirst($view) . ' must render attendance QR locally.'
    );
}

attendance_qr_assert(
    strpos($sources['student generator'], "getActiveQrCredential(\$subjectType") !== false
        && strpos($sources['staff generator'], "getActiveQrCredential('staff'") !== false,
    'Student and staff Studio generation must load active trusted credentials.'
);
attendance_qr_assert(
    strpos($sources['studio runtime'], 'IDCARD_STUDIO_CREDENTIAL_WARNING') !== false
        && strpos($sources['studio runtime'], 'attendanceCredentialIssues') !== false
        && strpos($sources['studio runtime'], 'button.disabled = true') !== false,
    'Studio preview must visibly block output when a credential is missing.'
);
attendance_qr_assert(
    strpos($sources['shared renderer'], "String(value || 'UNISSUED-CREDENTIAL')") === false
        && strpos($sources['shared renderer'], "String(value || 'UNISSUED')") === false
        && strpos($sources['shared renderer'], "requiredLabel + ' could not be generated") !== false,
    'The shared renderer must never substitute a dummy credential or silently hide QR failure.'
);
attendance_qr_assert(
    strpos($sources['legacy renderer'], "new window.QRCode") !== false
        && strpos($sources['legacy renderer'], 'IDCARD_LEGACY_QR_ERROR') !== false
        && strpos($sources['generation page'], 'IDCARD_LEGACY_QR_READY') !== false,
    'Legacy batch printing must wait for successful local QR rendering.'
);
attendance_qr_assert(
    strpos($sources['legacy renderer'], 'http://') === false
        && strpos($sources['legacy renderer'], 'https://') === false,
    'Attendance QR generation must not send credential data to an external service.'
);

echo 'ID card attendance QR contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
