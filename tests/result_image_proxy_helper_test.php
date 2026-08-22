<?php

require_once dirname(__DIR__) . '/helper/resultdownload_helper.php';

$assertions = 0;

function result_image_proxy_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

$base = 'https://schoollift.s3.us-east-2.amazonaws.com/';

result_image_proxy_assert(
    result_download_validate_image_proxy_url($base . 'uploads/school/logo.png')
        === $base . 'uploads/school/logo.png',
    'The SchoolLift S3 image host must be accepted.'
);
result_image_proxy_assert(
    result_download_validate_image_proxy_url(
        'HTTPS://SCHOOLLIFT.S3.US-EAST-2.AMAZONAWS.COM/uploads/photo.jpg?version=2'
    ) === $base . 'uploads/photo.jpg?version=2',
    'Host and scheme comparisons must be case-insensitive while preserving the asset path.'
);

foreach (array(
    '',
    'http://schoollift.s3.us-east-2.amazonaws.com/logo.png',
    'https://schoollift.s3.us-east-2.amazonaws.com.evil.test/logo.png',
    'https://evil.test/logo.png',
    'https://user@schoollift.s3.us-east-2.amazonaws.com/logo.png',
    'https://schoollift.s3.us-east-2.amazonaws.com:443/logo.png',
    'https://schoollift.s3.us-east-2.amazonaws.com/',
    'https://schoollift.s3.us-east-2.amazonaws.com/logo.png#fragment',
) as $invalidUrl) {
    result_image_proxy_assert(
        result_download_validate_image_proxy_url($invalidUrl) === null,
        'The image proxy must reject unsafe URL: ' . ($invalidUrl !== '' ? $invalidUrl : '(empty)')
    );
}

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    true
);
result_image_proxy_assert(
    result_download_detect_proxy_image_type($png) === 'image/png',
    'A real PNG payload must be accepted.'
);
result_image_proxy_assert(
    result_download_detect_proxy_image_type('<?php echo "not an image";') === null,
    'Non-image content must be rejected regardless of its remote filename.'
);
result_image_proxy_assert(
    result_download_detect_proxy_image_type('') === null,
    'Empty image responses must be rejected.'
);

echo 'result image proxy helper tests passed (' . $assertions . ' assertions)' . PHP_EOL;
