<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * One academic-scope policy for Question Bank and Online Examination staff.
 *
 * RBAC remains the feature/action gate. This model is the mandatory row-level
 * gate and deliberately uses the session-specific assignment tables instead
 * of a hard-coded role id.
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
        $query = $this->db->distinct()->select('class_sections.section_id')
            ->from('teacher_subjects')
            ->join('class_sections', 'class_sections.id = teacher_subjects.class_section_id')
            ->where('teacher_subjects.teacher_id', $this->staffId())
            ->where('teacher_subjects.session_id', (int) $session_id);
        if ((int) $class_id > 0) {
            $query->where('class_sections.class_id', (int) $class_id);
        }
        if ((int) $subject_id > 0) {
            $query->where('teacher_subjects.subject_id', (int) $subject_id);
        }
        return array_values(array_unique(array_map('intval', array_column(
            $query->get()->result_array(), 'section_id'
        ))));
    }

    /** Classes visible through either a class-teacher or subject-teacher assignment. */
    public function classChoicesFor($mode, $session_id)
    {
        if ($this->isAcademicAdministrator()) {
            return $this->db->select('id, class')->order_by('id')->get('classes')->result_array();
        }

        $staff_id = $this->staffId();
        $session_id = (int) $session_id;
        if (in_array($mode, array('content', 'mark'), true)) {
            return $this->db->distinct()->select('classes.id, classes.class')
                ->from('classes')
                ->join('class_sections', 'class_sections.class_id=classes.id')
                ->join('teacher_subjects access_ts', 'access_ts.class_section_id=class_sections.id')
                ->where('access_ts.teacher_id', $staff_id)
                ->where('access_ts.session_id', $session_id)
                ->order_by('classes.id')->get()->result_array();
        }

        $sql = 'SELECT DISTINCT classes.id, classes.class FROM classes '
            . 'WHERE EXISTS (SELECT 1 FROM class_teacher access_ct '
            . 'WHERE access_ct.class_id=classes.id AND access_ct.staff_id=? AND access_ct.session_id=?) '
            . 'OR EXISTS (SELECT 1 FROM class_sections access_cs '
            . 'INNER JOIN teacher_subjects access_ts ON access_ts.class_section_id=access_cs.id '
            . 'WHERE access_cs.class_id=classes.id AND access_ts.teacher_id=? AND access_ts.session_id=?) '
            . 'ORDER BY classes.id';
        return $this->db->query($sql, array($staff_id, $session_id, $staff_id, $session_id))->result_array();
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
        return '(EXISTS (SELECT 1 FROM class_teacher access_ct'
            . ' WHERE access_ct.staff_id=' . $staff_id
            . ' AND access_ct.session_id=' . $session_id
            . ' AND access_ct.class_id=' . $alias . '.class_id'
            . ' AND (' . $alias . '.section_id=0 OR access_ct.section_id=' . $alias . '.section_id))'
            . ' OR EXISTS (SELECT 1 FROM teacher_subjects access_ts'
            . ' INNER JOIN class_sections access_cs ON access_cs.id=access_ts.class_section_id'
            . ' WHERE access_ts.teacher_id=' . $staff_id
            . ' AND access_ts.session_id=' . $session_id
            . ' AND access_ts.subject_id=' . $alias . '.subject_id'
            . ' AND access_cs.class_id=' . $alias . '.class_id'
            . ' AND (' . $alias . '.section_id=0 OR access_cs.section_id=' . $alias . '.section_id)))';
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
}
