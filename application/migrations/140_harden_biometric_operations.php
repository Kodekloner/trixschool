<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Production hardening for biometric operations.
 *
 * This migration is deliberately additive and repeatable. It adds an optional
 * Live pilot allowlist, guardian notification queue, retention-run evidence,
 * and explicit go-live acknowledgement without rewriting historical events or
 * legacy attendance rows.
 */
class Migration_Harden_biometric_operations extends CI_Migration
{
    public function up()
    {
        $this->addFields('biometric_settings', array(
            'live_pilot_enabled' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'notify_student_in' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'notify_student_out' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'notify_email' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'notify_sms' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'notify_whatsapp' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
            'live_acknowledged_by' => array('type' => 'INT', 'constraint' => 11, 'null' => true),
            'live_acknowledged_at' => array('type' => 'DATETIME', 'null' => true),
            'last_retention_run_at' => array('type' => 'DATETIME', 'null' => true),
        ));
        $this->addFields('biometric_identity_mappings', array(
            'live_pilot' => array('type' => 'TINYINT', 'constraint' => 1, 'default' => 0),
        ));

        if (!$this->db->table_exists('biometric_notification_queue')) {
            $this->db->query("CREATE TABLE `biometric_notification_queue` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `channel` VARCHAR(16) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `next_attempt_at` DATETIME DEFAULT NULL,
                `last_error` VARCHAR(500) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `delivered_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_notification_event_channel` (`event_id`, `channel`),
                KEY `idx_biometric_notification_delivery` (`status`, `next_attempt_at`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            // Repair a partially applied migration without deleting queue data.
            if (!$this->db->field_exists('id', 'biometric_notification_queue')) {
                $this->db->query('ALTER TABLE `biometric_notification_queue` '
                    . 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
            }
            $this->addFields('biometric_notification_queue', array(
                'event_id' => array('type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true),
                'channel' => array('type' => 'VARCHAR', 'constraint' => 16),
                'status' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'),
                'attempt_count' => array('type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'default' => 0),
                'next_attempt_at' => array('type' => 'DATETIME', 'null' => true),
                'last_error' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true),
                'created_at' => array('type' => 'DATETIME', 'null' => true),
                'updated_at' => array('type' => 'DATETIME', 'null' => true),
                'delivered_at' => array('type' => 'DATETIME', 'null' => true),
            ));
        }
        $this->addIndex(
            'biometric_notification_queue',
            'uq_biometric_notification_event_channel',
            '`event_id`, `channel`',
            true
        );
        $this->addIndex(
            'biometric_notification_queue',
            'idx_biometric_notification_delivery',
            '`status`, `next_attempt_at`, `id`'
        );
        $this->addIndex(
            'biometric_identity_mappings',
            'idx_biometric_mapping_pilot',
            '`live_pilot`, `is_active`'
        );
    }

    public function down()
    {
        $this->dbforge->drop_table('biometric_notification_queue', true);
        $this->dropFields('biometric_identity_mappings', array('live_pilot'));
        $this->dropFields('biometric_settings', array(
            'live_pilot_enabled', 'notify_student_in', 'notify_student_out',
            'notify_email', 'notify_sms', 'notify_whatsapp',
            'live_acknowledged_by', 'live_acknowledged_at',
            'last_retention_run_at',
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
        if (!$this->db->table_exists($table)) {
            return;
        }
        $exists = $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            array($table, $name)
        )->row_array();
        if (!$exists) {
            $this->db->query('ALTER TABLE `' . $table . '` ADD '
                . ($unique ? 'UNIQUE ' : '') . 'KEY `' . $name . '` (' . $columns . ')');
        }
    }
}
