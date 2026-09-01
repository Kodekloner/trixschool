<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Mailsms extends Admin_Controller
{
    private $externalEmailTokenSessionKey = 'external_email_csrf';
    private $standardEmailTokenSessionKey = 'standard_email_csrf';

    public function __construct()
    {
        parent::__construct();

        $this->load->library('smsgateway');
        $this->load->library('whatsappgateway');
        $this->load->library('mailsmsconf');
        $this->load->model("classteacher_model");
        $this->load->model("notificationsetting_model");
        $this->mailer;
        $this->sch_setting_detail = $this->setting_model->getSetting();

        if (!$this->session->userdata($this->externalEmailTokenSessionKey)) {
            $this->session->set_userdata($this->externalEmailTokenSessionKey, $this->newExternalEmailToken());
        }
        if (!$this->session->userdata($this->standardEmailTokenSessionKey)) {
            $this->session->set_userdata($this->standardEmailTokenSessionKey, $this->newExternalEmailToken());
        }
    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('email_sms_log', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'mailsms/index');
        $data['title']       = 'Add Mailsms';
        $listMessage         = $this->messages_model->get();
        $data['listMessage'] = $listMessage;
        $this->load->view('layout/header');
        $this->load->view('admin/mailsms/index', $data);
        $this->load->view('layout/footer');
    }

    public function search()
    {
        if (!$this->rbac->hasPrivilege('email', 'can_view')
            && !$this->rbac->hasPrivilege('sms', 'can_view')) {
            access_denied();
        }

        $keyword     = $this->input->post('keyword');
        $category    = $this->input->post('category');
        $result      = array();
        $sch_setting = $this->setting_model->getSetting();
        if ($keyword != "" and $category != "") {
            if ($category == "student") {
                $result = $this->student_model->searchNameLike($keyword);
                foreach ($result as $key => $value) {
                    $result[$key]['fullname'] = $this->customlib->getFullName($value['firstname'], $value['middlename'], $value['lastname'], $sch_setting->middlename, $sch_setting->lastname);
                }
            } elseif ($category == "student_guardian") {
                $result = $this->student_model->searchNameLike($keyword);
                foreach ($result as $key => $value) {
                    $result[$key]['fullname'] = $this->customlib->getFullName($value['firstname'], $value['middlename'], $value['lastname'], $sch_setting->middlename, $sch_setting->lastname);
                }
            } elseif ($category == "parent") {

                $result = $this->student_model->searchGuardianNameLike($keyword);
            } elseif ($category == "staff") {
                $result = $this->staff_model->searchNameLike($keyword);
            } else {

            }
        }

        echo json_encode($result);
    }

    public function compose()
    {
        $can_standard_email = $this->rbac->hasPrivilege('email', 'can_view');
        $can_external_email = $this->rbac->hasPrivilege('external_email', 'can_add');
        if (!$can_standard_email && !$can_external_email) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'Communicate/mailsms/compose');
        $data['title']     = 'Add Mailsms';
        $class             = $this->class_model->get();
        $data['classlist'] = $class;
        $userdata          = $this->customlib->getUserData();
        $carray            = array();

        if (!empty($data["classlist"])) {
            foreach ($data["classlist"] as $ckey => $cvalue) {

                $carray[] = $cvalue["id"];
            }
        }
        $date          = date('Y-m-d');
        $birthDaysList = array();
        $birthStudents = $this->student_model->getBirthDayStudents($date, true);
        $birthStaff    = $this->staff_model->getBirthDayStaff($date, 1, true);

        if (!empty($birthStudents)) {
            $array = array();
            foreach ($birthStudents as $student_key => $student_value) {

                $array[] = array('name' => $this->customlib->getFullName($student_value['firstname'], $student_value['middlename'], $student_value['lastname'], $this->sch_setting_detail->middlename, $this->sch_setting_detail->lastname), 'email' => $student_value['email']);
            }
            $birthDaysList['students'] = $array;
        }
        if (!empty($birthStaff)) {
            $array = array();
            foreach ($birthStaff as $staff_key => $staff_value) {

                $array[] = array('name' => $staff_value['name'], 'email' => $staff_value['email']);
            }
            $birthDaysList['staff'] = $array;
        }

        $data['roles']         = $this->role_model->get();
        $data['birthDaysList'] = $birthDaysList;
        $data['sch_setting']   = $this->sch_setting_detail;
        $data['compose_notifications'] = $this->getComposeNotificationTemplates();
        $this->load->helper('support_email');
        $this->config->load('incoming_email', true);
        $requested_tab = strtolower(trim((string) $this->input->get('tab', true)));
        $data['can_standard_email'] = $can_standard_email;
        $data['can_external_email'] = $can_external_email;
        $data['active_email_tab'] = ($can_external_email && ($requested_tab === 'external' || !$can_standard_email))
            ? 'external'
            : 'group';
        $data['external_email_csrf'] = (string) $this->session->userdata($this->externalEmailTokenSessionKey);
        $data['standard_email_csrf'] = (string) $this->session->userdata($this->standardEmailTokenSessionKey);
        $data['external_email_draft'] = (array) $this->session->flashdata('external_email_draft');
        $data['inbound_email_address'] = schoollift_support_configured_inbound_address(
            $this->config->item('ses_inbound_recipient_local_part', 'incoming_email'),
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        );
        $data['external_email_ready'] = $this->db->table_exists('incoming_emails')
            && $this->db->table_exists('support_tickets')
            && $this->db->table_exists('support_messages')
            && $data['inbound_email_address'] !== '';
        $this->load->view('layout/header');
        $this->load->view('admin/mailsms/compose', $data);
        $this->load->view('layout/footer');
    }

    /**
     * Start an email conversation with a recipient who is not required to be
     * a student, parent, or member of staff.
     */
    public function send_external()
    {
        if (!$this->rbac->hasPrivilege('external_email', 'can_add')) {
            access_denied();
        }
        $this->requireExternalEmailPost();

        $this->load->helper('support_email');
        $this->config->load('incoming_email', true);
        $this->load->model('supportticket_model');
        if (!$this->db->table_exists('incoming_emails')
            || !$this->db->table_exists('support_tickets')
            || !$this->db->table_exists('support_messages')) {
            return $this->externalEmailRedirect(
                'danger',
                'External email storage is not ready. Import the all-school database migrations through version 134 first.'
            );
        }

        $recipientEmail = schoollift_support_normalize_email($this->input->post('external_email', true));
        $recipientName = trim(strip_tags((string) $this->input->post('external_name', true)));
        $subject = schoollift_support_normalize_subject($this->input->post('external_subject', true));
        $rawBody = trim((string) $this->input->post('external_message', false));
        $bodyHtml = trim((string) $this->security->xss_clean($rawBody));
        $bodyText = schoollift_support_html_to_text($bodyHtml);
        $inboundAddress = schoollift_support_configured_inbound_address(
            $this->config->item('ses_inbound_recipient_local_part', 'incoming_email'),
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''
        );

        $errors = array();
        if ($recipientEmail === '') {
            $errors[] = 'Enter a valid external recipient email address.';
        } elseif ($inboundAddress !== '' && hash_equals($inboundAddress, $recipientEmail)) {
            $errors[] = 'The recipient cannot be the school inbox address because that would create an email loop.';
        }
        if ($inboundAddress === '') {
            $errors[] = 'The school inbound reply address could not be determined.';
        }
        if ($recipientName !== '' && strlen($recipientName) > 191) {
            $errors[] = 'Recipient name must not exceed 191 characters.';
        }
        if ($subject === '') {
            $errors[] = 'Email subject is required.';
        }
        if ($bodyText === '') {
            $errors[] = 'Email message is required.';
        } elseif (strlen($rawBody) > 1000000) {
            $errors[] = 'Email message must not exceed 1 MB.';
        }
        if (!empty($errors)) {
            return $this->externalEmailRedirect('danger', implode(' ', $errors));
        }

        $staffId = (int) $this->customlib->getStaffID();
        $ticket = $this->supportticket_model->createOutgoingConversation(array(
            'requester_name'  => $recipientName,
            'requester_email' => $recipientEmail,
            'subject'         => $subject,
            'sender_staff_id' => $staffId,
        ));
        if (empty($ticket)) {
            return $this->externalEmailRedirect('danger', 'The email conversation could not be created. Please try again.');
        }

        $school = $this->setting_model->get();
        $schoolEmail = !empty($school[0]['email']) ? schoollift_support_normalize_email($school[0]['email']) : '';
        $staff = $staffId > 0 ? $this->staff_model->getAll($staffId) : array();
        $staffName = !empty($staff) ? trim($staff['name'] . ' ' . $staff['surname']) : '';
        $threadSubject = schoollift_support_thread_subject($subject, $ticket['ticket_number']);
        $messageId = $this->supportticket_model->buildOutgoingMessageId(
            $ticket['ticket_number'],
            $schoolEmail !== '' ? $schoolEmail : $inboundAddress
        );
        $mailBody = schoollift_support_append_ticket_note($bodyHtml, $ticket['ticket_number'], $inboundAddress);

        $this->load->library('mailer');
        $mailOptions = array(
            'message_id' => $messageId,
            'is_html' => true,
            'custom_headers' => array(
                'X-SchoolLift-Ticket' => $ticket['ticket_number'],
            ),
        );
        if ($inboundAddress !== '') {
            $mailOptions['reply_to_email'] = $inboundAddress;
            $mailOptions['reply_to_name'] = !empty($school[0]['name']) ? $school[0]['name'] : 'School office';
        }

        $sent = $this->mailer->send_mail($recipientEmail, $threadSubject, $mailBody, array(), '', $mailOptions);
        $error = $sent ? '' : $this->mailer->get_last_error();
        $this->supportticket_model->addOutgoingReply($ticket['id'], array(
            'sender_staff_id' => $staffId,
            'sender_name' => $staffName,
            'sender_email' => $schoolEmail !== '' ? $schoolEmail : $inboundAddress,
            'recipients' => array($recipientEmail),
            'subject' => $threadSubject,
            'body_text' => $bodyText . "\n\n--\nTicket: " . $ticket['ticket_number'],
            'body_html' => $mailBody,
            'message_id' => $messageId,
            'delivery_status' => $sent ? 'sent' : 'failed',
            'error_message' => $sent ? null : $error,
        ));

        if ($sent) {
            $this->session->set_flashdata('msg', '<div class="alert alert-success">External email sent successfully. Conversation ' . html_escape($ticket['ticket_number']) . ' has been saved.</div>');
            if ($this->rbac->hasPrivilege('support_ticket', 'can_view')) {
                return redirect('admin/support/view/' . (int) $ticket['id']);
            }
            return redirect('admin/mailsms/compose?tab=external');
        }

        $message = 'The external email could not be delivered, but the failed attempt was saved in ' . $ticket['ticket_number'] . '.';
        if ($error !== '') {
            $message .= ' ' . $error;
        }
        $hint = $this->mailer->get_last_hint();
        if ($hint !== '') {
            $message .= ' ' . $hint;
        }

        return $this->externalEmailRedirect('danger', $message);
    }

    private function getComposeNotificationTemplates()
    {
        $templates = array(
            'custom' => array(
                'label'     => 'Custom',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent', 'roles'),
            ),
            'student_admission' => array(
                'label'     => 'Student Admission',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent'),
            ),
            'exam_result' => array(
                'label'     => 'Exam Result',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent'),
            ),
            'fee_submission' => array(
                'label'     => 'Fee Submission',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('parent'),
            ),
            'absent_attendence' => array(
                'label'     => 'Absent Attendance',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('parent'),
            ),
            'login_credential' => array(
                'label'     => 'Login Credential',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent', 'roles'),
            ),
            'homework' => array(
                'label'     => 'Homework',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent'),
            ),
            'fees_reminder' => array(
                'label'     => 'Fees Reminder',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('parent'),
            ),
            'online_examination_publish_exam' => array(
                'label'     => 'Online examination publish exam',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student'),
            ),
            'online_examination_publish_result' => array(
                'label'     => 'Online examination publish result',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent'),
            ),
            'online_admission_form_submission' => array(
                'label'     => 'Online Admission form submission',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('student', 'parent'),
            ),
            'online_admission_fees_submission' => array(
                'label'     => 'Online Admission fees submission',
                'subject'   => '',
                'template'  => '',
                'variables' => '',
                'audience'  => array('parent'),
            ),
        );

        $notification_settings = $this->notificationsetting_model->get();
        if (!empty($notification_settings)) {
            foreach ($notification_settings as $setting) {
                if (isset($templates[$setting->type])) {
                    $templates[$setting->type]['subject']   = $setting->subject;
                    $templates[$setting->type]['template']  = $setting->template;
                    $templates[$setting->type]['variables'] = $setting->variables;
                }
            }
        }

        return $templates;
    }

    public function compose_sms()
    {
        if (!$this->rbac->hasPrivilege('sms', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'mailsms/compose_sms');
        $data['title']     = 'Add Mailsms';
        $class             = $this->class_model->get();
        $data['classlist'] = $class;
        $userdata          = $this->customlib->getUserData();
        $carray            = array();
        $date              = date('Y-m-d');
        $birthDaysList     = array();
        $birthStudents     = $this->student_model->getBirthDayStudents($date, false, false);
        $birthStaff        = $this->staff_model->getBirthDayStaff($date, 1, false, false);

        if (!empty($birthStudents)) {
            $array = array();
            foreach ($birthStudents as $student_key => $student_value) {

                $array[] = array('name' => $this->customlib->getFullName($student_value['firstname'], $student_value['middlename'], $student_value['lastname'], $this->sch_setting_detail->middlename, $this->sch_setting_detail->lastname),
                    'contact_no'            => $student_value['mobileno'],
                    'app_key'               => $student_value['app_key'],
                );
            }
            $birthDaysList['students'] = $array;
        }
        if (!empty($birthStaff)) {
            $array = array();
            foreach ($birthStaff as $staff_key => $staff_value) {

                $array[] = array('name' => $staff_value['name'], 'contact_no' => $staff_value['contact_no']);
            }
            $birthDaysList['staff'] = $array;
        }

        if (!empty($data["classlist"])) {
            foreach ($data["classlist"] as $ckey => $cvalue) {

                $carray[] = $cvalue["id"];
            }
        }
        
        $data['roles']         = $this->role_model->get();
        $data['birthDaysList'] = $birthDaysList;
        $data['sch_setting']   = $this->sch_setting_detail;
        $data['compose_notifications'] = $this->getComposeNotificationTemplates();
        $this->load->view('layout/header');
        $this->load->view('admin/mailsms/compose_sms', $data);
        $this->load->view('layout/footer');
    }

    public function compose_whatsapp()
    {
        if (!$this->rbac->hasPrivilege('whatsapp_messaging', 'can_view')) {
            access_denied();
        }

        $this->session->set_userdata('top_menu', 'Communicate');
        $this->session->set_userdata('sub_menu', 'mailsms/compose_whatsapp');

        $data['title'] = 'Send WhatsApp';
        $data['roles'] = $this->role_model->get();
        $data['sch_setting'] = $this->sch_setting_detail;
        $data['compose_notifications'] = $this->getComposeNotificationTemplates();
        $data['whatsapp_enabled'] = $this->whatsappgateway->isEnabled();

        $this->load->view('layout/header');
        $this->load->view('admin/mailsms/compose_whatsapp', $data);
        $this->load->view('layout/footer');
    }

    public function edit($id)
    {
        $data['title']       = 'Add Vehicle';
        $data['id']          = $id;
        $editvehicle         = $this->vehicle_model->get($id);
        $data['editvehicle'] = $editvehicle;
        $listVehicle         = $this->vehicle_model->get();
        $data['listVehicle'] = $listVehicle;
        $this->form_validation->set_rules('vehicle_no', $this->lang->line('vehicle_no'), 'trim|required|xss_clean');
        if ($this->form_validation->run() == false) {

            $this->load->view('layout/header');
            $this->load->view('admin/mailsms/edit', $data);
            $this->load->view('layout/footer');
        } else {
            $manufacture_year = $this->input->post('manufacture_year');
            $data             = array(
                'id'             => $this->input->post('id'),
                'vehicle_no'     => $this->input->post('vehicle_no'),
                'vehicle_model'  => $this->input->post('vehicle_model'),
                'driver_name'    => $this->input->post('driver_name'),
                'driver_licence' => $this->input->post('driver_licence'),
                'driver_contact' => $this->input->post('driver_contact'),
                'note'           => $this->input->post('note'),
            );
            ($manufacture_year != "") ? $data['manufacture_year'] = $manufacture_year : '';
            $this->vehicle_model->add($data);
            $this->session->set_flashdata('msg', '<div class="alert alert-success text-left">' . $this->lang->line('update_message') . '</div>');
            redirect('admin/mailsms/index');
        }
    }

    public function delete($id)
    {
        $data['title'] = 'Fees Master List';
        $this->vehicle_model->remove($id);
        redirect('admin/mailsms/index');
    }

    public function send_individual()
    {
        if (!$this->rbac->hasPrivilege('email', 'can_view')) {
            access_denied();
        }
        $this->requireStandardEmailPost();

        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('individual_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('individual_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('user_list', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('individual_send_by', $this->lang->line('send_through'), 'required|in_list[email]');
        if ($this->form_validation->run()) {

            $userlisting = json_decode($this->input->post('user_list'));
            if (is_object($userlisting)) {
                // The compose page keys selections by "category-id", so JSON
                // decoding produces an object rather than a numeric array.
                $userlisting = (array) $userlisting;
            }
            $user_array  = array();
            if (is_array($userlisting) || is_object($userlisting)) {
                foreach ((array) $userlisting as $userlisting_value) {
                    if (!is_array($userlisting_value) || empty($userlisting_value[0])) {
                        continue;
                    }
                    $submitted = $userlisting_value[0];
                    $resolved = $this->resolveInternalEmailRecipient(
                        isset($submitted->category) ? $submitted->category : '',
                        isset($submitted->record_id) ? $submitted->record_id : 0
                    );
                    if (!empty($resolved)) {
                        $user_array[] = $resolved;
                    }
                }
            }
            if (empty($user_array)) {
                echo json_encode(array(
                    'status' => 1,
                    'msg' => array('user_list' => '<li>Select a valid student, guardian, or staff recipient.</li>'),
                ));
                return;
            }

            $sms_mail = $this->input->post('individual_send_by');
            if ($sms_mail == "sms") {
                $send_sms  = 1;
                $send_mail = 0;
            } else {
                $send_sms  = 0;
                $send_mail = 1;
            }
            $message       = $this->input->post('individual_message');
            $message_title = $this->input->post('individual_title');
            $data          = array(
                'is_individual' => 1,
                'title'         => $message_title,
                'message'       => $message,
                'send_mail'     => $send_mail,
                'send_sms'      => $send_sms,
                'user_list'     => json_encode($user_array),
                'created_at'    => date('Y-m-d H:i:s'),
            );

            $this->messages_model->add($data);
            if (!empty($user_array)) {
                if ($send_mail) {
                    if (!empty($this->mail_config)) {
                        foreach ($user_array as $user_mail_key => $user_mail_value) {

                            if ($user_mail_value['email'] != "") {

                                $this->mailer->send_mail($user_mail_value['email'], $message_title, $message, $_FILES, $user_mail_value['guardianEmail']);
                            }
                        }
                    }
                }
                if ($send_sms) {
                    foreach ($user_array as $user_mail_key => $user_mail_value) {
                        if ($user_mail_value['mobileno'] != "") {

                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], "", ($message));
                        }
                    }
                }
            }
            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'individual_title'   => form_error('individual_title'),
                'individual_message' => form_error('individual_message'),
                'individual_send_by' => form_error('individual_send_by'),
                'user_list'          => form_error('user_list'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_birthday()
    {
        if (!$this->rbac->hasPrivilege('email', 'can_view')) {
            access_denied();
        }
        $this->requireStandardEmailPost();

        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('user[]', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('birthday_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('birthday_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('birthday_send_by', $this->lang->line('send_through'), 'required|in_list[email]');
        if ($this->form_validation->run()) {
            $user_array = array();

            $sms_mail = $this->input->post('birthday_send_by');
            if ($sms_mail == "sms") {
                $send_sms  = 1;
                $send_mail = 0;
            } else {
                $send_sms  = 0;
                $send_mail = 1;
            }
            $message       = $this->input->post('birthday_message');
            $message_title = $this->input->post('birthday_title');
            $data          = array(
                'is_group'   => 1,
                'title'      => $message_title,
                'message'    => $message,
                'send_mail'  => $send_mail,
                'send_sms'   => $send_sms,
                'group_list' => json_encode(array()),
            );

            $userlisting = $this->input->post('user[]');
            $allowed_birthday_emails = $this->getAllowedBirthdayEmailAddresses();

            foreach ((array) $userlisting as $users_key => $users_value) {
                $normalized_email = strtolower(trim((string) $users_value));
                if (!isset($allowed_birthday_emails[$normalized_email])) {
                    continue;
                }
                $array = array(
                    'email'    => $normalized_email,
                    'mobileno' => $normalized_email,
                );
                $user_array[] = $array;
            }

            if (empty($user_array)) {
                echo json_encode(array(
                    'status' => 1,
                    'msg' => array('user[]' => '<li>Select a valid birthday recipient.</li>'),
                ));
                return;
            }

            if (!empty($user_array)) {
                if ($send_mail) {
                    if (!empty($this->mail_config)) {
                        foreach ($user_array as $user_mail_key => $user_mail_value) {
                            if ($user_mail_value['email'] != "") {
                                $this->mailer->send_mail($user_mail_value['email'], $message_title, $message, $_FILES);
                            }
                        }
                    }
                }
                if ($send_sms) {
                    foreach ($user_array as $user_mail_key => $user_mail_value) {
                        if ($user_mail_value['mobileno'] != "") {
                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], "", ($message));
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'birthday_title'   => form_error('birthday_title'),
                'birthday_message' => form_error('birthday_message'),
                'birthday_send_by' => form_error('birthday_send_by'),
                'user[]'           => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_group()
    {
        if (!$this->rbac->hasPrivilege('email', 'can_view')) {
            access_denied();
        }
        $this->requireStandardEmailPost();

        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('group_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('group_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('user[]', $this->lang->line('message') . " " . $this->lang->line('to'), 'required');
        $this->form_validation->set_rules('group_send_by', $this->lang->line('send_through'), 'required|in_list[email]');
        if ($this->form_validation->run()) {
            $user_array = array();

            $sms_mail = $this->input->post('group_send_by');
            if ($sms_mail == "sms") {
                $send_sms  = 1;
                $send_mail = 0;
            } else {
                $send_sms  = 0;
                $send_mail = 1;
            }
            $message       = $this->input->post('group_message');
            $message_title = $this->input->post('group_title');
            $current_session_name = $this->setting_model->getCurrentSessionName();
            $data          = array(
                'is_group'   => 1,
                'title'      => $message_title,
                'message'    => $message,
                'send_mail'  => $send_mail,
                'send_sms'   => $send_sms,
                'group_list' => json_encode(array()),
                'created_at' => date('Y-m-d H:i:s'),
            );
            $this->messages_model->add($data);

            $userlisting = $this->input->post('user[]');
            foreach ($userlisting as $users_key => $users_value) {
                if ($users_value == "student") {
                    $student_array = $this->student_model->get();
                    if (!empty($student_array)) {
                        foreach ($student_array as $student_key => $student_value) {
                            $student_name = $this->customlib->getFullName(
                                $student_value['firstname'],
                                $student_value['middlename'],
                                $student_value['lastname'],
                                $this->sch_setting_detail->middlename,
                                $this->sch_setting_detail->lastname
                            );

                            $array = array_merge($student_value, array(
                                'user_id'  => $student_value['id'],
                                'email'    => $student_value['email'],
                                'mobileno' => $student_value['mobileno'],
                                'display_name' => $student_name,
                                'student_name' => $student_name,
                                'name' => $student_name,
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                } else if ($users_value == "parent") {
                    $parent_array = $this->student_model->get();
                    if (!empty($parent_array)) {
                        foreach ($parent_array as $parent_key => $parent_value) {
                            $parent_login = $this->user_model->getParentLoginDetails($parent_value['id']);
                            $student_name = $this->customlib->getFullName(
                                $parent_value['firstname'],
                                $parent_value['middlename'],
                                $parent_value['lastname'],
                                $this->sch_setting_detail->middlename,
                                $this->sch_setting_detail->lastname
                            );

                            $array = array_merge($parent_value, array(
                                'user_id'  => $parent_value['id'],
                                'email'    => $parent_value['guardian_email'],
                                'mobileno' => $parent_value['guardian_phone'],
                                'display_name' => $parent_value['guardian_name'],
                                'name' => $parent_value['guardian_name'],
                                'student_name' => $student_name,
                                'username' => isset($parent_login['username']) ? $parent_login['username'] : '',
                                'password' => isset($parent_login['password']) ? $parent_login['password'] : '',
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                } else if (is_numeric($users_value)) {

                    $staff = $this->staff_model->getEmployeeByRoleID($users_value);
                    if (!empty($staff)) {
                        foreach ($staff as $staff_key => $staff_value) {
                            $staff_name = trim($staff_value['name'] . ' ' . $staff_value['surname']);
                            $array = array_merge($staff_value, array(
                                'user_id'  => $staff_value['id'],
                                'email'    => $staff_value['email'],
                                'mobileno' => $staff_value['contact_no'],
                                'display_name' => $staff_name,
                                'name' => $staff_name,
                                'username' => !empty($staff_value['employee_id']) ? $staff_value['employee_id'] : $staff_value['email'],
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                }
            }

            if (!empty($user_array)) {
                if ($send_mail) {
                    if (!empty($this->mail_config)) {
                        foreach ($user_array as $user_mail_key => $user_mail_value) {
                            if ($user_mail_value['email'] != "") {
                                $personal_title   = $this->renderComposeTemplate($message_title, $user_mail_value);
                                $personal_message = $this->renderComposeTemplate($message, $user_mail_value);
                                $this->mailer->send_mail($user_mail_value['email'], $personal_title, $personal_message, $_FILES);
                            }
                        }
                    }
                }
                if ($send_sms) {
                    foreach ($user_array as $user_mail_key => $user_mail_value) {
                        if ($user_mail_value['mobileno'] != "") {
                            $personal_message = $this->renderComposeTemplate($message, $user_mail_value);
                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], "", ($personal_message));
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'group_title'   => form_error('group_title'),
                'group_message' => form_error('group_message'),
                'group_send_by' => form_error('group_send_by'),
                'user[]'        => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    private function renderComposeTemplate($template, $values)
    {
        if ($template === "" || empty($values) || !is_array($values)) {
            return $template;
        }

        $values = $this->suppressStoredPasswordHashes($values);
        $replace = array();
        foreach ($values as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replace['{{' . $key . '}}'] = (string) $value;
            }
        }

        return strtr($template, $replace);
    }

    private function suppressStoredPasswordHashes($values)
    {
        foreach ($values as $key => $value) {
            if (stripos((string) $key, 'password') !== false && $this->looksLikeStoredPasswordHash($value)) {
                $values[$key] = '';
            }
        }

        return $values;
    }

    private function looksLikeStoredPasswordHash($value)
    {
        if (!is_scalar($value) || $value === '') {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if (function_exists('password_get_info')) {
            $info = password_get_info($value);
            if (!empty($info['algo']) || (!empty($info['algoName']) && $info['algoName'] !== 'unknown')) {
                return true;
            }
        }

        return preg_match('/^\$(2y|2a|2b|argon2i|argon2id)\$/', $value) === 1;
    }

    private function getGroupComposeRecipients($userlisting)
    {
        $user_array = array();
        $current_session_name = $this->setting_model->getCurrentSessionName();

        if (empty($userlisting)) {
            return $user_array;
        }

        foreach ($userlisting as $users_key => $users_value) {
            if ($users_value == "student") {
                $student_array = $this->student_model->get();
                if (!empty($student_array)) {
                    foreach ($student_array as $student_key => $student_value) {
                        $student_name = $this->customlib->getFullName(
                            $student_value['firstname'],
                            $student_value['middlename'],
                            $student_value['lastname'],
                            $this->sch_setting_detail->middlename,
                            $this->sch_setting_detail->lastname
                        );

                        $user_array[] = array_merge($student_value, array(
                            'user_id' => $student_value['id'],
                            'email' => $student_value['email'],
                            'mobileno' => $student_value['mobileno'],
                            'app_key' => isset($student_value['app_key']) ? $student_value['app_key'] : '',
                            'display_name' => $student_name,
                            'student_name' => $student_name,
                            'name' => $student_name,
                            'url' => site_url('site/userlogin'),
                            'school_name' => $this->sch_setting_detail->name,
                            'current_session_name' => $current_session_name,
                        ));
                    }
                }
            } else if ($users_value == "parent") {
                $parent_array = $this->student_model->get();
                if (!empty($parent_array)) {
                    foreach ($parent_array as $parent_key => $parent_value) {
                        $parent_login = $this->user_model->getParentLoginDetails($parent_value['id']);
                        $student_name = $this->customlib->getFullName(
                            $parent_value['firstname'],
                            $parent_value['middlename'],
                            $parent_value['lastname'],
                            $this->sch_setting_detail->middlename,
                            $this->sch_setting_detail->lastname
                        );

                        $user_array[] = array_merge($parent_value, array(
                            'user_id' => $parent_value['id'],
                            'email' => $parent_value['guardian_email'],
                            'mobileno' => $parent_value['guardian_phone'],
                            'app_key' => isset($parent_value['parent_app_key']) ? $parent_value['parent_app_key'] : '',
                            'display_name' => $parent_value['guardian_name'],
                            'name' => $parent_value['guardian_name'],
                            'student_name' => $student_name,
                            'username' => isset($parent_login['username']) ? $parent_login['username'] : '',
                            'password' => isset($parent_login['password']) ? $parent_login['password'] : '',
                            'url' => site_url('site/userlogin'),
                            'school_name' => $this->sch_setting_detail->name,
                            'current_session_name' => $current_session_name,
                        ));
                    }
                }
            } else if (is_numeric($users_value)) {
                $staff = $this->staff_model->getEmployeeByRoleID($users_value);
                if (!empty($staff)) {
                    foreach ($staff as $staff_key => $staff_value) {
                        $staff_name = trim($staff_value['name'] . ' ' . $staff_value['surname']);
                        $user_array[] = array_merge($staff_value, array(
                            'user_id' => $staff_value['id'],
                            'email' => $staff_value['email'],
                            'mobileno' => $staff_value['contact_no'],
                            'app_key' => isset($staff_value['app_key']) ? $staff_value['app_key'] : '',
                            'display_name' => $staff_name,
                            'name' => $staff_name,
                            'username' => !empty($staff_value['employee_id']) ? $staff_value['employee_id'] : $staff_value['email'],
                            'url' => site_url('site/userlogin'),
                            'school_name' => $this->sch_setting_detail->name,
                            'current_session_name' => $current_session_name,
                        ));
                    }
                }
            }
        }

        return $user_array;
    }

    public function send_group_whatsapp()
    {
        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('group_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('group_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('user[]', $this->lang->line('message') . " " . $this->lang->line('to'), 'required');

        if (!$this->rbac->hasPrivilege('whatsapp_messaging', 'can_view')) {
            access_denied();
        }

        if (!$this->form_validation->run()) {
            $data = array(
                'group_title' => form_error('group_title'),
                'group_message' => form_error('group_message'),
                'user[]' => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
            return;
        }

        if (!$this->whatsappgateway->isEnabled()) {
            echo json_encode(array('status' => 1, 'msg' => array('whatsapp' => '<li>WhatsApp sending is disabled. Enable it from System Settings > WhatsApp Settings.</li>')));
            return;
        }

        $message = $this->input->post('group_message');
        $message_title = $this->input->post('group_title');
        $user_array = $this->getGroupComposeRecipients($this->input->post('user[]'));

        $data = array(
            'is_group' => 1,
            'title' => $message_title,
            'message' => $message,
            'send_mail' => 0,
            'send_sms' => 0,
            'group_list' => json_encode(array()),
            'created_at' => date('Y-m-d H:i:s'),
        );
        $this->messages_model->add($data);

        $sent = 0;
        $failed = 0;

        if (!empty($user_array)) {
            foreach ($user_array as $user_mail_key => $user_mail_value) {
                if (!empty($user_mail_value['mobileno'])) {
                    $personal_title = $this->renderComposeTemplate($message_title, $user_mail_value);
                    $personal_message = $this->renderComposeTemplate($message, $user_mail_value);

                    if ($this->whatsappgateway->sendMessage($user_mail_value['mobileno'], $personal_message, $personal_title)) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        if ($sent === 0) {
            echo json_encode(array('status' => 1, 'msg' => array('whatsapp' => '<li>No WhatsApp message was sent. Check recipient phone numbers and WhatsApp provider settings.</li>')));
            return;
        }

        $message = 'WhatsApp message sent successfully. Sent: ' . $sent;
        if ($failed > 0) {
            $message .= ', Failed: ' . $failed;
        }

        echo json_encode(array('status' => 0, 'msg' => $message));
    }

    public function send_group_sms()
    {

        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('group_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('group_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('user[]', $this->lang->line('message') . " " . $this->lang->line('to'), 'required');
        $this->form_validation->set_rules('group_send_by[]', $this->lang->line('send_through'), 'required');
        $template_id = $this->input->post('group_template_id');
        if ($this->form_validation->run()) {
            $user_array = array();

            $sms_mail = $this->input->post('group_send_by');

            $message       = $this->input->post('group_message');
            $message_title = $this->input->post('group_title');
            $current_session_name = $this->setting_model->getCurrentSessionName();
            $data          = array(
                'is_group'    => 1,
                'title'       => $message_title,
                'message'     => $message,
                'send_mail'   => 0,
                'send_sms'    => 1,
                'group_list'  => json_encode(array()),
                'created_at'  => date('Y-m-d H:i:s'),
                'template_id' => $template_id,
            );
            $this->messages_model->add($data);

            $userlisting = $this->input->post('user[]');
            foreach ($userlisting as $users_key => $users_value) {
                if ($users_value == "student") {
                    $student_array = $this->student_model->get();

                    if (!empty($student_array)) {
                        foreach ($student_array as $student_key => $student_value) {
                            $student_name = $this->customlib->getFullName(
                                $student_value['firstname'],
                                $student_value['middlename'],
                                $student_value['lastname'],
                                $this->sch_setting_detail->middlename,
                                $this->sch_setting_detail->lastname
                            );

                            $array = array_merge($student_value, array(
                                'user_id'  => $student_value['id'],
                                'email'    => $student_value['email'],
                                'mobileno' => $student_value['mobileno'],
                                'app_key'  => $student_value['app_key'],
                                'display_name' => $student_name,
                                'student_name' => $student_name,
                                'name' => $student_name,
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                } else if ($users_value == "parent") {
                    $parent_array = $this->student_model->get();
                    if (!empty($parent_array)) {
                        foreach ($parent_array as $parent_key => $parent_value) {
                            $parent_login = $this->user_model->getParentLoginDetails($parent_value['id']);
                            $student_name = $this->customlib->getFullName(
                                $parent_value['firstname'],
                                $parent_value['middlename'],
                                $parent_value['lastname'],
                                $this->sch_setting_detail->middlename,
                                $this->sch_setting_detail->lastname
                            );

                            $array = array_merge($parent_value, array(
                                'user_id'  => $parent_value['id'],
                                'email'    => $parent_value['guardian_email'],
                                'mobileno' => $parent_value['guardian_phone'],
                                'app_key'  => $parent_value['parent_app_key'],
                                'display_name' => $parent_value['guardian_name'],
                                'name' => $parent_value['guardian_name'],
                                'student_name' => $student_name,
                                'username' => isset($parent_login['username']) ? $parent_login['username'] : '',
                                'password' => isset($parent_login['password']) ? $parent_login['password'] : '',
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                } else if (is_numeric($users_value)) {

                    $staff = $this->staff_model->getEmployeeByRoleID($users_value);
                    if (!empty($staff)) {
                        foreach ($staff as $staff_key => $staff_value) {
                            $staff_name = trim($staff_value['name'] . ' ' . $staff_value['surname']);
                            $array = array_merge($staff_value, array(
                                'user_id'  => $staff_value['id'],
                                'email'    => $staff_value['email'],
                                'mobileno' => $staff_value['contact_no'],
                                'app_key'  => isset($staff_value['app_key']) ? $staff_value['app_key'] : '',
                                'display_name' => $staff_name,
                                'name' => $staff_name,
                                'username' => !empty($staff_value['employee_id']) ? $staff_value['employee_id'] : $staff_value['email'],
                                'url' => site_url('site/userlogin'),
                                'school_name' => $this->sch_setting_detail->name,
                                'current_session_name' => $current_session_name,
                            ));
                            $user_array[] = $array;
                        }
                    }
                }
            }

            if (!empty($user_array)) {

                foreach ($user_array as $user_mail_key => $user_mail_value) {
                    $personal_title   = $this->renderComposeTemplate($message_title, $user_mail_value);
                    $personal_message = $this->renderComposeTemplate($message, $user_mail_value);
                    if (in_array("sms", $sms_mail)) {
                        if ($user_mail_value['mobileno'] != "") {
                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], $personal_message, $template_id, "");
                        }
                    }
                    if (in_array("push", $sms_mail)) {
                        $push_array = array(
                            'title' => $personal_title,
                            'body'  => $personal_message,
                        );
                        if (!empty($user_mail_value['app_key'])) {
                            $this->pushnotification->send($user_mail_value['app_key'], $push_array, "mail_sms");
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'group_title'     => form_error('group_title'),
                'group_send_by[]' => form_error('group_send_by[]'),
                'group_message'   => form_error('group_message'),
                'user[]'          => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_birthday_sms()
    {
     
        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('user[]', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('birthday_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('birthday_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('birthday_send_by[]', $this->lang->line('send_through'), 'required');
        $template_id = $this->input->post('birthday_template_id');

        if ($this->form_validation->run()) {
            $user_array      = array();
            $user_push_array = array();

            $sms_mail = $this->input->post('birthday_send_by');

            $message       = $this->input->post('birthday_message');
            $message_title = $this->input->post('birthday_title');
            $data          = array(
                'is_group'   => 1,
                'title'      => $message_title,
                'message'    => $message,
                'send_mail'  => 0,
                'send_sms'   => 1,
                'group_list' => json_encode(array()),
            );
           
            $userlisting     = $this->input->post('user[]');

            $userpushlisting = $this->input->post('app-key');

            foreach ($userlisting as $users_key => $users_value) {
                $array = array(
                    
                    'mobileno' => $users_value,
                );
                $user_array[] = $array;
            }
            foreach ($userpushlisting as $user_push_key => $user_push_value) {
                $array = array(
                    'app-key' => $user_push_value,
                );
                $user_push_array[] = $array;
            }
      
            if (!empty($user_array)) {

                foreach ($user_array as $user_mail_key => $user_mail_value) {
                    if (in_array("sms", $sms_mail)) {
                        if ($user_mail_value['mobileno'] != "" && $user_mail_value['mobileno'] != 0) {
                            $this->smsgateway->sendSMS($user_mail_value['mobileno'],($message), $template_id, "");
                        }
                    }
                }
            }

            if (!empty($user_push_array)) {

                foreach ($user_push_array as $user_push_sms_key => $user_push_sms_value) {
                    if (in_array("push", $sms_mail)) {
                        $push_array = array(
                            'title' => $message_title,
                            'body'  => $message,
                        );
                        if ($user_push_sms_value['app-key'] != "") {
                            $this->pushnotification->send($user_push_sms_value['app-key'], $push_array, "mail_sms");
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'birthday_title'     => form_error('birthday_title'),
                'birthday_send_by[]' => form_error('birthday_send_by[]'),
                'birthday_message'   => form_error('birthday_message'),
                'user[]'             => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_individual_sms()
    {

        $this->form_validation->set_error_delimiters('<li>', '</li>');
        $this->form_validation->set_rules('individual_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('individual_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('user_list', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('individual_send_by[]', $this->lang->line('send_through'), 'required');
        $template_id = $this->input->post('individual_template_id');

        if ($this->form_validation->run()) {

            $userlisting = json_decode($this->input->post('user_list'));
            $user_array  = array();
            foreach ($userlisting as $userlisting_key => $userlisting_value) {
                $array = array(
                    'category'      => $userlisting_value[0]->category,
                    'user_id'       => $userlisting_value[0]->record_id,
                    'email'         => $userlisting_value[0]->email,
                    'guardianEmail' => $userlisting_value[0]->guardianEmail,
                    'mobileno'      => $userlisting_value[0]->mobileno,
                    'app_key'       => $userlisting_value[0]->app_key,
                );
                $user_array[] = $array;
            }

            $sms_mail = $this->input->post('individual_send_by');

            $message       = $this->input->post('individual_message');
            $message_title = $this->input->post('individual_title');
            $data          = array(
                'is_individual' => 1,
                'title'         => $message_title,
                'message'       => $message,
                'send_mail'     => 0,
                'send_sms'      => 1,
                'user_list'     => json_encode($user_array),
                'created_at'    => date('Y-m-d H:i:s'),
            );

            $this->messages_model->add($data);
            if (!empty($user_array)) {

                foreach ($user_array as $user_mail_key => $user_mail_value) {
                    if (in_array("sms", $sms_mail)) {

                        if ($user_mail_value['mobileno'] != "") {

                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], $message,$template_id,"");
                        }
                    }
                    if (in_array("push", $sms_mail)) {
                        $push_array = array(
                            'title' => $message_title,
                            'body'  => $message,
                        );
                        if ($user_mail_value['app_key'] != "") {
                            $this->pushnotification->send($user_mail_value['app_key'], $push_array, "mail_sms");
                        }
                    }
                }
            }
            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'individual_title'     => form_error('individual_title'),
                'individual_send_by[]' => form_error('individual_send_by[]'),
                'individual_message'   => form_error('individual_message'),
                'user_list'            => form_error('user_list'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_class_sms()
    {

        $this->form_validation->set_error_delimiters('<li>', '</li>');

        $this->form_validation->set_rules('class_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('class_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'required');
        $this->form_validation->set_rules('user[]', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('class_send_by[]', $this->lang->line('send_through'), 'required');
        $template_id = $this->input->post('class_template_id');
        if ($this->form_validation->run()) {

            $sms_mail = $this->input->post('class_send_by');

            $message       = $this->input->post('class_message');
            $message_title = $this->input->post('class_title');
            $section       = $this->input->post('user[]');
            $class_id      = $this->input->post('class_id');

            $user_array = array();
            foreach ($section as $section_key => $section_value) {
                $userlisting = $this->student_model->searchByClassSection($class_id, $section_value);
                if (!empty($userlisting)) {
                    foreach ($userlisting as $userlisting_key => $userlisting_value) {
                        $array = array(
                            'user_id'  => $userlisting_value['id'],
                            'email'    => $userlisting_value['email'],
                            'mobileno' => $userlisting_value['mobileno'],
                            'app_key'  => $userlisting_value['app_key'],
                        );
                        $user_array[] = $array;
                    }
                }
            }

            $data = array(
                'is_class'   => 1,
                'title'      => $message_title,
                'message'    => $message,
                'send_mail'  => 0,
                'send_sms'   => 1,
                'user_list'  => json_encode($user_array),
                'created_at' => date('Y-m-d H:i:s'),
            );
            $this->messages_model->add($data);
            if (!empty($user_array)) {

                foreach ($user_array as $user_mail_key => $user_mail_value) {
                    if (in_array("sms", $sms_mail)) {
                        if ($user_mail_value['mobileno'] != "") {

                            $this->smsgateway->sendSMS($user_mail_value['mobileno'],$message,$template_id,"");
                        }
                    }
                    if (in_array("push", $sms_mail)) {
                        $push_array = array(
                            'title' => $message_title,
                            'body'  => $message,
                        );
                        if ($user_mail_value['app_key'] != "") {
                            $this->pushnotification->send($user_mail_value['app_key'], $push_array, "mail_sms");
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'class_title'     => form_error('class_title'),
                'class_send_by[]' => form_error('class_send_by[]'),
                'class_message'   => form_error('class_message'),
                'class_id'        => form_error('class_id'),
                'user[]'          => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function send_class()
    {
        if (!$this->rbac->hasPrivilege('email', 'can_view')) {
            access_denied();
        }
        $this->requireStandardEmailPost();

        $this->form_validation->set_error_delimiters('<li>', '</li>');

        $this->form_validation->set_rules('class_title', $this->lang->line('title'), 'required');
        $this->form_validation->set_rules('class_message', $this->lang->line('message'), 'required');
        $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'required');
        $this->form_validation->set_rules('user[]', $this->lang->line('recipient'), 'required');
        $this->form_validation->set_rules('class_send_by', $this->lang->line('send_through'), 'required|in_list[email]');
        if ($this->form_validation->run()) {

            $sms_mail = $this->input->post('class_send_by');
            if ($sms_mail == "sms") {
                $send_sms  = 1;
                $send_mail = 0;
            } else {
                $send_sms  = 0;
                $send_mail = 1;
            }
            $message       = $this->input->post('class_message');
            $message_title = $this->input->post('class_title');
            $section       = $this->input->post('user[]');
            $class_id      = $this->input->post('class_id');

            $user_array = array();
            foreach ($section as $section_key => $section_value) {
                $userlisting = $this->student_model->searchByClassSection($class_id, $section_value);
                if (!empty($userlisting)) {
                    foreach ($userlisting as $userlisting_key => $userlisting_value) {
                        $array = array(
                            'user_id'  => $userlisting_value['id'],
                            'email'    => $userlisting_value['email'],
                            'mobileno' => $userlisting_value['mobileno'],
                        );
                        $user_array[] = $array;
                    }
                }
            }

            $data = array(
                'is_class'   => 1,
                'title'      => $message_title,
                'message'    => $message,
                'send_mail'  => $send_mail,
                'send_sms'   => $send_sms,
                'user_list'  => json_encode($user_array),
                'created_at' => date('Y-m-d H:i:s'),
            );
            $this->messages_model->add($data);
            if (!empty($user_array)) {
                if ($send_mail) {
                    if (!empty($this->mail_config)) {
                        foreach ($user_array as $user_mail_key => $user_mail_value) {
                            if ($user_mail_value['email'] != "") {
                                $this->mailer->send_mail($user_mail_value['email'], $message_title, $message, $_FILES);
                            }
                        }
                    }
                }
                if ($send_sms) {
                    foreach ($user_array as $user_mail_key => $user_mail_value) {
                        if ($user_mail_value['mobileno'] != "") {

                            $this->smsgateway->sendSMS($user_mail_value['mobileno'], "", ($message));
                        }
                    }
                }
            }

            echo json_encode(array('status' => 0, 'msg' => $this->lang->line('message_sent_successfully')));
        } else {

            $data = array(
                'class_title'   => form_error('class_title'),
                'class_message' => form_error('class_message'),
                'class_id'      => form_error('class_id'),
                'class_send_by' => form_error('class_send_by'),
                'user[]'        => form_error('user[]'),
            );

            echo json_encode(array('status' => 1, 'msg' => $data));
        }
    }

    public function test_sms()
    {
        if (!$this->rbac->hasPrivilege('sms_setting', 'can_view') && !$this->rbac->hasPrivilege('sms', 'can_view')) {
            access_denied();
        }

        $this->form_validation->set_rules('mobile', $this->lang->line('mobile_number'), 'required');

        if ($this->form_validation->run() == false) {
            $msg = array(
                'mobile' => form_error('mobile'),
            );
            $array = array('status' => 'fail', 'error' => $msg, 'message' => '');
        } else {
            $message = 'Smart School SMS Test Successful.';
            $status  = $this->smsgateway->sendSMS($this->input->post('mobile'), $message);

            if ($status) {
                $success_message = 'Test SMS sent successfully. Please check the recipient phone.';
                $sms_detail      = $this->smsconfig_model->getActiveSMS();

                if (!empty($sms_detail) && $sms_detail->type === 'africastalking' && strtolower(trim((string) $sms_detail->url)) === 'sandbox') {
                    $success_message = 'Test SMS accepted by Africa\'s Talking sandbox. Check the Africa\'s Talking simulator or sandbox dashboard, not the recipient phone.';
                }

                $array = array('status' => 'success', 'error' => '', 'message' => $success_message);
            } else {
                $error_message = 'Unable to send test SMS. Please review your SMS gateway settings and try again.';
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    $gateway_error = $this->smsgateway->getLastError();
                    if (!empty($gateway_error)) {
                        $error_message .= ' ' . $gateway_error;
                    }
                }

                $array = array('status' => 'fail', 'error' => array('mobile' => $error_message), 'message' => '');
            }
        }
        echo json_encode($array);
    }

    /**
     * Rebuild legacy individual recipients from database records so a changed
     * browser payload cannot turn the internal-email form into an untracked
     * external mail relay.
     */
    private function resolveInternalEmailRecipient($category, $recordId)
    {
        $category = strtolower(trim((string) $category));
        $recordId = (int) $recordId;
        if ($recordId <= 0) {
            return array();
        }

        if (in_array($category, array('student', 'parent', 'student_guardian'), true)) {
            $student = $this->student_model->get($recordId);
            if (empty($student) || !in_array(strtolower((string) $student['is_active']), array('yes', '1'), true)) {
                return array();
            }

            $studentEmail = strtolower(trim((string) $student['email']));
            $guardianEmail = strtolower(trim((string) $student['guardian_email']));
            if ($category === 'parent') {
                $email = $guardianEmail;
                $guardianCc = '';
            } else {
                $email = $studentEmail;
                $guardianCc = $category === 'student_guardian' ? $guardianEmail : '';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return array();
            }

            return array(
                'category' => $category,
                'user_id' => $recordId,
                'email' => $email,
                'guardianEmail' => filter_var($guardianCc, FILTER_VALIDATE_EMAIL) ? $guardianCc : '',
                'mobileno' => isset($student['mobileno']) ? $student['mobileno'] : '',
            );
        }

        if ($category === 'staff') {
            $staff = $this->staff_model->getAll($recordId);
            $email = !empty($staff['email']) ? strtolower(trim((string) $staff['email'])) : '';
            if (empty($staff) || (int) $staff['is_active'] !== 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return array();
            }

            return array(
                'category' => 'staff',
                'user_id' => $recordId,
                'email' => $email,
                'guardianEmail' => '',
                'mobileno' => isset($staff['contact_no']) ? $staff['contact_no'] : '',
            );
        }

        return array();
    }

    private function getAllowedBirthdayEmailAddresses()
    {
        $allowed = array();
        $date = date('Y-m-d');
        $students = $this->student_model->getBirthDayStudents($date, true);
        $staff = $this->staff_model->getBirthDayStaff($date, 1, true);

        foreach (array_merge((array) $students, (array) $staff) as $person) {
            $email = !empty($person['email']) ? strtolower(trim((string) $person['email'])) : '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $allowed[$email] = true;
            }
        }

        return $allowed;
    }

    private function requireExternalEmailPost()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Method not allowed.', 405);
        }

        $expected = (string) $this->session->userdata($this->externalEmailTokenSessionKey);
        $provided = (string) $this->input->post('external_email_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The email form expired. Refresh the page and try again.', 403);
        }

        // Make the token single-use so a browser retry cannot resend an email.
        $this->session->set_userdata($this->externalEmailTokenSessionKey, $this->newExternalEmailToken());
    }

    private function requireStandardEmailPost()
    {
        if ($this->input->method(true) !== 'POST') {
            show_error('Method not allowed.', 405);
        }

        $expected = (string) $this->session->userdata($this->standardEmailTokenSessionKey);
        $provided = (string) $this->input->post('standard_email_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The email form expired. Refresh the page and try again.', 403);
        }
    }

    private function externalEmailRedirect($type, $message)
    {
        $type = in_array($type, array('success', 'warning', 'danger', 'info'), true) ? $type : 'info';
        if ($this->input->method(true) === 'POST') {
            $draftBody = (string) $this->input->post('external_message', false);
            $this->session->set_flashdata('external_email_draft', array(
                'email' => trim((string) $this->input->post('external_email', true)),
                'name' => trim((string) $this->input->post('external_name', true)),
                'subject' => trim((string) $this->input->post('external_subject', true)),
                // Avoid placing an exceptionally large rejected payload in
                // the session while preserving normal drafts after errors.
                'message' => strlen($draftBody) <= 100000
                    ? (string) $this->security->xss_clean($draftBody)
                    : '',
            ));
        }
        $this->session->set_flashdata(
            'msg',
            '<div class="alert alert-' . $type . '">' . html_escape((string) $message) . '</div>'
        );

        return redirect('admin/mailsms/compose?tab=external');
    }

    private function newExternalEmailToken()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(32));
            } catch (Exception $exception) {
                // Fall through to the legacy-compatible entropy source.
            }
        }

        return hash('sha256', uniqid((string) mt_rand(), true));
    }

}
