<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Monnify_payment_model extends MY_Model
{
    const TABLE = 'monnify_payments';

    public function isReady()
    {
        return $this->db->table_exists(self::TABLE);
    }

    public function createPending($data)
    {
        $now = date('Y-m-d H:i:s');
        $record = array(
            'payment_reference' => $data['payment_reference'],
            'transaction_reference' => null,
            'payment_context' => $data['payment_context'],
            'context_id' => (int) $data['context_id'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'currency' => strtoupper($data['currency']),
            'customer_email' => $data['customer_email'],
            'customer_name' => $data['customer_name'],
            'context_data' => json_encode($data['context_data']),
            'gateway_mode' => !empty($data['gateway_mode']) ? 1 : 0,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        );

        return $this->db->insert(self::TABLE, $record);
    }

    public function findByPaymentReference($payment_reference)
    {
        return $this->db
            ->where('payment_reference', $payment_reference)
            ->get(self::TABLE)
            ->row();
    }

    public function markInitialized($payment_reference, $transaction_reference, $response)
    {
        return $this->updateByReference($payment_reference, array(
            'transaction_reference' => $transaction_reference,
            'gateway_response' => json_encode($response),
            'status' => 'initialized',
        ));
    }

    public function markInitializationFailed($payment_reference, $response)
    {
        return $this->updateByReference($payment_reference, array(
            'gateway_response' => json_encode($response),
            'status' => 'initialization_failed',
        ));
    }

    public function fulfillVerifiedPayment($payment_reference, $transaction)
    {
        $payment = $this->findByPaymentReference($payment_reference);
        if (empty($payment)) {
            return array('success' => false, 'status' => 'not_found', 'message' => 'Unknown Monnify payment reference.');
        }

        $validation_error = $this->validateTransaction($payment, $transaction);
        if ($validation_error !== '') {
            $this->updateByReference($payment_reference, array(
                'gateway_response' => json_encode($transaction),
                'status' => 'verification_failed',
            ));
            return array('success' => false, 'status' => 'verification_failed', 'message' => $validation_error, 'payment' => $payment);
        }

        $claim = $this->claimForProcessing($payment_reference, $transaction);
        if (!$claim['claimed']) {
            if ($claim['status'] === 'processed' || $claim['status'] === 'processing') {
                return array('success' => true, 'status' => $claim['status'], 'payment' => $claim['payment']);
            }
            return array('success' => false, 'status' => $claim['status'], 'message' => 'Unable to claim Monnify payment for processing.', 'payment' => $payment);
        }

        $payment = $claim['payment'];
        $context_data = json_decode($payment->context_data, true);
        if (!is_array($context_data)) {
            $this->releaseForRetry($payment_reference, 'Stored payment details are invalid.');
            return array('success' => false, 'status' => 'invalid_context', 'message' => 'Stored payment details are invalid.', 'payment' => $payment);
        }

        if ($payment->payment_context === 'student_fee') {
            $processed = $this->processStudentFee($payment, $context_data);
        } elseif ($payment->payment_context === 'online_admission') {
            $processed = $this->processOnlineAdmission($payment);
        } else {
            $processed = false;
        }

        if (!$processed) {
            $this->releaseForRetry($payment_reference, 'The verified payment could not be applied locally.');
            return array('success' => false, 'status' => 'fulfillment_failed', 'message' => 'The verified payment could not be applied locally.', 'payment' => $payment);
        }

        $marked_processed = $this->updateByReference($payment_reference, array(
            'status' => 'processed',
            'processed_at' => date('Y-m-d H:i:s'),
            'processing_started_at' => null,
        ));
        if (!$marked_processed) {
            return array('success' => false, 'status' => 'database_error', 'message' => 'The payment was applied but its processing status could not be saved.', 'payment' => $payment);
        }

        if ($payment->payment_context === 'online_admission') {
            $this->sendAdmissionNotification($payment);
        }

        $payment->status = 'processed';
        return array('success' => true, 'status' => 'processed', 'payment' => $payment);
    }

    protected function validateTransaction($payment, $transaction)
    {
        $remote_reference = isset($transaction['paymentReference']) ? (string) $transaction['paymentReference'] : '';
        if (!hash_equals((string) $payment->payment_reference, $remote_reference)) {
            return 'The Monnify payment reference does not match.';
        }

        if (!isset($transaction['paymentStatus']) || strtoupper($transaction['paymentStatus']) !== 'PAID') {
            return 'The Monnify transaction is not paid.';
        }

        $amount_paid = isset($transaction['amountPaid']) ? (float) $transaction['amountPaid'] : 0.0;
        if ($amount_paid + 0.009 < (float) $payment->amount) {
            return 'The amount paid is less than the expected amount.';
        }

        $currency = '';
        if (!empty($transaction['currencyCode'])) {
            $currency = $transaction['currencyCode'];
        } elseif (!empty($transaction['currency'])) {
            $currency = $transaction['currency'];
        }
        if (strtoupper($currency) !== strtoupper($payment->currency)) {
            return 'The Monnify payment currency does not match.';
        }

        $remote_transaction_reference = isset($transaction['transactionReference']) ? (string) $transaction['transactionReference'] : '';
        if (!empty($payment->transaction_reference) && !hash_equals((string) $payment->transaction_reference, $remote_transaction_reference)) {
            return 'The Monnify transaction reference does not match.';
        }

        return '';
    }

    protected function claimForProcessing($payment_reference, $transaction)
    {
        $this->db->trans_begin();
        $query = $this->db->query(
            'SELECT * FROM `' . self::TABLE . '` WHERE `payment_reference` = ? LIMIT 1 FOR UPDATE',
            array($payment_reference)
        );
        $payment = $query->row();

        if (empty($payment)) {
            $this->db->trans_rollback();
            return array('claimed' => false, 'status' => 'not_found', 'payment' => null);
        }

        if ($payment->status === 'processed') {
            $this->db->trans_commit();
            return array('claimed' => false, 'status' => 'processed', 'payment' => $payment);
        }

        if ($payment->status === 'processing' && !empty($payment->processing_started_at)) {
            $started_at = strtotime($payment->processing_started_at);
            if ($started_at !== false && $started_at > time() - 300) {
                $this->db->trans_commit();
                return array('claimed' => false, 'status' => 'processing', 'payment' => $payment);
            }
        }

        $now = date('Y-m-d H:i:s');
        $transaction_reference = isset($transaction['transactionReference']) ? $transaction['transactionReference'] : $payment->transaction_reference;
        $this->db->where('id', $payment->id)->update(self::TABLE, array(
            'transaction_reference' => $transaction_reference,
            'gateway_response' => json_encode($transaction),
            'status' => 'processing',
            'processing_started_at' => $now,
            'paid_at' => !empty($payment->paid_at) ? $payment->paid_at : $now,
            'updated_at' => $now,
        ));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('claimed' => false, 'status' => 'database_error', 'payment' => $payment);
        }

        $this->db->trans_commit();
        $payment->status = 'processing';
        $payment->transaction_reference = $transaction_reference;
        return array('claimed' => true, 'status' => 'processing', 'payment' => $payment);
    }

    public function validateStudentFeePayment($student_id, $fee_items, $expected_amount)
    {
        if ((int) $student_id <= 0 || empty($fee_items) || !is_array($fee_items)) {
            return false;
        }

        $allocated_amount = 0.0;
        $validated_keys = array();
        foreach ($fee_items as $fee_item) {
            if (!isset($fee_item['student_fees_master_id'], $fee_item['fee_groups_feetype_id'], $fee_item['amount'], $fee_item['fine'])) {
                return false;
            }

            $student_fees_master_id = (int) $fee_item['student_fees_master_id'];
            $fee_groups_feetype_id = (int) $fee_item['fee_groups_feetype_id'];
            $amount = (float) $fee_item['amount'];
            $fine = (float) $fee_item['fine'];
            $fee_key = $student_fees_master_id . ':' . $fee_groups_feetype_id;
            if (
                $student_fees_master_id <= 0
                || $fee_groups_feetype_id <= 0
                || isset($validated_keys[$fee_key])
                || $amount < 0
                || $fine < 0
            ) {
                return false;
            }

            $validated_keys[$fee_key] = array(
                'student_fees_master_id' => $student_fees_master_id,
                'fee_groups_feetype_id' => $fee_groups_feetype_id,
                'amount' => $amount,
                'fine' => $fine,
            );
            $allocated_amount += $amount + $fine;
        }

        if (abs($allocated_amount - (float) $expected_amount) > 0.01) {
            return false;
        }

        ksort($validated_keys, SORT_STRING);
        if (!$this->db->trans_begin()) {
            return false;
        }

        $payment = (object) array('context_id' => (int) $student_id);
        foreach ($validated_keys as $fee_item) {
            $ledger_item = $this->findStudentFeeLedgerItemForUpdate(
                (int) $student_id,
                $fee_item['student_fees_master_id'],
                $fee_item['fee_groups_feetype_id']
            );
            if (
                !$this->feeItemBelongsToStudent($payment, $fee_item, $ledger_item)
                || !$this->feeItemFitsCurrentBalance($fee_item, $ledger_item)
            ) {
                $this->db->trans_rollback();
                return false;
            }
        }

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->trans_commit();
        return true;
    }

    protected function processStudentFee($payment, $context_data)
    {
        if (empty($context_data['fee_items']) || !is_array($context_data['fee_items'])) {
            return false;
        }

        $this->load->model('studentfeemaster_model');
        $description = 'Online fees deposit through Monnify Ref ID: ' . $payment->payment_reference;
        $allocated_amount = 0.0;
        $seen_fee_items = array();
        foreach ($context_data['fee_items'] as $fee_item) {
            if (!isset($fee_item['student_fees_master_id'], $fee_item['fee_groups_feetype_id'], $fee_item['amount'], $fee_item['fine'])) {
                return false;
            }

            $student_fees_master_id = (int) $fee_item['student_fees_master_id'];
            $fee_groups_feetype_id = (int) $fee_item['fee_groups_feetype_id'];
            $amount = (float) $fee_item['amount'];
            $fine = (float) $fee_item['fine'];
            $fee_key = $student_fees_master_id . ':' . $fee_groups_feetype_id;
            if (
                $student_fees_master_id <= 0
                || $fee_groups_feetype_id <= 0
                || isset($seen_fee_items[$fee_key])
                || $amount < 0
                || $fine < 0
            ) {
                return false;
            }

            $seen_fee_items[$fee_key] = array(
                'student_fees_master_id' => $student_fees_master_id,
                'fee_groups_feetype_id' => $fee_groups_feetype_id,
                'amount' => $amount,
                'fine' => $fine,
            );
            $allocated_amount += $amount + $fine;
        }

        if (abs($allocated_amount - (float) $payment->amount) > 0.01) {
            return false;
        }

        // Use a stable lock order so simultaneous payments for overlapping fees do not
        // validate the same stale balance or deadlock each other unnecessarily.
        ksort($seen_fee_items, SORT_STRING);
        if (!$this->db->trans_begin()) {
            return false;
        }

        $validated_items = array();
        foreach ($seen_fee_items as $fee_item) {
            $ledger_item = $this->findStudentFeeLedgerItemForUpdate(
                (int) $payment->context_id,
                $fee_item['student_fees_master_id'],
                $fee_item['fee_groups_feetype_id']
            );
            if (!$this->feeItemBelongsToStudent($payment, $fee_item, $ledger_item)) {
                $this->db->trans_rollback();
                return false;
            }

            $validated_items[] = array('requested' => $fee_item, 'ledger' => $ledger_item);
        }

        // This recovers a request that stopped after depositing fees but before the
        // Monnify payment row was marked processed. Ownership is still checked first.
        if ($this->studentfeemaster_model->hasPaymentDescription($description)) {
            $this->db->trans_commit();
            return true;
        }

        $bulk_fees = array();
        foreach ($validated_items as $validated_item) {
            $fee_item = $validated_item['requested'];
            if (!$this->feeItemFitsCurrentBalance($fee_item, $validated_item['ledger'])) {
                $this->db->trans_rollback();
                return false;
            }

            $bulk_fees[] = array(
                'student_fees_master_id' => $fee_item['student_fees_master_id'],
                'fee_groups_feetype_id' => $fee_item['fee_groups_feetype_id'],
                'amount_detail' => array(
                    'amount' => $fee_item['amount'],
                    'date' => date('Y-m-d'),
                    'amount_discount' => 0,
                    'amount_fine' => $fee_item['fine'],
                    'description' => $description,
                    'received_by' => '',
                    'payment_mode' => 'Monnify',
                ),
            );
        }

        $processed = $this->studentfeemaster_model->fee_deposit_bulk($bulk_fees);
        if (!$processed || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }

        // fee_deposit_bulk() currently closes a nested transaction itself. Calling
        // commit here is harmless in that version and retains ownership if it changes.
        $this->db->trans_commit();
        return true;
    }

    protected function findStudentFeeLedgerItemForUpdate($student_id, $student_fees_master_id, $fee_groups_feetype_id)
    {
        $query = $this->db->query(
            'SELECT '
            . '`student_fees_master`.`id` AS `student_fees_master_id`, '
            . '`student_fees_master`.`student_session_id`, '
            . '`student_fees_master`.`is_system`, '
            . '`student_fees_master`.`amount` AS `student_fees_master_amount`, '
            . '`student_session`.`student_id`, '
            . '`fee_groups_feetype`.`id` AS `fee_groups_feetype_id`, '
            . '`fee_groups_feetype`.`amount` AS `fee_amount`, '
            . '`fee_groups_feetype`.`due_date`, '
            . '`fee_groups_feetype`.`fine_amount`, '
            . 'IFNULL(`student_fees_deposite`.`amount_detail`, \'0\') AS `amount_detail` '
            . 'FROM `student_fees_master` '
            . 'INNER JOIN `student_session` '
            . 'ON `student_session`.`id` = `student_fees_master`.`student_session_id` '
            . 'INNER JOIN `fee_session_groups` '
            . 'ON `fee_session_groups`.`id` = `student_fees_master`.`fee_session_group_id` '
            . 'AND `fee_session_groups`.`session_id` = `student_session`.`session_id` '
            . 'INNER JOIN `fee_groups_feetype` '
            . 'ON `fee_groups_feetype`.`id` = ? '
            . 'AND `fee_groups_feetype`.`fee_session_group_id` = `fee_session_groups`.`id` '
            . 'LEFT JOIN `student_fees_deposite` '
            . 'ON `student_fees_deposite`.`student_fees_master_id` = `student_fees_master`.`id` '
            . 'AND `student_fees_deposite`.`fee_groups_feetype_id` = `fee_groups_feetype`.`id` '
            . 'WHERE `student_fees_master`.`id` = ? '
            . 'AND `student_session`.`student_id` = ? '
            . 'ORDER BY `student_fees_deposite`.`id` ASC '
            . 'FOR UPDATE',
            array($fee_groups_feetype_id, $student_fees_master_id, $student_id)
        );

        $ledger_rows = $query->result();
        if (empty($ledger_rows)) {
            return null;
        }

        // The normal schema has one deposit row per fee pair, but older databases do
        // not enforce that uniqueness. Count every row so dirty legacy data cannot
        // make the outstanding balance appear larger than it really is.
        $amount_details = array();
        foreach ($ledger_rows as $ledger_row) {
            $row_details = json_decode((string) $ledger_row->amount_detail, true);
            if (!is_array($row_details)) {
                continue;
            }
            foreach ($row_details as $row_detail) {
                if (is_array($row_detail)) {
                    $amount_details[] = $row_detail;
                }
            }
        }

        $ledger_item = $ledger_rows[0];
        $ledger_item->amount_detail = json_encode($amount_details);
        return $ledger_item;
    }

    protected function feeItemBelongsToStudent($payment, $fee_item, $ledger_item)
    {
        return !empty($ledger_item)
            && (int) $payment->context_id > 0
            && (int) $ledger_item->student_id === (int) $payment->context_id
            && (int) $ledger_item->student_fees_master_id === (int) $fee_item['student_fees_master_id']
            && (int) $ledger_item->fee_groups_feetype_id === (int) $fee_item['fee_groups_feetype_id'];
    }

    protected function feeItemFitsCurrentBalance($fee_item, $ledger_item)
    {
        $balances = $this->calculateCurrentFeeBalances($ledger_item);

        return (float) $fee_item['amount'] <= $balances['amount'] + 0.009
            && (float) $fee_item['fine'] <= $balances['fine'] + 0.009;
    }

    protected function calculateCurrentFeeBalances($ledger_item)
    {
        $amount_paid = 0.0;
        $amount_discount = 0.0;
        $fine_paid = 0.0;
        $amount_details = json_decode((string) $ledger_item->amount_detail, true);
        if (is_array($amount_details)) {
            foreach ($amount_details as $amount_detail) {
                if (!is_array($amount_detail)) {
                    continue;
                }
                $amount_paid += isset($amount_detail['amount']) ? (float) $amount_detail['amount'] : 0.0;
                $amount_discount += isset($amount_detail['amount_discount']) ? (float) $amount_detail['amount_discount'] : 0.0;
                $fine_paid += isset($amount_detail['amount_fine']) ? (float) $amount_detail['amount_fine'] : 0.0;
            }
        }

        $fee_amount = !empty($ledger_item->is_system)
            ? (float) $ledger_item->student_fees_master_amount
            : (float) $ledger_item->fee_amount;
        $amount_balance = max(0.0, $fee_amount - $amount_paid - $amount_discount);
        $fine_balance = 0.0;
        $due_date = isset($ledger_item->due_date) ? (string) $ledger_item->due_date : '';
        $due_timestamp = $due_date !== '' && $due_date !== '0000-00-00' ? strtotime($due_date) : false;
        if (
            $amount_balance > 0.009
            && $due_timestamp !== false
            && $due_timestamp < strtotime(date('Y-m-d'))
        ) {
            $fine_balance = max(0.0, (float) $ledger_item->fine_amount - $fine_paid);
        }

        return array('amount' => $amount_balance, 'fine' => $fine_balance);
    }

    protected function processOnlineAdmission($payment)
    {
        $this->load->model('onlinestudent_model');
        $existing = $this->db
            ->where('transaction_id', $payment->payment_reference)
            ->get('online_admission_payment')
            ->row();
        if (!empty($existing)) {
            return true;
        }

        $gateway_response = array(
            'admission_id' => (int) $payment->context_id,
            'paid_amount' => (float) $payment->amount,
            'transaction_id' => $payment->payment_reference,
            'payment_mode' => 'monnify',
            'payment_type' => 'online',
            'note' => 'Payment deposit through Monnify Ref: ' . $payment->payment_reference,
            'date' => date('Y-m-d H:i:s'),
        );

        return (bool) $this->onlinestudent_model->paymentSuccess($gateway_response);
    }

    protected function sendAdmissionNotification($payment)
    {
        $this->load->model('onlinestudent_model');
        $online_data = $this->onlinestudent_model->getAdmissionData($payment->context_id);
        if (empty($online_data)) {
            return;
        }

        $this->load->library('mailsmsconf');
        $apply_date = date('Y-m-d H:i:s');
        $display_date = date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat($apply_date));
        $sender_details = array(
            'firstname' => $online_data->firstname,
            'lastname' => $online_data->lastname,
            'email' => $online_data->email,
            'date' => $display_date,
            'reference_no' => $online_data->reference_no,
            'mobileno' => $online_data->mobileno,
            'paid_amount' => (float) $payment->amount,
        );
        $this->mailsmsconf->mailsms('online_admission_fees_submission', $sender_details);
    }

    protected function releaseForRetry($payment_reference, $message)
    {
        log_message('error', 'Monnify fulfillment failed for ' . $payment_reference . ': ' . $message);
        return $this->updateByReference($payment_reference, array(
            'status' => 'paid',
            'processing_started_at' => null,
        ));
    }

    protected function updateByReference($payment_reference, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->db
            ->where('payment_reference', $payment_reference)
            ->update(self::TABLE, $data);
    }
}
