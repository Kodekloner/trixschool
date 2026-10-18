<?php

// Execute the production controller, model and DataTables queries against an
// isolated in-memory database. No school database or credentials are needed.
define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('ENVIRONMENT', 'testing');
// The bundled CodeIgniter version predates PHP 8.1's null-argument deprecations.
error_reporting(E_ALL & ~E_DEPRECATED);
if (!extension_loaded('sqlite3')) {
    fwrite(STDERR, "student search tests require the sqlite3 PHP extension\n");
    exit(1);
}
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});
function log_message($level, $message) {}
function is_php($version) { return version_compare(PHP_VERSION, $version, '>='); }
function config_item($key) { return $key === 'subclass_prefix' ? 'MY_' : false; }
function show_error($message) { throw new RuntimeException(is_array($message) ? implode('; ', $message) : $message); }
function base_url() { return 'https://school.test/'; }
function form_error($field) { return $field . ' is required'; }
function student_search_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

class StudentSearchServices {
    public function __get($name) { return $GLOBALS['student_search_ci']->$name; }
}
class MY_Model extends StudentSearchServices {}
class Admin_Controller extends StudentSearchServices {}
function &get_instance() { return $GLOBALS['student_search_ci']; }
class StudentSearchInput {
    public $data = array();
    public function post($key) { return isset($this->data[$key]) ? $this->data[$key] : null; }
}
class StudentSearchSettings {
    public function getCurrentSession() { return 1; }
    public function getDateYmd() { return '2026-10-13'; }
}
class StudentSearchCustomFields {
    public $fields = array();
    public function get_custom_fields($table, $visible) { return $this->fields; }
}
class StudentSearchCustomlib {
    public $user = array('id' => 10, 'role_id' => 1, 'class_teacher' => 'no');
    public function getUserData() { return $this->user; }
    public function getSchoolCurrencyFormat() { return 'NGN'; }
    public function getFullName($first, $middle, $last, $showMiddle, $showLast) {
        return trim($first . ($showMiddle ? ' ' . $middle : '') . ($showLast ? ' ' . $last : ''));
    }
    public function dateformat($date) { return $date; }
    public function getSchoolDateFormat() { return 'Y-m-d'; }
    public function dateyyyymmddTodateformat($date) { return strtotime($date); }
}
class StudentSearchTeacher {
    public $sections = array(array('section_id' => 1));
    public function get_teacherrestricted_modeallsections($id) { return $this->sections; }
}
class StudentSearchClassList {
    public function get() { return array(array('id' => 1), array('id' => 2)); }
}
class StudentSearchLanguage { public function line($key) { return $key; } }
class StudentSearchRbac { public function hasPrivilege($name, $action) { return true; } }
class StudentSearchValidation {
    public function set_rules($name, $label, $rules) {}
    public function run() { return !empty(get_instance()->input->data['class_id']); }
}
class StudentSearchLoader extends StudentSearchServices {
    public function view($name, $data, $return) {
        extract($data);
        ob_start();
        include APPPATH . 'views/' . $name . '.php';
        return ob_get_clean();
    }
}

$GLOBALS['student_search_ci'] = (object) array(
    'input' => new StudentSearchInput(), 'setting_model' => new StudentSearchSettings(),
    'customfield_model' => new StudentSearchCustomFields(), 'customlib' => new StudentSearchCustomlib(),
    'teacher_model' => new StudentSearchTeacher(), 'class_model' => new StudentSearchClassList(),
    'lang' => new StudentSearchLanguage(), 'rbac' => new StudentSearchRbac(),
    'form_validation' => new StudentSearchValidation(), 'load' => new StudentSearchLoader()
);
$ci = &get_instance();
require_once BASEPATH . 'database/DB.php';
$ci->db = DB(array('database' => ':memory:', 'dbdriver' => 'sqlite3', 'db_debug' => true), true);
// Match the production MySQL driver's identifier quoting; SQLite also accepts it.
$quoteCharacter = new ReflectionProperty('CI_DB_driver', '_escape_char');
$quoteCharacter->setAccessible(true);
$quoteCharacter->setValue($ci->db, '`');
$ci->db->conn_id->createFunction('CONCAT_WS', function ($separator, ...$values) {
    return implode($separator, array_filter($values, function ($value) { return $value !== null; }));
}, -1);
require_once APPPATH . 'libraries/Datatables.php';
require_once APPPATH . 'models/Student_model.php';

// Load the real controller class without its unrelated server-specific AWS and
// dotenv bootstrap, which runs before the class definition in Student.php.
$controllerSource = file_get_contents(APPPATH . 'controllers/Student.php');
$classStart = strpos($controllerSource, 'class Student extends Admin_Controller');
student_search_assert($classStart !== false, 'Student controller class was not found.');
eval(substr($controllerSource, $classStart));
$controller = (new ReflectionClass('Student'))->newInstanceWithoutConstructor();
$settings = (object) array('father_name' => 1, 'category' => 1, 'mobile_no' => 1,
    'middlename' => 1, 'lastname' => 1, 'student_photo' => 0, 'guardian_name' => 0);
$controller->sch_setting_detail = $settings;
$ci->student_model = (new ReflectionClass('Student_model'))->newInstanceWithoutConstructor();
$ci->student_model->current_session = 1;

$studentColumns = array('admission_no', 'roll_no', 'admission_date', 'firstname', 'middlename', 'lastname',
    'image', 'mobileno', 'email', 'state', 'city', 'pincode', 'religion', 'dob', 'current_address',
    'permanent_address', 'category_id', 'adhar_no', 'samagra_id', 'bank_account_no', 'bank_name', 'ifsc_code',
    'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_address', 'is_active', 'created_at',
    'updated_at', 'father_name', 'rte', 'gender', 'school_house_id', 'cast', 'blood_group', 'father_phone',
    'father_occupation', 'mother_name', 'mother_phone', 'mother_occupation', 'guardian_occupation',
    'guardian_email', 'previous_school', 'note', 'app_key', 'parent_app_key');
$ci->db->query('CREATE TABLE students (id INTEGER PRIMARY KEY, ' . implode(', ', array_map(function ($column) {
    return $column . " TEXT DEFAULT ''";
}, $studentColumns)) . ')');
$ci->db->query('CREATE TABLE classes (id INTEGER PRIMARY KEY, class TEXT)');
$ci->db->query("INSERT INTO classes VALUES (1, 'Basic 1'), (2, 'Basic 2')");
$ci->db->query('CREATE TABLE sections (id INTEGER PRIMARY KEY, section TEXT)');
$ci->db->query("INSERT INTO sections VALUES (1, 'A'), (2, 'B')");
$ci->db->query('CREATE TABLE categories (id INTEGER PRIMARY KEY, category TEXT)');
$ci->db->query("INSERT INTO categories VALUES (1, 'General')");
$ci->db->query('CREATE TABLE school_houses (id INTEGER PRIMARY KEY, house_name TEXT)');
$ci->db->query("INSERT INTO school_houses VALUES (1, 'Blue')");
$ci->db->query('CREATE TABLE student_session (id INTEGER PRIMARY KEY, student_id INTEGER, session_id INTEGER, class_id INTEGER, section_id INTEGER)');
$ci->db->query('CREATE TABLE custom_field_values (belong_table_id INTEGER, custom_field_id INTEGER, field_value TEXT)');

$fixtureRows = array(
    array(1, 'Ada', 'Mary', 'Okafor', 'ENR-001', '10', 'NAT-001', 'LOC-001', 1, 1, 1, 'yes'),
    array(2, 'Ada', '', 'Bello', 'ENR-002', '20', 'NAT-002', 'LOC-002', 1, 1, 1, 'yes'),
    array(3, 'Chidi', null, "O'Neil", 'ENR-003', '30', 'NAT-003', 'LOC-003', 2, 2, 1, 'yes'),
    array(4, 'Ada', '', 'Old', 'ENR-004', '40', 'NAT-004', 'LOC-004', 1, 1, 2, 'yes'),
    array(5, 'Ada', '', 'Disabled', 'ENR-005', '50', 'NAT-005', 'LOC-005', 1, 1, 1, 'no'),
    array(6, 'Literal%_!', '', 'Student', 'ENR-006', '60', 'NAT-006', 'LOC-006', 1, 2, 1, 'yes')
);
foreach ($fixtureRows as $fixture) {
    list($id, $first, $middle, $last, $admission, $roll, $national, $local, $class, $section, $session, $active) = $fixture;
    $ci->db->insert('students', array('id' => $id, 'firstname' => $first, 'middlename' => $middle,
        'lastname' => $last, 'admission_no' => $admission, 'roll_no' => $roll, 'adhar_no' => $national,
        'samagra_id' => $local, 'is_active' => $active, 'category_id' => 1, 'school_house_id' => 1,
        'gender' => 'Female', 'dob' => '2015-01-01'));
    $ci->db->insert('student_session', array('id' => $id, 'student_id' => $id,
        'session_id' => $session, 'class_id' => $class, 'section_id' => $section));
}
// A past enrollment of an active student must not duplicate current results.
$ci->db->insert('student_session', array('id' => 7, 'student_id' => 1, 'session_id' => 2, 'class_id' => 2, 'section_id' => 2));

function student_search_request($params, $tableSearch = '', $start = 0, $length = 10, $order = array()) {
    $ci = &get_instance();
    $ci->input->data = array_merge(array('draw' => 7, 'start' => $start, 'length' => $length,
        'search' => array('value' => $tableSearch), 'order' => $order, 'columns' => array()), $params);
    $ci->datatables = new Datatables();
}
function student_search_validation($controller, $params) {
    student_search_request($params);
    ob_start();
    $controller->searchvalidation();
    $result = json_decode(ob_get_clean(), true);
    student_search_assert(is_array($result), 'Validation must return valid JSON.');
    return $result;
}
function student_search_model_results($method, $arguments) {
    $result = json_decode(call_user_func_array(array(get_instance()->student_model, $method), $arguments), true);
    student_search_assert(is_array($result), 'Search must return valid DataTables JSON.');
    student_search_assert($result['draw'] === 7, 'Search must preserve the DataTables draw.');
    return $result;
}
function student_search_ids($result) { return array_map('intval', array_column($result['data'], 'id')); }

$keyword = student_search_validation($controller, array('search_type' => 'search_full', 'search_text' => '  Ada  ', 'class_id' => 2, 'section_id' => 2));
student_search_assert($keyword['params']['search_text'] === 'Ada', 'The keyword must survive validation and be trimmed.');
student_search_assert(!isset($keyword['params']['class_id']), 'Keyword searches must not retain stale class filters.');
$class = student_search_validation($controller, array('search_type' => 'search_filter', 'class_id' => 1, 'section_id' => 1));
student_search_assert($class['params']['srch_type'] === 'search_filter', 'The selected search mode must survive validation.');
$invalid = student_search_validation($controller, array('search_type' => 'search_filter', 'class_id' => ''));
student_search_assert($invalid['status'] === 0, 'A class search must still require a class.');

student_search_request($keyword['params']);
$result = student_search_model_results('searchFullText', array($keyword['params']['search_text'], array(1, 2), $settings));
student_search_assert(student_search_ids($result) === array(1, 2), 'Keyword matching must exclude unrelated, inactive and past-session students.');
foreach (array('Ada Mary Okafor' => 1, 'Ada Okafor' => 1, 'Ada Bello' => 2, '30' => 3,
    'ENR-003' => 3, 'NAT-003' => 3, 'LOC-003' => 3, "O'Neil" => 3, '%_!' => 6) as $term => $expectedId) {
    student_search_request(array());
    $result = student_search_model_results('searchFullText', array((string) $term, array(1, 2), $settings));
    student_search_assert(student_search_ids($result) === array($expectedId), 'Keyword failed: ' . $term);
    student_search_request(array(), (string) $term);
    $result = student_search_model_results('searchFullText', array('', array(1, 2), $settings));
    student_search_assert(student_search_ids($result) === array($expectedId), 'Table search failed without custom fields: ' . $term);
}
student_search_request(array(), 'Bello');
$result = student_search_model_results('searchFullText', array('Ada', array(1, 2), $settings));
student_search_assert(student_search_ids($result) === array(2), 'Table search must narrow the submitted keyword.');
student_search_request(array(), 'Chidi');
$result = student_search_model_results('searchdtByClassSection', array(1, null, $settings));
student_search_assert(student_search_ids($result) === array(), 'Table search must retain the class filter.');
student_search_request(array(), 'Ada Bello');
$result = student_search_model_results('searchdtByClassSection', array(1, 1, $settings));
student_search_assert(student_search_ids($result) === array(2), 'Class-filtered table search must match full names.');
student_search_request(array(), '', 1, 1);
$result = student_search_model_results('searchFullText', array('Ada', array(1, 2), $settings));
student_search_assert(student_search_ids($result) === array(2) && $result['recordsFiltered'] === 2, 'Pagination must retain keyword filtering and matching counts.');
student_search_request(array(), 'missing student');
$result = student_search_model_results('searchFullText', array('', array(1, 2), $settings));
student_search_assert($result['data'] === array() && $result['recordsFiltered'] === 0, 'No matches must produce an empty valid response.');

$ci->customfield_model->fields = array((object) array('id' => 8, 'name' => 'House Note', 'type' => 'text'));
$ci->db->insert('custom_field_values', array('belong_table_id' => 1, 'custom_field_id' => 8, 'field_value' => 'Sapphire'));
$ci->db->insert('custom_field_values', array('belong_table_id' => 2, 'custom_field_id' => 8, 'field_value' => 'Amber'));
foreach (array('searchFullText' => array('', array(1, 2), $settings), 'searchdtByClassSection' => array(1, 1, $settings)) as $method => $arguments) {
    student_search_request(array(), 'Sapphire');
    $result = student_search_model_results($method, $arguments);
    student_search_assert(student_search_ids($result) === array(1) && $result['data'][0]['House Note'] === 'Sapphire', 'Custom fields must search real columns and retain their display aliases.');
    student_search_request(array(), '', 0, 10, array(array('column' => 8, 'dir' => 'asc')));
    $result = student_search_model_results($method, $arguments);
    student_search_assert(array_slice(student_search_ids($result), -2) === array(2, 1), 'Custom-field sorting must follow the field values.');
}
student_search_request($keyword['params'], 'Sapphire');
ob_start();
$controller->dtstudentlist();
$customJson = json_decode(ob_get_clean(), true);
student_search_assert(array_column($customJson['data'], 0) === array('ENR-001') && $customJson['data'][0][8] === 'Sapphire', 'The JSON endpoint must render custom fields whose names contain spaces.');
$ci->customfield_model->fields = array();
$minimalSettings = clone $settings;
$minimalSettings->father_name = $minimalSettings->category = $minimalSettings->mobile_no = 0;
student_search_request(array(), '', 0, 10, array(array('column' => 3, 'dir' => 'desc')));
student_search_model_results('searchdtByClassSection', array(1, 1, $minimalSettings));
student_search_assert(strpos($ci->db->last_query(), 'ORDER BY `students`.`dob` DESC') !== false, 'Sorting must follow the visible columns when optional columns are hidden.');

$ci->customlib->user = array('id' => 10, 'role_id' => 2, 'class_teacher' => 'yes');
student_search_request(array(), 'Chidi');
$result = student_search_model_results('searchFullText', array('', array(1), $settings));
student_search_assert($result['data'] === array(), 'Table search must respect teacher class and section restrictions.');
$ci->teacher_model->sections = array();
student_search_request(array());
$result = student_search_model_results('searchFullText', array('', array(1), $settings));
student_search_assert($result['data'] === array(), 'Teachers with no permitted sections must get an empty valid response.');
$ci->customlib->user = array('id' => 10, 'role_id' => 1, 'class_teacher' => 'no');

// Exercise the actual JSON endpoint and detail-view rendering with the parameters
// returned by validation, including a subsequent DataTables filtering request.
foreach (array('' => array('ENR-001', 'ENR-002'), 'Bello' => array('ENR-002'), 'missing' => array()) as $tableTerm => $expected) {
    student_search_request($keyword['params'], $tableTerm);
    ob_start();
    $controller->dtstudentlist();
    $json = json_decode(ob_get_clean(), true);
    student_search_assert(is_array($json) && array_column($json['data'], 0) === $expected, 'The results endpoint must honor both search inputs.');
    student_search_assert(isset($json['student_detail_view']), 'List and detail views must share the same result response.');
}

// Shared-library regression: tolerate an empty entry left by older callers and
// treat SQL wildcard characters in the user's search as literal text.
student_search_request(array(), '%_!');
$ci->datatables->select('students.id,students.firstname')->searchable('students.firstname, ')->from('students');
$result = json_decode($ci->datatables->generate('json'), true);
student_search_assert(student_search_ids($result) === array(6), 'Shared DataTables filtering must ignore empty columns and escape literals once.');
student_search_request(array(), 'ada');
$ci->datatables->select('students.id,students.firstname')->searchable('students.firstname AS student_name,')->from('students');
$result = json_decode($ci->datatables->generate('json'), true);
student_search_assert(student_search_ids($result) === array(1, 2, 4, 5), 'Shared DataTables filtering must continue accepting uppercase SQL aliases.');

echo "student search tests passed\n";
