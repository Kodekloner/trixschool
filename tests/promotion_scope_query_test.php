<?php

define('BASEPATH', __DIR__);

class MY_Model
{
    public $db;
}

class PromotionScopeQueryResult
{
    private $rowCount;

    public function __construct($rowCount)
    {
        $this->rowCount = (int) $rowCount;
    }

    public function num_rows()
    {
        return $this->rowCount;
    }
}

class PromotionScopeQueryDatabase
{
    public $calls = array();
    private $rowCount;

    public function __construct($rowCount)
    {
        $this->rowCount = (int) $rowCount;
    }

    public function __call($method, array $arguments)
    {
        if ($method === 'count_all_results') {
            throw new RuntimeException('The scope existence query must not use count_all_results().');
        }

        $this->calls[] = array($method, $arguments);
        if ($method === 'get') {
            return new PromotionScopeQueryResult($this->rowCount);
        }

        return $this;
    }
}

function promotion_scope_query_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function promotion_scope_query_model($rowCount, &$database)
{
    $reflection = new ReflectionClass('Promotioncriteria_model');
    $model = $reflection->newInstanceWithoutConstructor();
    $database = new PromotionScopeQueryDatabase($rowCount);
    $model->db = $database;

    return $model;
}

require_once __DIR__ . '/../application/models/Promotioncriteria_model.php';

$database = null;
$model = promotion_scope_query_model(1, $database);
promotion_scope_query_assert(
    $model->studentMatchesScope(43, 19, 1, 5) === true,
    'A matching active student must be accepted in the posted promotion scope.'
);

$selectCalls = array_values(array_filter($database->calls, function ($call) {
    return $call[0] === 'select';
}));
promotion_scope_query_assert(count($selectCalls) === 1, 'The scope query must explicitly select one column.');
promotion_scope_query_assert(
    $selectCalls[0][1][0] === 'student_session.student_id AS matched_student_id',
    'The scope query must select an unambiguous alias instead of joined-table SELECT *.'
);

$getCalls = array_values(array_filter($database->calls, function ($call) {
    return $call[0] === 'get';
}));
promotion_scope_query_assert(count($getCalls) === 1, 'The scope query must execute as a limited existence lookup.');

$database = null;
$model = promotion_scope_query_model(0, $database);
promotion_scope_query_assert(
    $model->studentMatchesScope(43, 19, 1, 5) === false,
    'A student outside the posted promotion scope must be rejected.'
);

echo "promotion scope query tests passed" . PHP_EOL;
