<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Support extends Admin_Controller
{
    private $notificationTokenSessionKey = 'support_notification_csrf';
    private $actionTokenSessionKey = 'support_action_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->library('mailer');
        $this->config->load('incoming_email', true);
        $this->load->model('supportticket_model');
        $this->load->model('staff_model');
        $this->load->model('setting_model');
        $this->load->model('supportnotification_model');
        $this->load->helper('support_email');

        if (!$this->session->userdata($this->notificationTokenSessionKey)) {
            $this->session->set_userdata($this->notificationTokenSessionKey, $this->newToken());
        }
        if (!$this->session->userdata($this->actionTokenSessionKey)) {
            $this->session->set_userdata($this->actionTokenSessionKey, $this->newToken());
        }
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_view')) {
            access_denied();
        }

        $this->requireSupportTables();
        $this->session->set_userdata('top_menu', 'front_office');
        $this->session->set_userdata('sub_menu', 'admin/support');

        $filters = array(
            'status'   => $this->input->get('status', true),
            'priority' => $this->input->get('priority', true),
            'q'        => $this->input->get('q', true),
        );

        $data['tickets']          = $this->supportticket_model->getTickets($filters);
        $data['counts']           = $this->supportticket_model->getCounts();
        $data['filters']          = $filters;
        $data['status_options']   = $this->statusOptions();
        $data['priority_options'] = $this->priorityOptions();
        $data['inbound_email_address'] = $this->getInboundEmailAddress();
        $staff_id = (int) $this->customlib->getStaffID();
        $staff = $staff_id > 0 ? $this->staff_model->getAll($staff_id) : array();
        $data['notification_table_ready'] = $this->supportnotification_model->queueIsReady();
        $data['notification_preference'] = $data['notification_table_ready']
            ? $this->supportnotification_model->getForStaff($staff_id)
            : array();
        $data['notification_email'] = !empty($staff['email'])
            ? schoollift_support_normalize_email($staff['email'])
            : '';
        $saved_notification_email = !empty($data['notification_preference']['email'])
            ? schoollift_support_normalize_email($data['notification_preference']['email'])
            : '';
        $data['notification_enabled'] = !empty($data['notification_preference']['is_active'])
            && $data['notification_email'] !== ''
            && $saved_notification_email !== ''
            && hash_equals($data['notification_email'], $saved_notification_email);
        $data['support_notification_csrf'] = (string) $this->session->userdata($this->notificationTokenSessionKey);
        $data['support_action_csrf'] = (string) $this->session->userdata($this->actionTokenSessionKey);
        $data['can_send_external_email'] = $this->rbac->hasPrivilege('external_email', 'can_add');

        $this->load->view('layout/header');
        $this->load->view('admin/support/index', $data);
        $this->load->view('layout/footer');
    }

    public function notification()
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_view')) {
            access_denied();
        }

        $this->requireSupportTables();
        $this->requireNotificationPost();
        if (!$this->supportnotification_model->queueIsReady()) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">Email alerts are not ready. Import the all-school database migrations through version 134 first.</div>');
            return redirect('admin/support');
        }

        $staff_id = (int) $this->customlib->getStaffID();
        $staff = $staff_id > 0 ? $this->staff_model->getAll($staff_id) : array();
        $current_email = !empty($staff['email']) ? schoollift_support_normalize_email($staff['email']) : '';
        $existing = $this->supportnotification_model->getForStaff($staff_id);
        $enabled = (int) $this->input->post('enabled') === 1;

        if ($enabled) {
            if ($current_email === '') {
                $this->session->set_flashdata('msg', '<div class="alert alert-danger">Add a valid email address to your staff profile before enabling device alerts.</div>');
                return redirect('admin/support');
            }
            if (!schoollift_support_notification_recipient_allowed($current_email, $this->getInboundEmailAddress())) {
                $this->session->set_flashdata('msg', '<div class="alert alert-danger">The staff email cannot be the same as the school inbox address because that would create an email loop.</div>');
                return redirect('admin/support');
            }
            $notification_email = $current_email;
        } else {
            if (empty($existing)) {
                $this->session->set_flashdata('msg', '<div class="alert alert-info">Email alerts are already disabled.</div>');
                return redirect('admin/support');
            }
            $notification_email = schoollift_support_normalize_email($existing['email']);
        }

        if (!$this->supportnotification_model->setForStaff($staff_id, $notification_email, $enabled)) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">The email alert preference could not be saved. Please refresh the page and try again.</div>');
            return redirect('admin/support');
        }

        $message = $enabled
            ? 'Email alerts enabled for ' . html_escape($notification_email) . '. Add this account to your phone mail app and allow its notifications.'
            : 'Email alerts disabled.';
        $this->session->set_flashdata('msg', '<div class="alert alert-success">' . $message . '</div>');
        return redirect('admin/support');
    }

    public function view($id)
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_view')) {
            access_denied();
        }

        $this->requireSupportTables();
        $ticket = $this->supportticket_model->get($id);
        if (empty($ticket)) {
            show_404();
        }

        $this->session->set_userdata('top_menu', 'front_office');
        $this->session->set_userdata('sub_menu', 'admin/support');

        $data['ticket']           = $ticket;
        $data['messages']         = $this->supportticket_model->getMessages($id);
        $data['staff_list']       = $this->staff_model->getAll(null, 1);
        $data['status_options']   = $this->statusOptions();
        $data['priority_options'] = $this->priorityOptions();
        $data['support_action_csrf'] = (string) $this->session->userdata($this->actionTokenSessionKey);

        $this->load->view('layout/header');
        $this->load->view('admin/support/view', $data);
        $this->load->view('layout/footer');
    }

    public function reply($id)
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_add')) {
            access_denied();
        }

        $this->requireSupportActionPost();

        $this->requireSupportTables();
        $ticket = $this->supportticket_model->get($id);
        if (empty($ticket)) {
            show_404();
        }

        $message = trim((string) $this->input->post('message', false));
        if ($message === '') {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">Reply message is required.</div>');
            redirect('admin/support/view/' . $id);
        }

        $school       = $this->setting_model->get();
        $school_email = !empty($school[0]['email']) ? $school[0]['email'] : '';
        $inbound_email = $this->getInboundEmailAddress();
        $staff_id     = $this->customlib->getStaffID();
        $staff        = !empty($staff_id) ? $this->staff_model->getAll($staff_id) : array();
        $staff_name   = !empty($staff) ? trim($staff['name'] . ' ' . $staff['surname']) : '';
        $subject      = $this->supportticket_model->formatReplySubject($ticket);
        $headers      = $this->supportticket_model->buildReplyHeaders($ticket['id']);
        $message_id   = $this->supportticket_model->buildOutgoingMessageId($ticket['ticket_number'], $school_email);
        $body         = $this->buildReplyBody($message, $ticket);

        $custom_headers = array(
            'X-SchoolLift-Ticket' => $ticket['ticket_number'],
        );

        if (!empty($headers['in_reply_to'])) {
            $custom_headers['In-Reply-To'] = $headers['in_reply_to'];
        }

        if (!empty($headers['references_header'])) {
            $custom_headers['References'] = $headers['references_header'];
        }

        $mail_options = array(
            'message_id'     => $message_id,
            'custom_headers' => $custom_headers,
        );
        if ($inbound_email !== '') {
            $mail_options['reply_to_email'] = $inbound_email;
            $mail_options['reply_to_name'] = !empty($school[0]['name']) ? $school[0]['name'] : 'School office';
        }

        $sent = $this->mailer->send_mail(
            $ticket['requester_email'],
            $subject,
            $body,
            array(),
            '',
            $mail_options
        );

        $this->supportticket_model->addOutgoingReply($ticket['id'], array(
            'sender_staff_id'   => $staff_id,
            'sender_name'       => $staff_name,
            'sender_email'      => $school_email,
            'recipients'        => array($ticket['requester_email']),
            'subject'           => $subject,
            'body_text'         => $body,
            'message_id'        => $message_id,
            'in_reply_to'       => $headers['in_reply_to'],
            'references_header' => $headers['references_header'],
            'delivery_status'   => $sent ? 'sent' : 'failed',
            'error_message'     => $sent ? null : $this->mailer->get_last_error(),
        ));

        if ($sent) {
            $this->session->set_flashdata('msg', '<div class="alert alert-success">Reply sent successfully.</div>');
        } else {
            $hint = $this->mailer->get_last_hint();
            $error = $this->mailer->get_last_error();
            $message = 'Reply could not be sent.';
            if ($error !== '') {
                $message .= ' ' . html_escape($error);
            }
            if ($hint !== '') {
                $message .= ' ' . html_escape($hint);
            }
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . $message . '</div>');
        }

        redirect('admin/support/view/' . $id);
    }

    public function update($id)
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_edit')) {
            access_denied();
        }

        $this->requireSupportActionPost();

        $this->requireSupportTables();
        $ticket = $this->supportticket_model->get($id);
        if (empty($ticket)) {
            show_404();
        }

        $status   = $this->input->post('status', true);
        $priority = $this->input->post('priority', true);
        $assigned = $this->input->post('assigned_staff_id', true);

        $status_options   = $this->statusOptions();
        $priority_options = $this->priorityOptions();

        if (!array_key_exists($status, $status_options)) {
            $status = $ticket['status'];
        }

        if (!array_key_exists($priority, $priority_options)) {
            $priority = $ticket['priority'];
        }

        $closed_at = null;
        if (in_array($status, array('closed', 'resolved'), true)) {
            $closed_at = !empty($ticket['closed_at']) ? $ticket['closed_at'] : date('Y-m-d H:i:s');
        }

        $this->supportticket_model->updateTicket($id, array(
            'status'            => $status,
            'priority'          => $priority,
            'assigned_staff_id' => $assigned !== '' ? (int) $assigned : null,
            'closed_at'         => $closed_at,
        ));

        $this->session->set_flashdata('msg', '<div class="alert alert-success">Support ticket updated.</div>');
        redirect('admin/support/view/' . $id);
    }

    public function delete($id)
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_delete')) {
            access_denied();
        }

        $this->requireSupportActionPost();

        $this->requireSupportTables();
        $this->supportticket_model->delete($id);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Support ticket deleted.</div>');
        redirect('admin/support');
    }

    protected function buildReplyBody($message, $ticket)
    {
        return trim($message) . "\n\n--\nTicket: " . $ticket['ticket_number'];
    }

    protected function getInboundEmailAddress()
    {
        return schoollift_support_configured_inbound_address(
            $this->config->item('ses_inbound_recipient_local_part', 'incoming_email'),
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        );
    }

    protected function requireNotificationPost()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Method not allowed.', 405);
        }

        $expected = (string) $this->session->userdata($this->notificationTokenSessionKey);
        $provided = (string) $this->input->post('support_notification_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The notification form expired. Refresh the page and try again.', 403);
        }

        $this->session->set_userdata($this->notificationTokenSessionKey, $this->newToken());
    }

    protected function requireSupportActionPost()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Method not allowed.', 405);
        }

        $expected = (string) $this->session->userdata($this->actionTokenSessionKey);
        $provided = (string) $this->input->post('support_action_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The support form expired. Refresh the page and try again.', 403);
        }

        $this->session->set_userdata($this->actionTokenSessionKey, $this->newToken());
    }

    protected function newToken()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(32));
            } catch (Exception $exception) {
                // Fall through for older PHP installations.
            }
        }

        return hash('sha256', uniqid((string) mt_rand(), true));
    }

    protected function requireSupportTables()
    {
        if (!$this->db->table_exists('support_tickets') || !$this->db->table_exists('support_messages')) {
            show_error('Support ticket tables were not found. Run migration 127_add_support_tickets or docs/support_email_migration.sql first.', 500);
        }
    }

    protected function statusOptions()
    {
        return array(
            'open'     => 'Open',
            'pending'  => 'Pending',
            'resolved' => 'Resolved',
            'closed'   => 'Closed',
        );
    }

    protected function priorityOptions()
    {
        return array(
            'low'    => 'Low',
            'normal' => 'Normal',
            'high'   => 'High',
            'urgent' => 'Urgent',
        );
    }
}
