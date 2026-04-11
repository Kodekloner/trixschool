<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| SES Inbound Email
|--------------------------------------------------------------------------
|
| Configure the public webhook endpoint that Amazon SNS will call for SES
| inbound notifications. Keep the token empty to disable token checking.
|
*/
$config['ses_inbound_webhook_token'] = '';

/*
|--------------------------------------------------------------------------
| Allowed SNS Topic ARN
|--------------------------------------------------------------------------
|
| Optionally restrict the inbound webhook to one SNS topic ARN.
|
*/
$config['ses_inbound_allowed_topic_arn'] = '';

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

