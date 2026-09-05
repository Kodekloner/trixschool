<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Onlineexam extends Admin_Controller
{
    public $sch_setting_detail = array();
    public function __construct()
    {
        parent::__construct();
        $this->config->load('app-config');
        $this->sch_setting_detail = $this->setting_model->getSetting();
        $this->load->library('mailsmsconf');
        $this->load->model('onlineexamworkflow_model');
        $this->load->model('onlineexamoperations_model');
        if (!$this->session->userdata('onlineexam_workflow_csrf')) {
            $this->session->set_userdata('onlineexam_workflow_csrf', bin2hex(random_bytes(32)));
        }

    }

    public function index()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $data=array();
        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $questionList           = $this->onlineexam_model->get();
        $data['questionList']   = $questionList;
        $subject_result         = $this->subject_model->get();
        $data['subjectlist']    = $subject_result;
        $questionOpt            = $this->customlib->getQuesOption();
        $data['questionOpt']    = $questionOpt;
        $data['question_type']  = $this->config->item('question_type');
        $data['question_level'] = $this->config->item('question_level');
        $data['classList']      = $this->class_model->get();
        $data['sessionList']    = $this->session_model->get();
        $data['current_session_id'] = $this->setting_model->getCurrentSession();
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/index', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getexamlist()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $userdata = $this->customlib->getUserData();
        $teacher_id = !empty($userdata['role_id']) && (int) $userdata['role_id'] === 2
            ? (int) $this->customlib->getStaffID()
            : null;
        $questionList           = $this->onlineexam_model->getexamlist($teacher_id);
        
		$subject_result         = $this->subject_model->get();
        $subjectlist   	= $subject_result;
        $questionOpt            = $this->customlib->getQuesOption();
        $questionOpt    = $questionOpt;
        $question_type  = $this->config->item('question_type');
        $question_level = $this->config->item('question_level');
        $classList      = $this->class_model->get();
        $m       = json_decode($questionList);

        $currency_symbol = $this->customlib->getSchoolCurrencyFormat();
        $dt_data = array();
        if (!empty($m->data)) {
            foreach ($m->data as $key => $subject_value) {
                $workflow_version = isset($subject_value->workflow_version) ? (int) $subject_value->workflow_version : 1;
                $compact_supported = $workflow_version === 2
                    && $this->compactAssessmentIsExecutable($subject_value, true);
                $assign   = '';
                $addquestion_btn='';
                $editbtn = '';
                $deletebtn = '' ;
                $question_list='';
                $set_enable=false;
                if ($compact_supported) {
                    $set_enable = in_array($subject_value->lifecycle_status, array('draft', 'scheduled', 'published', 'in_progress', 'marking', 'completed'), true);
                }
                $title="<a href='#' data-toggle='popover' class='detail_popover'>".html_escape($subject_value->exam)."</a>" ;

                   if ($subject_value->description == "") {
                        $description= "<div class='fee_detail_popover' style='display: none'><p class='text text-danger'>".$this->lang->line('no_description')."</div></p>" ;
                   }else{
                     $description= "<div class='fee_detail_popover' style='display: none'><p>".html_escape(strip_tags($subject_value->description))."</p></div>" ;
                   }
                   if ($compact_supported) {
                    $is_quiz = '<span class="label label-primary">Online Assessment</span><br><small>' . html_escape(ucwords(str_replace('_', ' ', $subject_value->purpose))) . '</small>';
                    $title .= '<div class="text-muted small">' . html_escape($subject_value->session_name) . ' / ' . html_escape(strtoupper($subject_value->term)) . ' Term<br>' . html_escape($subject_value->class_name) . ' (' . html_escape($subject_value->section_names) . ') &middot; ' . html_escape($subject_value->subject_name) . '</div>';
                   } elseif ($workflow_version === 2) {
                    $is_quiz = '<span class="label label-default">Historical read-only</span>';
                   }else{
                    $is_quiz= '<span class="label label-default">Legacy read-only</span>';
                   }
                   $descriptive_ques = $workflow_version === 2
                        ? (int) $subject_value->total_papers . ' paper(s)<br><span>(' . (int) $subject_value->total_ques . ' questions)</span>'
                        : $subject_value->total_ques."<br /><span>(". $this->lang->line('descriptive').':'. $subject_value->total_descriptive_ques.")</span>" ;
                    if($subject_value->is_active == 1){ 
                        $is_active="<i class='fa fa-check-square-o'></i><span style='display:none'>Yes</span>" ;
                    } else{
                        $is_active="<i class='fa fa-exclamation-circle'></i><span style='display:none'>No</span>" ;
                    }  
                    if ($compact_supported) {
                        $status_class = in_array($subject_value->lifecycle_status, array('published', 'in_progress', 'marking', 'completed'), true) ? 'success' : ($subject_value->lifecycle_status === 'cancelled' ? 'danger' : 'warning');
                        $is_active = '<span class="label label-' . $status_class . '">' . html_escape(ucwords(str_replace('_', ' ', $subject_value->lifecycle_status))) . '</span>';
                        $publish_result = '<span class="label label-default">' . html_escape(ucwords(str_replace('_', ' ', $subject_value->feedback_status))) . '</span>';
                    } elseif($subject_value->publish_result == 1){
                        $publish_result= "<i class='fa fa-check-square-o'></i><span style='display:none'>Yes</span>" ;
                    }else{
                        $publish_result= "<i class='fa fa-exclamation-circle'></i><span style='display:none'>No</span>" ;
                    }
                    if($compact_supported && $this->rbac->hasPrivilege('online_assign_view_student', 'can_view') && $set_enable ){

                      $assign = "<a href='" . base_url('admin/onlineexam/assign/' . $subject_value->id) . "' data-toggle='tooltip' class='btn btn-default btn-xs' title='" . html_escape($this->lang->line('assign / view')) . "'><i class='fa fa-tag'></i></a>";
                    }
                    if ($compact_supported && $this->rbac->hasPrivilege('add_questions_in_exam', 'can_view')) {
                       $addquestion_btn=" <a class='btn btn-primary btn-xs' href='".base_url().'admin/onlineexam/builder/'.$subject_value->id."' data-toggle='tooltip' title='Paper and section builder'><i class='fa fa-sitemap'></i></a>" ;
                    }
                    if ($compact_supported && $subject_value->lifecycle_status === 'draft' && $this->rbac->hasPrivilege('online_examination', 'can_edit')) {
                        $editbtn = " <a data-toggle='tooltip' class='btn btn-default btn-xs' href='".base_url().'admin/onlineexam/workflow/'.$subject_value->id."' title='".$this->lang->line('edit')."'><i class='fa fa-pencil'></i></a>";
                    }
                    if ($compact_supported && $this->rbac->hasPrivilege('online_examination', 'can_delete') && $subject_value->lifecycle_status === 'draft') {
                            $deletebtn = " <form method='post' action='" . base_url('admin/onlineexam/workflowDelete/' . $subject_value->id) . "' style='display:inline' onsubmit='return confirm(\"Delete this unattempted draft assessment?\")'>"
                                . $this->customlib->getCSRF()
                                . "<input type='hidden' name='onlineexam_workflow_token' value='" . html_escape($this->session->userdata('onlineexam_workflow_csrf')) . "'><button type='submit' class='btn btn-default btn-xs' title='" . $this->lang->line('delete') . "'><i class='fa fa-remove'></i></button></form>";
                    }

                    if ($compact_supported) {
                        $question_list = "<a href='".base_url().'admin/onlineexam/operations/'.$subject_value->id."' class='btn btn-default btn-xs' data-toggle='tooltip' title='Operations and theory grading'><i class='fa fa-newspaper-o'></i></a>";
                    }

                $row       = array();
                $row[]     = $title.$description;
                $row[]     = $is_quiz ; 
                $row[]     = $descriptive_ques;
                $row[]     = $this->customlib->dateyyyymmddToDateTimeformat($subject_value->exam_from, false);
                $row[]     = $this->customlib->dateyyyymmddToDateTimeformat($subject_value->exam_to, false);
                $row[]     = $subject_value->duration;
                $row[]     = $is_active ;
                $row[]     = $publish_result;
                $row[]     = $assign." ".$addquestion_btn." ".$editbtn." ".$question_list." ".$deletebtn ;
                $dt_data[] = $row;
            }
        }

        $json_data = array(
            "draw"            => intval($m->draw),
            "recordsTotal"    => intval($m->recordsTotal),
            "recordsFiltered" => intval($m->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data);
    }

    /**
     * Create or edit a compact academic assessment. Historical examinations
     * remain visible as read-only records and are never converted here.
     */
    public function workflow($id = 0)
    {
        $id = (int) $id;
        $required_privilege = $id > 0 ? 'can_edit' : 'can_add';
        if (!$this->rbac->hasPrivilege('online_examination', $required_privilege)) {
            access_denied();
        }

        $exam = $id > 0 ? $this->onlineexam_model->getWorkflow($id) : null;
        if ($id > 0 && !$exam) {
            show_404();
        }
        if ($exam && !$this->compactAssessmentIsExecutable($exam, true)) {
            $this->retiredOnlineexamAction('This historical assessment is read-only.');
        }
        if ($exam && !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }

        $data = array(
            'exam'               => $exam,
            'classList'          => $this->class_model->get(),
            'subjectList'        => $this->subject_model->get(),
            'sessionList'        => $this->session_model->get(),
            'current_session_id' => $this->setting_model->getCurrentSession(),
            'workflow_csrf'      => $this->session->userdata('onlineexam_workflow_csrf'),
            'purposes'           => array(
                'ca'             => 'Continuous Assessment (CA)',
                'midterm'        => 'Midterm Assessment',
                'holiday'        => 'Holiday Assessment',
                'kindergarten'   => 'Kindergarten Assessment',
            ),
        );

        if ($exam) {
            $data['sections'] = $this->onlineexam_model->getClassSectionsForWorkflow($exam->class_id);
            $data['academic_configuration'] = $this->onlineexam_model->getAcademicConfiguration(
                $exam->class_id,
                $exam->subject_id,
                $exam->purpose,
                $exam->session_id,
                $exam->term,
                $exam->section_ids
            );
        } else {
            $data['sections'] = array();
            $data['academic_configuration'] = null;
        }

        if ($this->input->server('REQUEST_METHOD') === 'POST') {
            $this->requireWorkflowCsrf();
            if ($exam && ($exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($exam->id, $exam->revision))) {
                $data['workflow_error'] = 'This assessment is frozen. Unpublish it before editing, or create a new revision after attempts have started.';
            } else {
                $this->form_validation->set_rules('exam', 'Assessment title', 'trim|required|xss_clean');
                $this->form_validation->set_rules('session_id', 'Session', 'trim|required|integer');
                $this->form_validation->set_rules('term', 'Term', 'trim|required|in_list[1st,2nd,3rd]');
                $this->form_validation->set_rules('class_id', 'Class', 'trim|required|integer');
                $this->form_validation->set_rules('section_ids[]', 'Class arm/section', 'required');
                $this->form_validation->set_rules('subject_id', 'Subject', 'trim|required|integer');
                $this->form_validation->set_rules('purpose', 'Assessment purpose', 'trim|required|in_list[ca,midterm,holiday,kindergarten]');
                $this->form_validation->set_rules('exam_from', 'Opening date/time', 'trim|required');
                $this->form_validation->set_rules('exam_to', 'Closing date/time', 'trim|required');
                $this->form_validation->set_rules('duration_minutes', 'Duration', 'trim|required|integer|greater_than[0]|less_than_equal_to[1439]');
                $this->form_validation->set_rules('passing_percentage', 'Passing percentage', 'trim|required|numeric|greater_than_equal_to[0]|less_than_equal_to[100]');

                $class_id = (int) $this->input->post('class_id');
                $subject_id = (int) $this->input->post('subject_id');
                $section_ids = array_map('intval', (array) $this->input->post('section_ids'));
                $data['sections'] = $this->onlineexam_model->getClassSectionsForWorkflow($class_id);
                $purpose = strtolower(trim((string) $this->input->post('purpose')));
                $session_id = (int) $this->input->post('session_id');
                $term = strtolower(trim((string) $this->input->post('term')));
                $data['academic_configuration'] = $configuration = $this->onlineexam_model->getAcademicConfiguration(
                    $class_id,
                    $subject_id,
                    $purpose,
                    $session_id,
                    $term,
                    $section_ids
                );

                $extra_errors = array();
                if (!$this->workflowIdIsAllowed($session_id, $data['sessionList'])) {
                    $extra_errors[] = 'Select a valid academic session.';
                }
                if (!$this->workflowIdIsAllowed($class_id, $data['classList'])) {
                    $extra_errors[] = 'You are not assigned to the selected class.';
                }
                if (!$this->workflowIdIsAllowed($subject_id, $data['subjectList'])) {
                    $extra_errors[] = 'You are not assigned to the selected subject.';
                }
                $valid_section_ids = array_map('intval', array_column($data['sections'], 'id'));
                if (empty($section_ids) || array_diff($section_ids, $valid_section_ids)) {
                    $extra_errors[] = 'One or more selected class arms do not belong to this class.';
                }
                if (!$this->onlineexam_model->subjectIsAssignedToAcademicScope($class_id, $section_ids, $subject_id, $session_id)) {
                    $extra_errors[] = 'The selected subject is not assigned to every chosen class arm in this academic session.';
                }
                if (!$this->workflowTeacherHasAssignment($class_id, $section_ids, $subject_id, (int) $this->input->post('session_id'))) {
                    $extra_errors[] = 'Teachers may only create assessments for class arms and subjects assigned to them.';
                }

                $adapter = isset($configuration['adapter']) ? $configuration['adapter'] : null;
                if (empty($configuration['valid']) || $adapter === null) {
                    $extra_errors[] = !empty($configuration['message'])
                        ? $configuration['message']
                        : 'No valid result destination is configured for this assessment purpose.';
                }

                $target_component = null;
                $target_max_score = null;
                if ($adapter === 'standard_component') {
                    $target_component = strtolower(trim($this->input->post('target_component')));
                    foreach ($configuration['components'] as $component) {
                        if ($component['value'] === $target_component) {
                            $target_max_score = (float) $component['maximum'];
                            break;
                        }
                    }
                    if (!$configuration['valid'] || $target_max_score === null || $target_max_score <= 0) {
                        $extra_errors[] = 'Select a valid CA or Examination component with a positive maximum score.';
                    }
                } elseif (in_array($adapter, array('kindergarten_concept', 'holiday_assessment'), true)) {
                    $target_max_score = isset($configuration['target_maximum'])
                        ? (float) $configuration['target_maximum']
                        : null;
                    if ($target_max_score === null || $target_max_score <= 0) {
                        $extra_errors[] = 'The inferred result destination has no positive maximum score.';
                    }
                }

                $exam_from_timestamp = $this->workflowDateToTimestamp($this->input->post('exam_from'));
                $exam_to_timestamp   = $this->workflowDateToTimestamp($this->input->post('exam_to'));
                if (!$exam_from_timestamp || !$exam_to_timestamp || $exam_to_timestamp <= $exam_from_timestamp) {
                    $extra_errors[] = 'The closing date/time must be after the opening date/time.';
                }

                if ($this->form_validation->run() !== false && empty($extra_errors)) {
                    $duration_minutes = (int) $this->input->post('duration_minutes');
                    $hours = floor($duration_minutes / 60);
                    $minutes = $duration_minutes % 60;
                    $record = array(
                        'exam'                => $this->security->xss_clean($this->input->post('exam')),
                        'attempt'             => 1,
                        'exam_from'           => date('Y-m-d H:i:s', $exam_from_timestamp),
                        'exam_to'             => date('Y-m-d H:i:s', $exam_to_timestamp),
                        'duration'            => sprintf('%02d:%02d:00', min(23, $hours), $minutes),
                        'passing_percentage'  => (float) $this->input->post('passing_percentage'),
                        'description'         => $this->security->xss_clean($this->input->post('description')),
                        'session_id'          => (int) $this->input->post('session_id'),
                        'is_quiz'             => 0,
                        'publish_result'      => 0,
                        'is_active'           => 0,
                        'is_marks_display'    => $this->input->post('is_marks_display') ? 1 : 0,
                        'is_neg_marking'      => $this->input->post('is_neg_marking') ? 1 : 0,
                        'is_random_question'  => $this->input->post('is_random_question') ? 1 : 0,
                        'workflow_version'    => 2,
                        'term'                => $this->input->post('term'),
                        'class_id'            => $class_id,
                        'subject_id'          => $subject_id,
                        'purpose'             => $purpose,
                        'result_adapter'      => $adapter,
                        'target_component'    => $target_component,
                        'result_type'         => !empty($configuration['result_type']) ? $configuration['result_type'] : ($purpose === 'midterm' ? 'midterm' : 'termly'),
                        'target_max_score'     => $target_max_score,
                        'feedback_status'     => 'hidden',
                    );

                    if ($exam) {
                        $record['id'] = $exam->id;
                        $record['lifecycle_status'] = 'draft';
                        $record['revision'] = max(1, (int) $exam->revision);
                    } else {
                        $record['lifecycle_status'] = 'draft';
                        $record['revision'] = 1;
                        $record['created_by'] = (int) $this->customlib->getStaffID();
                        $record['publish_exam_notification'] = 0;
                        $record['publish_result_notification'] = 0;
                    }

                    $saved_id = $this->onlineexam_model->saveWorkflow(
                        $record,
                        $section_ids,
                        isset($configuration['holiday_mappings']) ? $configuration['holiday_mappings'] : array()
                    );
                    if ($saved_id) {
                        $this->onlineexam_model->auditWorkflow($saved_id, $this->customlib->getStaffID(), $exam ? 'update_assessment' : 'create_assessment', 'onlineexam', $saved_id, $exam, $record);
                        $this->session->set_flashdata('msg', '<div class="alert alert-success">Academic context saved. Build the papers and sections next.</div>');
                        redirect('admin/onlineexam/builder/' . $saved_id);
                        return;
                    }
                    $extra_errors[] = 'The assessment could not be saved.';
                }

                if (!empty($extra_errors)) {
                    $data['workflow_error'] = implode('<br>', array_map('html_escape', $extra_errors));
                }
            }
        }

        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/workflow', $data);
        $this->load->view('layout/footer', $data);
    }

    public function academicconfiguration()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $class_id = (int) $this->input->get('class_id');
        $subject_id = (int) $this->input->get('subject_id');
        $session_id = (int) $this->input->get('session_id');
        $purpose = strtolower(trim((string) $this->input->get('purpose')));
        $term = strtolower(trim((string) $this->input->get('term')));
        $selected_section_ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $this->input->get('section_ids')
        ))));
        if ($session_id <= 0) {
            $session_id = (int) $this->setting_model->getCurrentSession();
        }
        if (!$this->workflowIdIsAllowed($session_id, $this->session_model->get())) {
            return $this->output->set_status_header(422)->set_content_type('application/json')->set_output(json_encode(array(
                'status' => 0,
                'message' => 'Select a valid academic session.',
            )));
        }
        if ($class_id <= 0 || $subject_id <= 0) {
            return $this->output->set_status_header(422)->set_content_type('application/json')->set_output(json_encode(array(
                'status' => 0,
                'message' => 'Select a valid class and subject.',
            )));
        }
        $sections = $this->onlineexam_model->getClassSectionsForWorkflow($class_id);
        $userdata = $this->customlib->getUserData();
        if (!empty($userdata['role_id']) && (int) $userdata['role_id'] === 2) {
            $allowed = $this->workflowTeacherAssignedSectionIds($class_id, $subject_id, $session_id);
            $sections = array_values(array_filter($sections, function ($section) use ($allowed) {
                $id = is_array($section) ? $section['id'] : $section->id;
                return in_array((int) $id, $allowed, true);
            }));
            if (empty($sections)) {
                return $this->output->set_status_header(403)->set_content_type('application/json')->set_output(json_encode(array(
                    'status' => 0,
                    'message' => 'This class and subject are outside your teaching assignment.',
                )));
            }
        }
        $available_section_ids = array_map('intval', array_column($sections, 'id'));
        if (array_diff($selected_section_ids, $available_section_ids)) {
            return $this->output->set_status_header(403)->set_content_type('application/json')->set_output(json_encode(array(
                'status' => 0,
                'message' => 'One or more selected class arms are outside your available academic scope.',
            )));
        }
        if (!empty($selected_section_ids)
            && !$this->onlineexam_model->subjectIsAssignedToAcademicScope(
                $class_id,
                $selected_section_ids,
                $subject_id,
                $session_id
            )) {
            return $this->output->set_status_header(422)->set_content_type('application/json')->set_output(json_encode(array(
                'status' => 0,
                'message' => 'The selected subject is not assigned to every chosen class arm in this academic session.',
            )));
        }
        echo json_encode(array(
            'status'        => 1,
            'sections'      => $sections,
            'configuration' => $this->onlineexam_model->getAcademicConfiguration(
                $class_id,
                $subject_id,
                $purpose,
                $session_id,
                $term,
                $selected_section_ids
            ),
        ));
    }

    public function builder($id)
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_view')) {
            access_denied();
        }
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        $compact_supported = $this->compactAssessmentIsExecutable($exam, true);
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }

        $publish_validation = $compact_supported
            ? $this->onlineexamworkflow_model->validateAssessment($exam->id)
            : array('valid' => false, 'errors' => array('This historical assessment is read-only.'));
        $result_profile = $this->onlineexam_model->getWorkflowResultProfile($exam->id, $exam->result_adapter);
        $data = array(
            'exam'             => $exam,
            'papers'           => $this->onlineexam_model->getWorkflowPapers($exam->id),
            'question_type'    => array_diff_key($this->localizedQuestionTypes(), array('grouped_passage' => true)),
            'native_question_types' => $this->localizedQuestionTypes(),
            'authored_questions' => $this->onlineexam_model->getWorkflowAuthoredQuestions($exam->id),
            'compact_supported' => $compact_supported,
            'editable'         => $compact_supported && $exam->lifecycle_status === 'draft' && !$this->onlineexam_model->hasWorkflowAttemptsForRevision($exam->id, $exam->revision),
            'publish_errors'   => $publish_validation['valid'] ? array() : $publish_validation['errors'],
            'configuration'    => $this->onlineexam_model->getAcademicConfiguration(
                $exam->class_id,
                $exam->subject_id,
                $exam->purpose,
                $exam->session_id,
                $exam->term,
                $exam->section_ids
            ),
            'result_profile'   => $result_profile,
            'kindergarten_options' => $exam->result_adapter === 'kindergarten_concept' ? $this->onlineexam_model->getKindergartenMappingOptions($exam->class_id, $exam->subject_id) : array(),
            'kindergarten_mappings' => $exam->result_adapter === 'kindergarten_concept' ? $this->onlineexam_model->getKindergartenMappings($exam->id) : array(),
            'workflow_csrf'    => $this->session->userdata('onlineexam_workflow_csrf'),
        );

        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/builder', $data);
        $this->load->view('layout/footer', $data);
    }

    public function paperSave()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            show_error('This assessment is frozen and cannot be edited.', 409);
        }

        $this->form_validation->set_rules('title', 'Paper title', 'trim|required|xss_clean');
        $this->form_validation->set_rules('paper_type', 'Paper type', 'trim|required|in_list[objective,theory]');
        $this->form_validation->set_rules('delivery_mode', 'Delivery mode', 'trim|required|in_list[cbt]');
        $this->form_validation->set_rules('duration_minutes', 'Paper duration', 'trim|required|integer|greater_than[0]');
        $this->form_validation->set_rules('raw_max_score', 'Raw maximum', 'trim|required|numeric|greater_than[0]');
        $this->form_validation->set_rules('contribution_score', 'Contribution', 'trim|required|numeric|greater_than[0]|less_than_equal_to[100]');
        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . validation_errors() . '</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id);
            return;
        }

        $paper_id = (int) $this->input->post('paper_id');
        if ($paper_id > 0) {
            $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
            if (!$paper || (int) $paper['onlineexam_id'] !== $onlineexam_id) {
                show_error('Invalid paper.', 404);
            }
        }

        $starts_at = $this->input->post('starts_at') ? $this->workflowDateToTimestamp($this->input->post('starts_at')) : strtotime($exam->exam_from);
        $ends_at   = $this->input->post('ends_at') ? $this->workflowDateToTimestamp($this->input->post('ends_at')) : strtotime($exam->exam_to);
        if (!$starts_at || !$ends_at || $ends_at <= $starts_at || $starts_at < strtotime($exam->exam_from) || $ends_at > strtotime($exam->exam_to)) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">Paper dates must fall inside the assessment window and the closing date must be later.</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id);
            return;
        }

        $record = array(
            'id'                 => $paper_id ?: null,
            'onlineexam_id'      => $onlineexam_id,
            'title'              => $this->security->xss_clean($this->input->post('title')),
            'paper_code'         => $this->security->xss_clean($this->input->post('paper_code')),
            'paper_type'         => $this->input->post('paper_type'),
            'delivery_mode'      => 'cbt',
            'instructions'       => $this->security->xss_clean($this->input->post('instructions')),
            'starts_at'          => date('Y-m-d H:i:s', $starts_at),
            'ends_at'            => date('Y-m-d H:i:s', $ends_at),
            'duration_minutes'   => (int) $this->input->post('duration_minutes'),
            'raw_max_score'      => (float) $this->input->post('raw_max_score'),
            'contribution_score' => (float) $this->input->post('contribution_score'),
            'display_order'      => max(0, (int) $this->input->post('display_order')),
            'is_active'          => $this->input->post('is_active') ? 1 : 0,
        );
        $saved_paper_id = $this->onlineexam_model->saveWorkflowPaper($record);
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), $paper_id ? 'update_paper' : 'create_paper', 'onlineexam_papers', $saved_paper_id, isset($paper) ? $paper : null, $record);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Paper saved.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function paperDelete()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            show_error('This assessment is frozen and cannot be edited.', 409);
        }
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        if (!$paper || (int) $paper['onlineexam_id'] !== $onlineexam_id) {
            show_error('The selected paper does not belong to this assessment.', 404);
        }
        if (!$this->onlineexam_model->removeWorkflowPaper($paper_id, $onlineexam_id)) {
            show_error('The paper could not be removed.', 500);
        }
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'delete_paper', 'onlineexam_papers', $paper_id, $paper, null);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Paper removed.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function paperSectionSave()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$paper || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || (int) $paper['onlineexam_id'] !== $onlineexam_id || $exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            show_error('This assessment is frozen or the paper is invalid.', 409);
        }

        $this->form_validation->set_rules('title', 'Section title', 'trim|required|xss_clean');
        $this->form_validation->set_rules('answer_rule', 'Answer rule', 'trim|required|in_list[all,answer_any,compulsory_plus_choice]');
        $this->form_validation->set_rules('answer_count', 'Answer count', 'trim|integer|greater_than_equal_to[0]');
        if ($this->form_validation->run() === false) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . validation_errors() . '</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id);
            return;
        }

        $section_id = (int) $this->input->post('paper_section_id');
        if ($section_id > 0) {
            $section = $this->onlineexam_model->getWorkflowPaperSection($section_id);
            if (!$section || (int) $section['paper_id'] !== $paper_id) {
                show_error('Invalid paper section.', 404);
            }
        }
        $answer_rule = $this->input->post('answer_rule');
        $record = array(
            'id'            => $section_id ?: null,
            'paper_id'      => $paper_id,
            'title'         => $this->security->xss_clean($this->input->post('title')),
            'instructions'  => $this->security->xss_clean($this->input->post('instructions')),
            'answer_rule'   => $answer_rule,
            'answer_count'  => $answer_rule === 'all' ? null : max(1, (int) $this->input->post('answer_count')),
            'display_order' => max(0, (int) $this->input->post('display_order')),
        );
        $saved_section_id = $this->onlineexam_model->saveWorkflowPaperSection($record);
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), $section_id ? 'update_paper_section' : 'create_paper_section', 'onlineexam_paper_sections', $saved_section_id, isset($section) ? $section : null, $record);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Paper section saved.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function paperSectionDelete()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $section_id = (int) $this->input->post('paper_section_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$paper || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || (int) $paper['onlineexam_id'] !== $onlineexam_id || $exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            show_error('This assessment is frozen or the paper is invalid.', 409);
        }
        $section = $this->onlineexam_model->getWorkflowPaperSection($section_id);
        if (!$section || (int) $section['paper_id'] !== $paper_id || (int) $section['onlineexam_id'] !== $onlineexam_id) {
            show_error('The selected section does not belong to this paper.', 404);
        }
        if (!$this->onlineexam_model->removeWorkflowPaperSection($section_id, $paper_id, $onlineexam_id)) {
            show_error('The paper section could not be removed.', 500);
        }
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'delete_paper_section', 'onlineexam_paper_sections', $section_id, $section, null);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Paper section removed.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function workflowQuestionSave()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $paper_section_id = (int) $this->input->post('paper_section_id');
        $question_id = (int) $this->input->post('question_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        $question = $this->question_model->get($question_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$paper || !$question
            || !$this->question_model->canAccessQuestion($question_id)
            || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)
            || (int) $paper['onlineexam_id'] !== $onlineexam_id || $exam->lifecycle_status !== 'draft'
            || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            echo json_encode(array('status' => 0, 'message' => 'The assessment is frozen or the selected records are invalid.'));
            return;
        }
        if ((int) $question->subject_id !== (int) $exam->subject_id || (int) $question->class_id !== (int) $exam->class_id) {
            echo json_encode(array('status' => 0, 'message' => 'Only questions for the assessment class and subject may be assigned.'));
            return;
        }
        if ((int) $question->section_id > 0
            && !in_array((int) $question->section_id, array_map('intval', (array) $exam->section_ids), true)) {
            echo json_encode(array('status' => 0, 'message' => 'This question belongs to a class arm that is not selected for the assessment.'));
            return;
        }
        if (!in_array(strtolower((string) $question->question_type), $this->workflowResponseTypes(), true)) {
            echo json_encode(array('status' => 0, 'message' => 'This question uses a response type that is no longer supported by the CBT workflow.'));
            return;
        }
        if ($paper['paper_type'] === 'objective' && strtolower((string) $question->question_type) === 'long_answer') {
            echo json_encode(array('status' => 0, 'message' => 'Theory/long-answer questions must be assigned to a Theory paper.'));
            return;
        }
        $marking_scheme = $this->security->xss_clean($this->input->post('marking_scheme'));
        if (strtolower((string) $question->question_type) === 'long_answer'
            && trim(strip_tags((string) $marking_scheme)) === '') {
            echo json_encode(array('status' => 0, 'message' => 'Theory/long-answer questions require a marking scheme.'));
            return;
        }
        if ($paper_section_id > 0) {
            $section = $this->onlineexam_model->getWorkflowPaperSection($paper_section_id);
            if (!$section || (int) $section['paper_id'] !== $paper_id) {
                echo json_encode(array('status' => 0, 'message' => 'The selected paper section is invalid.'));
                return;
            }
        }
        $marks = (float) $this->input->post('marks');
        $negative_marks = (float) $this->input->post('neg_marks');
        if ($marks <= 0 || $negative_marks < 0 || $negative_marks > $marks) {
            echo json_encode(array('status' => 0, 'message' => 'Marks must be positive and negative marks must be between zero and the question mark.'));
            return;
        }

        $id = $this->onlineexam_model->assignWorkflowQuestion(array(
            'question_id'       => $question_id,
            'onlineexam_id'     => $onlineexam_id,
            'paper_id'          => $paper_id,
            'paper_section_id'  => $paper_section_id ?: null,
            'marks'             => $marks,
            'neg_marks'         => $exam->is_neg_marking && $paper['paper_type'] === 'objective' ? $negative_marks : 0,
            'display_order'     => max(0, (int) $this->input->post('display_order')),
            'is_compulsory'     => $this->input->post('is_compulsory') ? 1 : 0,
            'marking_scheme'    => $marking_scheme,
        ));
        if ($id) {
            $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'assign_question', 'onlineexam_questions', $id, null, array('question_id' => $question_id, 'paper_id' => $paper_id, 'paper_section_id' => $paper_section_id));
        }
        echo json_encode(array('status' => $id ? 1 : 0, 'id' => $id, 'message' => $id ? 'Question assigned to paper.' : 'Question could not be assigned.'));
    }

    /** Create and assign a structured question without leaving the v2 builder. */
    public function workflowQuestionAuthor()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {
            access_denied();
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_error('Method not allowed.', 405);
        }
        $this->requireWorkflowCsrf();

        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $paper_section_id = (int) $this->input->post('paper_section_id');
        $editing_assignment_id = (int) $this->input->post('onlineexam_question_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$paper
            || (int) $paper['onlineexam_id'] !== $onlineexam_id
            || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)
            || $exam->lifecycle_status !== 'draft'
            || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            show_error('This assessment is frozen or the selected paper is invalid.', 409);
        }
        if ($paper_section_id > 0) {
            $section = $this->onlineexam_model->getWorkflowPaperSection($paper_section_id);
            if (!$section || (int) $section['paper_id'] !== $paper_id || (int) $section['onlineexam_id'] !== $onlineexam_id) {
                show_error('The selected section does not belong to this paper.', 404);
            }
        }
        $editing_question = null;
        if ($editing_assignment_id > 0) {
            $editing_question = $this->onlineexam_model->getWorkflowAuthoredQuestion($editing_assignment_id, $onlineexam_id);
            if (!$editing_question) {
                show_error('The structured question does not belong to this assessment.', 404);
            }
        }

        $authoring_type = strtolower(trim((string) $this->input->post('authored_question_type')));
        $allowed_types = array_keys($this->localizedQuestionTypes());
        $errors = array();
        if (!in_array($authoring_type, $allowed_types, true)) {
            $errors[] = 'Select a supported question type.';
        }

        $question_type = $authoring_type;
        $passage = null;
        if ($authoring_type === 'grouped_passage') {
            $question_type = strtolower(trim((string) $this->input->post('passage_response_type')));
            $passage_response_types = array('singlechoice', 'multichoice', 'true_false', 'short_answer', 'numeric', 'long_answer');
            if (!in_array($question_type, $passage_response_types, true)) {
                $errors[] = 'Select a valid response type for the passage question.';
            }
            $group_key = strtolower(trim((string) $this->input->post('passage_group_key')));
            $passage_title = trim(strip_tags((string) $this->input->post('passage_title')));
            $passage_text = $this->workflowSanitizeAuthoredText($this->input->post('passage_text'));
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $group_key)) {
                $errors[] = 'Passage group must contain only letters, numbers, hyphens, or underscores and be no longer than 64 characters.';
            }
            if ($passage_title === '' || trim(strip_tags($passage_text)) === '') {
                $errors[] = 'A grouped passage needs both a title and passage text.';
            }
            $passage = array(
                'group_key' => $group_key,
                'title' => mb_substr($passage_title, 0, 191),
                'text' => $passage_text,
            );
            if ($group_key !== '') {
                $existing_passage = $this->onlineexam_model->getWorkflowPassageDefinition($onlineexam_id, $group_key, $editing_assignment_id ?: null);
                if ($existing_passage && (
                    trim((string) $existing_passage['title']) !== $passage['title']
                    || trim((string) $existing_passage['text']) !== trim((string) $passage['text'])
                )) {
                    $errors[] = 'This passage group already exists with different text. Reuse the same title and text or choose another group key.';
                }
            }
        }
        if ($paper['paper_type'] === 'objective' && $question_type === 'long_answer') {
            $errors[] = 'Theory/long-answer questions must be authored in a Theory paper.';
        }

        $question_text = $this->workflowSanitizeAuthoredText($this->input->post('question_text'));
        if (trim(strip_tags($question_text)) === '') {
            $errors[] = 'Question text is required.';
        }
        if (mb_strlen($question_text) > 30000) {
            $errors[] = 'Question text is too long.';
        }

        $marks = (float) $this->input->post('marks');
        $negative_marks = (float) $this->input->post('neg_marks');
        if ($marks <= 0 || $marks > 10000) {
            $errors[] = 'Marks must be greater than zero and no more than 10,000.';
        }
        if ($negative_marks < 0 || $negative_marks > $marks) {
            $errors[] = 'Negative marks must be between zero and the question mark.';
        }

        $options = array();
        $correct = null;
        $schema = array('version' => 1, 'input' => $question_type);
        $option_types = array('singlechoice', 'multichoice');
        if (in_array($question_type, $option_types, true)) {
            $option_lines = $this->workflowAuthoredLines($this->input->post('answer_options'), 20, false);
            if (count($option_lines) < 2) {
                $errors[] = 'Choice questions require at least two options, one option per line.';
            }
            $seen_options = array();
            foreach ($option_lines as $index => $label) {
                $normalized_label = mb_strtolower($label);
                if (isset($seen_options[$normalized_label])) {
                    $errors[] = 'Choice option labels must be unique.';
                    break;
                }
                $seen_options[$normalized_label] = true;
                $options['opt_' . ($index + 1)] = mb_substr($label, 0, 1000);
            }
            $correct_tokens = preg_split('/[\s,]+/', trim((string) $this->input->post('correct_choice')), -1, PREG_SPLIT_NO_EMPTY);
            $correct_choices = array();
            foreach ($correct_tokens as $token) {
                $choice_index = $this->workflowChoiceIndex($token);
                if ($choice_index < 1 || $choice_index > count($option_lines)) {
                    $errors[] = 'Correct choices must refer to an existing option number.';
                    continue;
                }
                $correct_choices[] = 'opt_' . $choice_index;
            }
            $correct_choices = array_values(array_unique($correct_choices));
            if ($question_type === 'singlechoice') {
                if (count($correct_choices) !== 1) {
                    $errors[] = 'Single-choice questions require exactly one correct option.';
                }
                $correct = isset($correct_choices[0]) ? $correct_choices[0] : null;
                $schema['selection_limit'] = 1;
            } else {
                if (empty($correct_choices)) {
                    $errors[] = 'Multiple-choice questions require at least one correct option.';
                }
                $correct = $correct_choices;
                $schema['selection_limit'] = count($correct_choices);
            }
        } elseif ($question_type === 'true_false') {
            $correct = strtolower(trim((string) $this->input->post('true_false_answer')));
            if (!in_array($correct, array('true', 'false'), true)) {
                $errors[] = 'Choose True or False as the correct answer.';
            }
            $options = array('true' => 'True', 'false' => 'False');
        } elseif ($question_type === 'short_answer') {
            $correct = $this->workflowAuthoredLines($this->input->post('accepted_answers'), 30);
            if (empty($correct)) {
                $errors[] = 'Provide at least one accepted short answer.';
            }
            $schema['case_sensitive'] = false;
            $schema['accepted_answer_count'] = count($correct);
        } elseif ($question_type === 'numeric') {
            $numeric_value = trim((string) $this->input->post('numeric_answer'));
            $numeric_tolerance = trim((string) $this->input->post('numeric_tolerance'));
            if (!is_numeric($numeric_value)) {
                $errors[] = 'The numeric answer must be a number.';
            }
            if ($numeric_tolerance === '' || !is_numeric($numeric_tolerance) || (float) $numeric_tolerance < 0) {
                $errors[] = 'Numeric tolerance must be zero or a positive number.';
            }
            $correct = array('value' => (float) $numeric_value, 'tolerance' => max(0, (float) $numeric_tolerance));
            $schema['tolerance'] = $correct['tolerance'];
        } elseif ($question_type === 'matching') {
            $matching_lines = $this->workflowAuthoredLines($this->input->post('matching_pairs'), 40, false);
            $left = array();
            $right = array();
            $left_lookup = array();
            $right_lookup = array();
            $correct = array();
            foreach ($matching_lines as $line_number => $line) {
                $parts = preg_split('/\s*(?:=>|->|\|)\s*/u', $line, 2);
                if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                    $errors[] = 'Matching pair ' . ($line_number + 1) . ' must use “Left => Right”.';
                    continue;
                }
                $left_label = mb_substr(trim(strip_tags($parts[0])), 0, 1000);
                $right_label = mb_substr(trim(strip_tags($parts[1])), 0, 1000);
                $left_key = mb_strtolower($left_label);
                if (isset($left_lookup[$left_key])) {
                    $errors[] = 'Each left-hand matching item must be unique.';
                    continue;
                }
                $left_lookup[$left_key] = true;
                $left_id = 'left_' . (count($left) + 1);
                $right_key = mb_strtolower($right_label);
                if (!isset($right_lookup[$right_key])) {
                    $right_id = 'right_' . (count($right) + 1);
                    $right_lookup[$right_key] = $right_id;
                    $right[] = array('id' => $right_id, 'label' => $right_label);
                }
                $left[] = array('id' => $left_id, 'label' => $left_label);
                $correct[$left_id] = $right_lookup[$right_key];
            }
            if (count($left) < 2) {
                $errors[] = 'Matching questions require at least two valid pairs.';
            }
            $options = array('matching_left' => $left, 'matching_right' => $right);
            $schema['pair_count'] = count($left);
        } elseif ($question_type === 'ordering') {
            $items = $this->workflowAuthoredLines($this->input->post('ordering_items'), 40);
            if (count($items) < 2) {
                $errors[] = 'Ordering questions require at least two items in the correct order.';
            }
            $options['dynamic'] = array();
            $correct = array();
            foreach ($items as $index => $item) {
                $item_id = 'item_' . ($index + 1);
                $options['dynamic'][] = array('id' => $item_id, 'option' => mb_substr($item, 0, 1000));
                $correct[] = $item_id;
            }
            $schema['item_count'] = count($items);
        } else {
            $manual_types = array('long_answer');
            if (!in_array($question_type, $manual_types, true)) {
                $errors[] = 'The selected response type is not supported.';
            }
            $schema['manual_marking'] = true;
        }

        $marking_scheme = trim(strip_tags((string) $this->input->post('marking_scheme')));
        if (!empty($schema['manual_marking']) && $marking_scheme === '') {
            $errors[] = 'Manual questions require a marking scheme or rubric.';
        }
        if (mb_strlen($marking_scheme) > 30000) {
            $errors[] = 'The marking scheme is too long.';
        }

        if (!empty($errors)) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . implode('<br>', array_map('html_escape', array_values(array_unique($errors)))) . '</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id . '#author-question');
            return;
        }

        $authoring = array(
            'version' => 1,
            'presentation_type' => $authoring_type,
            'question_type' => $question_type,
            'options' => $options,
            'correct_answer' => $correct,
            'response_schema' => $schema,
            'passage' => $passage,
        );
        $authoring_json = json_encode($authoring, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($authoring_json === false) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">The question contains text that could not be encoded safely.</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id . '#author-question');
            return;
        }
        $section_ids = array_values(array_filter(array_map('intval', (array) $exam->section_ids)));
        $source_section_id = !empty($section_ids) ? $section_ids[0] : 0;
        $class_section = $this->db->select('id')
            ->where('class_id', (int) $exam->class_id)
            ->where('section_id', $source_section_id)
            ->limit(1)
            ->get('class_sections')->row_array();
        $legacy_options = array_values($options);
        if (!in_array($question_type, $option_types, true)) {
            $legacy_options = array();
        }
        $source = array(
            'staff_id' => (int) $this->customlib->getStaffID(),
            'subject_id' => (int) $exam->subject_id,
            'question_type' => $question_type,
            // The shared question bank still requires its legacy level field;
            // compact Online Examination authoring no longer exposes difficulty.
            'level' => 'medium',
            'class_id' => (int) $exam->class_id,
            'section_id' => $source_section_id,
            'class_section_id' => !empty($class_section) ? (int) $class_section['id'] : null,
            'question' => $question_text,
            'opt_a' => isset($legacy_options[0]) ? $legacy_options[0] : '',
            'opt_b' => isset($legacy_options[1]) ? $legacy_options[1] : '',
            'opt_c' => isset($legacy_options[2]) ? $legacy_options[2] : '',
            'opt_d' => isset($legacy_options[3]) ? $legacy_options[3] : '',
            'opt_e' => isset($legacy_options[4]) ? $legacy_options[4] : '',
            'correct' => is_array($correct) ? json_encode($correct) : (string) $correct,
        );
        $assignment = array(
            'onlineexam_id' => $onlineexam_id,
            'paper_id' => $paper_id,
            'paper_section_id' => $paper_section_id ?: null,
            'marks' => round($marks, 2),
            'neg_marks' => $exam->is_neg_marking && $paper['paper_type'] === 'objective' && !empty($correct) ? round($negative_marks, 2) : 0,
            'display_order' => max(0, (int) $this->input->post('display_order')),
            'is_compulsory' => $this->input->post('is_compulsory') ? 1 : 0,
            'marking_scheme' => $marking_scheme,
            'authoring_json' => $authoring_json,
        );
        $saved = $editing_assignment_id > 0
            ? $this->onlineexam_model->reviseWorkflowAuthoredQuestion($editing_assignment_id, $onlineexam_id, $source, $assignment)
            : $this->onlineexam_model->createWorkflowAuthoredQuestion($source, $assignment);
        if (!$saved) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">The structured question could not be saved.</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id . '#author-question');
            return;
        }

        $this->onlineexam_model->auditWorkflow(
            $onlineexam_id,
            $this->customlib->getStaffID(),
            $editing_assignment_id > 0 ? 'revise_authored_question' : 'author_question',
            'onlineexam_questions',
            $saved['assignment_id'],
            $editing_question ? array(
                'question_id' => (int) $editing_question['question_id'],
                'paper_id' => (int) $editing_question['paper_id'],
                'paper_section_id' => empty($editing_question['paper_section_id']) ? null : (int) $editing_question['paper_section_id'],
                'question_type' => $editing_question['source_question_type'],
            ) : null,
            array('question_id' => $saved['question_id'], 'paper_id' => $paper_id, 'paper_section_id' => $paper_section_id, 'question_type' => $question_type, 'passage_group_key' => $passage ? $passage['group_key'] : null)
        );
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Structured question ' . ($editing_assignment_id > 0 ? 'updated' : 'created and assigned to the paper') . '.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id . '#author-question');
    }

    public function workflowQuestionDelete()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttemptsForRevision($onlineexam_id, $exam->revision)) {
            echo json_encode(array('status' => 0, 'message' => 'This assessment is frozen.'));
            return;
        }
        $assignment_id = (int) $this->input->post('onlineexam_question_id');
        $deleted = $this->onlineexam_model->removeWorkflowQuestion($assignment_id, $onlineexam_id);
        if ($deleted) {
            $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'remove_question', 'onlineexam_questions', $assignment_id);
        }
        echo json_encode(array('status' => $deleted ? 1 : 0, 'message' => $deleted ? 'Question removed.' : 'Question could not be removed.'));
    }

    public function britishProfileSave()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $this->retiredOnlineexamAction('Historical British outcome assessments are read-only.');
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        if (!$exam || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->result_adapter !== 'british_outcome' || $exam->lifecycle_status !== 'draft') {
            show_error('This British outcome profile cannot be edited.', 409);
        }
        $mode = $this->input->post('profile_mode');
        $profile = array('mode' => $mode);
        if ($mode === 'thresholds') {
            $minimums = (array) $this->input->post('outcome_min');
            $maximums = (array) $this->input->post('outcome_max');
            $profile['outcomes'] = array();
            foreach (array('Emerging', 'Expected', 'Exceeding') as $outcome) {
                $profile['outcomes'][] = array(
                    'value' => $outcome,
                    'min'   => isset($minimums[$outcome]) ? (float) $minimums[$outcome] : -1,
                    'max'   => isset($maximums[$outcome]) ? (float) $maximums[$outcome] : -1,
                );
            }
        }
        $this->load->library('onlineexam_scoring');
        $validation = $this->onlineexam_scoring->validateBritishProfile($profile);
        if (!$validation['valid']) {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . html_escape($validation['error']) . '</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id);
            return;
        }

        $profile_id = $this->onlineexam_model->saveWorkflowResultProfile(array(
            'onlineexam_id'       => $onlineexam_id,
            'adapter'             => 'british_outcome',
            'name'                => 'British outcome conversion',
            'configuration_json'  => json_encode($profile),
            'is_active'           => 1,
            'created_by'          => (int) $this->customlib->getStaffID(),
        ));
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'save_result_profile', 'onlineexam_result_profiles', $profile_id, null, $profile);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">British outcome profile saved.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function kindergartenMappingSave()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $paper_id = (int) $this->input->post('paper_id');
        $paper_section_id = (int) $this->input->post('paper_section_id');
        $concept_id = (int) $this->input->post('concept_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        $paper = $this->onlineexam_model->getWorkflowPaper($paper_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$paper || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->result_adapter !== 'kindergarten_concept' || $exam->lifecycle_status !== 'draft' || (int) $paper['onlineexam_id'] !== $onlineexam_id) {
            show_error('This Kindergarten mapping cannot be edited.', 409);
        }
        if ($paper_section_id) {
            $section = $this->onlineexam_model->getWorkflowPaperSection($paper_section_id);
            if (!$section || (int) $section['paper_id'] !== $paper_id) {
                show_error('The selected paper section is invalid.', 400);
            }
            if ($paper['delivery_mode'] === 'paper') {
                show_error('Paper-delivered work has one aggregate manual score. Map the whole paper, not an individual section.', 400);
            }
        }

        $option = null;
        foreach ($this->onlineexam_model->getKindergartenMappingOptions($exam->class_id, $exam->subject_id) as $candidate) {
            if ((int) $candidate['concept_id'] === $concept_id) {
                $option = $candidate;
                break;
            }
        }
        if (!$option) {
            show_error('The selected concept is not assigned to this class and subject.', 400);
        }

        $profile_json = null;
        if ($this->input->post('conversion_mode') === 'thresholds') {
            $minimums = array_values((array) $this->input->post('label_min'));
            $maximums = array_values((array) $this->input->post('label_max'));
            $labels = array();
            $label_count = (int) $option['num_result_labels'];
            $valid = count($minimums) === $label_count && count($maximums) === $label_count;
            for ($index = 0; $index < $label_count; $index++) {
                $minimum = isset($minimums[$index]) ? (float) $minimums[$index] : -1;
                $maximum = isset($maximums[$index]) ? (float) $maximums[$index] : -1;
                if ($minimum < 0 || $maximum > 100 || $minimum > $maximum) {
                    $valid = false;
                }
                if ($index === 0 && abs($minimum) > 0.001) {
                    $valid = false;
                }
                if ($index > 0 && (abs($minimum - ((float) $maximums[$index - 1] + 0.01)) > 0.011)) {
                    $valid = false;
                }
                $labels[] = array('index' => $index, 'min' => $minimum, 'max' => $maximum);
            }
            $last_label = empty($labels) ? null : $labels[count($labels) - 1];
            if (empty($last_label) || abs((float) $last_label['max'] - 100) > 0.001) {
                $valid = false;
            }
            if (!$valid) {
                $this->session->set_flashdata('msg', '<div class="alert alert-danger">Kindergarten outcome ranges must cover 0 through 100 in label order without gaps or overlaps.</div>');
                redirect('admin/onlineexam/builder/' . $onlineexam_id);
                return;
            }
            $profile_json = json_encode(array('labels' => $labels));
        } else {
            $this->session->set_flashdata('msg', '<div class="alert alert-danger">Configure percentage thresholds for every Kindergarten concept mapping.</div>');
            redirect('admin/onlineexam/builder/' . $onlineexam_id);
            return;
        }

        $mapping_id = $this->onlineexam_model->saveKindergartenMapping(array(
            'onlineexam_id'          => $onlineexam_id,
            'paper_id'               => $paper_id,
            'paper_section_id'       => $paper_section_id ?: null,
            'assessment_id'          => (int) $option['assessment_id'],
            'assessment_subject_id'  => (int) $option['assessment_subject_id'],
            'subject_id'             => (int) $option['subject_id'],
            'concept_id'             => $concept_id,
            'concept_stable_key'     => $option['concept_stable_key'],
            'outcome_profile_json'   => $profile_json,
        ));
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'save_kindergarten_mapping', 'onlineexam_kindergarten_mappings', $mapping_id, null, array('concept_id' => $concept_id, 'paper_id' => $paper_id));
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Kindergarten concept mapping saved.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function kindergartenMappingDelete()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->requireWorkflowCsrf();
        $onlineexam_id = (int) $this->input->post('onlineexam_id');
        $exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
        if (!$exam || !$this->compactAssessmentIsExecutable($exam, true) || !$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id) || $exam->lifecycle_status !== 'draft') {
            show_error('This mapping cannot be removed.', 409);
        }
        $mapping_id = (int) $this->input->post('mapping_id');
        $this->onlineexam_model->removeKindergartenMapping($mapping_id, $onlineexam_id);
        $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'delete_kindergarten_mapping', 'onlineexam_kindergarten_mappings', $mapping_id);
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Kindergarten concept mapping removed.</div>');
        redirect('admin/onlineexam/builder/' . $onlineexam_id);
    }

    public function lifecycle($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_error('Method not allowed.', 405);
        }
        $this->requireWorkflowCsrf();
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        $this->requireCompactAssessment($exam, true);
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }
        $action = $this->input->post('workflow_action');
        if ($action === 'publish') {
            $result = $this->onlineexamworkflow_model->publishAssessment($exam->id, (int) $this->customlib->getStaffID());
            if ($result['success']) {
                $this->session->set_flashdata('msg', '<div class="alert alert-success">Assessment revision frozen and published. Official result-card publication remains separate.</div>');
            } else {
                $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . implode('<br>', array_map('html_escape', $result['errors'])) . '</div>');
            }
        } elseif ($action === 'new_revision') {
            $result = $this->onlineexamworkflow_model->beginNewRevision($exam->id, (int) $this->customlib->getStaffID());
            $this->session->set_flashdata('msg', $result['success']
                ? '<div class="alert alert-success">A new editable revision has been opened. Earlier snapshots and attempts remain unchanged.</div>'
                : '<div class="alert alert-danger">' . implode('<br>', array_map('html_escape', $result['errors'])) . '</div>');
        } else {
            show_error('Invalid lifecycle action.', 400);
        }
        redirect('admin/onlineexam/builder/' . $exam->id);
    }

    /** Release online score feedback without publishing an official report card. */
    public function feedback($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_error('Method not allowed.', 405);
        }
        $this->requireWorkflowCsrf();
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        $this->requireCompactAssessment($exam, false);
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }
        $status = $this->input->post('feedback_action') === 'release' ? 'released' : 'hidden';
        $before = array('feedback_status' => $exam->feedback_status);
        $this->db->where('id', (int) $exam->id)->where('workflow_version', 2)->update('onlineexam', array('feedback_status' => $status));
        $this->onlineexam_model->auditWorkflow($exam->id, $this->customlib->getStaffID(), 'set_feedback_status', 'onlineexam', $exam->id, $before, array('feedback_status' => $status));
        $message = $status === 'released'
            ? 'Online examination feedback is now visible to candidates. The official report card remains unpublished until the existing result-publication workflow is used.'
            : 'Online examination feedback is hidden from candidates.';
        $this->session->set_flashdata('msg', '<div class="alert alert-success">' . html_escape($message) . '</div>');
        redirect('admin/onlineexam/builder/' . $exam->id);
    }

    public function workflowDelete($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_delete')) {
            access_denied();
        }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_error('Method not allowed.', 405);
        }
        $this->requireWorkflowCsrf();
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        $this->requireCompactAssessment($exam, true);
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }
        if ($exam->lifecycle_status !== 'draft' || $this->onlineexam_model->hasWorkflowAttempts($exam->id)) {
            show_error('Only an unattempted draft academic assessment can be deleted.', 409);
        }
        $this->onlineexam_model->auditWorkflow($exam->id, $this->customlib->getStaffID(), 'delete_assessment', 'onlineexam', $exam->id, $exam, null);
        if (!$this->onlineexam_model->removeWorkflowDraft($exam->id)) {
            show_error('The draft assessment could not be deleted.', 500);
        }
        $this->session->set_flashdata('msg', '<div class="alert alert-success">Draft academic assessment deleted.</div>');
        redirect('admin/onlineexam');
    }

    public function printpaper($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $this->retiredOnlineexamAction('Printed/offline Online Examination papers are retired. Use the CBT paper view.');
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }
        $data = array(
            'exam'      => $exam,
            'papers'    => $this->onlineexam_model->getWorkflowPrintablePapers($exam->id, $exam->revision),
            'questions' => $this->onlineexam_model->getWorkflowPrintableQuestions($exam->id, $exam->revision),
            'setting'   => $this->sch_setting_detail,
        );
        foreach ($data['questions'] as $key => $question) {
            $data['questions'][$key]['question_text'] = $this->security->xss_clean($question['question_text']);
            if (isset($question['options_json'])) {
                $options = (array) json_decode($question['options_json'], true);
                if ($question['question_type'] === 'matching' && !empty($options['matching_left']) && !empty($options['matching_right'])) {
                    $print_options = array();
                    foreach ($options['matching_left'] as $left_index => $left_item) {
                        $print_options[(string) ($left_index + 1)] = $left_item['label'] . '  ____________________';
                    }
                    $matching_right = $options['matching_right'];
                    usort($matching_right, function ($left, $right) use ($question) {
                        return strcmp(hash('sha256', $question['id'] . ':' . $left['id']), hash('sha256', $question['id'] . ':' . $right['id']));
                    });
                    $right_labels = array_map(function ($item) { return $item['label']; }, $matching_right);
                    $print_options['Choices'] = implode('; ', $right_labels);
                    $options = $print_options;
                } elseif (!empty($options['dynamic']) && is_array($options['dynamic'])) {
                    $dynamic_options = $options['dynamic'];
                    if ($question['question_type'] === 'ordering') {
                        usort($dynamic_options, function ($left, $right) use ($question) {
                            return strcmp(hash('sha256', $question['id'] . ':' . $left['id']), hash('sha256', $question['id'] . ':' . $right['id']));
                        });
                    }
                    foreach ($dynamic_options as $dynamic_option) {
                        if (isset($dynamic_option['id'], $dynamic_option['option'])) {
                            $options[(string) $dynamic_option['id']] = $dynamic_option['option'];
                        }
                    }
                    unset($options['dynamic']);
                }
                $data['questions'][$key]['options'] = $options;
            } else {
                $data['questions'][$key]['options'] = array(
                    'A' => $question['opt_a'], 'B' => $question['opt_b'], 'C' => $question['opt_c'],
                    'D' => $question['opt_d'], 'E' => $question['opt_e'],
                );
            }
        }
    }

    private function workflowIdIsAllowed($id, $records)
    {
        foreach ((array) $records as $record) {
            $record_id = is_array($record) ? $record['id'] : $record->id;
            if ((int) $record_id === (int) $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Keep pre-compact workflow-v2 rows visible as history without allowing
     * them into current authoring, roster, lifecycle, or operations routes.
     * Drafts may be incomplete, but every active paper/question they already
     * contain must belong to the compact CBT contract.
     */
    private function compactAssessmentIsExecutable($exam, $allow_incomplete_draft = false)
    {
        if (!$exam) {
            return false;
        }
        $value = function ($field, $default = null) use ($exam) {
            if (is_array($exam)) {
                return array_key_exists($field, $exam) ? $exam[$field] : $default;
            }
            return isset($exam->{$field}) ? $exam->{$field} : $default;
        };
        if ((int) $value('workflow_version', 0) < 2) {
            return false;
        }
        $purpose_adapters = array(
            'ca' => 'standard_component',
            'midterm' => 'standard_component',
            'holiday' => 'holiday_assessment',
            'kindergarten' => 'kindergarten_concept',
        );
        $purpose = (string) $value('purpose', '');
        if (!isset($purpose_adapters[$purpose])
            || $purpose_adapters[$purpose] !== (string) $value('result_adapter', '')) {
            return false;
        }

        $onlineexam_id = (int) $value('id', 0);
        $revision = (int) $value('revision', 0);
        if ($onlineexam_id < 1 || $revision < 1) {
            return false;
        }
        if ((string) $value('lifecycle_status', '') !== 'draft') {
            $this->load->model('onlineexamattempt_model');
            return $this->onlineexamattempt_model->isSupportedAssessmentContext((object) (is_array($exam) ? $exam : get_object_vars($exam)));
        }
        if (!$allow_incomplete_draft) {
            return false;
        }

        $papers = $this->db->select('id, paper_type, delivery_mode')
            ->where('onlineexam_id', $onlineexam_id)
            ->where('is_active', 1)
            ->get('onlineexam_papers')
            ->result_array();
        $paper_types = array();
        foreach ($papers as $paper) {
            if ($paper['delivery_mode'] !== 'cbt'
                || !in_array($paper['paper_type'], array('objective', 'theory'), true)) {
                return false;
            }
            $paper_types[(int) $paper['id']] = $paper['paper_type'];
        }
        if (empty($paper_types)) {
            return true;
        }

        $questions = $this->db->select('oq.paper_id, q.question_type')
            ->from('onlineexam_questions oq')
            ->join('questions q', 'q.id = oq.question_id', 'left')
            ->where('oq.onlineexam_id', $onlineexam_id)
            ->where_in('oq.paper_id', array_keys($paper_types))
            ->get()
            ->result_array();
        foreach ($questions as $question) {
            $question_type = isset($question['question_type']) ? (string) $question['question_type'] : '';
            if (!in_array($question_type, $this->workflowResponseTypes(), true)
                || ($paper_types[(int) $question['paper_id']] === 'objective' && $question_type === 'long_answer')) {
                return false;
            }
        }
        return true;
    }

    private function requireCompactAssessment($exam, $allow_incomplete_draft = false)
    {
        if (!$this->compactAssessmentIsExecutable($exam, $allow_incomplete_draft)) {
            $this->retiredOnlineexamAction('This historical assessment is read-only.');
        }
    }

    /** Stop old executable routes while retaining their database records. */
    private function retiredOnlineexamAction($message = null)
    {
        show_error(
            $message ?: 'This legacy Online Examination action is retired. Historical records remain read-only.',
            410
        );
    }

    private function workflowDateToTimestamp($value)
    {
        if (trim((string) $value) === '') {
            return false;
        }
        $timestamp = $this->customlib->dateTimeformatTwentyfourhour($value, false);
        return $timestamp ? (int) $timestamp : false;
    }

    private function requireWorkflowCsrf()
    {
        $expected = (string) $this->session->userdata('onlineexam_workflow_csrf');
        $received = (string) $this->input->post('onlineexam_workflow_token');
        if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
            show_error('The form expired or failed the security check. Reload the page and try again.', 403);
        }
    }

    /** Question types available only in the localized assessment builder. */
    private function localizedQuestionTypes()
    {
        return array(
            'singlechoice'    => 'MCQ / Single choice',
            'multichoice'     => 'Multiple choice',
            'true_false'      => 'True / False',
            'short_answer'    => 'Short answer',
            'numeric'         => 'Numeric (with tolerance)',
            'matching'        => 'Matching',
            'ordering'        => 'Ordering',
            'grouped_passage' => 'Grouped passage question',
            'long_answer'     => 'Theory / Long answer',
        );
    }

    private function workflowResponseTypes()
    {
        return array(
            'singlechoice', 'multichoice', 'true_false', 'short_answer',
            'numeric', 'matching', 'ordering', 'long_answer'
        );
    }

    private function workflowSanitizeAuthoredText($value)
    {
        $value = $this->security->xss_clean((string) $value);
        return trim(strip_tags($value, '<p><br><strong><b><em><i><u><ol><ul><li><sub><sup>'));
    }

    private function workflowAuthoredLines($value, $limit, $unique = true)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);
        $result = array();
        $seen = array();
        foreach ($lines as $line) {
            $line = trim(strip_tags($this->security->xss_clean($line)));
            if ($line === '') {
                continue;
            }
            $line = mb_substr($line, 0, 2000);
            $key = mb_strtolower($line);
            if ($unique && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $line;
            if (count($result) >= (int) $limit) {
                break;
            }
        }
        return $result;
    }

    private function workflowChoiceIndex($token)
    {
        $token = strtolower(trim((string) $token));
        if (preg_match('/^opt_(\d+)$/', $token, $match) || preg_match('/^(\d+)$/', $token, $match)) {
            return (int) $match[1];
        }
        if (preg_match('/^[a-z]$/', $token)) {
            return ord($token) - ord('a') + 1;
        }
        return 0;
    }

    private function workflowTeacherHasAssignment($class_id, $section_ids, $subject_id, $session_id)
    {
        $userdata = $this->customlib->getUserData();
        if (empty($userdata['role_id']) || (int) $userdata['role_id'] !== 2) {
            return true;
        }
        $section_ids = array_values(array_unique(array_filter(array_map('intval', (array) $section_ids))));
        if (empty($section_ids)) {
            return false;
        }
        $assigned = $this->workflowTeacherAssignedSectionIds($class_id, $subject_id, $session_id, $section_ids);
        return count($assigned) === count($section_ids);
    }

    private function workflowTeacherAssignedSectionIds($class_id, $subject_id, $session_id, $section_ids = array())
    {
        $this->db->select('class_sections.section_id');
        $this->db->from('teacher_subjects');
        $this->db->join('class_sections', 'class_sections.id=teacher_subjects.class_section_id');
        $this->db->where('teacher_subjects.teacher_id', (int) $this->customlib->getStaffID());
        $this->db->where('teacher_subjects.subject_id', (int) $subject_id);
        $this->db->where('teacher_subjects.session_id', (int) $session_id);
        $this->db->where('class_sections.class_id', (int) $class_id);
        $section_ids = array_values(array_unique(array_filter(array_map('intval', (array) $section_ids))));
        if (!empty($section_ids)) {
            $this->db->where_in('class_sections.section_id', $section_ids);
        }
        $this->db->group_by('class_sections.section_id');
        $assigned = $this->db->get()->result_array();
        return array_values(array_unique(array_map('intval', array_column($assigned, 'section_id'))));
    }

    /** Operational scope passed to the v2 domain model on every read/write. */
    private function workflowOperationScope($exam)
    {
        $scope = array(
            'allowed_onlineexam_ids' => array((int) $exam->id),
            'section_ids'            => array_values(array_unique(array_map('intval', (array) $exam->section_ids))),
        );
        $userdata = $this->customlib->getUserData();
        if (!empty($userdata['role_id']) && (int) $userdata['role_id'] === 2) {
            $scope['enforce_assignment'] = true;
            $scope['staff_id'] = (int) $this->customlib->getStaffID();
        } else {
            $scope['bypass_assignment'] = true;
        }
        return $scope;
    }

    private function workflowOperationsExam($id, $permission = 'can_view')
    {
        if (!$this->rbac->hasPrivilege('online_examination', $permission)) {
            access_denied();
        }
        $exam = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$exam) {
            show_404();
        }
        $this->requireCompactAssessment($exam, false);
        if (!$this->workflowTeacherHasAssignment($exam->class_id, $exam->section_ids, $exam->subject_id, $exam->session_id)) {
            access_denied();
        }
        return $exam;
    }

    private function workflowOperationPost($id)
    {
        if ($this->input->server('REQUEST_METHOD') !== 'POST') {
            show_error('Method not allowed.', 405);
        }
        $this->requireWorkflowCsrf();
        return $this->workflowOperationsExam($id, 'can_edit');
    }

    private function workflowOperationFlash($result, $success_message)
    {
        if (!empty($result['success'])) {
            $message = $success_message;
            if (!empty($result['requires_conflict_resolution'])) {
                $message .= ' The attempt was finalized, but automatic posting is held for conflict resolution.';
                $this->session->set_flashdata('msg', '<div class="alert alert-warning">' . html_escape($message) . '</div>');
                return;
            }
            if (!empty($result['warnings'])) {
                $message .= ' ' . implode(' ', (array) $result['warnings']);
                $this->session->set_flashdata('msg', '<div class="alert alert-warning">' . html_escape($message) . '</div>');
                return;
            }
            if (!empty($result['attempt_finalized'])) {
                $message .= ' The attempt was finalized and its configured result was posted automatically.';
            }
            if (!empty($result['idempotent'])) {
                $message .= ' No duplicate record was created.';
            }
            $this->session->set_flashdata('msg', '<div class="alert alert-success">' . html_escape($message) . '</div>');
            return;
        }
        $errors = isset($result['errors']) ? (array) $result['errors'] : array();
        if (empty($errors) && !empty($result['message'])) {
            $errors[] = $result['message'];
        }
        if (empty($errors) && !empty($result['reason'])) {
            $errors[] = $result['reason'];
        }
        if (empty($errors)) {
            $errors[] = 'The requested operation could not be completed.';
        }
        $this->session->set_flashdata('msg', '<div class="alert alert-danger">' . implode('<br>', array_map('html_escape', $errors)) . '</div>');
    }

    /** Invigilation, attendance, accommodations, incidents and posting state. */
    public function operations($id)
    {
        $exam = $this->workflowOperationsExam($id);
        $scope = $this->workflowOperationScope($exam);
        $dashboard = $this->onlineexamoperations_model->dashboard($exam->id, $scope);
        $candidates = $this->onlineexamoperations_model->listCandidates($exam->id, array('limit' => 1000), $scope);
        $attempts = $this->onlineexamoperations_model->listAttempts($exam->id, array('limit' => 1000), $scope);
        if (empty($dashboard['success']) || empty($candidates['success']) || empty($attempts['success'])) {
            show_error('The assessment operations dashboard could not be loaded for this account.', 403);
        }

        $incidents = $this->db
            ->select("i.*, CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, s.admission_no", false)
            ->from('onlineexam_incidents i')
            ->join('onlineexam_students os', 'os.id = i.onlineexam_student_id', 'left')
            ->join('student_session ss', 'ss.id = os.student_session_id', 'left')
            ->join('students s', 's.id = ss.student_id', 'left')
            ->where('i.onlineexam_id', (int) $exam->id)
            ->order_by('i.status', 'ASC')
            ->order_by('i.created_at', 'DESC')
            ->get()
            ->result_array();
        $sync_rows = $this->db
            ->select("rs.*, a.attempt_no, CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, s.admission_no", false)
            ->from('onlineexam_result_sync rs')
            ->join('onlineexam_candidate_attempts a', 'a.id = rs.attempt_id')
            ->join('onlineexam_students os', 'os.id = a.onlineexam_student_id')
            ->join('student_session ss', 'ss.id = os.student_session_id')
            ->join('students s', 's.id = ss.student_id')
            ->where('rs.onlineexam_id', (int) $exam->id)
            ->order_by('rs.updated_at', 'DESC')
            ->get()
            ->result_array();

        $data = array(
            'exam'          => $exam,
            'dashboard'     => $dashboard,
            'candidates'    => $candidates['rows'],
            'attempts'      => $attempts['rows'],
            'incidents'     => $incidents,
            'sync_rows'     => $sync_rows,
            'workflow_csrf' => $this->session->userdata('onlineexam_workflow_csrf'),
        );
        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/operations', $data);
        $this->load->view('layout/footer', $data);
    }

    public function analysis($id)
    {
        $exam = $this->workflowOperationsExam($id);
        $analysis = $this->onlineexamoperations_model->assessmentAnalysis(
            $exam->id,
            $this->workflowOperationScope($exam)
        );
        if (empty($analysis['success'])) {
            show_error(!empty($analysis['message']) ? $analysis['message'] : 'Analysis is unavailable.', 403);
        }
        $data = array('exam' => $exam, 'analysis' => $analysis);
        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/analysis_v2', $data);
        $this->load->view('layout/footer', $data);
    }

    public function attemptmarking($id, $attempt_id)
    {
        $exam = $this->workflowOperationsExam($id);
        $detail = $this->onlineexamoperations_model->attemptMarkingDetail(
            $exam->id,
            (int) $attempt_id,
            $this->workflowOperationScope($exam)
        );
        if (empty($detail['success'])) {
            $this->workflowOperationFlash($detail, '');
            redirect('admin/onlineexam/operations/' . $exam->id);
            return;
        }
        $student = $this->db
            ->select("s.admission_no, CONCAT_WS(' ', s.firstname, s.middlename, s.lastname) AS student_name, sec.section", false)
            ->from('student_session ss')
            ->join('students s', 's.id = ss.student_id')
            ->join('sections sec', 'sec.id = ss.section_id')
            ->where('ss.id', (int) $detail['attempt']['student_session_id'])
            ->limit(1)
            ->get()
            ->row_array();
        $british_profile_mode = null;
        if ($exam->result_adapter === 'british_outcome') {
            $snapshot = $this->db->select('configuration_json')
                ->where('onlineexam_id', (int) $exam->id)
                ->where('revision', (int) $detail['attempt']['revision'])
                ->limit(1)
                ->get('onlineexam_revision_snapshots')
                ->row_array();
            $configuration = empty($snapshot['configuration_json']) ? array() : json_decode($snapshot['configuration_json'], true);
            $british_profile_mode = isset($configuration['result']['profile']['mode'])
                ? $configuration['result']['profile']['mode']
                : null;
        }
        $data = array(
            'exam'          => $exam,
            'detail'        => $detail,
            'student'       => $student,
            'british_profile_mode' => $british_profile_mode,
            'workflow_csrf' => $this->session->userdata('onlineexam_workflow_csrf'),
        );
        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/marking_v2', $data);
        $this->load->view('layout/footer', $data);
    }

    /** Authenticated download for a candidate's private assessed attachment. */
    public function answerattachment($id, $answer_id)
    {
        $exam = $this->workflowOperationsExam((int) $id);
        $detail = $this->onlineexamoperations_model->markingDetail(
            $exam->id,
            (int) $answer_id,
            $this->workflowOperationScope($exam)
        );
        if (empty($detail['success']) || empty($detail['answer']['attachment_path'])) {
            show_404();
        }

        $stored_name = basename((string) $detail['answer']['attachment_path']);
        if ($stored_name === '' || !preg_match('/^[a-f0-9]{32,64}\.[a-z0-9]{1,10}$/i', $stored_name)) {
            show_error('The stored attachment reference is invalid.', 404);
        }
        $root = APPPATH . 'writable/onlineexam_answers/';
        $root_real = realpath($root);
        $path_real = realpath($root . $stored_name);
        if ($root_real === false || $path_real === false
            || strpos($path_real, rtrim($root_real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0
            || !is_file($path_real)) {
            show_error('The attachment file is unavailable.', 404);
        }

        $download_name = basename((string) $detail['answer']['attachment_name']);
        $download_name = preg_replace('/[\r\n"\\\\]/', '_', $download_name);
        if ($download_name === '') {
            $download_name = 'answer-attachment';
        }
        $mime = !empty($detail['answer']['attachment_mime'])
            ? preg_replace('/[^a-z0-9.+\/-]/i', '', $detail['answer']['attachment_mime'])
            : 'application/octet-stream';
        header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($path_real));
        header('Cache-Control: private, no-store, max-age=0');
        readfile($path_real);
        exit;
    }

    public function operationEnsureAttempt($id)
    {
        $exam = $this->workflowOperationPost($id);
        $result = $this->onlineexamoperations_model->ensureOfficialAttempt(
            $exam->id,
            (int) $this->input->post('onlineexam_student_id'),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam),
            $this->input->post('create_makeup') ? true : false
        );
        $this->workflowOperationFlash($result, 'Official attempt prepared.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationAccommodation($id)
    {
        $exam = $this->workflowOperationPost($id);
        $result = $this->onlineexamoperations_model->upsertAccommodation(
            $exam->id,
            (int) $this->input->post('onlineexam_student_id'),
            array(
                'extra_time_minutes' => (int) $this->input->post('extra_time_minutes'),
                'makeup_attempts'    => (int) $this->input->post('makeup_attempts'),
                'makeup_expires_at'  => $this->input->post('makeup_expires_at'),
                'notes'              => $this->security->xss_clean($this->input->post('notes')),
            ),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Candidate accommodation saved.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationIncident($id)
    {
        $exam = $this->workflowOperationPost($id);
        $result = $this->onlineexamoperations_model->createIncident(
            $exam->id,
            array(
                'attempt_id'           => (int) $this->input->post('attempt_id') ?: null,
                'onlineexam_student_id' => (int) $this->input->post('onlineexam_student_id') ?: null,
                'incident_type'         => $this->input->post('incident_type'),
                'severity'              => $this->input->post('severity'),
                'details'               => $this->security->xss_clean($this->input->post('details')),
            ),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Incident recorded.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationResolveIncident($id)
    {
        $exam = $this->workflowOperationPost($id);
        $result = $this->onlineexamoperations_model->resolveIncident(
            $exam->id,
            (int) $this->input->post('incident_id'),
            $this->security->xss_clean($this->input->post('resolution_note')),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Incident resolved.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationVoidAttempt($id)
    {
        $exam = $this->workflowOperationPost($id);
        $result = $this->onlineexamoperations_model->voidAttempt(
            $exam->id,
            (int) $this->input->post('attempt_id'),
            $this->security->xss_clean($this->input->post('reason')),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        if (!empty($result['success'])) {
            $this->load->model('onlineexamattempt_model');
            $this->onlineexamattempt_model->refreshAssessmentLifecycle((int) $exam->id);
        }
        $this->workflowOperationFlash($result, 'Attempt voided.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationManualMark($id)
    {
        $exam = $this->workflowOperationPost($id);
        $attempt_id = (int) $this->input->post('attempt_id');
        $result = $this->onlineexamoperations_model->saveManualMark(
            $exam->id,
            (int) $this->input->post('attempt_answer_id'),
            $this->input->post('marks'),
            $this->input->post('rubric_json'),
            $this->security->xss_clean($this->input->post('remark')),
            $this->input->post('mark_status'),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Manual answer mark saved.');
        redirect('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt_id);
    }

    public function operationPaperMark($id)
    {
        $this->retiredOnlineexamAction('Offline paper-score entry is retired. Mark Theory answers individually in the CBT marking screen.');
        $exam = $this->workflowOperationPost($id);
        $attempt_id = (int) $this->input->post('attempt_id');
        $result = $this->onlineexamoperations_model->saveManualPaperScore(
            $exam->id,
            $attempt_id,
            (int) $this->input->post('paper_id'),
            $this->input->post('raw_marks'),
            $this->input->post('rubric_json'),
            $this->security->xss_clean($this->input->post('remark')),
            $this->input->post('mark_status'),
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Paper score saved.');
        redirect('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt_id);
    }

    public function operationFinalize($id)
    {
        $exam = $this->workflowOperationPost($id);
        $attempt_id = (int) $this->input->post('attempt_id');
        $result = $this->onlineexamoperations_model->finalizeManualMarking(
            $exam->id,
            $attempt_id,
            (int) $this->customlib->getStaffID(),
            $this->workflowOperationScope($exam)
        );
        $this->workflowOperationFlash($result, 'Marking finalized and the configured result adapter was synchronized. Report-card publication remains separate.');
        redirect('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt_id);
    }

    public function operationBritishOutcome($id)
    {
        $this->retiredOnlineexamAction('Historical British outcome assessments are read-only.');
        $exam = $this->workflowOperationPost($id);
        if ($exam->result_adapter !== 'british_outcome') {
            show_error('This assessment does not use a British outcome destination.', 409);
        }
        $attempt_id = (int) $this->input->post('attempt_id');
        $outcome = trim((string) $this->input->post('outcome_value'));
        if (!in_array($outcome, array('Emerging', 'Expected', 'Exceeding'), true)) {
            show_error('Select Emerging, Expected or Exceeding.', 422);
        }
        $this->db->trans_begin();
        $attempt = $this->db->query(
            'SELECT * FROM `onlineexam_candidate_attempts` WHERE `id` = '
            . $this->db->escape($attempt_id) . ' AND `onlineexam_id` = '
            . $this->db->escape((int) $exam->id) . ' FOR UPDATE'
        )->row_array();
        if (empty($attempt) || $attempt['status'] === 'voided') {
            $this->db->trans_rollback();
            show_error('The attempt is not available for a British outcome.', 409);
        }
        $this->db->where('id', $attempt_id)->update('onlineexam_candidate_attempts', array(
            'outcome_value' => $outcome,
            'updated_at'    => date('Y-m-d H:i:s'),
        ));
        $this->onlineexam_model->auditWorkflow(
            $exam->id,
            $this->customlib->getStaffID(),
            'select_british_outcome',
            'onlineexam_candidate_attempts',
            $attempt_id,
            array('outcome_value' => $attempt['outcome_value']),
            array('outcome_value' => $outcome)
        );
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            show_error('The British outcome could not be saved.', 500);
        }
        $this->db->trans_commit();

        $result = array('success' => true);
        if ($attempt['status'] === 'completed') {
            $this->load->model('onlineexamresultsync_model');
            $result = $this->onlineexamresultsync_model->syncCompletedAttempt($attempt_id, (int) $this->customlib->getStaffID());
        }
        $this->workflowOperationFlash($result, 'British outcome finalized and synchronized when the attempt is complete.');
        redirect('admin/onlineexam/attemptmarking/' . $exam->id . '/' . $attempt_id);
    }

    public function operationRetrySync($id)
    {
        $exam = $this->workflowOperationPost($id);
        $attempt_id = (int) $this->input->post('attempt_id');
        $attempt = $this->db->select('id, status')
            ->where('id', $attempt_id)
            ->where('onlineexam_id', (int) $exam->id)
            ->limit(1)
            ->get('onlineexam_candidate_attempts')
            ->row_array();
        if (empty($attempt) || $attempt['status'] !== 'completed') {
            show_error('Only a completed attempt in this assessment can be synchronized.', 409);
        }
        $this->load->model('onlineexamresultsync_model');
        $result = $this->onlineexamresultsync_model->syncCompletedAttempt($attempt_id, (int) $this->customlib->getStaffID());
        $this->workflowOperationFlash($result, 'Result synchronization retried.');
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function operationAuthorizeSyncReplacement($id)
    {
        $exam = $this->workflowOperationPost($id);
        $this->load->model('onlineexamresultsync_model');
        $authorization = $this->onlineexamresultsync_model->authorizeConflictReplacement(
            $exam->id,
            (int) $this->input->post('sync_id'),
            (int) $this->customlib->getStaffID(),
            $this->security->xss_clean($this->input->post('override_reason'))
        );
        if (empty($authorization['success'])) {
            $this->workflowOperationFlash($authorization, '');
        } else {
            $sync = $this->onlineexamresultsync_model->syncCompletedAttempt((int) $authorization['attempt_id'], (int) $this->customlib->getStaffID());
            $this->workflowOperationFlash($sync, 'The reviewed destination value was replaced and the action was audited.');
        }
        redirect('admin/onlineexam/operations/' . $exam->id);
    }

    public function evalution($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $workflow_exam = $this->onlineexam_model->getWorkflow((int) $id);
        if ($workflow_exam) {
            $this->requireCompactAssessment($workflow_exam, false);
            if (!$this->workflowTeacherHasAssignment($workflow_exam->class_id, $workflow_exam->section_ids, $workflow_exam->subject_id, $workflow_exam->session_id)) {
                access_denied();
            }
            redirect('admin/onlineexam/operations/' . $workflow_exam->id);
            return;
        }
        $this->retiredOnlineexamAction();

        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $data['id']         = $id;
        $data['title']      = 'student fees';
        $class              = $this->class_model->get();
        $data['classlist']  = $class;
        $workflow_exam      = $this->onlineexam_model->getWorkflow((int) $id);
        $onlineexam         = $workflow_exam ? $workflow_exam : $this->onlineexam_model->get($id);
        if (!$onlineexam) {
            show_404();
        }
        if ($workflow_exam) {
            if (!$this->workflowTeacherHasAssignment($workflow_exam->class_id, $workflow_exam->section_ids, $workflow_exam->subject_id, $workflow_exam->session_id)) {
                access_denied();
            }
            redirect('admin/onlineexam/operations/' . $workflow_exam->id);
            return;
            $class = array_values(array_filter($class, function ($row) use ($workflow_exam) {
                return (int) $row['id'] === (int) $workflow_exam->class_id;
            }));
            $data['classlist'] = $class;
        }
        $data['onlineexam'] = $onlineexam;

        $onlineexam_questions         = $this->onlineexamquestion_model->getByExamNoLimit($id, 'descriptive');
        $data['onlineexam_questions'] = $onlineexam_questions;

        $data['sch_setting'] = $this->sch_setting_detail;
        if ($this->input->server('REQUEST_METHOD') == 'POST') {
            $data['class_id']      = $this->input->post('class_id');
            $data['section_id']    = $this->input->post('section_id');
            $data['onlineexam_id'] = $this->input->post('onlineexam_id');
            $resultlist            = $this->onlineexam_model->searchOnlineExamStudents($data['class_id'], $data['section_id'], $data['onlineexam_id'], $workflow_exam ? $workflow_exam->session_id : null);

            $data['resultlist'] = $resultlist;
        }
        $data['sch_setting'] = $this->sch_setting_detail;

        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/evalution', $data);
        $this->load->view('layout/footer', $data);
    }

    public function getDescQues()
    {
        $this->retiredOnlineexamAction();

        $pag_content    = '';
        $pag_navigation = '';

        if (isset($_POST['data']['page'])) {

            $page = $_POST['data']['page']; /* The page we are currently at */

            $max      = $_POST['data']['post_max']; /* Number of items to display per page */
            $cur_page = $page;
            $page -= 1;
            $per_page     = $max ? $max : 40;
            $previous_btn = true;
            $next_btn     = true;
            $first_btn    = true;
            $last_btn     = true;
            $start        = $page * $per_page;
            $count        = 0;

            $where_search = array();

            /* Check if there is a string inputted on the search box */
            if (!empty($_POST['data']['class_id'])) {
                $where_search['class_id'] = $_POST['data']['class_id'];
            }
            if (!empty($_POST['data']['section_id'])) {
                $where_search['section_id'] = $_POST['data']['section_id'];
            }
            if (!empty($_POST['data']['question_id'])) {
                $where_search['question_id'] = $_POST['data']['question_id'];
            }

            /* Retrieve all the posts */
            $all_items = $this->onlineexamresult_model->getDescriptionRecord($per_page, $start, $where_search, $_POST['data']['onlineexam_id']);

            /* Check if our query returns anything. */
            if ($all_items) {
                $result              = json_decode($all_items);
                $data['result']      = $result;
                $data['total_row']   = $result->total_row;
                $data['start']       = ($cur_page * $per_page) - $per_page + 1;
                $data['upto']        = ($result->total_row < ($cur_page * $per_page)) ? $result->total_row : ($cur_page * $per_page);
                $data['sch_setting'] = $this->sch_setting_detail;
                $pag_content         = $this->load->view('admin/onlineexam/_getDescQues', $data, true);

                /* If the query returns nothing, we throw an error message */
            } else {
                $pag_content = '';

            }
            $no_of_paginations = ceil($result->total_row / $per_page);

            if ($cur_page >= 7) {
                $start_loop = $cur_page - 3;
                if ($no_of_paginations > $cur_page + 3) {
                    $end_loop = $cur_page + 3;
                } else if ($cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6) {
                    $start_loop = $no_of_paginations - 6;
                    $end_loop   = $no_of_paginations;
                } else {
                    $end_loop = $no_of_paginations;
                }
            } else {
                $start_loop = 1;
                if ($no_of_paginations > 7) {
                    $end_loop = 7;
                } else {
                    $end_loop = $no_of_paginations;
                }

            }

            $pag_navigation .= "<ul class='pagination'>";

            if ($first_btn && $cur_page > 1) {
                $pag_navigation .= "<li p='1' class='active_v'><a>" . $this->lang->line('first') . "</a></li>";
            } else if ($first_btn) {

                $pag_navigation .= "<li p='1' class='disabled'><a>" . $this->lang->line('first') . "</a></li>";
            }

            if ($previous_btn && $cur_page > 1) {
                $pre = $cur_page - 1;
                $pag_navigation .= "<li p='$pre' class='active_v'><a>" . $this->lang->line('previous') . "</a></li>";
            } else if ($previous_btn) {
                $pag_navigation .= "<li class='disabled'><a>" . $this->lang->line('previous') . "</a></li>";
            }
            for ($i = $start_loop; $i <= $end_loop; $i++) {

                if ($cur_page == $i) {
                    $pag_navigation .= "<li p='$i' class = 'active' ><a href='#'>{$i}</a></li>";
                } else {
                    $pag_navigation .= "<li p='$i' class='active_v'><a>{$i}</a></li>";
                }

            }

            if ($next_btn && $cur_page < $no_of_paginations) {
                $nex = $cur_page + 1;
                $pag_navigation .= "<li p='$nex' class='active_v'><a>" . $this->lang->line('next') . "</a></li>";
            } else if ($next_btn) {
                $pag_navigation .= "<li class='disabled'><a>" . $this->lang->line('next') . "</a></li>";
            }

            if ($last_btn && $cur_page < $no_of_paginations) {
                $pag_navigation .= "<li p='$no_of_paginations' class='active_v'><a>" . $this->lang->line('last') . "</a></li>";
            } else if ($last_btn) {
                $pag_navigation .= "<li p='$no_of_paginations' class='disabled'><a>" . $this->lang->line('last') . "</a></li>";
            }

            $pag_navigation = $pag_navigation . "</ul>";
        }

        $response = array(
            'content'    => $pag_content,
            'navigation' => $pag_navigation,
        );

        echo json_encode($response);

        exit();
    }

    public function assign($id)
    {
        if (!$this->rbac->hasPrivilege('online_assign_view_student', 'can_view')) {
            access_denied();
        }
        $this->session->set_userdata('top_menu', 'Online_Examinations');
        $this->session->set_userdata('sub_menu', 'Online_Examinations/Onlineexam');
        $data['id']          = $id;
        $data['title']       = 'student fees';
        $class               = $this->class_model->get();
        $workflow_exam       = $this->onlineexam_model->getWorkflow((int) $id);
        if (!$workflow_exam) {
            $this->retiredOnlineexamAction();
        }
        $this->requireCompactAssessment($workflow_exam, true);
        $onlineexam          = $workflow_exam ? $workflow_exam : $this->onlineexam_model->get($id);
        if (!$onlineexam) {
            show_404();
        }
        if ($workflow_exam) {
            if (!$this->workflowTeacherHasAssignment($workflow_exam->class_id, $workflow_exam->section_ids, $workflow_exam->subject_id, $workflow_exam->session_id)) {
                access_denied();
            }
            $class = array_values(array_filter($class, function ($row) use ($workflow_exam) {
                return (int) $row['id'] === (int) $workflow_exam->class_id;
            }));
            $data['workflow_sections'] = $this->onlineexam_model->getClassSectionsForWorkflow($workflow_exam->class_id);
        }
        $data['classlist']   = $class;
        $data['onlineexam']  = $onlineexam;
        $data['sch_setting'] = $this->sch_setting_detail;
        $data['workflow_csrf'] = $this->session->userdata('onlineexam_workflow_csrf');

        if ($this->input->server('REQUEST_METHOD') == "GET") {
            $this->load->view('layout/header', $data);
            $this->load->view('admin/onlineexam/assign', $data);
            $this->load->view('layout/footer', $data);
        } else {
            $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'trim|required|xss_clean');
            if ($this->form_validation->run() == false) {
                $this->load->view('layout/header', $data);
                $this->load->view('admin/onlineexam/assign', $data);
                $this->load->view('layout/footer', $data);
            } else {
                $data['class_id']      = $this->input->post('class_id');
                $data['section_id']    = $this->input->post('section_id');
                $data['onlineexam_id'] = $this->input->post('onlineexam_id');
                if ($workflow_exam && ((int) $data['onlineexam_id'] !== (int) $workflow_exam->id || (int) $data['class_id'] !== (int) $workflow_exam->class_id || !in_array((int) $data['section_id'], array_map('intval', $workflow_exam->section_ids), true))) {
                    show_error('The selected class arm is not assigned to this assessment.', 400);
                }
                $resultlist            = $this->onlineexam_model->searchOnlineExamStudents($data['class_id'], $data['section_id'], $data['onlineexam_id'], $workflow_exam ? $workflow_exam->session_id : null);
                $data['resultlist']    = $resultlist;
                $data['sch_setting']   = $this->sch_setting_detail;
                $this->load->view('layout/header', $data);
                $this->load->view('admin/onlineexam/assign', $data);
                $this->load->view('layout/footer', $data);
            }
        }
    }

    public function addstudent()
    {
        if (!$this->rbac->hasPrivilege('online_assign_view_student', 'can_edit')) {
            access_denied();
        }
        $this->form_validation->set_rules('onlineexam_id', $this->lang->line('exam') . " " . $this->lang->line('id'), 'required|trim|xss_clean');

        if ($this->form_validation->run() == false) {
            $data = array(
                'onlineexam_id' => form_error('onlineexam_id'),
            );
            $array = array('status' => 'fail', 'error' => $data);
            echo json_encode($array);
        } else {

            $array_insert  = array();
            $array_delete  = array();
            $class_id      = $this->input->post('post_class_id');
            $section_id    = $this->input->post('post_section_id');
            $onlineexam_id = $this->input->post('onlineexam_id');
            $workflow_exam = $this->onlineexam_model->getWorkflow($onlineexam_id);
            if (!$workflow_exam) {
                $this->retiredOnlineexamAction();
            }
            if ($workflow_exam) {
                $this->requireWorkflowCsrf();
                $this->requireCompactAssessment($workflow_exam, true);
                if (!$this->workflowTeacherHasAssignment($workflow_exam->class_id, $workflow_exam->section_ids, $workflow_exam->subject_id, $workflow_exam->session_id)) {
                    access_denied();
                }
                if ((int) $workflow_exam->class_id !== (int) $class_id || !in_array((int) $section_id, array_map('intval', $workflow_exam->section_ids), true)) {
                    echo json_encode(array('status' => 'fail', 'error' => array('roster' => 'The class arm is not part of this assessment.')));
                    return;
                }
                if (!in_array($workflow_exam->lifecycle_status, array('draft', 'scheduled', 'published', 'in_progress'), true)) {
                    echo json_encode(array('status' => 'fail', 'error' => array('roster' => 'The candidate roster is locked for this assessment.')));
                    return;
                }
            }
            $resultlist    = $this->onlineexam_model->searchOnlineExamStudents($class_id, $section_id, $onlineexam_id, $workflow_exam ? $workflow_exam->session_id : null);
            if ($workflow_exam) {
                $eligible_student_sessions = array_map(function ($row) {
                    return (int) $row['student_session_id'];
                }, $resultlist);
                $selected_student_sessions = array_values(array_unique(array_map('intval', (array) $this->input->post('students_id'))));
                if (array_diff($selected_student_sessions, $eligible_student_sessions)) {
                    echo json_encode(array('status' => 'fail', 'error' => array('roster' => 'One or more submitted candidates do not belong to this academic roster.')));
                    return;
                }
                $before_roster = $this->db->select('student_session_id, candidate_status, excluded_at, exclusion_reason')
                    ->where('onlineexam_id', (int) $onlineexam_id)
                    ->order_by('student_session_id', 'ASC')
                    ->get('onlineexam_students')
                    ->result_array();
                $sync = $this->onlineexam_model->syncWorkflowCandidates(
                    $onlineexam_id,
                    $eligible_student_sessions,
                    $selected_student_sessions,
                    (int) $this->customlib->getStaffID()
                );
                if ($sync['success']) {
                    $after_roster = $this->db->select('student_session_id, candidate_status, excluded_at, exclusion_reason')
                        ->where('onlineexam_id', (int) $onlineexam_id)
                        ->order_by('student_session_id', 'ASC')
                        ->get('onlineexam_students')
                        ->result_array();
                    $this->onlineexam_model->auditWorkflow(
                        $onlineexam_id,
                        $this->customlib->getStaffID(),
                        'update_candidate_roster',
                        'onlineexam_students',
                        null,
                        $before_roster,
                        $after_roster
                    );
                }
                echo json_encode($sync['success']
                    ? array('status' => 'success', 'error' => '', 'message' => 'Candidate roster saved. Exclusions and late additions were recorded without deleting history.')
                    : array('status' => 'fail', 'error' => array('roster' => $sync['message'])));
                return;
            }
            $all_students  = array();
            if (!empty($resultlist)) {

                foreach ($resultlist as $each_student_key => $each_student_value) {
                    if ($each_student_value['onlineexam_student_session_id'] != 0) {
                        $all_students[] = $each_student_value['onlineexam_student_session_id'];
                    }

                }
            }

            $students_id = $this->input->post('students_id');
            $students    = array();
            if (!isset($students_id)) {
                $students_id = array();
            }
            if (!empty($all_students)) {
                $array_delete = array_diff($all_students, $students_id);

            }
            if (!empty($students_id)) {
                $student_session_array = array();
                foreach ($students_id as $student_key => $student_value) {
                    $student_session_array[] = $student_value;
                }

                $student_array = array_diff($student_session_array, $all_students);
                if (!empty($student_array)) {
                    foreach ($student_array as $insert_key => $insert_value) {
                        $array_insert[] = array(
                            'onlineexam_id'      => $onlineexam_id,
                            'student_session_id' => $insert_value,
                        );
                    }
                }
            }

            $this->onlineexam_model->addStudents($array_insert, $array_delete, $onlineexam_id);
            if ($workflow_exam) {
                $this->onlineexam_model->auditWorkflow($onlineexam_id, $this->customlib->getStaffID(), 'update_candidate_roster', 'onlineexam_students', null, array('assigned' => $all_students), array('assigned' => $students_id));
            }

            $array = array('status' => 'success', 'error' => '', 'message' => $this->lang->line('success_message'));
            echo json_encode($array);
        }
    }

    public function getOnlineExamByID()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_view')) {
            access_denied();
        }
        $this->retiredOnlineexamAction();
        $id = $this->input->post('recordid');

        $question_result = $this->onlineexam_model->get($id);

        echo json_encode(array('status' => 1, 'result' => $question_result));
    }

    public function searchQuestionByExamID()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_view')) {
            access_denied();
        }
        $recordsTotal_flter = "";
        $userdata           = $this->customlib->getUserData();
        $role_id            = $userdata["role_id"];
        $data               = array();
        $pag_content        = '';
        $pag_navigation     = '';
        $page               = max(1, (int) $this->input->post('page'));
        $exam_id            = $this->input->post('exam_id');
        $workflow_exam      = $this->onlineexam_model->getWorkflow((int) $exam_id);
        if (!$workflow_exam) {
            $this->retiredOnlineexamAction();
        }
        $this->requireWorkflowCsrf();
        $this->requireCompactAssessment($workflow_exam, true);
        if (!$this->workflowTeacherHasAssignment($workflow_exam->class_id, $workflow_exam->section_ids, $workflow_exam->subject_id, $workflow_exam->session_id)) {
            access_denied();
        }
        $keyword            = trim((string) $this->input->post('keyword'));
        $question_type      = strtolower(trim((string) $this->input->post('question_type')));
        $class_id           = (int) $this->input->post('class_id');
        $section_id         = (int) $this->input->post('section_id');
        $allowed_question_types = $this->workflowResponseTypes();
        if ($question_type !== '' && !in_array($question_type, $allowed_question_types, true)) {
            return $this->output->set_status_header(422)->set_content_type('application/json')->set_output(json_encode(array(
                'status' => 0,
                'message' => 'The selected question type is not supported by compact CBT.',
            )));
        }

        if (isset($page)) {
            $max      = 100;
            $cur_page = $page;
            $page -= 1;
            $per_page      = $max ? $max : 40;
            $previous_btn  = true;
            $next_btn      = true;
            $first_btn     = true;
            $last_btn      = true;
            $start         = $page * $per_page;
            $where_search  = array();
            $show_from     = ($cur_page * $max) - ($max - 1);
            $show_to       = $cur_page * $max;
            $total_display = 0;

            /* Optional compact bank filters. */
            if ($keyword !== '' || $question_type !== '' || $class_id > 0 || $section_id > 0) {
                $where_search['keyword']        = $keyword;
                $where_search['question_type']  = $question_type;
                $where_search['class_id']       = $class_id;
                $where_search['section_id']     = $section_id;

            }
            $where_search['allowed_question_types'] = $allowed_question_types;
            if ($workflow_exam) {
                $where_search['subject'] = $workflow_exam->subject_id;
                $where_search['class_id'] = $workflow_exam->class_id;
                $where_search['allowed_section_ids'] = array_values(array_unique(array_merge(
                    array(0),
                    array_map('intval', (array) $workflow_exam->section_ids)
                )));
                $data['workflow_exam'] = $workflow_exam;
                $data['workflow_papers'] = $this->onlineexam_model->getWorkflowPapers($workflow_exam->id);
                $data['workflow_editable'] = $workflow_exam->lifecycle_status === 'draft' && !$this->onlineexam_model->hasWorkflowAttemptsForRevision($workflow_exam->id, $workflow_exam->revision);
            }
            if ($role_id == 2) {
                if ($this->sch_setting_detail->class_teacher === 'yes') {
                    $teacher_sections = $this->teacher_model->get_teacherrestricted_modesections(
                        $this->customlib->getStaffID(),
                        (int) $workflow_exam->class_id
                    );
                    $teacher_allowed_sections = array_values(array_unique(array_merge(
                        array(0),
                        array_map('intval', array_column($teacher_sections, 'section_id'))
                    )));
                    $where_search['allowed_section_ids'] = array_values(array_intersect(
                        $where_search['allowed_section_ids'],
                        $teacher_allowed_sections
                    ));
                    if ($this->sch_setting_detail->my_question === '0') {
                        $where_search['question_staff_id'] = (int) $this->customlib->getStaffID();
                    }
                } elseif ($this->sch_setting_detail->my_question === '1') {
                    $where_search['question_staff_id'] = (int) $this->customlib->getStaffID();
                }
            }
            $data['question_type']   = $workflow_exam ? array_diff_key($this->localizedQuestionTypes(), array('grouped_passage' => true)) : $this->config->item('question_type');
            $questionList            = $this->onlineexamquestion_model->getByExamID($exam_id, $per_page, $start, $where_search);

            $dt_data = array();
            foreach ($questionList as $questionList_value) {
                if ($role_id != 2 || $this->question_model->canAccessQuestion((int) $questionList_value->id)) {
                    $dt_data[] = $questionList_value;
                }
            }
            $data['questionList'] = $dt_data;
            $recordsTotal_flter = count($dt_data);

            $count = $this->onlineexamquestion_model->getCountByExamID($exam_id, $where_search);

            $total_display = $recordsTotal_flter;
            /* Check if our query returns anything. */
            if ($count) {
                $pag_content = $this->load->view('admin/onlineexam/_searchQuestionByExamID', $data, true);
                /* If the query returns nothing, we throw an error message */
            }

            $no_of_paginations = ceil($count / $per_page);

            if ($cur_page >= 7) {
                $start_loop = $cur_page - 3;
                if ($no_of_paginations > $cur_page + 3) {
                    $end_loop = $cur_page + 3;
                } else if ($cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6) {
                    $start_loop = $no_of_paginations - 6;
                    $end_loop   = $no_of_paginations;
                } else {
                    $end_loop = $no_of_paginations;
                }
            } else {
                $start_loop = 1;
                if ($no_of_paginations > 7) {
                    $end_loop = 7;
                } else {
                    $end_loop = $no_of_paginations;
                }

            }

            $pag_navigation .= "<ul class='pagination pull-right'>";

            if ($first_btn && $cur_page > 1) {
                $pag_navigation .= "<li p='1' class='activee'><a href='#'><i class='fa fa-angle-double-left'></i></a></li>";
            } else if ($first_btn) {

                $pag_navigation .= "<li p='1' class='disabled'><a href='#'><i class='fa fa-angle-double-left'></i></a></li>";
            }

            if ($previous_btn && $cur_page > 1) {
                $pre = $cur_page - 1;
                $pag_navigation .= "<li p='$pre' class='activee'><a href='#'><i class='fa fa-angle-left'></i></a></li>";
            } else if ($previous_btn) {

                $pag_navigation .= "<li  class='disabled'><a href='#'><i class='fa fa-angle-left'></i></a></li>";
            }
            for ($i = $start_loop; $i <= $end_loop; $i++) {

                if ($cur_page == $i) {

                    $pag_navigation .= "<li p='$i' class='active'><a href='#'>{$i}</a></li>";
                } else {

                    $pag_navigation .= "<li p='$i'  class='activee'><a href='#'>{$i}</a></li>";
                }

            }

            if ($next_btn && $cur_page < $no_of_paginations) {
                $nex = $cur_page + 1;

                $pag_navigation .= "<li p='$nex' class='activee'><a href='#'><i class='fa fa-angle-right'></i></a></li>";
            } else if ($next_btn) {
                $pag_navigation .= "<li class='disabled'><a href='#'><i class='fa fa-angle-right'></i></a></li>";
            }

            if ($last_btn && $cur_page < $no_of_paginations) {
                $pag_navigation .= "<li p='$no_of_paginations'  class='activee'><a href='#'><i class='fa fa-angle-double-right'></i></a></li>";
            } else if ($last_btn) {
                $pag_navigation .= "<li p='$no_of_paginations' class='disabled'><a href='#'><i class='fa fa-angle-double-right'></i></a></li>";
            }

            $pag_navigation = $pag_navigation . "</ul>";
        }

        $response = array(
            'content'       => $pag_content,
            'navigation'    => $pag_navigation,
            'show_from'     => ($total_display <= 0) ? 0 : $show_from,
            'show_to'       => ($show_to > $total_display) ? $total_display : $show_to,
            'total_display' => $total_display,
        );

        echo json_encode($response);

    }

    public function rankgenerate()
    {
      $this->retiredOnlineexamAction();
      $examid=$this->input->post('examid');
      $student_data=$this->onlineexam_model->searchAllOnlineExamStudents($examid);
      $student_question_array=array();
        if(!empty($student_data)){
        foreach ($student_data as $student_key => $student_value) {            
          $student_question_array[$student_value['onlineexam_student_id']] = $this->onlineexamresult_model->getResultByStudent($student_value['onlineexam_student_id'], $examid);
        }
        }
      $data['onlineexam'] = $this->onlineexam_model->get($examid);
      $data['student_question_array']=$student_question_array;
      $data['examid']=$examid;
      $data['student_data']=$student_data;
      $data['sch_setting'] = $this->sch_setting_detail;
      $page = $this->load->view('admin/onlineexam/_rankgenerate', $data, true);
      $array = array('status' => 1, 'page' => $page,'examid'=>$examid, 'message' => $this->lang->line('success_message'));
        echo json_encode($array);
    }

    public function add()
    {
        $this->retiredOnlineexamAction();
        $record_id = (int) $this->input->post('recordid');
        $privilege = $record_id > 0 ? 'can_edit' : 'can_add';
        if (!$this->rbac->hasPrivilege('online_examination', $privilege)) {
            access_denied();
        }
        if ($record_id > 0 && $this->onlineexam_model->getWorkflow($record_id)) {
            show_error('Academic assessments can only be edited through their assessment screen.', 409);
        }
        $this->form_validation->set_rules('exam', $this->lang->line('exam'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('attempt', $this->lang->line('attempt'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('exam_from', $this->lang->line('exam') . " " . $this->lang->line('from'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('exam_to', $this->lang->line('exam') . " " . $this->lang->line('to'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('duration', $this->lang->line('duration'), 'trim|required|callback_validate_duration');
        $this->form_validation->set_rules('description', $this->lang->line('description'), 'trim|required');
        $this->form_validation->set_rules('passing_percentage', $this->lang->line('percentage'), 'trim|required|xss_clean');
        if ($this->form_validation->run() == false) {
            $msg = array(
                'exam'               => form_error('exam'),
                'attempt'            => form_error('attempt'),
                'exam_from'          => form_error('exam_from'),
                'duration'           => form_error('duration'),
                'exam_to'            => form_error('exam_to'),
                'description'        => form_error('description'),
                'passing_percentage' => form_error('passing_percentage'),
            );

            $array = array('status' => 0, 'error' => $msg, 'message' => '');
        } else {
            $is_active          = 0;
            $publish_result     = 0;
            $is_marks_display   = 0;
            $is_neg_marking     = 0;
            $is_random_question = 0;
            $is_quiz            = 0;
            $auto_publish_date  = "";
            if (isset($_POST['is_active'])) {
                $is_active = 1;
            }
            if (isset($_POST['publish_result'])) {
                $publish_result = 1;
            }
            if (isset($_POST['is_marks_display'])) {
                $is_marks_display = 1;
            }
            if (isset($_POST['is_neg_marking'])) {
                $is_neg_marking = 1;
            }
            if (isset($_POST['is_random_question'])) {
                $is_random_question = 1;
            }

            if (isset($_POST['auto_publish_date']) && $_POST['auto_publish_date'] != "") {

                $auto_publish_date = date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('auto_publish_date'), false));
            } else {
                $auto_publish_date = null;
            }

            $insert_data = array(
                'exam'               => $this->input->post('exam'),
                'attempt'            => $this->input->post('attempt'),
                'exam_from'          => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_from'), false)),
                'exam_to'            => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_to'), false)),
                'auto_publish_date'  => $auto_publish_date,
                'duration'           => $this->input->post('duration'),
                'description'        => $this->input->post('description'),
                'session_id'         => $this->setting_model->getCurrentSession(),
                'is_active'          => $is_active,
                'publish_result'     => $publish_result,
                'is_marks_display'   => $is_marks_display,
                'is_neg_marking'     => $is_neg_marking,
                'is_random_question' => $is_random_question,
                'passing_percentage' => $this->input->post('passing_percentage'),
            );
            if (isset($_POST['is_quiz']) && $_POST['is_quiz'] != "") {
                $insert_data['publish_result']    = 0;
                $insert_data['auto_publish_date'] = null;
                $insert_data['is_quiz']           = 1;
            } else {
                $insert_data['is_quiz'] = $is_quiz;
            }

            $id = $this->input->post('recordid');
            if ($id != 0) {
                $insert_data['id'] = $id;
            }

            $this->onlineexam_model->add($insert_data);
            if($id!=0){
                $exam_notification=$this->onlineexam_model->get_msnstatusByexam_id($id);
                if ($is_active == 1 && $exam_notification['publish_exam_notification']==0) {

                $sender_details = array(
                    'exam_id'               => $id,
                    'exam_title'               => $this->input->post('exam'),
                    'attempt'            => $this->input->post('attempt'),
                    'time_duration' => $this->input->post('duration'),
                    'passing_percentage' => $this->input->post('passing_percentage'),
                    'exam_from'          => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_from'), false)),
                    'exam_to'            => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_to'), false)),
                    
                );
    
                $notification_status=$this->mailsmsconf->mailsms('online_examination_publish_exam', $sender_details);
                   
                    $publish_exam_notification['id'] = $id;
                    $publish_exam_notification['publish_exam_notification'] = '1';
                    $this->onlineexam_model->add($publish_exam_notification);
                
               
            }

              if ($publish_result == 1 && $exam_notification['publish_result_notification']==0) {
              
                 $sender_details = array(
                    'exam_id'               => $id,
                    'exam_title'               => $this->input->post('exam'),
                    'attempt'            => $this->input->post('attempt'),
                    'time_duration' => $this->input->post('duration'),
                    'passing_percentage' => $this->input->post('passing_percentage'),
                    'exam_from'          => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_from'), false)),
                    'exam_to'            => date('Y-m-d H:i:s', $this->customlib->dateTimeformatTwentyfourhour($this->input->post('exam_to'), false)),
                    
                );

                $this->mailsmsconf->mailsms('online_examination_publish_result', $sender_details);    
                $publish_result_notification['id'] = $id;
                $publish_result_notification['publish_result_notification'] = '1';
                $this->onlineexam_model->add($publish_result_notification);
              
            }
            }
              
            $array = array('status' => 1, 'error' => '', 'message' => $this->lang->line('success_message'));
        }

        echo json_encode($array);
    }

    public function getRecord($id)
    {
        $this->retiredOnlineexamAction();

        $result            = $this->onlineexam_model->get_result($id);
        $result['options'] = $this->onlineexam_model->get_option($id);
        $result['ans']     = $this->onlineexam_model->get_answer($id);
        echo json_encode($result);
    }

    public function delete($id)
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_delete')) {
            access_denied();
        }
        $this->retiredOnlineexamAction();
        $workflow_exam = $this->onlineexam_model->getWorkflow((int) $id);
        if ($workflow_exam) {
            show_error('Localized academic assessments must be deleted from their protected workflow action.', 409);
        }
        $this->onlineexam_model->remove($id);
        redirect('admin/onlineexam', 'refresh');
    }


    public function saverank()
    {
        $this->retiredOnlineexamAction();

        $this->form_validation->set_rules('row[]', 'row', 'trim|required|xss_clean');
        

        if ($this->form_validation->run() == false) {

            $msg = array(
                'row'    => form_error('row[]'),
            
            );

            $array = array('status' => 0, 'error' => $msg, 'message' => '');
        } else {
            $row=$this->input->post('row');
            $exam_id=$this->input->post('exam_id');
            if ($this->onlineexam_model->getWorkflow((int) $exam_id)) {
                echo json_encode(array('status' => 0, 'error' => array(), 'message' => 'Localized assessments do not use the legacy rank writer.'));
                return;
            }
          
            if(!empty($row)){
                $students=array();
                foreach ($row as $row_key => $row_value) {
                   $students[]=array(
                      'id'=>$row_value,
                      'rank'=>$this->input->post('onlineexam_student_id_'.$row_value)
                  );
                }
        
           $this->onlineexam_model->updateStudentRank($students,$exam_id);
            
            }        
        
            $array = array('status' => 1, 'error' => '', 'message' => $this->lang->line('success_message'));
        }

        echo json_encode($array);
    }

    public function fillmarks()
    {
        if (!$this->rbac->hasPrivilege('online_examination', 'can_edit')) {
            access_denied();
        }
        $this->retiredOnlineexamAction();

        $this->form_validation->set_rules('onlineexam_student_result_id', $this->lang->line('exam'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('fill_mark', $this->lang->line('marks'), 'trim|xss_clean|callback_validate_marks');
        $this->form_validation->set_rules('question_marks', $this->lang->line('question_marks'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) {

            $msg = array(
                'question_id'    => form_error('onlineexam_student_result_id'),
                'fill_mark'      => form_error('fill_mark'),
                'question_marks' => form_error('question_marks'),
            );

            $array = array('status' => 0, 'error' => $msg, 'message' => '');
        } else {
            $insert_data = array(
                'id'     => $this->input->post('onlineexam_student_result_id'),
                'marks'  => $this->input->post('fill_mark'),
                'remark' => $this->input->post('remark'),

            );
            $this->onlineexamresult_model->update($insert_data);
            $array = array('status' => 1, 'error' => '', 'message' => $this->lang->line('success_message'));
        }

        echo json_encode($array);
    }

    public function validate_duration($str)
    {
        if ($this->input->post('duration') != "") {
            if ($this->input->post('duration') != "00:00:00") {
                if (!preg_match('/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $str)) {

                    $this->form_validation->set_message('validate_duration', 'The %s field must be HH:mm:ss');
                    return false;

                }

            } else {
                $this->form_validation->set_message('validate_duration', 'The %s field can not be 00:00:00 ');
                return false;
            }
            return true;
        }
        return true;
    }

    public function validate_marks($str)
    {
        if (($this->input->post('fill_mark') != "") && ($this->input->post('question_marks') != "")) {
            if (preg_match('/^[+-]?([0-9]*[.])?[0-9]+$/', $str)) {
                if ($this->input->post('question_marks') < $this->input->post('fill_mark')) {
                    $this->form_validation->set_message('validate_marks', 'The %s field must be between 0 and ' . $this->input->post('question_marks'));
                    return false;
                }
                return true;
            } else {
                $this->form_validation->set_message('validate_marks', 'The %s field can only contain numbers');
                return false;
            }
        } elseif ($this->input->post('fill_mark') != "") {
            $this->form_validation->set_message('validate_marks', 'The %s field is requiredss');
            return false;
        }
    }

    public function questionAdd()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {
            access_denied();
        }
        $this->retiredOnlineexamAction();
        if ($this->onlineexam_model->getWorkflow((int) $this->input->post('onlineexam_id'))) {
            echo json_encode(array('status' => 0, 'error' => array(), 'message' => 'Use the paper builder to assign questions to an academic assessment.'));
            return;
        }

        $this->form_validation->set_rules('question_id', $this->lang->line('exam'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('onlineexam_id', $this->lang->line('attempt'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('ques_mark', $this->lang->line('marks'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('ques_neg_mark', $this->lang->line('negative_marks'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) {

            $msg = array(
                'question_id'   => form_error('question_id'),
                'onlineexam_id' => form_error('onlineexam_id'),
                'ques_mark'     => form_error('ques_mark'),
                'ques_neg_mark' => form_error('ques_neg_mark'),
            );

            $array = array('status' => 0, 'error' => $msg, 'message' => '');
        } else {
            $insert_data = array(
                'question_id'   => $this->input->post('question_id'),
                'onlineexam_id' => $this->input->post('onlineexam_id'),
                'marks'         => $this->input->post('ques_mark'),
                'neg_marks'     => $this->input->post('ques_neg_mark'),
            );
            $this->onlineexam_model->insertExamQuestion($insert_data);
            $array = array('status' => 1, 'error' => '', 'message' => $this->lang->line('success_message'));
        }

        echo json_encode($array);
    }

    public function deleteExamQuestions()
    {
        if (!$this->rbac->hasPrivilege('add_questions_in_exam', 'can_edit')) {
            access_denied();
        }
        $this->retiredOnlineexamAction();

        $assignment_id = (int) $this->input->post('question_id');
        $assignment = $this->db->select('onlineexam.workflow_version')
            ->from('onlineexam_questions')
            ->join('onlineexam', 'onlineexam.id = onlineexam_questions.onlineexam_id')
            ->where('onlineexam_questions.id', $assignment_id)
            ->limit(1)
            ->get()
            ->row();
        if ($assignment && (int) $assignment->workflow_version >= 2) {
            echo json_encode(array('status' => 0, 'error' => array(), 'message' => 'Use the protected paper builder to remove this question.'));
            return;
        }

        $this->form_validation->set_rules('question_id', 'question', 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) {

            $msg = array(
                'question_id' => form_error('question_id'),
            );

            $array = array('status' => 0, 'error' => $msg, 'message' => '');
        } else {

            $this->onlineexamquestion_model->remove($this->input->post('question_id'));
            $array = array('status' => 1, 'error' => '', 'message' => $this->lang->line('delete_message'));
        }

        echo json_encode($array);
    }

    public function report()
    {

        $this->session->set_userdata('top_menu', 'Reports');
        $this->session->set_userdata('sub_menu', 'Reports/online_examinations');
        $this->session->set_userdata('subsub_menu', 'Reports/online_examinations/online_exam_report');
        $examList            = $this->onlineexam_model->get();
        $data['examList']    = $examList;
        $class               = $this->class_model->get();
        $data['classlist']   = $class;
        $data['sch_setting'] = $this->sch_setting_detail;
   
        $this->load->view('layout/header', $data);
        $this->load->view('admin/onlineexam/report', $data);
        $this->load->view('layout/footer', $data);
       
    }

    public function getstudentresult()
    {
        $onlineexam_student_id      = $this->input->post('recordid');
        $examid                     = $this->input->post('examid');
        $student_session_id         = $this->input->post('student_session_id');
        $data['student_session_id'] = $this->input->post('student_session_id');
        $exam                       = $this->onlineexam_model->get($examid);
        $data['exam']               = $exam;
        $online_exam_validate       = $this->onlineexam_model->examstudentsID($student_session_id, $examid);

        $data['question_result'] = $this->onlineexamresult_model->getResultByStudent($onlineexam_student_id, $examid);
        $data['result_prepare']        = $this->onlineexamresult_model->checkResultPrepare($onlineexam_student_id);
        $data['online_exam_validate']  = $online_exam_validate;
        $questionOpt                   = $this->customlib->getQuesOption();
        $data['questionOpt']           = $questionOpt;
        $data['onlineexam_student_id'] = $onlineexam_student_id;
        $data['question_true_false']   = $this->config->item('question_true_false');

        $print = $this->input->post('print');
        if (isset($print)) {

            $question_result = $this->load->view('admin/onlineexam/_print', $data, true);
        } else {
            $question_result = $this->load->view('admin/onlineexam/_getstudentresult', $data, true);

        }

        echo json_encode(array('status' => 1, 'result' => $question_result));

    }

    public function getExamQuestions()
    {
        $exam_id                  = $this->input->post('recordid');
        $exam                     = $this->onlineexam_model->get($exam_id);
        $data['exam']             = $exam;
        $data['questions']        = $this->onlineexamquestion_model->getExamQuestions($exam_id);
        $data['questionSubjects'] = $this->onlineexamquestion_model->getExamQuestionSubjects($exam_id);
        $data['question_type']    = $this->config->item('question_type');
        $data['question_level']   = $this->config->item('question_level');

        $questionList = $this->load->view('admin/onlineexam/_getexamquestions', $data, true);
        echo json_encode(array('status' => 1, 'result' => $questionList, 'exam' => $exam));
    }
    
    public function downloadattachment($doc)
    {
        $this->retiredOnlineexamAction('Legacy answer attachments are retained as data but direct downloads through this route are retired.');
        $this->load->helper('download');
        $filepath = "./uploads/onlinexam_images/" . $doc;
        $data     = file_get_contents($filepath);
        $name     = $doc;
        force_download($name, $data);
    }

    public function searchloginvalidation()
    {
        $class_id       = $this->input->post('class_id');
        $section_id     = $this->input->post('section_id');
        $exam_id     = $this->input->post('exam_id');

        $this->form_validation->set_rules('class_id', $this->lang->line('class'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('section_id', $this->lang->line('section'), 'trim|required|xss_clean');
         $this->form_validation->set_rules('exam_id', $this->lang->line('exam'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) { 
            $error = array();
            
            $error['class_id'] = form_error('class_id');
            $error['section_id'] = form_error('section_id');
             $error['exam_id'] = form_error('exam_id');
            $array = array('status' => 0, 'error' => $error);
            echo json_encode($array);
        } else {

            $params      = array('class_id' => $class_id, 'section_id' => $section_id, 'exam_id'=>$exam_id);
            $array       = array('status' => 1, 'error' => '', 'params' => $params);
            echo json_encode($array);
        }
    }

    public function dtreportlist()
    {
        
        $exam_id         = $this->input->post('exam_id');
        $class_id        = $this->input->post('class_id');
        $section_id      = $this->input->post('section_id');
        $sch_setting = $this->sch_setting_detail;
        $results         = $this->onlineexamresult_model->getStudentByExam($exam_id, $class_id, $section_id);
        $resultlist      = json_decode($results);
        $dt_data=array();
        
        if (!empty($resultlist->data)) {
            foreach ($resultlist->data as $resultlist_key => $student) { 


                $attempt_bn="";$action="";
                $viewbtn = "<a  href='".base_url()."student/view/".$student->id."'>".$this->customlib->getFullName($student->firstname,$student->middlename,$student->lastname,$sch_setting->middlename,$sch_setting->lastname)."</a>";
                if($student->is_attempted){
                    $attempt_btn= " <i class='fa fa-check-square-o'></i><span style='display:none'>".$this->lang->line('yes')."</span>" ;
                }else{
                     $attempt_btn= " <i class='fa fa-remove'></i><span style='display:none'>".$this->lang->line('no')."</span>" ;
                }
                 $action="<button type='button' class='btn btn-info btn-xs student_result' data-toggle='tooltip' id='load' data-recordid=".$student->onlineexam_student_id." data-student_session_id=".$student->student_session_id." data-examid=".$student->exam_id." data-loading-text='<i class=fa fa-spinner fa-spin></i>'   ><i class='fa fa-eye'></i></button>" ;
             
                $row   = array();
                $row[] = $student->admission_no ;
                $row[] = $viewbtn ;
                $row[] = $student->class . "(" . $student->section . ")" ;
                $row[] = $student->attempt ;
                $row[] = $student->attempt - $student->total_counter;
                $row[] = $attempt_btn;
                $row[] = $action;
                $dt_data[] = $row;  
            }

        }
        $json_data = array(
            "draw"            => intval($resultlist->draw),
            "recordsTotal"    => intval($resultlist->recordsTotal),
            "recordsFiltered" => intval($resultlist->recordsFiltered),
            "data"            => $dt_data,
        );
        echo json_encode($json_data); 
    }

}
