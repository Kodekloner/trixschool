<?php

$root = dirname(__DIR__);
$studentGenerator = file_get_contents($root . '/application/views/admin/certificate/generateidcard.php');
$studentCards = file_get_contents($root . '/application/views/admin/certificate/generatemultiple.php');
$singleStudentCard = file_get_contents($root . '/application/views/admin/certificate/studentidcard.php');
$staffGenerator = file_get_contents($root . '/application/views/admin/generatestaffidcard/generatestaffidcardview.php');
$staffCards = file_get_contents($root . '/application/views/admin/generatestaffidcard/generatemultiplestaffidcard.php');
$certificateGenerator = file_get_contents($root . '/application/views/admin/certificate/generatecertificate.php');
$certificates = file_get_contents($root . '/application/views/admin/certificate/printcertificate.php');
$assertions = 0;

function id_card_print_image_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

foreach (array(
    'student generator' => $studentGenerator,
    'student cards' => $studentCards,
    'single student card' => $singleStudentCard,
    'staff generator' => $staffGenerator,
    'staff cards' => $staffCards,
    'certificate generator' => $certificateGenerator,
    'certificates' => $certificates,
) as $name => $source) {
    id_card_print_image_assert($source !== false, ucfirst($name) . ' view must be readable.');
}

foreach (array(
    'student card' => $studentCards,
    'staff card' => $staffCards,
    'certificate' => $certificates,
) as $name => $source) {
    id_card_print_image_assert(
        strpos($source, 'get_school_asset_url') !== false,
        ucfirst($name) . ' images must use the shared S3/legacy asset resolver.'
    );
    id_card_print_image_assert(
        strpos($source, 'onerror="this.onerror=null;this.src=') !== false,
        ucfirst($name) . ' photos must fall back safely when a stored photo cannot load.'
    );
}

id_card_print_image_assert(
    strpos($studentCards, "get_school_asset_url(\$id_card[0]->background, 'uploads/student_id_card/background')") !== false,
    'Student card backgrounds must support both S3 keys and legacy filenames.'
);
id_card_print_image_assert(
    substr_count($studentCards, 'htmlspecialchars($student_photo_url') === 2
        && strpos($studentCards, 'https://demo.smart-school.in/uploads/student_images/no_image.png') === false,
    'Portrait and landscape student cards must render the selected student photo.'
);
id_card_print_image_assert(
    strpos($staffCards, "get_school_asset_url(\$id_card[0]->background, 'uploads/staff_id_card/background')") !== false,
    'Staff card backgrounds must not prepend a local path to a complete S3 key.'
);
id_card_print_image_assert(
    substr_count($staffCards, 'htmlspecialchars($staff_photo_url') === 2
        && strpos($staffCards, 'https://demo.smart-school.in/uploads/student_images/no_image.png') === false,
    'Portrait and landscape staff cards must render the selected staff photo.'
);
id_card_print_image_assert(
    strpos($singleStudentCard, "get_school_asset_url(\$idcardlist[0]->sign_image, 'uploads/student_id_card/signature')") !== false,
    'Individual student cards must render the selected template signature.'
);
id_card_print_image_assert(
    strpos($certificates, "get_school_asset_url(\$certificate[0]->background_image, 'uploads/certificate')") !== false
        && strpos($certificates, 'htmlspecialchars($student_photo_url') !== false,
    'Certificates must resolve both their background and student photo through storage-aware URLs.'
);

foreach (array(
    'student ID cards' => $studentGenerator,
    'staff ID cards' => $staffGenerator,
    'certificates' => $certificateGenerator,
) as $name => $source) {
    id_card_print_image_assert(
        strpos($source, 'child.document.images') !== false
            && strpos($source, 'return image.complete;') !== false
            && strpos($source, "child.document.readyState === 'complete' && imagesReady") !== false,
        'Selected ' . $name . ' must wait for iframe images before opening the print dialog.'
    );
}

echo 'ID card and certificate print image contract tests passed (' . $assertions . ' assertions)' . PHP_EOL;
