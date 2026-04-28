# SES Inbound Email Setup

This adds a public webhook endpoint that accepts Amazon SNS messages for Amazon SES inbound email notifications and turns received emails into support tickets.

## Endpoint

- Webhook URL: `https://your-domain/webhooks/ses-inbound`
- Optional token: `https://your-domain/webhooks/ses-inbound?token=YOUR_SECRET`

Set the token in [incoming_email.php](/mnt/c/Users/HomePC/Desktop/My%20files/demo_trixschool/application/config/incoming_email.php) with:

```php
$config['ses_inbound_webhook_token'] = 'YOUR_SECRET';
```

## What gets stored

Inbound email events are stored in the `incoming_emails` table with:

- SNS message metadata
- SES mail/receipt metadata
- sender, subject, recipients
- verdicts such as spam, virus, DKIM, SPF, DMARC
- raw SES payload
- raw MIME content when SES includes it
- extracted text/html bodies when available
- attachment filenames when they can be detected from the MIME content

Received email notifications are also converted into:

- `support_tickets` for the admin inbox
- `support_messages` for the ticket conversation history

## AWS setup

1. Verify the receiving domain or subdomain in SES.
2. Create an MX record for a receiving subdomain, for example `inbound.your-domain.com`.
3. Create an SNS topic for inbound mail notifications.
4. Subscribe this application webhook URL to the SNS topic.
5. Create an SES receipt rule for the receiving domain/subdomain.
6. Add an SNS action to the receipt rule that publishes to your SNS topic.
7. Optionally add an S3 action before the SNS action if you also want raw email copies stored in S3.

The webhook accepts both the normal SNS JSON envelope and SNS raw message delivery. If raw message delivery is enabled, the SES notification body is still converted into a support ticket.

SES notification contents reference:

- https://docs.aws.amazon.com/ses/latest/dg/receiving-email-notifications-contents.html

## Database setup

This setup includes migrations:

- `126_add_incoming_emails.php`
- `127_add_support_tickets.php`

To apply it in this project:

1. Temporarily set `migration_enabled` to `TRUE` in `application/config/migration.php`
2. Visit `https://your-domain/index.php/migrate`
3. Set `migration_enabled` back to `FALSE`

If you are applying SQL manually, run `docs/support_email_migration.sql` after the incoming email migration.

## Admin inbox

- URL: `https://your-domain/admin/support`
- Permission short code: `support_ticket`
- Default Admin/Super Admin permissions are inserted by the support ticket migration.

## Notes

- SNS subscription auto-confirmation is enabled by default.
- If you set `ses_inbound_allowed_topic_arn`, the webhook will reject other SNS topics.
- Replies are sent through the existing Email Settings SMTP configuration, so Amazon SES SMTP credentials can be used there.
