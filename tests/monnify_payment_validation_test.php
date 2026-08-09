<?php

define('BASEPATH', __DIR__);

class MY_Model
{
    public $db;
    public $load;
    public $studentfeemaster_model;
}

require_once __DIR__ . '/../application/models/Monnify_payment_model.php';

function monnify_validation_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

class MonnifyValidationFakeDb
{
    public $begin_count = 0;
    public $commit_count = 0;
    public $rollback_count = 0;

    public function trans_begin()
    {
        $this->begin_count++;
        return true;
    }

    public function trans_commit()
    {
        $this->commit_count++;
        return true;
    }

    public function trans_rollback()
    {
        $this->rollback_count++;
        return true;
    }

    public function trans_status()
    {
        return true;
    }
}

class MonnifyValidationFakeLoader
{
    public function model($name)
    {
        return $name === 'studentfeemaster_model';
    }
}

class MonnifyValidationFakeStudentFees
{
    public $description_exists = false;
    public $deposits = array();

    public function hasPaymentDescription($description)
    {
        return $this->description_exists;
    }

    public function fee_deposit_bulk($bulk_fees)
    {
        $this->deposits[] = $bulk_fees;
        return true;
    }
}

class TestableMonnifyPaymentModel extends Monnify_payment_model
{
    public $ledger_items = array();

    public function processStudentFeeForTest($payment, $context_data)
    {
        return $this->processStudentFee($payment, $context_data);
    }

    protected function findStudentFeeLedgerItemForUpdate($student_id, $student_fees_master_id, $fee_groups_feetype_id)
    {
        $key = $student_fees_master_id . ':' . $fee_groups_feetype_id;
        return isset($this->ledger_items[$key]) ? $this->ledger_items[$key] : null;
    }
}

function monnify_validation_model($ledger_items, $description_exists = false)
{
    $model = new TestableMonnifyPaymentModel();
    $model->db = new MonnifyValidationFakeDb();
    $model->load = new MonnifyValidationFakeLoader();
    $model->studentfeemaster_model = new MonnifyValidationFakeStudentFees();
    $model->studentfeemaster_model->description_exists = $description_exists;
    $model->ledger_items = $ledger_items;
    return $model;
}

function monnify_validation_ledger($student_id, $amount_detail = '0', $fine_amount = 0, $due_date = null)
{
    return (object) array(
        'student_fees_master_id' => 101,
        'fee_groups_feetype_id' => 202,
        'student_id' => $student_id,
        'is_system' => 0,
        'student_fees_master_amount' => '0.00',
        'fee_amount' => '5000.00',
        'fine_amount' => $fine_amount,
        'due_date' => $due_date === null ? date('Y-m-d', strtotime('+1 day')) : $due_date,
        'amount_detail' => $amount_detail,
    );
}

function monnify_validation_student_payment($amount = '5000.00')
{
    return (object) array(
        'payment_reference' => 'MNFY-SF-LEDGER-1',
        'context_id' => 7,
        'amount' => $amount,
    );
}

function monnify_validation_fee_context($amount = 5000, $fine = 0)
{
    return array('fee_items' => array(array(
        'student_fees_master_id' => 101,
        'fee_groups_feetype_id' => 202,
        'amount' => $amount,
        'fine' => $fine,
    )));
}

$model = new Monnify_payment_model();
$method = new ReflectionMethod($model, 'validateTransaction');
$method->setAccessible(true);

$payment = (object) array(
    'payment_reference' => 'MNFY-SF-TEST-1',
    'transaction_reference' => 'MNFY|TEST|1',
    'amount' => '5000.00',
    'currency' => 'NGN',
);
$transaction = array(
    'paymentReference' => 'MNFY-SF-TEST-1',
    'transactionReference' => 'MNFY|TEST|1',
    'paymentStatus' => 'PAID',
    'amountPaid' => 5000,
    'currencyCode' => 'NGN',
);

monnify_validation_assert_same('', $method->invoke($model, $payment, $transaction), 'A fully matching paid transaction must pass verification.');

$underpaid = $transaction;
$underpaid['amountPaid'] = 4999.99;
monnify_validation_assert_same('The amount paid is less than the expected amount.', $method->invoke($model, $payment, $underpaid), 'An underpayment must be rejected.');

$wrong_currency = $transaction;
$wrong_currency['currencyCode'] = 'USD';
monnify_validation_assert_same('The Monnify payment currency does not match.', $method->invoke($model, $payment, $wrong_currency), 'A currency mismatch must be rejected.');

$wrong_reference = $transaction;
$wrong_reference['transactionReference'] = 'MNFY|TEST|2';
monnify_validation_assert_same('The Monnify transaction reference does not match.', $method->invoke($model, $payment, $wrong_reference), 'A transaction-reference mismatch must be rejected.');

$preflight_cross_student_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(8),
));
monnify_validation_assert_same(
    false,
    $preflight_cross_student_model->validateStudentFeePayment(7, monnify_validation_fee_context()['fee_items'], 5000),
    'A cross-student fee selection must be rejected before checkout initialization.'
);
monnify_validation_assert_same(1, $preflight_cross_student_model->db->rollback_count, 'A rejected checkout preflight must release its fee-row locks.');

$preflight_valid_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(7),
));
monnify_validation_assert_same(
    true,
    $preflight_valid_model->validateStudentFeePayment(7, monnify_validation_fee_context()['fee_items'], 5000),
    'A current fee selection owned by the student must pass checkout preflight.'
);
monnify_validation_assert_same(1, $preflight_valid_model->db->commit_count, 'A successful checkout preflight must release its fee-row locks.');

$cross_student_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(8),
), true);
monnify_validation_assert_same(
    false,
    $cross_student_model->processStudentFeeForTest(monnify_validation_student_payment(), monnify_validation_fee_context()),
    'A fee item owned by another student must be rejected even during idempotency recovery.'
);
monnify_validation_assert_same(1, $cross_student_model->db->rollback_count, 'A cross-student ledger claim must roll back its transaction.');
monnify_validation_assert_same(array(), $cross_student_model->studentfeemaster_model->deposits, 'A cross-student ledger claim must never create a fee deposit.');

$prior_deposit = json_encode(array('1' => array(
    'amount' => 1000,
    'amount_discount' => 0,
    'amount_fine' => 0,
)));
$stale_balance_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(7, $prior_deposit),
));
monnify_validation_assert_same(
    false,
    $stale_balance_model->processStudentFeeForTest(monnify_validation_student_payment(), monnify_validation_fee_context()),
    'A delayed transfer must not credit more than the principal balance that remains at fulfillment time.'
);
monnify_validation_assert_same(array(), $stale_balance_model->studentfeemaster_model->deposits, 'A stale principal balance must not create a fee deposit.');

$prior_fine = json_encode(array('1' => array(
    'amount' => 0,
    'amount_discount' => 0,
    'amount_fine' => 50,
)));
$stale_fine_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(7, $prior_fine, 100, date('Y-m-d', strtotime('-1 day'))),
));
monnify_validation_assert_same(
    false,
    $stale_fine_model->processStudentFeeForTest(monnify_validation_student_payment('5100.00'), monnify_validation_fee_context(5000, 100)),
    'A delayed transfer must not credit more than the fine balance that remains at fulfillment time.'
);
monnify_validation_assert_same(array(), $stale_fine_model->studentfeemaster_model->deposits, 'A stale fine balance must not create a fee deposit.');

$valid_ledger_model = monnify_validation_model(array(
    '101:202' => monnify_validation_ledger(7),
));
monnify_validation_assert_same(
    true,
    $valid_ledger_model->processStudentFeeForTest(monnify_validation_student_payment(), monnify_validation_fee_context()),
    'A current fee item owned by the payment student must be deposited.'
);
monnify_validation_assert_same(1, count($valid_ledger_model->studentfeemaster_model->deposits), 'A valid payment must create exactly one bulk ledger deposit.');
$valid_deposit = $valid_ledger_model->studentfeemaster_model->deposits[0][0];
monnify_validation_assert_same(101, $valid_deposit['student_fees_master_id'], 'The validated fee-master ID must be retained in the deposit.');
monnify_validation_assert_same(202, $valid_deposit['fee_groups_feetype_id'], 'The validated fee-type ID must be retained in the deposit.');
monnify_validation_assert_same(5000.0, $valid_deposit['amount_detail']['amount'], 'The validated principal amount must be retained in the deposit.');

echo "monnify payment validation tests passed" . PHP_EOL;
