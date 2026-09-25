<?php

/** Source contracts for the non-support shared email inbox. */

$assertions = 0;

function shared_email_assert($condition, $message)
{
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

function shared_email_read($path)
{
    shared_email_assert(is_file($path), 'Missing implementation file: ' . $path);
    $source = file_get_contents($path);
    shared_email_assert($source !== false, 'Unreadable implementation file: ' . $path);
    return $source;
}

$root = dirname(__DIR__);
$migrationConfig = shared_email_read($root . '/application/config/migration.php');
$incomingConfig = shared_email_read($root . '/application/config/incoming_email.php');
$migration = shared_email_read($root . '/application/migrations/141_add_shared_email_inbox.php');
$model = shared_email_read($root . '/application/models/Emailconversation_model.php');
$controller = shared_email_read($root . '/application/controllers/admin/Emailinbox.php');
$mailsms = shared_email_read($root . '/application/controllers/admin/Mailsms.php');
$webhooks = shared_email_read($root . '/application/controllers/Webhooks.php');
$mailer = shared_email_read($root . '/application/libraries/Mailer.php');
$smtpSource = shared_email_read($root . '/application/libraries/PHPMailer/class.smtp.php');
$indexView = shared_email_read($root . '/application/views/admin/emailinbox/index.php');
$threadView = shared_email_read($root . '/application/views/admin/emailinbox/view.php');
$composeView = shared_email_read($root . '/application/views/admin/mailsms/compose.php');
$sidebar = shared_email_read($root . '/application/views/layout/sidebar.php');
$quickLinks = shared_email_read($root . '/application/views/layout/top_sidemenu.php');
$sql = shared_email_read($root . '/docs/all_school_database_migrations.sql');
$docs = shared_email_read($root . '/docs/ses-inbound-setup.md');

shared_email_assert(
    preg_match('/migration_version[\'\"]?\]\s*=\s*141\s*;/', $migrationConfig) === 1,
    'The configured migration target must be 141.'
);
foreach (array(
    'email_conversations',
    'email_conversation_messages',
    'email_conversation_incoming_unique',
    "'shared_email'",
    'Shared Email Inbox',
    "array('Admin', 'Super Admin', 'Head Teacher')",
    "'external_email'",
) as $contract) {
    shared_email_assert(strpos($migration, $contract) !== false, 'Migration 141 is missing: ' . $contract);
}
shared_email_assert(
    strpos($incomingConfig, "ses_inbound_recipient_local_part'] = 'admin'") !== false
        && strpos($incomingConfig, "ses_inbound_mail_local_part'] = 'mail'") !== false,
    'Support and shared correspondence must use distinct configured local parts.'
);

foreach (array(
    'createOutgoingConversation',
    'addOutgoingMessage',
    'processIncomingEmail',
    'buildReplyHeaders',
    'buildOutgoingMessageId',
    'findConversationForIncoming',
    'participantMatches',
    "getHeader(\$headers, 'in-reply-to')",
    "getHeader(\$headers, 'references')",
    "'unread_count', 'unread_count + 1'",
) as $contract) {
    shared_email_assert(strpos($model, $contract) !== false, 'Shared conversation model is missing: ' . $contract);
}
shared_email_assert(
    strpos($model, 'hash_equals($expected, $actual)') !== false
        && strpos($model, "'status' => 'correspondence'") === false
        && strpos($model, "markIncomingEmail(\$incomingEmailId, 'correspondence'") !== false,
    'Inbound shared replies must bind the participant identity and be marked as correspondence.'
);

foreach (array(
    "hasPrivilege('shared_email', 'can_view')",
    "hasPrivilege('shared_email', 'can_add')",
    'requireActionPost()',
    "post('shared_email_action_csrf')",
    "'reply_to_email' => \$inboundEmail",
    "'In-Reply-To'",
    "'References'",
    "'attachment_names' => \$attachments['names']",
    'get_last_message_id()',
    '$deliveredMessageId',
) as $contract) {
    shared_email_assert(strpos($controller, $contract) !== false, 'Shared inbox controller is missing: ' . $contract);
}
shared_email_assert(
    strpos($controller, "'X-SchoolLift-Ticket'") === false
        && strpos($controller, 'supportticket_model') === false,
    'Shared Email replies must not enter or imitate Support Tickets.'
);

foreach (array(
    'createOutgoingConversation',
    'buildOutgoingMessageId',
    "'reply_to_email' => \$inboundEmail",
    'addOutgoingMessage',
    "'delivery_status' => \$sent ? 'sent' : 'failed'",
    "'attachment_names' => \$attachments['names']",
    'get_last_message_id()',
    '$deliveredMessageId',
    "redirect('admin/emailinbox/view/'",
) as $contract) {
    shared_email_assert(strpos($mailsms, $contract) !== false, 'External send integration is missing: ' . $contract);
}
shared_email_assert(
    strpos($mailsms, "'X-SchoolLift-Ticket'") === false
        && strpos($mailsms, 'addOutgoingReply') === false,
    'External sends must remain ordinary correspondence, not Support replies.'
);
shared_email_assert(
    strpos($smtpSource, "'Amazon_SES' => '/[0-9]{3} Ok (.*)/i'") !== false
        && strpos($smtpSource, '$this->recordLastTransactionID();') !== false
        && strpos($mailer, 'getLastTransactionID') !== false
        && strpos($mailer, '@email.amazonses.com>') !== false,
    'The mail transport must store the Message-ID that SES actually assigns.'
);

foreach (array(
    "'support' => \$recipient_local_part",
    "'shared_email' => \$mail_recipient_local_part",
    "\$school_route['channel'] === 'shared_email'",
    'emailconversation_model->processIncomingEmail(',
    "table_exists('email_conversations')",
    "table_exists('email_conversation_messages')",
    "'email_conversation_id' => \$email_conversation_id",
) as $contract) {
    shared_email_assert(strpos($webhooks, $contract) !== false, 'SES shared-email routing is missing: ' . $contract);
}
shared_email_assert(
    strpos($webhooks, 'snsmessagevalidator->isValid($sns_payload)')
        < strpos($webhooks, 'resolveSchoolRoute($message_data'),
    'SNS signatures must be verified before shared-email tenant routing.'
);

foreach (array('All', 'Inbox', 'Sent', 'Unread', 'New External Email', 'html_escape') as $contract) {
    shared_email_assert(strpos($indexView, $contract) !== false, 'Shared inbox view is missing: ' . $contract);
}
foreach (array(
    'name="shared_email_action_csrf"',
    'name="email_attachment[]"',
    'multiple="multiple"',
    'html_escape($body)',
    'Send Reply',
) as $contract) {
    shared_email_assert(strpos($threadView, $contract) !== false, 'Shared thread view is missing: ' . $contract);
}
shared_email_assert(
    strpos($composeView, 'The message and future replies are kept in Shared Email.') !== false
        && strpos($composeView, '$external_email_reply_address') !== false,
    'External compose must explain where replies and history are stored.'
);
shared_email_assert(
    strpos($sidebar, "hasPrivilege('shared_email', 'can_view')") !== false
        && strpos($sidebar, 'admin/emailinbox') !== false
        && strpos($quickLinks, "hasPrivilege('shared_email', 'can_view')") !== false
        && strpos($quickLinks, 'admin/emailinbox') !== false,
    'Authorized staff need Shared Email navigation in both menus.'
);

foreach (array(
    '126 through 141',
    '141_add_shared_email_inbox.php',
    'CREATE TABLE IF NOT EXISTS `email_conversations`',
    'CREATE TABLE IF NOT EXISTS `email_conversation_messages`',
    "'shared_email'",
    "UNION ALL SELECT 'Head Teacher', 'shared_email'",
    "UNION ALL SELECT 'Head Teacher', 'external_email'",
) as $contract) {
    shared_email_assert(strpos($sql, $contract) !== false, 'Consolidated SQL is missing: ' . $contract);
}
foreach (array(
    'mail@SCHOOL-DOMAIN',
    '/admin/emailinbox',
    'Admin, Super Admin, and Head Teacher',
    'rather than creating a Support Ticket',
    'Message-ID',
    'In-Reply-To',
    'References',
    'does not provide AWS webmail',
    'replaces submitted Message-ID headers',
    '250 Ok',
) as $contract) {
    shared_email_assert(stripos($docs, $contract) !== false, 'Shared-email deployment documentation is missing: ' . $contract);
}

if (!class_exists('SMTP', false)) {
    require_once $root . '/application/libraries/PHPMailer/class.smtp.php';
}
$smtp = new SMTP();
$lastReply = new ReflectionProperty('SMTP', 'last_reply');
$lastReply->setAccessible(true);
$lastReply->setValue($smtp, '250 Ok 01010160d7de98d8-example-000000');
$captureTransaction = new ReflectionMethod('SMTP', 'recordLastTransactionID');
$captureTransaction->setAccessible(true);
$captureTransaction->invoke($smtp);
$lastReply->setValue($smtp, '221 Bye');
shared_email_assert(
    $smtp->getLastTransactionID() === '01010160d7de98d8-example-000000',
    'The SES transaction ID must survive the later SMTP QUIT response.'
);

echo 'shared_email_contract_test: OK (' . $assertions . ' assertions)' . PHP_EOL;
