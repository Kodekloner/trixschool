<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Support extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
        $this->load->library('mailer');
        $this->load->model('supportticket_model');
        $this->load->model('staff_model');
        $this->load->model('setting_model');
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

        $this->load->view('layout/header');
        $this->load->view('admin/support/index', $data);
        $this->load->view('layout/footer');
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

        $this->load->view('layout/header');
        $this->load->view('admin/support/view', $data);
        $this->load->view('layout/footer');
    }

    public function reply($id)
    {
        if (!$this->rbac->hasPrivilege('support_ticket', 'can_add')) {
            access_denied();
        }

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

        $sent = $this->mailer->send_mail($ticket['requester_email'], $subject, $body, array(), '', array(
            'message_id'     => $message_id,
            'custom_headers' => $custom_headers,
        ));

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

        $this->requireSupportTables();
        $this->supportticket_model->delete($id);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Support ticket deleted.</div>');
        redirect('admin/support');
    }

    protected function buildReplyBody($message, $ticket)
    {
        return trim($message) . "\n\n--\nTicket: " . $ticket['ticket_number'];
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
