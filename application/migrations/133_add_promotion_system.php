<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Adds advisory, session-specific promotion rules and an append-only override
 * history. This migration deliberately does not read from or write to
 * student_session; moving students between classes remains a manual workflow.
 */
class Migration_Add_promotion_system extends CI_Migration
{
    public function up()
    {
        $this->createTable('promotion_criteria', "
            CREATE TABLE `promotion_criteria` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `session_id` INT NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `minimum_average` DECIMAL(5,2) NOT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by` INT DEFAULT NULL,
                `updated_by` INT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `promotion_criteria_session_idx` (`session_id`, `is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('promotion_criteria_classes', "
            CREATE TABLE `promotion_criteria_classes` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `criteria_id` BIGINT UNSIGNED NOT NULL,
                `session_id` INT NOT NULL,
                `class_id` INT NOT NULL,
                `promoted_to_class_id` INT DEFAULT NULL,
                `promoted_to_label` VARCHAR(191) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `promotion_criteria_class_unique` (`session_id`, `class_id`),
                KEY `promotion_criteria_class_criteria_idx` (`criteria_id`),
                KEY `promotion_criteria_class_target_idx` (`promoted_to_class_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('promotion_criteria_subjects', "
            CREATE TABLE `promotion_criteria_subjects` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `criteria_id` BIGINT UNSIGNED NOT NULL,
                `subject_id` INT NOT NULL,
                `minimum_average` DECIMAL(5,2) NOT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `promotion_criteria_subject_unique` (`criteria_id`, `subject_id`),
                KEY `promotion_criteria_subject_subject_idx` (`subject_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->createTable('promotion_note_overrides', "
            CREATE TABLE `promotion_note_overrides` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `student_id` INT NOT NULL,
                `session_id` INT NOT NULL,
                `class_id` INT NOT NULL,
                `section_id` INT NOT NULL,
                `action` VARCHAR(10) NOT NULL,
                `decision` VARCHAR(20) DEFAULT NULL,
                `target_class_id` INT DEFAULT NULL,
                `target_label` VARCHAR(191) DEFAULT NULL,
                `reason` TEXT NOT NULL,
                `automatic_decision` VARCHAR(20) NOT NULL,
                `automatic_note` VARCHAR(255) NOT NULL,
                `created_by` INT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `promotion_override_scope_idx` (`student_id`, `session_id`, `class_id`, `section_id`, `id`),
                KEY `promotion_override_review_idx` (`session_id`, `class_id`, `section_id`, `id`),
                KEY `promotion_override_actor_idx` (`created_by`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->seedPermissions();
    }

    public function down()
    {
        $this->removePermissions();

        // Remove dependent/history tables before their parent definition.
        $this->dbforge->drop_table('promotion_note_overrides', true);
        $this->dbforge->drop_table('promotion_criteria_subjects', true);
        $this->dbforge->drop_table('promotion_criteria_classes', true);
        $this->dbforge->drop_table('promotion_criteria', true);

        // The shared Exam Setting group is intentionally retained.
    }

    private function seedPermissions()
    {
        if (!$this->db->table_exists('permission_group')
            || !$this->db->table_exists('permission_category')) {
            return;
        }

        $group = $this->db->select('id')
            ->from('permission_group')
            ->where('short_code', 'exam_setting')
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($group)) {
            $this->db->insert('permission_group', array(
                'name' => 'Exam Setting',
                'short_code' => 'exam_setting',
                'is_active' => 1,
                'system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $group = array('id' => $this->db->insert_id());
        }

        $this->seedPermissionCategory(
            (int) $group['id'],
            'Manage Promotion Criteria',
            'manage_promotion_criteria',
            array('Admin', 'Head Teacher', 'Super Admin')
        );

        $this->seedPermissionCategory(
            (int) $group['id'],
            'Override Promotion Note',
            'override_promotion_note',
            array('Teacher', 'Admin', 'Head Teacher', 'Super Admin')
        );
    }

    private function seedPermissionCategory($groupId, $name, $shortCode, array $roleNames)
    {
        $permission = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', $shortCode)
            ->limit(1)
            ->get()
            ->row_array();

        $permissionData = array(
            'perm_group_id' => (int) $groupId,
            'name' => $name,
            'enable_view' => 1,
            'enable_add' => 1,
            'enable_edit' => 1,
            // Criteria are archived and overrides are append-only.
            'enable_delete' => 0,
        );

        if (empty($permission)) {
            $permissionData['short_code'] = $shortCode;
            $permissionData['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('permission_category', $permissionData);
            $permission = array('id' => $this->db->insert_id());
        } else {
            $this->db->where('id', (int) $permission['id'])
                ->update('permission_category', $permissionData);
        }

        if (!$this->db->table_exists('roles')
            || !$this->db->table_exists('roles_permissions')) {
            return;
        }

        $roles = $this->db->select('id')
            ->from('roles')
            ->where_in('name', $roleNames)
            ->get()
            ->result_array();

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $permissionId = (int) $permission['id'];
            $existing = $this->db->select('id')
                ->from('roles_permissions')
                ->where('role_id', $roleId)
                ->where('perm_cat_id', $permissionId)
                ->limit(1)
                ->get()
                ->row_array();

            $grant = array(
                'can_view' => 1,
                'can_add' => 1,
                'can_edit' => 1,
                'can_delete' => 0,
            );

            if (empty($existing)) {
                $grant['role_id'] = $roleId;
                $grant['perm_cat_id'] = $permissionId;
                $grant['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('roles_permissions', $grant);
            } else {
                // Normalize every legacy duplicate so MAX-based RBAC checks
                // cannot retain an old delete grant on a second row.
                $this->db->where('role_id', $roleId)
                    ->where('perm_cat_id', $permissionId)
                    ->update('roles_permissions', $grant);
            }
        }
    }

    private function removePermissions()
    {
        if (!$this->db->table_exists('permission_category')) {
            return;
        }

        $permissions = $this->db->select('id')
            ->from('permission_category')
            ->where_in('short_code', array(
                'manage_promotion_criteria',
                'override_promotion_note',
            ))
            ->get()
            ->result_array();

        foreach ($permissions as $permission) {
            $permissionId = (int) $permission['id'];
            if ($this->db->table_exists('roles_permissions')) {
                $this->db->where('perm_cat_id', $permissionId)
                    ->delete('roles_permissions');
            }
            $this->db->where('id', $permissionId)
                ->delete('permission_category');
        }
    }

    private function createTable($table, $sql)
    {
        if (!$this->db->table_exists($table)) {
            $this->db->query($sql);
        }
    }
}
