<?php

define('BASEPATH', __DIR__);
require_once __DIR__ . '/../application/libraries/Biometric_attendance_service.php';

function log_message($level, $message) { $GLOBALS['delivery_logs'][] = $message; }
function delivery_assert($condition, $message) {
    if (!$condition) { fwrite(STDERR, $message . PHP_EOL); exit(1); }
    $GLOBALS['delivery_assertions']++;
}
$GLOBALS['delivery_assertions'] = 0;

class DeliveryResult {
    private $rows;
    public function __construct($rows) { $this->rows = $rows; }
    public function row_array() { return $this->rows ? reset($this->rows) : array(); }
    public function result_array() { return array_values($this->rows); }
}
class DeliveryDb {
    public $student;
    public $fields = array();
    public $queue = array();
    public $customQueries = array();
    public $beforeClaim;
    private $where = array();
    private $table;
    private $limitValue = 100;
    private $affected = 0;
    public function table_exists($table) { return true; }
    public function select($fields, $escape = null) { return $this; }
    public function from($table) { $this->table = $table; return $this; }
    public function join($table, $on, $type = null) { return $this; }
    public function where($field, $value = null, $escape = null) { $this->where[$field] = $value; return $this; }
    public function where_in($field, $values) { $this->where[$field] = $values; return $this; }
    public function or_where($field, $value = null) { $this->where[$field] = $value; return $this; }
    public function group_start() { return $this; }
    public function group_end() { return $this; }
    public function order_by($field, $order) { return $this; }
    public function limit($value) { $this->limitValue = $value; return $this; }
    public function get($table = null) {
        $table = $table ?: $this->table;
        $where = $this->where;
        $this->where = array();
        if ($table === 'student_session') {
            return new DeliveryResult(array($this->student));
        }
        if ($table === 'custom_fields') {
            $this->customQueries[] = $where;
            $rows = array_filter($this->fields, function ($row) use ($where) {
                return $row['belong_to'] === $where['custom_fields.belong_to']
                    && $row['student_id'] === $where['custom_field_values.belong_table_id'];
            });
            return new DeliveryResult($rows);
        }
        $integrationId = null;
        foreach ($where as $key => $value) {
            if (preg_match('/integration_id = ([0-9]+)/', $key, $matches)) { $integrationId = (int) $matches[1]; }
        }
        $rows = array_filter($this->queue, function ($row) use ($integrationId) {
            return in_array($row['status'], array('pending', 'retry'), true)
                && ($row['next_attempt_at'] === null || $row['next_attempt_at'] <= '2026-10-12 12:00:00')
                && ($integrationId === null || $row['integration_id'] === $integrationId);
        });
        return new DeliveryResult(array_slice($rows, 0, $this->limitValue));
    }
    public function update($table, $data) {
        $where = $this->where;
        $this->where = array();
        $this->affected = 0;
        if (isset($data['status']) && $data['status'] === 'processing' && $this->beforeClaim) {
            $callback = $this->beforeClaim;
            $this->beforeClaim = null;
            $callback($this);
        }
        foreach ($this->queue as &$row) {
            if (isset($where['id']) && $where['id'] !== $row['id']) { continue; }
            if (isset($where['status'])) {
                $states = is_array($where['status']) ? $where['status'] : array($where['status']);
                if (!in_array($row['status'], $states, true)) { continue; }
            }
            if (isset($where['updated_at <']) && $row['updated_at'] >= $where['updated_at <']) { continue; }
            if (isset($where['attempt_count']) && $row['attempt_count'] !== $where['attempt_count']) { continue; }
            if (isset($where['next_attempt_at <=']) && $row['next_attempt_at'] !== null
                && $row['next_attempt_at'] > $where['next_attempt_at <=']) { continue; }
            $row = array_merge($row, $data);
            $this->affected++;
        }
        return true;
    }
    public function affected_rows() { return $this->affected; }
}
class DeliveryLoader {
    public function model($name) {}
    public function library($name) {}
}
class DeliveryMailer {
    public $sent = array();
    public $result = true;
    public function send_mail($recipient, $subject, $body) {
        $this->sent[] = array($recipient, $subject, $body);
        return $this->result;
    }
}
class DeliverySetting {
    public function getSetting() { return (object) array('name' => 'Test School', 'currency_symbol' => 'NGN'); }
    public function getCurrentSessionName() { return '2026-27'; }
}
class DeliveryEventModel {
    public $event;
    public function getEvent($id) { return $this->event; }
}
class DeliveryService extends Biometric_attendance_service {
    public $settings = array('notify_email' => 1, 'notify_sms' => 1, 'notify_whatsapp' => 1,
        'notify_student_in' => 1, 'notify_student_out' => 1);
    public $templateEnabled = true;
    public $balance = 0;
    public $queueResults = null;
    public $queueCalls = array();
    public $delayFirst = false;
    public function __construct($ci, $model) { $this->CI = $ci; $this->model = $model; }
    public function getSettings() { return $this->settings; }
    public function deliver($row) { return parent::deliverGuardianNotification($row); }
    public function contact($student) { return $this->guardianEmailContact($student); }
    protected function notificationTemplate($type) {
        return array('is_mail' => $this->templateEnabled, 'is_sms' => 1, 'is_whatsapp' => 1,
            'template_id' => '', 'template' => 'Hello {{student_name}} {{admission_no}} {{attendance_action}} {{outstanding_amount}}',
            'subject' => '{{school_name}} {{attendance_direction}}');
    }
    protected function studentFeeBalance($id) { return array('outstanding' => $this->balance, 'item_count' => 1); }
    protected function now() { return '2026-10-12 12:00:00'; }
    protected function redactGatewayText($message) { return $message; }
    protected function deliverGuardianNotification(array $row) {
        if ($this->queueResults !== null) {
            $this->queueCalls[] = $row['id'];
            if ($this->delayFirst && count($this->queueCalls) === 1) { usleep(1100000); }
            $result = $this->queueResults[$row['id']];
            if ($result instanceof Throwable) { throw $result; }
            return $result;
        }
        return parent::deliverGuardianNotification($row);
    }
}
function field($name, $value, $id = 92, $belongs = 'students') {
    return array('name' => $name, 'field_value' => $value, 'student_id' => $id, 'belong_to' => $belongs);
}
function queue_item($id, $integration = 2) {
    return array('id' => $id, 'event_id' => 7, 'channel' => 'email', 'notification_type' => 'biometric_attendance_in',
        'integration_id' => $integration, 'status' => 'pending', 'attempt_count' => 0,
        'next_attempt_at' => null, 'updated_at' => '2026-10-12 12:00:00');
}
$ci = (object) array('db' => new DeliveryDb(), 'load' => new DeliveryLoader(),
    'mailer' => new DeliveryMailer(), 'setting_model' => new DeliverySetting());
$ci->db->student = array('student_id' => 92, 'guardian_email' => '', 'guardian_phone' => '',
    'guardian_name' => '', 'student_name' => 'OSAGIE KACHY', 'admission_no' => '0987');
$model = new DeliveryEventModel();
$model->event = array('id' => 7, 'operating_mode' => 'live', 'processing_status' => 'accepted',
    'projection_status' => 'projected', 'subject_type' => 'student', 'subject_id' => 177,
    'direction' => 'IN', 'occurred_at_local' => '2026-10-12 13:20:01');
$service = new DeliveryService($ci, $model);
$row = queue_item(1);

$ci->db->fields = array(field("Parent's Email", 'parent@example.test'),
    field("Parent's Email", 'other-student@example.test', 177), field('Guardian Email', 'staff@example.test', 92, 'staff'));
$delivery = $service->deliver($row);
delivery_assert($delivery['success'], 'GIS custom Parent email must deliver when Guardian Email is blank.');
delivery_assert($ci->mailer->sent[0][0] === 'parent@example.test', 'Custom contacts must join by students.id, not student_session.id.');
delivery_assert($ci->db->customQueries[0]['custom_fields.belong_to'] === 'students', 'Only student custom fields may be read.');
delivery_assert(strpos($ci->mailer->sent[0][2], 'OSAGIE KACHY 0987 checked in') !== false, 'Attendance template must render unchanged.');

$ci->db->student['guardian_email'] = 'authoritative@example.test';
delivery_assert($service->contact($ci->db->student)['recipient'] === 'authoritative@example.test', 'Standard guardian email must take precedence.');
$ci->db->student['guardian_email'] = 'bad-standard';
delivery_assert($service->contact($ci->db->student)['error'] !== null, 'Invalid standard contact must not fall back.');
$ci->db->student['guardian_email'] = '';
foreach (array("Parent’s Email", 'Parent Email', 'Guardian Email', "Guardian's Email", 'Parent/Guardian Email', 'Parent or Guardian Email') as $label) {
    delivery_assert(Biometric_guardian_contact::email('', array(field($label, 'parent@example.test')))['recipient'] === 'parent@example.test', 'Recognise ' . $label);
}
foreach (array('Student Email', 'Emergency Email', 'Staff Email', 'Email') as $label) {
    delivery_assert(Biometric_guardian_contact::email('', array(field($label, 'wrong@example.test')))['error'] !== null, 'Do not guess ' . $label);
}
delivery_assert(Biometric_guardian_contact::email('', array(field('Parent Email', 'bad')))['error'] !== null, 'Invalid custom email must fail safely.');
delivery_assert(Biometric_guardian_contact::email('', array(field('Parent Email', 'a@example.test'), field('Guardian Email', 'b@example.test')))['error'] !== null, 'Conflicting recipients must never be guessed.');
delivery_assert(Biometric_guardian_contact::email('', array(field('Parent Email', 'a@example.test'), field('Guardian Email', 'a@example.test')))['recipient'] === 'a@example.test', 'Identical addresses must be deduplicated.');

$sentCount = count($ci->mailer->sent);
foreach (array('operating_mode' => 'shadow', 'processing_status' => 'quarantined',
    'projection_status' => 'not_applicable', 'subject_type' => 'staff') as $key => $value) {
    $original = $model->event[$key]; $model->event[$key] = $value;
    delivery_assert($service->deliver($row)['skip'], 'Ineligible ' . $key . ' must not send.');
    $model->event[$key] = $original;
}
$service->settings['notify_email'] = 0;
delivery_assert($service->deliver($row)['skip'], 'Master channel OFF must prevent sending.');
$service->settings['notify_email'] = 1;
$service->templateEnabled = false;
delivery_assert($service->deliver($row)['skip'], 'Template channel OFF must prevent sending.');
$service->templateEnabled = true;
$feeRow = $row; $feeRow['notification_type'] = 'biometric_fees_due';
delivery_assert($service->deliver($feeRow)['skip'], 'Paid fees must cancel stale reminders.');
delivery_assert(count($ci->mailer->sent) === $sentCount, 'Safety gates must not call the mail provider.');
$service->balance = 100;
delivery_assert($service->deliver($feeRow)['success'], 'Unpaid fee reminder must use custom parent email too.');
$model->event['direction'] = 'OUT';
$outRow = $row; $outRow['notification_type'] = 'biometric_attendance_out';
delivery_assert($service->deliver($outRow)['success'], 'Check Out must use the same guardian contact resolver.');
$model->event['direction'] = 'IN';
$ci->mailer->result = false;
delivery_assert(!$service->deliver($row)['success'], 'Provider rejection must remain a retryable failure.');

$success = array('success' => true, 'skip' => false, 'message' => 'Delivered.');
$ci->db->queue = array(queue_item(1), queue_item(2), queue_item(3, 99));
$service->queueResults = array(1 => new RuntimeException('sensitive-provider-details'), 2 => $success, 3 => $success);
$result = $service->processNotificationQueue(20, 2, 15);
delivery_assert($result['retried'] === 1 && $result['delivered'] === 1, 'One item failure must not abandon another item.');
delivery_assert($ci->db->queue[0]['status'] === 'retry' && $ci->db->queue[0]['attempt_count'] === 1, 'Failures must retain backoff and attempt count.');
delivery_assert($ci->db->queue[2]['status'] === 'pending', 'Heartbeat must only drain its integration.');
delivery_assert(strpos($ci->db->queue[0]['last_error'], 'sensitive') === false, 'Unexpected exceptions must not disclose sensitive diagnostics.');

$ci->db->queue = array(queue_item(1)); $service->queueCalls = array();
$ci->db->beforeClaim = function ($db) { $db->queue[0]['attempt_count'] = 1; $db->queue[0]['status'] = 'retry'; $db->queue[0]['next_attempt_at'] = '2099-01-01 00:00:00'; };
$service->processNotificationQueue(20, 2);
delivery_assert(!$service->queueCalls, 'Concurrent retry must not be resent from a stale selection.');
$ci->db->queue = array(queue_item(1));
$ci->db->beforeClaim = function ($db) { $db->queue[0]['status'] = 'delivered'; };
$service->processNotificationQueue(20, 2);
delivery_assert(!$service->queueCalls, 'Concurrent delivered row must not be sent again.');

$ci->db->queue = array(queue_item(1), queue_item(2));
$service->queueResults = array(1 => $success, 2 => $success); $service->queueCalls = array(); $service->delayFirst = true;
$service->processNotificationQueue(20, 2, 1);
delivery_assert($service->queueCalls === array(1) && $ci->db->queue[1]['status'] === 'pending', 'Time budget must leave unclaimed work for the next heartbeat.');
$service->delayFirst = false;
$service->processNotificationQueue(20, 2);
delivery_assert($ci->db->queue[1]['status'] === 'delivered', 'Next automatic run must drain remaining work.');
echo 'biometric notification delivery tests passed (' . $GLOBALS['delivery_assertions'] . " assertions)\n";
