<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Paystack extends Student_Controller
{

    public $api_config = "";
 
    public function __construct()
    {
        parent::__construct();
        $this->api_config = $this->paymentsetting_model->getActiveMethod();
        $this->setting    = $this->setting_model->get();
    }

    public function index()
    {

        $data                 = array();
        $data['params']       = $this->session->userdata('params');
        $data['setting']      = $this->setting;
        $data['api_error']    = array();
        $data['student_data'] = $this->student_model->get($data['params']['student_id']);
        $data['student_fees_master_array']=$data['params']['student_fees_master_array'];
        $this->load->view('student/paystack', $data);
    }

    public function paystack_pay()
    {
        $this->form_validation->set_rules('phone', $this->lang->line('phone'), 'trim|required|xss_clean');
        $this->form_validation->set_rules('email', $this->lang->line('email'), 'trim|required|xss_clean');

        if ($this->form_validation->run() == false) {
            $data              = array();
            $data['params']    = $this->session->userdata('params');
            $data['setting']   = $this->setting;
            $data['api_error'] = array();
            $data['student_data'] = $this->student_model->get($data['params']['student_id']);
            $data['student_fees_master_array'] = $data['params']['student_fees_master_array'];
            $this->load->view('student/paystack', $data);
        } else {
            $params                         = $this->session->userdata('params');
            $data                           = array();
            $amount                         = number_format((float) ($params['fine_amount_balance'] + $params['total']), 2, '.', '');
            $total                          = $amount;
            $data['student_id']             = $params['student_id'];
            $data['total']                  = $total * 100;
            $data['symbol']                 = $params['invoice']->symbol;
            $data['currency_name']          = $params['invoice']->currency_name;
            $data['name']                   = $params['name'];
            $data['guardian_phone']         = $params['guardian_phone'];

            if (isset($data)) {
                $amount       = $data['total'];
                $ref          = $this->generateReference();
                $callback_url = base_url() . 'students/paystack/verify_payment/' . $ref;
                $postdata     = array('email' => $this->input->post('email'), 'amount' => $amount, "reference" => $ref, "callback_url" => $callback_url);
                $result       = $this->paystackRequest('https://api.paystack.co/transaction/initialize', $postdata);

                if (!empty($result['status']) && !empty($result['data']['authorization_url'])) {
                    redirect($result['data']['authorization_url']);
                } else {
                    $data['params']    = $this->session->userdata('params');
                    $data['setting']   = $this->setting;
                    $data['student_data'] = $this->student_model->get($data['params']['student_id']);
                    $data['api_error'] = isset($result['message']) ? $result['message'] : 'Unable to initialize Paystack payment at the moment.';
                    $data['student_fees_master_array'] = $data['params']['student_fees_master_array'];
                    $this->load->view('student/paystack', $data);
                }
            }
        }
    }

    public function verify_payment($ref)
    {
        $description = "Online fees deposit through Paystack Ref ID: " . $ref;
        if ($this->studentfeemaster_model->hasPaymentDescription($description)) {
            redirect(base_url("students/payment/successinvoice"));
        }

        $params = $this->session->userdata('params');
        if (empty($params) || empty($params['student_fees_master_array'])) {
            redirect(base_url("students/payment/paymentfailed"));
        }

        $result = $this->paystackRequest('https://api.paystack.co/transaction/verify/' . $ref);
        if (empty($result['status']) || empty($result['data'])) {
            redirect(base_url("students/payment/paymentfailed"));
        }

        $transaction = $result['data'];
        $expected_amount = (int) round(($params['fine_amount_balance'] + $params['total']) * 100);
        if ($transaction['status'] !== 'success' || $transaction['reference'] !== $ref || (int) $transaction['amount'] !== $expected_amount) {
            redirect(base_url("students/payment/paymentfailed"));
        }

        $bulk_fees = array();
        foreach ($params['student_fees_master_array'] as $fee_key => $fee_value) {
            $json_array = array(
                'amount'          => $fee_value['amount_balance'],
                'date'            => date('Y-m-d'),
                'amount_discount' => 0,
                'amount_fine'     => $fee_value['fine_balance'],
                'description'     => $description,
                'received_by'     => '',
                'payment_mode'    => 'Paystack',
            );

            $bulk_fees[] = array(
                'student_fees_master_id' => $fee_value['student_fees_master_id'],
                'fee_groups_feetype_id'  => $fee_value['fee_groups_feetype_id'],
                'amount_detail'          => $json_array,
            );
        }

        $send_to = $params['guardian_phone'];
        $inserted_id = $this->studentfeemaster_model->fee_deposit_bulk($bulk_fees, $send_to);
        if ($inserted_id) {
            redirect(base_url("students/payment/successinvoice"));
        }

        redirect(base_url('students/payment/paymentfailed'));
    }

    private function generateReference()
    {
        return 'PSK' . date('YmdHis') . mt_rand(1000, 9999);
    }

    private function paystackRequest($url, $payload = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $headers = array(
            'Authorization: Bearer ' . $this->api_config->api_secret_key,
            'Content-Type: application/json',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($request === false) {
            log_message('error', 'Paystack student request failed: ' . $curl_error);
            return array('status' => false, 'message' => 'Unable to connect to Paystack right now.');
        }

        $result = json_decode($request, true);
        if (!is_array($result)) {
            log_message('error', 'Paystack student request returned invalid JSON. HTTP ' . $http_code . ' Response: ' . $request);
            return array('status' => false, 'message' => 'Invalid response received from Paystack.');
        }

        if (empty($result['status'])) {
            $message = isset($result['message']) ? $result['message'] : 'Paystack request failed.';
            log_message('error', 'Paystack student request failed. HTTP ' . $http_code . ' Message: ' . $message);
            return array('status' => false, 'message' => $message);
        }

        return $result;
    }

}
