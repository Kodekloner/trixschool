<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Onlineexam extends Student_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->sch_setting_detail = $this->setting_model->getSetting();
        $this->config->load("mailsms");
        $this->load->model('onlineexamattempt_model');

        if (!$this->session->userdata('onlineexam_v2_token')) {
            $this->session->set_userdata('onlineexam_v2_token', bin2hex(random_bytes(32)));
        }
    }

    public function index()
    {
        $data = array();
        $this->session->set_userdata('top_menu', 'Onlineexam');

        $student_current_class = $this->customlib->getStudentCurrentClsSection();
        $student_session_id    = $student_current_class->student_session_id;

        $onlineexam         = $this->onlineexam_model->getStudentexam($student_session_id);
        $data['onlineexam'] = $onlineexam;
        $this->load->view('layout/student/header');
        $this->load->view('user/onlineexam/onlineexamlist', $data);
        $this->load->view('layout/student/footer');
    }

    public function view($id)
    {
        $data = array();
        $this->session->set_userdata('top_menu', 'Onlineexam');
        $data['sch_setting']         = $this->sch_setting_detail;
        $role                        = $this->customlib->getUserRole();
        $data['role']                = $role;
        $student_current_class       = $this->customlib->getStudentCurrentClsSection();
        $student_session_id          = $student_current_class->student_session_id;
        $online_exam_validate        = $this->onlineexam_model->examstudentsID($student_session_id, $id);
        $student                     = $this->student_model->getByStudentSession($student_session_id);
        $data['question_true_false'] = $this->config->item('question_true_false');
        $exam                        = $this->onlineexam_model->get($id);
        if ($exam && isset($exam->workflow_version) && (int) $exam->workflow_version >= 2) {
            return $this->viewLocalizedExam($exam, $online_exam_validate, $student_session_id);
        }
        $data['exam']                = $exam;
        $data['student']             = $student;
        $questionOpt                 = $this->customlib->getQuesOption();
        $data['questionOpt']         = $questionOpt;
        if (!empty($online_exam_validate)) {
            $data['question_result'] = $this->onlineexamresult_model->getResultByStudent($online_exam_validate->id, $online_exam_validate->onlineexam_id);
            $data['result_prepare']  = $this->onlineexamresult_model->checkResultPrepare($online_exam_validate->id);
        }
        $data['online_exam_validate'] = $online_exam_validate;
        $filetype                     = $this->filetype_model->get();

        $data['allowed_extension']   = array_map('trim', array_map('strtolower', explode(',', $filetype->image_extension)));
        $data['allowed_mime_type']   = array_map('trim', array_map('strtolower', explode(',', $filetype->image_mime)));
        $data['allowed_upload_size'] = $filetype->image_size;

        $this->load->view('layout/student/header');
        $this->load->view('user/onlineexam/view', $data);
        $this->load->view('layout/student/footer');
    }

    function print() {
        $data                        = array();
        $data['sch_setting']         = $this->sch_setting_detail;
        $exam_id                     = $this->input->post('exam_id');
        $role                        = $this->customlib->getUserRole();
        $data['role']                = $role;
        $student_current_class       = $this->customlib->getStudentCurrentClsSection();
        $student_session_id          = $student_current_class->student_session_id;
        $online_exam_validate        = $this->onlineexam_model->examstudentsID($student_session_id, $exam_id);
        $data['question_true_false'] = $this->config->item('question_true_false');
        $exam                        = $this->onlineexam_model->get($exam_id);
        if ($exam && isset($exam->workflow_version) && (int) $exam->workflow_version >= 2) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Localized assessments use their frozen paper workflow.'), 409);
        }
        $data['exam']                = $exam;
        $questionOpt                 = $this->customlib->getQuesOption();
        $data['questionOpt']         = $questionOpt;
        $student                     = $this->student_model->getByStudentSession($student_session_id);
        $data['student']             = $student;

        if (!empty($online_exam_validate)) {

            $data['question_result'] = $this->onlineexamresult_model->getResultByStudent($online_exam_validate->id, $online_exam_validate->onlineexam_id);
            $data['result_prepare']  = $this->onlineexamresult_model->checkResultPrepare($online_exam_validate->id);

        }
        $data['online_exam_validate'] = $online_exam_validate;
        $data['page']                 = $this->load->view('user/onlineexam/_print', $data, true);
        echo json_encode(array('status' => 1, 'page' => $data['page']));
    }

    public function save()
    {

        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $legacy_assignment_id = (int) $this->input->post('onlineexam_student_id');
            $legacy_assignment = $this->db->select('onlineexam.workflow_version')
                ->from('onlineexam_students')
                ->join('onlineexam', 'onlineexam.id = onlineexam_students.onlineexam_id')
                ->where('onlineexam_students.id', $legacy_assignment_id)
                ->limit(1)
                ->get()
                ->row();
            if ($legacy_assignment && (int) $legacy_assignment->workflow_version >= 2) {
                show_error('Localized assessments can only be submitted through the official paper workflow.', 409);
            }
            $total_rows = $this->input->post('total_rows');

            if (!empty($total_rows)) {
                $save_result = array();
                foreach ($total_rows as $row_key => $row_value) {
                    if (($_POST['question_type_' . $row_value]) == "singlechoice") {

                        if (isset($_POST['radio' . $row_value])) {
                            $save_result[] = array(
                                'onlineexam_student_id'  => $this->input->post('onlineexam_student_id'),
                                'onlineexam_question_id' => $this->input->post('question_id_' . $row_value),
                                'select_option'          => $_POST['radio' . $row_value],
                                'attachment_name'        => "",
                                'attachment_upload_name' => "",
                            );
                        }
                    } elseif (($_POST['question_type_' . $row_value]) == "true_false") {
                        # code...
                        if (isset($_POST['radio' . $row_value])) {
                            $save_result[] = array(
                                'onlineexam_student_id'  => $this->input->post('onlineexam_student_id'),
                                'onlineexam_question_id' => $this->input->post('question_id_' . $row_value),
                                'select_option'          => $_POST['radio' . $row_value],
                                'attachment_name'        => "",
                                'attachment_upload_name' => "",
                            );
                        }
                    } elseif (($_POST['question_type_' . $row_value]) == "multichoice") {
                        # code...
                        if (isset($_POST['checkbox' . $row_value])) {
                            $save_result[] = array(
                                'onlineexam_student_id'  => $this->input->post('onlineexam_student_id'),
                                'onlineexam_question_id' => $this->input->post('question_id_' . $row_value),
                                'select_option'          => json_encode($_POST['checkbox' . $row_value]),
                                'attachment_name'        => "",
                                'attachment_upload_name' => "",
                            );
                        }
                    } elseif (($_POST['question_type_' . $row_value]) == "descriptive") {
                        # code...
                        if (isset($_POST['answer' . $row_value]) || (isset($_FILES["attachment" . $row_value]) && !empty($_FILES["attachment" . $row_value]['name']))) {
                            $inst_array = array(
                                'onlineexam_student_id'  => $this->input->post('onlineexam_student_id'),
                                'onlineexam_question_id' => $this->input->post('question_id_' . $row_value),
                                'select_option'          => $_POST['answer' . $row_value],
                            );

                            $file_name        = "";
                            $upload_file_name = "";
                            if (isset($_FILES["attachment" . $row_value]) && !empty($_FILES["attachment" . $row_value]['name'])) {
                                $file_name        = $_FILES["attachment" . $row_value]["name"];
                                $fileInfo         = pathinfo($_FILES["attachment" . $row_value]["name"]);
                                $upload_file_name = time() . uniqid(rand()) . '.' . $fileInfo['extension'];
                                move_uploaded_file($_FILES["attachment" . $row_value]["tmp_name"], "./uploads/onlinexam_images/" . $upload_file_name);

                            }
                            $inst_array['attachment_name']        = $file_name;
                            $inst_array['attachment_upload_name'] = $upload_file_name;

                            $save_result[] = $inst_array;
                        }
                    }

                }

                $this->onlineexamresult_model->add($save_result);
                $this->onlineexam_model->updateExamResult($this->input->post('onlineexam_student_id'));
                redirect('user/onlineexam', 'refresh');
            }
        } else {

        }
    }

    public function startexam____($id)
    {
        $data = array();
        $this->session->set_userdata('top_menu', 'Hostel');
        $this->session->set_userdata('sub_menu', 'hostel/index');
        $questionOpt          = $this->customlib->getQuesOption();
        $data['questionOpt']  = $questionOpt;
        $onlineexam_question  = $this->onlineexam_model->getExamQuestions($id);
        $data['examquestion'] = $onlineexam_question;
        $this->load->view('layout/student/header');
        $this->load->view('user/onlineexam/startexam', $data);
        $this->load->view('layout/student/footer');
    }

    public function getExamForm()
    {
        $data            = array();
        $question_status = 0;
        $recordid        = $this->input->post('recordid');
        $exam            = $this->onlineexam_model->get($recordid);
        if ($exam && isset($exam->workflow_version) && (int) $exam->workflow_version >= 2) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Use the localized assessment paper workflow.'), 409);
        }
        $data['exam']    = $exam;

        $data['questions'] = $this->onlineexam_model->getExamQuestions($recordid, $exam->is_random_question);

        $student_current_class         = $this->customlib->getStudentCurrentClsSection();
        $student_session_id            = $student_current_class->student_session_id;
        $onlineexam_student            = $this->onlineexam_model->examstudentsID($student_session_id, $exam->id);
        $data['onlineexam_student_id'] = $onlineexam_student;
        $getStudentAttemts             = $this->onlineexam_model->getStudentAttemts($onlineexam_student->id);
        $data['question_status']       = 0;
        $data['exam_duration']         = $exam->duration;
        if (strtotime(date('Y-m-d H:i:s')) >= strtotime(date($exam->exam_to))) {
            $question_status         = 1;
            $data['question_status'] = 1;
        } else if ($exam->attempt > $getStudentAttemts) {
            $this->onlineexam_model->addStudentAttemts(array('onlineexam_student_id' => $onlineexam_student->id));
        } else {
            $question_status         = 1;
            $data['question_status'] = 1;
        }

        $questionOpt         = $this->customlib->getQuesOption();
        $data['questionOpt'] = $questionOpt;
        $pag_content         = $this->load->view('user/onlineexam/_searchQuestionByExamID', $data, true);

        $total_remaining_seconds = round((strtotime($exam->exam_to) - strtotime(date('Y-m-d H:i:s'))) / 3600 * 60 * 60, 1);
        $exam_duration           = ($total_remaining_seconds < getSecondsFromHMS($exam->duration)) ? getHMSFromSeconds($total_remaining_seconds) : $exam->duration;

        echo json_encode(array('status' => 0, 'exam' => $exam, 'duration' => $exam_duration, 'page' => $pag_content, 'question_status' => $question_status, 'total_question' => count($data['questions'])));
    }

    /**
     * Start or resume one CBT/hybrid paper in a workflow-v2 assessment.
     */
    public function startpaper()
    {
        if (!$this->isValidV2Request()) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Your examination session token has expired.'), 403);
        }

        $student_session_id = $this->currentStudentSessionId();
        $result = $this->onlineexamattempt_model->startPaper(
            $student_session_id,
            (int) $this->input->post('onlineexam_id'),
            (int) $this->input->post('paper_id')
        );
        if (!$result['status']) {
            return $this->jsonResponse($result, 422);
        }

        foreach ($result['questions'] as $question) {
            $question->question_text = $this->security->xss_clean($question->question_text);
            if (isset($question->passage_title)) {
                $question->passage_title = $this->security->xss_clean($question->passage_title);
            }
            if (isset($question->passage_text)) {
                $question->passage_text = $this->security->xss_clean($question->passage_text);
            }
            $question->marking_scheme = null;
        }

        $data = $result;
        $data['workflow_token'] = $this->session->userdata('onlineexam_v2_token');
        $data['page'] = $this->load->view('user/onlineexam/_paper_v2', $data, true);
        $remaining = max(0, strtotime($result['attempt_paper']->deadline_at) - time());

        $this->auditV2($result['context']->id, $result['attempt']->id, 'paper_started', 'paper', $result['paper']->id);
        return $this->jsonResponse(array(
            'status' => true,
            'page' => $data['page'],
            'attempt_id' => (int) $result['attempt']->id,
            'paper_id' => (int) $result['paper']->id,
            'submission_key' => $result['attempt']->submission_key,
            'deadline_at' => $result['attempt_paper']->deadline_at,
            'server_time' => date('c'),
            'remaining_seconds' => $remaining,
        ));
    }

    /**
     * Idempotent per-answer autosave. A failed browser request can be safely
     * replayed because the model upserts by attempt and question snapshot.
     */
    public function autosave()
    {
        if (!$this->isValidV2Request()) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Your examination session token has expired.'), 403);
        }

        $response = $this->input->post('response', false);
        $response = $this->sanitizeAnswer($response);
        $result = $this->onlineexamattempt_model->saveAnswer(
            $this->currentStudentSessionId(),
            (int) $this->input->post('attempt_id'),
            (int) $this->input->post('question_snapshot_id'),
            $response,
            $this->input->post('client_sequence')
        );
        return $this->jsonResponse($result, $result['status'] ? 200 : 422);
    }

    /**
     * Server-side attachment validation for theory, oral and practical work.
     */
    public function uploadanswer()
    {
        if (!$this->isValidV2Request()) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Your examination session token has expired.'), 403);
        }
        if (empty($_FILES['attachment']) || !is_uploaded_file($_FILES['attachment']['tmp_name'])) {
            return $this->jsonResponse(array('status' => false, 'message' => 'No valid attachment was received.'), 422);
        }

        $settings = $this->filetype_model->get();
        $extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array_filter(array_map('trim', explode(',', strtolower($settings->file_extension))));
        $allowed_mimes = array_filter(array_map('trim', explode(',', strtolower($settings->file_mime))));
        $blocked_extensions = array('php', 'phtml', 'phar', 'cgi', 'pl', 'sh', 'html', 'htm', 'js', 'svg', 'apk');

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($_FILES['attachment']['tmp_name']));
        if (
            in_array($extension, $blocked_extensions, true)
            || !in_array($extension, $allowed_extensions, true)
            || !in_array($mime, $allowed_mimes, true)
            || (int) $_FILES['attachment']['size'] > (int) $settings->file_size
        ) {
            return $this->jsonResponse(array('status' => false, 'message' => 'The attachment type or size is not allowed.'), 422);
        }

        // Keep assessed work outside the public webroot. Markers retrieve it
        // through an authenticated, scoped controller action.
        $upload_directory = APPPATH . 'writable/onlineexam_answers/';
        if (!is_dir($upload_directory) && !mkdir($upload_directory, 0755, true)) {
            return $this->jsonResponse(array('status' => false, 'message' => 'The attachment directory is unavailable.'), 500);
        }
        $stored_name = bin2hex(random_bytes(24)) . '.' . $extension;
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_directory . $stored_name)) {
            return $this->jsonResponse(array('status' => false, 'message' => 'The attachment could not be stored.'), 500);
        }

        $result = $this->onlineexamattempt_model->attachFile(
            $this->currentStudentSessionId(),
            (int) $this->input->post('attempt_id'),
            (int) $this->input->post('question_snapshot_id'),
            array(
                'original_name' => basename($_FILES['attachment']['name']),
                'stored_name' => $stored_name,
                'mime' => $mime,
                'size' => (int) $_FILES['attachment']['size'],
            )
        );
        if (!$result['status']) {
            @unlink($upload_directory . $stored_name);
        } elseif (!empty($result['replaced_attachment'])) {
            $previous = basename((string) $result['replaced_attachment']);
            if ($previous !== '' && $previous !== $stored_name) {
                @unlink($upload_directory . $previous);
            }
        }
        return $this->jsonResponse($result, $result['status'] ? 200 : 422);
    }

    public function submitpaper()
    {
        if (!$this->isValidV2Request()) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Your examination session token has expired.'), 403);
        }

        $result = $this->onlineexamattempt_model->submitPaper(
            $this->currentStudentSessionId(),
            (int) $this->input->post('attempt_id'),
            (int) $this->input->post('paper_id'),
            (string) $this->input->post('submission_key')
        );
        if (!$result['status']) {
            return $this->jsonResponse($result, 422);
        }

        $this->auditV2(null, $result['attempt_id'], 'paper_submitted', 'paper', (int) $this->input->post('paper_id'));
        if ($result['attempt_status'] === Onlineexamattempt_model::STATUS_COMPLETED) {
            // A lost response may be retried after finalization committed but
            // before synchronization completed. Re-run the idempotent adapter.
            $this->load->model('onlineexamresultsync_model');
            $result['result_sync'] = $this->onlineexamresultsync_model->syncCompletedAttempt($result['attempt_id'], null);
        } elseif (in_array($result['attempt_status'], array(Onlineexamattempt_model::STATUS_SUBMITTED, Onlineexamattempt_model::STATUS_TIMED_OUT), true)) {
            $this->load->model('onlineexamworkflow_model');
            $finalization = $this->onlineexamworkflow_model->finalizeAttempt($result['attempt_id'], null);
            $result['finalization'] = $finalization;
            if (!empty($finalization['success'])) {
                $this->load->model('onlineexamresultsync_model');
                $result['result_sync'] = $this->onlineexamresultsync_model->syncCompletedAttempt($result['attempt_id'], null);
                $result['attempt_status'] = Onlineexamattempt_model::STATUS_COMPLETED;
            } elseif (!empty($finalization['pending_marking'])) {
                $result['attempt_status'] = Onlineexamattempt_model::STATUS_MARKING;
            }
        }
        if (!empty($result['onlineexam_id'])) {
            $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $result['onlineexam_id']);
        }
        return $this->jsonResponse($result);
    }

    protected function viewLocalizedExam($exam, $assignment, $student_session_id)
    {
        if (!$assignment) {
            show_error('You are not assigned to this assessment.', 403);
        }
        $context = $this->onlineexamattempt_model->getCandidateContext($student_session_id, $exam->id);
        if (!$context) {
            show_error('You are not assigned to this assessment.', 403);
        }

        $expired_results = $this->onlineexamattempt_model->submitExpiredPapers($student_session_id, $assignment->id);
        foreach ($expired_results as $expired_result) {
            if (!empty($expired_result['status']) && in_array($expired_result['attempt_status'], array(Onlineexamattempt_model::STATUS_SUBMITTED, Onlineexamattempt_model::STATUS_TIMED_OUT), true)) {
                $this->load->model('onlineexamworkflow_model');
                $finalization = $this->onlineexamworkflow_model->finalizeAttempt($expired_result['attempt_id'], null);
                if (!empty($finalization['success'])) {
                    $this->load->model('onlineexamresultsync_model');
                    $this->onlineexamresultsync_model->syncCompletedAttempt($expired_result['attempt_id'], null);
                }
            }
        }
        if (!empty($expired_results)) {
            $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $exam->id);
        }

        $attempt = $this->onlineexamattempt_model->getCurrentAttempt($assignment->id);
        $student = $this->student_model->getByStudentSession($student_session_id);
        $data = array(
            'exam' => $context,
            'assignment' => $assignment,
            'student' => $student,
            'sch_setting' => $this->sch_setting_detail,
            'attempt' => $attempt,
            'papers' => $this->onlineexamattempt_model->getPapers($exam->id, $attempt ? $attempt->id : null),
            'workflow_token' => $this->session->userdata('onlineexam_v2_token'),
            'released_feedback' => $attempt ? $this->onlineexamattempt_model->getReleasedFeedback($student_session_id, $attempt->id) : array(),
        );

        $this->load->view('layout/student/header');
        $this->load->view('user/onlineexam/view_v2', $data);
        $this->load->view('layout/student/footer');
    }

    protected function currentStudentSessionId()
    {
        $current = $this->customlib->getStudentCurrentClsSection();
        return (int) $current->student_session_id;
    }

    protected function isValidV2Request()
    {
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            return false;
        }
        $provided = (string) $this->input->post('workflow_token');
        $expected = (string) $this->session->userdata('onlineexam_v2_token');
        return $provided !== '' && $expected !== '' && hash_equals($expected, $provided);
    }

    protected function sanitizeAnswer($answer)
    {
        if (is_array($answer)) {
            $clean = array();
            foreach ($answer as $key => $value) {
                $clean[$this->security->xss_clean($key)] = $this->sanitizeAnswer($value);
            }
            return $clean;
        }
        return is_string($answer) ? $this->security->xss_clean($answer) : $answer;
    }

    protected function auditV2($onlineexam_id, $attempt_id, $action, $entity_type, $entity_id)
    {
        if (!$this->db->table_exists('onlineexam_audit_log')) {
            return;
        }
        if (!$onlineexam_id && $attempt_id) {
            $attempt = $this->onlineexamattempt_model->getOwnedAttempt($this->currentStudentSessionId(), $attempt_id);
            $onlineexam_id = $attempt ? $attempt->onlineexam_id : null;
        }
        $this->db->insert('onlineexam_audit_log', array(
            'onlineexam_id' => $onlineexam_id ? (int) $onlineexam_id : null,
            'attempt_id' => $attempt_id ? (int) $attempt_id : null,
            'actor_id' => $this->currentStudentSessionId(),
            'actor_type' => 'student',
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => (string) $entity_id,
            'ip_address' => $this->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function jsonResponse(array $payload, $status_code = 200)
    {
        return $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

}
