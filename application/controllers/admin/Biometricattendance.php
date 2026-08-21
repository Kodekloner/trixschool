<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Biometricattendance extends Admin_Controller
{
    private $tokenSessionKey = 'biometric_attendance_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('biometric_attendance_service');
        $this->load->library('enc_lib');
        if (!$this->session->userdata($this->tokenSessionKey)) {
            $this->session->set_userdata($this->tokenSessionKey, $this->newToken());
        }
    }

    public function index()
    {
        $this->requirePrivilege('can_view');
        $this->requireReady();
        $this->session->set_userdata('top_menu', 'Attendance');
        $this->session->set_userdata('sub_menu', 'biometricattendance/index');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $settings = $this->biometric_attendance_service->getSettings();
        $rawDashboard = $this->biometric_attendance_service->dashboard();
        $counters = isset($rawDashboard['counters']) && is_array($rawDashboard['counters'])
            ? $rawDashboard['counters'] : array();
        $integrations = $this->biometric_attendance_service->listIntegrations(array(), 1, 100);
        $integrationItems = isset($integrations['items']) ? $integrations['items'] : array();
        $devices = $this->biometric_attendance_service->listDevices(array(), 1, 100);
        $punchStateMappings = array();
        foreach ($integrationItems as $integration) {
            $punchStateMappings[(int) $integration['id']] = $this->biometric_attendance_service
                ->getPunchStateMappings((int) $integration['id']);
        }
        $stations = $this->biometric_attendance_service->listScannerStations(array(), 1, 100);
        $stationItems = isset($stations['items']) ? $stations['items'] : array();
        $assignedStation = null;
        $assignedStationUuid = (string) $this->session->userdata('biometric_scanner_station_uuid');
        foreach ($stationItems as $station) {
            if (!empty($station['is_active']) && hash_equals((string) $station['station_uuid'], $assignedStationUuid)) {
                $assignedStation = $station;
                break;
            }
        }
        $lastSeen = null;
        $lastCursor = null;
        foreach ($integrationItems as $integration) {
            if (!empty($integration['last_seen_at']) && ($lastSeen === null || $integration['last_seen_at'] > $lastSeen)) {
                $lastSeen = $integration['last_seen_at'];
                $lastCursor = isset($integration['last_cursor']) ? $integration['last_cursor'] : null;
            }
        }
        $readiness = $this->liveReadiness($integrationItems, isset($devices['items']) ? $devices['items'] : array());

        $data = array(
            'title' => 'Biometric Attendance',
            'settings' => $settings,
            'dashboard' => array(
                'entries_today' => isset($counters['check_ins']) ? $counters['check_ins'] : 0,
                'checkouts_today' => isset($counters['check_outs']) ? $counters['check_outs'] : 0,
                'missing_checkout' => isset($counters['missing_checkouts']) ? $counters['missing_checkouts'] : 0,
                'open_exceptions' => isset($counters['open_exceptions']) ? $counters['open_exceptions'] : 0,
                'simulated_events' => isset($counters['simulated']) ? $counters['simulated'] : 0,
                'active_devices' => isset($rawDashboard['devices']) ? count($rawDashboard['devices']) : 0,
                'last_gateway_seen' => $lastSeen,
                'last_cursor' => $lastCursor,
            ),
            'devices' => $devices,
            'mappings' => $this->biometric_attendance_service->listMappings(array(), 1, 100),
            'integrations' => $integrations,
            'punch_state_mappings' => $punchStateMappings,
            'events' => $this->biometric_attendance_service->listEvents(array(), 1, 100),
            'days' => $this->biometric_attendance_service->listDays(array(), 1, 100),
            'exceptions' => $this->biometric_attendance_service->listExceptions(array(), 1, 100),
            'audit' => $this->biometric_attendance_service->listAudit(array(), 1, 100),
            'stations' => $stations,
            'qr_credentials' => $this->biometric_attendance_service->listQrCredentials(array(), 1, 100),
            'qr_encryption_ready' => $this->biometric_attendance_service->qrEncryptionReady(),
            'assigned_station' => $assignedStation,
            'live_readiness' => $readiness,
            'student_attendance_types' => $this->db->order_by('id', 'ASC')->get('attendence_type')->result_array(),
            'staff_attendance_types' => $this->db->order_by('id', 'ASC')->get('staff_attendance_type')->result_array(),
            'can_add_biometric' => $this->rbac->hasPrivilege('biometric_attendance', 'can_add'),
            'can_edit_biometric' => $this->rbac->hasPrivilege('biometric_attendance', 'can_edit'),
            'last_simulation' => $this->session->userdata('biometric_last_simulation'),
            'terminal_result' => $this->session->flashdata('biometric_terminal_result'),
            'scan_result' => $this->session->flashdata('biometric_scan_result'),
            'mapping_preview' => $this->session->userdata('biometric_mapping_preview'),
            'biometric_token' => $this->session->userdata($this->tokenSessionKey),
            'message' => $this->session->flashdata('biometric_message'),
            'issued_token' => $this->session->flashdata('biometric_issued_token'),
            'issued_credential' => $this->session->flashdata('biometric_issued_credential'),
        );

        $this->load->view('layout/header', $data);
        $this->load->view('admin/biometricattendance/index', $data);
        $this->load->view('layout/footer', $data);
    }

    public function settings()
    {
        $this->requireMutation('can_edit');
        $before = $this->biometric_attendance_service->getSettings();
        $requestedMode = strtolower(trim((string) $this->input->post('mode', true)));
        $liveAuthorized = false;
        if ($requestedMode === 'live' && (!is_array($before) || $before['mode'] !== 'live')) {
            $integrations = $this->biometric_attendance_service->listIntegrations(array(), 1, 100);
            $devices = $this->biometric_attendance_service->listDevices(array(), 1, 100);
            $readiness = $this->liveReadiness(
                isset($integrations['items']) ? $integrations['items'] : array(),
                isset($devices['items']) ? $devices['items'] : array()
            );
            if (!is_array($before) || $before['mode'] !== 'shadow') {
                return $this->failRedirect('Live mode can be enabled only after the physical gateway has been tested in Shadow mode.', '#bio-setup');
            }
            if (empty($readiness['ready'])) {
                return $this->failRedirect('Live preflight failed: ' . implode(' ', $readiness['problems']), '#bio-setup');
            }
            $projectStudents = (int) $this->input->post('project_students') === 1;
            $school = $this->db->select('attendence_type')->limit(1)->get('sch_settings')->row_array();
            if ($projectStudents && isset($school['attendence_type']) && (int) $school['attendence_type'] !== 0) {
                return $this->failRedirect('Student live projection supports daily attendance only. Disable student projection or change the school from period-wise attendance before enabling Live.', '#bio-setup');
            }
            if (!$this->rbac->hasPrivilege('general_setting', 'can_edit')) {
                return $this->failRedirect('General Settings edit permission is also required to enable Live mode.', '#bio-setup');
            }
            $user = $this->customlib->getUserData();
            $password = (string) $this->input->post('current_password', false);
            $liveAuthorized = !empty($user['password']) && $password !== ''
                && $this->enc_lib->passHashDyc($password, $user['password']);
            if (!$liveAuthorized) {
                return $this->failRedirect('Current password confirmation failed. Live mode was not enabled.', '#bio-setup');
            }
        }

        $result = $this->biometric_attendance_service->updateSettings(array(
            'mode' => $requestedMode,
            'timezone' => trim((string) $this->input->post('timezone', true)),
            'student_late_after' => trim((string) $this->input->post('student_late_after', true)),
            'staff_late_after' => trim((string) $this->input->post('staff_late_after', true)),
            'student_present_type_id' => (int) $this->input->post('student_present_type_id'),
            'student_late_type_id' => (int) $this->input->post('student_late_type_id'),
            'staff_present_type_id' => (int) $this->input->post('staff_present_type_id'),
            'staff_late_type_id' => (int) $this->input->post('staff_late_type_id'),
            'project_students' => (int) $this->input->post('project_students'),
            'project_staff' => (int) $this->input->post('project_staff'),
            'retention_days' => (int) $this->input->post('retention_days'),
            'max_event_age_days' => (int) $this->input->post('max_event_age_days'),
        ), $this->actorId(), array('live_authorized' => $liveAuthorized));

        return $this->resultRedirect($result, 'Biometric mode and attendance rules were updated.', '#bio-setup');
    }

    public function integration()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->createIntegrationToken(array(
            'name' => trim((string) $this->input->post('name', true)),
            'provider' => trim((string) $this->input->post('provider', true)),
        ), $this->actorId());
        if (!empty($result['success']) && !empty($result['token'])) {
            $this->session->set_flashdata('biometric_issued_token', $result['token']);
        }
        return $this->resultRedirect($result, 'Integration created. Copy its token now.', '#bio-setup');
    }

    public function rotateintegration()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->rotateIntegrationToken(
            (int) $this->input->post('integration_id'),
            $this->actorId()
        );
        if (!empty($result['success']) && !empty($result['token'])) {
            $this->session->set_flashdata('biometric_issued_token', $result['token']);
        }
        return $this->resultRedirect($result, 'Integration token rotated. Update the gateway before its next run.', '#bio-setup');
    }

    public function toggleintegration()
    {
        $this->requireMutation('can_edit');
        $active = (int) $this->input->post('is_active') === 1;
        $result = $this->biometric_attendance_service->setIntegrationActive(
            (int) $this->input->post('integration_id'),
            $active,
            $this->actorId()
        );
        return $this->resultRedirect($result, $active ? 'Integration enabled.' : 'Integration disabled.', '#bio-setup');
    }

    public function punchstates()
    {
        $this->requireMutation('can_edit');
        $inState = trim((string) $this->input->post('in_state', true));
        $outState = trim((string) $this->input->post('out_state', true));
        if ($inState === '' || $outState === '' || hash_equals($inState, $outState)) {
            return $this->failRedirect('IN and OUT need distinct provider punch-state values.', '#bio-setup');
        }
        $result = $this->biometric_attendance_service->savePunchStateMappings(
            (int) $this->input->post('integration_id'),
            array($inState => 'IN', $outState => 'OUT'),
            $this->actorId()
        );
        return $this->resultRedirect($result, 'Provider punch-state mapping updated.', '#bio-setup');
    }

    public function device()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->saveDevice(array(
            'serial_number' => trim((string) $this->input->post('serial_number', true)),
            'name' => trim((string) $this->input->post('name', true)),
            'location' => trim((string) $this->input->post('location', true)),
            'integration_id' => (int) $this->input->post('integration_id'),
            'device_type' => 'biometric',
            'is_virtual' => 0,
            'is_active' => 1,
        ), $this->actorId());
        return $this->resultRedirect($result, 'The bidirectional terminal was registered.', '#bio-setup');
    }

    public function toggledevice()
    {
        $this->requireMutation('can_edit');
        $active = (int) $this->input->post('is_active') === 1;
        $result = $this->biometric_attendance_service->setDeviceActive(
            (int) $this->input->post('device_id'),
            $active,
            $this->actorId()
        );
        return $this->resultRedirect($result, $active ? 'Device enabled.' : 'Device disabled.', '#bio-setup');
    }

    public function mapping()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->saveMapping(array(
            'subject_type' => trim((string) $this->input->post('subject_type', true)),
            'subject_id' => (int) $this->input->post('subject_id'),
            'external_person_code' => trim((string) $this->input->post('external_person_code', true)),
            'is_active' => 1,
        ), $this->actorId());
        return $this->resultRedirect($result, 'Identity mapping saved.', '#bio-mappings');
    }

    public function togglemapping()
    {
        $this->requireMutation('can_edit');
        $active = (int) $this->input->post('is_active') === 1;
        $result = $this->biometric_attendance_service->setMappingActive(
            (int) $this->input->post('mapping_id'),
            $active,
            $this->actorId()
        );
        return $this->resultRedirect($result, $active ? 'Identity mapping enabled.' : 'Identity mapping disabled.', '#bio-mappings');
    }

    public function bulkseed()
    {
        $this->requireMutation('can_edit');
        $subjectType = trim((string) $this->input->post('subject_type', true));
        $previewHash = trim((string) $this->input->post('preview_hash', true));
        if (!in_array($subjectType, array('student', 'staff'), true) || $previewHash === '') {
            return $this->failRedirect('Preview one roster and confirm the unchanged preview before importing mappings.', '#bio-mappings');
        }
        $summary = $this->biometric_attendance_service->bulkSeedMappings(
            $subjectType,
            $this->actorId(),
            array('preview_hash' => $previewHash)
        );
        $this->session->unset_userdata('biometric_mapping_preview');
        return $this->resultRedirect($summary, 'Unambiguous active roster mappings were imported.', '#bio-mappings');
    }

    public function bulkpreview()
    {
        $this->requireMutation('can_edit');
        $subjectType = trim((string) $this->input->post('subject_type', true));
        if (!in_array($subjectType, array('student', 'staff'), true)) {
            return $this->failRedirect('Choose the student or staff roster to preview.', '#bio-mappings');
        }
        $preview = $this->biometric_attendance_service->previewBulkMappings($subjectType);
        if (empty($preview['success'])) {
            return $this->resultRedirect($preview, '', '#bio-mappings');
        }
        $this->session->set_userdata('biometric_mapping_preview', $preview);
        $this->session->set_flashdata('biometric_message', '<div class="alert alert-info">Review the roster preview below, then confirm only the unambiguous Create rows.</div>');
        redirect(site_url('admin/biometricattendance') . '#bio-mappings');
    }

    public function simulate()
    {
        $this->requireMutation('can_add');
        $settings = $this->biometric_attendance_service->getSettings();
        if (!is_array($settings) || $settings['mode'] !== 'simulation') {
            return $this->failRedirect('The Test Terminal is available only in Simulation mode.', '#bio-terminal');
        }
        $direction = strtoupper(trim((string) $this->input->post('direction', true)));
        if (!in_array($direction, array('IN', 'OUT'), true)) {
            return $this->failRedirect('Choose Check In or Check Out.', '#bio-terminal');
        }
        try {
            $zone = new DateTimeZone($settings['timezone']);
            $localInput = trim((string) $this->input->post('occurred_at', true));
            $occurred = $localInput === '' ? new DateTimeImmutable('now', $zone) : new DateTimeImmutable($localInput, $zone);
        } catch (Exception $exception) {
            return $this->failRedirect('The event date and time are invalid.', '#bio-terminal');
        }
        $externalId = trim((string) $this->input->post('external_event_id', true));
        if ($externalId === '') {
            $externalId = 'sim-' . date('YmdHis') . '-' . substr($this->newToken(), 0, 16);
        }
        $eventPayload = array(
            'external_event_id' => $externalId,
            'person_code' => trim((string) $this->input->post('person_code', true)),
            'occurred_at' => $occurred->format(DATE_ATOM),
            'device_serial' => 'SIM-GATE-001',
            'punch_state' => $direction === 'IN' ? '0' : '1',
            'direction' => $direction,
            'verification_method' => trim((string) $this->input->post('verification_method', true)),
        );
        $result = $this->biometric_attendance_service->ingestEvent(
            $eventPayload,
            array('source' => 'simulator', 'actor_id' => $this->actorId())
        );
        if (in_array(isset($result['status']) ? $result['status'] : '', array('accepted', 'quarantined'), true)) {
            $this->session->set_userdata('biometric_last_simulation', $eventPayload);
        }
        $this->session->set_flashdata('biometric_terminal_result', $result);
        $success = in_array(isset($result['status']) ? $result['status'] : '', array('accepted', 'duplicate', 'quarantined'), true);
        return $this->resultRedirect(array('success' => $success, 'errors' => $success ? array() : array('event' => isset($result['message']) ? $result['message'] : 'Event rejected.')), 'One simulated punch was processed.', '#bio-terminal');
    }

    public function resendsimulation()
    {
        $this->requireMutation('can_add');
        $settings = $this->biometric_attendance_service->getSettings();
        if (!is_array($settings) || $settings['mode'] !== 'simulation') {
            return $this->failRedirect('The Test Terminal is available only in Simulation mode.', '#bio-terminal');
        }
        $eventPayload = $this->session->userdata('biometric_last_simulation');
        if (!is_array($eventPayload) || empty($eventPayload['external_event_id'])) {
            return $this->failRedirect('Submit a simulated event before testing a resend.', '#bio-terminal');
        }
        $result = $this->biometric_attendance_service->ingestEvent(
            $eventPayload,
            array('source' => 'simulator', 'actor_id' => $this->actorId())
        );
        $this->session->set_flashdata('biometric_terminal_result', $result);
        $success = isset($result['status']) && $result['status'] === 'duplicate';
        return $this->resultRedirect(
            array('success' => $success, 'errors' => $success ? array() : array('event' => isset($result['message']) ? $result['message'] : 'Resend failed.')),
            'The identical event ID was safely recognized as a duplicate.',
            '#bio-terminal'
        );
    }

    public function resolveexception()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->resolveException(
            (int) $this->input->post('exception_id'),
            array(
                'action' => trim((string) $this->input->post('action', true)),
                'note' => trim((string) $this->input->post('note', true)),
            ),
            $this->actorId()
        );
        return $this->resultRedirect($result, 'Exception resolution recorded.', '#bio-exceptions');
    }

    public function station()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->saveScannerStation(array(
            'name' => trim((string) $this->input->post('name', true)),
            'location' => trim((string) $this->input->post('location', true)),
            'is_active' => 1,
        ), $this->actorId());
        if (!empty($result['success']) && !empty($result['item']['station_uuid'])) {
            $this->session->set_userdata('biometric_scanner_station_uuid', $result['item']['station_uuid']);
        }
        return $this->resultRedirect($result, 'Trusted scanner station saved.', '#bio-scanner');
    }

    public function assignstation()
    {
        $this->requireMutation('can_edit');
        $stationUuid = trim((string) $this->input->post('station_uuid', true));
        $stations = $this->biometric_attendance_service->listScannerStations(
            array('station_uuid' => $stationUuid, 'is_active' => 1),
            1,
            2
        );
        if (empty($stations['items'])) {
            return $this->failRedirect('Choose an active trusted scanner station.', '#bio-scanner');
        }
        $this->session->set_userdata('biometric_scanner_station_uuid', $stationUuid);
        return $this->resultRedirect(array('success' => true, 'errors' => array()), 'This browser session is assigned to the selected gate scanner.', '#bio-scanner');
    }

    public function issuecredential()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->issueQrCredential(
            trim((string) $this->input->post('subject_type', true)),
            (int) $this->input->post('subject_id'),
            $this->actorId()
        );
        if (!empty($result['success'])) {
            $this->session->set_flashdata('biometric_issued_credential', $result);
        }
        return $this->resultRedirect($result, 'Trusted card credential issued. Copy or print it now.', '#bio-scanner');
    }

    public function revokecredential()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->revokeQrCredential(
            trim((string) $this->input->post('credential_uuid', true)),
            $this->actorId(),
            trim((string) $this->input->post('reason', true))
        );
        return $this->resultRedirect($result, 'Credential revoked.', '#bio-scanner');
    }

    public function scan()
    {
        $this->requireMutation('can_add');
        $settings = $this->biometric_attendance_service->getSettings();
        if (!is_array($settings) || !in_array($settings['mode'], array('simulation', 'live'), true)) {
            return $this->failRedirect('Trusted QR scans are accepted only in Simulation or Live mode.', '#bio-scanner');
        }
        $stationUuid = (string) $this->session->userdata('biometric_scanner_station_uuid');
        if ($stationUuid === '') {
            return $this->failRedirect('This browser session has not been assigned to a trusted scanner station.', '#bio-scanner');
        }
        $result = $this->biometric_attendance_service->scanQrCredential(
            trim((string) $this->input->post('credential', false)),
            $stationUuid,
            strtoupper(trim((string) $this->input->post('direction', true))),
            $this->actorId()
        );
        $this->session->set_flashdata('biometric_scan_result', $result);
        return $this->resultRedirect($result, 'Trusted QR scan processed.', '#bio-scanner');
    }

    public function subjectphoto($subjectType, $subjectId)
    {
        $this->requirePrivilege('can_view');
        $this->requireReady();
        if (!in_array($subjectType, array('student', 'staff'), true) || !ctype_digit((string) $subjectId)) {
            show_404();
        }
        if ($subjectType === 'student') {
            $row = $this->db->select('students.image')
                ->from('student_session')
                ->join('students', 'students.id = student_session.student_id')
                ->where('student_session.id', (int) $subjectId)
                ->where('students.is_active', 'yes')
                ->get()->row_array();
            $directory = 'uploads/student_images';
        } else {
            $row = $this->db->select('image')->where('id', (int) $subjectId)
                ->where('is_active', 1)->get('staff')->row_array();
            $directory = 'uploads/staff_images';
        }
        $image = !empty($row['image']) ? $row['image'] : 'default_male.jpg';
        $this->streamTrustedImage(get_school_asset_url($image, $directory));
    }

    public function purgesimulation()
    {
        $this->requireMutation('can_delete');
        $result = $this->biometric_attendance_service->purgeSimulationData(
            $this->actorId(),
            trim((string) $this->input->post('confirmation', true))
        );
        return $this->resultRedirect($result, 'Simulation event, session, and exception data was purged; its audit history was retained.', '#bio-setup');
    }

    public function subjectsearch($subjectType = 'student')
    {
        $this->requirePrivilege('can_view');
        $this->requireReady();
        if (!in_array($subjectType, array('student', 'staff'), true)) {
            return $this->json(array('results' => array()), 404);
        }
        $query = substr(trim((string) $this->input->get('q', true)), 0, 100);
        if ($subjectType === 'student') {
            $school = $this->db->select('session_id')->limit(1)->get('sch_settings')->row_array();
            $this->db->select("student_session.id, students.admission_no, CONCAT_WS(' ', students.firstname, students.middlename, students.lastname) AS full_name, classes.class, sections.section", false)
                ->from('student_session')
                ->join('students', 'students.id = student_session.student_id')
                ->join('classes', 'classes.id = student_session.class_id', 'left')
                ->join('sections', 'sections.id = student_session.section_id', 'left')
                ->where('student_session.session_id', isset($school['session_id']) ? (int) $school['session_id'] : 0)
                ->where('students.is_active', 'yes');
            if ($query !== '') {
                $this->db->group_start()->like('students.admission_no', $query)
                    ->or_like('students.firstname', $query)->or_like('students.middlename', $query)
                    ->or_like('students.lastname', $query)->group_end();
            }
            $rows = $this->db->order_by('students.firstname', 'ASC')->limit(30)->get()->result_array();
            $results = array();
            foreach ($rows as $row) {
                $results[] = array(
                    'id' => (int) $row['id'],
                    'text' => trim($row['admission_no'] . ' — ' . $row['full_name'] . ' (' . $row['class'] . ' ' . $row['section'] . ')'),
                );
            }
        } else {
            $this->db->select("staff.id, staff.employee_id, CONCAT_WS(' ', staff.name, staff.surname) AS full_name", false)
                ->from('staff')->where('staff.is_active', 1);
            if ($query !== '') {
                $this->db->group_start()->like('staff.employee_id', $query)
                    ->or_like('staff.name', $query)->or_like('staff.surname', $query)->group_end();
            }
            $rows = $this->db->order_by('staff.name', 'ASC')->limit(30)->get()->result_array();
            $results = array();
            foreach ($rows as $row) {
                $results[] = array('id' => (int) $row['id'], 'text' => trim($row['employee_id'] . ' — ' . $row['full_name']));
            }
        }
        return $this->json(array('results' => $results));
    }

    private function requireMutation($privilege)
    {
        $this->requirePrivilege($privilege);
        $this->requirePost();
        $this->requireToken();
        $this->requireReady();
    }

    private function requirePrivilege($action)
    {
        if (!$this->rbac->hasPrivilege('biometric_attendance', $action)) {
            access_denied();
        }
    }

    private function requireReady()
    {
        if (!$this->biometric_attendance_service->isReady()) {
            show_error('Biometric attendance migration 130 has not been installed for this school database.', 503);
        }
    }

    private function requirePost()
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error('This operation accepts POST requests only.', 405);
        }
    }

    private function requireToken()
    {
        $expected = (string) $this->session->userdata($this->tokenSessionKey);
        $provided = (string) $this->input->post('biometric_token');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The biometric form expired or failed its security check. Reload the page and try again.', 403);
        }
    }

    private function resultRedirect(array $result, $successMessage, $fragment)
    {
        if (!empty($result['success'])) {
            $this->session->set_flashdata('biometric_message', '<div class="alert alert-success">' . html_escape($successMessage) . '</div>');
        } else {
            $this->session->set_flashdata('biometric_message', '<div class="alert alert-danger">' . html_escape($this->resultErrors($result)) . '</div>');
        }
        redirect(site_url('admin/biometricattendance') . $fragment);
    }

    private function json(array $payload, $status = 200)
    {
        return $this->output
            ->set_status_header((int) $status)
            ->set_header('Cache-Control: no-store')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function failRedirect($message, $fragment)
    {
        return $this->resultRedirect(array('success' => false, 'errors' => array('request' => $message)), '', $fragment);
    }

    private function resultErrors(array $result)
    {
        if (!empty($result['message'])) {
            return (string) $result['message'];
        }
        if (empty($result['errors']) || !is_array($result['errors'])) {
            return 'The requested operation could not be completed.';
        }
        $messages = array();
        array_walk_recursive($result['errors'], function ($value) use (&$messages) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $messages[] = trim((string) $value);
            }
        });
        return $messages ? implode(' ', array_unique($messages)) : 'The requested operation could not be completed.';
    }

    private function actorId()
    {
        return (int) $this->customlib->getStaffID();
    }

    private function liveReadiness(array $integrations, array $devices)
    {
        $activeIntegrations = array();
        foreach ($integrations as $integration) {
            if (!empty($integration['is_active'])) {
                $activeIntegrations[(int) $integration['id']] = $integration;
            }
        }
        $physical = array();
        foreach ($devices as $device) {
            if (!empty($device['is_active']) && empty($device['is_virtual'])
                && $device['device_type'] === 'biometric'
                && isset($activeIntegrations[(int) $device['integration_id']])) {
                $physical[] = $device;
            }
        }
        $mapped = $this->biometric_attendance_service->listMappings(array('is_active' => 1), 1, 1);
        $exceptions = $this->biometric_attendance_service->listExceptions(array('status' => 'open'), 1, 1);
        $hasPunchMap = false;
        foreach ($activeIntegrations as $integrationId => $integration) {
            $directions = array();
            foreach ($this->biometric_attendance_service->getPunchStateMappings($integrationId) as $mapping) {
                $directions[] = isset($mapping['direction']) ? $mapping['direction'] : null;
            }
            if (in_array('IN', $directions, true) && in_array('OUT', $directions, true)) {
                $hasPunchMap = true;
                break;
            }
        }
        $hasSeenPhysical = false;
        foreach ($physical as $device) {
            if (!empty($device['last_seen_at'])) {
                $hasSeenPhysical = true;
                break;
            }
        }
        $problems = array();
        if (!$activeIntegrations) { $problems[] = 'Create and enable a ZKBio integration.'; }
        if (!$physical) { $problems[] = 'Register one enabled physical bidirectional terminal.'; }
        if (!$hasPunchMap) { $problems[] = 'Confirm distinct IN and OUT punch-state mappings.'; }
        if (empty($mapped['total'])) { $problems[] = 'Map at least one active student or staff identity.'; }
        if (!$hasSeenPhysical) { $problems[] = 'Process at least one accepted physical event in Shadow mode.'; }
        if (!empty($exceptions['total'])) { $problems[] = 'Resolve all open biometric exceptions.'; }
        return array(
            'ready' => !$problems,
            'problems' => $problems,
            'checks' => array(
                'integration' => (bool) $activeIntegrations,
                'physical_device' => (bool) $physical,
                'punch_states' => $hasPunchMap,
                'identity_mapping' => !empty($mapped['total']),
                'shadow_event' => $hasSeenPhysical,
                'exceptions_clear' => empty($exceptions['total']),
            ),
        );
    }

    private function streamTrustedImage($url)
    {
        $parts = parse_url((string) $url);
        $assetHost = isset($parts['host']) ? preg_replace('/:\d+$/', '', strtolower($parts['host'])) : '';
        $requestHost = preg_replace('/:\d+$/', '', strtolower((string) $this->input->server('HTTP_HOST')));
        if ($assetHost !== '' && !in_array($assetHost, array('schoollift.s3.us-east-2.amazonaws.com', $requestHost), true)) {
            show_error('Stored photo location is not permitted.', 403);
        }
        $content = false;
        if ($assetHost === '' || ($requestHost !== '' && $assetHost === $requestHost)) {
            $path = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
            $basePath = trim((string) parse_url(base_url(), PHP_URL_PATH), '/');
            if ($basePath !== '' && strpos($path, $basePath . '/') === 0) {
                $path = substr($path, strlen($basePath) + 1);
            }
            $root = realpath(FCPATH);
            $candidate = realpath(FCPATH . str_replace(array('../', '..\\'), '', $path));
            if ($root && $candidate && strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
                $content = @file_get_contents($candidate);
            }
        } elseif (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ));
            $content = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($status < 200 || $status >= 300) { $content = false; }
        }
        if (!is_string($content) || $content === '') { show_404(); }
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? finfo_buffer($finfo, $content) : '';
        if ($finfo) { finfo_close($finfo); }
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)) {
            show_error('Stored photo is not a supported image.', 415);
        }
        $this->output->set_content_type($mime)
            ->set_header('Cache-Control: private, max-age=300')
            ->set_header('X-Content-Type-Options: nosniff')
            ->set_output($content);
    }

    private function newToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $exception) {
            return hash('sha256', uniqid('biometric-', true) . mt_rand());
        }
    }
}
