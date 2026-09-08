<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reserves one Online Examination subject/component slot for each class arm.
 *
 * Historical overlapping assessments are left intact. During the backfill,
 * the oldest assessment owns a duplicated slot; a newer conflicting record
 * remains readable but must be corrected before it can be edited/published.
 */
class Migration_Onlineexam_single_subject_slots extends CI_Migration
{
    public function up()
    {
        foreach (array('onlineexam', 'onlineexam_class_sections') as $table) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('Install the Online Examination schema before migration 138.');
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `onlineexam_academic_slots` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `onlineexam_id` INT NOT NULL,
            `session_id` INT NOT NULL,
            `term` VARCHAR(10) NOT NULL,
            `class_id` INT NOT NULL,
            `section_id` INT NOT NULL,
            `assessment_type` VARCHAR(24) NOT NULL,
            `component_key` VARCHAR(32) NOT NULL,
            `subject_id` INT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `onlineexam_academic_slot_unique`
                (`session_id`,`term`,`class_id`,`section_id`,`assessment_type`,`component_key`,`subject_id`),
            UNIQUE KEY `onlineexam_academic_slot_exam_section_unique` (`onlineexam_id`,`section_id`),
            KEY `onlineexam_academic_slot_exam_idx` (`onlineexam_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // INSERT IGNORE deliberately preserves pre-migration duplicates. The
        // deterministic oldest row owns the slot; no examination data is lost.
        $deleted_filter = $this->db->field_exists('deleted_at', 'onlineexam')
            ? ' AND e.deleted_at IS NULL' : '';
        $this->db->query("INSERT IGNORE INTO `onlineexam_academic_slots`
            (`onlineexam_id`,`session_id`,`term`,`class_id`,`section_id`,`assessment_type`,`component_key`,`subject_id`,`created_at`,`updated_at`)
            SELECT e.id, e.session_id, LOWER(e.term), e.class_id, ecs.section_id,
                CASE WHEN e.purpose IN ('ca','kindergarten') THEN 'term'
                     WHEN e.purpose = 'midterm' THEN 'midterm'
                     WHEN e.purpose = 'holiday' THEN 'holiday' END,
                COALESCE(NULLIF(LOWER(e.target_component),''), LOWER(e.purpose)),
                e.subject_id, NOW(), NOW()
            FROM onlineexam e
            INNER JOIN onlineexam_class_sections ecs ON ecs.onlineexam_id = e.id
            WHERE e.workflow_version = 2
              AND e.session_id IS NOT NULL AND e.class_id IS NOT NULL AND e.subject_id IS NOT NULL
              AND LOWER(e.term) IN ('1st','2nd','3rd')
              AND e.purpose IN ('ca','midterm','holiday','kindergarten')
              AND COALESCE(NULLIF(LOWER(e.target_component),''), LOWER(e.purpose)) <> ''"
            . $deleted_filter . " ORDER BY e.id, ecs.section_id");

        if ($this->db->table_exists('onlineexam_papers')
            && $this->db->table_exists('onlineexam_candidate_attempts')) {
            $this->db->query("UPDATE onlineexam_papers p
                INNER JOIN onlineexam e ON e.id=p.onlineexam_id
                INNER JOIN (
                    SELECT onlineexam_id FROM onlineexam_papers
                    GROUP BY onlineexam_id HAVING COUNT(*)=1
                ) one_paper ON one_paper.onlineexam_id=e.id
                SET p.contribution_score=100.00,p.updated_at=NOW()
                WHERE e.workflow_version=2 AND e.lifecycle_status='draft'
                  AND e.frozen_at IS NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM onlineexam_candidate_attempts a
                    WHERE a.onlineexam_id=e.id AND a.revision=e.revision
                  )");
        }
        unset($this->db->data_cache['table_names']);
    }

    public function down()
    {
        // Keep the reservation table on rollback. Dropping it would reopen the
        // duplicate-subject race while older application code is still live.
    }
}
