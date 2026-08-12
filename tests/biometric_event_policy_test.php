<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Biometric_event_policy.php';

function biometric_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$policy = new Biometric_event_policy();

biometric_assert($policy->sourceAllowedInMode('simulator', 'simulation'), 'Simulation must accept the manual Test Terminal.');
biometric_assert(!$policy->sourceAllowedInMode('simulator', 'live'), 'Live mode must reject simulator events.');
biometric_assert(!$policy->sourceAllowedInMode('gateway', 'simulation'), 'Simulation must reject physical gateway events.');
biometric_assert($policy->sourceAllowedInMode('gateway', 'shadow'), 'Shadow mode must accept physical gateway events.');
biometric_assert($policy->sourceAllowedInMode('gateway', 'live'), 'Live mode must accept physical gateway events.');
biometric_assert(!$policy->sourceAllowedInMode('qr', 'shadow'), 'Shadow mode must not accept QR attendance.');

$map = array('0' => 'IN', '1' => 'OUT');
biometric_assert($policy->directionFromState('0', $map) === 'IN', 'Punch state 0 must map to IN.');
biometric_assert($policy->directionFromState('1', $map) === 'OUT', 'Punch state 1 must map to OUT.');
biometric_assert($policy->directionFromState('9', $map) === null, 'Unknown punch states must be quarantinable.');

$now = new DateTimeImmutable('2026-08-11T12:00:00Z');
$timestamp = $policy->normalizeTimestamp('2026-08-11T07:31:00+01:00', 'Africa/Lagos', 30, $now);
biometric_assert($timestamp['valid'] === true, 'Offset ISO-8601 timestamp should be valid.');
biometric_assert($timestamp['local'] === '2026-08-11 07:31:00', 'Event should normalize into school local time.');
$ambiguous = $policy->normalizeTimestamp('2026-08-11 07:31:00', 'Africa/Lagos', 30, $now);
biometric_assert($ambiguous['valid'] === false && $ambiguous['code'] === 'TIMESTAMP_OFFSET_REQUIRED', 'Timestamp without an offset must be rejected.');

$day = $policy->aggregateDay(array(
    array('direction' => 'OUT', 'occurred_at_local' => '2026-08-11 15:05:00'),
    array('direction' => 'IN', 'occurred_at_local' => '2026-08-11 07:55:00'),
    array('direction' => 'IN', 'occurred_at_local' => '2026-08-11 08:10:00'),
    array('direction' => 'OUT', 'occurred_at_local' => '2026-08-11 14:55:00'),
), '08:00:00');
biometric_assert($day['first_in_at'] === '2026-08-11 07:55:00', 'Repeated IN must retain the earliest time.');
biometric_assert($day['last_out_at'] === '2026-08-11 15:05:00', 'Repeated OUT must retain the latest time.');
biometric_assert($day['attendance_status'] === 'present', 'Early first IN must be present.');
biometric_assert($day['duration_minutes'] === 430, 'Duration must use earliest IN and latest OUT.');

$missing = $policy->aggregateDay(array(
    array('direction' => 'IN', 'occurred_at_local' => '2026-08-11 08:10:00'),
), '08:00:00');
biometric_assert($missing['attendance_status'] === 'late' && $missing['missing_checkout'] === 1, 'IN-only day must remain missing checkout and can be late.');

$orphanOut = $policy->aggregateDay(array(
    array('direction' => 'OUT', 'occurred_at_local' => '2026-08-11 15:00:00'),
), '08:00:00');
biometric_assert($orphanOut['problem'] === 'OUT_WITHOUT_IN', 'OUT without IN must become an exception.');

$metadata = $policy->sanitizedMetadata(array(
    'provider_record_id' => '42',
    'work_code' => 'A',
    'face_template' => 'must-not-be-kept',
    'image' => 'must-not-be-kept',
));
biometric_assert(isset($metadata['provider_record_id']) && !isset($metadata['face_template']) && !isset($metadata['image']), 'Biometric material must not be retained in metadata.');

echo "biometric event policy tests passed\n";
