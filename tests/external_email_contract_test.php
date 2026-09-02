<?php

/** Source and deployment contracts for external email and device alerts. */

$assertions = 0;

function external_email_contract_assert($condition, $message)
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}

function external_email_contract_read($path)
{
    external_email_contract_assert(is_file($path), 'Missing implementation file: ' . $path);
    $source = file_get_contents($path);
    external_email_contract_assert($source !== false, 'Implementation file is unreadable: ' . $path);

    return $source;
}

function external_email_contract_method($source, $visibility, $name)
{
    $pattern = '/\n\s*' . preg_quote($visibility, '/') . '\s+function\s+'
        . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{(.*?)(?=\n\s*(?:public|protected|private)\s+function\s+|\n\})/s';
    if (!preg_match($pattern, $source, $matches)) {
        return '';
    }

    return $matches[0];
}

$root = dirname(__DIR__);
$mailsms = external_email_contract_read($root . '/application/controllers/admin/Mailsms.php');
$support = external_email_contract_read($root . '/application/controllers/admin/Support.php');
$webhooks = external_email_contract_read($root . '/application/controllers/Webhooks.php');
$cron = external_email_contract_read($root . '/application/controllers/Cron.php');
$ticketModel = external_email_contract_read($root . '/application/models/Supportticket_model.php');
$notificationModel = external_email_contract_read($root . '/application/models/Supportnotification_model.php');
$notifier = external_email_contract_read($root . '/application/libraries/Supportemailnotifier.php');
$mailer = external_email_contract_read($root . '/application/libraries/Mailer.php');
$snsValidator = external_email_contract_read($root . '/application/libraries/Snsmessagevalidator.php');
$helper = external_email_contract_read($root . '/application/helpers/support_email_helper.php');
$composeView = external_email_contract_read($root . '/application/views/admin/mailsms/compose.php');
$supportView = external_email_contract_read($root . '/application/views/admin/support/index.php');
$supportDetailView = external_email_contract_read($root . '/application/views/admin/support/view.php');
$migration = external_email_contract_read($root . '/application/migrations/134_add_external_email_notifications.php');
$standaloneSql = external_email_contract_read($root . '/docs/external_email_notifications_migration.sql');
$consolidatedSql = external_email_contract_read($root . '/docs/all_school_database_migrations.sql');
$setupDocs = external_email_contract_read($root . '/docs/ses-inbound-setup.md');
$migrationConfig = external_email_contract_read($root . '/application/config/migration.php');
$incomingConfig = external_email_contract_read($root . '/application/config/incoming_email.php');

$sendExternal = external_email_contract_method($mailsms, 'public', 'send_external');
$externalPostGuard = external_email_contract_method($mailsms, 'private', 'requireExternalEmailPost');
external_email_contract_assert($sendExternal !== '', 'The external-email action must exist.');
external_email_contract_assert(
    strpos($sendExternal, "hasPrivilege('external_email', 'can_add')") !== false
        && strpos($sendExternal, 'access_denied()') !== false,
    'External compose must enforce the dedicated add permission server-side.'
);
external_email_contract_assert(
    strpos($sendExternal, 'requireExternalEmailPost()') !== false
        && strpos($externalPostGuard, "method(true) !== 'POST'") !== false
        && strpos($externalPostGuard, "post('external_email_csrf')") !== false
        && strpos($externalPostGuard, 'hash_equals($expected, $provided)') !== false,
    'External sending must be POST-only and protected by a scoped CSRF token.'
);
external_email_contract_assert(
    strpos($externalPostGuard, 'set_userdata($this->externalEmailTokenSessionKey') !== false,
    'The external-email CSRF token must rotate after successful validation.'
);

foreach (array(
    "name=\"external_email_csrf\"",
    "name=\"external_email\"",
    "name=\"external_name\"",
    "name=\"external_subject\"",
    "name=\"external_message\"",
) as $formContract) {
    external_email_contract_assert(
        strpos($composeView, $formContract) !== false,
        'The external compose form is missing: ' . $formContract
    );
}
external_email_contract_assert(
    strpos($sendExternal, 'schoollift_support_normalize_email') !== false
        && strpos($sendExternal, 'hash_equals($inboundAddress, $recipientEmail)') !== false
        && strpos($sendExternal, '$this->security->xss_clean($rawBody)') !== false,
    'The action must validate the address, reject an inbound-mail loop, and sanitize HTML.'
);

external_email_contract_assert(
    strpos($sendExternal, 'createOutgoingConversation') !== false
        && strpos($sendExternal, 'addOutgoingReply') !== false
        && strpos($ticketModel, "'source'                   => 'external_email'") !== false,
    'Every external send attempt must create an audited Support Ticket conversation and message.'
);
foreach (array("'delivery_status' => \$sent ? 'sent' : 'failed'", "'error_message' => \$sent ? null : \$error") as $deliveryContract) {
    external_email_contract_assert(
        strpos($sendExternal, $deliveryContract) !== false,
        'Both successful and failed delivery attempts must be recorded: ' . $deliveryContract
    );
}

external_email_contract_assert(
    strpos($sendExternal, 'schoollift_support_thread_subject') !== false
        && strpos($sendExternal, "['reply_to_email'] =") !== false
        && strpos($sendExternal, "'X-SchoolLift-Ticket'") !== false,
    'New external conversations must use a ticket subject marker, inbound Reply-To, and ticket header.'
);
$supportReply = external_email_contract_method($support, 'public', 'reply');
external_email_contract_assert(
    strpos($supportReply, "'reply_to_email'") !== false
        && strpos($supportReply, "'In-Reply-To'") !== false
        && strpos($supportReply, "'References'") !== false
        && strpos($supportReply, "'X-SchoolLift-Ticket'") !== false
        && strpos($supportReply, 'addOutgoingReply') !== false,
    'Existing Support replies must preserve Reply-To and email-thread headers and remain audited.'
);
external_email_contract_assert(
    strpos($mailer, 'SMTPDebug  = 0') !== false
        && strpos($mailer, 'SMTPDebug  = 2') === false
        && strpos($mailer, 'Mailer SMTP transcript:') === false,
    'SMTP DATA, bodies, and attachments must not be written to application debug logs.'
);
external_email_contract_assert(
    strpos($mailer, "(int) trim((string) \$this->CI->mail_config->smtp_port)") !== false,
    'SMTP ports must be normalized so legacy whitespace does not break Apex delivery.'
);
external_email_contract_assert(
    strpos($mailer, "isset(\$options['smtp_timeout'])") !== false
        && strpos($mailer, '$mail->Timeout') !== false
        && strpos($notifier, "'smtp_timeout' => 10") !== false,
    'Queued alerts must use bounded SMTP connection and command timeouts.'
);

$individualSend = external_email_contract_method($mailsms, 'public', 'send_individual');
$standardEmailGuard = external_email_contract_method($mailsms, 'private', 'requireStandardEmailPost');
external_email_contract_assert(
    strpos($individualSend, "hasPrivilege('email', 'can_view')") !== false
        && strpos($individualSend, 'required|in_list[email]') !== false
        && strpos($individualSend, 'foreach ((array) $userlisting') !== false
        && strpos($individualSend, 'resolveInternalEmailRecipient') !== false
        && strpos($individualSend, 'requireStandardEmailPost()') !== false,
    'The legacy individual form must enforce email permission and CSRF, accept keyed JSON, and re-resolve internal recipients.'
);
foreach (array('send_birthday', 'send_group', 'send_class') as $legacyActionName) {
    $legacyAction = external_email_contract_method($mailsms, 'public', $legacyActionName);
    external_email_contract_assert(
        strpos($legacyAction, "hasPrivilege('email', 'can_view')") !== false
            && strpos($legacyAction, 'required|in_list[email]') !== false
            && strpos($legacyAction, 'requireStandardEmailPost()') !== false,
        $legacyActionName . ' must enforce the standard email permission, transport, and scoped CSRF token.'
    );
}
external_email_contract_assert(
    strpos($standardEmailGuard, "method(true) !== 'POST'") !== false
        && strpos($standardEmailGuard, "post('standard_email_csrf')") !== false
        && strpos($standardEmailGuard, 'hash_equals($expected, $provided)') !== false
        && substr_count($composeView, 'name="standard_email_csrf"') === 4,
    'Every legacy email mutation must be POST-only and submit the standard scoped CSRF token.'
);

$findTicket = external_email_contract_method($ticketModel, 'protected', 'findTicketForIncoming');
external_email_contract_assert(
    strpos($findTicket, '$reference_ids') !== false
        && strpos($findTicket, '$ticket_number') !== false
        && strpos($findTicket, '$reference_ids') < strpos($findTicket, '$ticket_number')
        && substr_count($findTicket, 'ticketRequesterMatches') >= 3,
    'Inbound threading must prioritize RFC references and require the original requester for every match.'
);
external_email_contract_assert(
    strpos($ticketModel, '$is_new_ticket') !== false
        && strpos($ticketModel, 'Never replace an') !== false,
    'An inbound reply must never overwrite an existing ticket requester identity.'
);

$notificationAction = external_email_contract_method($support, 'public', 'notification');
$notificationGuard = external_email_contract_method($support, 'protected', 'requireNotificationPost');
external_email_contract_assert(
    strpos($notificationAction, "hasPrivilege('support_ticket', 'can_view')") !== false
        && strpos($notificationAction, 'requireNotificationPost()') !== false
        && strpos($notificationGuard, "method(true) !== 'POST'") !== false
        && strpos($notificationGuard, "post('support_notification_csrf')") !== false
        && strpos($notificationGuard, 'hash_equals($expected, $provided)') !== false,
    'Only Support viewers may change their own preference through a scoped POST/CSRF action.'
);
external_email_contract_assert(
    strpos($notificationAction, '$this->customlib->getStaffID()') !== false
        && strpos($notificationAction, '$this->staff_model->getAll($staff_id)') !== false
        && strpos($notificationAction, "post('email'") === false
        && strpos($notificationAction, 'setForStaff($staff_id, $notification_email, $enabled)') !== false,
    'The alert destination must come from the authenticated staff profile, not request input.'
);
external_email_contract_assert(
    strpos($supportView, "site_url('admin/support/notification')") !== false
        && strpos($supportView, 'support_notification_csrf') !== false,
    'The Support inbox must expose the protected device-alert preference control.'
);
$supportActionGuard = external_email_contract_method($support, 'protected', 'requireSupportActionPost');
external_email_contract_assert(
    strpos($supportActionGuard, "method(true) !== 'POST'") !== false
        && strpos($supportActionGuard, "post('support_action_csrf')") !== false
        && strpos($supportActionGuard, 'hash_equals($expected, $provided)') !== false
        && substr_count($support, 'requireSupportActionPost()') >= 3,
    'Support reply, update, and delete actions must be POST-only with a scoped CSRF token.'
);
external_email_contract_assert(
    substr_count($supportDetailView, 'name="support_action_csrf"') === 2
        && strpos($supportView, "<form method=\"post\" action=\"<?php echo site_url('admin/support/delete/") !== false
        && strpos($supportView, 'name="support_action_csrf"') !== false,
    'Every Support mutation form, including delete, must submit the scoped action token.'
);

$notifyPosition = strpos($webhooks, 'queueIncoming(');
external_email_contract_assert(
    $notifyPosition !== false
        && strpos($webhooks, 'if (!empty($support_ticket_id))') !== false
        && strpos($webhooks, "'failed' => (int) \$notification_result['failed']") === false
        && strpos($webhooks, "!empty(\$notification_result['failed'])") !== false,
    'Idempotent alert reservation must be retried for any resolved inbound ticket, and queue failures must not be hidden.'
);
external_email_contract_assert(
    preg_match('/catch \((?:Exception|Throwable) \$exception\)/', $webhooks) === 1
        && strpos($webhooks, 'Support email alert queue temporarily unavailable') !== false
        && strpos($webhooks, '), 503)') !== false,
    'Alert reservation failures must return a retryable response after the inbound message is safely stored.'
);

foreach (array(
    "permission_category.short_code = 'support_ticket'",
    "roles_permissions.can_view",
    "roles.name', 'Super Admin'",
    "->where('staff.is_active', 1)",
    "->where(\$this->table . '.is_active', 1)",
) as $permissionFilter) {
    external_email_contract_assert(
        strpos($notificationModel, $permissionFilter) !== false,
        'Alert recipients must still satisfy the current Support permission filter: ' . $permissionFilter
    );
}
external_email_contract_assert(
    strpos($notifier, 'schoollift_support_notification_recipient_allowed') !== false
        && strpos($notifier, 'isset($seen[$email])') !== false
        && strpos($notifier, 'array_slice(') === false
        && strpos($notifier, "'Auto-Submitted' => 'auto-generated'") !== false,
    'Notifier fan-out must cover every authorized subscriber, prevent loops and duplicates, and identify automatic mail.'
);
external_email_contract_assert(
    strpos($helper, 'Deliberately omit the message body') !== false
        && strpos($notifier, 'schoollift_support_notification_html') !== false,
    'Device alerts must use the dedicated body-free notification formatter.'
);
external_email_contract_assert(
    strpos($notificationModel, 'ON DUPLICATE KEY UPDATE') !== false
        && strpos($notificationModel, 'return -1') !== false
        && strpos($notificationModel, 'support_email_alert_deliveries') !== false
        && strpos($notificationModel, "'processing'") !== false
        && strpos($notificationModel, '$attemptCount < 3') !== false,
    'Alert delivery must use an idempotent queue with bounded retries.'
);
external_email_contract_assert(
    strpos($notificationModel, 'LOWER(TRIM(staff.email))') !== false
        && strpos($notifier, '$authorized[$notificationId] !== $email') !== false,
    'Queued alerts must be cancelled when a staff profile email or authorization changes.'
);
external_email_contract_assert(
    strpos($cron, 'supportemailalerts($key)') !== false
        && strpos($cron, 'processQueue(20)') !== false,
    'The protected tenant cron must drain a bounded alert batch.'
);

external_email_contract_assert(
    strpos($webhooks, "load->library('snsmessagevalidator')") !== false
        && strpos($webhooks, 'snsmessagevalidator->isValid($sns_payload)') !== false
        && strpos($webhooks, 'snsmessagevalidator->isRetryableFailure()') !== false
        && strpos($webhooks, 'CURLOPT_FOLLOWLOCATION, false') !== false,
    'The SES webhook must verify the SNS signature and must not follow confirmation redirects.'
);
external_email_contract_assert(
    strpos($snsValidator, 'openssl_verify') !== false
        && strpos($snsValidator, 'amazonaws\\.com') !== false
        && strpos($snsValidator, 'isRetryableFailure') !== false
        && strpos($snsValidator, 'CURLOPT_SSL_VERIFYPEER => true') !== false,
    'SNS validation must use OpenSSL and restrict TLS certificate downloads to Amazon SNS.'
);
external_email_contract_assert(
    strpos($incomingConfig, "getenv('SES_INBOUND_WEBHOOK_TOKEN')") !== false
        && preg_match("/ses_inbound_webhook_token'\]\\s*=.*?:\\s*'';/s", $incomingConfig) === 1,
    'The inbound webhook secret must come from deployment environment without a checked-in fallback.'
);

$signatureCheckPosition = strpos($webhooks, 'snsmessagevalidator->isValid($sns_payload)');
$subscriptionConfirmationPosition = strpos($webhooks, 'confirmSnsSubscription(');
$schoolRoutePosition = strpos($webhooks, 'resolveSchoolRoute($message_data');
$inboundSavePosition = strpos($webhooks, 'saveFromWebhook($record)');
external_email_contract_assert(
    $signatureCheckPosition !== false
        && $subscriptionConfirmationPosition !== false
        && $schoolRoutePosition !== false
        && $inboundSavePosition !== false
        && $signatureCheckPosition < $subscriptionConfirmationPosition
        && $signatureCheckPosition < $schoolRoutePosition
        && $signatureCheckPosition < $inboundSavePosition,
    'SNS signatures must be verified before subscription confirmation, tenant routing, or database writes.'
);
external_email_contract_assert(
    strpos($webhooks, "if (\$allowed_topic_arn === '')") !== false
        && strpos($webhooks, 'hash_equals($allowed_topic_arn, $topic_arn)') !== false,
    'The webhook must fail closed and compare the signed TopicArn exactly.'
);
foreach (array("'Signature'", "'SignatureVersion'", "'SigningCertURL'") as $signedEnvelopeField) {
    external_email_contract_assert(
        strpos($snsValidator, $signedEnvelopeField) !== false,
        'The SNS validator must require signed-envelope field ' . $signedEnvelopeField . '.'
    );
}
$normalizeInbound = external_email_contract_method($webhooks, 'protected', 'normalizeInboundPayload');
$rawHeaderEnvelope = external_email_contract_method($webhooks, 'protected', 'buildSnsPayloadFromHeaders');
external_email_contract_assert(
    strpos($normalizeInbound, 'isSesNotificationPayload') !== false
        && strpos($normalizeInbound, 'buildSnsPayloadFromHeaders') !== false
        && strpos($rawHeaderEnvelope, "'Signature'") === false
        && strpos($rawHeaderEnvelope, "'SignatureVersion'") === false
        && strpos($rawHeaderEnvelope, "'SigningCertURL'") === false,
    'Raw SNS delivery must remain unverifiable and therefore fail the mandatory signature check.'
);
external_email_contract_assert(
    strpos($incomingConfig, 'SNS signature is always required') !== false
        && stripos($setupDocs, 'Leave raw message delivery disabled') !== false
        && stripos($setupDocs, 'unsigned raw delivery is rejected') !== false,
    'Configuration and deployment instructions must require signed SNS JSON delivery.'
);

$findIncomingTicket = external_email_contract_method($ticketModel, 'protected', 'findTicketForIncoming');
$requesterMatches = external_email_contract_method($ticketModel, 'protected', 'ticketRequesterMatches');
external_email_contract_assert(
    substr_count($findIncomingTicket, 'ticketRequesterMatches($ticket, $requester_email)') >= 2
        && strpos($findIncomingTicket, 'extractTicketNumber($subject)') !== false
        && strpos($findIncomingTicket, "getHeader(\$headers, 'in-reply-to')") !== false
        && strpos($findIncomingTicket, "getHeader(\$headers, 'references')") !== false,
    'Both RFC references and public ticket markers must be bound to the original requester.'
);
external_email_contract_assert(
    strpos($requesterMatches, "ticket['requester_email']") !== false
        && strpos($requesterMatches, 'normalizeEmail($requester_email)') !== false
        && strpos($requesterMatches, 'hash_equals($expected, $actual)') !== false,
    'Thread sender binding must compare normalized non-empty requester addresses safely.'
);

$queueIncoming = external_email_contract_method($notifier, 'public', 'queueIncoming');
$processQueue = external_email_contract_method($notifier, 'public', 'processQueue');
$cronAlerts = external_email_contract_method($cron, 'public', 'supportemailalerts');
$cronKeyGuard = external_email_contract_method($cron, 'protected', 'hasValidCronKey');
external_email_contract_assert(
    strpos($webhooks, 'queueIncoming(') !== false
        && strpos($webhooks, 'processQueue(') === false
        && strpos($queueIncoming, 'queueIsReady()') !== false
        && strpos($queueIncoming, 'reserveDelivery(array(') !== false
        && strpos($queueIncoming, "'incoming_email_id' => \$incomingEmailId") !== false,
    'The public webhook must reserve idempotent alert jobs instead of sending mail synchronously.'
);
external_email_contract_assert(
    strpos($notificationModel, 'ON DUPLICATE KEY UPDATE') !== false
        && strpos($migration, 'UNIQUE KEY `support_email_alert_delivery_unique` (`notification_id`, `incoming_email_id`)') !== false,
    'One incoming email may reserve at most one queued alert per staff subscription.'
);
external_email_contract_assert(
    strpos($processQueue, 'getPendingDeliveries($limit)') !== false
        && strpos($processQueue, 'getAuthorizedRecipients()') !== false
        && strpos($processQueue, 'claimDelivery($deliveryId)') !== false
        && strpos($processQueue, 'cancelQueuedDelivery(') !== false
        && strpos($processQueue, 'completeQueuedDelivery(') !== false
        && strpos($processQueue, '$attemptCount < 3') !== false,
    'The worker must claim bounded jobs, reauthorize recipients, cancel stale authority, and cap retries.'
);
external_email_contract_assert(
    strpos($cronAlerts, 'hasValidCronKey($key)') !== false
        && strpos($cronAlerts, 'processQueue(20)') !== false
        && strpos($cron, '$this->supportemailalerts($key)') !== false
        && strpos($cronKeyGuard, 'hash_equals($expected, $provided)') !== false,
    'A protected, bounded queue worker must run from the existing tenant cron.'
);
external_email_contract_assert(
    strpos($notifier, 'get_last_message_id()') !== false
        && strpos($mailer, 'public function get_last_message_id()') !== false
        && strpos($notificationModel, "'provider_message_id'") !== false,
    'Queue delivery history must retain the outbound provider message ID when available.'
);

$legacyIndividualSend = external_email_contract_method($mailsms, 'public', 'send_individual');
$resolveInternalRecipient = external_email_contract_method($mailsms, 'private', 'resolveInternalEmailRecipient');
external_email_contract_assert(
    strpos($legacyIndividualSend, "json_decode(\$this->input->post('user_list'))") !== false
        && strpos($legacyIndividualSend, 'is_object($userlisting)') !== false
        && strpos($legacyIndividualSend, '$userlisting = (array) $userlisting') !== false
        && strpos($legacyIndividualSend, 'foreach ((array) $userlisting') !== false,
    'Legacy object-shaped recipient JSON must be handled without stdClass indexing errors.'
);
external_email_contract_assert(
    strpos($legacyIndividualSend, '!is_array($userlisting_value)') !== false
        && strpos($legacyIndividualSend, 'resolveInternalEmailRecipient(') !== false
        && strpos($legacyIndividualSend, '$user_array[] = $resolved') !== false
        && strpos($legacyIndividualSend, '$user_array[] = $submitted') === false,
    'Legacy recipient entries must be structurally checked and rebuilt server-side.'
);
external_email_contract_assert(
    strpos($resolveInternalRecipient, '$this->student_model->get($recordId)') !== false
        && strpos($resolveInternalRecipient, '$this->staff_model->getAll($recordId)') !== false
        && strpos($resolveInternalRecipient, 'FILTER_VALIDATE_EMAIL') !== false
        && strpos($resolveInternalRecipient, "array('student', 'parent', 'student_guardian')") !== false,
    'Internal recipients must come from current student/guardian/staff database records and valid mailboxes.'
);

external_email_contract_assert(
    preg_match('/migration_version[\'\"]?\]\s*=\s*(\d+)\s*;/', $migrationConfig, $migrationMatch) === 1
        && (int) $migrationMatch[1] >= 134,
    'The configured migration target must include migration 134.'
);
foreach (array(
    'support_email_notifications',
    'support_email_notifications_staff_unique',
    'support_email_alert_deliveries',
    'support_email_alert_delivery_unique',
    "'external_email'",
    'Send External Email',
    "'enable_add' => 1",
    "array('Admin', 'Super Admin')",
) as $migrationContract) {
    external_email_contract_assert(
        strpos($migration, $migrationContract) !== false,
        'Migration 134 is missing: ' . $migrationContract
    );
}
foreach (array($standaloneSql, $consolidatedSql) as $sql) {
    foreach (array(
        'CREATE TABLE IF NOT EXISTS `support_email_notifications`',
        'CREATE TABLE IF NOT EXISTS `support_email_alert_deliveries`',
        'UNIQUE KEY `support_email_notifications_staff_unique` (`staff_id`)',
        'UNIQUE KEY `support_email_alert_delivery_unique` (`notification_id`, `incoming_email_id`)',
        "'external_email'",
        'Send External Email',
        "SELECT 'Admin' AS `role_name`",
        "UNION ALL SELECT 'Super Admin'",
    ) as $sqlContract) {
        external_email_contract_assert(
            strpos($sql, $sqlContract) !== false,
            'The standalone and consolidated imports must include: ' . $sqlContract
        );
    }
}
external_email_contract_assert(
    strpos($consolidatedSql, '126 through 134') !== false
        && strpos($consolidatedSql, '134_add_external_email_notifications.php') !== false,
    'The all-school import must advertise and include migration 134.'
);

foreach (array(
    'admin@apexstaracademy.com.ng',
    'admin@apexstaracademyaso.com.ng',
    'Gmail, Outlook, Apple Mail',
    'cannot be added directly',
    'support_email_notifications',
    'support_email_alert_deliveries',
    'once per minute',
    '/cron/supportemailalerts/CRON_SECRET',
    'external_email_notifications_migration.sql',
) as $documentationContract) {
    external_email_contract_assert(
        stripos($setupDocs, $documentationContract) !== false,
        'SES/device setup documentation is missing: ' . $documentationContract
    );
}

echo 'external_email_contract_test: OK (' . $assertions . ' assertions)' . PHP_EOL;
