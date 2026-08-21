<?php

require_once dirname(__DIR__, 3) . '/application/libraries/Idcard_design.php';

$design = new Idcard_design();
$passed = 0;
$failed = 0;

function check($condition, $message)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$message}\n";
}

function rejects(callable $callback, $message)
{
    try {
        $callback();
        check(false, $message);
    } catch (InvalidArgumentException $exception) {
        check(true, $message);
    }
}

$portrait = $design->dimensionsFromLegacy((object) array('enable_vertical_card' => 1));
check(abs($portrait['width_mm'] - 53.98) < 0.001 && abs($portrait['height_mm'] - 85.60) < 0.001, 'legacy portrait converts to 53.98 x 85.60 mm');

$landscape = $design->dimensionsFromLegacy((object) array('enable_vertical_card' => 0));
check(abs($landscape['width_mm'] - 85.60) < 0.001 && abs($landscape['height_mm'] - 53.98) < 0.001, 'legacy landscape converts to CR80 landscape');

$personal = $design->dimensionsFromLegacy((object) array(
    'enable_vertical_card' => 1,
    'card_unit' => 'in',
    'card_width' => 2.10,
    'card_height' => 3.30,
));
check(abs($personal['width_mm'] - 53.34) < 0.001 && abs($personal['height_mm'] - 83.82) < 0.001, 'personal-branch dimensions are preserved and converted from inches');

foreach (array('student', 'staff') as $subjectType) {
    $defaults = $design->defaultDocuments($subjectType, (object) array(
        'id' => 1,
        'title' => ucfirst($subjectType) . ' ID Card',
        'school_name' => 'SchoolLift Academy',
        'school_address' => 'Lagos, Nigeria',
        'header_color' => '#1f3c88',
        'enable_vertical_card' => 0,
    ));
    $front = $design->validateDocument($defaults['front'], $subjectType, 85.6, 53.98, 'front');
    $back = $design->validateDocument($defaults['back'], $subjectType, 85.6, 53.98, 'back');
    check(count($front['objects']) > 5 && count($back['objects']) > 2, $subjectType . ' default front/back documents validate');
    check(strlen($design->checksum($front, $back, $defaults['printSettings'])) === 64, $subjectType . ' design checksum is SHA-256');
}

$defaults = $design->defaultDocuments('student');
$badHtml = $defaults['front'];
$badHtml['objects'][1]['text'] = '<script>alert(1)</script>';
$badHtml['objects'][1]['binding'] = '';
rejects(function () use ($design, $badHtml) {
    $design->validateDocument($badHtml, 'student', 85.6, 53.98, 'front');
}, 'HTML is rejected from text objects');

$badBinding = $defaults['front'];
$badBinding['objects'][1]['binding'] = 'student.password';
rejects(function () use ($design, $badBinding) {
    $design->validateDocument($badBinding, 'student', 85.6, 53.98, 'front');
}, 'non-allowlisted dynamic fields are rejected');

$badImage = $defaults['front'];
$badImage['objects'][] = array(
    'id' => 'remote-image', 'type' => 'image', 'binding' => '', 'assetId' => 0,
    'url' => 'https://evil.example/image.png', 'x' => 1, 'y' => 1, 'width' => 10, 'height' => 10,
    'rotation' => 0, 'opacity' => 1, 'visible' => true, 'locked' => false,
);
rejects(function () use ($design, $badImage) {
    $design->validateDocument($badImage, 'student', 85.6, 53.98, 'front');
}, 'arbitrary remote image URLs are rejected');

$outside = $defaults['front'];
$outside['objects'][0]['x'] = 84;
$outside['objects'][0]['width'] = 10;
rejects(function () use ($design, $outside) {
    $design->validateDocument($outside, 'student', 85.6, 53.98, 'front');
}, 'objects outside the physical card boundary are rejected');

$validPrint = $design->validatePrintSettings(array(
    'paper' => 'a4', 'orientation' => 'portrait', 'marginMm' => 8, 'gapMm' => 4,
    'cropMarks' => true, 'duplex' => true, 'flip' => 'long-edge', 'dpi' => 300,
    'maxCardsPerPart' => 100,
));
check($validPrint['dpi'] === 300 && $validPrint['duplex'] === true, 'A4 duplex print settings validate');

rejects(function () use ($design) {
    $design->validatePrintSettings(array('paper' => 'letter'));
}, 'unsupported paper formats are rejected');

$grouped = $defaults['front'];
$grouped['objects'][0]['group'] = 'identity-block-1';
$validatedGrouped = $design->validateDocument($grouped, 'student', 85.6, 53.98, 'front');
check($validatedGrouped['objects'][0]['group'] === 'identity-block-1', 'safe editor group metadata is retained');

$unsafeGroup = $defaults['front'];
$unsafeGroup['objects'][0]['group'] = '<script>';
rejects(function () use ($design, $unsafeGroup) {
    $design->validateDocument($unsafeGroup, 'student', 85.6, 53.98, 'front');
}, 'unsafe editor group metadata is rejected');

$personalLayout = $design->defaultDocuments('student', (object) array(
    'id' => 9,
    'title' => 'Personal layout',
    'school_name' => 'School',
    'school_address' => 'Nigeria',
    'enable_vertical_card' => 0,
    'layout_json' => json_encode(array('photo' => array('x' => 10, 'y' => 20, 'w' => 25, 'h' => 40))),
));
$photo = null;
foreach ($personalLayout['front']['objects'] as $object) {
    if ($object['id'] === 'photo') { $photo = $object; break; }
}
check($photo && abs($photo['x'] - 8.56) < .001 && abs($photo['height'] - 21.592) < .001, 'personal percentage layout is converted without changing the source row');

$legacyBackground = $design->defaultDocuments('staff', (object) array(
    'enable_vertical_card' => 0, 'background' => 'uploads/background.png',
));
$validatedBackground = $design->validateDocument($legacyBackground['front'], 'staff', 85.6, 53.98, 'front');
check($validatedBackground['background']['type'] === 'binding' && $validatedBackground['background']['binding'] === 'school.background', 'legacy background becomes a safe same-origin binding');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
