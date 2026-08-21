<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Promotioncriteria extends Admin_Controller
{
    private $tokenSessionKey = 'promotion_criteria_csrf';
    // Pending is generated only by the automatic evaluator. A human override
    // must make an explicit promoted/not-promoted decision.
    private $allowedDecisions = array('promoted', 'not_promoted');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('promotioncriteria_model');

        if (!$this->session->userdata($this->tokenSessionKey)) {
            $this->session->set_userdata($this->tokenSessionKey, $this->newToken());
        }
    }

    public function index()
    {
        $this->requireAnyViewAccess();
        $this->requireReady();
        $this->session->set_userdata('top_menu', 'Academics');
        $this->session->set_userdata('sub_menu', 'promotioncriteria/index');
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $currentSessionId = (int) $this->setting_model->getCurrentSession();
        $sessions = $this->promotioncriteria_model->getSessions();
        if (!$this->promotioncriteria_model->sessionExists($currentSessionId) && !empty($sessions)) {
            $currentSessionId = (int) $sessions[0]['id'];
        }
        $classes = $this->promotioncriteria_model->getClasses();
        $subjects = $this->promotioncriteria_model->getSubjects();
        $classSections = $this->promotioncriteria_model->getClassSections();
        $senior = $this->isSenior();
        $teacher = $this->isTeacher();
        $staffId = (int) $this->customlib->getStaffID();
        $teacherScopes = (!$senior && $teacher)
            ? $this->promotioncriteria_model->getTeacherScopes($staffId)
            : array();

        $tab = strtolower(trim((string) $this->input->get('tab', true)));
        if (!in_array($tab, array('criteria', 'review'), true)) {
            $tab = $this->canViewCriteria() ? 'criteria' : 'review';
        }
        if ($tab === 'criteria' && !$this->canViewCriteria()) {
            $tab = 'review';
        }
        if ($tab === 'review' && !$this->canViewReview()) {
            $tab = 'criteria';
        }

        $criteriaSessionId = $this->positiveInt($this->input->get('criteria_session_id'));
        if ($criteriaSessionId <= 0 || !$this->promotioncriteria_model->sessionExists($criteriaSessionId)) {
            $criteriaSessionId = $currentSessionId;
        }

        $editCriterion = null;
        $editId = $this->positiveInt($this->input->get('edit'));
        if ($editId > 0) {
            if (!$this->rbac->hasPrivilege('manage_promotion_criteria', 'can_edit')) {
                access_denied();
            }
            $editCriterion = $this->promotioncriteria_model->getCriterion($editId);
            if (empty($editCriterion)) {
                show_404();
            }
            $criteriaSessionId = (int) $editCriterion['session_id'];
            $tab = 'criteria';
        }

        $reviewSessionId = $this->positiveInt($this->input->get('session_id'));
        if ($reviewSessionId <= 0 || !$this->promotioncriteria_model->sessionExists($reviewSessionId)) {
            $reviewSessionId = $currentSessionId;
        }
        $reviewClassId = $this->positiveInt($this->input->get('class_id'));
        $reviewSectionId = $this->positiveInt($this->input->get('section_id'));
        $reviewRows = array();
        $reviewError = '';
        $reviewScope = null;

        if ($reviewClassId > 0 || $reviewSectionId > 0) {
            if ($reviewSessionId <= 0 || $reviewClassId <= 0 || $reviewSectionId <= 0) {
                $reviewError = 'Select a session, class, and section to review promotion outcomes.';
            } elseif (!$this->promotioncriteria_model->sessionExists($reviewSessionId)
                || !$this->promotioncriteria_model->classSectionExists($reviewClassId, $reviewSectionId)) {
                $reviewError = 'The selected academic scope is not valid.';
            } elseif (!$this->canAccessScope($reviewSessionId, $reviewClassId, $reviewSectionId)) {
                $reviewError = 'You are not assigned to the selected class and section for this session.';
            } else {
                $reviewScope = $this->promotioncriteria_model->getScopeMeta($reviewSessionId, $reviewClassId, $reviewSectionId);
                $qualitative = $this->promotioncriteria_model->isQualitativeClass($reviewClassId);
                $students = $this->promotioncriteria_model->getReviewStudents($reviewSessionId, $reviewClassId, $reviewSectionId);
                foreach ($students as $student) {
                    $student['automatic'] = $this->promotioncriteria_model->evaluateStudent(
                        (int) $student['id'],
                        $reviewSessionId,
                        $reviewClassId,
                        $reviewSectionId,
                        $qualitative,
                        true
                    );
                    $student['final'] = $this->promotioncriteria_model->evaluateStudent(
                        (int) $student['id'],
                        $reviewSessionId,
                        $reviewClassId,
                        $reviewSectionId,
                        $qualitative,
                        false
                    );
                    $reviewRows[] = $student;
                }
            }
        }

        $data = array(
            'title' => 'Promotion Criteria',
            'tab' => $tab,
            'sessions' => $sessions,
            'classes' => $classes,
            'subjects' => $subjects,
            'class_sections' => $classSections,
            'teacher_scopes' => $teacherScopes,
            'is_senior' => $senior,
            'can_view_criteria' => $this->canViewCriteria(),
            'can_add_criteria' => $this->rbac->hasPrivilege('manage_promotion_criteria', 'can_add'),
            'can_edit_criteria' => $this->rbac->hasPrivilege('manage_promotion_criteria', 'can_edit'),
            'can_view_review' => $this->canViewReview(),
            'can_override' => $this->canOverride(),
            'criteria_session_id' => $criteriaSessionId,
            'criteria_list' => $this->canViewCriteria()
                ? $this->promotioncriteria_model->getCriteriaList($criteriaSessionId)
                : array(),
            'edit_criterion' => $editCriterion,
            'review_session_id' => $reviewSessionId,
            'review_class_id' => $reviewClassId,
            'review_section_id' => $reviewSectionId,
            'review_rows' => $reviewRows,
            'review_error' => $reviewError,
            'review_scope' => $reviewScope,
            'promotion_csrf' => (string) $this->session->userdata($this->tokenSessionKey),
            'promotion_message' => $this->session->flashdata('promotion_message'),
        );

        $this->load->view('layout/header', $data);
        $this->load->view('admin/promotioncriteria/index', $data);
        $this->load->view('layout/footer', $data);
    }

    public function save()
    {
        $this->requireReady();
        $this->requirePostAndToken();

        $criterionId = $this->positiveInt($this->input->post('id'));
        $requiredAction = $criterionId > 0 ? 'can_edit' : 'can_add';
        $this->requireCriteriaPrivilege($requiredAction);

        $existingCriterion = null;
        if ($criterionId > 0) {
            $existingCriterion = $this->promotioncriteria_model->getCriterion($criterionId);
            if (empty($existingCriterion)) {
                show_404();
            }
        }

        $sessionId = $this->positiveInt($this->input->post('session_id'));
        $name = trim((string) $this->input->post('name', true));
        $minimum = $this->validPercentage($this->input->post('minimum_average'));
        $classIds = $this->normalizeIds($this->input->post('class_ids'));
        $subjectIds = $this->normalizeIds($this->input->post('subject_ids'));
        $targetClassInput = $this->input->post('target_class_id', true);
        $targetLabelInput = $this->input->post('target_label', true);
        $subjectMinimumInput = $this->input->post('subject_minimum', true);
        $targetClassInput = is_array($targetClassInput) ? $targetClassInput : array();
        $targetLabelInput = is_array($targetLabelInput) ? $targetLabelInput : array();
        $subjectMinimumInput = is_array($subjectMinimumInput) ? $subjectMinimumInput : array();
        $errors = array();

        if (!$this->promotioncriteria_model->sessionExists($sessionId)) {
            $errors[] = 'Select a valid academic session.';
        }
        if ($existingCriterion && $sessionId !== (int) $existingCriterion['session_id']) {
            $errors[] = 'An existing criterion cannot be moved to another session; use Clone instead.';
        }
        if ($name === '' || strlen($name) > 191) {
            $errors[] = 'Criteria name is required and must not exceed 191 characters.';
        }
        if ($minimum === null) {
            $errors[] = 'Minimum cumulative average must be a number from 0 to 100.';
        }
        if (empty($classIds)) {
            $errors[] = 'Assign the criterion to at least one class.';
        }

        $classAssignments = array();
        foreach ($classIds as $classId) {
            if (!$this->promotioncriteria_model->classExists($classId)) {
                $errors[] = 'One of the selected classes is invalid.';
                continue;
            }

            $targetClassId = isset($targetClassInput[$classId]) ? $this->positiveInt($targetClassInput[$classId]) : 0;
            $targetLabel = isset($targetLabelInput[$classId]) ? trim(strip_tags((string) $targetLabelInput[$classId])) : '';

            if ($targetClassId > 0 && !$this->promotioncriteria_model->classExists($targetClassId)) {
                $errors[] = 'A selected promoted-to class is invalid.';
                continue;
            }
            if ($targetLabel !== '' && strlen($targetLabel) > 191) {
                $errors[] = 'A custom promoted-to label is longer than 191 characters.';
                continue;
            }
            if ($targetClassId <= 0 && $targetLabel === '') {
                $errors[] = 'Choose a promoted-to class or enter a custom label for every assigned class.';
                continue;
            }
            if ($targetClassId > 0 && $targetLabel !== '') {
                $errors[] = 'Use either a promoted-to class or a custom label for each class, not both.';
                continue;
            }

            $classAssignments[$classId] = array(
                'promoted_to_class_id' => $targetClassId > 0 ? $targetClassId : null,
                'promoted_to_label' => $targetLabel,
            );
        }

        $subjectRules = array();
        foreach ($subjectIds as $subjectId) {
            if (!$this->promotioncriteria_model->subjectExists($subjectId)) {
                $errors[] = 'One of the selected priority subjects is invalid.';
                continue;
            }
            $subjectMinimum = isset($subjectMinimumInput[$subjectId])
                ? $this->validPercentage($subjectMinimumInput[$subjectId])
                : null;
            if ($subjectMinimum === null) {
                $errors[] = 'Every priority subject must have a minimum score from 0 to 100.';
                continue;
            }
            $subjectRules[$subjectId] = $subjectMinimum;
        }

        if (!empty($errors)) {
            return $this->redirectMessage('danger', implode(' ', array_unique($errors)), $this->criteriaUrl($sessionId, $criterionId));
        }

        $result = $this->promotioncriteria_model->saveCriterion(array(
            'session_id' => $sessionId,
            'name' => $name,
            'minimum_average' => $minimum,
            'is_active' => (int) $this->input->post('is_active') === 1,
        ), $classAssignments, $subjectRules, (int) $this->customlib->getStaffID(), $criterionId);

        if (empty($result['success'])) {
            return $this->redirectMessage(
                'danger',
                $this->resultMessage($result),
                $this->criteriaUrl($sessionId, $criterionId)
            );
        }

        return $this->redirectMessage(
            'success',
            $criterionId > 0 ? 'Promotion criterion updated.' : 'Promotion criterion created.',
            $this->criteriaUrl($sessionId)
        );
    }

    public function archive()
    {
        $this->requireReady();
        $this->requirePostAndToken();
        // Archiving is an edit, not a destructive delete permission.
        $this->requireCriteriaPrivilege('can_edit');

        $criterionId = $this->positiveInt($this->input->post('id'));
        $criterion = $this->promotioncriteria_model->getCriterion($criterionId);
        if (empty($criterion)) {
            show_404();
        }

        $result = $this->promotioncriteria_model->archiveCriterion($criterionId, (int) $this->customlib->getStaffID());
        return $this->redirectMessage(
            !empty($result['success']) ? 'success' : 'danger',
            !empty($result['success']) ? 'Promotion criterion archived.' : $this->resultMessage($result),
            $this->criteriaUrl((int) $criterion['session_id'])
        );
    }

    public function clonecriterion()
    {
        $this->requireReady();
        $this->requirePostAndToken();
        $this->requireCriteriaPrivilege('can_add');

        $criterionId = $this->positiveInt($this->input->post('id'));
        $targetSessionId = $this->positiveInt($this->input->post('target_session_id'));
        $source = $this->promotioncriteria_model->getCriterion($criterionId);
        if (empty($source)) {
            show_404();
        }
        if ($targetSessionId === (int) $source['session_id']) {
            return $this->redirectMessage('danger', 'Choose a different target session.', $this->criteriaUrl((int) $source['session_id']));
        }

        $result = $this->promotioncriteria_model->cloneCriterion(
            $criterionId,
            $targetSessionId,
            (int) $this->customlib->getStaffID()
        );
        if (empty($result['success'])) {
            return $this->redirectMessage('danger', $this->resultMessage($result), $this->criteriaUrl((int) $source['session_id']));
        }

        $message = 'Promotion criterion cloned to the selected session.';
        if (!empty($result['conflicts']) && is_array($result['conflicts'])) {
            $conflictNames = array();
            foreach ($result['conflicts'] as $conflict) {
                if (!empty($conflict['class'])) {
                    $conflictNames[] = $conflict['class'];
                }
            }
            if (!empty($conflictNames)) {
                $message .= ' Skipped existing target-session assignments: ' . implode(', ', array_unique($conflictNames)) . '.';
            }
        }
        return $this->redirectMessage('success', $message, $this->criteriaUrl($targetSessionId));
    }

    public function setoverride()
    {
        $this->requireReady();
        $this->requirePostAndToken();
        $this->requireOverridePrivilege();

        $scope = $this->postedScope();
        $this->validatePostedScope($scope);

        $decision = strtolower(trim((string) $this->input->post('decision', true)));
        $targetClassId = $this->positiveInt($this->input->post('target_class_id'));
        $targetLabel = trim(strip_tags((string) $this->input->post('target_label', true)));
        $reason = trim(strip_tags((string) $this->input->post('reason', true)));
        $errors = array();

        if (!in_array($decision, $this->allowedDecisions, true)) {
            $errors[] = 'Select a valid promotion decision.';
        }
        if ($targetClassId > 0 && !$this->promotioncriteria_model->classExists($targetClassId)) {
            $errors[] = 'The selected promoted-to class is invalid.';
        }
        if ($targetClassId > 0 && $targetLabel !== '') {
            $errors[] = 'Use either a promoted-to class or a custom label, not both.';
        }
        if ($decision === 'not_promoted' && ($targetClassId > 0 || $targetLabel !== '')) {
            $errors[] = 'A not-promoted decision cannot include a promoted-to destination.';
        }
        if ($decision === 'promoted' && $targetClassId <= 0 && $targetLabel === '') {
            $errors[] = 'Choose a promoted-to class or enter a custom label for a promoted decision.';
        }
        if (strlen($targetLabel) > 191) {
            $errors[] = 'The promoted-to label must not exceed 191 characters.';
        }
        if ($reason === '' || strlen($reason) > 1000) {
            $errors[] = 'A reason is required and must not exceed 1000 characters.';
        }
        if (!empty($errors)) {
            return $this->redirectMessage('danger', implode(' ', $errors), $this->reviewUrl($scope));
        }

        $qualitative = $this->promotioncriteria_model->isQualitativeClass($scope['class_id']);
        $automatic = $this->promotioncriteria_model->evaluateStudent(
            $scope['student_id'],
            $scope['session_id'],
            $scope['class_id'],
            $scope['section_id'],
            $qualitative,
            true
        );
        $result = $this->promotioncriteria_model->setOverride(
            $scope,
            $decision,
            $targetClassId,
            $targetLabel,
            $reason,
            is_array($automatic) ? $automatic : array(),
            (int) $this->customlib->getStaffID()
        );

        return $this->redirectMessage(
            !empty($result['success']) ? 'success' : 'danger',
            !empty($result['success']) ? 'Promotion note override recorded.' : $this->resultMessage($result),
            $this->reviewUrl($scope)
        );
    }

    public function clearoverride()
    {
        $this->requireReady();
        $this->requirePostAndToken();
        $this->requireOverridePrivilege();

        $scope = $this->postedScope();
        $this->validatePostedScope($scope);
        $reason = trim(strip_tags((string) $this->input->post('reason', true)));
        if ($reason === '' || strlen($reason) > 1000) {
            return $this->redirectMessage('danger', 'A reason is required and must not exceed 1000 characters.', $this->reviewUrl($scope));
        }

        $qualitative = $this->promotioncriteria_model->isQualitativeClass($scope['class_id']);
        $current = $this->promotioncriteria_model->evaluateStudent(
            $scope['student_id'],
            $scope['session_id'],
            $scope['class_id'],
            $scope['section_id'],
            $qualitative,
            false
        );
        if (!is_array($current) || strtolower((string) (isset($current['source']) ? $current['source'] : '')) !== 'override') {
            return $this->redirectMessage('danger', 'This student does not have an active promotion-note override.', $this->reviewUrl($scope));
        }

        $automatic = $this->promotioncriteria_model->evaluateStudent(
            $scope['student_id'],
            $scope['session_id'],
            $scope['class_id'],
            $scope['section_id'],
            $qualitative,
            true
        );
        $result = $this->promotioncriteria_model->clearOverride(
            $scope,
            $reason,
            is_array($automatic) ? $automatic : array(),
            (int) $this->customlib->getStaffID()
        );

        return $this->redirectMessage(
            !empty($result['success']) ? 'success' : 'danger',
            !empty($result['success']) ? 'Promotion-note override cleared; the system decision is active again.' : $this->resultMessage($result),
            $this->reviewUrl($scope)
        );
    }

    private function requireReady()
    {
        if (!$this->promotioncriteria_model->isReady()) {
            show_error('Promotion-system migration 133 has not been installed for this school database.', 503);
        }
    }

    private function requireAnyViewAccess()
    {
        if (!$this->canViewCriteria() && !$this->canViewReview()) {
            access_denied();
        }
    }

    private function canViewCriteria()
    {
        return $this->rbac->hasPrivilege('manage_promotion_criteria', 'can_view');
    }

    private function canViewReview()
    {
        return $this->rbac->hasPrivilege('override_promotion_note', 'can_view') || $this->canOverride();
    }

    private function canOverride()
    {
        return $this->rbac->hasPrivilege('override_promotion_note', 'can_add')
            || $this->rbac->hasPrivilege('override_promotion_note', 'can_edit');
    }

    private function requireCriteriaPrivilege($action)
    {
        if (!$this->rbac->hasPrivilege('manage_promotion_criteria', $action)) {
            access_denied();
        }
    }

    private function requireOverridePrivilege()
    {
        if (!$this->canOverride()) {
            access_denied();
        }
    }

    private function requirePostAndToken()
    {
        if (strtoupper((string) $this->input->server('REQUEST_METHOD')) !== 'POST') {
            show_error('This operation accepts POST requests only.', 405);
        }

        // The regular CI token is also rendered in every form. This scoped
        // session token keeps the module protected on legacy school installs
        // where the application-wide csrf_protection switch is still off.
        $expected = (string) $this->session->userdata($this->tokenSessionKey);
        $provided = (string) $this->input->post('promotion_csrf');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            show_error('The promotion form expired or failed its security check. Reload the page and try again.', 403);
        }
    }

    private function validatePostedScope(array $scope)
    {
        foreach ($scope as $value) {
            if ((int) $value <= 0) {
                show_error('The submitted student scope is incomplete.', 400);
            }
        }
        if (!$this->promotioncriteria_model->sessionExists($scope['session_id'])
            || !$this->promotioncriteria_model->classSectionExists($scope['class_id'], $scope['section_id'])
            || !$this->promotioncriteria_model->studentMatchesScope(
                $scope['student_id'],
                $scope['session_id'],
                $scope['class_id'],
                $scope['section_id']
            )) {
            show_error('The submitted student does not belong to the selected academic scope.', 403);
        }
        if (!$this->canAccessScope($scope['session_id'], $scope['class_id'], $scope['section_id'])) {
            show_error('You are not assigned to this class and section for the selected session.', 403);
        }
    }

    private function canAccessScope($sessionId, $classId, $sectionId)
    {
        $isSenior = $this->isSenior();
        $isTeacher = $this->isTeacher();
        $hasExactTeacherAssignment = false;
        if (!$isSenior && $isTeacher) {
            $hasExactTeacherAssignment = $this->promotioncriteria_model->teacherHasScope(
                (int) $this->customlib->getStaffID(),
                (int) $sessionId,
                (int) $classId,
                (int) $sectionId
            );
        }

        return promotion_scope_authorized($isSenior, $isTeacher, $hasExactTeacherAssignment);
    }

    private function isSenior()
    {
        $roles = $this->roleNames();
        return in_array('admin', $roles, true)
            || in_array('head teacher', $roles, true)
            || in_array('super admin', $roles, true);
    }

    private function isTeacher()
    {
        return in_array('teacher', $this->roleNames(), true);
    }

    private function roleNames()
    {
        $admin = $this->session->userdata('admin');
        $roles = isset($admin['roles']) && is_array($admin['roles']) ? array_keys($admin['roles']) : array();
        return array_values(array_unique(array_map(function ($role) {
            return strtolower(trim((string) $role));
        }, $roles)));
    }

    private function postedScope()
    {
        return array(
            'student_id' => $this->positiveInt($this->input->post('student_id')),
            'session_id' => $this->positiveInt($this->input->post('session_id')),
            'class_id' => $this->positiveInt($this->input->post('class_id')),
            'section_id' => $this->positiveInt($this->input->post('section_id')),
        );
    }

    private function criteriaUrl($sessionId, $editId = 0)
    {
        $query = array('tab' => 'criteria');
        if ((int) $sessionId > 0) {
            $query['criteria_session_id'] = (int) $sessionId;
        }
        if ((int) $editId > 0) {
            $query['edit'] = (int) $editId;
        }
        return 'admin/promotioncriteria?' . http_build_query($query);
    }

    private function reviewUrl(array $scope)
    {
        return 'admin/promotioncriteria?' . http_build_query(array(
            'tab' => 'review',
            'session_id' => (int) $scope['session_id'],
            'class_id' => (int) $scope['class_id'],
            'section_id' => (int) $scope['section_id'],
        ));
    }

    private function redirectMessage($type, $message, $url)
    {
        $this->session->set_flashdata('promotion_message', array(
            'type' => in_array($type, array('success', 'danger', 'warning', 'info'), true) ? $type : 'info',
            'message' => (string) $message,
        ));
        redirect($url);
    }

    private function resultMessage(array $result)
    {
        $message = !empty($result['message']) ? (string) $result['message'] : 'The requested promotion operation could not be completed.';
        if (!empty($result['conflicts']) && is_array($result['conflicts'])) {
            $names = array();
            foreach ($result['conflicts'] as $conflict) {
                if (!empty($conflict['class'])) {
                    $names[] = $conflict['class'];
                }
            }
            if (!empty($names)) {
                $message .= ' Conflicts: ' . implode(', ', array_unique($names)) . '.';
            }
        }
        return $message;
    }

    private function validPercentage($value)
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 0 || $number > 100) {
            return null;
        }
        return number_format(round($number, 2), 2, '.', '');
    }

    private function normalizeIds($value)
    {
        if (!is_array($value)) {
            return array();
        }
        $ids = array();
        foreach ($value as $item) {
            $id = $this->positiveInt($item);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function positiveInt($value)
    {
        if (is_array($value) || is_object($value)) {
            return 0;
        }
        if (is_int($value)) {
            $number = $value;
        } else {
            $value = trim((string) $value);
            if ($value === '' || !ctype_digit($value)) {
                return 0;
            }
            $number = (int) $value;
        }
        return $number > 0 ? $number : 0;
    }

    private function newToken()
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Exception $exception) {
            return hash('sha256', uniqid((string) mt_rand(), true));
        }
    }
}
