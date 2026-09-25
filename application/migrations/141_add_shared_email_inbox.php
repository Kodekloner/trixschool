<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_shared_email_inbox extends CI_Migration
{
    public function up()
    {
        $this->createEmailConversationsTable();
        $this->createEmailConversationMessagesTable();
        $this->addSharedEmailPermission();
    }

    public function down()
    {
        $this->dbforge->drop_table('email_conversation_messages', true);
        $this->dbforge->drop_table('email_conversations', true);

        if (!$this->db->table_exists('permission_category')) {
            return;
        }

        $permissions = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'shared_email')
            ->get()
            ->result_array();

        foreach ($permissions as $permission) {
            $permissionId = (int) $permission['id'];
            if ($this->db->table_exists('roles_permissions')) {
                $this->db->where('perm_cat_id', $permissionId)->delete('roles_permissions');
            }
            $this->db->where('id', $permissionId)->delete('permission_category');
        }
    }

    protected function createEmailConversationsTable()
    {
        if ($this->db->table_exists('email_conversations')) {
            return;
        }

        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ),
            'conversation_number' => array(
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
            ),
            'participant_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
            ),
            'participant_email' => array(
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => false,
            ),
            'subject' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ),
            'inbound_address' => array(
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
            ),
            'created_by_staff_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ),
            'last_outgoing_message_id' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'last_incoming_message_id' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'last_message_direction' => array(
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'outgoing',
            ),
            'incoming_count' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ),
            'outgoing_count' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ),
            'unread_count' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ),
            'last_message_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'last_incoming_at' => array(
                'type' => 'DATETIME',
                'null' => true,
            ),
            'last_outgoing_at' => array(
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
        $this->dbforge->add_key('participant_email');
        $this->dbforge->add_key('last_message_at');
        $this->dbforge->add_key('unread_count');
        $this->dbforge->create_table('email_conversations', true);
        $this->db->query('ALTER TABLE `email_conversations` ADD UNIQUE KEY `email_conversation_number_unique` (`conversation_number`)');
    }

    protected function createEmailConversationMessagesTable()
    {
        if ($this->db->table_exists('email_conversation_messages')) {
            return;
        }

        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ),
            'email_conversation_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ),
            'incoming_email_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ),
            'direction' => array(
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
            ),
            'sender_staff_id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ),
            'sender_name' => array(
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
            ),
            'sender_email' => array(
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
            ),
            'recipients_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'subject' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
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
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'in_reply_to' => array(
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ),
            'references_header' => array(
                'type' => 'TEXT',
                'null' => true,
            ),
            'attachment_count' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ),
            'attachment_names_json' => array(
                'type' => 'LONGTEXT',
                'null' => true,
            ),
            'delivery_status' => array(
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'received',
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
        $this->dbforge->add_key('email_conversation_id');
        $this->dbforge->add_key('message_id');
        $this->dbforge->create_table('email_conversation_messages', true);
        $this->db->query('ALTER TABLE `email_conversation_messages` ADD UNIQUE KEY `email_conversation_incoming_unique` (`incoming_email_id`)');
    }

    protected function addSharedEmailPermission()
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
            ->where('short_code', 'shared_email')
            ->limit(1)
            ->get()
            ->row_array();

        $permissionData = array(
            'perm_group_id' => (int) $group['id'],
            'name' => 'Shared Email Inbox',
            'enable_view' => 1,
            'enable_add' => 1,
            'enable_edit' => 0,
            'enable_delete' => 0,
        );

        if (empty($permission)) {
            $permissionData['short_code'] = 'shared_email';
            $permissionData['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('permission_category', $permissionData);
            $permission = array('id' => $this->db->insert_id());
        } else {
            $this->db->where('id', (int) $permission['id'])->update('permission_category', $permissionData);
        }

        if (!$this->db->table_exists('roles') || !$this->db->table_exists('roles_permissions')) {
            return;
        }

        $roles = $this->db->select('id')
            ->from('roles')
            ->where_in('name', array('Admin', 'Super Admin', 'Head Teacher'))
            ->get()
            ->result_array();

        foreach ($roles as $role) {
            $this->upsertRolePermission((int) $role['id'], (int) $permission['id'], array(
                'can_view' => 1,
                'can_add' => 1,
                'can_edit' => 0,
                'can_delete' => 0,
            ));
        }

        $externalPermission = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'external_email')
            ->limit(1)
            ->get()
            ->row_array();

        if (!empty($externalPermission)) {
            foreach ($roles as $role) {
                $this->upsertRolePermission((int) $role['id'], (int) $externalPermission['id'], array(
                    'can_view' => 1,
                    'can_add' => 1,
                    'can_edit' => 0,
                    'can_delete' => 0,
                ));
            }
        }
    }

    protected function upsertRolePermission($roleId, $permissionId, $grant)
    {
        $existing = $this->db->select('id')
            ->from('roles_permissions')
            ->where('role_id', (int) $roleId)
            ->where('perm_cat_id', (int) $permissionId)
            ->limit(1)
            ->get()
            ->row_array();

        if (empty($existing)) {
            $grant['role_id'] = (int) $roleId;
            $grant['perm_cat_id'] = (int) $permissionId;
            $grant['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('roles_permissions', $grant);
            return;
        }

        $this->db->where('id', (int) $existing['id'])->update('roles_permissions', $grant);
    }
}
