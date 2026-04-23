<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_support_tickets extends CI_Migration
{
    public function up()
    {
        $this->createSupportTicketsTable();
        $this->createSupportMessagesTable();
        $this->addSupportTicketPermission();
    }

    public function down()
    {
        $this->dbforge->drop_table('support_messages', true);
        $this->dbforge->drop_table('support_tickets', true);
    }

    protected function createSupportTicketsTable()
    {
        if ($this->db->table_exists('support_tickets')) {
            return;
        }

        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ),
            'ticket_number' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ),
            'source' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'email',
            ),
            'requester_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ),
            'requester_email' => array(
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => false,
            ),
            'requester_phone' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ),
            'subject' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ),
            'status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'open',
            ),
            'priority' => array(
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'normal',
            ),
            'assigned_staff_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ),
            'incoming_email_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ),
            'last_incoming_email_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ),
            'message_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'last_outgoing_message_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'last_message_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'last_customer_message_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'last_staff_message_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'opened_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'closed_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
        ));

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('ticket_number');
        $this->dbforge->add_key('status');
        $this->dbforge->add_key('requester_email');
        $this->dbforge->add_key('last_message_at');
        $this->dbforge->create_table('support_tickets', true);
        $this->db->query('ALTER TABLE `support_tickets` ADD UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`)');
    }

    protected function createSupportMessagesTable()
    {
        if ($this->db->table_exists('support_messages')) {
            return;
        }

        $this->dbforge->add_field(array(
            'id' => array(
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ),
            'support_ticket_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ),
            'incoming_email_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ),
            'direction' => array(
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ),
            'sender_type' => array(
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
            ),
            'sender_staff_id' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ),
            'sender_name' => array(
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ),
            'sender_email' => array(
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ),
            'recipients_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'subject' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'body_text' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'body_html' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'message_id' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'in_reply_to' => array(
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ),
            'references_header' => array(
                'type' => 'TEXT',
                'null' => true,
            ),
            'attachment_count' => array(
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ),
            'attachment_names_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'delivery_status' => array(
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'received',
            ),
            'error_message' => array(
                'type' => 'TEXT',
                'null' => true,
            ),
            'created_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
            'updated_at' => array(
                'type' => 'DATETIME',
                'null' => false,
            ),
        ));

        $this->dbforge->add_key('id', true);
        $this->dbforge->add_key('support_ticket_id');
        $this->dbforge->add_key('incoming_email_id');
        $this->dbforge->add_key('message_id');
        $this->dbforge->create_table('support_messages', true);
    }

    protected function addSupportTicketPermission()
    {
        $this->db->query("INSERT INTO `permission_group` (`name`, `short_code`, `is_active`, `system`, `created_at`)
            SELECT 'Front Office', 'front_office', 1, 0, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM `permission_group` WHERE `short_code` = 'front_office')");

        $front_office_group = $this->db->select('id')
            ->from('permission_group')
            ->where('short_code', 'front_office')
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($front_office_group)) {
            return;
        }

        $group_id = (int) $front_office_group['id'];

        $this->db->query("INSERT INTO `permission_category` (`perm_group_id`, `name`, `short_code`, `enable_view`, `enable_add`, `enable_edit`, `enable_delete`, `created_at`)
            SELECT " . $group_id . ", 'Support Tickets', 'support_ticket', 1, 1, 1, 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM `permission_category` WHERE `short_code` = 'support_ticket')");

        $permission = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'support_ticket')
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($permission)) {
            return;
        }

        $permission_id = (int) $permission['id'];

        $this->db->query("INSERT INTO `roles_permissions` (`role_id`, `perm_cat_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`)
            SELECT `roles`.`id`, " . $permission_id . ", 1, 1, 1, 1, NOW()
            FROM `roles`
            WHERE `roles`.`name` IN ('Admin', 'Super Admin')
            AND NOT EXISTS (
                SELECT 1 FROM `roles_permissions`
                WHERE `roles_permissions`.`role_id` = `roles`.`id`
                AND `roles_permissions`.`perm_cat_id` = " . $permission_id . "
            )");
    }
}
