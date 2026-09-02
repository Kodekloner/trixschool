<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stores the staff members who have opted in to receive an email alert when
 * a new external support email reaches the school, and adds the dedicated
 * permission used to send email to addresses outside the school directory.
 * One row per staff member keeps preference changes idempotent and auditable.
 */
class Migration_Add_external_email_notifications extends CI_Migration
{
    public function up()
    {
        if (!$this->db->table_exists('support_email_notifications')) {
            $this->db->query("
                CREATE TABLE `support_email_notifications` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `staff_id` INT NOT NULL,
                    `email` VARCHAR(191) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `last_notified_at` DATETIME DEFAULT NULL,
                    `last_error` TEXT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `support_email_notifications_staff_unique` (`staff_id`),
                    KEY `support_email_notifications_email_idx` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        $this->createSupportEmailAlertDeliveries();

        $this->seedExternalEmailPermission();
    }

    public function down()
    {
        $this->removeExternalEmailPermission();
        $this->dbforge->drop_table('support_email_alert_deliveries', true);
        $this->dbforge->drop_table('support_email_notifications', true);
    }

    private function createSupportEmailAlertDeliveries()
    {
        if ($this->db->table_exists('support_email_alert_deliveries')) {
            return;
        }

        $this->db->query("
            CREATE TABLE `support_email_alert_deliveries` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `notification_id` INT UNSIGNED NOT NULL,
                `incoming_email_id` INT UNSIGNED NOT NULL,
                `support_ticket_id` INT UNSIGNED NOT NULL,
                `school_domain` VARCHAR(253) NOT NULL,
                `inbound_address` VARCHAR(191) NOT NULL,
                `recipient_email` VARCHAR(191) NOT NULL,
                `delivery_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
                `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `available_at` DATETIME NOT NULL,
                `last_attempt_at` DATETIME DEFAULT NULL,
                `sent_at` DATETIME DEFAULT NULL,
                `provider_message_id` VARCHAR(255) DEFAULT NULL,
                `error_message` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `support_email_alert_delivery_unique` (`notification_id`, `incoming_email_id`),
                KEY `support_email_alert_queue_idx` (`delivery_status`, `available_at`),
                KEY `support_email_alert_ticket_idx` (`support_ticket_id`),
                KEY `support_email_alert_incoming_idx` (`incoming_email_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function seedExternalEmailPermission()
    {
        if (!$this->db->table_exists('permission_group')
            || !$this->db->table_exists('permission_category')) {
            return;
        }

        $group = $this->db->select('id')
            ->from('permission_group')
            ->where('short_code', 'communicate')
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($group)) {
            $this->db->insert('permission_group', array(
                'name' => 'Communicate',
                'short_code' => 'communicate',
                'is_active' => 1,
                'system' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $group = array('id' => $this->db->insert_id());
        }

        $permission = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'external_email')
            ->limit(1)
            ->get()
            ->row_array();

        $permissionData = array(
            'perm_group_id' => (int) $group['id'],
            'name' => 'Send External Email',
            'enable_view' => 1,
            'enable_add' => 1,
            'enable_edit' => 0,
            'enable_delete' => 0,
        );

        if (empty($permission)) {
            $permissionData['short_code'] = 'external_email';
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
            ->where_in('name', array('Admin', 'Super Admin'))
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
                'can_edit' => 0,
                'can_delete' => 0,
            );

            if (empty($existing)) {
                $grant['role_id'] = $roleId;
                $grant['perm_cat_id'] = $permissionId;
                $grant['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('roles_permissions', $grant);
            } else {
                $this->db->where('role_id', $roleId)
                    ->where('perm_cat_id', $permissionId)
                    ->update('roles_permissions', $grant);
            }
        }
    }

    private function removeExternalEmailPermission()
    {
        if (!$this->db->table_exists('permission_category')) {
            return;
        }

        $permissions = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'external_email')
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

        // The shared Communicate group is intentionally retained.
    }
}
