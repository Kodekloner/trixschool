<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Narrows new Online Examination authoring to the compact academic-assessment
 * flow and adds explicit Holiday Assessment mapping/provenance.
 */
class Migration_Compact_online_examination extends CI_Migration
{
    public function up()
    {
        $this->createHolidayMappingTable();
        $this->addHolidayScoreProvenance();
        $this->addSyncMetadata();

        if ($this->db->table_exists('onlineexam')
            && $this->db->field_exists('result_adapter', 'onlineexam')) {
            $this->db->query("ALTER TABLE `onlineexam` MODIFY `result_adapter` VARCHAR(32) NOT NULL DEFAULT 'standard_component'");
            $retired = array('result_adapter' => 'legacy_read_only');
            if ($this->db->field_exists('lifecycle_status', 'onlineexam')) {
                $retired['lifecycle_status'] = 'legacy';
            }
            if ($this->db->field_exists('is_active', 'onlineexam')) {
                $retired['is_active'] = 0;
            }
            $this->db->where('result_adapter', 'unlinked_practice')->update('onlineexam', $retired);
        }
    }

    public function down()
    {
        $this->dbforge->drop_table('onlineexam_holiday_mappings', true);
        $this->dropFields('onlineexam_result_sync', array(
            'previous_metadata_json', 'applied_metadata_json'
        ));
        $this->dropFields('holiday_assessment_scores', array(
            'score_origin', 'source_onlineexam_id', 'source_attempt_id',
            'source_sync_id', 'updated_at'
        ));

        if ($this->db->table_exists('onlineexam')
            && $this->db->field_exists('result_adapter', 'onlineexam')) {
            $this->db->query("ALTER TABLE `onlineexam` MODIFY `result_adapter` VARCHAR(32) NOT NULL DEFAULT 'standard_component'");
        }
    }

    private function createHolidayMappingTable()
    {
        if ($this->db->table_exists('onlineexam_holiday_mappings')) {
            return;
        }
        $this->db->query("
            CREATE TABLE `onlineexam_holiday_mappings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `onlineexam_id` INT NOT NULL,
                `section_id` INT NOT NULL,
                `setting_id` INT NOT NULL,
                `setting_subject_id` INT NOT NULL,
                `max_score` DECIMAL(10,2) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `onlineexam_holiday_mapping_section_unique` (`onlineexam_id`, `section_id`),
                KEY `onlineexam_holiday_mapping_setting_idx` (`setting_id`, `setting_subject_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function addHolidayScoreProvenance()
    {
        if (!$this->db->table_exists('holiday_assessment_scores')) {
            return;
        }
        $this->addFields('holiday_assessment_scores', array(
            'score_origin' => array(
                'type' => 'VARCHAR', 'constraint' => 24, 'default' => 'manual',
                'null' => false, 'after' => 'max_score'
            ),
            'source_onlineexam_id' => array(
                'type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'score_origin'
            ),
            'source_attempt_id' => array(
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
                'after' => 'source_onlineexam_id'
            ),
            'source_sync_id' => array(
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true,
                'after' => 'source_attempt_id'
            ),
            'updated_at' => array('type' => 'DATETIME', 'null' => true, 'after' => 'source_sync_id'),
        ));

        // New explicit writes label placeholders/manual values. Historical rows
        // retain the safe default "manual" because a legacy zero is ambiguous.
        $this->db->query("UPDATE `holiday_assessment_scores` SET `score_origin` = 'manual' WHERE `score_origin` IS NULL OR `score_origin` = ''");
        $this->addIndex(
            'holiday_assessment_scores',
            'holiday_assessment_scores_source_idx',
            '(`source_onlineexam_id`, `source_attempt_id`)'
        );
        $this->addIndex(
            'holiday_assessment_scores',
            'holiday_assessment_scores_source_sync_unique',
            '(`source_sync_id`)',
            true
        );
    }

    private function addSyncMetadata()
    {
        $this->addFields('onlineexam_result_sync', array(
            'previous_metadata_json' => array(
                'type' => 'LONGTEXT', 'null' => true, 'after' => 'previous_value'
            ),
            'applied_metadata_json' => array(
                'type' => 'LONGTEXT', 'null' => true, 'after' => 'applied_value'
            ),
        ));
    }

    private function addFields($table, array $fields)
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

    private function dropFields($table, array $fields)
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

    private function addIndex($table, $name, $columns, $unique = false)
    {
        if ($this->indexExists($table, $name)) {
            return;
        }
        $this->db->query(
            'ALTER TABLE `' . $table . '` ADD ' . ($unique ? 'UNIQUE ' : '')
            . 'KEY `' . $name . '` ' . $columns
        );
    }

    private function indexExists($table, $name)
    {
        return $this->db->query(
            'SHOW INDEX FROM `' . $table . '` WHERE `Key_name` = ' . $this->db->escape($name)
        )->num_rows() > 0;
    }
}
