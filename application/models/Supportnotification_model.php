<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Supportnotification_model extends CI_Model
{
    protected $table         = 'support_email_notifications';
    protected $deliveryTable = 'support_email_alert_deliveries';

    public function isReady()
    {
        return $this->db->table_exists($this->table)
            && $this->db->table_exists($this->deliveryTable);
    }

    public function queueIsReady()
    {
        return $this->isReady();
    }

    public function getForStaff($staffId)
    {
        if (!$this->isReady()) {
            return array();
        }

        return $this->db->where('staff_id', (int) $staffId)
            ->get($this->table)
            ->row_array();
    }

    public function setForStaff($staffId, $email, $enabled)
    {
        if (!$this->isReady()) {
            return false;
        }

        $staffId = (int) $staffId;
        $email = strtolower(trim((string) $email));
        if ($staffId <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->getForStaff($staffId);

        // Disabling must remain possible even if an old/manual row contains
        // an address that no longer passes validation.
        if (!$enabled) {
            if (empty($existing)) {
                return true;
            }

            $this->db->where('id', (int) $existing['id'])->update($this->table, array(
                'is_active'  => 0,
                'updated_at' => $now,
            ));
            return $this->db->affected_rows() >= 0;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $payload = array(
            'email'      => $email,
            'is_active'  => 1,
            'updated_at' => $now,
        );

        if (!empty($existing)) {
            $this->db->where('id', (int) $existing['id'])->update($this->table, $payload);
            return $this->db->affected_rows() >= 0;
        }

        $payload['staff_id'] = $staffId;
        $payload['last_notified_at'] = null;
        $payload['last_error'] = null;
        $payload['created_at'] = $now;

        return $this->db->insert($this->table, $payload);
    }

    /**
     * Return enabled staff who still have Support Tickets view permission.
     * Super Admin keeps the same RBAC bypass used by the web application.
     */
    public function getAuthorizedRecipients()
    {
        if (!$this->isReady()
            || !$this->db->table_exists('staff')
            || !$this->db->table_exists('staff_roles')
            || !$this->db->table_exists('roles')
            || !$this->db->table_exists('roles_permissions')
            || !$this->db->table_exists('permission_category')) {
            return array();
        }

        return $this->db
            ->select($this->table . '.id, ' . $this->table . '.staff_id, ' . $this->table . '.email')
            ->from($this->table)
            ->join('staff', 'staff.id = ' . $this->table . '.staff_id', 'inner')
            ->join('staff_roles', 'staff_roles.staff_id = staff.id', 'inner')
            ->join('roles', 'roles.id = staff_roles.role_id', 'inner')
            ->join('permission_category', "permission_category.short_code = 'support_ticket'", 'inner')
            ->join(
                'roles_permissions',
                'roles_permissions.role_id = staff_roles.role_id AND roles_permissions.perm_cat_id = permission_category.id',
                'left'
            )
            ->where($this->table . '.is_active', 1)
            ->where('staff.is_active', 1)
            ->where('LOWER(TRIM(' . $this->table . '.email)) = LOWER(TRIM(staff.email))', null, false)
            ->group_start()
                ->where('roles.name', 'Super Admin')
                ->or_where('roles_permissions.can_view', 1)
            ->group_end()
            ->group_by(array($this->table . '.id', $this->table . '.staff_id', $this->table . '.email'))
            ->order_by($this->table . '.id', 'asc')
            ->get()
            ->result_array();
    }

    public function reserveDelivery(array $data)
    {
        if (!$this->queueIsReady()) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT IGNORE INTO `' . $this->deliveryTable . '` '
            . '(`notification_id`, `incoming_email_id`, `support_ticket_id`, `school_domain`, '
            . '`inbound_address`, `recipient_email`, `delivery_status`, `attempt_count`, '
            . '`available_at`, `last_attempt_at`, `sent_at`, `provider_message_id`, '
            . '`error_message`, `created_at`, `updated_at`) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, NULL, NULL, NULL, NULL, ?, ?)';

        $this->db->query($sql, array(
            (int) $data['notification_id'],
            (int) $data['incoming_email_id'],
            (int) $data['support_ticket_id'],
            strtolower(trim((string) $data['school_domain'])),
            strtolower(trim((string) $data['inbound_address'])),
            strtolower(trim((string) $data['recipient_email'])),
            'pending',
            $now,
            $now,
            $now,
        ));

        return $this->db->affected_rows() === 1 ? (int) $this->db->insert_id() : 0;
    }

    public function getPendingDeliveries($limit = 20)
    {
        if (!$this->queueIsReady()) {
            return array();
        }

        $now = date('Y-m-d H:i:s');
        $stale = date('Y-m-d H:i:s', time() - 900);
        $this->db->query(
            'UPDATE `' . $this->deliveryTable . '` SET `delivery_status` = ?, `available_at` = ?, `updated_at` = ? '
            . 'WHERE `delivery_status` = ? AND `updated_at` < ?',
            array('pending', $now, $now, 'processing', $stale)
        );

        return $this->db->where('delivery_status', 'pending')
            ->where('available_at <=', $now)
            ->order_by('available_at', 'asc')
            ->order_by('id', 'asc')
            ->limit(max(1, min(100, (int) $limit)))
            ->get($this->deliveryTable)
            ->result_array();
    }

    public function claimDelivery($id)
    {
        if (!$this->queueIsReady()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE `' . $this->deliveryTable . '` '
            . 'SET `delivery_status` = ?, `attempt_count` = `attempt_count` + 1, '
            . '`last_attempt_at` = ?, `updated_at` = ? '
            . 'WHERE `id` = ? AND `delivery_status` = ? AND `available_at` <= ?',
            array('processing', $now, $now, (int) $id, 'pending', $now)
        );

        return $this->db->affected_rows() === 1;
    }

    public function completeQueuedDelivery($id, $attemptCount, $sent, $providerMessageId = '', $error = '')
    {
        if (!$this->queueIsReady()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $payload = array(
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : null,
            'error_message'       => $sent ? null : substr(trim((string) $error), 0, 4000),
            'updated_at'          => $now,
        );

        if ($sent) {
            $payload['delivery_status'] = 'sent';
            $payload['sent_at'] = $now;
        } elseif ((int) $attemptCount < 3) {
            $delays = array(1 => 60, 2 => 300);
            $delay = isset($delays[(int) $attemptCount]) ? $delays[(int) $attemptCount] : 900;
            $payload['delivery_status'] = 'pending';
            $payload['available_at'] = date('Y-m-d H:i:s', time() + $delay);
        } else {
            $payload['delivery_status'] = 'failed';
        }

        return $this->db->where('id', (int) $id)
            ->where('delivery_status', 'processing')
            ->update($this->deliveryTable, $payload);
    }

    public function cancelQueuedDelivery($id, $reason)
    {
        if (!$this->queueIsReady()) {
            return false;
        }

        return $this->db->where('id', (int) $id)
            ->where('delivery_status', 'processing')
            ->update($this->deliveryTable, array(
                'delivery_status' => 'cancelled',
                'error_message'   => substr(trim((string) $reason), 0, 4000),
                'updated_at'      => date('Y-m-d H:i:s'),
            ));
    }

    public function recordDelivery($subscriptionId, $sent, $error = '')
    {
        if (!$this->isReady()) {
            return false;
        }

        $payload = array(
            'last_error' => $sent ? null : substr(trim((string) $error), 0, 2000),
        );
        if ($sent) {
            $payload['last_notified_at'] = date('Y-m-d H:i:s');
        }

        $this->db->where('id', (int) $subscriptionId)->update($this->table, $payload);
        return $this->db->affected_rows() >= 0;
    }
}
