<?php

class Admin_Controller
{
    public $rbac;
    public $input;
    public $form_validation;
    public $lang;
    public $setting_model;
    public $session;
    public $language_model;
    public $customlib;
    public $session_model;
    public $load;
}

function settings_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function set_language($language_id)
{
}

require_once __DIR__ . '/../application/controllers/Schsettings.php';

$view = file_get_contents(__DIR__ . '/../application/views/setting/settingList.php');
$retired_fields = array('biometric', 'biometric_device', 'my_question');
foreach ($retired_fields as $field) {
    settings_assert(strpos($view, 'name="' . $field . '"') === false, 'Remove the retired settings field ' . $field . '.');
}
settings_assert(strpos($view, "line('online_examination')") === false, 'Remove the empty Online Examination settings section.');
settings_assert(strpos($view, 'name="attendence_type"') !== false, 'Keep the day-wise / period-wise attendance setting.');
settings_assert(strpos($view, 'name="class_teacher"') !== false, 'Keep the class teacher setting.');

$reflection = new ReflectionClass('Schsettings');
$controller = $reflection->newInstanceWithoutConstructor();
$controller->rbac = new class {
    public function hasPrivilege($resource, $action)
    {
        settings_assert($resource === 'general_setting' && $action === 'can_edit', 'General Settings saves must check edit permission.');
        return true;
    }
};
$controller->input = new class {
    public $values;

    public function post($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : null;
    }
};
$controller->form_validation = new class {
    public function set_rules($field, $label, $rules)
    {
    }

    public function run()
    {
        return true;
    }
};
$controller->lang = new class {
    public function line($key)
    {
        return $key;
    }
};
$controller->setting_model = new class {
    public $stored;
    public $saved;

    public function getSetting()
    {
        return (object) $this->stored;
    }

    public function add($data)
    {
        $this->saved = $data;
        $this->stored = array_replace($this->stored, $data);
    }

    public function get_stafflang($staff_id)
    {
        return array('lang_id' => 0);
    }
};
$controller->session = new class {
    public $userdata = array('admin' => array('id' => 1));

    public function set_userdata($key, $value)
    {
        $this->userdata[$key] = $value;
    }

    public function userdata($key)
    {
        return $this->userdata[$key];
    }
};
$controller->language_model = new class {
    public function get($id)
    {
        return array('short_code' => 'en');
    }
};
$controller->customlib = new class {
    public function get_rtl_languages($short_code)
    {
        return false;
    }
};
$controller->session_model = new class {
    public function get($id)
    {
        return array('id' => $id, 'session' => '2026/2027');
    }
};
$controller->load = new class {
    public function helper($name)
    {
    }
};

foreach (array(0, 1) as $legacy_value) {
    foreach (array(false, true) as $stale_form) {
        $original = array(
            'id' => 1,
            'biometric' => $legacy_value,
            'biometric_device' => 'LEGACY-TERMINAL',
            'my_question' => $legacy_value,
        );
        $controller->setting_model->stored = $original;
        $controller->setting_model->saved = null;
        $controller->input->values = array(
            'sch_id' => 1,
            'sch_name' => 'Updated School',
            'sch_session_id' => 22,
            'term' => '1st',
            'sch_lang_id' => 1,
            'sch_start_week' => 'Monday',
            'attendence_type' => '1',
            'class_teacher' => 'yes',
        );
        if ($stale_form) {
            $controller->input->values['biometric'] = 1 - $legacy_value;
            $controller->input->values['biometric_device'] = 'REPLACEMENT';
            $controller->input->values['my_question'] = 1 - $legacy_value;
        }

        ob_start();
        $controller->ajax_schedit();
        $response = json_decode(ob_get_clean(), true);

        settings_assert(isset($response['status']) && $response['status'] === 'success', 'General Settings must save successfully after removing the controls.');
        $saved = $controller->setting_model->saved;
        settings_assert($saved['name'] === 'Updated School' && $saved['attendence_type'] === '1' && $saved['class_teacher'] === 'yes', 'Active settings must still be saved.');
        foreach ($retired_fields as $field) {
            settings_assert(!array_key_exists($field, $saved), 'General Settings must omit retired field ' . $field . ' from updates.');
            settings_assert($controller->setting_model->stored[$field] === $original[$field], 'Keep the stored value for ' . $field . ' when forms omit or submit retired controls.');
        }
    }
}

echo "general settings legacy controls tests passed" . PHP_EOL;
