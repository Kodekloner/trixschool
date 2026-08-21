<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Adds the outbound-only control plane used by the Windows biometric gateway.
 * The website may queue only a small, fixed command vocabulary; the gateway
 * claims commands during its authenticated heartbeat and posts bounded results.
 */
class Migration_Add_biometric_gateway_control extends CI_Migration
{
    public function up()
    {
        $this->createTable('biometric_gateway_agents', "
            CREATE TABLE `biometric_gateway_agents` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `integration_id` INT UNSIGNED NOT NULL,
                `gateway_id` VARCHAR(128) NOT NULL,
                `gateway_version` VARCHAR(40) DEFAULT NULL,
                `status_json` TEXT NULL,
                `provider_reachable` TINYINT(1) DEFAULT NULL,
                `last_sync_ok` TINYINT(1) DEFAULT NULL,
                `last_sync_at` DATETIME DEFAULT NULL,
                `queue_pending` INT UNSIGNED NOT NULL DEFAULT 0,
                `queue_retry` INT UNSIGNED NOT NULL DEFAULT 0,
                `queue_dead` INT UNSIGNED NOT NULL DEFAULT 0,
                `provider_cursor` VARCHAR(191) DEFAULT NULL,
                `last_error` VARCHAR(500) DEFAULT NULL,
                `first_seen_at` DATETIME NOT NULL,
                `last_heartbeat_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_gateway_agent_id` (`gateway_id`),
                KEY `idx_biometric_gateway_agent_heartbeat` (`integration_id`, `last_heartbeat_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('biometric_gateway_commands', "
            CREATE TABLE `biometric_gateway_commands` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `command_uuid` CHAR(32) NOT NULL,
                `integration_id` INT UNSIGNED NOT NULL,
                `gateway_id` VARCHAR(128) DEFAULT NULL,
                `command_type` VARCHAR(32) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'queued',
                `result_json` TEXT NULL,
                `queued_by` INT DEFAULT NULL,
                `queued_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `claimed_at` DATETIME DEFAULT NULL,
                `completed_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_biometric_gateway_command_uuid` (`command_uuid`),
                KEY `idx_biometric_gateway_command_claim` (`integration_id`, `status`, `expires_at`, `id`),
                KEY `idx_biometric_gateway_command_gateway` (`integration_id`, `gateway_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down()
    {
        $this->dbforge->drop_table('biometric_gateway_commands', true);
        $this->dbforge->drop_table('biometric_gateway_agents', true);
    }

    private function createTable($table, $sql)
    {
        if (!$this->db->table_exists($table)) {
            $this->db->query($sql);
        }
    }
}
