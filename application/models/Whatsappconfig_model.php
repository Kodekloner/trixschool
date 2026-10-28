<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Whatsappconfig_model extends MY_Model
{
    private $table = 'whatsapp_twilio_config';
    private $last_error = '';

    public function defaults()
    {
        $profile = array('account_sid' => '', 'auth_token' => '', 'from' => '', 'test_recipient' => '', 'login_content_sid' => '');
        return array('provider' => 'twilio', 'enabled' => 0, 'environment' => 'sandbox',
            'default_country_code' => '234', 'auto_send_login_credentials' => 0,
            'auto_credential_for' => 'parent', 'login_template_parameters' => 'display_name,school_name,url,username,password',
            'sandbox' => $profile, 'production' => $profile);
    }

    public function get()
    {
        $defaults = $this->defaults();
        if (!$this->db->table_exists($this->table)) { return $defaults; }
        $row = $this->db->where('id', 1)->get($this->table)->row_array();
        $saved = !empty($row['settings_json']) ? json_decode($row['settings_json'], true) : array();
        $config = is_array($saved) ? array_replace_recursive($defaults, $saved) : $defaults;
        $config['provider'] = 'twilio';
        return $config;
    }

    public function publicConfig($config, $stored = null)
    {
        $stored = $stored === null ? $config : $stored;
        foreach (array('sandbox', 'production') as $environment) {
            $config[$environment]['token_saved'] = $stored[$environment]['auth_token'] !== '' && $stored[$environment]['account_sid'] === $config[$environment]['account_sid'];
            $config[$environment]['auth_token'] = '';
        }
        return $config;
    }

    public function getGatewayConfig($environment = null, $diagnostic = false)
    {
        $config = $this->get();
        $environment = $environment === null ? $config['environment'] : $environment;
        if (!in_array($environment, array('sandbox', 'production'), true)) { $environment = 'sandbox'; }
        return array('provider' => 'twilio', 'enabled' => $diagnostic ? 1 : $config['enabled'],
            'environment' => $environment, 'diagnostic' => (bool) $diagnostic,
            'default_country_code' => $config['default_country_code'],
            'auto_send_login_credentials' => $config['auto_send_login_credentials'],
            'auto_credential_for' => $this->csv($config['auto_credential_for']),
            'login_template_parameters' => $this->csv($config['login_template_parameters']),
            'twilio' => $config[$environment]);
    }

    public function prepare($data)
    {
        $saved = $this->get();
        $config = $this->defaults();
        foreach (array('environment', 'default_country_code', 'login_template_parameters') as $field) {
            $config[$field] = isset($data[$field]) && is_string($data[$field]) ? trim($data[$field]) : $config[$field];
        }
        $config['enabled'] = isset($data['enabled']) && $data['enabled'] === '1' ? 1 : 0;
        $config['auto_send_login_credentials'] = isset($data['auto_send_login_credentials']) && $data['auto_send_login_credentials'] === '1' ? 1 : 0;
        $groups = isset($data['auto_credential_for']) && is_array($data['auto_credential_for']) ? array_filter($data['auto_credential_for'], 'is_string') : array();
        $config['auto_credential_for'] = implode(',', array_intersect(array('parent', 'student', 'staff'), $groups));
        foreach (array('sandbox', 'production') as $environment) {
            foreach ($config[$environment] as $field => $default) {
                $input = $environment . '_' . $field;
                $config[$environment][$field] = isset($data[$input]) && is_string($data[$input]) ? trim($data[$input]) : '';
            }
            // A token is never copied between environments or different accounts.
            if ($config[$environment]['auth_token'] === '' && $config[$environment]['account_sid'] === $saved[$environment]['account_sid']) {
                $config[$environment]['auth_token'] = $saved[$environment]['auth_token'];
            }
            $config[$environment]['from'] = preg_replace('/^whatsapp:/i', '', $config[$environment]['from']);
        }
        return $config;
    }

    public function validate($config)
    {
        $errors = array();
        if (!in_array($config['environment'], array('sandbox', 'production'), true)) { $errors[] = 'Choose Sandbox or Production.'; }
        if (!preg_match('/^[1-9][0-9]{0,3}$/', $config['default_country_code'])) { $errors[] = 'Enter a valid default country code.'; }
        if ($config['auto_send_login_credentials'] && $config['auto_credential_for'] === '') { $errors[] = 'Select at least one login credential recipient group.'; }
        if (array_diff($this->csv($config['login_template_parameters']), array('display_name', 'school_name', 'url', 'username', 'password'))) { $errors[] = 'Use only the listed login template parameter names.'; }
        foreach (array('sandbox', 'production') as $environment) {
            $profile = $config[$environment];
            $required = $config['enabled'] && $config['environment'] === $environment;
            foreach (array('account_sid' => '/^AC[0-9a-fA-F]{32}$/', 'auth_token' => '/^[0-9a-fA-F]{32}$/', 'from' => '/^\+[1-9][0-9]{7,14}$/') as $field => $pattern) {
                if (($required || $profile[$field] !== '') && !preg_match($pattern, $profile[$field])) {
                    $labels = array('account_sid' => 'Account SID (AC followed by 32 hex characters)', 'auth_token' => 'Auth Token (32 hex characters)', 'from' => 'WhatsApp sender in international format, e.g. +14155238886');
                    $errors[] = ucfirst($environment) . ': enter a valid ' . $labels[$field] . '.';
                }
            }
            if ($profile['test_recipient'] !== '' && !preg_match('/^\+[1-9][0-9]{7,14}$/', $profile['test_recipient'])) { $errors[] = ucfirst($environment) . ': use +countrycode format for the test recipient.'; }
            if (($environment === 'production' && $config['environment'] === 'production' && $config['auto_send_login_credentials']) || $profile['login_content_sid'] !== '') {
                if (!preg_match('/^HX[0-9a-fA-F]{32}$/', $profile['login_content_sid'])) { $errors[] = ucfirst($environment) . ': enter a valid login Content SID (HX followed by 32 hex characters).'; }
            }
        }
        return $errors;
    }

    public function getLastError() { return $this->last_error; }

    public function save($config)
    {
        $errors = $this->validate($config);
        if ($errors) { $this->last_error = implode(' ', $errors); return false; }
        // A separate table avoids reusing legacy secrets or changing migration versions.
        $debug = $this->db->db_debug;
        $this->db->db_debug = false;
        try {
            $table = $this->db->dbprefix($this->table);
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) { throw new RuntimeException('Unsupported table prefix'); }
            $table = '`' . $table . '`';
            $created = $this->db->table_exists($this->table) || $this->db->query('CREATE TABLE IF NOT EXISTS ' . $table . ' (`id` TINYINT UNSIGNED NOT NULL PRIMARY KEY, `settings_json` MEDIUMTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $json = json_encode($config);
            $saved = $created && $json !== false && $this->db->query('INSERT INTO ' . $table . ' (`id`, `settings_json`) VALUES (1, ?) ON DUPLICATE KEY UPDATE `settings_json` = VALUES(`settings_json`)', array($json));
        } catch (Throwable $error) { $saved = false; }
        finally { $this->db->db_debug = $debug; }
        $this->last_error = $saved ? '' : 'Could not save Twilio settings. Ask the administrator to apply docs/whatsapp-twilio-config.sql and check database write permissions.';
        return (bool) $saved;
    }

    private function csv($value)
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
