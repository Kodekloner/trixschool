<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Production biometric attendance storage.
 *
 * The tables intentionally do not contain biometric templates, photographs or
 * other biometric material. They store attendance transaction metadata only.
 */
class Migration_Add_biometric_attendance extends CI_Migration
{
    public function up()
    {
        $this->createTables();
        $this->repairExistingTables();
        $this->addLegacyAttendanceLinks();
        $this->seedDefaults();
        $this->seedPermission();
    }

    public function down()
    {
        // Retain the permission category on rollback: an installation may have
        // owned the same short code before this migration was introduced.
        $this->dropLegacyAttendanceLinks();

        $tables = array(
            'biometric_reconciliation_actions',
            'biometric_exceptions',
            'biometric_events',
            'biometric_attendance_days',
            'biometric_gateway_cursors',
            'biometric_gateway_batches',
            'biometric_punch_state_mappings',
            'biometric_devices',
            'biometric_identity_mappings',
            'biometric_qr_credentials',
            'biometric_scanner_stations',
            'biometric_integrations',
            'biometric_audit_logs',
            'biometric_settings',
        );

        foreach ($tables as $table) {
            $this->dbforge->drop_table($table, true);
        }
    }

    protected function createTables()
    {
        $this->createTable('biometric_settings', "
            CREATE TABLE `biometric_settings` (
                `id` TINYINT UNSIGNED NOT NULL,
                `mode` VARCHAR(20) NOT NULL DEFAULT 'disabled',
                `timezone` VARCHAR(64) NOT NULL DEFAULT 'Africa/Lagos',
                `student_late_after` TIME NOT NULL DEFAULT '08:00:00',
                `staff_late_after` TIME NOT NULL DEFAULT '08:00:00',
                `student_present_type_id` INT NOT NULL DEFAULT 1,
                `student_late_type_id` INT NOT NULL DEFAULT 3,
                `staff_present_type_id` INT NOT NULL DEFAULT 1,
                `staff_late_type_id` INT NOT NULL DEFAULT 2,
                `project_students` TINYINT(1) NOT NULL DEFAULT 1,
                `project_staff` TINYINT(1) NOT NULL DEFAULT 1,
                `retention_days` SMALLINT UNSIGNED NOT NULL DEFAULT 730,
                `max_event_age_days` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_integrations', "
            CREATE TABLE `biometric_integrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `provider` VARCHAR(40) NOT NULL DEFAULT 'zkbio_time',
                `endpoint_url` VARCHAR(500) DEFAULT NULL,
                `token_prefix` VARCHAR(32) DEFAULT NULL,
                `token_hash` CHAR(64) DEFAULT NULL,
                `token_version` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_seen_at` DATETIME DEFAULT NULL,
                `last_cursor` VARCHAR(191) DEFAULT NULL,
                `last_error` VARCHAR(500) DEFAULT NULL,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_integration_token_prefix` (`token_prefix`),
                KEY `idx_biometric_integration_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_devices', "
            CREATE TABLE `biometric_devices` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED DEFAULT NULL,
                `serial_number` VARCHAR(100) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `location` VARCHAR(191) DEFAULT NULL,
                `device_type` VARCHAR(24) NOT NULL DEFAULT 'biometric',
                `direction_mode` VARCHAR(20) NOT NULL DEFAULT 'bidirectional',
                `firmware_version` VARCHAR(100) DEFAULT NULL,
                `is_virtual` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_seen_at` DATETIME DEFAULT NULL,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_device_serial` (`serial_number`),
                KEY `idx_biometric_device_integration` (`integration_id`),
                KEY `idx_biometric_device_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_punch_state_mappings', "
            CREATE TABLE `biometric_punch_state_mappings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED NOT NULL,
                `raw_punch_state` VARCHAR(32) NOT NULL,
                `direction` VARCHAR(8) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_punch_mapping` (`integration_id`, `raw_punch_state`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_identity_mappings', "
            CREATE TABLE `biometric_identity_mappings` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject_type` VARCHAR(12) NOT NULL,
                `subject_id` INT NOT NULL,
                `external_person_code` VARCHAR(100) NOT NULL,
                `valid_from` DATE DEFAULT NULL,
                `valid_until` DATE DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_mapping_code` (`external_person_code`),
                UNIQUE KEY `uq_biometric_mapping_subject` (`subject_type`, `subject_id`),
                KEY `idx_biometric_mapping_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_gateway_batches', "
            CREATE TABLE `biometric_gateway_batches` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED NOT NULL,
                `batch_id` VARCHAR(100) NOT NULL,
                `gateway_version` VARCHAR(40) DEFAULT NULL,
                `provider_cursor` VARCHAR(191) DEFAULT NULL,
                `request_hash` CHAR(64) NOT NULL,
                `event_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `accepted_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `duplicate_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `quarantined_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `rejected_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'processing',
                `results_json` LONGTEXT NULL,
                `committed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_gateway_batch` (`integration_id`, `batch_id`),
                KEY `idx_biometric_batch_created` (`integration_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_gateway_cursors', "
            CREATE TABLE `biometric_gateway_cursors` (
                `integration_id` INT UNSIGNED NOT NULL,
                `provider_cursor` VARCHAR(191) DEFAULT NULL,
                `batch_id` VARCHAR(100) DEFAULT NULL,
                `committed_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`integration_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_attendance_days', "
            CREATE TABLE `biometric_attendance_days` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject_type` VARCHAR(12) NOT NULL,
                `subject_id` INT NOT NULL,
                `attendance_date` DATE NOT NULL,
                `record_scope` VARCHAR(16) NOT NULL,
                `academic_session_id` INT DEFAULT NULL,
                `term` VARCHAR(225) DEFAULT NULL,
                `first_in_at` DATETIME DEFAULT NULL,
                `last_out_at` DATETIME DEFAULT NULL,
                `duration_minutes` INT UNSIGNED DEFAULT NULL,
                `attendance_status` VARCHAR(24) NOT NULL DEFAULT 'incomplete',
                `attendance_type_id` INT DEFAULT NULL,
                `missing_checkout` TINYINT(1) NOT NULL DEFAULT 0,
                `manual_locked` TINYINT(1) NOT NULL DEFAULT 0,
                `official_table` VARCHAR(40) DEFAULT NULL,
                `official_attendance_id` INT DEFAULT NULL,
                `projection_status` VARCHAR(24) NOT NULL DEFAULT 'not_applicable',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_attendance_day` (`subject_type`, `subject_id`, `attendance_date`, `record_scope`),
                KEY `idx_biometric_day_date` (`attendance_date`, `record_scope`),
                KEY `idx_biometric_day_missing` (`missing_checkout`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_events', "
            CREATE TABLE `biometric_events` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `batch_id` BIGINT UNSIGNED DEFAULT NULL,
                `integration_id` INT UNSIGNED DEFAULT NULL,
                `device_id` INT UNSIGNED DEFAULT NULL,
                `attendance_day_id` BIGINT UNSIGNED DEFAULT NULL,
                `dedup_key` CHAR(64) NOT NULL,
                `external_event_id` VARCHAR(191) NOT NULL,
                `device_serial` VARCHAR(100) NOT NULL,
                `person_code` VARCHAR(100) NOT NULL,
                `subject_type` VARCHAR(12) DEFAULT NULL,
                `subject_id` INT DEFAULT NULL,
                `raw_punch_state` VARCHAR(32) DEFAULT NULL,
                `direction` VARCHAR(8) DEFAULT NULL,
                `verification_method` VARCHAR(24) NOT NULL DEFAULT 'unknown',
                `source` VARCHAR(16) NOT NULL,
                `operating_mode` VARCHAR(16) NOT NULL,
                `occurred_at_utc` DATETIME NOT NULL,
                `occurred_at_local` DATETIME NOT NULL,
                `attendance_date` DATE NOT NULL,
                `received_at` DATETIME NOT NULL,
                `processing_status` VARCHAR(24) NOT NULL,
                `projection_status` VARCHAR(24) NOT NULL DEFAULT 'not_applicable',
                `failure_code` VARCHAR(64) DEFAULT NULL,
                `failure_message` VARCHAR(500) DEFAULT NULL,
                `payload_hash` CHAR(64) NOT NULL,
                `metadata_json` TEXT NULL,
                `processed_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_event_dedup` (`dedup_key`),
                KEY `idx_biometric_event_external` (`integration_id`, `external_event_id`),
                KEY `idx_biometric_event_date` (`attendance_date`, `operating_mode`),
                KEY `idx_biometric_event_status` (`processing_status`),
                KEY `idx_biometric_event_subject` (`subject_type`, `subject_id`, `attendance_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_exceptions', "
            CREATE TABLE `biometric_exceptions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_id` BIGINT UNSIGNED DEFAULT NULL,
                `attendance_day_id` BIGINT UNSIGNED DEFAULT NULL,
                `exception_code` VARCHAR(64) NOT NULL,
                `message` VARCHAR(500) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                `resolution_action` VARCHAR(32) DEFAULT NULL,
                `resolution_note` TEXT NULL,
                `resolved_by` INT DEFAULT NULL,
                `resolved_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_biometric_exception_status` (`status`, `created_at`),
                KEY `idx_biometric_exception_event` (`event_id`),
                KEY `idx_biometric_exception_day` (`attendance_day_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_reconciliation_actions', "
            CREATE TABLE `biometric_reconciliation_actions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `exception_id` BIGINT UNSIGNED NOT NULL,
                `action` VARCHAR(32) NOT NULL,
                `details_json` TEXT NULL,
                `actor_id` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_biometric_reconciliation_exception` (`exception_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_scanner_stations', "
            CREATE TABLE `biometric_scanner_stations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `station_uuid` CHAR(32) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `location` VARCHAR(191) DEFAULT NULL,
                `device_id` INT UNSIGNED DEFAULT NULL,
                `direction_mode` VARCHAR(20) NOT NULL DEFAULT 'bidirectional',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_seen_at` DATETIME DEFAULT NULL,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_scanner_uuid` (`station_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_qr_credentials', "
            CREATE TABLE `biometric_qr_credentials` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `credential_uuid` CHAR(32) NOT NULL,
                `subject_type` VARCHAR(12) NOT NULL,
                `subject_id` INT NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `token_ciphertext` TEXT NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `issued_by` INT DEFAULT NULL,
                `issued_at` DATETIME NOT NULL,
                `expires_at` DATETIME DEFAULT NULL,
                `revoked_by` INT DEFAULT NULL,
                `revoked_at` DATETIME DEFAULT NULL,
                `revoke_reason` VARCHAR(255) DEFAULT NULL,
                `last_used_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_qr_uuid` (`credential_uuid`),
                UNIQUE KEY `uq_biometric_qr_hash` (`token_hash`),
                KEY `idx_biometric_qr_subject` (`subject_type`, `subject_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_audit_logs', "
            CREATE TABLE `biometric_audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `actor_id` INT DEFAULT NULL,
                `action` VARCHAR(64) NOT NULL,
                `entity_type` VARCHAR(40) DEFAULT NULL,
                `entity_id` VARCHAR(64) DEFAULT NULL,
                `before_json` TEXT NULL,
                `after_json` TEXT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_biometric_audit_created` (`created_at`),
                KEY `idx_biometric_audit_entity` (`entity_type`, `entity_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    protected function addLegacyAttendanceLinks()
    {
        $fields = array(
            'attendance_source' => array('type' => 'VARCHAR', 'constraint' => 24, 'null' => true),
            'biometric_day_id' => array('type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true),
        );

        $this->addFields('student_attendences', $fields);
        $this->addFields('staff_attendance', $fields);
        $this->addIndex('student_attendences', 'idx_student_attendance_biometric_day', 'biometric_day_id');
        $this->addIndex('staff_attendance', 'idx_staff_attendance_biometric_day', 'biometric_day_id');
    }

    protected function repairExistingTables()
    {
        // A migration can be interrupted after MySQL commits one CREATE TABLE
        // or ALTER TABLE. Re-running up() must finish every required column.
        $this->addFields('biometric_attendance_days', array(
            'academic_session_id' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'term' => array('type' => 'VARCHAR', 'constraint' => 225, 'null' => true),
            'manual_locked' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'official_table' => array('type' => 'VARCHAR', 'constraint' => 40, 'null' => true),
            'official_attendance_id' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'projection_status' => array('type' => 'VARCHAR', 'constraint' => 24, 'default' => 'not_applicable'),
        ));
        $this->addFields('biometric_events', array(
            'payload_hash' => array('type' => 'CHAR', 'constraint' => 64, 'null' => true),
            'metadata_json' => array('type' => 'TEXT', 'null' => true),
            'projection_status' => array('type' => 'VARCHAR', 'constraint' => 24, 'default' => 'not_applicable'),
        ));
    }

    protected function dropLegacyAttendanceLinks()
    {
        foreach (array('student_attendences', 'staff_attendance') as $table) {
            if (!$this->db->table_exists($table)) {
                continue;
            }
            foreach (array('attendance_source', 'biometric_day_id') as $field) {
                if ($this->db->field_exists($field, $table)) {
                    $this->dbforge->drop_column($table, $field);
                }
            }
        }
    }

    protected function seedDefaults()
    {
        $now = date('Y-m-d H:i:s');
        if ($this->db->table_exists('biometric_settings')) {
            $this->db->query("INSERT INTO `biometric_settings`
                (`id`, `mode`, `timezone`, `created_at`, `updated_at`)
                SELECT 1, 'disabled', 'Africa/Lagos', ?, ?
                WHERE NOT EXISTS (SELECT 1 FROM `biometric_settings` WHERE `id` = 1)", array($now, $now));
        }

        if ($this->db->table_exists('biometric_devices')) {
            $this->db->query("INSERT INTO `biometric_devices`
                (`integration_id`, `serial_number`, `name`, `location`, `device_type`, `direction_mode`, `is_virtual`, `is_active`, `created_at`, `updated_at`)
                SELECT NULL, 'SIM-GATE-001', 'Simulation Gate', 'Browser Test Terminal', 'biometric', 'bidirectional', 1, 1, ?, ?
                WHERE NOT EXISTS (SELECT 1 FROM `biometric_devices` WHERE `serial_number` = 'SIM-GATE-001')", array($now, $now));
        }
    }

    protected function seedPermission()
    {
        if (!$this->db->table_exists('permission_group') || !$this->db->table_exists('permission_category')) {
            return;
        }

        $group = $this->db->select('id')->from('permission_group')
            ->where('short_code', 'student_attendance')->limit(1)->get()->row_array();

        if (empty($group)) {
            $this->db->insert('permission_group', array(
                'name' => 'Attendance',
                'short_code' => 'student_attendance',
                'is_active' => 1,
                'system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $group = array('id' => $this->db->insert_id());
        }

        $permission = $this->db->select('id')->from('permission_category')
            ->where('short_code', 'biometric_attendance')->limit(1)->get()->row_array();

        if (empty($permission)) {
            $this->db->insert('permission_category', array(
                'perm_group_id' => (int) $group['id'],
                'name' => 'Biometric Attendance',
                'short_code' => 'biometric_attendance',
                'enable_view' => 1,
                'enable_add' => 1,
                'enable_edit' => 1,
                'enable_delete' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $permission = array('id' => $this->db->insert_id());
        }

        if (!$this->db->table_exists('roles_permissions')) {
            return;
        }

        $roles = $this->db->select('id')->from('roles')
            ->where_in('name', array('Admin', 'Super Admin'))->get()->result_array();
        foreach ($roles as $role) {
            $exists = $this->db->where('role_id', $role['id'])
                ->where('perm_cat_id', $permission['id'])
                ->count_all_results('roles_permissions');
            if (!$exists) {
                $this->db->insert('roles_permissions', array(
                    'role_id' => (int) $role['id'],
                    'perm_cat_id' => (int) $permission['id'],
                    'can_view' => 1,
                    'can_add' => 1,
                    'can_edit' => 1,
                    'can_delete' => 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ));
            }
        }
    }

    protected function removePermission()
    {
        if (!$this->db->table_exists('permission_category')) {
            return;
        }
        $permission = $this->db->select('id')->from('permission_category')
            ->where('short_code', 'biometric_attendance')->limit(1)->get()->row_array();
        if (empty($permission)) {
            return;
        }
        if ($this->db->table_exists('roles_permissions')) {
            $this->db->where('perm_cat_id', $permission['id'])->delete('roles_permissions');
        }
        $this->db->where('id', $permission['id'])->delete('permission_category');
    }

    protected function createTable($table, $sql)
    {
        if (!$this->db->table_exists($table)) {
            $this->db->query($sql);
        }
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

    protected function addIndex($table, $index, $column)
    {
        if (!$this->db->table_exists($table) || !$this->db->field_exists($column, $table)) {
            return;
        }

        $row = $this->db->query(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            array($table, $index)
        )->row_array();
        if (empty($row['total'])) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (`' . $column . '`)');
        }
    }
}
