<?php

class Student_id_card_model extends MY_model {

    public function idcardlist() {
        $this->db->select('*');
        $this->db->from('id_card');
        $query = $this->db->get();
        return $this->withLegacyOptions($query->result());
    }

    public function addidcard($data) {
        $data = $this->prepareLegacyOptions($data);
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        if (isset($data['id'])) {
            $this->db->where('id', $data['id']);
            $this->db->update('id_card', $data);
            $message = UPDATE_RECORD_CONSTANT . " On  id card id " . $data['id'];
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
            $this->db->insert('id_card', $data);
            $insert_id = $this->db->insert_id();
            $message = INSERT_RECORD_CONSTANT . " On id card id " . $insert_id;
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

    public function idcardbyid($id) {
        $this->db->select('*');
        $this->db->from('id_card');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $this->withLegacyOption($query->row());
    }

    public function get($id) {
        $this->db->select('*');
        $this->db->from('id_card');
        $this->db->where('status = 1');
        $this->db->where('id', $id);
        $query = $this->db->get();
        return $this->withLegacyOptions($query->result());
    }

    public function remove($id) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->delete('id_card');
        $message = DELETE_RECORD_CONSTANT . " On id card id " . $id;
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
     * Legacy templates already have a JSON metadata column for layout details.
     * Keep optional legacy renderer settings there so tenant databases do not
     * need another schema migration for a single switch.
     */
    private function prepareLegacyOptions($data)
    {
        if (!array_key_exists('enable_attendance_qr', $data)) {
            return $data;
        }

        $enabled = !empty($data['enable_attendance_qr']) ? 1 : 0;
        unset($data['enable_attendance_qr']);

        if (!$this->db->field_exists('layout_json', 'id_card')) {
            return $data;
        }

        $raw = isset($data['layout_json']) ? $data['layout_json'] : '';
        if ($raw === '' && !empty($data['id'])) {
            $current = $this->db->select('layout_json')->where('id', (int) $data['id'])
                ->limit(1)->get('id_card')->row_array();
            $raw = isset($current['layout_json']) ? $current['layout_json'] : '';
        }
        $layout = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : array();
        if (!is_array($layout)) {
            $layout = array();
        }
        if (!isset($layout['_options']) || !is_array($layout['_options'])) {
            $layout['_options'] = array();
        }
        $layout['_options']['attendance_qr'] = $enabled;
        $data['layout_json'] = json_encode($layout, JSON_UNESCAPED_SLASHES);
        return $data;
    }

    private function withLegacyOptions($records)
    {
        foreach ($records as $record) {
            $this->withLegacyOption($record);
        }
        return $records;
    }

    private function withLegacyOption($record)
    {
        if (!$record) {
            return $record;
        }
        $layout = !empty($record->layout_json) ? json_decode($record->layout_json, true) : array();
        if (!is_array($layout)) {
            $layout = array();
        }
        $record->enable_attendance_qr = !empty($layout['_options']['attendance_qr']) ? 1 : 0;
        return $record;
    }

}

?>
