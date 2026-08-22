<?php

/** Unit coverage for the dependency-free Amazon SNS signature validator. */

define('BASEPATH', dirname(__DIR__) . '/system/');
require_once dirname(__DIR__) . '/application/libraries/Snsmessagevalidator.php';

$assertions = 0;

function sns_validator_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

sns_validator_assert(extension_loaded('openssl'), 'The OpenSSL extension is required for this test.');

$privateKey = openssl_pkey_new(array(
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
));
sns_validator_assert($privateKey !== false, 'A temporary RSA key must be generated.');

$certificateRequest = openssl_csr_new(array('commonName' => 'sns.us-east-2.amazonaws.com'), $privateKey);
$certificateResource = $certificateRequest !== false
    ? openssl_csr_sign($certificateRequest, null, $privateKey, 1)
    : false;
$certificate = '';
if ($certificateResource !== false) {
    openssl_x509_export($certificateResource, $certificate);
}
sns_validator_assert($certificate !== '', 'A temporary certificate must be generated.');

$validator = new Snsmessagevalidator(array(
    'certificate_fetcher' => function ($url) use ($certificate) {
        return $certificate;
    },
));

$notification = array(
    'Type' => 'Notification',
    'MessageId' => '11111111-2222-3333-4444-555555555555',
    'TopicArn' => 'arn:aws:sns:us-east-2:123456789012:schoollift-support-inbound',
    'Subject' => 'SES Email Receipt Notification',
    'Message' => '{"notificationType":"Received"}',
    'Timestamp' => '2026-08-22T12:00:00.000Z',
    'SignatureVersion' => '1',
    'SigningCertURL' => 'https://sns.us-east-2.amazonaws.com/SimpleNotificationService-test.pem',
);

$expectedCanonical = "Message\n" . $notification['Message'] . "\n"
    . "MessageId\n" . $notification['MessageId'] . "\n"
    . "Subject\n" . $notification['Subject'] . "\n"
    . "Timestamp\n" . $notification['Timestamp'] . "\n"
    . "TopicArn\n" . $notification['TopicArn'] . "\n"
    . "Type\n" . $notification['Type'] . "\n";
sns_validator_assert(
    $validator->getStringToSign($notification) === $expectedCanonical,
    'Notification fields must use the canonical AWS ordering.'
);

$signature = '';
sns_validator_assert(
    openssl_sign($validator->getStringToSign($notification), $signature, $privateKey, OPENSSL_ALGO_SHA1),
    'The notification fixture must be signed.'
);
$notification['Signature'] = base64_encode($signature);
sns_validator_assert($validator->isValid($notification), 'A valid SignatureVersion 1 message must pass.');

$tampered = $notification;
$tampered['Message'] = '{"notificationType":"AttackerChangedIt"}';
sns_validator_assert(!$validator->isValid($tampered), 'A changed signed message must fail.');
sns_validator_assert(
    strpos($validator->getLastError(), 'signature is invalid') !== false,
    'A failed signature must expose a safe diagnostic.'
);

$confirmation = array(
    'Type' => 'SubscriptionConfirmation',
    'MessageId' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    'Token' => 'signed-token',
    'TopicArn' => $notification['TopicArn'],
    'Message' => 'You have chosen to subscribe.',
    'SubscribeURL' => 'https://sns.us-east-2.amazonaws.com/?Action=ConfirmSubscription&TopicArn=' . rawurlencode($notification['TopicArn']) . '&Token=signed-token',
    'Timestamp' => '2026-08-22T12:01:00.000Z',
    'SignatureVersion' => '2',
    'SigningCertURL' => $notification['SigningCertURL'],
);
$confirmationSignature = '';
sns_validator_assert(
    openssl_sign($validator->getStringToSign($confirmation), $confirmationSignature, $privateKey, OPENSSL_ALGO_SHA256),
    'The confirmation fixture must be signed.'
);
$confirmation['Signature'] = base64_encode($confirmationSignature);
sns_validator_assert($validator->isValid($confirmation), 'A valid SignatureVersion 2 confirmation must pass.');

foreach (array(
    'http://sns.us-east-2.amazonaws.com/cert.pem',
    'https://sns.us-east-2.amazonaws.com.evil.test/cert.pem',
    'https://user@sns.us-east-2.amazonaws.com/cert.pem',
    'https://sns.us-east-2.amazonaws.com:8443/cert.pem',
    'https://sns.us-east-2.amazonaws.com/cert.pem?redirect=https://evil.test',
    'https://127.0.0.1/cert.pem',
) as $untrustedUrl) {
    sns_validator_assert(
        !$validator->isTrustedSnsUrl($untrustedUrl, true),
        'Untrusted certificate URL must be rejected: ' . $untrustedUrl
    );
}

sns_validator_assert(
    $validator->isTrustedSnsUrl('https://sns.eu-west-1.amazonaws.com/ConfirmSubscription?Action=ConfirmSubscription'),
    'A regional Amazon SNS confirmation endpoint must be allowed.'
);

$missingField = $notification;
unset($missingField['TopicArn']);
sns_validator_assert(!$validator->isValid($missingField), 'A signed-envelope field may not be omitted.');

$badVersion = $notification;
$badVersion['SignatureVersion'] = '3';
sns_validator_assert(!$validator->isValid($badVersion), 'Unknown signature versions must be rejected.');

echo 'sns_message_validator_test: OK (' . $assertions . ' assertions)' . PHP_EOL;
