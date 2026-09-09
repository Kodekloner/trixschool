# Monnify payment setup

The integration uses Monnify hosted checkout for student fees and online admission fees. Card, account transfer, USSD, and phone-number payment methods are enabled. Every callback and webhook is reverified against Monnify before the school records a payment. Student fee ownership and the current outstanding balance are checked both before checkout and again when a delayed payment is fulfilled.

## 1. Apply the database migration

Run CodeIgniter migration `129_add_monnify_payments`, or import `docs/all_school_database_migrations.sql` into each school database through phpMyAdmin.

The migration creates the local payment ledger used to fulfill delayed bank transfers without relying on a student's browser session.

## 2. Add test credentials

In **System Settings > Payment Methods > Monnify**, select **Test / Sandbox** and enter the test API Key, Secret Key, and Contract Code from **Developer > API Keys & Contracts** in the Monnify dashboard.

Test API keys must begin with `MK_TEST_`. Test mode automatically uses:

```text
https://sandbox.monnify.com
```

Save the credentials, then select **Monnify** in the payment-gateway list and save again.

After the first save, the settings page deliberately leaves the Secret Key field blank and never sends the stored secret back to the browser. Leave it blank only while keeping the same mode and API key. A new secret is required when the mode or API key changes, because those credentials must remain a matching set. You can change the contract code alone without re-entering the secret.

## 3. Configure the webhook

Copy the webhook URL shown in the Monnify settings panel into **Developer > Webhook URLs** in the Monnify dashboard. It has this shape:

```text
https://your-school-domain.example/webhooks/monnify
```

Configure the URL separately for each school domain because the hostname selects that school's database.

If CodeIgniter CSRF protection is enabled later, add `webhooks/monnify` to `csrf_exclude_uris` so Monnify can POST notifications to it.

## 4. Test and go live

Start a fee payment while test mode is active and complete it on Monnify's sandbox checkout. Confirm that the payment appears once in the student's fee history or admission-payment record.

When the Monnify account is approved for production, replace all three credentials with live values and change the mode to **Live**. Production API keys must begin with `MK_PROD_`; live mode automatically uses `https://api.monnify.com` and rejects every other API-key prefix.

Monnify checkout is enabled only when the school's configured currency is `NGN`.
