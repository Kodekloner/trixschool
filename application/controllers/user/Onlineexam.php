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
        show_error('This historical Online Examination is read-only and can no longer be opened for an attempt.', 410);
    }

    public function print()
    {
        show_error('Legacy Online Examination printing is retired.', 410);
    }

    public function save()
    {
        show_error('Historical Online Examination submission is retired. Use a published CBT assessment.', 410);
    }

    public function startexam____($id)
    {
        show_error('Historical Online Examination attempts are retired.', 410);
    }

    public function getExamForm()
    {
        return $this->jsonResponse(array(
            'status' => false,
            'message' => 'Historical Online Examination attempts are retired.',
        ), 410);
    }

    /**
     * Start or resume one CBT paper in a workflow-v2 assessment.
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
            $this->input->post('client_sequence'),
            $this->requestReceivedAt()
        );
        return $this->jsonResponse($result, $result['status'] ? 200 : 422);
    }

    /** File-based examination responses were retired from the compact CBT flow. */
    public function uploadanswer()
    {
        return $this->jsonResponse(array(
            'status' => false,
            'message' => 'File responses are not supported in Online Examination.',
        ), 410);
    }

    public function submitpaper()
    {
        if (!$this->isValidV2Request()) {
            return $this->jsonResponse(array('status' => false, 'message' => 'Your examination session token has expired.'), 403);
        }

        $final_answers = $this->input->post('final_answers', false);
        if (is_string($final_answers)
            && strlen($final_answers) > Onlineexamattempt_model::FINAL_ANSWERS_MAX_BYTES) {
            return $this->jsonResponse(array(
                'status' => false,
                'code' => 'final_answers_too_large',
                'preserve_local_queue' => true,
                'message' => 'The queued-answer packet is too large, so the paper was not submitted.',
            ), 422);
        }
        if (is_string($final_answers) && trim($final_answers) !== '') {
            $decoded = json_decode($final_answers, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return $this->jsonResponse(array(
                    'status' => false,
                    'code' => 'invalid_final_answers',
                    'preserve_local_queue' => true,
                    'message' => 'Queued answers could not be read, so the paper was not submitted.',
                ), 422);
            }
            $final_answers = $decoded;
        }
        if (!is_array($final_answers)) {
            $final_answers = array();
        }
        foreach ($final_answers as $index => $answer) {
            if (is_array($answer) && array_key_exists('response', $answer)) {
                $final_answers[$index]['response'] = $this->sanitizeAnswer($answer['response']);
            }
        }
        $encoded_final_answers = json_encode($final_answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded_final_answers === false
            || strlen($encoded_final_answers) > Onlineexamattempt_model::FINAL_ANSWERS_MAX_BYTES
            || count($final_answers) > Onlineexamattempt_model::FINAL_ANSWERS_MAX_ITEMS) {
            return $this->jsonResponse(array(
                'status' => false,
                'code' => 'final_answers_too_large',
                'preserve_local_queue' => true,
                'message' => 'The queued-answer packet is too large, so the paper was not submitted.',
            ), 422);
        }

        $result = $this->onlineexamattempt_model->submitPaper(
            $this->currentStudentSessionId(),
            (int) $this->input->post('attempt_id'),
            (int) $this->input->post('paper_id'),
            (string) $this->input->post('submission_key'),
            $final_answers,
            $this->requestReceivedAt()
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
        if (!$this->onlineexamattempt_model->isSupportedAssessmentContext($context)) {
            show_error('This historical assessment is read-only.', 410);
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

    /** Use PHP's trusted request-arrival time so database lock waits do not cost a candidate an answer. */
    protected function requestReceivedAt()
    {
        $received_at = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? (float) $_SERVER['REQUEST_TIME_FLOAT']
            : microtime(true);
        return $received_at > 0 ? $received_at : microtime(true);
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
