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

$individualSend = external_email_contract_method($mailsms, 'public', 'send_individual');
external_email_contract_assert(
    strpos($individualSend, "hasPrivilege('email', 'can_view')") !== false
        && strpos($individualSend, 'required|in_list[email]') !== false
        && strpos($individualSend, 'foreach ((array) $userlisting') !== false
        && strpos($individualSend, 'resolveInternalEmailRecipient') !== false,
    'The legacy individual form must enforce email permission, accept keyed JSON, and re-resolve internal recipients.'
);
foreach (array('send_birthday', 'send_group', 'send_class') as $legacyActionName) {
    $legacyAction = external_email_contract_method($mailsms, 'public', $legacyActionName);
    external_email_contract_assert(
        strpos($legacyAction, "hasPrivilege('email', 'can_view')") !== false
            && strpos($legacyAction, 'required|in_list[email]') !== false,
        $legacyActionName . ' must enforce the standard email permission and transport.'
    );
}

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

$incomingGuardPosition = strpos($webhooks, 'wasLastIncomingMessageCreated()');
$notifyPosition = strpos($webhooks, 'queueIncoming(');
external_email_contract_assert(
    $incomingGuardPosition !== false && $notifyPosition !== false && $incomingGuardPosition < $notifyPosition,
    'Alerts must run only when the webhook persisted a genuinely new inbound message.'
);
external_email_contract_assert(
    strpos($ticketModel, 'last_incoming_message_created') !== false
        && strpos($ticketModel, 'wasLastIncomingMessageCreated') !== false,
    'The ticket model must expose whether the inbound message was newly created.'
);
external_email_contract_assert(
    preg_match('/catch \((?:Exception|Throwable) \$exception\)/', $webhooks) === 1
        && strpos($webhooks, 'Support email alert failed:') !== false,
    'Alert delivery failures must not make SES inbound storage fail.'
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
        && strpos($notifier, "'Auto-Submitted' => 'auto-generated'") !== false,
    'Notifier fan-out must prevent loops and duplicates and identify automatic mail.'
);
external_email_contract_assert(
    strpos($helper, 'Deliberately omit the message body') !== false
        && strpos($notifier, 'schoollift_support_notification_html') !== false,
    'Device alerts must use the dedicated body-free notification formatter.'
);
external_email_contract_assert(
    strpos($notificationModel, 'INSERT IGNORE INTO') !== false
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
        && strpos($webhooks, 'CURLOPT_FOLLOWLOCATION, false') !== false,
    'The SES webhook must verify the SNS signature and must not follow confirmation redirects.'
);
external_email_contract_assert(
    strpos($snsValidator, 'openssl_verify') !== false
        && strpos($snsValidator, 'amazonaws\\.com') !== false
        && strpos($snsValidator, 'CURLOPT_SSL_VERIFYPEER => true') !== false,
    'SNS validation must use OpenSSL and restrict TLS certificate downloads to Amazon SNS.'
);
external_email_contract_assert(
    strpos($incomingConfig, "getenv('SES_INBOUND_WEBHOOK_TOKEN')") !== false
        && preg_match("/ses_inbound_webhook_token'\]\\s*=.*?:\\s*'';/s", $incomingConfig) === 1,
    'The inbound webhook secret must come from deployment environment without a checked-in fallback.'
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
    "array('Admin', 'Head Teacher', 'Super Admin')",
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
        "UNION ALL SELECT 'Head Teacher'",
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
    'external_email_notifications_migration.sql',
) as $documentationContract) {
    external_email_contract_assert(
        stripos($setupDocs, $documentationContract) !== false,
        'SES/device setup documentation is missing: ' . $documentationContract
    );
}

echo 'external_email_contract_test: OK (' . $assertions . ' assertions)' . PHP_EOL;
