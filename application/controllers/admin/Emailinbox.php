<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Emailinbox extends Admin_Controller
{
    private $actionTokenSessionKey = 'shared_email_action_csrf';

    public function __construct()
    {
        parent::__construct();
        $this->load->library('mailer');
        $this->load->model('emailconversation_model');
        $this->load->model('staff_model');
        $this->load->model('setting_model');
        $this->load->helper('support_email');
        $this->config->load('incoming_email', true);

        if (!$this->session->userdata($this->actionTokenSessionKey)) {
            $this->session->set_userdata($this->actionTokenSessionKey, $this->newToken());
        }
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('shared_email', 'can_view')) {
            access_denied();
        }

        $this->requireTables();
        $folder = strtolower(trim((string) $this->input->get('folder', true)));
        if (!in_array($folder, array('all', 'inbox', 'sent', 'unread'), true)) {
            $folder = 'all';
        }

        $filters = array(
            'folder' => $folder,
            'q' => trim((string) $this->input->get('q', true)),
        );

        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'admin/emailinbox');
        $data['conversations'] = $this->emailconversation_model->getConversations($filters);
        $data['counts'] = $this->emailconversation_model->getCounts();
        $data['filters'] = $filters;
        $data['inbound_email_address'] = $this->getInboundEmailAddress();
        $data['can_send'] = $this->rbac->hasPrivilege('external_email', 'can_add');

        $this->load->view('layout/header');
        $this->load->view('admin/emailinbox/index', $data);
        $this->load->view('layout/footer');
    }

    public function view($id)
    {
        if (!$this->rbac->hasPrivilege('shared_email', 'can_view')) {
            access_denied();
        }

        $this->requireTables();
        $conversation = $this->emailconversation_model->get($id);
        if (empty($conversation)) {
            show_404();
        }

        $this->emailconversation_model->markRead($id);
        $conversation['unread_count'] = 0;
        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'admin/emailinbox');
        $data['conversation'] = $conversation;
        $data['messages'] = $this->emailconversation_model->getMessages($id);
        $data['shared_email_action_csrf'] = (string) $this->session->userdata($this->actionTokenSessionKey);
        $data['inbound_email_address'] = $this->getInboundEmailAddress();

        $this->load->view('layout/header');
        $this->load->view('admin/emailinbox/view', $data);
        $this->load->view('layout/footer');
    }

    public function reply($id)
    {
        if (!$this->rbac->hasPrivilege('shared_email', 'can_view')
            || !$this->rbac->hasPrivilege('shared_email', 'can_add')) {
            access_denied();
        }

        $this->requireActionPost();
        $this->requireTables();
        $conversation = $this->emailconversation_model->get($id);
        if (empty($conversation)) {
            show_404();
        }

        $message = trim((string) $this->input->post('message', false));
        $attachments = $this->prepareAttachments();
        $errors = array();
        if ($message === '') {
            $errors[] = 'Reply message is required.';
        } elseif (strlen($message) > 1000000) {
            $errors[] = 'Reply message must not exceed 1 MB.';
        }
        if ($attachments['error'] !== '') {
            $errors[] = $attachments['error'];
        }

        $inboundEmail = $this->getInboundEmailAddress();
        if ($inboundEmail === '') {
            $errors[] = 'The shared reply address is not configured for this school.';
        }

        if (!empty($errors)) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . html_escape(implode(' ', $errors)) . '</div>');
            return redirect('admin/emailinbox/view/' . (int) $id);
        }

        $school = $this->setting_model->get();
        $schoolEmail = !empty($school[0]['email'])
            ? schoollift_support_normalize_email($school[0]['email'])
            : '';
        $staffId = (int) $this->customlib->getStaffID();
        $staff = $staffId > 0 ? $this->staff_model->getAll($staffId) : array();
        $staffName = !empty($staff) ? trim($staff['name'] . ' ' . $staff['surname']) : '';
        $subject = $this->emailconversation_model->formatReplySubject($conversation);
        $headers = $this->emailconversation_model->buildReplyHeaders($id);
        $messageId = $this->emailconversation_model->buildOutgoingMessageId(
            $conversation['conversation_number'],
            $schoolEmail !== '' ? $schoolEmail : $inboundEmail
        );

        $customHeaders = array();
        if ($headers['in_reply_to'] !== '') {
            $customHeaders['In-Reply-To'] = $headers['in_reply_to'];
        }
        if ($headers['references_header'] !== '') {
            $customHeaders['References'] = $headers['references_header'];
        }

        $mailOptions = array(
            'message_id' => $messageId,
            'reply_to_email' => $inboundEmail,
            'reply_to_name' => !empty($school[0]['name']) ? $school[0]['name'] : 'School office',
            'custom_headers' => $customHeaders,
            'to_name' => $conversation['participant_name'],
        );
        $sent = $this->mailer->send_mail(
            $conversation['participant_email'],
            $subject,
            $message,
            $attachments['files'],
            '',
            $mailOptions
        );
        $error = $sent ? '' : $this->mailer->get_last_error();
        $deliveredMessageId = $sent ? $this->mailer->get_last_message_id() : '';
        if ($deliveredMessageId === '') {
            $deliveredMessageId = $messageId;
        }

        $messageRecordId = $this->emailconversation_model->addOutgoingMessage($id, array(
            'sender_staff_id' => $staffId,
            'sender_name' => $staffName,
            'sender_email' => $schoolEmail !== '' ? $schoolEmail : $inboundEmail,
            'recipients' => array($conversation['participant_email']),
            'subject' => $subject,
            'body_text' => $message,
            'body_html' => null,
            'message_id' => $deliveredMessageId,
            'in_reply_to' => $headers['in_reply_to'],
            'references_header' => $headers['references_header'],
            'attachment_names' => $attachments['names'],
            'delivery_status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : $error,
        ));
        if ($messageRecordId === false) {
            log_message('error', 'Shared email conversation #' . (int) $id . ' could not record its outgoing reply.');
        }

        if ($sent && $messageRecordId !== false) {
            $this->session->set_flashdata('msg', '<div class="alert alert-success">Reply sent successfully.</div>');
        } elseif ($sent) {
            $this->session->set_flashdata('msg', '<div class="alert alert-warning">The reply was sent, but its history could not be saved. Contact the system administrator before sending it again.</div>');
        } else {
            $failureMessage = 'Reply could not be sent.';
            if ($error !== '') {
                $failureMessage .= ' ' . $error;
            }
            $hint = $this->mailer->get_last_hint();
            if ($hint !== '') {
                $failureMessage .= ' ' . $hint;
            }
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . html_escape($failureMessage) . '</div>');
        }

        return redirect('admin/emailinbox/view/' . (int) $id);
    }

    protected function getInboundEmailAddress()
    {
        return schoollift_support_configured_inbound_address(
            $this->config->item('ses_inbound_mail_local_part', 'incoming_email'),
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        );
    }

    protected function requireTables()
    {
        if (!$this->emailconversation_model->isReady()) {
            show_error('Shared email tables were not found. Run migration 141 or import docs/all_school_database_migrations.sql first.', 500);
        }
    }

    protected function requireActionPost()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Method not allowed.', 405);
        }

        $expected = (string) $this->session->userdata($this->actionTokenSessionKey);
        $provided = (string) $this->input->post('shared_email_action_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The email form expired. Refresh the page and try again.', 403);
        }

        $this->session->set_userdata($this->actionTokenSessionKey, $this->newToken());
    }

    protected function prepareAttachments()
    {
        $result = array('files' => array(), 'names' => array(), 'error' => '');
        if (empty($_FILES['email_attachment']) || !is_array($_FILES['email_attachment'])) {
            return $result;
        }

        $upload = $_FILES['email_attachment'];
        $names = isset($upload['name']) && is_array($upload['name']) ? $upload['name'] : array();
        $files = array('name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array());

        foreach ($names as $index => $name) {
            $error = isset($upload['error'][$index]) ? (int) $upload['error'][$index] : UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $result['error'] = 'One or more attachments could not be uploaded. Check the file size and try again.';
                return $result;
            }

            $tmpName = isset($upload['tmp_name'][$index]) ? (string) $upload['tmp_name'][$index] : '';
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $result['error'] = 'One or more attachments could not be verified. Please select the files again.';
                return $result;
            }

            $safeName = basename((string) $name);
            $files['name'][] = $safeName;
            $files['type'][] = isset($upload['type'][$index]) ? (string) $upload['type'][$index] : '';
            $files['tmp_name'][] = $tmpName;
            $files['error'][] = UPLOAD_ERR_OK;
            $files['size'][] = isset($upload['size'][$index]) ? (int) $upload['size'][$index] : 0;
            $result['names'][] = $safeName;
        }

        if (!empty($files['name'])) {
            $result['files']['files'] = $files;
        }

        return $result;
    }

    protected function newToken()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(32));
            } catch (Exception $exception) {
                // Fall through for older installations.
            }
        }

        return hash('sha256', uniqid((string) mt_rand(), true));
    }
}
