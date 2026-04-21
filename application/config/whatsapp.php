<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
|--------------------------------------------------------------------------
| WhatsApp Gateway
|--------------------------------------------------------------------------
|
| Set WHATSAPP_ENABLED=true after the WhatsApp Business provider is ready.
| For Meta Cloud API, create and approve a template whose BODY variables match
| the order in login_template_parameters below.
|
| Suggested template body:
| Hello {{1}}, your {{2}} portal login details are:
| Login URL: {{3}}
| Username: {{4}}
| Password: {{5}}
|
*/
$config['whatsapp'] = array(
    'enabled'                     => getenv('WHATSAPP_ENABLED') ?: 'false',
    'provider'                    => getenv('WHATSAPP_PROVIDER') ?: 'meta_cloud',
    'default_country_code'        => getenv('WHATSAPP_DEFAULT_COUNTRY_CODE') ?: '234',
    'auto_send_login_credentials' => getenv('WHATSAPP_AUTO_SEND_LOGIN_CREDENTIALS') ?: 'false',
    'auto_credential_for'         => array('parent'),
    'login_template_parameters'   => array('display_name', 'school_name', 'url', 'username', 'password'),

    'meta_cloud' => array(
        'graph_api_version' => getenv('WHATSAPP_GRAPH_API_VERSION') ?: 'v20.0',
        'phone_number_id'   => getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '',
        'access_token'      => getenv('WHATSAPP_ACCESS_TOKEN') ?: '',
        'template_name'     => getenv('WHATSAPP_LOGIN_TEMPLATE_NAME') ?: 'parent_login_credentials',
        'template_language' => getenv('WHATSAPP_LOGIN_TEMPLATE_LANGUAGE') ?: 'en',
    ),

    'custom_webhook' => array(
        'url'          => getenv('WHATSAPP_WEBHOOK_URL') ?: '',
        'bearer_token' => getenv('WHATSAPP_WEBHOOK_TOKEN') ?: '',
    ),
);
