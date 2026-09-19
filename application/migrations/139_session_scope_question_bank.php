<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/** Session/term ownership and assignment-scoped permissions for Question Bank. */
class Migration_Session_scope_question_bank extends CI_Migration
{
    public function up()
    {
        foreach (array('questions', 'sessions', 'sch_settings') as $table) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('Install the academic and Question Bank schema before migration 139.');
            }
        }

        $columns = array(
            'session_id' => array('type' => 'INT', 'null' => true, 'after' => 'staff_id'),
            'term' => array('type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'session_id'),
            'context_root_id' => array('type' => 'INT', 'null' => true, 'after' => 'term'),
        );
        foreach ($columns as $name => $definition) {
            if (!$this->db->field_exists($name, 'questions')
                && !$this->dbforge->add_column('questions', array($name => $definition))) {
                throw new RuntimeException('Unable to add questions.' . $name . '.');
            }
        }
        unset($this->db->data_cache['field_names']['questions']);

        $setting = $this->db->select('session_id, term')->limit(1)->get('sch_settings')->row_array();
        $fallback_session = !empty($setting['session_id']) ? (int) $setting['session_id'] : 0;
        if ($fallback_session < 1) {
            $session = $this->db->select('id')->order_by('id', 'DESC')->limit(1)->get('sessions')->row_array();
            $fallback_session = !empty($session['id']) ? (int) $session['id'] : 1;
        }
        $fallback_term = !empty($setting['term']) && in_array(strtolower($setting['term']), array('1st', '2nd', '3rd'), true)
            ? strtolower($setting['term']) : '1st';

        $this->db->trans_begin();
        $this->db->query('UPDATE `questions` SET `context_root_id`=`id` WHERE `context_root_id` IS NULL OR `context_root_id`=0');
        $questions = $this->db->where('session_id IS NULL', null, false)
            ->or_where('session_id <=', 0)
            ->or_where('term IS NULL', null, false)
            ->or_where_not_in('term', array('1st', '2nd', '3rd'))
            ->get('questions')->result_array();

        foreach ($questions as $question) {
            $contexts = array();
            if ($this->db->table_exists('onlineexam_questions') && $this->db->table_exists('onlineexam')) {
                $rows = $this->db->select('e.session_id, LOWER(e.term) AS term, MIN(e.id) AS first_exam_id', false)
                    ->from('onlineexam_questions oq')
                    ->join('onlineexam e', 'e.id = oq.onlineexam_id')
                    ->where('oq.question_id', (int) $question['id'])
                    ->where('e.session_id >', 0)
                    ->where_in('LOWER(e.term)', array('1st', '2nd', '3rd'))
                    ->group_by(array('e.session_id', 'LOWER(e.term)'))
                    ->order_by('first_exam_id', 'ASC')
                    ->get()->result_array();
                foreach ($rows as $row) {
                    $contexts[] = array('session_id' => (int) $row['session_id'], 'term' => strtolower($row['term']));
                }
            }
            if (empty($contexts)) {
                $contexts[] = array('session_id' => $fallback_session, 'term' => $fallback_term);
            }

            $root_id = (int) $question['id'];
            $primary = array_shift($contexts);
            $this->db->where('id', $root_id)->update('questions', array(
                'session_id' => $primary['session_id'],
                'term' => $primary['term'],
                'context_root_id' => $root_id,
            ));

            foreach ($contexts as $context) {
                $copy = $this->db->select('id')->where(array(
                    'context_root_id' => $root_id,
                    'session_id' => $context['session_id'],
                    'term' => $context['term'],
                ))->limit(1)->get('questions')->row_array();
                if (empty($copy)) {
                    $copy_data = $question;
                    unset($copy_data['id']);
                    $copy_data['session_id'] = $context['session_id'];
                    $copy_data['term'] = $context['term'];
                    $copy_data['context_root_id'] = $root_id;
                    $this->db->insert('questions', $copy_data);
                    $copy_id = (int) $this->db->insert_id();
                    $this->cloneQuestionChildren($root_id, $copy_id);
                } else {
                    $copy_id = (int) $copy['id'];
                }
                $this->remapSourceAssignments($root_id, $copy_id, $context['session_id'], $context['term']);
            }
        }

        // A previous partial/manual migration must never leave unscoped rows.
        $this->db->where('session_id IS NULL', null, false)->or_where('session_id <=', 0)
            ->update('questions', array('session_id' => $fallback_session));
        $this->db->where_not_in('term', array('1st', '2nd', '3rd'))
            ->or_where('term IS NULL', null, false)
            ->update('questions', array('term' => $fallback_term));
        $this->db->query('UPDATE `questions` q INNER JOIN `class_sections` cs'
            . ' ON cs.class_id=q.class_id AND cs.section_id=q.section_id'
            . ' SET q.class_section_id=cs.id WHERE q.section_id>0'
            . ' AND (q.class_section_id IS NULL OR q.class_section_id<>cs.id)');

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            throw new RuntimeException('Question Bank academic-context backfill failed.');
        }
        $this->db->trans_commit();

        $this->db->query('ALTER TABLE `questions` MODIFY `session_id` INT NOT NULL, MODIFY `term` VARCHAR(10) NOT NULL');
        if (!$this->indexExists('questions', 'question_academic_scope_idx')) {
            $this->db->query('ALTER TABLE `questions` ADD KEY `question_academic_scope_idx` (`session_id`,`term`,`class_id`,`section_id`,`subject_id`)');
        }
        if (!$this->indexExists('questions', 'question_context_root_idx')) {
            $this->db->query('ALTER TABLE `questions` ADD KEY `question_context_root_idx` (`context_root_id`,`session_id`,`term`)');
        }

        $this->seedRolePermissions();
        unset($this->db->data_cache['field_names']['questions']);
    }

    public function down()
    {
        // Session ownership, copied question definitions and permission grants
        // are retained because removing them would merge distinct exam history.
    }

    private function cloneQuestionChildren($source_id, $copy_id)
    {
        if ($this->db->table_exists('onlineexam_question_definitions')) {
            $definitions = $this->db->where('question_id', (int) $source_id)
                ->get('onlineexam_question_definitions')->result_array();
            foreach ($definitions as $definition) {
                unset($definition['id']);
                $definition['question_id'] = (int) $copy_id;
                $this->db->insert('onlineexam_question_definitions', $definition);
            }
        }

        $option_map = array();
        if ($this->db->table_exists('question_options')) {
            $options = $this->db->where('question_id', (int) $source_id)
                ->order_by('id', 'ASC')->get('question_options')->result_array();
            foreach ($options as $option) {
                $old_option_id = (int) $option['id'];
                unset($option['id']);
                $option['question_id'] = (int) $copy_id;
                $this->db->insert('question_options', $option);
                $option_map[$old_option_id] = (int) $this->db->insert_id();
            }
        }
        if ($this->db->table_exists('question_answers')) {
            $answers = $this->db->where('question_id', (int) $source_id)
                ->order_by('id', 'ASC')->get('question_answers')->result_array();
            foreach ($answers as $answer) {
                $old_option_id = (int) $answer['option_id'];
                if (!isset($option_map[$old_option_id])) {
                    continue;
                }
                unset($answer['id']);
                $answer['question_id'] = (int) $copy_id;
                $answer['option_id'] = $option_map[$old_option_id];
                $this->db->insert('question_answers', $answer);
            }
        }
    }

    private function remapSourceAssignments($source_id, $copy_id, $session_id, $term)
    {
        if (!$this->db->table_exists('onlineexam_questions') || !$this->db->table_exists('onlineexam')) {
            return;
        }
        $exam_ids = array_map('intval', array_column($this->db->select('e.id')
            ->from('onlineexam e')
            ->where('e.session_id', (int) $session_id)
            ->where('LOWER(e.term)', strtolower($term))
            ->get()->result_array(), 'id'));
        if (!empty($exam_ids)) {
            $this->db->where('question_id', (int) $source_id)
                ->where_in('onlineexam_id', $exam_ids)
                ->update('onlineexam_questions', array('question_id' => (int) $copy_id));
        }
    }

    private function seedRolePermissions()
    {
        if (!$this->db->table_exists('roles') || !$this->db->table_exists('roles_permissions')
            || !$this->db->table_exists('permission_category')) {
            return;
        }
        $grants = array(
            'question_bank' => array(1, 1, 1, 1),
            'import_question' => array(1, 0, 0, 0),
            'online_examination' => array(1, 1, 1, 1),
            'add_questions_in_exam' => array(1, 1, 1, 1),
            'online_assign_view_student' => array(1, 0, 1, 0),
        );
        $roles = $this->db->select('id')->where_in('LOWER(name)', array('admin', 'head teacher', 'teacher'))
            ->get('roles')->result_array();
        foreach ($grants as $short_code => $flags) {
            $permission = $this->db->select('id')->where('short_code', $short_code)
                ->limit(1)->get('permission_category')->row_array();
            if (empty($permission)) {
                continue;
            }
            foreach ($roles as $role) {
                $where = array('role_id' => (int) $role['id'], 'perm_cat_id' => (int) $permission['id']);
                $data = array('can_view' => $flags[0], 'can_add' => $flags[1], 'can_edit' => $flags[2], 'can_delete' => $flags[3]);
                if ($this->db->where($where)->count_all_results('roles_permissions') > 0) {
                    $this->db->where($where)->update('roles_permissions', $data);
                } else {
                    $this->db->insert('roles_permissions', array_merge($where, $data, array('created_at' => date('Y-m-d H:i:s'))));
                }
            }
        }
    }

    private function indexExists($table, $index)
    {
        return $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE()'
            . ' AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
            array($table, $index)
        )->num_rows() > 0;
    }
}
