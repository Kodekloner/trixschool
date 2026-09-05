<?php

// Exercise the real controller/model entry points with a schema that has no level.
define('BASEPATH', __DIR__);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

class CI_Model
{
    public $db;
    public $customlib;
    public $datatables;
}
class MY_model extends CI_Model {}
class Admin_Controller
{
    public $input;
    public $rbac;
    public $form_validation;
    public $question_model;
    public $customlib;
    public $lang;
    public $config;
}

function level_free_assert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function access_denied() { throw new RuntimeException('Unexpected access denial.'); }
function form_error($field) { return $field . ' is invalid'; }
function site_url($path) { return 'https://school.test/' . $path; }
function base_url() { return site_url(''); }
function html_escape($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function readmorelink($text, $url) { return '<a href="' . $url . '">' . html_escape($text) . '</a>'; }

class LevelFreeInput
{
    public $data;
    public function __construct(array $data) { $this->data = $data; }
    public function post($field) { return isset($this->data[$field]) ? $this->data[$field] : null; }
}

class LevelFreeValidation
{
    private $input;
    private $rules = array();
    public function __construct($input) { $this->input = $input; }
    public function set_rules($field, $label, $rules) { $this->rules[$field] = explode('|', $rules); }
    public function run()
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->input->post(str_replace('[]', '', $field));
            if (in_array('required', $rules, true) && ($value === null || $value === '' || $value === array())) {
                return false;
            }
        }
        return true;
    }
}

class LevelFreeQuestionStore
{
    public $writes = array();
    public $row;
    public $accessChecks = array();
    public function canAccessQuestion($id) { $this->accessChecks[] = $id; return true; }
    public function canAccessQuestionScope($class, $section) { return $class === 4 && $section === 2; }
    public function add($data)
    {
        level_free_assert(!array_key_exists('question_level', $data), 'Question writes must not use the removed database column.');
        $this->writes[] = $data;
        return 17;
    }
    public function getAllRecord()
    {
        return json_encode(array('draw' => 3, 'recordsTotal' => 1, 'recordsFiltered' => 1, 'data' => array($this->row)));
    }
}

// The query double rejects any reference to the removed column, as MySQL would.
class LevelFreeQuery
{
    public $calls = array();
    public $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function __call($method, array $arguments)
    {
        level_free_assert(!preg_match('/question_level|questions[.`]+level\b/i', json_encode($arguments)), 'Query still references the removed level column: ' . $method);
        $this->calls[] = array($method, $arguments);
        if ($method === 'escape') { return "'" . str_replace("'", "''", $arguments[0]) . "'"; }
        if ($method === 'result') { return $this->rows; }
        if ($method === 'num_rows') { return count($this->rows); }
        if ($method === 'generate') { return json_encode($this->rows); }
        return $this;
    }
    public function argumentsFor($method)
    {
        return array_values(array_map(function ($call) { return $call[1]; }, array_filter($this->calls, function ($call) use ($method) { return $call[0] === $method; })));
    }
}

require_once __DIR__ . '/../application/controllers/admin/Question.php';
require_once __DIR__ . '/../application/models/Question_model.php';
require_once __DIR__ . '/../application/models/Onlineexam_model.php';
require_once __DIR__ . '/../application/models/Onlineexamquestion_model.php';

$customlib = new class {
    public function getStaffID() { return 9; }
    public function getUserData() { return array('role_id' => 1); }
};
$controller = (new ReflectionClass('Question'))->newInstanceWithoutConstructor();
$controller->rbac = new class {
    public $checks = array();
    public function hasPrivilege($module, $action) { $this->checks[] = array($module, $action); return true; }
};
$controller->lang = new class { public function line($key) { return $key; } };
$controller->config = new class {
    public function item($key)
    {
        level_free_assert($key === 'question_type', 'The controller must not request the retired level configuration.');
        return array('singlechoice' => 'Single Choice');
    }
};
$controller->customlib = $customlib;
$controller->question_model = new LevelFreeQuestionStore();
$base = array('subject_id' => 6, 'class_id' => 4, 'section_id' => 2, 'question_type' => 'singlechoice', 'question' => 'What is 2 + 2?', 'opt_a' => '4', 'opt_b' => '5', 'correct' => 'opt_a');

foreach (array(0, 17) as $id) {
    $controller->input = new LevelFreeInput(array_merge($base, array('recordid' => $id)));
    $controller->form_validation = new LevelFreeValidation($controller->input);
    ob_start();
    $controller->add();
    $response = json_decode(ob_get_clean(), true);
    level_free_assert($response['status'] === 1, 'Creating and editing a question must succeed without a level.');
    $written = end($controller->question_model->writes);
    level_free_assert($written['correct'] === 'opt_a' && $written['opt_a'] === '4' && $written['question'] === $base['question'], 'Question content and answer must survive saving.');
    level_free_assert(($id === 0 && !isset($written['id'])) || ($id === 17 && $written['id'] === 17), 'Create/edit must retain their original record behavior.');
}
level_free_assert($controller->question_model->accessChecks === array(17), 'Editing must retain its question ownership check.');
level_free_assert(in_array(array('question_bank', 'can_add'), $controller->rbac->checks, true) && in_array(array('question_bank', 'can_edit'), $controller->rbac->checks, true), 'Removing level must preserve add/edit authorization.');

// A stale browser may still submit level; it must never reach the database.
$controller->input = new LevelFreeInput(array_merge($base, array('question_level' => 'hard')));
$controller->form_validation = new LevelFreeValidation($controller->input);
ob_start();
$controller->add();
level_free_assert(json_decode(ob_get_clean(), true)['status'] === 1, 'A stale level field must not break a valid question save.');

// Removing level must not accidentally remove the remaining required validation.
$invalid = $base;
unset($invalid['question']);
$controller->input = new LevelFreeInput($invalid);
$controller->form_validation = new LevelFreeValidation($controller->input);
$before = count($controller->question_model->writes);
ob_start();
$controller->add();
level_free_assert(json_decode(ob_get_clean(), true)['status'] === 0 && count($controller->question_model->writes) === $before, 'Missing question text must remain rejected.');

$row = (object) array('id' => 17, 'name' => 'Mathematics', 'question_type' => 'singlechoice', 'question' => 'What is 2 + 2?');
$controller->question_model->row = $row;
ob_start();
$controller->getDatatable();
$table = json_decode(ob_get_clean(), true);
level_free_assert($table['draw'] === 3 && $table['recordsTotal'] === 1 && count($table['data']) === 1, 'DataTables response must preserve pagination metadata.');
$cells = $table['data'][0];
level_free_assert(count($cells) === 6, 'Question Bank rows must contain exactly six cells after level removal.');
level_free_assert(strpos($cells[0], 'data-question-id=\'17\'') !== false && $cells[1] === 17 && $cells[2] === 'Mathematics' && $cells[3] === 'Single Choice' && strpos($cells[4], $row->question) !== false && strpos($cells[5], 'question-bank-row-actions') !== false, 'Checkbox, ID, subject, type, question and actions must align with their headers.');

$questionModel = (new ReflectionClass('Question_model'))->newInstanceWithoutConstructor();
$questionModel->customlib = $customlib;
$questionModel->datatables = new LevelFreeQuery(array($row));
$questionModel->getAllRecord();
$orderable = $questionModel->datatables->argumentsFor('orderable');
level_free_assert(count($orderable) === 1, 'Question Bank must provide one DataTables sorting map.');
$columns = explode(',', $orderable[0][0]);
level_free_assert($columns === array('questions.id', 'questions.id', 'subjects.name', 'questions.question_type', 'questions.question', 'questions.id'), 'Sorting indexes must align with the six visible columns, particularly question text at index four.');
$searchable = $questionModel->datatables->argumentsFor('searchable');
level_free_assert(strpos($searchable[0][0], 'questions.question') !== false, 'Question text must remain searchable.');

$examModel = (new ReflectionClass('Onlineexam_model'))->newInstanceWithoutConstructor();
foreach (array(false, true) as $random) {
    $examModel->db = new LevelFreeQuery(array($row));
    level_free_assert($examModel->getExamQuestions(3, $random) === array($row), 'Exam delivery must read questions without level in either ordering mode.');
    level_free_assert(in_array(array('onlineexam_questions.onlineexam_id', 3), $examModel->db->argumentsFor('where'), true), 'Exam delivery must retain its assessment boundary.');
}

$searchModel = (new ReflectionClass('Onlineexamquestion_model'))->newInstanceWithoutConstructor();
$filters = array('subject' => 6, 'keyword' => '2 + 2', 'question_type' => 'singlechoice', 'class_id' => 4, 'section_id' => 2, 'question_level' => 'hard');
foreach (array('getByExamID', 'getCountByExamID') as $method) {
    $searchModel->db = new LevelFreeQuery(array($row));
    $result = $method === 'getByExamID' ? $searchModel->$method(3, 20, 0, $filters) : $searchModel->$method(3, $filters);
    level_free_assert($result === ($method === 'getByExamID' ? array($row) : 1), 'Question selection and its count must work after dropping level.');
    $where = $searchModel->db->argumentsFor('where');
    foreach (array(array('subjects.id', 6), array('question_type', 'singlechoice'), array('class_id', 4), array('section_id', 2)) as $expected) {
        level_free_assert(in_array($expected, $where, true), 'Removing level must retain the academic/question-type filters.');
    }
    level_free_assert(in_array(array('question', '2 + 2'), $searchModel->db->argumentsFor('like'), true), 'Keyword search must remain functional.');
}

restore_error_handler();
echo "question bank without level behavioral tests passed" . PHP_EOL;
