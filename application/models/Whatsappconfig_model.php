<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Whatsappconfig_model extends MY_Model
{
    private $table = 'whatsapp_config';

    public function __construct()
    {
        parent::__construct();
    }

    public function defaults()
    {
        return array(
            'id' => 1,
            'enabled' => 0,
            'provider' => 'meta_cloud',
            'default_country_code' => '234',
            'auto_send_login_credentials' => 0,
            'auto_credential_for' => 'parent',
            'login_template_parameters' => 'display_name,school_name,url,username,password',
            'meta_graph_api_version' => 'v20.0',
            'meta_phone_number_id' => '',
            'meta_access_token' => '',
            'meta_template_name' => 'parent_login_credentials',
            'meta_template_language' => 'en',
            'webhook_url' => '',
            'webhook_bearer_token' => '',
        );
    }

    public function get()
    {
        $defaults = $this->defaults();

        if (!$this->db->table_exists($this->table)) {
            return $defaults;
        }

        $row = $this->db->where('id', 1)->get($this->table)->row_array();

        if (empty($row)) {
            return $defaults;
        }

        return array_merge($defaults, $row);
    }

    public function getGatewayConfig()
    {
        $config = $this->get();

        return array(
            'enabled' => $config['enabled'],
            'provider' => $config['provider'],
            'default_country_code' => $config['default_country_code'],
            'auto_send_login_credentials' => $config['auto_send_login_credentials'],
            'auto_credential_for' => $this->csvToArray($config['auto_credential_for']),
            'login_template_parameters' => $this->csvToArray($config['login_template_parameters']),
            'meta_cloud' => array(
                'graph_api_version' => $config['meta_graph_api_version'],
                'phone_number_id' => $config['meta_phone_number_id'],
                'access_token' => $config['meta_access_token'],
                'template_name' => $config['meta_template_name'],
                'template_language' => $config['meta_template_language'],
            ),
            'custom_webhook' => array(
                'url' => $config['webhook_url'],
                'bearer_token' => $config['webhook_bearer_token'],
            ),
            // One active provider per school; the existing credential column
            // holds either the direct Meta token or the 360dialog channel key.
            'dialog360' => array(
                'api_key' => $config['provider'] === '360dialog' ? $config['meta_access_token'] : '',
            ),
        );
    }

    public function save($data)
    {
        if (!$this->db->table_exists($this->table)) {
            return false;
        }

        $current = $this->get();
        $provider = isset($data['provider']) ? trim((string) $data['provider']) : 'meta_cloud';
        if (!in_array($provider, array('meta_cloud', '360dialog', 'custom_webhook'), true)) {
            return false;
        }
        $credential_field = $provider === '360dialog' ? 'dialog360_api_key' : 'meta_access_token';
        $credential = isset($data[$credential_field]) && is_string($data[$credential_field]) ? trim($data[$credential_field]) : '';
        if ($credential === '' && $current['provider'] === $provider) {
            $credential = $current['meta_access_token'];
        }
        if (preg_match('/[\r\n]/', (string) $credential)) {
            return false;
        }
        $payload = array(
            'id' => 1,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'provider' => $provider,
            'default_country_code' => isset($data['default_country_code']) ? $data['default_country_code'] : '234',
            'auto_send_login_credentials' => !empty($data['auto_send_login_credentials']) ? 1 : 0,
            'auto_credential_for' => $this->arrayToCsv(isset($data['auto_credential_for']) ? $data['auto_credential_for'] : array('parent')),
            'login_template_parameters' => isset($data['login_template_parameters']) ? $data['login_template_parameters'] : 'display_name,school_name,url,username,password',
            'meta_graph_api_version' => isset($data['meta_graph_api_version']) ? $data['meta_graph_api_version'] : 'v20.0',
            'meta_phone_number_id' => isset($data['meta_phone_number_id']) ? $data['meta_phone_number_id'] : '',
            'meta_access_token' => $credential,
            'meta_template_name' => isset($data['meta_template_name']) ? $data['meta_template_name'] : 'parent_login_credentials',
            'meta_template_language' => isset($data['meta_template_language']) ? $data['meta_template_language'] : 'en',
            'webhook_url' => isset($data['webhook_url']) ? $data['webhook_url'] : '',
            'webhook_bearer_token' => isset($data['webhook_bearer_token']) ? $data['webhook_bearer_token'] : '',
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $exists = $this->db->where('id', 1)->count_all_results($this->table);

        if ($exists) {
            return $this->db->where('id', 1)->update($this->table, $payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->table, $payload);
        }
    }

    private function csvToArray($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function arrayToCsv($value)
    {
        return implode(',', $this->csvToArray($value));
    }
}
