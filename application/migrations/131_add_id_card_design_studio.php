<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Adds the non-destructive ID Card Design Studio beside the legacy student
 * and staff card builders. Legacy rows remain the source of truth until an
 * administrator explicitly converts a template in the studio.
 */
class Migration_Add_id_card_design_studio extends CI_Migration
{
    public function up()
    {
        $this->addLegacyCompatibilityFields('id_card');
        $this->addLegacyCompatibilityFields('staff_id_card');

        $this->createTable('id_card_designs', "
            CREATE TABLE `id_card_designs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `subject_type` VARCHAR(16) NOT NULL,
                `legacy_template_id` INT NOT NULL,
                `title` VARCHAR(191) NOT NULL,
                `width_mm` DECIMAL(8,3) NOT NULL DEFAULT 85.600,
                `height_mm` DECIMAL(8,3) NOT NULL DEFAULT 53.980,
                `orientation` VARCHAR(16) NOT NULL DEFAULT 'landscape',
                `draft_version_id` BIGINT UNSIGNED DEFAULT NULL,
                `published_version_id` BIGINT UNSIGNED DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id_card_design_legacy_unique` (`subject_type`, `legacy_template_id`),
                KEY `id_card_design_status_idx` (`subject_type`, `is_active`),
                KEY `id_card_design_published_idx` (`published_version_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('id_card_design_versions', "
            CREATE TABLE `id_card_design_versions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `design_id` BIGINT UNSIGNED NOT NULL,
                `version_no` INT UNSIGNED NOT NULL,
                `state` VARCHAR(16) NOT NULL DEFAULT 'draft',
                `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `front_json` LONGTEXT NOT NULL,
                `back_json` LONGTEXT NOT NULL,
                `print_settings_json` LONGTEXT NOT NULL,
                `checksum` CHAR(64) NOT NULL,
                `created_by` INT DEFAULT NULL,
                `published_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `published_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `id_card_design_version_unique` (`design_id`, `version_no`),
                KEY `id_card_design_version_state_idx` (`design_id`, `state`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('id_card_design_assets', "
            CREATE TABLE `id_card_design_assets` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `design_id` BIGINT UNSIGNED NOT NULL,
                `storage_key` VARCHAR(500) NOT NULL,
                `original_name` VARCHAR(191) NOT NULL,
                `mime_type` VARCHAR(100) NOT NULL,
                `byte_size` INT UNSIGNED NOT NULL,
                `pixel_width` INT UNSIGNED DEFAULT NULL,
                `pixel_height` INT UNSIGNED DEFAULT NULL,
                `sha256` CHAR(64) NOT NULL,
                `created_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `deleted_at` DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `id_card_design_asset_design_idx` (`design_id`, `deleted_at`),
                KEY `id_card_design_asset_hash_idx` (`sha256`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('id_card_design_audit', "
            CREATE TABLE `id_card_design_audit` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `design_id` BIGINT UNSIGNED NOT NULL,
                `version_id` BIGINT UNSIGNED DEFAULT NULL,
                `staff_id` INT DEFAULT NULL,
                `action` VARCHAR(40) NOT NULL,
                `summary_json` LONGTEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `id_card_design_audit_design_idx` (`design_id`, `created_at`),
                KEY `id_card_design_audit_staff_idx` (`staff_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down()
    {
        $this->dbforge->drop_table('id_card_design_audit', true);
        $this->dbforge->drop_table('id_card_design_assets', true);
        $this->dbforge->drop_table('id_card_design_versions', true);
        $this->dbforge->drop_table('id_card_designs', true);

        // Compatibility fields are intentionally retained. Some installations
        // received them from the personal branch before this migration existed;
        // removing them would destroy those layouts during a rollback.
    }

    private function addLegacyCompatibilityFields($table)
    {
        if (!$this->db->table_exists($table)) {
            return;
        }

        $had_width = $this->db->field_exists('card_width', $table);
        $had_height = $this->db->field_exists('card_height', $table);
        $had_unit = $this->db->field_exists('card_unit', $table);

        $fields = array(
            'card_unit' => array(
                'type' => 'VARCHAR',
                'constraint' => 10,
                'default' => 'mm',
                'null' => false,
            ),
            'card_width' => array(
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => '85.60',
                'null' => false,
            ),
            'card_height' => array(
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => '53.98',
                'null' => false,
            ),
            'photo_style' => array(
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'round',
                'null' => false,
            ),
            'layout_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
        );

        foreach ($fields as $name => $definition) {
            if (!$this->db->field_exists($name, $table)) {
                $this->dbforge->add_column($table, array($name => $definition));
            }
        }

        if ($this->db->field_exists('enable_vertical_card', $table)) {
            $portrait_updates = array();
            if (!$had_width) {
                $portrait_updates[] = '`card_width` = 53.98';
            }
            if (!$had_height) {
                $portrait_updates[] = '`card_height` = 85.60';
            }
            if (!$had_unit) {
                $portrait_updates[] = "`card_unit` = 'mm'";
            }
            if ($portrait_updates) {
                // Never overwrite personal-branch values that already exist.
                $this->db->query("UPDATE `" . $table . "` SET " . implode(', ', $portrait_updates)
                    . " WHERE `enable_vertical_card` = 1");
            }
        }
    }

    private function createTable($table, $sql)
    {
        if (!$this->db->table_exists($table)) {
            $this->db->query($sql);
        }
    }
}
