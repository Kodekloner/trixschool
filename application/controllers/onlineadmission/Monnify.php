<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Monnify extends Front_Controller
{
    public $pay_method;
    public $setting;
    public $amount = 0;

    public function __construct()
    {
        parent::__construct();
        $this->pay_method = $this->paymentsetting_model->getByType('monnify');
        $this->setting = $this->setting_model->getSetting();
        $this->amount = (float) $this->setting->online_admission_amount;
        $this->load->model(array('onlinestudent_model', 'monnify_payment_model'));

        if (!empty($this->pay_method)) {
            $this->load->library('monnify_gateway', $this->gatewayConfig());
        }
    }

    public function index()
    {
        $this->renderPaymentPage();
    }

    public function pay()
    {
        $admission_id = (int) $this->session->userdata('reference');
        $online_data = $this->onlinestudent_model->getAdmissionData($admission_id);
        if (empty($online_data) || empty($online_data->email)) {
            return $this->renderPaymentPage('Unable to find an admission record with a valid email address.');
        }
        if (!$this->canStartPayment()) {
            return $this->renderPaymentPage('Monnify is not ready. Confirm the gateway settings and run database migration 129.');
        }

        $currency = strtoupper((string) $this->setting->currency);
        if ($currency !== 'NGN') {
            return $this->renderPaymentPage('Monnify checkout currently requires the school currency to be NGN.');
        }
        if ($this->amount <= 0) {
            return $this->renderPaymentPage('The admission payment amount must be greater than zero.');
        }

        $payment_reference = $this->generateReference('OA');
        $customer_name = trim($online_data->firstname . ' ' . $online_data->lastname);
        $created = $this->monnify_payment_model->createPending(array(
            'payment_reference' => $payment_reference,
            'payment_context' => 'online_admission',
            'context_id' => $admission_id,
            'amount' => $this->amount,
            'currency' => $currency,
            'customer_email' => $online_data->email,
            'customer_name' => $customer_name,
            'gateway_mode' => (int) $this->pay_method->gateway_mode,
            'context_data' => array('reference_no' => $online_data->reference_no),
        ));
        if (!$created) {
            return $this->renderPaymentPage('Unable to prepare the Monnify payment. Please try again.');
        }

        $payload = array(
            'amount' => $this->amount,
            'customerName' => $customer_name,
            'customerEmail' => $online_data->email,
            'paymentReference' => $payment_reference,
            'paymentDescription' => 'Online admission fee',
            'currencyCode' => $currency,
            'contractCode' => $this->pay_method->api_username,
            'redirectUrl' => base_url('onlineadmission/monnify/complete/' . $payment_reference),
            'paymentMethods' => array('CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'),
            'metadata' => array('paymentContext' => 'online_admission', 'admissionId' => (string) $admission_id),
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
        if (empty($this->pay_method)) {
            redirect(base_url('onlineadmission/checkout/paymentfailed/' . rawurlencode($payment_reference)));
        }
        $payment = $this->monnify_payment_model->findByPaymentReference($payment_reference);
        $failure_reference = $payment_reference;
        if (!empty($payment)) {
            $online_data = $this->onlinestudent_model->getAdmissionData($payment->context_id);
            if (!empty($online_data->reference_no)) {
                $failure_reference = $online_data->reference_no;
            }
        }

        if (empty($payment) || (int) $payment->gateway_mode !== (int) $this->pay_method->gateway_mode) {
            redirect(base_url('onlineadmission/checkout/paymentfailed/' . rawurlencode($failure_reference)));
        }

        if ($payment->status !== 'processed') {
            $verification = $this->monnify_gateway->verifyTransaction($payment_reference);
            if (empty($verification['success']) || empty($verification['response']['responseBody'])) {
                redirect(base_url('onlineadmission/checkout/paymentfailed/' . rawurlencode($failure_reference)));
            }

            $result = $this->monnify_payment_model->fulfillVerifiedPayment($payment_reference, $verification['response']['responseBody']);
            if (empty($result['success']) || !isset($result['status']) || $result['status'] !== 'processed') {
                if (!empty($result['success']) && isset($result['status']) && $result['status'] === 'processing') {
                    log_message('info', 'Monnify payment ' . $payment_reference . ' is still being fulfilled by another request.');
                }
                redirect(base_url('onlineadmission/checkout/paymentfailed/' . rawurlencode($failure_reference)));
            }
        }

        redirect(base_url('onlineadmission/checkout/successinvoice/' . rawurlencode($failure_reference)));
    }

    protected function renderPaymentPage($error = '')
    {
        $data = array('setting' => $this->setting, 'amount' => $this->amount, 'error' => $error);
        $this->load->view('onlineadmission/monnify/index', $data);
    }

    protected function canStartPayment()
    {
        $active_gateway = $this->paymentsetting_model->getActiveMethod();

        return !empty($active_gateway)
            && isset($active_gateway->payment_type)
            && strtolower((string) $active_gateway->payment_type) === 'monnify'
            && !empty($this->pay_method)
            && !empty($this->pay_method->api_publishable_key)
            && !empty($this->pay_method->api_secret_key)
            && !empty($this->pay_method->api_username)
            && $this->monnify_payment_model->isReady();
    }

    protected function gatewayConfig()
    {
        return array(
            'api_key' => $this->pay_method->api_publishable_key,
            'secret_key' => $this->pay_method->api_secret_key,
            'contract_code' => $this->pay_method->api_username,
            'gateway_mode' => (int) $this->pay_method->gateway_mode,
        );
    }

    protected function generateReference($context)
    {
        return 'MNFY-' . $context . '-' . date('YmdHis') . '-' . mt_rand(100000, 999999);
    }
}
