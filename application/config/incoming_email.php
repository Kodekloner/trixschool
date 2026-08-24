<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| SES Inbound Email
|--------------------------------------------------------------------------
|
| Configure the public webhook endpoint that Amazon SNS will call for SES
| inbound notifications. The SNS signature is always required; this optional
| token is a second check and should come from the server environment.
|
*/
$environment_token = getenv('SES_INBOUND_WEBHOOK_TOKEN');
$config['ses_inbound_webhook_token'] = ($environment_token !== false && trim($environment_token) !== '')
    ? trim($environment_token)
    : '';

/*
|--------------------------------------------------------------------------
| Allowed SNS Topic ARN
|--------------------------------------------------------------------------
|
| Restrict the inbound webhook to exactly one SNS topic ARN. The controller
| rejects inbound processing when this value is empty.
|
*/
$environment_topic_arn = getenv('SES_INBOUND_TOPIC_ARN');
$config['ses_inbound_allowed_topic_arn'] = ($environment_topic_arn !== false && trim($environment_topic_arn) !== '')
    ? trim($environment_topic_arn)
    : 'arn:aws:sns:us-east-2:487461038503:schoollift-support-inbound';

/*
|--------------------------------------------------------------------------
| Auto-confirm SNS subscriptions
|--------------------------------------------------------------------------
|
| When enabled, the webhook will call SubscribeURL automatically for SNS
| SubscriptionConfirmation messages.
|
*/
$config['ses_inbound_auto_confirm'] = true;

/*
|--------------------------------------------------------------------------
| School inbound address
|--------------------------------------------------------------------------
|
| Inbound notifications are routed to a school database using the domain
| from admin@school-domain. The domain must match a database group name in
| application/config/database.php.
|
*/
$config['ses_inbound_recipient_local_part'] = 'admin';
