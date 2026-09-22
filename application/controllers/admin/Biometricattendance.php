<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Biometricattendance extends Admin_Controller
{
    private $tokenSessionKey = 'biometric_attendance_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('biometric_attendance_model');
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
        $gatewayAgents = $this->biometric_attendance_service->listGatewayAgents(array(), 1, 100);
        $gatewayCommands = $this->biometric_attendance_service->listGatewayCommands(array(), 1, 50);
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
        $filters = $this->listFilters();
        $perPage = 25;

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
            'mappings' => $this->biometric_attendance_service->listMappings($filters['mappings'], $this->queryPage('mapping_page'), $perPage),
            'integrations' => $integrations,
            'gateway_agents' => $gatewayAgents,
            'gateway_commands' => $gatewayCommands,
            'punch_state_mappings' => $punchStateMappings,
            'events' => $this->biometric_attendance_service->listEvents($filters['events'], $this->queryPage('event_page'), $perPage),
            'days' => $this->biometric_attendance_service->listDays($filters['days'], $this->queryPage('day_page'), $perPage),
            'exceptions' => $this->biometric_attendance_service->listExceptions($filters['exceptions'], $this->queryPage('exception_page'), $perPage),
            'audit' => $this->biometric_attendance_service->listAudit($filters['audit'], $this->queryPage('audit_page'), $perPage),
            'stations' => $stations,
            'qr_credentials' => $this->biometric_attendance_service->listQrCredentials(array(), 1, 100),
            'qr_encryption_ready' => $this->biometric_attendance_service->qrEncryptionReady(),
            'assigned_station' => $assignedStation,
            'live_readiness' => $readiness,
            'list_filters' => $filters,
            'notification_queue' => $this->biometric_attendance_service->notificationQueueSummary(),
            'notification_items' => $this->biometric_attendance_service->listNotificationQueue(25),
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
        $proposedSettings = is_array($before) ? $before : array();
        $proposedSettings['project_students'] = (int) $this->input->post('project_students') === 1 ? 1 : 0;
        $proposedSettings['project_staff'] = (int) $this->input->post('project_staff') === 1 ? 1 : 0;
        $proposedSettings['live_pilot_enabled'] = (int) $this->input->post('live_pilot_enabled') === 1 ? 1 : 0;
        $enteringLive = $requestedMode === 'live' && (!is_array($before) || $before['mode'] !== 'live');
        $wideningLive = $requestedMode === 'live' && is_array($before) && $before['mode'] === 'live'
            && ((empty($before['project_students']) && !empty($proposedSettings['project_students']))
                || (empty($before['project_staff']) && !empty($proposedSettings['project_staff']))
                || (!empty($before['live_pilot_enabled']) && empty($proposedSettings['live_pilot_enabled'])));
        $liveAuthorized = false;
        if ($enteringLive || $wideningLive) {
            $integrations = $this->biometric_attendance_service->listIntegrations(array(), 1, 100);
            $devices = $this->biometric_attendance_service->listDevices(array(), 1, 100);
            $readiness = $this->liveReadiness(
                isset($integrations['items']) ? $integrations['items'] : array(),
                isset($devices['items']) ? $devices['items'] : array(),
                $proposedSettings
            );
            if ($enteringLive && (!is_array($before) || $before['mode'] !== 'shadow')) {
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
                return $this->failRedirect('General Settings edit permission is also required to enable or widen Live attendance.', '#bio-setup');
            }
            if ((int) $this->input->post('live_acknowledgement') !== 1) {
                return $this->failRedirect('Confirm the Live-mode acknowledgement after reviewing the physical IN/OUT evidence and projection scope.', '#bio-setup');
            }
            $user = $this->customlib->getUserData();
            $password = (string) $this->input->post('current_password', false);
            $liveAuthorized = !empty($user['password']) && $password !== ''
                && $this->enc_lib->passHashDyc($password, $user['password']);
            if (!$liveAuthorized) {
                return $this->failRedirect('Current password confirmation failed. The Live attendance scope was not changed.', '#bio-setup');
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
            'live_pilot_enabled' => (int) $this->input->post('live_pilot_enabled'),
            'notify_student_in' => (int) $this->input->post('notify_student_in'),
            'notify_student_out' => (int) $this->input->post('notify_student_out'),
            'notify_email' => (int) $this->input->post('notify_email'),
            'notify_sms' => (int) $this->input->post('notify_sms'),
            'notify_whatsapp' => (int) $this->input->post('notify_whatsapp'),
            'retention_days' => (int) $this->input->post('retention_days'),
            'max_event_age_days' => (int) $this->input->post('max_event_age_days'),
        ), $this->actorId(), array(
            'live_authorized' => $liveAuthorized,
            'live_scope_authorized' => $liveAuthorized && $wideningLive,
        ));

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

    /**
     * Queue one fixed connector action. The web server never executes a local
     * process: the authenticated Windows connector collects this request on
     * its next outbound heartbeat.
     */
    public function gatewaycommand()
    {
        $this->requireMutation('can_edit');
        $type = strtolower(trim((string) $this->input->post('command_type', true)));
        $integrationId = (int) $this->input->post('integration_id');
        if (!in_array($type, array('connection_test', 'sync_now', 'retry_failed'), true)) {
            return $this->failRedirect('Choose a supported school-computer connector action.', '#bio-connector');
        }

        $settings = $this->biometric_attendance_service->getSettings();
        if (!is_array($settings) || !in_array($settings['mode'], array('shadow', 'live'), true)) {
            return $this->failRedirect('School-computer connector actions are available only in Shadow or Live mode.', '#bio-connector');
        }
        if ($type === 'retry_failed') {
            $confirmation = trim((string) $this->input->post('confirmation', true));
            if (!hash_equals('RETRY_FAILED_ITEMS', $confirmation)) {
                return $this->failRedirect('Type RETRY_FAILED_ITEMS exactly before retrying permanently failed connector items.', '#bio-connector');
            }
        }

        $result = $this->biometric_attendance_service->queueGatewayCommand(
            $type,
            $integrationId,
            $this->actorId(),
            300
        );
        $labels = array(
            'connection_test' => 'Connection check requested. The school computer should collect it within one minute.',
            'sync_now' => 'Synchronization requested. The school computer should collect it within one minute.',
            'retry_failed' => 'Retry requested. The school computer should collect it within one minute.',
        );
        if (!empty($result['duplicate'])) {
            $labels[$type] = 'That connector action is already waiting or running; another copy was not created.';
        }
        return $this->resultRedirect($result, $labels[$type], '#bio-connector');
    }

    public function punchstates()
    {
        $this->requireMutation('can_edit');
        $settings = $this->biometric_attendance_service->getSettings();
        if (is_array($settings) && $settings['mode'] === 'live') {
            return $this->failRedirect('Return to Shadow before changing the terminal punch-state meanings.', '#bio-setup');
        }
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
            'id' => (int) $this->input->post('device_id'),
            'serial_number' => trim((string) $this->input->post('serial_number', true)),
            'name' => trim((string) $this->input->post('name', true)),
            'location' => trim((string) $this->input->post('location', true)),
            'firmware_version' => trim((string) $this->input->post('firmware_version', true)),
            'integration_id' => (int) $this->input->post('integration_id'),
            'device_type' => 'biometric',
            'is_virtual' => 0,
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
            'live_pilot' => (int) $this->input->post('live_pilot'),
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

    public function togglemappingpilot()
    {
        $this->requireMutation('can_edit');
        $enabled = (int) $this->input->post('live_pilot') === 1;
        $result = $this->biometric_attendance_service->setMappingPilot(
            (int) $this->input->post('mapping_id'),
            $enabled,
            $this->actorId()
        );
        return $this->resultRedirect($result, $enabled ? 'Person added to the Live pilot.' : 'Person removed from the Live pilot.', '#bio-mappings');
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
                'mapping' => array(
                    'subject_type' => trim((string) $this->input->post('subject_type', true)),
                    'subject_id' => (int) $this->input->post('subject_id'),
                    'external_person_code' => trim((string) $this->input->post('external_person_code', true)),
                    'live_pilot' => (int) $this->input->post('live_pilot'),
                    'is_active' => 1,
                ),
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

    public function togglestation()
    {
        $this->requireMutation('can_edit');
        $stationId = (int) $this->input->post('station_id');
        $active = (int) $this->input->post('is_active') === 1;
        $station = $this->biometric_attendance_model->getScannerStation($stationId);
        $result = $this->biometric_attendance_service->setScannerStationActive($stationId, $active, $this->actorId());
        if (!$active && $station && hash_equals((string) $station['station_uuid'], (string) $this->session->userdata('biometric_scanner_station_uuid'))) {
            $this->session->unset_userdata('biometric_scanner_station_uuid');
        }
        return $this->resultRedirect($result, $active ? 'Scanner station enabled.' : 'Scanner station disabled and its device revoked.', '#bio-scanner');
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

    public function runretention()
    {
        $this->requireMutation('can_delete');
        if (!hash_equals('RUN_RETENTION_CLEANUP', trim((string) $this->input->post('confirmation', true)))) {
            return $this->failRedirect('Type RUN_RETENTION_CLEANUP exactly.', '#bio-setup');
        }
        $result = $this->biometric_attendance_service->runRetentionCleanup($this->actorId(), 5000);
        return $this->resultRedirect($result, 'The configured biometric retention policy was applied safely.', '#bio-setup');
    }

    public function processnotifications()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->processNotificationQueue(10);
        $result['success'] = true;
        return $this->resultRedirect(
            $result,
            'Notification queue processed: ' . (int) $result['delivered'] . ' delivered, '
                . (int) $result['retried'] . ' scheduled for retry, ' . (int) $result['failed'] . ' failed.',
            '#bio-setup'
        );
    }

    public function retrynotifications()
    {
        $this->requireMutation('can_edit');
        $result = $this->biometric_attendance_service->retryFailedNotifications(
            $this->actorId(),
            $this->input->post('confirmation', true)
        );
        return $this->resultRedirect(
            $result,
            (int) (isset($result['count']) ? $result['count'] : 0) . ' failed notification(s) were safely returned to the retry queue.',
            '#bio-setup'
        );
    }

    public function eventfeed()
    {
        $this->requirePrivilege('can_view');
        $this->requireReady();
        $after = max(0, (int) $this->input->get('after_id'));
        $result = $this->biometric_attendance_service->recentEventsAfter($after, 50);
        return $this->json(array('success' => true, 'items' => $result['items'], 'server_time' => gmdate(DateTime::ATOM)));
    }

    public function export($type = 'events')
    {
        $this->requirePrivilege('can_view');
        $this->requireReady();
        $filters = $this->listFilters();
        $definitions = array(
            'events' => array(
                'method' => 'listEvents', 'filters' => $filters['events'],
                'columns' => array(
                    'id' => 'Event ID', 'occurred_at_local' => 'Local time', 'subject_name' => 'Person',
                    'subject_code' => 'School code', 'person_code' => 'Device person code',
                    'device_serial' => 'Terminal serial', 'raw_punch_state' => 'Raw punch state',
                    'direction' => 'Direction', 'verification_method' => 'Method', 'source' => 'Source',
                    'operating_mode' => 'Mode', 'processing_status' => 'Event status',
                    'projection_status' => 'Projection', 'failure_code' => 'Exception code',
                ),
            ),
            'days' => array(
                'method' => 'listDays', 'filters' => $filters['days'],
                'columns' => array(
                    'attendance_date' => 'Date', 'subject_type' => 'Type', 'subject_name' => 'Person',
                    'subject_code' => 'School code', 'record_scope' => 'Mode', 'first_in_at' => 'First IN',
                    'last_out_at' => 'Last OUT', 'duration_minutes' => 'Duration minutes',
                    'attendance_status' => 'Attendance status', 'missing_checkout' => 'Missing checkout',
                    'manual_locked' => 'Manual lock', 'projection_status' => 'Projection',
                ),
            ),
            'exceptions' => array(
                'method' => 'listExceptions', 'filters' => $filters['exceptions'],
                'columns' => array(
                    'id' => 'Exception ID', 'created_at' => 'Created', 'exception_code' => 'Code',
                    'message' => 'Message', 'subject_name' => 'Person', 'subject_code' => 'School code',
                    'person_code' => 'Device person code', 'raw_punch_state' => 'Raw punch state',
                    'status' => 'Status', 'resolution_action' => 'Resolution', 'resolution_note' => 'Note',
                ),
            ),
            'mappings' => array(
                'method' => 'listMappings', 'filters' => $filters['mappings'],
                'columns' => array(
                    'subject_type' => 'Type', 'subject_name' => 'Person', 'subject_code' => 'School code',
                    'external_person_code' => 'Device person code', 'live_pilot' => 'Live pilot',
                    'is_active' => 'Active', 'valid_from' => 'Valid from', 'valid_until' => 'Valid until',
                ),
            ),
        );
        if (!isset($definitions[$type])) {
            show_404();
        }
        $definition = $definitions[$type];
        $rows = array();
        for ($page = 1; $page <= 25 && count($rows) < 5000; $page++) {
            $batch = $this->biometric_attendance_service->{$definition['method']}($definition['filters'], $page, 200);
            foreach ($batch['items'] as $item) {
                $rows[] = $item;
                if (count($rows) >= 5000) { break; }
            }
            if ($page >= (int) $batch['pages']) { break; }
        }
        $filename = 'schoollift-biometric-' . $type . '-' . date('Ymd-His') . '.csv';
        $this->output->set_header('Content-Type: text/csv; charset=UTF-8');
        $this->output->set_header('Content-Disposition: attachment; filename="' . $filename . '"');
        $this->output->set_header('Cache-Control: no-store');
        $stream = fopen('php://output', 'w');
        fputcsv($stream, array_values($definition['columns']));
        foreach ($rows as $row) {
            $values = array();
            foreach (array_keys($definition['columns']) as $column) {
                $values[] = $this->csvSafeValue(isset($row[$column]) ? $row[$column] : '');
            }
            fputcsv($stream, $values);
        }
        fclose($stream);
        exit;
    }

    /** Prevent spreadsheet applications from treating exported text as a formula. */
    private function csvSafeValue($value)
    {
        if (!is_scalar($value) || $value === null) {
            return '';
        }
        $value = (string) $value;
        return $value !== '' && preg_match('/^[=+\-@\t\r]/', $value)
            ? "'" . $value : $value;
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
            show_error('Biometric attendance migrations through 140 have not been installed for this school database.', 503);
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

    private function queryPage($name)
    {
        return max(1, min(100000, (int) $this->input->get($name)));
    }

    private function listFilters()
    {
        return array(
            'events' => array_filter(array(
                'search' => $this->queryText('event_search'),
                'date_from' => $this->queryDate('event_from'),
                'date_to' => $this->queryDate('event_to'),
                'direction' => $this->queryEnum('event_direction', array('IN', 'OUT')),
                'operating_mode' => $this->queryEnum('event_mode', array('simulation', 'shadow', 'live')),
                'processing_status' => $this->queryEnum('event_status', array('accepted', 'duplicate', 'quarantined', 'rejected')),
            ), function ($value) { return $value !== null && $value !== ''; }),
            'days' => array_filter(array(
                'date_from' => $this->queryDate('day_from'),
                'date_to' => $this->queryDate('day_to'),
                'subject_type' => $this->queryEnum('day_type', array('student', 'staff')),
                'record_scope' => $this->queryEnum('day_mode', array('simulation', 'shadow', 'live')),
                'attendance_status' => $this->queryEnum('day_status', array('present', 'late', 'incomplete')),
                'missing_checkout' => $this->queryEnum('day_missing', array('0', '1')),
            ), function ($value) { return $value !== null && $value !== ''; }),
            'exceptions' => array_filter(array(
                'search' => $this->queryText('exception_search'),
                'date_from' => $this->queryDate('exception_from'),
                'date_to' => $this->queryDate('exception_to'),
                'status' => $this->queryEnum('exception_status', array('open', 'resolved', 'ignored')),
            ), function ($value) { return $value !== null && $value !== ''; }),
            'mappings' => array_filter(array(
                'search' => $this->queryText('mapping_search'),
                'subject_type' => $this->queryEnum('mapping_type', array('student', 'staff')),
                'is_active' => $this->queryEnum('mapping_active', array('0', '1')),
                'live_pilot' => $this->queryEnum('mapping_pilot', array('0', '1')),
            ), function ($value) { return $value !== null && $value !== ''; }),
            'audit' => array_filter(array(
                'search' => $this->queryText('audit_search'),
                'date_from' => $this->queryDate('audit_from'),
                'date_to' => $this->queryDate('audit_to'),
            ), function ($value) { return $value !== null && $value !== ''; }),
        );
    }

    private function queryText($name)
    {
        return substr(trim((string) $this->input->get($name, true)), 0, 100);
    }

    private function queryEnum($name, array $allowed)
    {
        $value = trim((string) $this->input->get($name, true));
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function queryDate($name)
    {
        $value = trim((string) $this->input->get($name, true));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        list($year, $month, $day) = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : null;
    }

    private function liveReadiness(array $integrations, array $devices, array $proposedSettings = null)
    {
        $activeIntegrations = array();
        foreach ($integrations as $integration) {
            if (!empty($integration['is_active'])) {
                $activeIntegrations[(int) $integration['id']] = $integration;
            }
        }
        // Go-live evidence must describe one coherent connector path. Without
        // this guard, a terminal on integration A, punch states on B, and a
        // healthy connector on C could incorrectly satisfy separate checks.
        $integrationReady = count($activeIntegrations) === 1;
        $integrationId = $integrationReady ? (int) key($activeIntegrations) : null;
        $physical = array();
        foreach ($devices as $device) {
            if (!empty($device['is_active']) && empty($device['is_virtual'])
                && $device['device_type'] === 'biometric'
                && $integrationId !== null && (int) $device['integration_id'] === $integrationId) {
                $physical[] = $device;
            }
        }
        $mapped = $this->biometric_attendance_service->listMappings(array('is_active' => 1), 1, 1);
        $exceptions = $this->biometric_attendance_service->listExceptions(array('status' => 'open'), 1, 1);
        $gatewayAgents = $this->biometric_attendance_service->listGatewayAgents(
            array('integration_id' => $integrationId), 1, 100
        );
        $recentAgents = array();
        foreach (isset($gatewayAgents['items']) ? $gatewayAgents['items'] : array() as $agent) {
            $heartbeat = !empty($agent['last_heartbeat_at']) ? strtotime($agent['last_heartbeat_at'] . ' UTC') : false;
            if ($heartbeat !== false && time() - $heartbeat <= 600) {
                $recentAgents[] = $agent;
            }
        }
        // A second recent connector would poll the same school integration.
        // Keep exactly one active path for the single-school-computer design;
        // stale records from a replaced PC do not block the transition.
        $connectorOnline = count($recentAgents) === 1;
        $activeAgent = $connectorOnline ? $recentAgents[0] : null;
        $connectorHealthy = $activeAgent !== null
            && (int) $activeAgent['provider_reachable'] === 1
            && (int) $activeAgent['last_sync_ok'] === 1;
        $connectorQueueClear = $connectorHealthy
            && (int) $activeAgent['queue_pending'] === 0
            && (int) $activeAgent['queue_retry'] === 0
            && (int) $activeAgent['queue_dead'] === 0;
        $hasPunchMap = false;
        if ($integrationId !== null) {
            $directions = array();
            foreach ($this->biometric_attendance_service->getPunchStateMappings($integrationId) as $mapping) {
                $directions[] = isset($mapping['direction']) ? $mapping['direction'] : null;
            }
            $hasPunchMap = in_array('IN', $directions, true) && in_array('OUT', $directions, true);
        }
        $settingsForLive = $proposedSettings !== null ? $proposedSettings : $this->biometric_attendance_service->getSettings();
        $hasSeenPhysical = !empty($physical);
        foreach ($physical as $device) {
            if (!$this->hasCompletedShadowSession((int) $device['id'], $integrationId, null)) {
                $hasSeenPhysical = false;
                break;
            }
        }
        $pilotOnlyProof = !empty($settingsForLive['live_pilot_enabled']);
        $studentProof = empty($settingsForLive['project_students'])
            || $this->hasCompletedShadowSession(null, $integrationId, 'student', $pilotOnlyProof);
        $staffProof = empty($settingsForLive['project_staff'])
            || $this->hasCompletedShadowSession(null, $integrationId, 'staff', $pilotOnlyProof);
        $studentPilotReady = empty($settingsForLive['live_pilot_enabled'])
            || empty($settingsForLive['project_students'])
            || (bool) $this->db->from('biometric_identity_mappings')
                ->where('subject_type', 'student')->where('is_active', 1)
                ->where('live_pilot', 1)->count_all_results();
        $staffPilotReady = empty($settingsForLive['live_pilot_enabled'])
            || empty($settingsForLive['project_staff'])
            || (bool) $this->db->from('biometric_identity_mappings')
                ->where('subject_type', 'staff')->where('is_active', 1)
                ->where('live_pilot', 1)->count_all_results();
        $pilotReady = $studentPilotReady && $staffPilotReady;
        $problems = array();
        if (!$integrationReady) { $problems[] = 'Enable exactly one ZKBio integration for this school connector path.'; }
        if (!$physical) { $problems[] = 'Register one enabled physical bidirectional terminal.'; }
        if (!$hasPunchMap) { $problems[] = 'Confirm distinct IN and OUT punch-state mappings.'; }
        if (empty($mapped['total'])) { $problems[] = 'Map at least one active student or staff identity.'; }
        if (!$connectorOnline) { $problems[] = 'Keep exactly one Windows connector online for that integration, with a heartbeat within ten minutes.'; }
        if (!$connectorHealthy) { $problems[] = 'Complete a successful ZKBio connection and synchronization check.'; }
        if (!$connectorQueueClear) { $problems[] = 'Drain or resolve every waiting, retrying, and failed connector item before Live.'; }
        if (!$hasSeenPhysical) { $problems[] = 'Complete one accepted physical Shadow IN and OUT session from every enabled terminal.'; }
        if (!$studentProof) { $problems[] = $pilotOnlyProof ? 'Complete a physical Shadow IN and OUT for a selected student pilot.' : 'Complete a physical student IN and OUT in Shadow before enabling student projection.'; }
        if (!$staffProof) { $problems[] = $pilotOnlyProof ? 'Complete a physical Shadow IN and OUT for a selected staff pilot.' : 'Complete a physical staff IN and OUT in Shadow before enabling staff projection.'; }
        if (!$studentPilotReady) { $problems[] = 'Select at least one active student mapping for the Live pilot, or disable student projection.'; }
        if (!$staffPilotReady) { $problems[] = 'Select at least one active staff mapping for the Live pilot, or disable staff projection.'; }
        if (!empty($exceptions['total'])) { $problems[] = 'Resolve all open biometric exceptions.'; }
        return array(
            'ready' => !$problems,
            'problems' => $problems,
            'checks' => array(
                'integration' => $integrationReady,
                'physical_device' => (bool) $physical,
                'punch_states' => $hasPunchMap,
                'identity_mapping' => !empty($mapped['total']),
                'connector_online' => $connectorOnline,
                'connector_healthy' => $connectorHealthy,
                'connector_queue_clear' => $connectorQueueClear,
                'shadow_in_out' => $hasSeenPhysical,
                'student_shadow_in_out' => $studentProof,
                'staff_shadow_in_out' => $staffProof,
                'student_pilot_ready' => $studentPilotReady,
                'staff_pilot_ready' => $staffPilotReady,
                'pilot_ready' => $pilotReady,
                'exceptions_clear' => empty($exceptions['total']),
            ),
        );
    }

    /** A completed Shadow session must contain accepted IN and OUT events from the physical gateway. */
    private function hasCompletedShadowSession($deviceId, $integrationId, $subjectType, $pilotOnly = false)
    {
        if ($integrationId === null) {
            return false;
        }
        $sql = "SELECT 1
                FROM biometric_attendance_days d
                INNER JOIN biometric_events ein
                    ON ein.attendance_day_id = d.id
                   AND ein.direction = 'IN'
                   AND ein.source = 'gateway'
                   AND ein.operating_mode = 'shadow'
                   AND ein.processing_status = 'accepted'
                INNER JOIN biometric_events eout
                    ON eout.attendance_day_id = d.id
                   AND eout.direction = 'OUT'
                   AND eout.source = 'gateway'
                   AND eout.operating_mode = 'shadow'
                   AND eout.processing_status = 'accepted'
                WHERE d.record_scope = 'shadow'
                  AND d.first_in_at IS NOT NULL
                  AND d.last_out_at IS NOT NULL
                  AND ein.integration_id = ?
                  AND eout.integration_id = ?";
        $params = array((int) $integrationId, (int) $integrationId);
        if ($deviceId !== null) {
            $sql .= ' AND ein.device_id = ? AND eout.device_id = ?';
            $params[] = (int) $deviceId;
            $params[] = (int) $deviceId;
        }
        if ($subjectType !== null) {
            $sql .= ' AND d.subject_type = ?';
            $params[] = $subjectType;
        }
        if ($pilotOnly) {
            $sql .= " AND EXISTS (
                         SELECT 1 FROM biometric_identity_mappings pm
                         WHERE pm.subject_type = d.subject_type
                           AND pm.subject_id = d.subject_id
                           AND pm.is_active = 1
                           AND pm.live_pilot = 1
                     )";
        }
        $sql .= ' LIMIT 1';
        return (bool) $this->db->query($sql, $params)->row_array();
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
