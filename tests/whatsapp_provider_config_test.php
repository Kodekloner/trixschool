<?php

define('BASEPATH', __DIR__);
class MY_Model { public $db; }
require_once __DIR__ . '/../application/models/Whatsappconfig_model.php';

function provider_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
class ProviderConfigDb {
    public $row;
    public $saved;
    public $write_result = true;
    public $table_present = true;
    public function table_exists($table) { return $this->table_present; }
    public function where($key, $value) { return $this; }
    public function get($table) { return $this; }
    public function row_array() { return $this->row; }
    public function count_all_results($table) { return !empty($this->row) ? 1 : 0; }
    public function update($table, $payload) { $this->saved = $payload; return $this->write_result; }
    public function insert($table, $payload) { $this->saved = $payload; return $this->write_result; }
}
$model = (new ReflectionClass('Whatsappconfig_model'))->newInstanceWithoutConstructor();
$model->db = new ProviderConfigDb();
$model->db->row = array_merge($model->defaults(), array('provider' => 'meta_cloud', 'meta_access_token' => 'old-meta-token'));
provider_assert($model->save(array('provider' => 'meta_cloud', 'meta_access_token' => '')), 'Existing Meta settings must still save.');
provider_assert($model->db->saved['meta_access_token'] === 'old-meta-token', 'Blank input for the same provider must keep the saved secret.');
provider_assert($model->save(array('provider' => '360dialog', 'dialog360_api_key' => ' new-channel-key ', 'meta_access_token' => 'irrelevant-meta-token')), 'Managed provider settings must save.');
provider_assert($model->db->saved['provider'] === '360dialog' && $model->db->saved['meta_access_token'] === 'new-channel-key', '360dialog must store its trimmed channel key, not a hidden Meta input.');
provider_assert(!isset($model->db->saved['dialog360_api_key']), 'The existing schema must be used without adding a database column.');
$model->db->row = array_merge($model->defaults(), $model->db->saved);
$gateway_config = $model->getGatewayConfig();
provider_assert($gateway_config['dialog360']['api_key'] === 'new-channel-key', 'The gateway must receive the active channel key.');
provider_assert($model->save(array('provider' => '360dialog', 'dialog360_api_key' => '')), 'Editing a managed provider without changing its key must work.');
provider_assert($model->db->saved['meta_access_token'] === 'new-channel-key', 'A blank key must preserve the saved 360dialog key.');
provider_assert($model->save(array('provider' => 'meta_cloud', 'meta_access_token' => '')), 'A disabled provider switch can be saved without credentials.');
provider_assert($model->db->saved['meta_access_token'] === '', 'Switching provider must not reuse an incompatible provider credential.');
provider_assert(!$model->save(array('provider' => '360dialog', 'dialog360_api_key' => "bad\r\nHeader: test")), 'Header injection in a provider key must fail.');
provider_assert(!$model->save(array('provider' => 'unsupported')), 'Unknown providers must fail.');
$model->db->write_result = false;
provider_assert(!$model->save(array('provider' => '360dialog', 'dialog360_api_key' => 'new-channel-key')), 'Database write failure must not report success.');
$model->db->table_present = false;
provider_assert(!$model->save(array('provider' => '360dialog')), 'A missing configuration table must block saving.');

echo "WhatsApp provider configuration tests passed\n";
