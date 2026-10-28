<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__); }
class MY_Model { public $db; }
require_once __DIR__ . '/../application/models/Whatsappconfig_model.php';
function provider_assert($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
class ProviderConfigDb {
    public $row = array();
    public $db_debug = true;
    public $table_present = false;
    public $write_result = true;
    public $prefix = '';
    public $queries = array();
    public function table_exists($table) { provider_assert($table === 'whatsapp_twilio_config', 'Legacy provider table must never be read.'); return $this->table_present; }
    public function where($key, $value) { return $this; }
    public function dbprefix($table) { return $this->prefix . $table; }
    public function get($table) { return $this; }
    public function row_array() { return $this->row; }
    public function query($sql, $bindings = array()) {
        $this->queries[] = array($sql, $bindings);
        if (!$this->write_result) { return false; }
        if ($bindings) { $this->row = array('settings_json' => $bindings[0]); $this->table_present = true; }
        return true;
    }
}
$model = (new ReflectionClass('Whatsappconfig_model'))->newInstanceWithoutConstructor();
$model->db = new ProviderConfigDb();
$defaults = $model->get();
provider_assert(!$defaults['enabled'] && $defaults['environment'] === 'sandbox' && $defaults['provider'] === 'twilio', 'New or legacy installations must default to disabled Sandbox.');
$post = array('environment' => 'sandbox', 'enabled' => '0', 'sandbox_account_sid' => 'AC' . str_repeat('a', 32), 'sandbox_auth_token' => str_repeat('b', 32), 'sandbox_from' => 'whatsapp:+14155238886', 'sandbox_test_recipient' => '+2348012345678', 'auto_credential_for' => array('parent'));
$config = $model->prepare($post);
provider_assert(!$model->validate($config) && $model->save($config), 'Saving Sandbox must create isolated config storage.');
provider_assert($config['sandbox']['from'] === '+14155238886' && $model->db->db_debug === true, 'Sender prefix normalization and DB debug restoration must work.');
$config = $model->prepare(array_replace($post, array('sandbox_auth_token' => '')));
provider_assert($config['sandbox']['auth_token'] === str_repeat('b', 32), 'Blank token must preserve the saved token for the same account.');
$config = $model->prepare(array_replace($post, array('sandbox_account_sid' => 'AC' . str_repeat('c', 32), 'sandbox_auth_token' => '')));
provider_assert($config['sandbox']['auth_token'] === '', 'Changing Account SID must not reuse the previous account token.');
$config = $model->prepare(array_replace($post, array('environment' => 'production', 'enabled' => '1')));
provider_assert(count($model->validate($config)) === 3 && $config['production']['auth_token'] === '', 'Production must not inherit Sandbox credentials.');
foreach (array(array('sandbox_auth_token' => "bad\r\nHeader: injected"), array('sandbox_test_recipient' => '09076164920'), array('environment' => 'bad'), array('sandbox_login_content_sid' => 'school_notice')) as $invalid) {
    provider_assert($model->validate($model->prepare(array_replace($post, $invalid))), 'Invalid configuration must fail locally.');
}
$public = $model->publicConfig($model->get());
provider_assert($public['sandbox']['auth_token'] === '' && $public['sandbox']['token_saved'] && $public['production']['auth_token'] === '', 'Public page configuration must not contain tokens.');
$candidate = $model->get();
$candidate['production']['account_sid'] = 'AC' . str_repeat('d', 32);
$candidate['production']['auth_token'] = str_repeat('e', 32);
provider_assert(!$model->publicConfig($candidate, $model->get())['production']['token_saved'], 'An unsaved token must not be displayed as already stored after validation errors.');
$diagnostic = $model->getGatewayConfig('sandbox', true);
provider_assert($diagnostic['enabled'] === 1 && $diagnostic['diagnostic'] && $diagnostic['twilio']['test_recipient'] === '+2348012345678', 'Explicit tests work while school sends are disabled and use the saved test recipient.');
provider_assert(!$model->getGatewayConfig()['enabled'], 'Diagnostic enablement must not change school sending settings.');
$model->db->prefix = 'school_';
provider_assert($model->save($model->get()) && strpos(end($model->db->queries)[0], '`school_whatsapp_twilio_config`') !== false, 'Configured table prefixes must apply to writes.');
$model->db->write_result = false;
provider_assert(!$model->save($model->get()) && strpos($model->getLastError(), 'permissions') !== false && $model->db->db_debug, 'Database failures must be explained without exposing SQL or credentials.');
echo "Twilio configuration tests passed\n";
