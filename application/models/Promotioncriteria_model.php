<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Persistence and scope checks for advisory promotion decisions.
 *
 * This model deliberately never writes to student_session. Moving a learner to
 * another class remains the responsibility of the existing manual promotion
 * workflow.
 */
class Promotioncriteria_model extends MY_Model
{
    private $requiredTables = array(
        'promotion_criteria',
        'promotion_criteria_classes',
        'promotion_criteria_subjects',
        'promotion_note_overrides',
    );

    public function __construct()
    {
        parent::__construct();
        $helperPath = FCPATH . 'helper/promotion_helper.php';
        if (is_file($helperPath)) {
            require_once $helperPath;
        }
    }

    public function isReady()
    {
        foreach ($this->requiredTables as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }

        return true;
    }

    public function getSessions()
    {
        return $this->db->order_by('id', 'DESC')->get('sessions')->result_array();
    }

    public function getClasses()
    {
        return $this->db->select('id, class')->order_by('id', 'ASC')->get('classes')->result_array();
    }

    public function getSubjects()
    {
        return $this->db->select('id, name, code')->order_by('name', 'ASC')->get('subjects')->result_array();
    }

    public function getClassSections()
    {
        return $this->db
            ->select('class_sections.class_id, class_sections.section_id, classes.class, sections.section')
            ->from('class_sections')
            ->join('classes', 'classes.id = class_sections.class_id')
            ->join('sections', 'sections.id = class_sections.section_id')
            ->order_by('classes.id', 'ASC')
            ->order_by('sections.section', 'ASC')
            ->get()
            ->result_array();
    }

    public function getTeacherScopes($staffId)
    {
        return $this->db
            ->distinct()
            ->select('class_teacher.session_id, class_teacher.class_id, class_teacher.section_id, sessions.session, classes.class, sections.section')
            ->from('class_teacher')
            ->join('sessions', 'sessions.id = class_teacher.session_id')
            ->join('classes', 'classes.id = class_teacher.class_id')
            ->join('sections', 'sections.id = class_teacher.section_id')
            ->where('class_teacher.staff_id', (int) $staffId)
            ->order_by('class_teacher.session_id', 'DESC')
            ->order_by('classes.id', 'ASC')
            ->order_by('sections.section', 'ASC')
            ->get()
            ->result_array();
    }

    public function teacherHasScope($staffId, $sessionId, $classId, $sectionId)
    {
        return $this->db
            ->from('class_teacher')
            ->where('staff_id', (int) $staffId)
            ->where('session_id', (int) $sessionId)
            ->where('class_id', (int) $classId)
            ->where('section_id', (int) $sectionId)
            ->limit(1)
            ->count_all_results() > 0;
    }

    public function sessionExists($sessionId)
    {
        return $this->rowExists('sessions', (int) $sessionId);
    }

    public function classExists($classId)
    {
        return $this->rowExists('classes', (int) $classId);
    }

    public function subjectExists($subjectId)
    {
        return $this->rowExists('subjects', (int) $subjectId);
    }

    public function classSectionExists($classId, $sectionId)
    {
        return $this->db
            ->from('class_sections')
            ->where('class_id', (int) $classId)
            ->where('section_id', (int) $sectionId)
            ->limit(1)
            ->count_all_results() > 0;
    }

    public function studentMatchesScope($studentId, $sessionId, $classId, $sectionId)
    {
        return $this->db
            // Select one unambiguous column for this existence check. Using
            // count_all_results() with LIMIT made CI wrap SELECT * in a
            // derived table; both joined tables have an `id` column, which
            // causes MySQL error 1060 (duplicate column name `id`).
            ->select('student_session.student_id AS matched_student_id')
            ->from('student_session')
            ->join('students', 'students.id = student_session.student_id')
            ->where('student_session.student_id', (int) $studentId)
            ->where('student_session.session_id', (int) $sessionId)
            ->where('student_session.class_id', (int) $classId)
            ->where('student_session.section_id', (int) $sectionId)
            ->where('students.is_active', 'yes')
            ->limit(1)
            ->get()
            ->num_rows() > 0;
    }

    public function getCriteriaList($sessionId = null)
    {
        $this->db
            ->select('promotion_criteria.*, sessions.session')
            ->from('promotion_criteria')
            ->join('sessions', 'sessions.id = promotion_criteria.session_id');

        if ((int) $sessionId > 0) {
            $this->db->where('promotion_criteria.session_id', (int) $sessionId);
        }

        $rows = $this->db
            ->order_by('promotion_criteria.is_active', 'DESC')
            ->order_by('promotion_criteria.updated_at', 'DESC')
            ->order_by('promotion_criteria.id', 'DESC')
            ->get()
            ->result_array();

        foreach ($rows as &$row) {
            $row['classes'] = $this->getCriterionClasses((int) $row['id']);
            $row['subjects'] = $this->getCriterionSubjects((int) $row['id']);
        }
        unset($row);

        return $rows;
    }

    public function getCriterion($criterionId)
    {
        $row = $this->db
            ->where('id', (int) $criterionId)
            ->get('promotion_criteria')
            ->row_array();

        if (empty($row)) {
            return null;
        }

        $row['classes'] = $this->getCriterionClasses((int) $criterionId);
        $row['subjects'] = $this->getCriterionSubjects((int) $criterionId);

        return $row;
    }

    public function getCriterionClasses($criterionId)
    {
        return $this->db
            ->select('promotion_criteria_classes.*, classes.class, target_classes.class AS promoted_to_class')
            ->from('promotion_criteria_classes')
            ->join('classes', 'classes.id = promotion_criteria_classes.class_id')
            ->join('classes AS target_classes', 'target_classes.id = promotion_criteria_classes.promoted_to_class_id', 'left')
            ->where('promotion_criteria_classes.criteria_id', (int) $criterionId)
            ->order_by('classes.id', 'ASC')
            ->get()
            ->result_array();
    }

    public function getCriterionSubjects($criterionId)
    {
        return $this->db
            ->select('promotion_criteria_subjects.*, subjects.name AS subject_name, subjects.code AS subject_code')
            ->from('promotion_criteria_subjects')
            ->join('subjects', 'subjects.id = promotion_criteria_subjects.subject_id')
            ->where('promotion_criteria_subjects.criteria_id', (int) $criterionId)
            ->order_by('subjects.name', 'ASC')
            ->get()
            ->result_array();
    }

    /**
     * Creates or updates one criterion and replaces its child rules in a
     * transaction. A class already assigned in the same session is never
     * silently reassigned.
     */
    public function saveCriterion(array $criterion, array $classAssignments, array $subjectRules, $actorId, $criterionId = null)
    {
        $criterionId = (int) $criterionId;
        $sessionId = (int) $criterion['session_id'];
        $now = date('Y-m-d H:i:s');

        $this->db->trans_begin();

        if ($criterionId > 0) {
            $existing = $this->db->query(
                'SELECT id FROM promotion_criteria WHERE id = ? FOR UPDATE',
                array($criterionId)
            )->row_array();
            if (empty($existing)) {
                $this->db->trans_rollback();
                return array('success' => false, 'message' => 'The promotion criterion no longer exists.');
            }
        }

        $classIds = array_map('intval', array_keys($classAssignments));
        $conflicts = $this->findAssignmentConflicts($sessionId, $classIds, $criterionId);
        if (!empty($conflicts)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'One or more classes already use another criterion in this session.', 'conflicts' => $conflicts);
        }

        $criterionRow = array(
            'session_id' => $sessionId,
            'name' => $criterion['name'],
            'minimum_average' => $criterion['minimum_average'],
            'is_active' => !empty($criterion['is_active']) ? 1 : 0,
            'updated_by' => (int) $actorId,
            'updated_at' => $now,
        );

        if ($criterionId > 0) {
            $this->db->where('id', $criterionId)->update('promotion_criteria', $criterionRow);
            $this->db->where('criteria_id', $criterionId)->delete('promotion_criteria_classes');
            $this->db->where('criteria_id', $criterionId)->delete('promotion_criteria_subjects');
        } else {
            $criterionRow['created_by'] = (int) $actorId;
            $criterionRow['created_at'] = $now;
            $this->db->insert('promotion_criteria', $criterionRow);
            $criterionId = (int) $this->db->insert_id();
        }

        foreach ($classAssignments as $classId => $assignment) {
            $this->db->insert('promotion_criteria_classes', array(
                'criteria_id' => $criterionId,
                'session_id' => $sessionId,
                'class_id' => (int) $classId,
                'promoted_to_class_id' => !empty($assignment['promoted_to_class_id']) ? (int) $assignment['promoted_to_class_id'] : null,
                'promoted_to_label' => $assignment['promoted_to_label'] !== '' ? $assignment['promoted_to_label'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        foreach ($subjectRules as $subjectId => $minimumAverage) {
            $this->db->insert('promotion_criteria_subjects', array(
                'criteria_id' => $criterionId,
                'subject_id' => (int) $subjectId,
                'minimum_average' => $minimumAverage,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The promotion criterion could not be saved.');
        }

        $this->db->trans_commit();
        return array('success' => true, 'id' => $criterionId);
    }

    public function archiveCriterion($criterionId, $actorId)
    {
        $criterionId = (int) $criterionId;
        if ($criterionId <= 0 || !$this->rowExists('promotion_criteria', $criterionId)) {
            return array('success' => false, 'message' => 'The promotion criterion was not found.');
        }

        $updated = $this->db
            ->where('id', $criterionId)
            ->update('promotion_criteria', array(
                'is_active' => 0,
                'updated_by' => (int) $actorId,
                'updated_at' => date('Y-m-d H:i:s'),
            ));

        return $updated
            ? array('success' => true)
            : array('success' => false, 'message' => 'The promotion criterion could not be archived.');
    }

    /**
     * Clones all non-conflicting assignments. Conflicting target-session
     * classes are reported and left untouched.
     */
    public function cloneCriterion($criterionId, $targetSessionId, $actorId)
    {
        $source = $this->getCriterion((int) $criterionId);
        if (empty($source)) {
            return array('success' => false, 'message' => 'The source promotion criterion was not found.');
        }

        $targetSessionId = (int) $targetSessionId;
        if (!$this->sessionExists($targetSessionId)) {
            return array('success' => false, 'message' => 'The target session was not found.');
        }

        $sourceClassIds = array();
        foreach ($source['classes'] as $assignment) {
            $sourceClassIds[] = (int) $assignment['class_id'];
        }
        $skippedConflicts = $this->findAssignmentConflicts($targetSessionId, $sourceClassIds, 0);
        $conflictingIds = array_map('intval', array_column($skippedConflicts, 'class_id'));

        $available = array();
        foreach ($source['classes'] as $assignment) {
            $classId = (int) $assignment['class_id'];
            if (!in_array($classId, $conflictingIds, true)) {
                $available[] = $assignment;
            }
        }

        if (empty($available)) {
            return array(
                'success' => false,
                'message' => 'Every source class already has a promotion criterion in the target session.',
                'conflicts' => $skippedConflicts,
            );
        }

        $now = date('Y-m-d H:i:s');
        $this->db->trans_begin();
        // Re-check after the transaction begins to close the usual clone race.
        $raceConflicts = $this->findAssignmentConflicts($targetSessionId, array_map('intval', array_column($available, 'class_id')), 0);
        if (!empty($raceConflicts)) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'A class was assigned while the criterion was being cloned. No changes were made.', 'conflicts' => $raceConflicts);
        }

        $this->db->insert('promotion_criteria', array(
            'session_id' => $targetSessionId,
            'name' => $source['name'],
            'minimum_average' => $source['minimum_average'],
            'is_active' => (int) $source['is_active'],
            'created_by' => (int) $actorId,
            'updated_by' => (int) $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $newId = (int) $this->db->insert_id();

        foreach ($available as $assignment) {
            $this->db->insert('promotion_criteria_classes', array(
                'criteria_id' => $newId,
                'session_id' => $targetSessionId,
                'class_id' => (int) $assignment['class_id'],
                'promoted_to_class_id' => !empty($assignment['promoted_to_class_id']) ? (int) $assignment['promoted_to_class_id'] : null,
                'promoted_to_label' => !empty($assignment['promoted_to_label']) ? $assignment['promoted_to_label'] : null,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        foreach ($source['subjects'] as $subject) {
            $this->db->insert('promotion_criteria_subjects', array(
                'criteria_id' => $newId,
                'subject_id' => (int) $subject['subject_id'],
                'minimum_average' => $subject['minimum_average'],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('success' => false, 'message' => 'The promotion criterion could not be cloned.');
        }

        $this->db->trans_commit();
        return array('success' => true, 'id' => $newId, 'conflicts' => $skippedConflicts, 'skipped_conflicts' => $conflictingIds);
    }

    public function getReviewStudents($sessionId, $classId, $sectionId)
    {
        return $this->db
            ->select("students.id, students.admission_no, students.roll_no, CONCAT_WS(' ', students.firstname, students.middlename, students.lastname) AS student_name", false)
            ->from('student_session')
            ->join('students', 'students.id = student_session.student_id')
            ->where('student_session.session_id', (int) $sessionId)
            ->where('student_session.class_id', (int) $classId)
            ->where('student_session.section_id', (int) $sectionId)
            ->where('students.is_active', 'yes')
            ->group_by(array('students.id', 'students.admission_no', 'students.roll_no', 'students.firstname', 'students.middlename', 'students.lastname'))
            ->order_by('students.firstname', 'ASC')
            ->order_by('students.lastname', 'ASC')
            ->get()
            ->result_array();
    }

    public function getScopeMeta($sessionId, $classId, $sectionId)
    {
        return $this->db
            ->select('sessions.session, classes.class, sections.section')
            ->from('sessions')
            ->join('classes', 'classes.id = ' . (int) $classId, 'inner', false)
            ->join('sections', 'sections.id = ' . (int) $sectionId, 'inner', false)
            ->where('sessions.id', (int) $sessionId)
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function isQualitativeClass($classId)
    {
        $classId = (int) $classId;
        if ($this->db->table_exists('kindergarten_assignment')
            && $this->db->where('class_id', $classId)->limit(1)->count_all_results('kindergarten_assignment') > 0) {
            return true;
        }

        if (!$this->db->table_exists('assigncatoclass')) {
            return false;
        }

        $assignment = $this->db
            ->select('ResultType')
            ->where('ClassID', $classId)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('assigncatoclass')
            ->row_array();

        return !empty($assignment) && strtolower(trim((string) $assignment['ResultType'])) === 'british';
    }

    public function evaluateStudent($studentId, $sessionId, $classId, $sectionId, $qualitative = null, $automaticOnly = false)
    {
        if ($qualitative === null) {
            $qualitative = $this->isQualitativeClass($classId);
        }

        $function = $automaticOnly ? 'get_promotion_automatic_outcome' : 'get_final_promotion_outcome';
        if (function_exists($function)) {
            return call_user_func(
                $function,
                $this->db->conn_id,
                (int) $studentId,
                (int) $sessionId,
                (int) $classId,
                (int) $sectionId,
                (bool) $qualitative
            );
        }

        // Keeps the administration page safe during a rolling deployment in
        // which the migration lands before the shared evaluator.
        return array(
            'decision' => 'pending',
            'note' => 'PROMOTION PENDING',
            'target_label' => null,
            'source' => 'system',
            'reason_code' => 'evaluator_unavailable',
            'overall_average' => null,
            'priority_results' => array(),
            'has_data' => false,
            'criteria_id' => null,
            'override' => null,
        );
    }

    public function setOverride(array $scope, $decision, $targetClassId, $targetLabel, $reason, array $automatic, $actorId)
    {
        return $this->appendOverride(array(
            'student_id' => (int) $scope['student_id'],
            'session_id' => (int) $scope['session_id'],
            'class_id' => (int) $scope['class_id'],
            'section_id' => (int) $scope['section_id'],
            'action' => 'set',
            'decision' => $decision,
            'target_class_id' => $targetClassId ? (int) $targetClassId : null,
            'target_label' => $targetLabel !== '' ? $targetLabel : null,
            'reason' => $reason,
            'automatic_decision' => isset($automatic['decision']) ? $automatic['decision'] : 'pending',
            'automatic_note' => isset($automatic['note']) ? $automatic['note'] : 'PROMOTION PENDING',
            'created_by' => (int) $actorId,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function clearOverride(array $scope, $reason, array $automatic, $actorId)
    {
        return $this->appendOverride(array(
            'student_id' => (int) $scope['student_id'],
            'session_id' => (int) $scope['session_id'],
            'class_id' => (int) $scope['class_id'],
            'section_id' => (int) $scope['section_id'],
            'action' => 'clear',
            'decision' => null,
            'target_class_id' => null,
            'target_label' => null,
            'reason' => $reason,
            'automatic_decision' => isset($automatic['decision']) ? $automatic['decision'] : 'pending',
            'automatic_note' => isset($automatic['note']) ? $automatic['note'] : 'PROMOTION PENDING',
            'created_by' => (int) $actorId,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function appendOverride(array $row)
    {
        $inserted = $this->db->insert('promotion_note_overrides', $row);
        if (!$inserted) {
            return array('success' => false, 'message' => 'The promotion-note change could not be recorded.');
        }

        return array('success' => true, 'id' => (int) $this->db->insert_id());
    }

    private function findAssignmentConflicts($sessionId, array $classIds, $excludingCriterionId)
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if (empty($classIds)) {
            return array();
        }

        $this->db
            ->select('promotion_criteria_classes.class_id, classes.class, promotion_criteria.name AS criteria_name')
            ->from('promotion_criteria_classes')
            ->join('promotion_criteria', 'promotion_criteria.id = promotion_criteria_classes.criteria_id')
            ->join('classes', 'classes.id = promotion_criteria_classes.class_id')
            ->where('promotion_criteria_classes.session_id', (int) $sessionId)
            ->where_in('promotion_criteria_classes.class_id', $classIds);

        if ((int) $excludingCriterionId > 0) {
            $this->db->where('promotion_criteria_classes.criteria_id !=', (int) $excludingCriterionId);
        }

        return $this->db->get()->result_array();
    }

    private function rowExists($table, $id)
    {
        return $id > 0 && $this->db
            ->from($table)
            ->where('id', (int) $id)
            ->limit(1)
            ->count_all_results() === 1;
    }
}
