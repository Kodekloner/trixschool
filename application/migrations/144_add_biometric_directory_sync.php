<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Durable SchoolLift-to-ZKBio directory synchronization state.
 *
 * The SchoolLift database owns desired identities while the Windows gateway
 * owns provider communication. These tables keep provider links and bounded
 * run summaries server-side so a gateway reinstall cannot lose ownership.
 */
class Migration_Add_biometric_directory_sync extends CI_Migration
{
    public function up()
    {
        $this->addColumn('biometric_integrations', 'directory_sync_mode', array(
            'type' => 'VARCHAR', 'constraint' => 12, 'default' => 'off',
            'after' => 'last_error',
        ));
        $this->addColumn('biometric_integrations', 'directory_sync_interval_seconds', array(
            'type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 300,
            'after' => 'directory_sync_mode',
        ));
        $this->addColumn('biometric_integrations', 'directory_delete_approval_hash', array(
            'type' => 'CHAR', 'constraint' => 64, 'null' => true,
            'after' => 'directory_sync_interval_seconds',
        ));
        $this->addColumn('biometric_integrations', 'directory_delete_approved_at', array(
            'type' => 'DATETIME', 'null' => true,
            'after' => 'directory_delete_approval_hash',
        ));
        $this->addColumn('biometric_integrations', 'last_directory_sync_at', array(
            'type' => 'DATETIME', 'null' => true,
            'after' => 'directory_delete_approved_at',
        ));
        $this->addColumn('biometric_integrations', 'last_directory_sync_status', array(
            'type' => 'VARCHAR', 'constraint' => 24, 'null' => true,
            'after' => 'last_directory_sync_at',
        ));
        $this->addColumn('biometric_integrations', 'last_directory_sync_error', array(
            'type' => 'VARCHAR', 'constraint' => 500, 'null' => true,
            'after' => 'last_directory_sync_status',
        ));

        if (!$this->db->table_exists('biometric_directory_links')) {
            $this->db->query("CREATE TABLE `biometric_directory_links` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED NOT NULL,
                `subject_type` VARCHAR(12) NOT NULL,
                `subject_key` INT NOT NULL,
                `subject_id` INT DEFAULT NULL,
                `external_person_code` VARCHAR(100) NOT NULL,
                `provider_person_id` VARCHAR(64) DEFAULT NULL,
                `desired_hash` CHAR(64) DEFAULT NULL,
                `applied_hash` CHAR(64) DEFAULT NULL,
                `sync_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
                `last_error` VARCHAR(500) DEFAULT NULL,
                `last_seen_snapshot` CHAR(64) DEFAULT NULL,
                `last_synced_at` DATETIME DEFAULT NULL,
                `deleted_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_directory_subject` (`integration_id`, `subject_type`, `subject_key`),
                UNIQUE KEY `uq_biometric_directory_code` (`integration_id`, `external_person_code`),
                KEY `idx_biometric_directory_status` (`integration_id`, `sync_status`),
                KEY `idx_biometric_directory_provider` (`integration_id`, `provider_person_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$this->db->table_exists('biometric_directory_runs')) {
            $this->db->query("CREATE TABLE `biometric_directory_runs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED NOT NULL,
                `gateway_id` VARCHAR(128) NOT NULL,
                `snapshot_id` CHAR(32) NOT NULL,
                `snapshot_hash` CHAR(64) NOT NULL,
                `mode` VARCHAR(12) NOT NULL,
                `status` VARCHAR(24) NOT NULL,
                `desired_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `adopted_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `deleted_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `unchanged_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `conflict_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `delete_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `requires_delete_approval` TINYINT(1) NOT NULL DEFAULT 0,
                `error_summary` VARCHAR(500) DEFAULT NULL,
                `started_at` DATETIME NOT NULL,
                `completed_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_directory_run` (`integration_id`, `gateway_id`, `snapshot_id`),
                KEY `idx_biometric_directory_run_status` (`integration_id`, `status`, `completed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down()
    {
        foreach (array('biometric_directory_runs', 'biometric_directory_links') as $table) {
            if ($this->db->table_exists($table)) {
                $this->dbforge->drop_table($table, true);
            }
        }
        if ($this->db->table_exists('biometric_integrations')) {
            foreach (array(
                'last_directory_sync_error', 'last_directory_sync_status', 'last_directory_sync_at',
                'directory_delete_approved_at', 'directory_delete_approval_hash',
                'directory_sync_interval_seconds', 'directory_sync_mode'
            ) as $field) {
                if ($this->db->field_exists($field, 'biometric_integrations')) {
                    $this->dbforge->drop_column('biometric_integrations', $field);
                }
            }
        }
    }

    private function addColumn($table, $field, array $definition)
    {
        if ($this->db->table_exists($table) && !$this->db->field_exists($field, $table)) {
            $this->dbforge->add_column($table, array($field => $definition));
        }
    }
}
