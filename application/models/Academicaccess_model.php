<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * One academic-scope policy for Question Bank and Online Examination staff.
 *
 * RBAC remains the feature/action gate. This model is the mandatory row-level
 * gate and deliberately uses the session-specific assignment records instead
 * of a hard-coded role id. Schools in this codebase may assign a subject
 * either through teacher_subjects or through either subject-timetable format;
 * each is authoritative only when its session, class arm and subject match.
 */
class Academicaccess_model extends CI_Model
{
    private $administrator_roles = array('super admin', 'admin', 'head teacher');

    public function roleNames()
    {
        $admin = $this->session->userdata('admin');
        $roles = isset($admin['roles']) && is_array($admin['roles'])
            ? array_keys($admin['roles']) : array();

        return array_values(array_unique(array_map(function ($role) {
            return strtolower(trim((string) $role));
        }, $roles)));
    }

    public function isAcademicAdministrator()
    {
        return count(array_intersect($this->administrator_roles, $this->roleNames())) > 0;
    }

    public function staffId()
    {
        return (int) $this->customlib->getStaffID();
    }

    public function classTeacherSectionIds($session_id, $class_id = 0)
    {
        if ($this->isAcademicAdministrator()) {
            return $this->allSectionIds($class_id);
        }
        $query = $this->db->distinct()->select('class_teacher.section_id')
            ->from('class_teacher')
            ->where('class_teacher.staff_id', $this->staffId())
            ->where('class_teacher.session_id', (int) $session_id);
        if ((int) $class_id > 0) {
            $query->where('class_teacher.class_id', (int) $class_id);
        }
        return array_values(array_unique(array_map('intval', array_column(
            $query->get()->result_array(), 'section_id'
        ))));
    }

    public function subjectTeacherSectionIds($session_id, $class_id = 0, $subject_id = 0)
    {
        if ($this->isAcademicAdministrator()) {
            return $this->allSectionIds($class_id);
        }
        return array_values(array_unique(array_map('intval', array_column(
            $this->subjectTeacherAssignments($session_id, $class_id, $subject_id), 'section_id'
        ))));
    }

    /**
     * Exact subject-teaching tuples from every assignment store used by the
     * application. Timetable rows are accepted only when their referenced
     * subject belongs to the same session and the class/arm actually exists.
     */
    public function subjectTeacherAssignments($session_id, $class_id = 0, $subject_id = 0, $section_id = 0, $staff_id = null)
    {
        $session_id = (int) $session_id;
        $class_id = (int) $class_id;
        $subject_id = (int) $subject_id;
        $section_id = (int) $section_id;
        $staff_id = $staff_id === null ? $this->staffId() : (int) $staff_id;
        if ($session_id < 1 || $staff_id < 1) {
            return array();
        }

        $assignments = array();
        if ($this->db->table_exists('teacher_subjects') && $this->db->table_exists('class_sections')) {
            $query = $this->db->distinct()
                ->select('class_sections.class_id, class_sections.section_id, teacher_subjects.subject_id')
                ->from('teacher_subjects')
                ->join('class_sections', 'class_sections.id=teacher_subjects.class_section_id')
                ->where('teacher_subjects.teacher_id', $staff_id)
                ->where('teacher_subjects.session_id', $session_id);
            $this->applyAssignmentFilters($query, 'class_sections.class_id', 'class_sections.section_id', 'teacher_subjects.subject_id', $class_id, $section_id, $subject_id);
            foreach ($query->get()->result_array() as $row) {
                $assignments[$this->assignmentKey($row)] = $this->integerAssignment($row);
            }
        }

        foreach ($this->timetableAssignmentTables() as $table) {
            $query = $this->db->distinct()
                ->select('access_timetable.class_id, access_timetable.section_id, access_timetable_subject.subject_id')
                ->from($table . ' access_timetable')
                ->join('subject_group_subjects access_timetable_subject',
                    'access_timetable_subject.id=access_timetable.subject_group_subject_id'
                    . ' AND access_timetable_subject.subject_group_id=access_timetable.subject_group_id'
                    . ' AND access_timetable_subject.session_id=access_timetable.session_id')
                ->join('class_sections access_timetable_class',
                    'access_timetable_class.class_id=access_timetable.class_id'
                    . ' AND access_timetable_class.section_id=access_timetable.section_id')
                ->join('subject_group_class_sections access_timetable_group',
                    'access_timetable_group.subject_group_id=access_timetable.subject_group_id'
                    . ' AND access_timetable_group.class_section_id=access_timetable_class.id'
                    . ' AND access_timetable_group.session_id=access_timetable.session_id')
                ->where('access_timetable.staff_id', $staff_id)
                ->where('access_timetable.session_id', $session_id);
            $this->applyAssignmentFilters($query, 'access_timetable.class_id', 'access_timetable.section_id', 'access_timetable_subject.subject_id', $class_id, $section_id, $subject_id);
            foreach ($query->get()->result_array() as $row) {
                $assignments[$this->assignmentKey($row)] = $this->integerAssignment($row);
            }
        }

        ksort($assignments);
        return array_values($assignments);
    }

    /** Classes visible through either a class-teacher or subject-teacher assignment. */
    public function classChoicesFor($mode, $session_id)
    {
        if ($this->isAcademicAdministrator()) {
            return $this->db->select('id, class')->order_by('id')->get('classes')->result_array();
        }

        $session_id = (int) $session_id;
        $class_ids = array_map('intval', array_column(
            $this->subjectTeacherAssignments($session_id), 'class_id'
        ));
        if (!in_array($mode, array('content', 'mark'), true)) {
            $class_ids = array_merge($class_ids, array_map('intval', array_column(
                $this->db->distinct()->select('class_id')->from('class_teacher')
                    ->where('staff_id', $this->staffId())->where('session_id', $session_id)
                    ->get()->result_array(), 'class_id'
            )));
        }
        $class_ids = array_values(array_unique(array_filter($class_ids)));
        if (empty($class_ids)) {
            return array();
        }
        return $this->db->select('id, class')->where_in('id', $class_ids)
            ->order_by('id')->get('classes')->result_array();
    }

    public function sectionIdsFor($mode, $session_id, $class_id, $subject_id = 0, array $requested = array())
    {
        $requested = array_values(array_unique(array_filter(array_map('intval', $requested))));
        if ($this->isAcademicAdministrator()) {
            $allowed = !empty($requested) ? $requested : $this->allSectionIds($class_id);
        } elseif (in_array($mode, array('content', 'mark'), true)) {
            $allowed = $this->subjectTeacherSectionIds($session_id, $class_id, $subject_id);
        } else {
            $allowed = array_values(array_unique(array_merge(
                $this->classTeacherSectionIds($session_id, $class_id),
                $this->subjectTeacherSectionIds($session_id, $class_id, $subject_id)
            )));
        }
        return empty($requested) ? $allowed : array_values(array_intersect($requested, $allowed));
    }

    public function canViewContext($session_id, $class_id, $section_id, $subject_id)
    {
        return $this->canUseSection('view', $session_id, $class_id, $section_id, $subject_id);
    }

    public function canManageContent($session_id, $class_id, $section_id, $subject_id)
    {
        return $this->canUseSection('content', $session_id, $class_id, $section_id, $subject_id);
    }

    public function canManageCandidates($session_id, $class_id, $section_id, $subject_id)
    {
        return $this->canUseSection('candidate', $session_id, $class_id, $section_id, $subject_id);
    }

    public function canMark($session_id, $class_id, $section_id, $subject_id)
    {
        return $this->canUseSection('mark', $session_id, $class_id, $section_id, $subject_id);
    }

    public function canManageAllSections($mode, $session_id, $class_id, array $section_ids, $subject_id)
    {
        $section_ids = array_values(array_unique(array_filter(array_map('intval', $section_ids))));
        if (empty($section_ids)) {
            return false;
        }
        return count($this->sectionIdsFor($mode, $session_id, $class_id, $subject_id, $section_ids))
            === count($section_ids);
    }

    public function questionVisibilitySql($alias, $session_id)
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($alias === '') {
            $alias = 'questions';
        }
        if ($this->isAcademicAdministrator()) {
            return '1=1';
        }
        $staff_id = $this->staffId();
        $session_id = (int) $session_id;
        $class_teacher = 'EXISTS (SELECT 1 FROM class_teacher access_ct'
            . ' WHERE access_ct.staff_id=' . $staff_id
            . ' AND access_ct.session_id=' . $session_id
            . ' AND access_ct.class_id=' . $alias . '.class_id'
            . ' AND (' . $alias . '.section_id=0 OR access_ct.section_id=' . $alias . '.section_id))';
        $subject_assignment = '((' . $alias . '.section_id=0 AND '
            . $this->subjectAssignmentExistsSql(
                $alias . '.class_id', null, $alias . '.session_id', $alias . '.subject_id', $staff_id
            ) . ') OR (' . $alias . '.section_id>0 AND '
            . $this->subjectAssignmentExistsSql(
                $alias . '.class_id', $alias . '.section_id', $alias . '.session_id', $alias . '.subject_id', $staff_id
            ) . '))';
        return '(' . $class_teacher . ' OR ' . $subject_assignment . ')';
    }

    /** SQL EXISTS expression shared by server-side assessment/review queries. */
    public function subjectAssignmentExistsSql($class_expression, $section_expression, $session_expression, $subject_expression = null, $staff_id = null)
    {
        foreach (array_filter(array($class_expression, $section_expression, $session_expression, $subject_expression), function ($value) {
            return $value !== null;
        }) as $expression) {
            if (!preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_]*\.)?[a-zA-Z_][a-zA-Z0-9_]*$|^[0-9]+$/', (string) $expression)) {
                throw new InvalidArgumentException('Unsafe academic assignment SQL expression.');
            }
        }
        $staff_id = $staff_id === null ? $this->staffId() : (int) $staff_id;
        $clauses = array();
        if ($this->db->table_exists('teacher_subjects') && $this->db->table_exists('class_sections')) {
            $clause = 'EXISTS (SELECT 1 FROM teacher_subjects access_ts'
                . ' INNER JOIN class_sections access_cs ON access_cs.id=access_ts.class_section_id'
                . ' WHERE access_ts.teacher_id=' . $staff_id
                . ' AND access_ts.session_id=' . $session_expression
                . ' AND access_cs.class_id=' . $class_expression;
            if ($section_expression !== null) {
                $clause .= ' AND access_cs.section_id=' . $section_expression;
            }
            if ($subject_expression !== null) {
                $clause .= ' AND access_ts.subject_id=' . $subject_expression;
            }
            $clauses[] = $clause . ')';
        }
        foreach ($this->timetableAssignmentTables() as $table) {
            $clause = 'EXISTS (SELECT 1 FROM ' . $table . ' access_st'
                . ' INNER JOIN subject_group_subjects access_sgs'
                . ' ON access_sgs.id=access_st.subject_group_subject_id'
                . ' AND access_sgs.subject_group_id=access_st.subject_group_id'
                . ' AND access_sgs.session_id=access_st.session_id'
                . ' INNER JOIN class_sections access_tcs'
                . ' ON access_tcs.class_id=access_st.class_id AND access_tcs.section_id=access_st.section_id'
                . ' INNER JOIN subject_group_class_sections access_sgcs'
                . ' ON access_sgcs.subject_group_id=access_st.subject_group_id'
                . ' AND access_sgcs.class_section_id=access_tcs.id'
                . ' AND access_sgcs.session_id=access_st.session_id'
                . ' WHERE access_st.staff_id=' . $staff_id
                . ' AND access_st.session_id=' . $session_expression
                . ' AND access_st.class_id=' . $class_expression;
            if ($section_expression !== null) {
                $clause .= ' AND access_st.section_id=' . $section_expression;
            }
            if ($subject_expression !== null) {
                $clause .= ' AND access_sgs.subject_id=' . $subject_expression;
            }
            $clauses[] = $clause . ')';
        }
        return empty($clauses) ? '(0=1)' : '(' . implode(' OR ', $clauses) . ')';
    }

    public function questionCapabilities(array $question)
    {
        $session_id = isset($question['session_id']) ? (int) $question['session_id'] : 0;
        $class_id = isset($question['class_id']) ? (int) $question['class_id'] : 0;
        $section_id = isset($question['section_id']) ? (int) $question['section_id'] : 0;
        $subject_id = isset($question['subject_id']) ? (int) $question['subject_id'] : 0;
        $view = $this->canViewContext($session_id, $class_id, $section_id, $subject_id);
        $content = $this->canManageContent($session_id, $class_id, $section_id, $subject_id);
        return array('view' => $view, 'content' => $content, 'delete' => $content);
    }

    public function assessmentCapabilities($exam, array $section_ids = array())
    {
        $exam = (array) $exam;
        if (empty($section_ids) && !empty($exam['section_ids'])) {
            $section_ids = array_map('intval', (array) $exam['section_ids']);
        }
        $base = array(
            'view_sections' => $this->sectionIdsFor('view', $exam['session_id'], $exam['class_id'], $exam['subject_id'], $section_ids),
            'content_sections' => $this->sectionIdsFor('content', $exam['session_id'], $exam['class_id'], $exam['subject_id'], $section_ids),
            'candidate_sections' => $this->sectionIdsFor('candidate', $exam['session_id'], $exam['class_id'], $exam['subject_id'], $section_ids),
            'mark_sections' => $this->sectionIdsFor('mark', $exam['session_id'], $exam['class_id'], $exam['subject_id'], $section_ids),
        );
        $base['can_view'] = !empty($base['view_sections']);
        $base['can_manage_content'] = !empty($section_ids)
            && count($base['content_sections']) === count(array_unique($section_ids));
        $base['can_manage_candidates'] = !empty($base['candidate_sections']);
        $base['can_mark'] = !empty($base['mark_sections']);
        return $base;
    }

    private function canUseSection($mode, $session_id, $class_id, $section_id, $subject_id)
    {
        if ((int) $session_id < 1 || (int) $class_id < 1 || (int) $subject_id < 1) {
            return false;
        }
        if ($this->isAcademicAdministrator()) {
            return true;
        }
        if ((int) $section_id > 0) {
            return in_array((int) $section_id, $this->sectionIdsFor(
                $mode, $session_id, $class_id, $subject_id, array((int) $section_id)
            ), true);
        }

        // Legacy class-wide questions may be changed only by a subject teacher
        // assigned to every arm in that class for the selected session.
        if ($mode === 'content' || $mode === 'mark') {
            $all = $this->allSectionIds($class_id);
            return !empty($all) && count($this->subjectTeacherSectionIds(
                $session_id, $class_id, $subject_id
            )) === count($all);
        }
        return !empty($this->sectionIdsFor('view', $session_id, $class_id, $subject_id));
    }

    private function allSectionIds($class_id)
    {
        if ((int) $class_id < 1) {
            return array();
        }
        return array_values(array_unique(array_map('intval', array_column(
            $this->db->select('section_id')->where('class_id', (int) $class_id)
                ->get('class_sections')->result_array(),
            'section_id'
        ))));
    }

    private function timetableAssignmentTables()
    {
        if (!$this->db->table_exists('subject_group_subjects')
            || !$this->db->table_exists('subject_group_class_sections')
            || !$this->db->table_exists('class_sections')) {
            return array();
        }
        return array_values(array_filter(array('subjecttables', 'subject_timetable'), function ($table) {
            return $this->db->table_exists($table);
        }));
    }

    private function applyAssignmentFilters($query, $class_field, $section_field, $subject_field, $class_id, $section_id, $subject_id)
    {
        if ((int) $class_id > 0) {
            $query->where($class_field, (int) $class_id);
        }
        if ((int) $section_id > 0) {
            $query->where($section_field, (int) $section_id);
        }
        if ((int) $subject_id > 0) {
            $query->where($subject_field, (int) $subject_id);
        }
    }

    private function integerAssignment(array $row)
    {
        return array(
            'class_id' => (int) $row['class_id'],
            'section_id' => (int) $row['section_id'],
            'subject_id' => (int) $row['subject_id'],
        );
    }

    private function assignmentKey(array $row)
    {
        return sprintf('%010d:%010d:%010d', (int) $row['class_id'], (int) $row['section_id'], (int) $row['subject_id']);
    }
}
