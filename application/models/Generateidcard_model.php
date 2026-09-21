<?php
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Generateidcard_model extends CI_model {

    function __construct() {
        parent::__construct();
        $this->current_session = $this->setting_model->getCurrentSession();
    }

    public function getstudentidcard() {
        $this->db->select('*');
        $this->db->from('id_card');
        $query = $this->db->get();
        return $this->withLegacyOptions($query->result());
    }

    public function getidcardbyid($idcard) {
        $this->db->select('*');
        $this->db->from('id_card');
        $this->db->where('id', $idcard);
        $query = $this->db->get();
        return $this->withLegacyOptions($query->result());
    }

    private function withLegacyOptions($records) {
        foreach ($records as $record) {
            $layout = !empty($record->layout_json) ? json_decode($record->layout_json, true) : array();
            if (!is_array($layout)) {
                $layout = array();
            }
            $record->enable_attendance_qr = !empty($layout['_options']['attendance_qr']) ? 1 : 0;
        }
        return $records;
    }

}

?>
