<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Shared production biometric workflow.
 *
 * All event sources (ZKBio gateway, browser test terminal and trusted QR
 * scanner) pass through ingestEvent(). Simulation and shadow events are kept
 * in distinct attendance-day scopes and can never reach legacy attendance.
 */
class Biometric_attendance_service
{
    protected $CI;
    protected $model;
    protected $policy;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->model('biometric_attendance_model');
        $this->CI->load->library('biometric_event_policy');
        $this->model = $this->CI->biometric_attendance_model;
        $this->policy = $this->CI->biometric_event_policy;
    }

    public function isReady()
    {
        return $this->model->isReady();
    }

    public function getSettings()
    {
        if (!$this->isReady()) {
            return null;
        }
        return $this->model->getSettings();
    }

    /**
     * $options['live_authorized'] must be true for a transition to live. The
     * admin controller is responsible for password and RBAC verification.
     */
    public function updateSettings(array $data, $actorId = null, array $options = array())
    {
        if (!$this->isReady()) {
            return $this->failure('Biometric migration 130 has not been applied.');
        }

        $before = $this->getSettings();
        $allowed = array(
            'mode', 'timezone', 'student_late_after', 'staff_late_after',
            'student_present_type_id', 'student_late_type_id',
            'staff_present_type_id', 'staff_late_type_id', 'project_students',
            'project_staff', 'retention_days', 'max_event_age_days'
        );
        $save = $this->only($data, $allowed);
        $errors = array();

        if (isset($save['mode']) && !in_array($save['mode'], array('disabled', 'simulation', 'shadow', 'live'), true)) {
            $errors['mode'] = 'Mode must be disabled, simulation, shadow or live.';
        }
        if (isset($save['mode']) && $save['mode'] === 'live'
            && (!is_array($before) || $before['mode'] !== 'live')
            && empty($options['live_authorized'])) {
            $errors['mode'] = 'A password-confirmed live transition is required.';
        }
        if (isset($save['timezone'])) {
            try {
                new DateTimeZone($save['timezone']);
            } catch (Exception $exception) {
                $errors['timezone'] = 'Timezone is not valid.';
            }
        }
        foreach (array('student_late_after', 'staff_late_after') as $field) {
            if (isset($save[$field]) && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $save[$field])) {
                $errors[$field] = 'Use a 24-hour time such as 08:00.';
            } elseif (isset($save[$field]) && strlen($save[$field]) === 5) {
                $save[$field] .= ':00';
            }
        }
        foreach (array('student_present_type_id', 'student_late_type_id', 'staff_present_type_id', 'staff_late_type_id') as $field) {
            if (isset($save[$field]) && (int) $save[$field] < 1) {
                $errors[$field] = 'Attendance type must be a positive ID.';
            } elseif (isset($save[$field])) {
                $save[$field] = (int) $save[$field];
                $typeTable = strpos($field, 'student_') === 0 ? 'attendence_type' : 'staff_attendance_type';
                if (!$this->attendanceTypeExists($typeTable, $save[$field])) {
                    $errors[$field] = strpos($field, 'student_') === 0
                        ? 'Choose an active student attendance type.'
                        : 'Choose an active staff attendance type.';
                }
            }
        }
        foreach (array('project_students', 'project_staff') as $field) {
            if (isset($save[$field])) {
                $save[$field] = empty($save[$field]) ? 0 : 1;
            }
        }
        if (isset($save['retention_days'])) {
            $save['retention_days'] = max(30, min(3650, (int) $save['retention_days']));
        }
        if (isset($save['max_event_age_days'])) {
            $save['max_event_age_days'] = max(1, min(365, (int) $save['max_event_age_days']));
        }
        if ($errors) {
            return array('success' => false, 'errors' => $errors, 'item' => $before);
        }

        $save['updated_by'] = $this->actorId($actorId);
        $save['updated_at'] = $this->now();
        if (!$before) {
            $save['created_by'] = $save['updated_by'];
            $save['created_at'] = $save['updated_at'];
        }
        $item = $this->model->saveSettings($save);
        $this->audit('settings.updated', 'settings', 1, $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function dashboard($date = null)
    {
        $settings = $this->getSettings();
        if (!$settings) {
            return array('ready' => false, 'settings' => null, 'counters' => array());
        }
        $date = $date ?: $this->localNow($settings['timezone'])->format('Y-m-d');
        $mode = $settings['mode'];
        $db = $this->CI->db;

        $countEvent = function ($direction = null, $source = null) use ($db, $date, $mode) {
            $db->from('biometric_events')->where('attendance_date', $date)
                ->where('operating_mode', $mode)->where('processing_status', 'accepted');
            if ($direction !== null) {
                $db->where('direction', $direction);
            }
            if ($source !== null) {
                $db->where('source', $source);
            }
            return (int) $db->count_all_results();
        };

        $counters = array(
            'events' => $countEvent(),
            'check_ins' => $countEvent('IN'),
            'check_outs' => $countEvent('OUT'),
            'simulated' => $countEvent(null, 'simulator'),
            'missing_checkouts' => (int) $db->from('biometric_attendance_days')
                ->where('attendance_date', $date)->where('record_scope', $mode)
                ->where('missing_checkout', 1)->count_all_results(),
            'open_exceptions' => (int) $db->from('biometric_exceptions')
                ->where('status', 'open')->count_all_results(),
        );

        return array(
            'ready' => true,
            'date' => $date,
            'settings' => $settings,
            'counters' => $counters,
            'devices' => $this->listDevices(array('is_active' => 1), 1, 25)['items'],
            'integrations' => $this->listIntegrations(array('is_active' => 1), 1, 25)['items'],
        );
    }

    public function listDevices(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_devices',
            $this->filter($filters, array('id', 'integration_id', 'serial_number', 'device_type', 'is_virtual', 'is_active')),
            $page,
            $perPage,
            'id'
        );
    }

    public function saveDevice(array $data, $actorId = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $before = $id ? $this->model->getDevice($id) : null;
        $serial = strtoupper(trim(isset($data['serial_number']) ? $data['serial_number'] : ''));
        $name = trim(isset($data['name']) ? $data['name'] : '');
        $errors = array();
        if ($serial === '' || strlen($serial) > 100 || !preg_match('/^[A-Z0-9._:-]+$/', $serial)) {
            $errors['serial_number'] = 'Use 1-100 letters, numbers, dots, dashes, colons or underscores.';
        }
        if ($name === '' || strlen($name) > 100) {
            $errors['name'] = 'Device name is required and must not exceed 100 characters.';
        }
        $existing = $serial === '' ? null : $this->model->findDeviceBySerial($serial);
        if ($existing && (int) $existing['id'] !== (int) $id) {
            $errors['serial_number'] = 'That device serial is already registered.';
        }
        $deviceType = isset($data['device_type']) ? $data['device_type'] : 'biometric';
        if (!in_array($deviceType, array('biometric', 'qr_scanner'), true)) {
            $errors['device_type'] = 'Unsupported device type.';
        }
        $isVirtual = empty($data['is_virtual']) ? 0 : 1;
        $integrationId = empty($data['integration_id']) ? null : (int) $data['integration_id'];
        if (!$isVirtual && $deviceType === 'biometric' && !$integrationId) {
            $errors['integration_id'] = 'A physical biometric terminal requires an integration.';
        }
        if ($integrationId) {
            $integration = $this->model->getIntegration($integrationId);
            if (!$integration || empty($integration['is_active'])) {
                $errors['integration_id'] = 'Choose an active biometric integration.';
            }
        }
        if ($errors) {
            return array('success' => false, 'errors' => $errors, 'item' => $before);
        }

        $now = $this->now();
        $save = array(
            'integration_id' => $integrationId,
            'serial_number' => $serial,
            'name' => $name,
            'location' => $this->nullableString(isset($data['location']) ? $data['location'] : null, 191),
            'device_type' => $deviceType,
            'direction_mode' => 'bidirectional',
            'firmware_version' => $this->nullableString(isset($data['firmware_version']) ? $data['firmware_version'] : null, 100),
            'is_virtual' => $isVirtual,
            'is_active' => isset($data['is_active']) && !$data['is_active'] ? 0 : 1,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $now,
        );
        if (!$id) {
            $save['created_by'] = $save['updated_by'];
            $save['created_at'] = $now;
        }
        $item = $this->model->saveDevice($save, $id);
        $this->audit($id ? 'device.updated' : 'device.created', 'device', $item['id'], $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function setDeviceActive($deviceId, $active, $actorId = null)
    {
        if (!in_array($active, array(true, false, 0, 1, '0', '1'), true)) {
            return array('success' => false, 'errors' => array('is_active' => 'Active state must be boolean.'), 'item' => null);
        }
        $enabled = $active === true || $active === 1 || $active === '1';
        $before = $this->model->getDevice((int) $deviceId);
        if (!$before) {
            return $this->failure('Device not found.');
        }
        $this->CI->db->where('id', $before['id'])->update('biometric_devices', array(
            'is_active' => $enabled ? 1 : 0,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $this->now(),
        ));
        $item = $this->model->getDevice($before['id']);
        $this->audit($enabled ? 'device.enabled' : 'device.disabled', 'device', $before['id'], $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function listMappings(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_identity_mappings',
            $this->filter($filters, array('id', 'subject_type', 'subject_id', 'external_person_code', 'is_active')),
            $page,
            $perPage,
            'id'
        );
    }

    /** Student subject_id is student_session.id; staff subject_id is staff.id. */
    public function saveMapping(array $data, $actorId = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $before = $id ? $this->model->getMapping($id) : null;
        $subjectType = strtolower(trim(isset($data['subject_type']) ? $data['subject_type'] : ''));
        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : 0;
        $code = strtoupper(trim(isset($data['external_person_code']) ? $data['external_person_code'] : ''));
        $errors = array();
        if (!in_array($subjectType, array('student', 'staff'), true)) {
            $errors['subject_type'] = 'Subject type must be student or staff.';
        }
        if ($subjectId < 1 || !$this->subjectExists($subjectType, $subjectId)) {
            $errors['subject_id'] = $subjectType === 'student'
                ? 'Choose a valid current student-session record.'
                : 'Choose a valid active staff record.';
        }
        if ($code === '' || strlen($code) > 100 || preg_match('/[\x00-\x1F\x7F]/', $code)) {
            $errors['external_person_code'] = 'Person code is required and must not exceed 100 characters.';
        }
        if ($code !== '') {
            $conflict = $this->CI->db->where('external_person_code', $code)
                ->get('biometric_identity_mappings')->row_array();
            if ($conflict && (int) $conflict['id'] !== (int) $id) {
                $errors['external_person_code'] = 'That person code is already mapped.';
            }
        }
        if ($subjectId > 0 && in_array($subjectType, array('student', 'staff'), true)) {
            $conflict = $this->CI->db->where('subject_type', $subjectType)->where('subject_id', $subjectId)
                ->get('biometric_identity_mappings')->row_array();
            if ($conflict && (int) $conflict['id'] !== (int) $id) {
                $errors['subject_id'] = 'That person already has a biometric mapping.';
            }
        }
        foreach (array('valid_from', 'valid_until') as $field) {
            if (!empty($data[$field]) && !$this->validDate($data[$field])) {
                $errors[$field] = 'Use a valid YYYY-MM-DD date.';
            }
        }
        if ($errors) {
            return array('success' => false, 'errors' => $errors, 'item' => $before);
        }

        $now = $this->now();
        $save = array(
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'external_person_code' => $code,
            'valid_from' => empty($data['valid_from']) ? null : $data['valid_from'],
            'valid_until' => empty($data['valid_until']) ? null : $data['valid_until'],
            'is_active' => isset($data['is_active']) && !$data['is_active'] ? 0 : 1,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $now,
        );
        if (!$id) {
            $save['created_by'] = $save['updated_by'];
            $save['created_at'] = $now;
        }
        $item = $this->model->saveMapping($save, $id);
        $this->audit($id ? 'mapping.updated' : 'mapping.created', 'mapping', $item['id'], $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function setMappingActive($mappingId, $active, $actorId = null)
    {
        if (!in_array($active, array(true, false, 0, 1, '0', '1'), true)) {
            return array('success' => false, 'errors' => array('is_active' => 'Active state must be boolean.'), 'item' => null);
        }
        $enabled = $active === true || $active === 1 || $active === '1';
        $before = $this->model->getMapping((int) $mappingId);
        if (!$before) {
            return $this->failure('Identity mapping not found.');
        }
        $this->CI->db->where('id', $before['id'])->update('biometric_identity_mappings', array(
            'is_active' => $enabled ? 1 : 0,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $this->now(),
        ));
        $item = $this->model->getMapping($before['id']);
        $this->audit($enabled ? 'mapping.enabled' : 'mapping.disabled', 'mapping', $before['id'], $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function previewBulkMappings($subjectType)
    {
        $subjectType = strtolower(trim($subjectType));
        if (!in_array($subjectType, array('student', 'staff'), true)) {
            return array(
                'success' => false,
                'subject_type' => $subjectType,
                'items' => array(),
                'totals' => array('create' => 0, 'skip' => 0, 'conflict' => 0),
                'preview_hash' => null,
                'errors' => array('subject_type' => 'Subject type must be student or staff.'),
            );
        }

        if ($subjectType === 'student') {
            $setting = $this->CI->db->select('session_id')->limit(1)->get('sch_settings')->row_array();
            $rows = $this->CI->db->select("student_session.id AS subject_id, students.admission_no AS external_person_code, CONCAT_WS(' ', students.firstname, students.middlename, students.lastname) AS subject_name", false)
                ->from('student_session')->join('students', 'students.id = student_session.student_id')
                ->where('student_session.session_id', isset($setting['session_id']) ? $setting['session_id'] : 0)
                ->where('students.is_active', 'yes')->order_by('students.admission_no', 'ASC')->get()->result_array();
        } else {
            $rows = $this->CI->db->select("staff.id AS subject_id, staff.employee_id AS external_person_code, CONCAT_WS(' ', staff.name, staff.surname) AS subject_name", false)
                ->from('staff')->where('staff.is_active', 1)->order_by('staff.employee_id', 'ASC')->get()->result_array();
        }

        $codeCounts = array();
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) $row['external_person_code']));
            if ($code !== '') {
                $codeCounts[$code] = isset($codeCounts[$code]) ? $codeCounts[$code] + 1 : 1;
            }
        }

        $items = array();
        $totals = array('create' => 0, 'skip' => 0, 'conflict' => 0);
        foreach ($rows as $row) {
            $subjectId = (int) $row['subject_id'];
            $code = strtoupper(trim((string) $row['external_person_code']));
            $item = array(
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_name' => trim((string) $row['subject_name']),
                'external_person_code' => $code,
                'mapping_id' => null,
                'action' => 'create',
                'reason' => 'A new identity mapping will be created.',
            );

            if ($code === '') {
                $item['action'] = 'conflict';
                $item['reason'] = $subjectType === 'student'
                    ? 'The student has no admission number.'
                    : 'The staff member has no employee ID.';
            } elseif (!empty($codeCounts[$code]) && $codeCounts[$code] > 1) {
                $item['action'] = 'conflict';
                $item['reason'] = 'More than one active record uses this person code.';
            } else {
                $byCode = $this->CI->db->where('external_person_code', $code)
                    ->get('biometric_identity_mappings')->row_array();
                $bySubject = $this->CI->db->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId)
                    ->get('biometric_identity_mappings')->row_array();

                if ($byCode && $bySubject && (int) $byCode['id'] !== (int) $bySubject['id']) {
                    $item['action'] = 'conflict';
                    $item['reason'] = 'The person code and subject are already linked to different mappings.';
                } elseif ($byCode) {
                    $item['mapping_id'] = (int) $byCode['id'];
                    if ($byCode['subject_type'] === $subjectType && (int) $byCode['subject_id'] === $subjectId) {
                        if (!empty($byCode['is_active'])) {
                            $item['action'] = 'skip';
                            $item['reason'] = 'The identity is already mapped and active.';
                        } else {
                            $item['reason'] = 'The existing identity mapping will be reactivated.';
                        }
                    } elseif ($subjectType === 'student' && $byCode['subject_type'] === 'student' && !$bySubject) {
                        $item['reason'] = 'The admission number mapping will advance to the current student-session record.';
                    } else {
                        $item['action'] = 'conflict';
                        $item['reason'] = 'The person code is already assigned to another identity.';
                    }
                } elseif ($bySubject) {
                    $item['mapping_id'] = (int) $bySubject['id'];
                    if (strtoupper((string) $bySubject['external_person_code']) === $code) {
                        if (!empty($bySubject['is_active'])) {
                            $item['action'] = 'skip';
                            $item['reason'] = 'The identity is already mapped and active.';
                        } else {
                            $item['reason'] = 'The existing identity mapping will be reactivated.';
                        }
                    } else {
                        $item['action'] = 'conflict';
                        $item['reason'] = 'This identity is already mapped to a different person code.';
                    }
                }
            }

            $totals[$item['action']]++;
            $items[] = $item;
        }

        $hashRows = array();
        foreach ($items as $item) {
            $hashRows[] = array(
                'subject_type' => $item['subject_type'],
                'subject_id' => $item['subject_id'],
                'external_person_code' => $item['external_person_code'],
                'mapping_id' => $item['mapping_id'],
                'action' => $item['action'],
            );
        }

        return array(
            'success' => true,
            'subject_type' => $subjectType,
            'items' => $items,
            'totals' => $totals,
            'preview_hash' => hash('sha256', json_encode($hashRows)),
            'errors' => array(),
        );
    }

    public function bulkSeedMappings($subjectType, $actorId = null, array $options = array())
    {
        $preview = $this->previewBulkMappings($subjectType);
        if (empty($preview['success'])) {
            return array(
                'success' => false,
                'created' => 0,
                'skipped' => 0,
                'conflicts' => 0,
                'preview_hash' => null,
                'errors' => $preview['errors'],
            );
        }
        if (!empty($options['preview_hash'])
            && !hash_equals($preview['preview_hash'], (string) $options['preview_hash'])) {
            return array(
                'success' => false,
                'created' => 0,
                'skipped' => 0,
                'conflicts' => $preview['totals']['conflict'],
                'preview_hash' => $preview['preview_hash'],
                'errors' => array('preview' => 'Identity records changed after preview. Review the refreshed preview before confirming.'),
            );
        }

        $created = 0;
        $skipped = $preview['totals']['skip'];
        $conflicts = $preview['totals']['conflict'];
        $errors = array();
        foreach ($preview['items'] as $item) {
            if ($item['action'] !== 'create') {
                continue;
            }
            $mappingData = array(
                'subject_type' => $item['subject_type'],
                'subject_id' => (int) $item['subject_id'],
                'external_person_code' => $item['external_person_code'],
                'is_active' => 1,
            );
            if (!empty($item['mapping_id'])) {
                $mappingData['id'] = (int) $item['mapping_id'];
            }
            $result = $this->saveMapping($mappingData, $actorId);
            if (!empty($result['success'])) {
                $created++;
            } else {
                $conflicts++;
                if (count($errors) < 20) {
                    $errors[] = array(
                        'subject_id' => (int) $item['subject_id'],
                        'code' => $item['external_person_code'],
                        'errors' => $result['errors'],
                    );
                }
            }
        }

        $this->audit('mappings.bulk_seeded', 'identity_mapping', $subjectType, null, array(
            'created' => $created,
            'skipped' => $skipped,
            'conflicts' => $conflicts,
            'preview_hash' => $preview['preview_hash'],
        ), $actorId);

        return array(
            'success' => true,
            'created' => $created,
            'skipped' => $skipped,
            'conflicts' => $conflicts,
            'preview_hash' => $preview['preview_hash'],
            'errors' => $errors,
        );
    }

    public function listIntegrations(array $filters = array(), $page = 1, $perPage = 50)
    {
        $result = $this->model->paginate(
            'biometric_integrations',
            $this->filter($filters, array('id', 'provider', 'is_active')),
            $page,
            $perPage,
            'id'
        );
        foreach ($result['items'] as &$item) {
            $item = $this->publicIntegration($item);
        }
        unset($item);
        return $result;
    }

    public function createIntegrationToken(array $data, $actorId = null)
    {
        $name = trim(isset($data['name']) ? $data['name'] : 'ZKBio Time Gateway');
        $provider = trim(isset($data['provider']) ? $data['provider'] : 'zkbio_time');
        $errors = array();
        if ($name === '' || strlen($name) > 100) {
            $errors['name'] = 'Integration name is required.';
        }
        if ($provider !== 'zkbio_time') {
            $errors['provider'] = 'This release supports the zkbio_time provider.';
        }
        if ($errors) {
            return array('success' => false, 'errors' => $errors, 'item' => null, 'token' => null);
        }

        list($token, $prefix, $hash) = $this->newIntegrationToken();
        $now = $this->now();
        $save = array(
            'name' => $name,
            'provider' => $provider,
            'endpoint_url' => $this->nullableString(isset($data['endpoint_url']) ? $data['endpoint_url'] : null, 500),
            'token_prefix' => $prefix,
            'token_hash' => $hash,
            'token_version' => 1,
            'is_active' => 1,
            'created_by' => $this->actorId($actorId),
            'updated_by' => $this->actorId($actorId),
            'created_at' => $now,
            'updated_at' => $now,
        );
        $this->CI->db->insert('biometric_integrations', $save);
        $id = (int) $this->CI->db->insert_id();
        $this->seedPunchMappings($id);
        $item = $this->publicIntegration($this->model->getIntegration($id));
        $this->audit('integration.created', 'integration', $id, null, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item, 'token' => $token);
    }

    public function setIntegrationActive($integrationId, $active, $actorId = null)
    {
        if (!in_array($active, array(true, false, 0, 1, '0', '1'), true)) {
            return array('success' => false, 'errors' => array('is_active' => 'Active state must be boolean.'), 'item' => null);
        }
        $enabled = $active === true || $active === 1 || $active === '1';
        $before = $this->model->getIntegration((int) $integrationId);
        if (!$before) {
            return $this->failure('Integration not found.');
        }
        $this->CI->db->where('id', $before['id'])->update('biometric_integrations', array(
            'is_active' => $enabled ? 1 : 0,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $this->now(),
        ));
        $item = $this->publicIntegration($this->model->getIntegration($before['id']));
        $this->audit($enabled ? 'integration.enabled' : 'integration.disabled', 'integration', $before['id'], $this->publicIntegration($before), $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function rotateIntegrationToken($integrationId, $actorId = null)
    {
        $before = $this->model->getIntegration((int) $integrationId);
        if (!$before) {
            return array('success' => false, 'errors' => array('integration' => 'Integration not found.'), 'item' => null, 'token' => null);
        }
        list($token, $prefix, $hash) = $this->newIntegrationToken();
        $this->CI->db->where('id', $before['id'])->update('biometric_integrations', array(
            'token_prefix' => $prefix,
            'token_hash' => $hash,
            'token_version' => (int) $before['token_version'] + 1,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $this->now(),
        ));
        $item = $this->publicIntegration($this->model->getIntegration($before['id']));
        $this->audit('integration.token_rotated', 'integration', $before['id'], $this->publicIntegration($before), $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item, 'token' => $token);
    }

    public function getPunchStateMappings($integrationId)
    {
        return $this->CI->db->select('id, integration_id, raw_punch_state, direction')
            ->where('integration_id', (int) $integrationId)
            ->order_by('id', 'ASC')->get('biometric_punch_state_mappings')->result_array();
    }

    /** $map is raw provider state => IN|OUT and must contain distinct IN/OUT. */
    public function savePunchStateMappings($integrationId, array $map, $actorId = null)
    {
        $integration = $this->model->getIntegration((int) $integrationId);
        if (!$integration) {
            return array('success' => false, 'errors' => array('integration' => 'Integration not found.'), 'items' => array());
        }
        $normalized = array();
        foreach ($map as $state => $direction) {
            $state = trim((string) $state);
            $direction = strtoupper(trim((string) $direction));
            if ($state === '' || strlen($state) > 32 || !in_array($direction, array('IN', 'OUT'), true)) {
                return array('success' => false, 'errors' => array('mapping' => 'Each punch state needs a short value and an IN or OUT direction.'), 'items' => $this->getPunchStateMappings($integrationId));
            }
            $normalized[$state] = $direction;
        }
        if (!in_array('IN', $normalized, true) || !in_array('OUT', $normalized, true)) {
            return array('success' => false, 'errors' => array('mapping' => 'A bidirectional terminal requires at least one IN and one OUT punch state.'), 'items' => $this->getPunchStateMappings($integrationId));
        }
        $before = $this->getPunchStateMappings($integrationId);
        $now = $this->now();
        $this->CI->db->trans_start();
        $this->CI->db->where('integration_id', (int) $integrationId)->delete('biometric_punch_state_mappings');
        foreach ($normalized as $state => $direction) {
            $this->CI->db->insert('biometric_punch_state_mappings', array(
                'integration_id' => (int) $integrationId,
                'raw_punch_state' => $state,
                'direction' => $direction,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
        $this->CI->db->trans_complete();
        if (!$this->CI->db->trans_status()) {
            return array('success' => false, 'errors' => array('mapping' => 'Punch-state mapping could not be saved.'), 'items' => $before);
        }
        $items = $this->getPunchStateMappings($integrationId);
        $this->audit('integration.punch_states_updated', 'integration', $integrationId, $before, $items, $actorId);
        return array('success' => true, 'errors' => array(), 'items' => $items);
    }

    public function authenticateToken($token)
    {
        if (!is_string($token) || !preg_match('/^slbio_([a-f0-9]{12})_[A-Za-z0-9_-]{40,100}$/', trim($token), $matches)) {
            return null;
        }
        $integration = $this->model->findIntegrationByPrefix($matches[1]);
        if (!$integration || empty($integration['token_hash'])) {
            return null;
        }
        return hash_equals($integration['token_hash'], hash('sha256', trim($token))) ? $integration : null;
    }

    /**
     * Ingest one durable gateway batch. $context['integration'] must contain
     * the record returned by authenticateToken().
     */
    public function ingestBatch(array $payload, array $context = array())
    {
        if (!$this->isReady()) {
            return array('success' => false, 'http_status' => 503, 'message' => 'Biometric storage is not ready.', 'results' => array());
        }
        $integration = isset($context['integration']) && is_array($context['integration'])
            ? $context['integration'] : null;
        if (!$integration || empty($integration['id']) || empty($integration['is_active'])) {
            return array('success' => false, 'http_status' => 401, 'message' => 'Invalid integration credential.', 'results' => array());
        }

        $batchId = trim(isset($payload['batch_id']) ? (string) $payload['batch_id'] : '');
        $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : array();
        if ($batchId === '' || strlen($batchId) > 100 || !$events) {
            return array('success' => false, 'http_status' => 400, 'message' => 'batch_id and at least one event are required.', 'results' => array());
        }
        if (count($events) > 100) {
            return array('success' => false, 'http_status' => 413, 'message' => 'A batch may contain at most 100 events.', 'results' => array());
        }

        $requestHash = hash('sha256', json_encode($this->canonicalize($payload)));
        $resumeBatch = null;
        $existing = $this->CI->db->where('integration_id', $integration['id'])
            ->where('batch_id', $batchId)->get('biometric_gateway_batches')->row_array();
        if ($existing) {
            if (!hash_equals($existing['request_hash'], $requestHash)) {
                return array('success' => false, 'http_status' => 409, 'message' => 'batch_id was already used for different data.', 'results' => array());
            }
            if ($existing['status'] === 'committed') {
                $stored = json_decode((string) $existing['results_json'], true);
                if (!is_array($stored)) {
                    return array('success' => false, 'http_status' => 503, 'message' => 'Committed batch results are unavailable.', 'results' => array());
                }
                return array(
                    'success' => true,
                    'http_status' => 200,
                    'batch_id' => $batchId,
                    'cursor' => $existing['provider_cursor'],
                    'replayed' => true,
                    'results' => $stored,
                );
            }
            // A worker may have stopped between durable event inserts and
            // cursor commit. Replaying is safe because every event is
            // idempotent; already-stored items return duplicate.
            $resumeBatch = $existing;
        }

        if (!$resumeBatch) {
            $recentCount = $this->CI->db->from('biometric_gateway_batches')
                ->where('integration_id', $integration['id'])
                ->where('created_at >=', gmdate('Y-m-d H:i:s', time() - 60))
                ->count_all_results();
            if ($recentCount >= 60) {
                return array('success' => false, 'http_status' => 429, 'message' => 'Gateway rate limit exceeded. Retry later.', 'results' => array());
            }
        }

        $settings = $this->getSettings();
        if (!$settings || !$this->policy->sourceAllowedInMode('gateway', $settings['mode'])) {
            return array('success' => false, 'http_status' => 403, 'message' => 'Gateway events are not allowed in the current operating mode.', 'results' => array());
        }

        $now = $this->now();
        $batch = array(
            'integration_id' => (int) $integration['id'],
            'batch_id' => $batchId,
            'gateway_version' => $this->nullableString(isset($payload['gateway_version']) ? $payload['gateway_version'] : null, 40),
            'provider_cursor' => $this->nullableString(isset($payload['cursor']) ? $payload['cursor'] : null, 191),
            'request_hash' => $requestHash,
            'event_count' => count($events),
            'status' => 'processing',
            'created_at' => $now,
            'updated_at' => $now,
        );
        if ($resumeBatch) {
            $databaseBatchId = (int) $resumeBatch['id'];
        } else {
            $this->CI->db->insert('biometric_gateway_batches', $batch);
            $databaseBatchId = (int) $this->CI->db->insert_id();
        }

        $results = array();
        $counts = array('accepted' => 0, 'duplicate' => 0, 'quarantined' => 0, 'rejected' => 0);
        foreach ($events as $event) {
            if (!is_array($event)) {
                $result = array('external_event_id' => null, 'status' => 'rejected', 'message' => 'Event must be a JSON object.');
            } else {
                $result = $this->ingestEvent($event, array(
                    'source' => 'gateway',
                    'integration' => $integration,
                    'batch_id' => $databaseBatchId,
                ));
            }
            $results[] = $result;
            $status = isset($result['status']) && isset($counts[$result['status']]) ? $result['status'] : 'rejected';
            $counts[$status]++;
        }

        $cursor = isset($batch['provider_cursor']) ? $batch['provider_cursor'] : null;
        $commitTime = $this->now();
        $this->CI->db->trans_start();
        $this->CI->db->where('id', $databaseBatchId)->update('biometric_gateway_batches', array(
            'accepted_count' => $counts['accepted'],
            'duplicate_count' => $counts['duplicate'],
            'quarantined_count' => $counts['quarantined'],
            'rejected_count' => $counts['rejected'],
            'status' => 'committed',
            'results_json' => json_encode($results),
            'committed_at' => $commitTime,
            'updated_at' => $commitTime,
        ));
        $this->CI->db->query("INSERT INTO `biometric_gateway_cursors`
            (`integration_id`, `provider_cursor`, `batch_id`, `committed_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `provider_cursor` = VALUES(`provider_cursor`),
                `batch_id` = VALUES(`batch_id`), `committed_at` = VALUES(`committed_at`),
                `updated_at` = VALUES(`updated_at`)", array($integration['id'], $cursor, $batchId, $commitTime, $commitTime));
        $this->CI->db->where('id', $integration['id'])->update('biometric_integrations', array(
            'last_cursor' => $cursor,
            'last_seen_at' => $commitTime,
            'last_error' => null,
            'updated_at' => $commitTime,
        ));
        $this->CI->db->trans_complete();

        if (!$this->CI->db->trans_status()) {
            return array('success' => false, 'http_status' => 503, 'message' => 'Could not commit the gateway cursor.', 'results' => array());
        }

        return array(
            'success' => true,
            'http_status' => 202,
            'batch_id' => $batchId,
            'cursor' => $cursor,
            'replayed' => $resumeBatch ? true : false,
            'results' => $results,
            'counts' => $counts,
        );
    }

    /**
     * $context source: gateway|simulator|qr. API callers cannot set it.
     */
    public function ingestEvent(array $event, array $context = array())
    {
        $externalId = trim(isset($event['external_event_id']) ? (string) $event['external_event_id'] : '');
        $baseResult = array('external_event_id' => $externalId !== '' ? $externalId : null);
        $settings = $this->getSettings();
        if (!$settings) {
            return $baseResult + array('status' => 'rejected', 'message' => 'Biometric storage is not ready.');
        }
        $source = isset($context['source']) ? strtolower((string) $context['source']) : 'simulator';
        if (!in_array($source, array('gateway', 'simulator', 'qr'), true)) {
            return $baseResult + array('status' => 'rejected', 'message' => 'Unknown event source.');
        }
        if (!$this->policy->sourceAllowedInMode($source, $settings['mode'])) {
            return $baseResult + array('status' => 'rejected', 'message' => 'Event source is disabled in the current operating mode.');
        }
        if ($externalId === '' || strlen($externalId) > 191 || preg_match('/[\x00-\x1F\x7F]/', $externalId)) {
            return $baseResult + array('status' => 'rejected', 'message' => 'external_event_id is required and must not exceed 191 characters.');
        }

        $serial = strtoupper(trim(isset($event['device_serial']) ? (string) $event['device_serial'] : ''));
        $personCode = strtoupper(trim(isset($event['person_code']) ? (string) $event['person_code'] : ''));
        $occurredAt = isset($event['occurred_at']) ? $event['occurred_at'] : null;
        if ($serial === '' || strlen($serial) > 100) {
            return $baseResult + array('status' => 'rejected', 'message' => 'device_serial is required.');
        }
        if ($personCode === '' || strlen($personCode) > 100) {
            return $baseResult + array('status' => 'rejected', 'message' => 'person_code is required.');
        }
        if ($occurredAt === null || $occurredAt === '') {
            return $baseResult + array('status' => 'rejected', 'message' => 'occurred_at is required.');
        }

        $time = $this->policy->normalizeTimestamp(
            $occurredAt,
            $settings['timezone'],
            (int) $settings['max_event_age_days']
        );
        if (empty($time['valid'])) {
            return $baseResult + array('status' => 'rejected', 'message' => $time['message'], 'code' => $time['code']);
        }

        $integration = isset($context['integration']) && is_array($context['integration']) ? $context['integration'] : null;
        $integrationId = $integration ? (int) $integration['id'] : null;
        $device = $this->model->findDeviceBySerial($serial);
        $failureCode = null;
        $failureMessage = null;
        if (!$device || empty($device['is_active'])) {
            $failureCode = 'UNKNOWN_DEVICE';
            $failureMessage = 'The terminal is not registered or is disabled.';
        } elseif ($source === 'gateway' && ((int) $device['integration_id'] !== $integrationId || !empty($device['is_virtual']))) {
            $failureCode = 'DEVICE_INTEGRATION_MISMATCH';
            $failureMessage = 'The terminal is not assigned to this integration.';
        } elseif ($source === 'simulator' && empty($device['is_virtual'])) {
            $failureCode = 'SIMULATOR_DEVICE_REQUIRED';
            $failureMessage = 'The Test Terminal may use virtual devices only.';
        } elseif ($source === 'qr' && $device['device_type'] !== 'qr_scanner') {
            $failureCode = 'QR_STATION_DEVICE_REQUIRED';
            $failureMessage = 'The QR scan did not originate from a trusted scanner station.';
        }

        $rawPunch = trim(isset($event['punch_state']) ? (string) $event['punch_state'] : '');
        $punchMap = $this->punchStateMap($integrationId);
        $direction = $this->policy->directionFromState($rawPunch, $punchMap);
        if ($failureCode === null && $direction === null) {
            $failureCode = 'UNKNOWN_PUNCH_STATE';
            $failureMessage = 'The punch state is not mapped to IN or OUT.';
        }
        if ($failureCode === null && !empty($event['direction'])
            && strtoupper((string) $event['direction']) !== $direction) {
            $failureCode = 'DIRECTION_CONFLICT';
            $failureMessage = 'The supplied direction contradicts the configured punch state.';
        }

        $mapping = null;
        if (!empty($context['resolved_subject_type']) && !empty($context['resolved_subject_id'])) {
            $mapping = array(
                'subject_type' => $context['resolved_subject_type'],
                'subject_id' => (int) $context['resolved_subject_id'],
            );
        } elseif ($failureCode === null || in_array($failureCode, array('UNKNOWN_PUNCH_STATE', 'DIRECTION_CONFLICT'), true)) {
            $mapping = $this->model->findMappingByCode($personCode, $time['date']);
        }
        if ($failureCode === null && !$mapping) {
            $failureCode = 'UNKNOWN_PERSON';
            $failureMessage = 'The device person code is not mapped to a student or staff member.';
        }
        if ($failureCode === null && !$this->subjectExists($mapping['subject_type'], (int) $mapping['subject_id'])) {
            $failureCode = 'INACTIVE_SUBJECT';
            $failureMessage = 'The mapped student-session or staff record is no longer active.';
        }

        $verification = strtolower(trim(isset($event['verification_method']) ? (string) $event['verification_method'] : 'unknown'));
        if (!in_array($verification, array('face', 'fingerprint', 'card', 'pin', 'qr', 'unknown'), true)) {
            $verification = 'unknown';
        }
        $dedupScope = $integrationId !== null ? 'integration:' . $integrationId : 'device:' . (isset($device['id']) ? $device['id'] : $serial);
        $dedupKey = hash('sha256', $source . '|' . $dedupScope . '|' . $externalId);
        $metadata = $this->policy->sanitizedMetadata($event);
        $payloadHash = hash('sha256', json_encode($this->canonicalize(array(
            'external_event_id' => $externalId,
            'person_code' => $personCode,
            'occurred_at_utc' => $time['utc'],
            'device_serial' => $serial,
            'punch_state' => $rawPunch,
            'verification_method' => $verification,
            'metadata' => $metadata,
        ))));
        $duplicate = $this->model->findEventByDedupKey($dedupKey);
        if ($duplicate) {
            return $baseResult + $this->duplicateEventResult($duplicate, $payloadHash);
        }

        $now = $this->now();
        $eventRow = array(
            'batch_id' => empty($context['batch_id']) ? null : (int) $context['batch_id'],
            'integration_id' => $integrationId,
            'device_id' => $device ? (int) $device['id'] : null,
            'attendance_day_id' => null,
            'dedup_key' => $dedupKey,
            'external_event_id' => $externalId,
            'device_serial' => $serial,
            'person_code' => $personCode,
            'subject_type' => $mapping ? $mapping['subject_type'] : null,
            'subject_id' => $mapping ? (int) $mapping['subject_id'] : null,
            'raw_punch_state' => $rawPunch,
            'direction' => $direction,
            'verification_method' => $verification,
            'source' => $source,
            'operating_mode' => $settings['mode'],
            'occurred_at_utc' => $time['utc'],
            'occurred_at_local' => $time['local'],
            'attendance_date' => $time['date'],
            'received_at' => $now,
            'processing_status' => $failureCode === null ? 'accepted' : 'quarantined',
            'projection_status' => 'not_applicable',
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'payload_hash' => $payloadHash,
            'metadata_json' => $metadata ? json_encode($metadata) : null,
            'processed_at' => $now,
        );

        $this->CI->db->trans_begin();
        $eventId = $this->insertEventIdempotent($eventRow);
        if (!$eventId) {
            $this->CI->db->trans_rollback();
            $duplicate = $this->model->findEventByDedupKey($dedupKey);
            return $baseResult + ($duplicate
                ? $this->duplicateEventResult($duplicate, $payloadHash)
                : array('status' => 'rejected', 'message' => 'The event could not be stored safely.'));
        }
        if ($failureCode !== null) {
            $exceptionId = $this->openException($eventId, null, $failureCode, $failureMessage);
            $this->CI->db->trans_commit();
            return $baseResult + array(
                'status' => 'quarantined',
                'message' => $failureMessage,
                'code' => $failureCode,
                'event_id' => $eventId,
                'exception_id' => $exceptionId,
                'attendance_day_id' => null,
                'projection_status' => 'not_applicable',
            );
        }

        $day = $this->recomputeDay(
            $mapping['subject_type'],
            (int) $mapping['subject_id'],
            $time['date'],
            $settings['mode'],
            $settings
        );
        $this->CI->db->where('id', $eventId)->update('biometric_events', array(
            'attendance_day_id' => $day['id'],
            'projection_status' => $day['projection_status'],
        ));
        if ($device) {
            $this->CI->db->where('id', $device['id'])->update('biometric_devices', array('last_seen_at' => $now, 'updated_at' => $now));
        }
        if (!$this->CI->db->trans_status()) {
            $this->CI->db->trans_rollback();
            return $baseResult + array('status' => 'rejected', 'message' => 'The event could not be stored safely.');
        }
        $this->CI->db->trans_commit();

        return $baseResult + array(
            'status' => 'accepted',
            'message' => 'The event was stored and processed.',
            'event_id' => $eventId,
            'attendance_day_id' => (int) $day['id'],
            'direction' => $direction,
            'attendance_status' => $day['attendance_status'],
            'projection_status' => $day['projection_status'],
        );
    }

    public function listEvents(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_events',
            $this->filter($filters, array(
                'id', 'batch_id', 'integration_id', 'device_id', 'attendance_day_id',
                'external_event_id', 'device_serial', 'person_code', 'subject_type',
                'subject_id', 'direction', 'verification_method', 'source',
                'operating_mode', 'attendance_date', 'processing_status',
                'projection_status', 'failure_code'
            )),
            $page,
            $perPage,
            'id'
        );
    }

    public function listDays(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_attendance_days',
            $this->filter($filters, array(
                'id', 'subject_type', 'subject_id', 'attendance_date', 'record_scope',
                'attendance_status', 'missing_checkout', 'manual_locked', 'projection_status'
            )),
            $page,
            $perPage,
            'attendance_date'
        );
    }

    public function listExceptions(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_exceptions',
            $this->filter($filters, array('id', 'event_id', 'attendance_day_id', 'exception_code', 'status')),
            $page,
            $perPage,
            'id'
        );
    }

    public function listAudit(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_audit_logs',
            $this->filter($filters, array('id', 'actor_id', 'action', 'entity_type', 'entity_id')),
            $page,
            $perPage,
            'id'
        );
    }

    /**
     * $resolution: action=retry|map_and_retry|resolve|ignore, note string,
     * mapping optional saveMapping() payload for map_and_retry.
     */
    public function resolveException($exceptionId, array $resolution, $actorId = null)
    {
        $exception = $this->model->getException((int) $exceptionId);
        if (!$exception) {
            return $this->failure('Exception not found.');
        }
        if ($exception['status'] !== 'open') {
            return array('success' => true, 'item' => $exception, 'event_result' => null, 'errors' => array());
        }
        $action = strtolower(trim(isset($resolution['action']) ? $resolution['action'] : ''));
        if (!in_array($action, array('retry', 'map_and_retry', 'resolve', 'ignore'), true)) {
            return array('success' => false, 'item' => $exception, 'event_result' => null, 'errors' => array('action' => 'Choose retry, map_and_retry, resolve or ignore.'));
        }
        if ($action === 'map_and_retry') {
            if (empty($resolution['mapping']) || !is_array($resolution['mapping'])) {
                return array('success' => false, 'item' => $exception, 'event_result' => null, 'errors' => array('mapping' => 'A mapping is required.'));
            }
            $mapping = $this->saveMapping($resolution['mapping'], $actorId);
            if (empty($mapping['success'])) {
                return array('success' => false, 'item' => $exception, 'event_result' => null, 'errors' => $mapping['errors']);
            }
        }

        $eventResult = null;
        if (in_array($action, array('retry', 'map_and_retry'), true)) {
            if (empty($exception['event_id'])) {
                return array('success' => false, 'item' => $exception, 'event_result' => null, 'errors' => array('event' => 'This exception has no event to retry.'));
            }
            $eventResult = $this->retryQuarantinedEvent((int) $exception['event_id']);
            if (empty($eventResult['success'])) {
                return array('success' => false, 'item' => $exception, 'event_result' => $eventResult, 'errors' => array('event' => $eventResult['message']));
            }
        }

        $now = $this->now();
        $status = $action === 'ignore' ? 'ignored' : 'resolved';
        $this->CI->db->where('id', $exception['id'])->update('biometric_exceptions', array(
            'status' => $status,
            'resolution_action' => $action,
            'resolution_note' => $this->nullableString(isset($resolution['note']) ? $resolution['note'] : null, 1000),
            'resolved_by' => $this->actorId($actorId),
            'resolved_at' => $now,
            'updated_at' => $now,
        ));
        $this->CI->db->insert('biometric_reconciliation_actions', array(
            'exception_id' => (int) $exception['id'],
            'action' => $action,
            'details_json' => json_encode(array('note' => isset($resolution['note']) ? $resolution['note'] : null)),
            'actor_id' => $this->actorId($actorId),
            'created_at' => $now,
        ));
        $item = $this->model->getException($exception['id']);
        $this->audit('exception.' . $action, 'exception', $exception['id'], $exception, $item, $actorId);
        return array('success' => true, 'item' => $item, 'event_result' => $eventResult, 'errors' => array());
    }

    public function listScannerStations(array $filters = array(), $page = 1, $perPage = 50)
    {
        return $this->model->paginate(
            'biometric_scanner_stations',
            $this->filter($filters, array('id', 'station_uuid', 'device_id', 'is_active')),
            $page,
            $perPage,
            'id'
        );
    }

    public function saveScannerStation(array $data, $actorId = null)
    {
        $id = !empty($data['id']) ? (int) $data['id'] : null;
        $before = $id ? $this->CI->db->where('id', $id)->get('biometric_scanner_stations')->row_array() : null;
        $name = trim(isset($data['name']) ? $data['name'] : '');
        if ($name === '' || strlen($name) > 100) {
            return array('success' => false, 'errors' => array('name' => 'Station name is required.'), 'item' => $before);
        }
        $uuid = $before ? $before['station_uuid'] : bin2hex(random_bytes(16));
        $deviceId = !empty($data['device_id']) ? (int) $data['device_id'] : null;
        if ($deviceId) {
            $device = $this->model->getDevice($deviceId);
            if (!$device || $device['device_type'] !== 'qr_scanner') {
                return array('success' => false, 'errors' => array('device_id' => 'Choose a registered QR scanner device.'), 'item' => $before);
            }
        } elseif (!$before) {
            $created = $this->saveDevice(array(
                'serial_number' => 'QR-' . strtoupper(substr($uuid, 0, 16)),
                'name' => $name . ' Browser Scanner',
                'location' => isset($data['location']) ? $data['location'] : null,
                'device_type' => 'qr_scanner',
                'is_virtual' => 0,
                'is_active' => 1,
            ), $actorId);
            if (empty($created['success'])) {
                return array('success' => false, 'errors' => $created['errors'], 'item' => $before);
            }
            $deviceId = (int) $created['item']['id'];
        } elseif ($before) {
            $deviceId = (int) $before['device_id'];
        }

        $now = $this->now();
        $save = array(
            'station_uuid' => $uuid,
            'name' => $name,
            'location' => $this->nullableString(isset($data['location']) ? $data['location'] : null, 191),
            'device_id' => $deviceId,
            'direction_mode' => 'bidirectional',
            'is_active' => isset($data['is_active']) && !$data['is_active'] ? 0 : 1,
            'updated_by' => $this->actorId($actorId),
            'updated_at' => $now,
        );
        if (!$id) {
            $save['created_by'] = $save['updated_by'];
            $save['created_at'] = $now;
            $this->CI->db->insert('biometric_scanner_stations', $save);
            $id = (int) $this->CI->db->insert_id();
        } else {
            $this->CI->db->where('id', $id)->update('biometric_scanner_stations', $save);
        }
        $item = $this->CI->db->where('id', $id)->get('biometric_scanner_stations')->row_array();
        $this->audit($before ? 'scanner.updated' : 'scanner.created', 'scanner_station', $id, $before, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function issueQrCredential($subjectType, $subjectId, $actorId = null, array $options = array())
    {
        $subjectType = strtolower(trim($subjectType));
        $subjectId = (int) $subjectId;
        if (!in_array($subjectType, array('student', 'staff'), true) || !$this->subjectExists($subjectType, $subjectId)) {
            return array('success' => false, 'errors' => array('subject' => 'Choose a valid active student-session or staff record.'), 'item' => null, 'token' => null);
        }
        $expiresAt = isset($options['expires_at']) && $options['expires_at'] !== '' ? $options['expires_at'] : null;
        if ($expiresAt !== null && strtotime($expiresAt) === false) {
            return array('success' => false, 'errors' => array('expires_at' => 'Expiry date is invalid.'), 'item' => null, 'token' => null);
        }

        $uuid = bin2hex(random_bytes(16));
        $secret = $this->base64Url(random_bytes(32));
        $token = 'SLQR1.' . $uuid . '.' . $secret;
        $ciphertext = $this->encryptQrToken($token);
        if ($ciphertext === null) {
            return array(
                'success' => false,
                'errors' => array('encryption' => 'QR issuance requires OpenSSL and BIOMETRIC_QR_ENCRYPTION_KEY with at least 32 characters.'),
                'item' => null,
                'token' => null,
                'reprintable' => false,
            );
        }

        $now = $this->now();
        $this->CI->db->trans_begin();
        $this->CI->db->where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->where('is_active', 1)->update('biometric_qr_credentials', array(
                'is_active' => 0,
                'revoked_by' => $this->actorId($actorId),
                'revoked_at' => $now,
                'revoke_reason' => 'Reissued',
            ));

        $row = array(
            'credential_uuid' => $uuid,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'token_hash' => hash('sha256', $token),
            'token_ciphertext' => $ciphertext,
            'is_active' => 1,
            'issued_by' => $this->actorId($actorId),
            'issued_at' => $now,
            'expires_at' => $expiresAt ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null,
        );
        $this->CI->db->insert('biometric_qr_credentials', $row);
        $id = (int) $this->CI->db->insert_id();
        if ($id < 1 || !$this->CI->db->trans_status()) {
            $this->CI->db->trans_rollback();
            return array(
                'success' => false,
                'errors' => array('storage' => 'QR credential could not be stored. The previous credential remains active.'),
                'item' => null,
                'token' => null,
                'reprintable' => false,
            );
        }
        $this->CI->db->trans_commit();
        $item = $this->CI->db->where('id', $id)->get('biometric_qr_credentials')->row_array();
        unset($item['token_hash'], $item['token_ciphertext']);
        $this->audit('qr.issued', 'qr_credential', $id, null, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item, 'token' => $token, 'reprintable' => true);
    }

    public function qrEncryptionReady()
    {
        $configured = getenv('BIOMETRIC_QR_ENCRYPTION_KEY');
        if (!is_string($configured) || strlen($configured) < 32
            || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            return false;
        }
        if (function_exists('openssl_get_cipher_methods')) {
            $methods = array_map('strtolower', openssl_get_cipher_methods());
            if (!in_array('aes-256-cbc', $methods, true)) {
                return false;
            }
        }
        return true;
    }

    public function getQrCredentialToken($credentialId)
    {
        $row = $this->CI->db->where('id', (int) $credentialId)->where('is_active', 1)
            ->get('biometric_qr_credentials')->row_array();
        if (!$row || empty($row['token_ciphertext'])) {
            return null;
        }
        return $this->decryptQrToken($row['token_ciphertext']);
    }

    public function getActiveQrCredential($subjectType, $subjectId, $includeToken = false)
    {
        $row = $this->CI->db->where('subject_type', strtolower(trim((string) $subjectType)))
            ->where('subject_id', (int) $subjectId)->where('is_active', 1)
            ->where('revoked_at IS NULL', null, false)->order_by('id', 'DESC')
            ->limit(1)->get('biometric_qr_credentials')->row_array();
        if (!$row || (!empty($row['expires_at']) && strtotime($row['expires_at']) < time())) {
            return null;
        }
        $token = $includeToken && !empty($row['token_ciphertext'])
            ? $this->decryptQrToken($row['token_ciphertext']) : null;
        unset($row['token_hash'], $row['token_ciphertext']);
        if ($includeToken) {
            $row['token'] = $token;
        }
        return $row;
    }

    public function listQrCredentials(array $filters = array(), $page = 1, $perPage = 50)
    {
        $result = $this->model->paginate(
            'biometric_qr_credentials',
            $this->filter($filters, array('id', 'credential_uuid', 'subject_type', 'subject_id', 'is_active')),
            $page,
            $perPage,
            'id'
        );
        foreach ($result['items'] as &$item) {
            unset($item['token_hash'], $item['token_ciphertext']);
        }
        unset($item);
        return $result;
    }

    public function revokeQrCredential($credentialId, $actorId = null, $reason = 'Revoked by administrator')
    {
        if (is_string($credentialId) && preg_match('/^[a-f0-9]{32}$/', $credentialId)) {
            $this->CI->db->where('credential_uuid', $credentialId);
        } else {
            $this->CI->db->where('id', (int) $credentialId);
        }
        $before = $this->CI->db->get('biometric_qr_credentials')->row_array();
        if (!$before) {
            return $this->failure('QR credential not found.');
        }
        $this->CI->db->where('id', $before['id'])->update('biometric_qr_credentials', array(
            'is_active' => 0,
            'revoked_by' => $this->actorId($actorId),
            'revoked_at' => $this->now(),
            'revoke_reason' => substr(trim((string) $reason), 0, 255),
        ));
        $item = $this->CI->db->where('id', $before['id'])->get('biometric_qr_credentials')->row_array();
        unset($item['token_hash'], $item['token_ciphertext']);
        $this->audit('qr.revoked', 'qr_credential', $before['id'], null, $item, $actorId);
        return array('success' => true, 'errors' => array(), 'item' => $item);
    }

    public function lookupQrCredential($token)
    {
        $token = trim((string) $token);
        if (!preg_match('/^SLQR1\.([a-f0-9]{32})\.([A-Za-z0-9_-]{40,60})$/', $token, $matches)) {
            return null;
        }
        $row = $this->model->getQrCredentialByUuid($matches[1]);
        if (!$row || empty($row['is_active']) || !empty($row['revoked_at'])) {
            return null;
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return null;
        }
        if (!hash_equals($row['token_hash'], hash('sha256', $token))) {
            return null;
        }
        unset($row['token_hash'], $row['token_ciphertext']);
        return $row;
    }

    public function scanQrCredential($token, $stationUuid, $direction, $actorId = null)
    {
        $credential = $this->lookupQrCredential($token);
        if (!$credential) {
            return array('success' => false, 'status' => 'rejected', 'message' => 'QR credential is invalid, expired or revoked.', 'subject' => null);
        }
        $station = $this->model->getScannerStationByUuid(trim((string) $stationUuid));
        if (!$station || empty($station['is_active'])) {
            return array('success' => false, 'status' => 'rejected', 'message' => 'Scanner station is not trusted or is disabled.', 'subject' => null);
        }
        $device = $this->model->getDevice($station['device_id']);
        if (!$device || empty($device['is_active']) || $device['device_type'] !== 'qr_scanner') {
            return array('success' => false, 'status' => 'rejected', 'message' => 'Scanner station device is unavailable.', 'subject' => null);
        }
        $direction = strtoupper(trim((string) $direction));
        if (!in_array($direction, array('IN', 'OUT'), true)) {
            return array('success' => false, 'status' => 'rejected', 'message' => 'Select Check In or Check Out before scanning.', 'subject' => null);
        }

        $settings = $this->getSettings();
        $occurred = $this->localNow($settings['timezone']);
        $result = $this->ingestEvent(array(
            'external_event_id' => 'qr-' . bin2hex(random_bytes(16)),
            'person_code' => 'QR-' . $credential['credential_uuid'],
            'occurred_at' => $occurred->format(DateTime::ATOM),
            'device_serial' => $device['serial_number'],
            'punch_state' => $direction === 'IN' ? '0' : '1',
            'verification_method' => 'qr',
        ), array(
            'source' => 'qr',
            'resolved_subject_type' => $credential['subject_type'],
            'resolved_subject_id' => (int) $credential['subject_id'],
        ));
        if ($result['status'] === 'accepted') {
            $this->CI->db->where('id', $credential['id'])->update('biometric_qr_credentials', array('last_used_at' => $this->now()));
            $this->CI->db->where('id', $station['id'])->update('biometric_scanner_stations', array('last_seen_at' => $this->now(), 'updated_at' => $this->now()));
        }
        return array(
            'success' => $result['status'] === 'accepted',
            'status' => $result['status'],
            'message' => $result['message'],
            'event' => $result,
            'subject' => $this->subjectSummary($credential['subject_type'], (int) $credential['subject_id']),
            'direction' => $direction,
        );
    }

    public function markManualOverride($attendanceTable, $attendanceId, $actorId = null)
    {
        if (!in_array($attendanceTable, array('student_attendences', 'staff_attendance'), true)) {
            return false;
        }
        $row = $this->CI->db->where('id', (int) $attendanceId)->get($attendanceTable)->row_array();
        if (!$row || empty($row['biometric_day_id'])) {
            return false;
        }
        $day = $this->model->getDay($row['biometric_day_id']);
        if (!$day) {
            return false;
        }
        $this->CI->db->where('id', $day['id'])->update('biometric_attendance_days', array(
            'manual_locked' => 1,
            'projection_status' => 'conflict',
            'updated_at' => $this->now(),
        ));
        $this->openException(null, $day['id'], 'MANUAL_OVERRIDE', 'An operator edited the linked official attendance record. Automatic projection is locked.');
        $this->audit('attendance.manual_override', 'attendance_day', $day['id'], $day, $this->model->getDay($day['id']), $actorId);
        return true;
    }

    /**
     * Delete simulation-scope operational rows only. Configuration, mappings,
     * credentials, devices and audit history are retained. The explicit phrase
     * prevents accidental calls from a generic delete action.
     */
    public function purgeSimulationData($actorId = null, $confirmation = null)
    {
        if (!is_string($confirmation) || !hash_equals('PURGE_SIMULATION_DATA', $confirmation)) {
            return array(
                'success' => false,
                'errors' => array('confirmation' => 'Type PURGE_SIMULATION_DATA to confirm.'),
                'counts' => array(),
            );
        }

        $dayRows = $this->CI->db->select('id')->where('record_scope', 'simulation')
            ->get('biometric_attendance_days')->result_array();
        $eventRows = $this->CI->db->select('id')->where('operating_mode', 'simulation')
            ->get('biometric_events')->result_array();
        $dayIds = array_map('intval', array_column($dayRows, 'id'));
        $eventIds = array_map('intval', array_column($eventRows, 'id'));

        if ($dayIds) {
            $studentLinks = $this->CI->db->where_in('biometric_day_id', $dayIds)
                ->count_all_results('student_attendences');
            $staffLinks = $this->CI->db->where_in('biometric_day_id', $dayIds)
                ->count_all_results('staff_attendance');
            if ($studentLinks || $staffLinks) {
                return array(
                    'success' => false,
                    'errors' => array('invariant' => 'Simulation data is unexpectedly linked to official attendance; purge was stopped.'),
                    'counts' => array(),
                );
            }
        }

        $exceptionIds = array();
        if ($eventIds || $dayIds) {
            $this->CI->db->select('id')->from('biometric_exceptions')->group_start();
            if ($eventIds) {
                $this->CI->db->where_in('event_id', $eventIds);
            }
            if ($dayIds) {
                if ($eventIds) {
                    $this->CI->db->or_where_in('attendance_day_id', $dayIds);
                } else {
                    $this->CI->db->where_in('attendance_day_id', $dayIds);
                }
            }
            $exceptionRows = $this->CI->db->group_end()->get()->result_array();
            $exceptionIds = array_map('intval', array_column($exceptionRows, 'id'));
        }

        $counts = array(
            'events' => count($eventIds),
            'days' => count($dayIds),
            'exceptions' => count($exceptionIds),
            'reconciliation_actions' => 0,
        );
        if ($exceptionIds) {
            $counts['reconciliation_actions'] = (int) $this->CI->db->where_in('exception_id', $exceptionIds)
                ->count_all_results('biometric_reconciliation_actions');
        }

        $this->CI->db->trans_start();
        if ($exceptionIds) {
            $this->CI->db->where_in('exception_id', $exceptionIds)->delete('biometric_reconciliation_actions');
            $this->CI->db->where_in('id', $exceptionIds)->delete('biometric_exceptions');
        }
        if ($eventIds) {
            $this->CI->db->where_in('id', $eventIds)->delete('biometric_events');
        }
        if ($dayIds) {
            $this->CI->db->where_in('id', $dayIds)->delete('biometric_attendance_days');
        }
        $this->audit('simulation.purged', 'simulation_data', null, null, $counts, $actorId);
        $this->CI->db->trans_complete();
        if (!$this->CI->db->trans_status()) {
            return array('success' => false, 'errors' => array('general' => 'Simulation data could not be purged.'), 'counts' => array());
        }
        return array('success' => true, 'errors' => array(), 'counts' => $counts);
    }

    protected function recomputeDay($subjectType, $subjectId, $date, $scope, array $settings)
    {
        $now = $this->now();
        $this->CI->db->query("INSERT INTO `biometric_attendance_days`
            (`subject_type`, `subject_id`, `attendance_date`, `record_scope`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `updated_at` = VALUES(`updated_at`)",
            array($subjectType, $subjectId, $date, $scope, $now, $now));

        $day = $this->CI->db->query(
            'SELECT * FROM `biometric_attendance_days` WHERE `subject_type` = ? AND `subject_id` = ? AND `attendance_date` = ? AND `record_scope` = ? FOR UPDATE',
            array($subjectType, $subjectId, $date, $scope)
        )->row_array();

        $events = $this->CI->db->select('direction, occurred_at_local')->from('biometric_events')
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->where('attendance_date', $date)->where('operating_mode', $scope)
            ->where('processing_status', 'accepted')->get()->result_array();
        $lateAfter = $subjectType === 'student' ? $settings['student_late_after'] : $settings['staff_late_after'];
        $aggregate = $this->policy->aggregateDay($events, $lateAfter);
        $attendanceType = null;
        if ($aggregate['first_in_at'] !== null) {
            if ($subjectType === 'student') {
                $attendanceType = $aggregate['attendance_status'] === 'late'
                    ? (int) $settings['student_late_type_id'] : (int) $settings['student_present_type_id'];
            } else {
                $attendanceType = $aggregate['attendance_status'] === 'late'
                    ? (int) $settings['staff_late_type_id'] : (int) $settings['staff_present_type_id'];
            }
        }

        $sessionId = null;
        $term = null;
        if ($subjectType === 'student') {
            $studentSession = $this->CI->db->select('session_id')->where('id', $subjectId)
                ->get('student_session')->row_array();
            $sessionId = isset($studentSession['session_id']) ? (int) $studentSession['session_id'] : null;
            $school = $this->CI->db->select('term')->limit(1)->get('sch_settings')->row_array();
            $term = isset($school['term']) ? $school['term'] : null;
        }

        $save = array(
            'academic_session_id' => $sessionId,
            'term' => $term,
            'first_in_at' => $aggregate['first_in_at'],
            'last_out_at' => $aggregate['last_out_at'],
            'duration_minutes' => $aggregate['duration_minutes'],
            'attendance_status' => $aggregate['attendance_status'],
            'attendance_type_id' => $attendanceType,
            'missing_checkout' => $aggregate['missing_checkout'],
            'projection_status' => $scope === 'live' ? 'pending' : 'not_applicable',
            'updated_at' => $now,
        );
        if (!empty($day['manual_locked'])) {
            $save['projection_status'] = 'conflict';
        }
        $this->CI->db->where('id', $day['id'])->update('biometric_attendance_days', $save);
        $day = $this->model->getDay($day['id']);

        if ($aggregate['problem'] !== null) {
            $message = $aggregate['problem'] === 'OUT_WITHOUT_IN'
                ? 'A Check Out event exists without a valid Check In event.'
                : 'The latest Check Out occurred before the earliest Check In.';
            $this->openException(null, $day['id'], $aggregate['problem'], $message);
        } else {
            $this->autoResolveDayTimingExceptions($day['id']);
        }

        if ($this->shouldProjectOfficial($scope, !empty($day['manual_locked']), $aggregate['first_in_at'] !== null)) {
            $day = $this->projectDay($day, $settings);
        }
        $this->CI->db->where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->where('attendance_date', $date)->where('operating_mode', $scope)
            ->where('processing_status', 'accepted')->update('biometric_events', array(
                'attendance_day_id' => $day['id'],
                'projection_status' => $day['projection_status'],
            ));
        return $day;
    }

    protected function projectDay(array $day, array $settings)
    {
        if ($day['record_scope'] !== 'live') {
            return $day;
        }
        if (!empty($day['manual_locked'])) {
            return $this->projectionConflict($day, 'MANUAL_OVERRIDE', 'Automatic projection is locked after a manual edit.');
        }
        if ($day['subject_type'] === 'student' && empty($settings['project_students'])) {
            return $this->setDayProjection($day, 'disabled');
        }
        if ($day['subject_type'] === 'staff' && empty($settings['project_staff'])) {
            return $this->setDayProjection($day, 'disabled');
        }
        if ($day['subject_type'] === 'student') {
            $schoolAttendance = $this->CI->db->select('attendence_type')->limit(1)
                ->get('sch_settings')->row_array();
            if (!empty($schoolAttendance['attendence_type'])) {
                return $this->projectionConflict(
                    $day,
                    'PERIOD_ATTENDANCE_UNSUPPORTED',
                    'This school uses period-wise student attendance. Biometric daily sessions remain visible, but official day-wise projection is disabled.'
                );
            }
        }

        $table = $day['subject_type'] === 'student' ? 'student_attendences' : 'staff_attendance';
        $subjectField = $day['subject_type'] === 'student' ? 'student_session_id' : 'staff_id';
        $existing = $this->CI->db->where($subjectField, $day['subject_id'])
            ->where('date', $day['attendance_date'])->order_by('id', 'ASC')->get($table)->result_array();
        $linked = null;
        foreach ($existing as $row) {
            if (!empty($row['biometric_day_id']) && (int) $row['biometric_day_id'] === (int) $day['id']) {
                $linked = $row;
            } else {
                return $this->projectionConflict($day, 'MANUAL_ATTENDANCE_CONFLICT', 'Official attendance already exists for this person and date.');
            }
        }

        $attendanceType = (int) $day['attendance_type_id'];
        $values = array(
            $subjectField => (int) $day['subject_id'],
            'date' => $day['attendance_date'],
            'remark' => '',
            'attendance_source' => 'biometric',
            'biometric_day_id' => (int) $day['id'],
            'created_at' => $day['first_in_at'],
        );
        if ($day['subject_type'] === 'student') {
            $values['attendence_type_id'] = $attendanceType;
            $values['biometric_attendence'] = 1;
            $values['biometric_device_data'] = null;
            $values['is_active'] = 'no';
            if ($this->CI->db->field_exists('session', $table)) {
                $values['session'] = $day['academic_session_id'];
            }
            if ($this->CI->db->field_exists('term', $table)) {
                $values['term'] = $day['term'];
            }
        } else {
            $values['staff_attendance_type_id'] = $attendanceType;
            $values['is_active'] = 0;
        }

        if ($linked) {
            $this->CI->db->where('id', $linked['id'])->update($table, $values);
            $attendanceId = (int) $linked['id'];
        } else {
            $this->CI->db->insert($table, $values);
            $attendanceId = (int) $this->CI->db->insert_id();
        }
        if ($attendanceId < 1) {
            return $this->projectionConflict($day, 'PROJECTION_FAILED', 'Official attendance could not be written.');
        }

        $this->CI->db->where('id', $day['id'])->update('biometric_attendance_days', array(
            'official_table' => $table,
            'official_attendance_id' => $attendanceId,
            'projection_status' => 'projected',
            'updated_at' => $this->now(),
        ));
        return $this->model->getDay($day['id']);
    }

    protected function projectionConflict(array $day, $code, $message)
    {
        $this->CI->db->where('id', $day['id'])->update('biometric_attendance_days', array(
            'projection_status' => 'conflict',
            'updated_at' => $this->now(),
        ));
        $this->openException(null, $day['id'], $code, $message);
        return $this->model->getDay($day['id']);
    }

    protected function setDayProjection(array $day, $status)
    {
        $this->CI->db->where('id', $day['id'])->update('biometric_attendance_days', array(
            'projection_status' => $status,
            'updated_at' => $this->now(),
        ));
        return $this->model->getDay($day['id']);
    }

    protected function retryQuarantinedEvent($eventId)
    {
        $event = $this->model->getEvent($eventId);
        if (!$event || $event['processing_status'] !== 'quarantined') {
            return array('success' => false, 'message' => 'Event is not available for retry.');
        }
        if ($event['failure_code'] === 'DIRECTION_CONFLICT') {
            return array('success' => false, 'message' => 'A contradictory direction is immutable; ignore this event and submit a corrected event with a new external ID.');
        }
        $settings = $this->getSettings();
        if (!$settings || $event['operating_mode'] !== $settings['mode']) {
            return array('success' => false, 'message' => 'Switch back to the event operating mode before retrying it.');
        }
        $device = $this->model->findDeviceBySerial($event['device_serial']);
        if (!$device || empty($device['is_active'])) {
            return array('success' => false, 'message' => 'The event device is still unknown or disabled.');
        }
        if ($event['source'] === 'gateway'
            && ((int) $device['integration_id'] !== (int) $event['integration_id'] || !empty($device['is_virtual']))) {
            return array('success' => false, 'message' => 'The event device is still assigned to a different integration.');
        }
        if ($event['source'] === 'simulator' && empty($device['is_virtual'])) {
            return array('success' => false, 'message' => 'The simulator event still points at a physical device.');
        }
        $mapping = $this->model->findMappingByCode($event['person_code'], $event['attendance_date']);
        if (!$mapping || !$this->subjectExists($mapping['subject_type'], $mapping['subject_id'])) {
            return array('success' => false, 'message' => 'The person code is still not mapped to an active record.');
        }
        $direction = $this->policy->directionFromState($event['raw_punch_state'], $this->punchStateMap($event['integration_id']));
        if ($direction === null) {
            return array('success' => false, 'message' => 'The punch state is still not mapped.');
        }

        $this->CI->db->trans_begin();
        $this->CI->db->where('id', $eventId)->update('biometric_events', array(
            'device_id' => $device['id'],
            'subject_type' => $mapping['subject_type'],
            'subject_id' => $mapping['subject_id'],
            'direction' => $direction,
            'processing_status' => 'accepted',
            'failure_code' => null,
            'failure_message' => null,
            'processed_at' => $this->now(),
        ));
        $day = $this->recomputeDay(
            $mapping['subject_type'],
            (int) $mapping['subject_id'],
            $event['attendance_date'],
            $event['operating_mode'],
            $settings
        );
        $this->CI->db->where('id', $eventId)->update('biometric_events', array(
            'attendance_day_id' => $day['id'],
            'projection_status' => $day['projection_status'],
        ));
        if (!$this->CI->db->trans_status()) {
            $this->CI->db->trans_rollback();
            return array('success' => false, 'message' => 'Retry could not be committed.');
        }
        $this->CI->db->trans_commit();
        return array('success' => true, 'message' => 'Event accepted after reconciliation.', 'event_id' => $eventId, 'attendance_day_id' => (int) $day['id']);
    }

    protected function openException($eventId, $dayId, $code, $message)
    {
        $this->CI->db->from('biometric_exceptions')->where('exception_code', $code)->where('status', 'open');
        if ($eventId !== null) {
            $this->CI->db->where('event_id', (int) $eventId);
        } else {
            $this->CI->db->where('event_id IS NULL', null, false);
        }
        if ($dayId !== null) {
            $this->CI->db->where('attendance_day_id', (int) $dayId);
        } else {
            $this->CI->db->where('attendance_day_id IS NULL', null, false);
        }
        $existing = $this->CI->db->get()->row_array();
        if ($existing) {
            return (int) $existing['id'];
        }
        $now = $this->now();
        $this->CI->db->insert('biometric_exceptions', array(
            'event_id' => $eventId,
            'attendance_day_id' => $dayId,
            'exception_code' => $code,
            'message' => substr((string) $message, 0, 500),
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ));
        return (int) $this->CI->db->insert_id();
    }

    protected function autoResolveDayTimingExceptions($dayId)
    {
        $now = $this->now();
        $this->CI->db->where('attendance_day_id', (int) $dayId)
            ->where_in('exception_code', array('OUT_WITHOUT_IN', 'OUT_BEFORE_IN'))
            ->where('status', 'open')->update('biometric_exceptions', array(
                'status' => 'resolved',
                'resolution_action' => 'auto_recomputed',
                'resolution_note' => 'A later or delayed event completed a valid IN/OUT sequence.',
                'resolved_at' => $now,
                'updated_at' => $now,
            ));
    }

    protected function punchStateMap($integrationId)
    {
        if (!$integrationId) {
            return array('0' => 'IN', '1' => 'OUT');
        }
        $rows = $this->CI->db->select('raw_punch_state, direction')
            ->where('integration_id', (int) $integrationId)
            ->get('biometric_punch_state_mappings')->result_array();
        $map = array();
        foreach ($rows as $row) {
            $map[(string) $row['raw_punch_state']] = strtoupper($row['direction']);
        }
        return $map;
    }

    protected function shouldProjectOfficial($scope, $manualLocked, $hasCheckIn)
    {
        return $scope === 'live' && !$manualLocked && $hasCheckIn;
    }

    protected function duplicateEventResult(array $duplicate, $payloadHash)
    {
        if (empty($duplicate['payload_hash']) || !hash_equals($duplicate['payload_hash'], $payloadHash)) {
            $exceptionId = $this->openException(
                (int) $duplicate['id'],
                !empty($duplicate['attendance_day_id']) ? (int) $duplicate['attendance_day_id'] : null,
                'EXTERNAL_EVENT_ID_CONFLICT',
                'The same external event ID was received with different attendance data.'
            );
            return array(
                'status' => 'rejected',
                'code' => 'EXTERNAL_EVENT_ID_CONFLICT',
                'message' => 'external_event_id conflicts with a previously stored event.',
                'event_id' => (int) $duplicate['id'],
                'exception_id' => $exceptionId,
                'attendance_day_id' => !empty($duplicate['attendance_day_id']) ? (int) $duplicate['attendance_day_id'] : null,
                'projection_status' => $duplicate['projection_status'],
            );
        }
        return array(
            'status' => 'duplicate',
            'message' => 'The event was already stored.',
            'event_id' => (int) $duplicate['id'],
            'attendance_day_id' => !empty($duplicate['attendance_day_id']) ? (int) $duplicate['attendance_day_id'] : null,
            'projection_status' => $duplicate['projection_status'],
        );
    }

    protected function insertEventIdempotent(array $eventRow)
    {
        $sql = $this->CI->db->insert_string('biometric_events', $eventRow);
        $sql = preg_replace('/^INSERT INTO/i', 'INSERT IGNORE INTO', $sql, 1);
        $this->CI->db->query($sql);
        return $this->CI->db->affected_rows() === 1 ? (int) $this->CI->db->insert_id() : null;
    }

    protected function seedPunchMappings($integrationId)
    {
        $now = $this->now();
        foreach (array('0' => 'IN', '1' => 'OUT') as $state => $direction) {
            $this->CI->db->query("INSERT INTO `biometric_punch_state_mappings`
                (`integration_id`, `raw_punch_state`, `direction`, `created_at`, `updated_at`)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE `direction` = VALUES(`direction`), `updated_at` = VALUES(`updated_at`)",
                array((int) $integrationId, $state, $direction, $now, $now));
        }
    }

    protected function subjectExists($subjectType, $subjectId)
    {
        if ($subjectType === 'student') {
            $setting = $this->CI->db->select('session_id')->limit(1)->get('sch_settings')->row_array();
            return (bool) $this->CI->db->from('student_session')
                ->join('students', 'students.id = student_session.student_id')
                ->where('student_session.id', (int) $subjectId)
                ->where('student_session.session_id', isset($setting['session_id']) ? (int) $setting['session_id'] : 0)
                ->where('students.is_active', 'yes')->count_all_results();
        }
        if ($subjectType === 'staff') {
            return (bool) $this->CI->db->from('staff')->where('id', (int) $subjectId)
                ->where('is_active', 1)->count_all_results();
        }
        return false;
    }

    protected function attendanceTypeExists($table, $typeId)
    {
        if (!in_array($table, array('attendence_type', 'staff_attendance_type'), true)
            || !$this->CI->db->table_exists($table)) {
            return false;
        }
        $this->CI->db->from($table)->where('id', (int) $typeId);
        if ($this->CI->db->field_exists('is_active', $table)) {
            $this->CI->db->where_in('is_active', array('yes', '1', 1));
        }
        return (bool) $this->CI->db->count_all_results();
    }

    protected function subjectSummary($subjectType, $subjectId)
    {
        if ($subjectType === 'student') {
            return $this->CI->db->select("student_session.id AS subject_id, 'student' AS subject_type, students.admission_no AS code, CONCAT_WS(' ', students.firstname, students.middlename, students.lastname) AS name, students.image, classes.class, sections.section", false)
                ->from('student_session')->join('students', 'students.id = student_session.student_id')
                ->join('classes', 'classes.id = student_session.class_id', 'left')
                ->join('sections', 'sections.id = student_session.section_id', 'left')
                ->where('student_session.id', (int) $subjectId)->get()->row_array();
        }
        return $this->CI->db->select("staff.id AS subject_id, 'staff' AS subject_type, staff.employee_id AS code, CONCAT_WS(' ', staff.name, staff.surname) AS name, staff.image", false)
            ->from('staff')->where('staff.id', (int) $subjectId)->get()->row_array();
    }

    protected function newIntegrationToken()
    {
        $prefix = bin2hex(random_bytes(6));
        $secret = $this->base64Url(random_bytes(48));
        $token = 'slbio_' . $prefix . '_' . $secret;
        return array($token, $prefix, hash('sha256', $token));
    }

    protected function encryptQrToken($token)
    {
        if (!$this->qrEncryptionReady()) {
            return null;
        }
        $configured = getenv('BIOMETRIC_QR_ENCRYPTION_KEY');
        $key = hash('sha256', $configured, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($token, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return null;
        }
        $mac = hash_hmac('sha256', $iv . $cipher, $key, true);
        return base64_encode($iv . $mac . $cipher);
    }

    protected function decryptQrToken($encoded)
    {
        if (!$this->qrEncryptionReady()) {
            return null;
        }
        $configured = getenv('BIOMETRIC_QR_ENCRYPTION_KEY');
        $raw = base64_decode((string) $encoded, true);
        if ($raw === false || strlen($raw) < 49) {
            return null;
        }
        $key = hash('sha256', $configured, true);
        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $cipher = substr($raw, 48);
        if (!hash_equals($mac, hash_hmac('sha256', $iv . $cipher, $key, true))) {
            return null;
        }
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    protected function publicIntegration(array $row)
    {
        unset($row['token_hash']);
        $row['has_token'] = !empty($row['token_prefix']);
        return $row;
    }

    protected function audit($action, $entityType, $entityId, $before, $after, $actorId = null)
    {
        if (!$this->CI->db->table_exists('biometric_audit_logs')) {
            return;
        }
        $this->CI->db->insert('biometric_audit_logs', array(
            'actor_id' => $this->actorId($actorId),
            'action' => substr((string) $action, 0, 64),
            'entity_type' => $this->nullableString($entityType, 40),
            'entity_id' => $entityId === null ? null : substr((string) $entityId, 0, 64),
            'before_json' => $before === null ? null : json_encode($before),
            'after_json' => $after === null ? null : json_encode($after),
            'ip_address' => isset($this->CI->input) ? $this->CI->input->ip_address() : null,
            'created_at' => $this->now(),
        ));
    }

    protected function actorId($actorId)
    {
        if ($actorId !== null && (int) $actorId > 0) {
            return (int) $actorId;
        }
        if (isset($this->CI->customlib) && method_exists($this->CI->customlib, 'getStaffID')) {
            $id = (int) $this->CI->customlib->getStaffID();
            return $id > 0 ? $id : null;
        }
        return null;
    }

    protected function localNow($timezone)
    {
        try {
            return new DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (Exception $exception) {
            return new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
        }
    }

    protected function now()
    {
        return gmdate('Y-m-d H:i:s');
    }

    protected function validDate($value)
    {
        $date = DateTime::createFromFormat('Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    protected function nullableString($value, $limit)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return substr(trim((string) $value), 0, $limit);
    }

    protected function only(array $data, array $allowed)
    {
        $result = array();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }

    protected function filter(array $filters, array $allowed)
    {
        return $this->only($filters, $allowed);
    }

    protected function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $keys = array_keys($value);
        $isList = $keys === range(0, count($value) - 1);
        if (!$isList) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $child) {
            $value[$key] = $this->canonicalize($child);
        }
        return $value;
    }

    protected function base64Url($bytes)
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    protected function failure($message)
    {
        return array('success' => false, 'errors' => array('general' => $message), 'message' => $message, 'item' => null);
    }
}
