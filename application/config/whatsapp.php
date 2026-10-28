<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
|--------------------------------------------------------------------------
| WhatsApp Gateway
|--------------------------------------------------------------------------
|
| Twilio WhatsApp settings are stored per school in `whatsapp_twilio_config`.
| Configure Sandbox and Production from System Settings > WhatsApp Settings.
| The legacy Meta/360dialog configuration is no longer loaded.
|
*/
$config['whatsapp'] = array();
