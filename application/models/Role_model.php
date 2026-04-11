<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Role_model extends MY_Model {

    public function __construct() {
        parent::__construct();
        $this->current_session = $this->setting_model->getCurrentSession();
    }

    /**
     * This funtion takes id as a parameter and will fetch the record.
     * If id is not provided, then it will fetch all the records form the table.
     * @param int $id
     * @return mixed
     */
    public function get($id = null) {
        $userdata = $this->customlib->getUserData();
        if ($userdata["role_id"] != 7) {
            $this->db->where("id !=", 7);
        }


        $this->db->select()->from('roles');
        if ($id != null) {
            $this->db->where('roles.id', $id);
        } else {
            $this->db->order_by('roles.id');
        }
        $query = $this->db->get();
        if ($id != null) {
            return $query->row_array();
        } else {
            return $query->result_array();
        }
    }

    /**
     * This function will delete the record based on the id
     * @param $id
     */
    public function remove($id) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->delete('roles');
        $message = DELETE_RECORD_CONSTANT . " On roles id " . $id;
        $action = "Delete";
        $record_id = $id;
        $this->log($message, $record_id, $action);
        //======================Code End==============================
        $this->db->trans_complete(); # Completing transaction
        /* Optional */
        if ($this->db->trans_status() === false) {
            # Something went wrong.
            $this->db->trans_rollback();
            return false;
        } else {
            //return $return_value;
        }
    }

    /**
     * This function will take the post data passed from the controller
     * If id is present, then it will do an update
     * else an insert. One function doing both add and edit.
     * @param $data
     */
    public function add($data) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('roles', $data);
            $message = UPDATE_RECORD_CONSTANT . " On roles id " . $data['id'];
            $action = "Update";
            $record_id = $data['id'];
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /* Optional */

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;
            } else {
                //return $return_value;
            }
        } else {
            $this->db->insert('roles', $data);
            $insert_id = $this->db->insert_id();
            $message = INSERT_RECORD_CONSTANT . " On roles id " . $insert_id;
            $action = "Insert";
            $record_id = $insert_id;
            $this->log($message, $record_id, $action);
            //======================Code End==============================

            $this->db->trans_complete(); # Completing transaction
            /* Optional */

            if ($this->db->trans_status() === false) {
                # Something went wrong.
                $this->db->trans_rollback();
                return false;
            } else {
                //return $return_value;
            }
            return $insert_id;
        }
    }

    public function valid_check_exists($str) {
        $name = $this->input->post('name');
        $id = $this->input->post('id');

        if (!isset($id)) {
            $id = 0;
        }
        if ($this->check_data_exists($name, $id)) {
            $this->form_validation->set_message('check_exists', 'Record already exists');
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function check_data_exists($name, $id) {
        $this->db->where('name', $name);

        $this->db->where('id !=', $id);
        $query = $this->db->get('roles');
        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

///======================

    public function find($role_id = null) {
        $this->db->select()->from('permission_group');
        $this->db->order_by('permission_group.id');

        $query = $this->db->get();

        $result = $query->result();
        foreach ($result as $key => $value) {
            $value->permission_category = $this->getPermissions($value->id, $role_id);
        }
        return $result;
    }

    public function getPermissions($group_id, $role_id) {
        $sql = "SELECT permission_category.*,IFNULL(roles_permissions.id,0) as `roles_permissions_id`,roles_permissions.can_view,roles_permissions.can_add ,roles_permissions.can_edit ,roles_permissions.can_delete FROM `permission_category` LEFT JOIN roles_permissions on permission_category.id = roles_permissions.perm_cat_id and roles_permissions.role_id= $role_id WHERE permission_category.perm_group_id = $group_id ORDER BY `permission_category`.`id`";
        $query = $this->db->query($sql);

        return $query->result();
    }

    public function ensurePublishResultPermissionSetup() {
        $created_at = date('Y-m-d H:i:s');

        $permission_group = $this->db
            ->where('short_code', 'exam_setting')
            ->get('permission_group')
            ->row_array();

        if (!empty($permission_group['id'])) {
            $permission_group_id = (int) $permission_group['id'];
        } else {
            $this->db->insert('permission_group', array(
                'name'       => 'Exam Setting',
                'short_code' => 'exam_setting',
                'is_active'  => 1,
                'system'     => 0,
                'created_at' => $created_at,
            ));
            $permission_group_id = (int) $this->db->insert_id();
        }

        $permission_category = $this->db
            ->where('short_code', 'publish_result')
            ->get('permission_category')
            ->row_array();

        $permission_data = array(
            'perm_group_id' => $permission_group_id,
            'name'          => 'Publish Result',
            'short_code'    => 'publish_result',
            'enable_view'   => 0,
            'enable_add'    => 0,
            'enable_edit'   => 1,
            'enable_delete' => 0,
        );

        if (!empty($permission_category['id'])) {
            $permission_category_id = (int) $permission_category['id'];
            $this->db->where('id', $permission_category_id);
            $this->db->update('permission_category', $permission_data);
        } else {
            $permission_data['created_at'] = $created_at;
            $this->db->insert('permission_category', $permission_data);
            $permission_category_id = (int) $this->db->insert_id();
        }

        if ($permission_category_id <= 0) {
            return 0;
        }

        $default_roles = $this->db
            ->select('id')
            ->from('roles')
            ->where_in('name', array('Admin', 'Super Admin', 'Head Teacher'))
            ->get()
            ->result_array();

        if (empty($default_roles)) {
            return $permission_category_id;
        }

        $existing_rows = $this->db
            ->select('role_id')
            ->from('roles_permissions')
            ->where('perm_cat_id', $permission_category_id)
            ->get()
            ->result_array();

        $existing_role_ids = array_map('intval', array_column($existing_rows, 'role_id'));
        $insert_rows = array();

        foreach ($default_roles as $role) {
            $role_id = (int) $role['id'];

            if ($role_id <= 0 || in_array($role_id, $existing_role_ids, true)) {
                continue;
            }

            $insert_rows[] = array(
                'role_id'    => $role_id,
                'perm_cat_id'=> $permission_category_id,
                'can_view'   => 0,
                'can_add'    => 0,
                'can_edit'   => 1,
                'can_delete' => 0,
                'created_at' => $created_at,
            );
        }

        if (!empty($insert_rows)) {
            $this->db->insert_batch('roles_permissions', $insert_rows);
        }

        return $permission_category_id;
    }

    public function getInsertBatch($role_id, $to_be_insert = array(), $to_be_update = array(), $to_be_delete = array()) {
        $this->db->trans_start();
        $this->db->trans_strict(FALSE);
        if (!empty($to_be_insert)) {
            $this->db->insert_batch('roles_permissions', $to_be_insert);
        }
        if (!empty($to_be_update)) {

            $this->db->update_batch('roles_permissions', $to_be_update, 'id');
        }


// # Updating data
        if (!empty($to_be_delete)) {
            $this->db->where('role_id', $role_id);
            $this->db->where_in('id', $to_be_delete);
            $this->db->delete('roles_permissions');
        }
        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {

            $this->db->trans_rollback();
            return FALSE;
        } else {

            $this->db->trans_commit();
            return TRUE;
        }
    }

    public function count_roles($id) {

        $query = $this->db->select("*")->join("staff", "staff.id = staff_roles.staff_id")->where("staff_roles.role_id", $id)->where("staff.is_active", 1)->get("staff_roles");

        return $query->num_rows();
    }

}
