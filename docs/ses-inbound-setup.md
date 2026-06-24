# Central SES Inbound Email Setup

SchoolLift uses one Amazon SES receiving setup, one SNS topic, and one HTTPS
webhook subscription for every school. Each school publishes a friendly email
address using its website domain:

- `admin@demo.schoollift.com.ng`
- `admin@purplinsschool.com.ng`

The webhook reads the SES envelope recipient and uses its domain as the
CodeIgniter database group. For example, `admin@purplinsschool.com.ng` is
written to the `purplinsschool.com.ng` database group.

## Application requirements

1. Every school domain must have a database group with the exact same name in
   `application/config/database.php`.
2. Every participating school database must have `incoming_emails`,
   `support_tickets`, and `support_messages`.
3. The public local part is configured once in `application/config/incoming_email.php`:

```php
$config['ses_inbound_recipient_local_part'] = 'admin';
```

4. The central webhook endpoint is:

```text
https://demo.schoollift.com.ng/webhooks/ses-inbound?token=YOUR_SECRET
```

The webhook token and topic ARN can be provided as server environment
variables. The checked-in values remain fallbacks for existing deployments.

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

For each school domain:

1. Temporarily set `migration_enabled` to `TRUE` in
   `application/config/migration.php`.
2. Visit `https://SCHOOL-DOMAIN/index.php/migrate` while that domain is mapped
   to the correct database.
3. Confirm the migration reports version `127`.
4. Set `migration_enabled` back to `FALSE` immediately.

Migration 127 also creates the `support_ticket` permission and assigns it to
Admin and Super Admin roles.

## One-time AWS setup

The existing setup uses the `schoollift-support-inbound` SNS topic. Keep that
topic and its single HTTPS subscription.

1. Open Amazon SNS in the same AWS region as SES receiving.
2. Open the `schoollift-support-inbound` topic.
3. Confirm there is one HTTPS subscription for the central webhook URL.
4. Leave raw message delivery disabled unless the existing subscription is
   already using it. The webhook accepts either format.
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
2. Create a receipt rule for the exact recipient, such as
   `admin@purplinsschool.com.ng`.
3. Add an SNS action using the existing `schoollift-support-inbound` topic.
4. Use Base64 encoding so the webhook can parse the original MIME content.
5. Enable the rule and place it before any broad catch-all rule.

Do not create another SNS topic or another webhook subscription for the new
school.

## Configure replies

Amazon SES receiving is not a mailbox and does not provide webmail. The school
uses `https://SCHOOL-DOMAIN/admin/support` as its inbox.

Configure the school's existing Email Settings with SES SMTP credentials and a
verified sender address so administrators can reply from the ticket page. Add
SPF, DKIM, and DMARC DNS records for reliable outbound delivery.

## End-to-end test

1. Sign in to `https://SCHOOL-DOMAIN/admin/support` and confirm the header shows
   `admin@SCHOOL-DOMAIN`.
2. Send a new email from an unrelated personal email account to that address.
3. Wait briefly, then refresh the Support Tickets page.
4. Open the new ticket and confirm sender, subject, body, and attachments.
5. Reply from the ticket and verify the requester receives the response.
6. Reply to that response and verify it returns to the same ticket thread.

If no ticket appears, check in this order:

1. The domain MX record resolves to the SES receiving endpoint.
2. The SES receipt rule matched the exact `admin@` recipient.
3. The SNS subscription is confirmed and reports successful delivery.
4. The domain exactly matches a database group in `database.php`.
5. The school database migration version is 127.
6. The `incoming_emails` table contains the notification and its status/error.

## Contact and complaint forms

Website contact and complaint forms do not pass through AWS. After validation,
they create support tickets directly in the current school's database and can
send the configured WhatsApp notification. AWS SES/SNS is used only for actual
emails sent to the school's `admin@` address.
