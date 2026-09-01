<?php

/** Focused unit coverage for external/support email value handling. */

define('BASEPATH', dirname(__DIR__) . '/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
define('ENVIRONMENT', 'testing');
$_SERVER['HTTP_HOST'] = 'apexstaracademy.com.ng';
require_once dirname(__DIR__) . '/application/helpers/support_email_helper.php';

$assertions = 0;

function support_email_helper_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

support_email_helper_assert(
    schoollift_support_normalize_email('  Office.User+Tag@Example.COM ') === 'office.user+tag@example.com',
    'A valid mailbox must be trimmed and normalized to lowercase.'
);
foreach (array('', 'not-an-email', 'Name <person@example.com>', "person@example.com\r\nBcc: attacker@example.com") as $invalidEmail) {
    support_email_helper_assert(
        schoollift_support_normalize_email($invalidEmail) === '',
        'Invalid or header-like mailbox input must be rejected.'
    );
}

$normalizedSubject = schoollift_support_normalize_subject("  Term report\r\nBcc: hidden@example.com\t ready  ");
support_email_helper_assert(
    $normalizedSubject === 'Term report Bcc: hidden@example.com ready'
        && strpos($normalizedSubject, "\r") === false
        && strpos($normalizedSubject, "\n") === false,
    'Subjects must be reduced to one trimmed line.'
);
support_email_helper_assert(
    strlen(schoollift_support_normalize_subject(str_repeat('x', 30), 12)) === 12,
    'Subject length limits must be enforced.'
);

support_email_helper_assert(
    schoollift_support_thread_subject('[Ticket #OLD-19] Admission enquiry', ' sl-2026-0042! ') === '[Ticket #SL-2026-0042] Admission enquiry',
    'Thread subjects must replace an old marker with one sanitized current marker.'
);
support_email_helper_assert(
    schoollift_support_thread_subject("\r\n", 'ABC-1') === '[Ticket #ABC-1] School message',
    'An empty subject must receive the safe fallback while retaining the ticket marker.'
);

support_email_helper_assert(
    schoollift_support_inbound_address('Admin', 'WWW.ApexStarAcademy.com.ng:443') === 'admin@apexstaracademy.com.ng',
    'The tenant inbox must normalize its local part, www prefix, host case, and port.'
);
support_email_helper_assert(
    schoollift_support_inbound_address('admin', 'apexstaracademyaso.com.ng') === 'admin@apexstaracademyaso.com.ng',
    'The second priority Apex domain must produce its expected tenant inbox.'
);
foreach (array('localhost', 'example.com/path', 'example.com@attacker.test', '-bad.example.com') as $invalidHost) {
    support_email_helper_assert(
        schoollift_support_inbound_address('admin', $invalidHost) === '',
        'Invalid or ambiguous hosts must not produce an inbound address.'
    );
}
support_email_helper_assert(
    schoollift_support_configured_inbound_address('admin', 'apexstaracademy.com.ng') === 'admin@apexstaracademy.com.ng',
    'A configured tenant domain must be accepted for Reply-To.'
);
support_email_helper_assert(
    schoollift_support_configured_inbound_address('admin', 'attacker.example') === '',
    'A syntactically valid but unconfigured Host header must not control Reply-To.'
);

support_email_helper_assert(
    schoollift_support_notification_recipient_allowed('Staff@Example.com', 'admin@example.com'),
    'A valid staff mailbox distinct from the inbound mailbox must be allowed.'
);
support_email_helper_assert(
    !schoollift_support_notification_recipient_allowed('ADMIN@example.com', 'admin@example.com'),
    'The inbound mailbox must never be used as its own notification destination.'
);
support_email_helper_assert(
    !schoollift_support_notification_recipient_allowed('invalid', 'admin@example.com'),
    'An invalid notification destination must be rejected.'
);

$notification = schoollift_support_notification_html(array(
    'ticket_number' => 'SL-42<script>',
    'requester_name' => 'Ada <script>alert(1)</script>',
    'requester_email' => 'ada@example.com',
    'subject' => 'Question <img src=x onerror=alert(1)>',
    'body_text' => 'CONFIDENTIAL_BODY_MUST_NOT_BE_FORWARDED',
    'body_html' => '<p>SECOND_SECRET_BODY</p>',
), 'https://apexstaracademy.com.ng/admin/support/view/42?a=1&b=2', 'Apex & Star');

foreach (array(
    'SL-42&lt;script&gt;',
    'Ada &lt;script&gt;alert(1)&lt;/script&gt;',
    'Question &lt;img src=x onerror=alert(1)&gt;',
    'Apex &amp; Star',
    'a=1&amp;b=2',
) as $escapedValue) {
    support_email_helper_assert(
        strpos($notification, $escapedValue) !== false,
        'Notification metadata and links must be HTML escaped: ' . $escapedValue
    );
}
foreach (array('CONFIDENTIAL_BODY_MUST_NOT_BE_FORWARDED', 'SECOND_SECRET_BODY', '<script>', '<img src=x') as $forbiddenValue) {
    support_email_helper_assert(
        strpos($notification, $forbiddenValue) === false,
        'Device alerts must omit message bodies and unsafe raw markup: ' . $forbiddenValue
    );
}
support_email_helper_assert(
    strpos($notification, 'Sign in to SchoolLift to read and reply') !== false,
    'The body-free notification must direct staff to the authenticated inbox.'
);

$notedBody = schoollift_support_append_ticket_note(
    '<p>Hello</p>',
    'SL-1<script>',
    'admin@example.com"><img src=x>'
);
support_email_helper_assert(
    strpos($notedBody, '<p>Hello</p>') !== false
        && strpos($notedBody, 'SL-1&lt;script&gt;') !== false
        && strpos($notedBody, 'admin@example.com&quot;&gt;&lt;img src=x&gt;') !== false
        && strpos($notedBody, '<img src=x>') === false,
    'The audit note must preserve the message while escaping ticket metadata.'
);

echo 'support_email_helper_test: OK (' . $assertions . ' assertions)' . PHP_EOL;
