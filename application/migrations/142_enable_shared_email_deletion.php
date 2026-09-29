<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Enable_shared_email_deletion extends CI_Migration
{
    public function up()
    {
        $this->setDeletePermission(1);
    }

    public function down()
    {
        $this->setDeletePermission(0);
    }

    protected function setDeletePermission($enabled)
    {
        if (!$this->db->table_exists('permission_category')) {
            return;
        }

        $permission = $this->db->select('id')
            ->from('permission_category')
            ->where('short_code', 'shared_email')
            ->limit(1)
            ->get()
            ->row_array();
        if (empty($permission)) {
            return;
        }

        $permissionId = (int) $permission['id'];
        $this->db->where('id', $permissionId)->update('permission_category', array(
            'enable_delete' => (int) $enabled,
        ));

        if (!$this->db->table_exists('roles') || !$this->db->table_exists('roles_permissions')) {
            return;
        }

        $roles = $this->db->select('id')
            ->from('roles')
            ->where_in('name', array('Admin', 'Super Admin', 'Head Teacher'))
            ->get()
            ->result_array();
        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $grant = $this->db->select('id')
                ->from('roles_permissions')
                ->where('role_id', $roleId)
                ->where('perm_cat_id', $permissionId)
                ->limit(1)
                ->get()
                ->row_array();

            if (empty($grant)) {
                if ((int) $enabled === 1) {
                    $this->db->insert('roles_permissions', array(
                        'role_id' => $roleId,
                        'perm_cat_id' => $permissionId,
                        'can_view' => 1,
                        'can_add' => 1,
                        'can_edit' => 0,
                        'can_delete' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ));
                }
                continue;
            }

            $this->db->where('id', (int) $grant['id'])->update('roles_permissions', array(
                'can_delete' => (int) $enabled,
            ));
        }
    }
}
