<?php

define('BASEPATH', __DIR__);

class CI_Model
{
}

require_once __DIR__ . '/../application/models/Onlineexamattempt_model.php';

class TestableOnlineexamAttemptModel extends Onlineexamattempt_model
{
    public function equalResponse($response, $correct, $type)
    {
        return $this->responsesEqual($response, $correct, $type);
    }
}

function response_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$model = new TestableOnlineexamAttemptModel();

response_assert($model->equalResponse(
    array('left_1' => 'right_2', 'left_2' => 'right_1'),
    array('left_2' => 'right_1', 'left_1' => 'right_2'),
    'matching'
), 'Matching maps should compare by their stable left/right identifiers.');
response_assert(!$model->equalResponse(
    array('left_1' => 'right_1', 'left_2' => 'right_2'),
    array('left_1' => 'right_2', 'left_2' => 'right_1'),
    'matching'
), 'An incorrect matching map must not pass.');

response_assert($model->equalResponse(array('item_2', 'item_1'), array('item_2', 'item_1'), 'ordering'), 'Ordering must preserve position.');
response_assert(!$model->equalResponse(array('item_1', 'item_2'), array('item_2', 'item_1'), 'ordering'), 'A different order must fail.');

response_assert($model->equalResponse('12.59', array('value' => 12.5, 'tolerance' => 0.1), 'numeric'), 'A numeric response inside tolerance should pass.');
response_assert(!$model->equalResponse('12.61', array('value' => 12.5, 'tolerance' => 0.1), 'numeric'), 'A numeric response outside tolerance should fail.');

response_assert($model->equalResponse('  FCT ', array('Abuja', 'fct'), 'short_answer'), 'Short-answer alternatives should ignore case and surrounding spaces.');
response_assert(!$model->equalResponse('Lagos', array('Abuja', 'fct'), 'short_answer'), 'An unlisted short answer must fail.');

echo "onlineexam response type tests passed" . PHP_EOL;
