<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Adds the Nigerian academic-assessment workflow alongside the legacy online
 * examination tables. Existing onlineexam rows deliberately remain workflow
 * version 1 and lifecycle "legacy"; only newly-created v2 rows use the new
 * structures.
 */
class Migration_Localize_online_examination extends CI_Migration
{
    public function up()
    {
        $this->addOnlineexamFields();
        $this->addDraftQuestionFields();
        $this->addCandidateFields();
        $this->addStableKindergartenFields();

        $this->createAcademicSectionTable();
        $this->createPaperTables();
        $this->createQuestionDefinitionTable();
        $this->createRevisionSnapshotTable();
        $this->createSnapshotTable();
        $this->createAttemptTables();
        $this->createAccommodationTable();
        $this->createMarkingTable();
        $this->createResultProfileTables();
        $this->createResultTargetLockTable();
        $this->createResultSyncTable();
        $this->createIncidentTable();
        $this->createAuditTable();
    }

    public function down()
    {
        $tables = array(
            'onlineexam_audit_log',
            'onlineexam_incidents',
            'onlineexam_result_sync',
            'onlineexam_result_target_locks',
            'onlineexam_kindergarten_mappings',
            'onlineexam_result_profiles',
            'onlineexam_paper_marking',
            'onlineexam_marking',
            'onlineexam_accommodations',
            'onlineexam_attempt_answers',
            'onlineexam_attempt_papers',
            'onlineexam_candidate_attempts',
            'onlineexam_question_snapshots',
            'onlineexam_revision_snapshots',
            'onlineexam_question_definitions',
            'onlineexam_paper_sections',
            'onlineexam_papers',
            'onlineexam_class_sections',
        );

        foreach ($tables as $table) {
            $this->dbforge->drop_table($table, true);
        }

        $this->dropFields('kindergarten_assessment_concepts', array('stable_key', 'is_active', 'updated_at'));
        $this->dropFields('kindergarten_assessment_subjects', array('stable_key', 'is_active', 'updated_at'));
        $this->dropFields('onlineexam_students', array(
            'candidate_status', 'assigned_at', 'excluded_at', 'excluded_by', 'exclusion_reason'
        ));
        $this->dropFields('onlineexam_questions', array(
            'paper_id', 'paper_section_id', 'display_order', 'is_compulsory', 'marking_scheme',
            'authoring_json'
        ));
        $this->dropFields('onlineexam', array(
            'workflow_version', 'term', 'class_id', 'subject_id', 'purpose',
            'result_adapter', 'target_component', 'result_type', 'target_max_score',
            'revision', 'lifecycle_status', 'feedback_status', 'result_config_snapshot',
            'frozen_at', 'published_at', 'created_by', 'lifecycle_checked_at'
        ));
    }

    protected function addOnlineexamFields()
    {
        $this->addFields('onlineexam', array(
            'workflow_version' => array('type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1),
            'term' => array('type' => 'VARCHAR', 'constraint' => 10, 'null' => true),
            'class_id' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'subject_id' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'purpose' => array('type' => 'VARCHAR', 'constraint' => 32, 'null' => true),
            'result_adapter' => array('type' => 'VARCHAR', 'constraint' => 32, 'default' => 'unlinked_practice'),
            'target_component' => array('type' => 'VARCHAR', 'constraint' => 16, 'null' => true),
            'result_type' => array('type' => 'VARCHAR', 'constraint' => 20, 'null' => true),
            'target_max_score' => array('type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true),
            'revision' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 1),
            'lifecycle_status' => array('type' => 'VARCHAR', 'constraint' => 24, 'default' => 'legacy'),
            'feedback_status' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'hidden'),
            'result_config_snapshot' => array('type' => 'LONGTEXT', 'null' => true),
            'frozen_at' => array('type' => 'DATETIME', 'null' => true),
            'published_at' => array('type' => 'DATETIME', 'null' => true),
            'created_by' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'lifecycle_checked_at' => array('type' => 'DATETIME', 'null' => true),
        ));
    }

    protected function addDraftQuestionFields()
    {
        $this->addFields('onlineexam_questions', array(
            'paper_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true),
            'paper_section_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true),
            'display_order' => array('type' => 'INT', 'constraint' => 11, 'default' => 0),
            'is_compulsory' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'marking_scheme' => array('type' => 'LONGTEXT', 'null' => true),
            // V2-native questions keep their structured, editable definition
            // on the assessment assignment. The shared legacy question bank
            // remains readable and does not need a destructive schema change.
            'authoring_json' => array('type' => 'LONGTEXT', 'null' => true),
        ));
    }

    protected function addCandidateFields()
    {
        $this->addFields('onlineexam_students', array(
            'candidate_status' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'assigned'),
            'assigned_at' => array('type' => 'DATETIME', 'null' => true),
            'excluded_at' => array('type' => 'DATETIME', 'null' => true),
            'excluded_by' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'exclusion_reason' => array('type' => 'TEXT', 'null' => true),
        ));
    }

    protected function addStableKindergartenFields()
    {
        $fields = array(
            'stable_key' => array('type' => 'VARCHAR', 'constraint' => 64, 'null' => true),
            'is_active' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 1),
            'updated_at' => array('type' => 'DATETIME', 'null' => true),
        );

        $this->addFields('kindergarten_assessment_subjects', $fields);
        $this->addFields('kindergarten_assessment_concepts', $fields);

        if ($this->db->table_exists('kindergarten_assessment_subjects')) {
            $this->db->query("UPDATE `kindergarten_assessment_subjects` SET `stable_key` = CONCAT('subject-', `id`) WHERE `stable_key` IS NULL OR `stable_key` = ''");
        }
        if ($this->db->table_exists('kindergarten_assessment_concepts')) {
            $this->db->query("UPDATE `kindergarten_assessment_concepts` SET `stable_key` = CONCAT('concept-', `id`) WHERE `stable_key` IS NULL OR `stable_key` = ''");
        }
    }

    protected function createAcademicSectionTable()
    {
        $this->createTable('onlineexam_class_sections', "
            CREATE TABLE `onlineexam_class_sections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `section_id` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_class_section_unique` (`onlineexam_id`, `section_id`),
                KEY `onlineexam_class_sections_section_idx` (`section_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createPaperTables()
    {
        $this->createTable('onlineexam_papers', "
            CREATE TABLE `onlineexam_papers` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `title` VARCHAR(191) NOT NULL,
                `paper_code` VARCHAR(50) DEFAULT NULL,
                `paper_type` VARCHAR(24) NOT NULL,
                `delivery_mode` VARCHAR(16) NOT NULL DEFAULT 'cbt',
                `instructions` LONGTEXT NULL,
                `starts_at` DATETIME DEFAULT NULL,
                `ends_at` DATETIME DEFAULT NULL,
                `duration_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `raw_max_score` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `contribution_score` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `display_order` INT NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `onlineexam_papers_exam_idx` (`onlineexam_id`),
                KEY `onlineexam_papers_order_idx` (`onlineexam_id`, `display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('onlineexam_paper_sections', "
            CREATE TABLE `onlineexam_paper_sections` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `paper_id` INT UNSIGNED NOT NULL,
                `title` VARCHAR(191) NOT NULL,
                `instructions` LONGTEXT NULL,
                `answer_rule` VARCHAR(32) NOT NULL DEFAULT 'all',
                `answer_count` INT UNSIGNED DEFAULT NULL,
                `display_order` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `onlineexam_paper_sections_paper_idx` (`paper_id`),
                KEY `onlineexam_paper_sections_order_idx` (`paper_id`, `display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createSnapshotTable()
    {
        $this->createTable('onlineexam_question_snapshots', "
            CREATE TABLE `onlineexam_question_snapshots` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `paper_id` INT UNSIGNED NOT NULL,
                `paper_section_id` INT UNSIGNED DEFAULT NULL,
                `source_question_id` INT DEFAULT NULL,
                `revision` INT UNSIGNED NOT NULL,
                `question_type` VARCHAR(100) NOT NULL,
                `question_text` LONGTEXT NOT NULL,
                `options_json` LONGTEXT NULL,
                `correct_answer_json` LONGTEXT NULL,
                `response_schema_json` LONGTEXT NULL,
                `passage_group_key` VARCHAR(64) DEFAULT NULL,
                `passage_title` VARCHAR(191) DEFAULT NULL,
                `passage_text` LONGTEXT NULL,
                `marking_scheme` LONGTEXT NULL,
                `marks` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `neg_marks` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `is_compulsory` TINYINT(1) NOT NULL DEFAULT 0,
                `display_order` INT NOT NULL DEFAULT 0,
                `checksum` CHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_snapshot_source_unique` (`onlineexam_id`, `revision`, `paper_id`, `source_question_id`),
                KEY `onlineexam_snapshot_paper_idx` (`paper_id`, `paper_section_id`, `display_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createQuestionDefinitionTable()
    {
        $this->createTable('onlineexam_question_definitions', "
            CREATE TABLE `onlineexam_question_definitions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `question_id` INT NOT NULL,
                `definition_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `definition_json` LONGTEXT NOT NULL,
                `created_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_question_definition_source_unique` (`question_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createRevisionSnapshotTable()
    {
        $this->createTable('onlineexam_revision_snapshots', "
            CREATE TABLE `onlineexam_revision_snapshots` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `revision` INT UNSIGNED NOT NULL,
                `configuration_json` LONGTEXT NOT NULL,
                `checksum` CHAR(64) NOT NULL,
                `frozen_by` INT DEFAULT NULL,
                `frozen_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_revision_snapshot_unique` (`onlineexam_id`, `revision`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createAttemptTables()
    {
        $this->createTable('onlineexam_candidate_attempts', "
            CREATE TABLE `onlineexam_candidate_attempts` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `onlineexam_student_id` INT NOT NULL,
                `revision` INT UNSIGNED NOT NULL,
                `attempt_no` INT UNSIGNED NOT NULL DEFAULT 1,
                `status` VARCHAR(24) NOT NULL DEFAULT 'in_progress',
                `started_at` DATETIME NOT NULL,
                `deadline_at` DATETIME NOT NULL,
                `submitted_at` DATETIME DEFAULT NULL,
                `last_saved_at` DATETIME DEFAULT NULL,
                `submission_key` VARCHAR(64) DEFAULT NULL,
                `extra_time_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `raw_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `raw_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `weighted_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `weighted_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `final_score` DECIMAL(10,2) DEFAULT NULL,
                `outcome_value` VARCHAR(100) DEFAULT NULL,
                `manual_marking_required` TINYINT(1) NOT NULL DEFAULT 0,
                `marking_status` VARCHAR(24) NOT NULL DEFAULT 'not_required',
                `voided_at` DATETIME DEFAULT NULL,
                `voided_by` INT DEFAULT NULL,
                `void_reason` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_candidate_attempt_no_unique` (`onlineexam_student_id`, `attempt_no`),
                UNIQUE KEY `onlineexam_submission_key_unique` (`submission_key`),
                KEY `onlineexam_candidate_attempt_exam_idx` (`onlineexam_id`, `status`),
                KEY `onlineexam_candidate_attempt_deadline_idx` (`deadline_at`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('onlineexam_attempt_papers', "
            CREATE TABLE `onlineexam_attempt_papers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attempt_id` BIGINT UNSIGNED NOT NULL,
                `paper_id` INT UNSIGNED NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
                `started_at` DATETIME DEFAULT NULL,
                `deadline_at` DATETIME DEFAULT NULL,
                `submitted_at` DATETIME DEFAULT NULL,
                `raw_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `raw_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `contribution_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `contribution_max_score` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                `manual_score` DECIMAL(12,4) DEFAULT NULL,
                `manual_marking_status` VARCHAR(24) NOT NULL DEFAULT 'not_required',
                `marked_by` INT DEFAULT NULL,
                `marked_at` DATETIME DEFAULT NULL,
                `marking_notes` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_attempt_paper_unique` (`attempt_id`, `paper_id`),
                KEY `onlineexam_attempt_papers_status_idx` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('onlineexam_attempt_answers', "
            CREATE TABLE `onlineexam_attempt_answers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attempt_id` BIGINT UNSIGNED NOT NULL,
                `question_snapshot_id` BIGINT UNSIGNED NOT NULL,
                `response_json` LONGTEXT NULL,
                `response_hash` CHAR(64) DEFAULT NULL,
                `attachment_name` VARCHAR(255) DEFAULT NULL,
                `attachment_path` VARCHAR(500) DEFAULT NULL,
                `attachment_mime` VARCHAR(100) DEFAULT NULL,
                `attachment_size` INT UNSIGNED DEFAULT NULL,
                `is_answered` TINYINT(1) NOT NULL DEFAULT 0,
                `is_correct` TINYINT(1) DEFAULT NULL,
                `auto_mark` DECIMAL(10,2) DEFAULT NULL,
                `manual_mark` DECIMAL(10,2) DEFAULT NULL,
                `final_mark` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `marking_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
                `version` INT UNSIGNED NOT NULL DEFAULT 1,
                `client_sequence` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `saved_at` DATETIME NOT NULL,
                `submitted_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_attempt_answer_unique` (`attempt_id`, `question_snapshot_id`),
                KEY `onlineexam_attempt_answers_marking_idx` (`attempt_id`, `marking_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createAccommodationTable()
    {
        $this->createTable('onlineexam_accommodations', "
            CREATE TABLE `onlineexam_accommodations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `onlineexam_student_id` INT NOT NULL,
                `extra_time_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `makeup_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `makeup_expires_at` DATETIME DEFAULT NULL,
                `notes` TEXT NULL,
                `authorized_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_accommodation_student_unique` (`onlineexam_id`, `onlineexam_student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createMarkingTable()
    {
        $this->createTable('onlineexam_marking', "
            CREATE TABLE `onlineexam_marking` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attempt_answer_id` BIGINT UNSIGNED NOT NULL,
                `marking_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `marks` DECIMAL(10,2) NOT NULL,
                `rubric_json` LONGTEXT NULL,
                `remark` TEXT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
                `marked_by` INT NOT NULL,
                `marked_at` DATETIME NOT NULL,
                `reviewed_by` INT DEFAULT NULL,
                `reviewed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_marking_version_unique` (`attempt_answer_id`, `marking_version`),
                KEY `onlineexam_marking_status_idx` (`status`, `marked_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('onlineexam_paper_marking', "
            CREATE TABLE `onlineexam_paper_marking` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `attempt_paper_id` BIGINT UNSIGNED NOT NULL,
                `marking_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `raw_marks` DECIMAL(10,2) NOT NULL,
                `rubric_json` LONGTEXT NULL,
                `remark` TEXT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
                `marked_by` INT NOT NULL,
                `marked_at` DATETIME NOT NULL,
                `reviewed_by` INT DEFAULT NULL,
                `reviewed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_paper_marking_version_unique` (`attempt_paper_id`, `marking_version`),
                KEY `onlineexam_paper_marking_status_idx` (`status`, `marked_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createResultProfileTables()
    {
        $this->createTable('onlineexam_result_profiles', "
            CREATE TABLE `onlineexam_result_profiles` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `adapter` VARCHAR(32) NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `configuration_json` LONGTEXT NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_result_profile_unique` (`onlineexam_id`, `adapter`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('onlineexam_kindergarten_mappings', "
            CREATE TABLE `onlineexam_kindergarten_mappings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `paper_id` INT UNSIGNED DEFAULT NULL,
                `paper_section_id` INT UNSIGNED DEFAULT NULL,
                `assessment_id` INT NOT NULL,
                `assessment_subject_id` INT NOT NULL,
                `subject_id` INT NOT NULL,
                `concept_id` INT NOT NULL,
                `concept_stable_key` VARCHAR(64) NOT NULL,
                `outcome_profile_json` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_kindergarten_concept_unique` (`onlineexam_id`, `concept_id`),
                KEY `onlineexam_kindergarten_mapping_idx` (`assessment_id`, `subject_id`, `concept_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createResultSyncTable()
    {
        $this->createTable('onlineexam_result_sync', "
            CREATE TABLE `onlineexam_result_sync` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `attempt_id` BIGINT UNSIGNED NOT NULL,
                `student_session_id` INT NOT NULL,
                `adapter` VARCHAR(32) NOT NULL,
                `target_table` VARCHAR(64) DEFAULT NULL,
                `target_record_id` INT DEFAULT NULL,
                `target_field` VARCHAR(64) DEFAULT NULL,
                `source_score` DECIMAL(12,4) DEFAULT NULL,
                `scaled_score` DECIMAL(10,2) DEFAULT NULL,
                `previous_value` VARCHAR(255) DEFAULT NULL,
                `applied_value` VARCHAR(255) DEFAULT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
                `conflict_reason` TEXT NULL,
                `error_message` TEXT NULL,
                `reversed_at` DATETIME DEFAULT NULL,
                `reversed_by` INT DEFAULT NULL,
                `reversal_reason` TEXT NULL,
                `override_authorized_at` DATETIME DEFAULT NULL,
                `override_authorized_by` INT DEFAULT NULL,
                `override_reason` TEXT NULL,
                `source_fingerprint` CHAR(64) NOT NULL,
                `idempotency_key` CHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `synced_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_result_sync_idempotency_unique` (`idempotency_key`),
                KEY `onlineexam_result_sync_attempt_idx` (`attempt_id`, `status`),
                KEY `onlineexam_result_sync_target_idx` (`onlineexam_id`, `student_session_id`, `adapter`, `target_field`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createResultTargetLockTable()
    {
        $this->createTable('onlineexam_result_target_locks', "
            CREATE TABLE `onlineexam_result_target_locks` (
                `target_hash` CHAR(64) NOT NULL,
                `target_descriptor` VARCHAR(255) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`target_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createIncidentTable()
    {
        $this->createTable('onlineexam_incidents', "
            CREATE TABLE `onlineexam_incidents` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `attempt_id` BIGINT UNSIGNED DEFAULT NULL,
                `onlineexam_student_id` INT DEFAULT NULL,
                `incident_type` VARCHAR(50) NOT NULL,
                `severity` VARCHAR(16) NOT NULL DEFAULT 'info',
                `details` LONGTEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                `reported_by` INT DEFAULT NULL,
                `resolved_by` INT DEFAULT NULL,
                `resolved_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `onlineexam_incidents_exam_idx` (`onlineexam_id`, `status`),
                KEY `onlineexam_incidents_attempt_idx` (`attempt_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function createAuditTable()
    {
        $this->createTable('onlineexam_audit_log', "
            CREATE TABLE `onlineexam_audit_log` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT DEFAULT NULL,
                `attempt_id` BIGINT UNSIGNED DEFAULT NULL,
                `actor_id` INT DEFAULT NULL,
                `actor_type` VARCHAR(20) DEFAULT NULL,
                `action` VARCHAR(80) NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `entity_id` VARCHAR(64) DEFAULT NULL,
                `before_json` LONGTEXT NULL,
                `after_json` LONGTEXT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `onlineexam_audit_exam_idx` (`onlineexam_id`, `created_at`),
                KEY `onlineexam_audit_attempt_idx` (`attempt_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function addFields($table, array $fields)
    {
        if (!$this->db->table_exists($table)) {
            return;
        }

        foreach ($fields as $name => $definition) {
            if (!$this->db->field_exists($name, $table)) {
                $this->dbforge->add_column($table, array($name => $definition));
            }
        }
    }

    protected function dropFields($table, array $fields)
    {
        if (!$this->db->table_exists($table)) {
            return;
        }

        foreach ($fields as $field) {
            if ($this->db->field_exists($field, $table)) {
                $this->dbforge->drop_column($table, $field);
            }
        }
    }

    protected function createTable($table, $sql)
    {
        if (!$this->db->table_exists($table)) {
            $this->db->query(trim($sql));
        }
    }
}
