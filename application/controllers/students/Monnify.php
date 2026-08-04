<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Monnify extends Student_Controller
{
    public $api_config;
    public $setting;

    public function __construct()
    {
        parent::__construct();
        $this->api_config = $this->paymentsetting_model->getByType('monnify');
        $this->setting = $this->setting_model->get();
        $this->load->model('monnify_payment_model');

        if (!empty($this->api_config)) {
            $this->load->library('monnify_gateway', $this->gatewayConfig());
        }
    }

    public function index()
    {
        $this->renderPaymentPage();
    }

    public function pay()
    {
        $this->form_validation->set_rules('phone', $this->lang->line('phone'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', $this->lang->line('email'), 'trim|required|valid_email|xss_clean');

        if ($this->form_validation->run() == false) {
            return $this->renderPaymentPage();
        }

        $params = $this->session->userdata('params');
        if (!$this->canStartPayment($params)) {
            return $this->renderPaymentPage('Monnify is not ready. Confirm the gateway settings and run database migration 129.');
        }

        $currency = strtoupper((string) $params['invoice']->currency_name);
        if ($currency !== 'NGN') {
            return $this->renderPaymentPage('Monnify checkout currently requires the school currency to be NGN.');
        }

        $amount = round((float) $params['fine_amount_balance'] + (float) $params['total'], 2);
        if ($amount <= 0) {
            return $this->renderPaymentPage('The payment amount must be greater than zero.');
        }

        $payment_reference = $this->generateReference('SF');
        $email = trim((string) $this->input->post('email', true));
        $phone = trim((string) $this->input->post('phone', true));
        $fee_items = array();
        foreach ($params['student_fees_master_array'] as $fee_value) {
            $fee_items[] = array(
                'student_fees_master_id' => (int) $fee_value['student_fees_master_id'],
                'fee_groups_feetype_id' => (int) $fee_value['fee_groups_feetype_id'],
                'amount' => (float) $fee_value['amount_balance'],
                'fine' => (float) $fee_value['fine_balance'],
            );
        }

        if (!$this->monnify_payment_model->validateStudentFeePayment((int) $params['student_id'], $fee_items, $amount)) {
            return $this->renderPaymentPage('The selected fees no longer match this student or their current balance. Refresh the fees page and try again.');
        }

        $created = $this->monnify_payment_model->createPending(array(
            'payment_reference' => $payment_reference,
            'payment_context' => 'student_fee',
            'context_id' => (int) $params['student_id'],
            'amount' => $amount,
            'currency' => $currency,
            'customer_email' => $email,
            'customer_name' => $params['name'],
            'gateway_mode' => (int) $this->api_config->gateway_mode,
            'context_data' => array('guardian_phone' => $phone, 'fee_items' => $fee_items),
        ));
        if (!$created) {
            return $this->renderPaymentPage('Unable to prepare the Monnify payment. Please try again.');
        }

        $payload = array(
            'amount' => $amount,
            'customerName' => $params['name'],
            'customerEmail' => $email,
            'paymentReference' => $payment_reference,
            'paymentDescription' => 'School fee payment',
            'currencyCode' => $currency,
            'contractCode' => $this->api_config->api_username,
            'redirectUrl' => base_url('students/monnify/complete/' . $payment_reference),
            'paymentMethods' => array('CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'),
            'metadata' => array('paymentContext' => 'student_fee', 'studentId' => (string) $params['student_id']),
        );
        $result = $this->monnify_gateway->initializeTransaction($payload);
        if (empty($result['success']) || empty($result['response']['responseBody']['checkoutUrl'])) {
            $this->monnify_payment_model->markInitializationFailed($payment_reference, $result);
            $message = !empty($result['message']) ? $result['message'] : 'Unable to initialize Monnify payment at the moment.';
            return $this->renderPaymentPage($message);
        }

        $response_body = $result['response']['responseBody'];
        $transaction_reference = isset($response_body['transactionReference']) ? $response_body['transactionReference'] : null;
        $this->monnify_payment_model->markInitialized($payment_reference, $transaction_reference, $result['response']);
        redirect($response_body['checkoutUrl']);
    }

    public function complete($payment_reference)
    {
        $payment_reference = trim((string) $payment_reference);
        if (empty($this->api_config)) {
            redirect(base_url('students/payment/paymentfailed'));
        }
        $payment = $this->monnify_payment_model->findByPaymentReference($payment_reference);
        if (empty($payment) || (int) $payment->gateway_mode !== (int) $this->api_config->gateway_mode) {
            redirect(base_url('students/payment/paymentfailed'));
        }

        if ($payment->status === 'processed') {
            redirect(base_url('students/payment/successinvoice'));
        }

        $verification = $this->monnify_gateway->verifyTransaction($payment_reference);
        if (empty($verification['success']) || empty($verification['response']['responseBody'])) {
            redirect(base_url('students/payment/paymentfailed'));
        }

        $result = $this->monnify_payment_model->fulfillVerifiedPayment($payment_reference, $verification['response']['responseBody']);
        if (!empty($result['success']) && isset($result['status']) && $result['status'] === 'processed') {
            redirect(base_url('students/payment/successinvoice'));
        }

        if (!empty($result['success']) && isset($result['status']) && $result['status'] === 'processing') {
            log_message('info', 'Monnify payment ' . $payment_reference . ' is still being fulfilled by another request.');
        }

        redirect(base_url('students/payment/paymentfailed'));
    }

    protected function renderPaymentPage($api_error = '')
    {
        $params = $this->session->userdata('params');
        if (empty($params) || empty($params['student_id']) || empty($params['student_fees_master_array'])) {
            redirect(base_url('students/payment/paymentfailed'));
        }

        $data = array(
            'params' => $params,
            'setting' => $this->setting,
            'api_error' => $api_error,
            'student_data' => $this->student_model->get($params['student_id']),
            'student_fees_master_array' => $params['student_fees_master_array'],
        );
        $this->load->view('student/monnify', $data);
    }

    protected function canStartPayment($params)
    {
        $active_gateway = $this->paymentsetting_model->getActiveMethod();

        return !empty($active_gateway)
            && isset($active_gateway->payment_type)
            && strtolower((string) $active_gateway->payment_type) === 'monnify'
            && !empty($this->api_config)
            && !empty($this->api_config->api_publishable_key)
            && !empty($this->api_config->api_secret_key)
            && !empty($this->api_config->api_username)
            && !empty($params['student_fees_master_array'])
            && $this->monnify_payment_model->isReady();
    }

    protected function gatewayConfig()
    {
        return array(
            'api_key' => $this->api_config->api_publishable_key,
            'secret_key' => $this->api_config->api_secret_key,
            'contract_code' => $this->api_config->api_username,
            'gateway_mode' => (int) $this->api_config->gateway_mode,
        );
    }

    protected function generateReference($context)
    {
        return 'MNFY-' . $context . '-' . date('YmdHis') . '-' . mt_rand(100000, 999999);
    }
}
