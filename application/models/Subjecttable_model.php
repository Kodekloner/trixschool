<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Subjecttable_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    public function remove($id) {
        $this->db->where('id', $id);
        $this->db->delete('subjecttables');
    }

    public function add($data) {
        if (($data['id']) != 0) {
            $this->db->where('id', $data['id']);
            $this->db->update('subjecttables', $data);
        } else {
            $this->db->insert('subjecttables', $data);
            return $this->db->insert_id();
        }
    }

    public function get($data) {
        $query = $this->db->get_where('subjecttables', $data);
        return $query->result_array();
    }

}
