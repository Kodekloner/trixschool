<?php

/**
 * Characterisation tests for the existing /biometric receiver.
 *
 * These tests intentionally use the real Biometric controller and the real
 * Stuattendence_model::onlineattendence() method, with in-memory CodeIgniter
 * and database doubles. No configured school database is contacted.
 *
 * Run from the repository root:
 *
 *     php tests/legacy_biometric_endpoint_test.php
 */

define('BASEPATH', __DIR__);

class CI_Controller
{
    public $input;
    public $output;
    public $load;
    public $setting_model;
    public $student_model;
    public $stuattendence_model;

    public function __construct()
    {
        $this->input = new LegacyBiometricInput();
        $this->output = new LegacyBiometricOutput();
        $this->load = new LegacyBiometricLoader($this);
        $GLOBALS['legacy_biometric_ci_instance'] = $this;
    }
}

class MY_Model
{
    public $db;
}

function &get_instance()
{
    $instance = &$GLOBALS['legacy_biometric_ci_instance'];
    return $instance;
}

class LegacyBiometricInput
{
    public $requestMethod = 'POST';

    public function server($key)
    {
        return $key === 'REQUEST_METHOD' ? $this->requestMethod : null;
    }
}

class LegacyBiometricOutput
{
    public $contentType;
    public $status;
    public $body;

    public function set_content_type($contentType)
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function set_status_header($status)
    {
        $this->status = $status;
        return $this;
    }

    public function set_output($body)
    {
        $this->body = $body;
        return $this;
    }
}

class LegacyBiometricLoader
{
    private $owner;

    public function __construct($owner)
    {
        $this->owner = $owner;
    }

    public function helper($name)
    {
        // The production json_output helper is loaded below.
        return $this;
    }

    public function model($name)
    {
        if (!isset($GLOBALS['legacy_biometric_services'][$name])) {
            throw new RuntimeException('Missing test service: ' . $name);
        }

        $this->owner->{$name} = $GLOBALS['legacy_biometric_services'][$name];
        return $this;
    }
}

class LegacyBiometricSettings
{
    private $settings;

    public function __construct($enabled, $serialNumbers)
    {
        $this->settings = (object) array(
            'biometric' => $enabled ? 1 : 0,
            'biometric_device' => implode(',', $serialNumbers),
        );
    }

    public function getSchoolDetail()
    {
        return $this->settings;
    }
}

class LegacyBiometricStudents
{
    private $students;
    public $lookups = array();

    public function __construct(array $students)
    {
        $this->students = $students;
    }

    public function findByAdmission($admissionNumber = null)
    {
        $this->lookups[] = $admissionNumber;
        return isset($this->students[$admissionNumber])
            ? (object) $this->students[$admissionNumber]
            : false;
    }
}

class LegacyBiometricQueryResult
{
    private $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function num_rows()
    {
        return count($this->rows);
    }
}

class LegacyBiometricDatabase
{
    private $conditions = array();
    private $affectedRows = 0;
    private $attendanceRows = array();

    public function where($field, $value)
    {
        $this->conditions[$field] = $value;
        return $this;
    }

    public function get($table)
    {
        if ($table !== 'student_attendences') {
            throw new RuntimeException('Unexpected table read: ' . $table);
        }

        $matchingRows = array_filter(
            $this->attendanceRows,
            function ($row) {
                foreach ($this->conditions as $field => $value) {
                    if (!array_key_exists($field, $row) || $row[$field] != $value) {
                        return false;
                    }
                }
                return true;
            }
        );
        $this->conditions = array();

        return new LegacyBiometricQueryResult(array_values($matchingRows));
    }

    public function insert($table, array $data)
    {
        if ($table !== 'student_attendences') {
            throw new RuntimeException('Unexpected table insert: ' . $table);
        }

        $this->attendanceRows[] = $data;
        $this->affectedRows = 1;
        return true;
    }

    public function affected_rows()
    {
        return $this->affectedRows;
    }

    public function attendanceRows()
    {
        return $this->attendanceRows;
    }
}

/**
 * Supplies a repeatable body for both php://input reads in the legacy
 * controller. It replaces the php stream wrapper only for this test process.
 */
class LegacyBiometricPhpStream
{
    public static $payload = '';
    public $context;
    private $position = 0;

    public function stream_open($path, $mode, $options, &$openedPath)
    {
        $this->position = 0;
        return $path === 'php://input';
    }

    public function stream_read($length)
    {
        $chunk = substr(self::$payload, $this->position, $length);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$payload);
    }

    public function stream_stat()
    {
        return array();
    }
}

require_once __DIR__ . '/../application/helpers/json_output_helper.php';
require_once __DIR__ . '/../application/models/Stuattendence_model.php';
require_once __DIR__ . '/../application/controllers/Biometric.php';

class LegacyTestStuattendenceModel extends Stuattendence_model
{
    public function __construct($database)
    {
        $this->db = $database;
    }
}

function legacy_biometric_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true) . PHP_EOL
        );
        exit(1);
    }
}

function legacy_biometric_request($method, $payload)
{
    LegacyBiometricPhpStream::$payload = $payload;

    $controller = new Biometric();
    $controller->input->requestMethod = $method;
    $controller->index();

    return array(
        'status' => $controller->output->status,
        'content_type' => $controller->output->contentType,
        'body' => $controller->output->body === null
            ? null
            : json_decode($controller->output->body, true),
    );
}

$database = new LegacyBiometricDatabase();
$students = new LegacyBiometricStudents(array(
    'TBSW/ST/0002' => array('student_session_id' => 7002),
));

$GLOBALS['legacy_biometric_services'] = array(
    'setting_model' => new LegacyBiometricSettings(
        true,
        array('SIM-IN-001', 'SIM-OUT-001')
    ),
    'student_model' => $students,
    'stuattendence_model' => new LegacyTestStuattendenceModel($database),
);

if (!stream_wrapper_unregister('php')) {
    fwrite(STDERR, 'Unable to replace the php stream wrapper for this test.' . PHP_EOL);
    exit(1);
}
stream_wrapper_register('php', 'LegacyBiometricPhpStream');

$entryPayload = json_encode(array(
    'serial_number' => 'SIM-IN-001',
    'user_id' => 'TBSW/ST/0002',
    't' => '2026-08-03 07:30:00',
    'ip' => '127.0.0.1',
));
$entry = legacy_biometric_request('POST', $entryPayload);

legacy_biometric_assert_same(200, $entry['status'], 'A valid entry punch must return HTTP 200.');
legacy_biometric_assert_same('application/json', $entry['content_type'], 'A valid entry punch must return JSON.');
legacy_biometric_assert_same(
    array('status' => 200, 'message' => 'Record Inserted.'),
    $entry['body'],
    'A valid entry punch must report that its attendance row was inserted.'
);

$rowsAfterEntry = $database->attendanceRows();
legacy_biometric_assert_same(1, count($rowsAfterEntry), 'A valid entry punch must create one daily attendance row.');
legacy_biometric_assert_same('2026-08-03', $rowsAfterEntry[0]['date'], 'The punch timestamp must determine the attendance date.');
legacy_biometric_assert_same(7002, $rowsAfterEntry[0]['student_session_id'], 'The admission number must resolve to the current student session.');
legacy_biometric_assert_same(1, $rowsAfterEntry[0]['attendence_type_id'], 'A legacy biometric punch is always marked Present.');
legacy_biometric_assert_same(1, $rowsAfterEntry[0]['biometric_attendence'], 'A legacy biometric row must carry the biometric marker.');
legacy_biometric_assert_same('2026-08-03 07:30:00', $rowsAfterEntry[0]['created_at'], 'The original punch time must be retained.');
legacy_biometric_assert_same($entryPayload, $rowsAfterEntry[0]['biometric_device_data'], 'The raw legacy payload must be retained.');

$checkoutPayload = json_encode(array(
    'serial_number' => 'SIM-OUT-001',
    'user_id' => 'TBSW/ST/0002',
    't' => '2026-08-03 15:12:00',
    'ip' => '127.0.0.1',
));
$checkout = legacy_biometric_request('POST', $checkoutPayload);

legacy_biometric_assert_same(200, $checkout['status'], 'A duplicate same-day punch currently returns HTTP 200.');
legacy_biometric_assert_same(
    array('status' => 200, 'message' => 'Something Wrong.'),
    $checkout['body'],
    'The legacy receiver must expose its current inability to store checkout.'
);
legacy_biometric_assert_same(
    1,
    count($database->attendanceRows()),
    'The legacy daily table must reject the second same-day punch rather than overwrite entry.'
);

$lookupsBeforeInvalidSerial = count($students->lookups);
$invalidSerial = legacy_biometric_request(
    'POST',
    json_encode(array(
        'serial_number' => 'UNREGISTERED-001',
        'user_id' => 'TBSW/ST/0002',
        't' => '2026-08-04 07:30:00',
    ))
);
legacy_biometric_assert_same(null, $invalidSerial['status'], 'An unregistered serial currently produces no HTTP response body/status.');
legacy_biometric_assert_same(null, $invalidSerial['body'], 'An unregistered serial currently produces no JSON response.');
legacy_biometric_assert_same($lookupsBeforeInvalidSerial, count($students->lookups), 'An unregistered serial must be rejected before student lookup.');
legacy_biometric_assert_same(1, count($database->attendanceRows()), 'An unregistered serial must not create attendance.');

$unknownStudent = legacy_biometric_request(
    'POST',
    json_encode(array(
        'serial_number' => 'SIM-IN-001',
        'user_id' => 'UNKNOWN/STUDENT',
        't' => '2026-08-04 07:30:00',
    ))
);
legacy_biometric_assert_same(null, $unknownStudent['status'], 'An unknown student currently produces no HTTP response body/status.');
legacy_biometric_assert_same(null, $unknownStudent['body'], 'An unknown student currently produces no JSON response.');
legacy_biometric_assert_same(1, count($database->attendanceRows()), 'An unknown student must not create attendance.');

$malformed = legacy_biometric_request('POST', '{not valid json');
legacy_biometric_assert_same(null, $malformed['status'], 'Malformed JSON currently produces no HTTP response body/status.');
legacy_biometric_assert_same(null, $malformed['body'], 'Malformed JSON currently produces no JSON response.');
legacy_biometric_assert_same(1, count($database->attendanceRows()), 'Malformed JSON must not create attendance.');

$wrongMethod = legacy_biometric_request('GET', '');
legacy_biometric_assert_same(400, $wrongMethod['status'], 'A non-POST request must return HTTP 400.');
legacy_biometric_assert_same(
    array('status' => 400, 'message' => 'Bad request.'),
    $wrongMethod['body'],
    'A non-POST request must return the documented bad-request JSON.'
);

stream_wrapper_restore('php');

echo "legacy biometric endpoint tests passed" . PHP_EOL;
