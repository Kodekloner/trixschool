<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Feetype_model extends MY_Model {

    public function __construct() {
        parent::__construct();
    }

    public function get($id = null) {
        $this->db->select()->from('feetype');
        $this->db->where('is_system', 0);
        if ($id != null) {
            $this->db->where('id', $id);
        } else {
            $this->db->order_by('id');
        }
        $query = $this->db->get();
        if ($id != null) {
            return $query->row_array();
        } else {
            return $query->result_array();
        }
    }
    
    public function next_get($session, $id = null) {
        $this->db->select('feenext.*, classes.class');
        $this->db->from('feenext');
        $this->db->join('classes', 'feenext.class_id = classes.id', 'left');
        $this->db->where('feenext.is_system', 0);
    
        if ($id !== null) {
            $this->db->where('feenext.id', $id);
        } else {
            $this->db->where('feenext.session', $session);
            $this->db->order_by('feenext.id', 'asc');
        }
    
        $query = $this->db->get();
    
        if ($id !== null) {
            return $query->row_array();
        } else {
            return $query->result_array();
        }
    }


    public function next_get_fee($class_id = null, $session, $term) {
        $this->db->select('feenext.*, classes.class');
        $this->db->where('feenext.is_system', 0);
    
        if ($class_id !== null) {
            $this->db->where('feenext.class_id', $class_id); // Use class_id for filtering
            $this->db->where('feenext.term', $term);
            $this->db->where('feenext.session', $session);
        } else {
            $this->db->order_by('feenext.id', 'asc'); // Update order_by clause if needed
        }
    
        $query = $this->db->get();
    
        if ($class_id !== null) {
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
        $this->db->where('is_system', 0);
        $this->db->delete('feetype');
        $message = DELETE_RECORD_CONSTANT . " On  fee type id " . $id;
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
    
    public function next_remove($id) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->where('is_system', 0);
        $this->db->delete('feenext');
        $message = DELETE_RECORD_CONSTANT . " On  fee type id " . $id;
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
    
    public function affective_domain_remove($id) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->delete('affective_domain_settings');
        $message = DELETE_RECORD_CONSTANT . " On  Affective Domain id " . $id;
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
    
    public function psycomotor_remove($id) {
        $this->db->trans_start(); # Starting Transaction
        $this->db->trans_strict(false); # See Note 01. If you wish can remove as well
        //=======================Code Start===========================
        $this->db->where('id', $id);
        $this->db->delete('psycomotor_settings');
        $message = DELETE_RECORD_CONSTANT . " On  Psycomomtor id " . $id;
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
			// This is an update operation
			$this->db->where('id', $data['id']);
			// Remove the "id" field from the data to avoid updating it in the database
			unset($data['id']);
			// Set the value of the "occurence" field from the form input
			
			$this->db->update('feetype', $data);
			$message = UPDATE_RECORD_CONSTANT . " On  fee type id " . $data['id'];
			$action = "Update";
			$record_id = $data['id'];
			$this->log($message, $record_id, $action);
		} else {
			// This is an insert operation
			// Set the value of the "occurence" field from the form input
			$this->db->insert('feetype', $data);
			$id = $this->db->insert_id();
			$message = INSERT_RECORD_CONSTANT . " On  fee type id " . $id;
			$action = "Insert";
			$record_id = $id;
			$this->log($message, $record_id, $action);
		}
		//======================Code End==============================

		$this->db->trans_complete(); # Completing transaction
		/* Optional */

		if ($this->db->trans_status() === false) {
			# Something went wrong.
			$this->db->trans_rollback();
			return false;
		} else {
			return $id;
		}            
	}
	
	
	public function next_add($data) {
		$this->db->trans_start(); # Starting Transaction
		$this->db->trans_strict(false); # See Note 01. If you wish can remove as well
		//=======================Code Start===========================
		if (isset($data['id'])) {
			// This is an update operation
			$this->db->where('id', $data['id']);
			// Remove the "id" field from the data to avoid updating it in the database
			unset($data['id']);
			// Set the value of the "occurence" field from the form input
			
			$this->db->update('feenext', $data);
			$message = UPDATE_RECORD_CONSTANT . " On  next fee id " . $data['id'];
			$action = "Update";
			$record_id = $data['id'];
			$this->log($message, $record_id, $action);
		} else {
			// This is an insert operation
			// Set the value of the "occurence" field from the form input
			$this->db->insert('feenext', $data);
			$id = $this->db->insert_id();
			$message = INSERT_RECORD_CONSTANT . " On  next fee id " . $id;
			$action = "Insert";
			$record_id = $id;
			$this->log($message, $record_id, $action);
		}
		//======================Code End==============================

		$this->db->trans_complete(); # Completing transaction
		/* Optional */

		if ($this->db->trans_status() === false) {
			# Something went wrong.
			$this->db->trans_rollback();
			return false;
		} else {
			return $id;
		}            
	}



    public function check_exists($str) {
        $name = $this->security->xss_clean($str);
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
        $this->db->where('type', $name);
        $this->db->where('id !=', $id);

        $query = $this->db->get('feetype');
        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function checkFeetypeByName($name) {
        $this->db->where('type', $name);


        $query = $this->db->get('feetype');
        if ($query->num_rows() > 0) {
            return $query->row();
        } else {
            return FALSE;
        }
    }

}
