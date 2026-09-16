<?php

function base_url($path = '')
{
    return 'https://tenant.example/' . ltrim((string) $path, '/');
}

$_SERVER['HTTP_HOST'] = 'hameedacademy.com.ng';
require_once dirname(__DIR__) . '/application/helpers/s3_helper.php';

$assertions = 0;

function school_media_assert_same($expected, $actual, $message)
{
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . $expected . PHP_EOL . 'Actual:   ' . $actual . PHP_EOL);
        exit(1);
    }
}

function school_media_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$s3 = 'https://schoollift.s3.us-east-2.amazonaws.com/';

$jpegFixture = tempnam(sys_get_temp_dir(), 'school-media-');
file_put_contents($jpegFixture, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAEf/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q=='));
school_media_assert_same(
    'image/jpeg',
    school_upload_content_type($jpegFixture, 'png'),
    'Upload metadata must follow the actual image bytes when the extension is incorrect.'
);
unlink($jpegFixture);
school_media_assert_same(
    'image/png',
    school_upload_content_type('/path/that/does/not/exist', '.png'),
    'Upload metadata must fall back to a normalized extension when byte detection is unavailable.'
);
$_SERVER['HTTP_HOST'] = 'www.HameedAcademy.com.ng:443';
school_media_assert_same(
    'hameedacademy',
    school_asset_tenant_prefix(),
    'Tenant prefixes must normalize www, letter case, and development ports consistently.'
);
$_SERVER['HTTP_HOST'] = 'hameedacademy.com.ng';

school_media_assert_same(
    $s3 . 'uploads/staff_images/hameedacademy.10.jpg',
    get_school_asset_url('hameedacademy.10.jpg', 'uploads/staff_images'),
    'Filename-only staff photos must resolve to their S3 upload directory.'
);
school_media_assert_same(
    $s3 . 'uploads/staff_images/hameedacademy.10.jpg',
    get_school_asset_url('uploads/staff_images/hameedacademy.10.jpg', 'uploads/staff_images'),
    'Complete S3 keys must not receive a duplicate upload directory.'
);
school_media_assert_same(
    $s3 . 'uploads/staff_images/hameedacademy.10.jpg',
    get_school_asset_url('/uploads/staff_images/hameedacademy.10.jpg'),
    'Leading slashes on S3 keys must be normalized.'
);
school_media_assert_same(
    $s3 . 'uploads/staff_images/hameedacademy.10.jpg',
    get_school_asset_url('hameedacademy.10.jpg', 'uploads\\staff_images'),
    'Windows-style directory separators must be normalized.'
);
school_media_assert_same(
    'https://images.example/staff/photo.jpg',
    get_school_asset_url('https://images.example/staff/photo.jpg', 'uploads/staff_images'),
    'Complete HTTPS URLs must remain unchanged.'
);
school_media_assert_same(
    'https://tenant.example/theme/images/photo.jpg',
    get_school_asset_url('photo.jpg', 'theme/images'),
    'Non-upload directories must continue to resolve on the tenant origin.'
);
school_media_assert_same(
    'https://tenant.example/uploads/staff_images/photo.jpg',
    get_school_asset_url('../photo.jpg', 'uploads/staff_images'),
    'Filename-only resolution must discard parent-directory traversal.'
);
school_media_assert_same(
    'https://tenant.example/uploads/staff_images/12.jpg',
    get_school_asset_url('12.jpg', 'uploads/staff_images'),
    'Unprefixed legacy filenames must remain on the tenant origin.'
);
school_media_assert_same(
    'https://tenant.example/uploads/staff_images/another-school.10.jpg',
    get_school_asset_url('another-school.10.jpg', 'uploads/staff_images'),
    'A filename belonging to another tenant must not be redirected to this tenant\'s S3 key.'
);
school_media_assert_same(
    $s3 . 'uploads/staff_images/hameedacademy.10.jpg',
    get_school_media_url('hameedacademy.10.jpg', 'uploads/staff_images'),
    'The media URL wrapper must share the same storage-aware behavior.'
);

foreach (array(
    'active staff directory' => 'application/views/admin/staff/staffsearch.php',
    'disabled staff directory' => 'application/views/admin/staff/disablestaff.php',
    'staff profile' => 'application/views/admin/staff/staffprofile.php',
    'admin header' => 'application/views/layout/header.php',
) as $surface => $relativePath) {
    $source = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
    school_media_assert(
        strpos($source, "get_school_asset_url(") !== false,
        ucfirst($surface) . ' must use the storage-aware asset resolver.'
    );
    school_media_assert(
        strpos($source, 'schoollift.s3.us-east-2.amazonaws.com/uploads/staff_images') === false,
        ucfirst($surface) . ' must not construct staff S3 URLs directly.'
    );
}

echo 'School media URL tests passed (' . $assertions . ' assertions)' . PHP_EOL;
