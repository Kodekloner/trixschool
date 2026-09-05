<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Remove the retired question-difficulty field without deleting questions,
 * their options/answers, exam assignments, attempts, or result history.
 */
class Migration_Remove_question_level extends CI_Migration
{
    public function up()
    {
        if (!$this->db->table_exists('questions')
            || !$this->db->field_exists('level', 'questions')) {
            return;
        }

        if (!$this->dbforge->drop_column('questions', 'level')) {
            throw new RuntimeException('Unable to remove the retired questions.level column.');
        }
        unset($this->db->data_cache['field_names']['questions']);
    }

    public function down()
    {
        if (!$this->db->table_exists('questions')
            || $this->db->field_exists('level', 'questions')) {
            return;
        }

        // Rollback restores the old column structure only. Removed difficulty
        // labels can be recovered only from the backup taken before upgrade.
        if (!$this->dbforge->add_column('questions', array(
            'level' => array(
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => false,
                'default' => '',
                'after' => 'question_type',
            ),
        ))) {
            throw new RuntimeException('Unable to restore the legacy questions.level column.');
        }
        unset($this->db->data_cache['field_names']['questions']);
    }
}
