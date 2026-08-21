<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Biometric_attendance_model extends MY_Model
{
    public function isReady()
    {
        foreach (array(
            'biometric_settings', 'biometric_integrations', 'biometric_devices',
            'biometric_punch_state_mappings', 'biometric_identity_mappings',
            'biometric_gateway_batches', 'biometric_gateway_cursors',
            'biometric_events', 'biometric_attendance_days',
            'biometric_exceptions', 'biometric_reconciliation_actions',
            'biometric_scanner_stations', 'biometric_qr_credentials',
            'biometric_audit_logs'
        ) as $table) {
            if (!$this->db->table_exists($table)) {
                return false;
            }
        }
        foreach (array('student_attendences', 'staff_attendance') as $table) {
            if (!$this->db->field_exists('attendance_source', $table)
                || !$this->db->field_exists('biometric_day_id', $table)) {
                return false;
            }
        }
        return true;
    }

    public function getSettings()
    {
        return $this->db->where('id', 1)->get('biometric_settings')->row_array();
    }

    public function saveSettings(array $data)
    {
        $exists = $this->db->where('id', 1)->count_all_results('biometric_settings');
        if ($exists) {
            $this->db->where('id', 1)->update('biometric_settings', $data);
        } else {
            $data['id'] = 1;
            $this->db->insert('biometric_settings', $data);
        }
        return $this->getSettings();
    }

    public function getDevice($id)
    {
        return $this->db->where('id', (int) $id)->get('biometric_devices')->row_array();
    }

    public function findDeviceBySerial($serial)
    {
        return $this->db->where('serial_number', $serial)
            ->get('biometric_devices')->row_array();
    }

    public function saveDevice(array $data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int) $id)->update('biometric_devices', $data);
            return $this->getDevice($id);
        }
        $this->db->insert('biometric_devices', $data);
        return $this->getDevice($this->db->insert_id());
    }

    public function getMapping($id)
    {
        return $this->db->where('id', (int) $id)
            ->get('biometric_identity_mappings')->row_array();
    }

    public function findMappingByCode($code, $date = null)
    {
        $this->db->where('external_person_code', $code)->where('is_active', 1);
        if ($date !== null) {
            $this->db->group_start()
                ->where('valid_from IS NULL', null, false)
                ->or_where('valid_from <=', $date)
                ->group_end();
            $this->db->group_start()
                ->where('valid_until IS NULL', null, false)
                ->or_where('valid_until >=', $date)
                ->group_end();
        }
        return $this->db->get('biometric_identity_mappings')->row_array();
    }

    public function saveMapping(array $data, $id = null)
    {
        if ($id) {
            $this->db->where('id', (int) $id)->update('biometric_identity_mappings', $data);
            return $this->getMapping($id);
        }
        $this->db->insert('biometric_identity_mappings', $data);
        return $this->getMapping($this->db->insert_id());
    }

    public function getIntegration($id)
    {
        return $this->db->where('id', (int) $id)
            ->get('biometric_integrations')->row_array();
    }

    public function findIntegrationByPrefix($prefix)
    {
        return $this->db->where('token_prefix', $prefix)->where('is_active', 1)
            ->get('biometric_integrations')->row_array();
    }

    public function getEvent($id)
    {
        return $this->db->where('id', (int) $id)->get('biometric_events')->row_array();
    }

    public function findEventByDedupKey($dedupKey)
    {
        return $this->db->where('dedup_key', $dedupKey)
            ->get('biometric_events')->row_array();
    }

    public function getDay($id)
    {
        return $this->db->where('id', (int) $id)
            ->get('biometric_attendance_days')->row_array();
    }

    public function getException($id)
    {
        return $this->db->where('id', (int) $id)
            ->get('biometric_exceptions')->row_array();
    }

    public function getScannerStationByUuid($uuid)
    {
        return $this->db->where('station_uuid', $uuid)
            ->get('biometric_scanner_stations')->row_array();
    }

    public function getQrCredentialByUuid($uuid)
    {
        return $this->db->where('credential_uuid', $uuid)
            ->get('biometric_qr_credentials')->row_array();
    }

    public function paginate($table, array $filters, $page, $perPage, $orderBy, $orderDirection = 'DESC')
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(200, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        $this->applyFilters($filters);
        $total = $this->db->count_all_results($table);

        $this->applyFilters($filters);
        $items = $this->db->order_by($orderBy, $orderDirection)
            ->limit($perPage, $offset)->get($table)->result_array();

        return array(
            'items' => $items,
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $total ? (int) ceil($total / $perPage) : 0,
        );
    }

    protected function applyFilters(array $filters)
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $this->db->where_in($field, $value);
            } else {
                $this->db->where($field, $value);
            }
        }
    }
}
