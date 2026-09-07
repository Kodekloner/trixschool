<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/** Candidate-specific paper recovery without replacing an assessment attempt. */
class Migration_Onlineexam_candidate_paper_review extends CI_Migration
{
    public function up()
    {
        foreach (array('onlineexam' => array(
            'deleted_at' => array('type' => 'DATETIME', 'null' => true),
            'deleted_by' => array('type' => 'INT', 'null' => true),
        ), 'onlineexam_attempt_papers' => array(
            'completion_source' => array('type' => 'VARCHAR', 'constraint' => 24, 'null' => true),
        )) as $table => $fields) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('Install the Online Examination schema before migration 137.');
            }
            foreach ($fields as $name => $definition) {
                if (!$this->db->field_exists($name, $table)
                    && !$this->dbforge->add_column($table, array($name => $definition))) {
                    throw new RuntimeException('Unable to add ' . $table . '.' . $name . '.');
                }
            }
            unset($this->db->data_cache['field_names'][$table]);
        }

        if ($this->db->table_exists('onlineexam_questions') && $this->db->field_exists('is_compulsory', 'onlineexam_questions')) {
            $this->db->query('ALTER TABLE `onlineexam_questions` ALTER COLUMN `is_compulsory` SET DEFAULT 1');
        }
        if ($this->db->table_exists('onlineexam_papers') && $this->db->table_exists('onlineexam_candidate_attempts')) {
            $this->db->query("UPDATE onlineexam_papers p JOIN onlineexam e ON e.id = p.onlineexam_id
                SET p.raw_max_score = e.target_max_score
                WHERE e.workflow_version = 2 AND e.lifecycle_status = 'draft' AND e.frozen_at IS NULL
                  AND e.target_max_score > 0 AND NOT EXISTS (
                    SELECT 1 FROM onlineexam_candidate_attempts a
                    WHERE a.onlineexam_id = e.id AND a.revision = e.revision
                  )");
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `onlineexam_candidate_paper_windows` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `onlineexam_id` INT NOT NULL,
            `onlineexam_student_id` INT NOT NULL,
            `paper_id` INT UNSIGNED NOT NULL,
            `revision` INT UNSIGNED NOT NULL,
            `starts_at` DATETIME NOT NULL,
            `ends_at` DATETIME NOT NULL,
            `reason` TEXT NOT NULL,
            `scheduled_by` INT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `onlineexam_candidate_paper_window_unique` (`onlineexam_id`,`onlineexam_student_id`,`paper_id`),
            KEY `onlineexam_candidate_paper_window_end_idx` (`onlineexam_id`,`ends_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `onlineexam_candidate_paper_history` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `onlineexam_id` INT NOT NULL,
            `onlineexam_student_id` INT NOT NULL,
            `attempt_id` BIGINT UNSIGNED NOT NULL,
            `paper_id` INT UNSIGNED NOT NULL,
            `action` VARCHAR(32) NOT NULL,
            `reason` TEXT NOT NULL,
            `before_json` LONGTEXT NOT NULL,
            `after_json` LONGTEXT NOT NULL,
            `actor_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `onlineexam_candidate_paper_history_idx` (`attempt_id`,`paper_id`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        unset($this->db->data_cache['table_names']);
    }

    public function down()
    {
        // Retain recovery and removal history on rollback. Deleting it would
        // make restored scores and past examination work impossible to audit.
    }
}
