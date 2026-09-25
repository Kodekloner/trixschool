# Central SES Inbound Email Setup

SchoolLift uses one Amazon SES receiving setup, one SNS topic, and one HTTPS
webhook subscription for every school. Each school publishes a friendly email
addresses using its website domain. `admin@` is reserved for Support Tickets;
`mail@` is the ordinary shared correspondence inbox:

- `admin@demo.schoollift.com.ng`
- `mail@demo.schoollift.com.ng`
- `admin@purplinsschool.com.ng`
- `mail@purplinsschool.com.ng`

The webhook reads the SES envelope recipient and uses its domain as the
CodeIgniter database group and uses the local part to select Support or Shared
Email. For example, both addresses at `purplinsschool.com.ng` are written to
that tenant database, but only mail sent to `admin@` becomes a Support Ticket.

## Application requirements

1. Every school domain must have a database group with the exact same name in
   `application/config/database.php`.
2. Every participating school database must have `incoming_emails`,
   `support_tickets`, `support_messages`, `email_conversations`, and
   `email_conversation_messages`. Schools using Support staff email alerts must
   also have `support_email_notifications` and `support_email_alert_deliveries`.
   PHP must have the OpenSSL extension enabled, and the web server must be able
   to fetch HTTPS certificates from `sns.<region>.amazonaws.com` so SNS
   signatures can be verified.
3. The two public local parts are configured once in
   `application/config/incoming_email.php` and must remain different:

```php
$config['ses_inbound_recipient_local_part'] = 'admin';
$config['ses_inbound_mail_local_part'] = 'mail';
```

4. The central webhook endpoint is:

```text
https://demo.schoollift.com.ng/webhooks/ses-inbound?token=YOUR_SECRET
```

The webhook verifies the Amazon SNS signature and requires the configured topic
ARN on every request. A webhook token can be provided as an additional secret;
there is no checked-in fallback token. Keep production values in server
environment variables.

```text
SES_INBOUND_WEBHOOK_TOKEN=YOUR_SECRET
SES_INBOUND_TOPIC_ARN=arn:aws:sns:REGION:ACCOUNT_ID:TOPIC_NAME
```

Only one school endpoint should be subscribed to SNS. Do not subscribe every
school domain to the same topic because SNS broadcasts to every subscription.

## Database setup for each school

The project includes these migrations:

- `126_add_incoming_emails.php`
- `127_add_support_tickets.php`
- `134_add_external_email_notifications.php`
- `141_add_shared_email_inbox.php`

For existing tenant databases, use the rerunnable SQL import described below.
Only run CodeIgniter's `/migrate` endpoint when the tenant's migration ledger
has first been audited and correctly records its current version. In
particular, do not use `/migrate` on the two Apex Star snapshots: their ledger
tables are empty even though migrations 126 through 133 are already present.

Migration 127 creates the `support_ticket` permission. Migration 134 adds the
per-staff Support alert queue and the dedicated `external_email` permission.
Migration 141 adds the shared conversation tables and `shared_email`
permission. Admin, Super Admin, and Head Teacher receive view/reply access to
Shared Email and permission to send External email by default. Other roles can
be authorized through **System Settings > Roles Permissions**. No staff member
is subscribed automatically to Support alerts.

For a phpMyAdmin deployment, import
`docs/all_school_database_migrations.sql` to apply the consolidated
126-through-141 tenant migrations. The file is rerunnable and intentionally
leaves the CodeIgniter `migrations` ledger unchanged.

## One-time AWS setup

The existing setup uses the `schoollift-support-inbound` SNS topic. Keep that
topic and its single HTTPS subscription.

1. Open Amazon SNS in the same AWS region as SES receiving.
2. Open the `schoollift-support-inbound` topic.
3. Confirm there is one HTTPS subscription for the central webhook URL.
4. Leave raw message delivery disabled. The signed SNS JSON envelope is
   required; unsigned raw delivery is rejected.
5. Confirm the subscription status is `Confirmed`.

## Add a school in Amazon SES

Repeat these steps for every new school.

### 1. Verify the school domain

1. Open Amazon SES in the same region as the existing SNS topic.
2. Go to **Configuration > Identities**.
3. Choose **Create identity**, select **Domain**, and enter the school domain.
4. Add the generated verification and DKIM DNS records at the DNS provider.
5. Wait for the SES identity status to become verified.

For the demo school, use `demo.schoollift.com.ng`. For Purplins, use
`purplinsschool.com.ng`.

### 2. Add the receiving MX record

The current AWS region is `us-east-2`, whose receiving endpoint is:

```text
inbound-smtp.us-east-2.amazonaws.com
```

Create this DNS record:

```text
Type: MX
Priority: 10
Value: inbound-smtp.us-east-2.amazonaws.com
```

Use `demo` as the DNS name when editing the `schoollift.com.ng` zone for
`demo.schoollift.com.ng`. Use `@` when editing the root
`purplinsschool.com.ng` zone. Adding MX records does not change the website A
or CNAME records.

### 3. Add the SES receipt rule

1. Go to **Email receiving > Rule sets** and open the active rule set.
2. Add both exact recipients to the receipt rule (or to two rules with the same
   action), such as `admin@purplinsschool.com.ng` and
   `mail@purplinsschool.com.ng`.
3. Add an SNS action using the existing `schoollift-support-inbound` topic.
4. Use Base64 encoding so the webhook can parse the original MIME content.
5. Enable the rule and place it before any broad catch-all rule.

Do not create another SNS topic or another webhook subscription for the new
school.

## External recipients and shared conversations

Authorized staff can use **Communicate > Send Email > External** to enter an
external address that is not attached to a student, guardian, or staff record.
External recipients are sent through the same active Email Settings/SMTP
configuration as directory recipients. Sending to an external address does not
create that person as a SchoolLift user or create a Support Ticket. The External
form also accepts multiple file attachments. Its subject and body are sent as a
regular outbound email without a ticket marker or Support-specific headers.
The send is saved under **Communicate > Shared Email > Sent**, and its Reply-To
address is `mail@SCHOOL-DOMAIN`.

Shared Email and Support Tickets are deliberately separate:

- Use External compose to start regular correspondence with any valid address.
- Admin, Super Admin, and Head Teacher can open **Shared Email**, see the sent
  message, and reply from the same conversation. Replies received at `mail@`
  appear in its Inbox/Unread views.
- Use **Support Tickets** to answer a message that arrived at the school's
  `admin@` address.
- Reply threading uses the standard `Message-ID`, `In-Reply-To`, and
  `References` headers and also verifies that the external sender matches the
  conversation participant. It does not depend on a ticket marker. Amazon SES
  [replaces submitted Message-ID headers](https://docs.aws.amazon.com/ses/latest/dg/header-fields.html),
  so SchoolLift captures the ID returned in SES's successful `250 Ok` SMTP
  response and stores the actual `ID@email.amazonses.com` value used by the
  recipient's reply.
- Messages addressed to `admin@SCHOOL-DOMAIN` are processed by SES and appear
  in Support Tickets. Replies sent from the Support Ticket page retain the
  ticket headers and marker needed to stay on the existing thread.

Do not create student, guardian, or staff records merely to send an email to an
external recipient. The dedicated **Send External Email** and **Shared Email
Inbox** permissions are granted to Admin, Super Admin, and Head Teacher by
default. A school can explicitly authorize another staff role through Roles
Permissions. Support Tickets remains controlled by its separate permission.

## Configure replies

Amazon SES receiving is not an IMAP mailbox and does not provide AWS webmail.
SchoolLift supplies the web interfaces instead:

- `https://SCHOOL-DOMAIN/admin/emailinbox` for ordinary `mail@` correspondence.
- `https://SCHOOL-DOMAIN/admin/support` for `admin@` Support Tickets.

Configure the school's existing Email Settings with SES SMTP credentials and a
verified sender address so authorized staff can reply from either interface.
Add SPF, DKIM, and DMARC DNS records for reliable outbound delivery.

## Staff alerts on phones and computers

An SES receiving address is not an IMAP or POP mailbox. Therefore,
`admin@SCHOOL-DOMAIN` cannot be added directly to Gmail, Outlook, Apple Mail, or
another device mail client with a mailbox password. The authenticated
`https://SCHOOL-DOMAIN/admin/support` page remains the source of truth for the
school's incoming conversations.

Staff who need device notifications can opt in to support-email alerts using
the email address saved on their staff profile. That address should be a work
or personal mailbox they already receive on their phone or computer through
Gmail, Outlook, Apple Mail, or a similar client. When SchoolLift receives an
email at `admin@`, it sends a short alert with a secure link to the new or
updated Support Ticket. This alert setting is Support-only; mail received at
`mail@` is shown through the Shared Email Inbox/Unread view. The alert is not a
second mailbox and is not a forward of the complete original message.

Recommended setup for each recipient:

1. Confirm the staff member has permission to view Support Tickets.
2. Save and verify the destination email on the staff member's profile, then
   use **Enable Email Alerts** on the Support Tickets page.
3. Add that destination mailbox to the staff member's device mail app and allow
   notifications for the app.
4. On every tenant domain, schedule only the dedicated protected alert URL at
   least once per minute:

   `https://SCHOOL-DOMAIN/cron/supportemailalerts/CRON_SECRET`

   Do not use the generic `/cron/CRON_SECRET` endpoint for this one-minute job;
   that endpoint also runs database backup and other scheduled work. The alert
   worker sends queued alerts and retries temporary failures three times.
5. Send a test message to `admin@SCHOOL-DOMAIN` and confirm the device receives
   one alert whose link requires a valid SchoolLift staff login.
6. Disable the alert when the staff member changes responsibility or leaves the
   school.

Alerts should contain only the sender, subject, ticket number, and authenticated
ticket link. Staff should read and reply inside Support Tickets so the complete
conversation and delivery history remain together. If a school requires a real
device-synchronised `admin@` mailbox, it must deploy a hosted mailbox service
and redesign the MX/SES routing; the alert feature does not provide IMAP.

## End-to-end tests

### Shared Email (`mail@`)

1. Sign in as an Admin, Super Admin, or Head Teacher and open
   `https://SCHOOL-DOMAIN/admin/mailsms/compose?tab=external`.
2. Send an External email with an attachment to an unrelated personal address.
3. Confirm it opens under `https://SCHOOL-DOMAIN/admin/emailinbox` and appears
   in Sent with the sender staff member recorded.
4. In the personal mailbox, verify Reply-To is `mail@SCHOOL-DOMAIN` and reply.
5. Refresh Shared Email, open Inbox or Unread, and confirm the reply is attached
   to the original conversation rather than creating a Support Ticket.
6. Reply from Shared Email and verify the personal mailbox receives it in the
   same email thread.

### Support Tickets (`admin@`)

1. Sign in to `https://SCHOOL-DOMAIN/admin/support` and confirm the header shows
   `admin@SCHOOL-DOMAIN`.
2. Send a new email from an unrelated personal email account to that address.
3. Wait briefly, then refresh the Support Tickets page.
4. Open the new ticket and confirm sender, subject, body, and attachments.
5. Reply from the ticket and verify the requester receives the response.
6. Reply to that response and verify it returns to the same ticket thread.

If no ticket appears, check in this order:

1. The domain MX record resolves to the SES receiving endpoint.
2. The SES receipt rule matched the exact `admin@` or `mail@` recipient being
   tested.
3. The SNS subscription is confirmed and reports successful delivery.
4. The domain exactly matches a database group in `database.php`.
5. Migration 141 shared-email tables (and, for Support alerts, the two
   migration-134 queue tables) exist. The phpMyAdmin scripts intentionally do
   not alter the migration ledger.
6. The `incoming_emails` table contains the notification and its status/error.

If the ticket appears but a staff alert does not, confirm that the staff member
is active, has opted in, has a valid destination in
`support_email_notifications`, and can receive an ordinary SMTP test message.
Confirm the protected cron is running. The preference row's `last_notified_at`
and `last_error` show its latest attempt; `support_email_alert_deliveries`
contains each queued, retried, sent, failed, or cancelled alert.

## Apex Star Academy deployment checklist

Apply this checklist first to the two requested schools. The same application
code and migration remain tenant-safe for every other school.

### `apexstaracademy.com.ng`

1. Confirm the CodeIgniter database group is exactly
   `apexstaracademy.com.ng` and points to the intended tenant database.
2. Import `docs/all_school_database_migrations.sql` into that database.
3. Verify the SES domain identity and DKIM for `apexstaracademy.com.ng`.
4. Confirm the domain MX record targets
   `inbound-smtp.us-east-2.amazonaws.com` and the active receipt rule matches
   exactly `admin@apexstaracademy.com.ng` and
   `mail@apexstaracademy.com.ng`.
5. Confirm the school Email Settings sender is verified for outbound SMTP and
   normalize the saved SMTP port to `587` (the audited snapshot contains
   leading whitespace).
6. Open `https://apexstaracademy.com.ng/admin/support`, opt in the authorized
   staff recipients, and test `admin@` receipt, device alert, and Support reply
   threading. Then send an External email with an attachment, open it at
   `https://apexstaracademy.com.ng/admin/emailinbox`, reply to it from the
   recipient mailbox, and confirm the response enters Shared Email through
   `mail@` without creating a Support Ticket.
7. Schedule
   `https://apexstaracademy.com.ng/cron/supportemailalerts/CRON_SECRET` once per
   minute using that tenant's configured cron secret.

### `apexstaracademyaso.com.ng`

1. Confirm the CodeIgniter database group is exactly
   `apexstaracademyaso.com.ng` and points to the intended tenant database.
2. Import `docs/all_school_database_migrations.sql` into that database.
3. Verify the SES domain identity and DKIM for `apexstaracademyaso.com.ng`.
4. Confirm the domain MX record targets
   `inbound-smtp.us-east-2.amazonaws.com` and the active receipt rule matches
   exactly `admin@apexstaracademyaso.com.ng` and
   `mail@apexstaracademyaso.com.ng`.
5. Verify or correct the Email Settings sender domain. The audited snapshot
   uses `apexacademyaso.com.ng`, which differs from
   `apexstaracademyaso.com.ng`; SES must verify the actual sender identity.
6. Open `https://apexstaracademyaso.com.ng/admin/support`, opt in the authorized
   staff recipients, and test `admin@` receipt, device alert, and Support reply
   threading. Then send an External email with an attachment, open it at
   `https://apexstaracademyaso.com.ng/admin/emailinbox`, reply from the
   recipient mailbox, and confirm the response enters Shared Email through
   `mail@` without creating a Support Ticket.
7. Schedule
   `https://apexstaracademyaso.com.ng/cron/supportemailalerts/CRON_SECRET` once
   per minute using that tenant's configured cron secret.

The files under `database/migration-audit-dumps/` are raw audit snapshots and
may contain tenant data. Do not edit or distribute them as deployment scripts.
Apply migrations 134 and 141 with the consolidated SQL above, then create
a fresh sanitized audit snapshot only through the established audit process.
If a snapshot containing SMTP credentials or an old webhook token has been
shared outside the trusted deployment team, rotate those credentials and keep
their replacements in environment/deployment secrets rather than audit files.

## Contact and complaint forms

Website contact and complaint forms do not pass through AWS. After validation,
they create support tickets directly in the current school's database and can
send the configured WhatsApp notification. AWS SES/SNS is used only for actual
emails sent to the school's `admin@` Support address or `mail@` Shared Email
address.
