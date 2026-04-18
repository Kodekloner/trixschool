<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Paystack extends Front_Controller
{

    public $pay_method = "";
    public $amount = 0;

    function __construct() {
        parent::__construct();
        $this->pay_method = $this->paymentsetting_model->getActiveMethod();
        $this->setting = $this->setting_model->getSetting();
        $this->amount = $this->setting->online_admission_amount;
        $this->load->library('mailsmsconf');
        $this->load->model('onlinestudent_model');
    }

    public function index() {

        $reference = $this->session->userdata('reference');
        $data['setting'] = $this->setting;
        $total = $this->amount;
        $data['amount'] = $total;
        $data['error'] = '';
        $this->load->view('onlineadmission/paystack/index', $data);
    } 


    public function pay()
    {
        $reference = $this->session->userdata('reference');

        $amount       = $this->amount * 100;
        $online_data = $this->onlinestudent_model->getAdmissionData($reference);
        if (empty($online_data) || empty($online_data->email)) {
            $data['error'] = "<div class='alert alert-danger'>Unable to find the admission record for this payment.</div>";
            $data['setting'] = $this->setting;
            $data['amount'] = $this->amount;
            $this->load->view('onlineadmission/paystack/index', $data);
            return;
        }
        $this->session->set_userdata('payment_amount',$this->amount);
        $ref          = $this->generateReference();
        $callback_url = base_url() . 'onlineadmission/paystack/complete/' . $ref;
        $postdata     = array('email' => $online_data->email, 'amount' => $amount, "reference" => $ref, "callback_url" => $callback_url);
        $result = $this->paystackRequest('https://api.paystack.co/transaction/initialize', $postdata);
        
        if (!empty($result['status']) && !empty($result['data']['authorization_url'])) {
            redirect($result['data']['authorization_url']);
        }else{
            $data['error']="<div class='alert alert-danger'>".(isset($result['message']) ? $result['message'] : 'Unable to initialize Paystack payment at the moment.')."</div>";
            $reference = $this->session->userdata('reference');
            $data['setting'] = $this->setting;
            $total = $this->amount;
            $data['amount'] = $total;
            $this->load->view('onlineadmission/paystack/index', $data);
        }
    }

    public function complete($ref)
    {
        $amount = $this->amount;
        $reference  = $this->session->userdata('reference');
        $online_data = $this->onlinestudent_model->getAdmissionData($reference);
        if (empty($online_data)) {
            redirect(base_url("onlineadmission/checkout/paymentfailed/" . $ref));
        }

        if ($this->db->where('transaction_id', $ref)->count_all_results('online_admission_payment') > 0) {
            redirect(base_url("onlineadmission/checkout/successinvoice/".$online_data->reference_no));
        }

        $apply_date=date("Y-m-d H:i:s");
        $date         = date($this->customlib->getSchoolDateFormat(), $this->customlib->dateyyyymmddTodateformat($apply_date));
        $result = $this->paystackRequest('https://api.paystack.co/transaction/verify/' . $ref);
        if (empty($result['status']) || empty($result['data'])) {
            redirect(base_url("onlineadmission/checkout/paymentfailed/".$online_data->reference_no));
        }

        $transaction = $result['data'];
        $expected_amount = (int) round($amount * 100);
        if ($transaction['status'] !== 'success' || $transaction['reference'] !== $ref || (int) $transaction['amount'] !== $expected_amount) {
            redirect(base_url("onlineadmission/checkout/paymentfailed/".$online_data->reference_no));
        }

        $gateway_response['admission_id']   = $reference; 
        $gateway_response['paid_amount']    = $amount;
        $gateway_response['transaction_id'] = $ref;
        $gateway_response['payment_mode']   = 'paystack';
        $gateway_response['payment_type']   = 'online';
        $gateway_response['note']           = "Payment deposit through Paystack TXN ID: " . $ref;
        $gateway_response['date']           = date("Y-m-d H:i:s");
        $return_detail                      = $this->onlinestudent_model->paymentSuccess($gateway_response);
        if (!$return_detail) {
            redirect(base_url("onlineadmission/checkout/paymentfailed/".$online_data->reference_no));
        }

        $sender_details = array('firstname' => $online_data->firstname, 'lastname' => $online_data->lastname, 'email' => $online_data->email,'date'=>$date,'reference_no'=>$online_data->reference_no,'mobileno'=>$online_data->mobileno,'paid_amount'=>$amount);
        $this->mailsmsconf->mailsms('online_admission_fees_submission', $sender_details);
        redirect(base_url("onlineadmission/checkout/successinvoice/".$online_data->reference_no));
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
            'Authorization: Bearer ' . $this->pay_method->api_secret_key,
            'Content-Type: application/json',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($request === false) {
            log_message('error', 'Paystack online admission request failed: ' . $curl_error);
            return array('status' => false, 'message' => 'Unable to connect to Paystack right now.');
        }

        $result = json_decode($request, true);
        if (!is_array($result)) {
            log_message('error', 'Paystack online admission request returned invalid JSON. HTTP ' . $http_code . ' Response: ' . $request);
            return array('status' => false, 'message' => 'Invalid response received from Paystack.');
        }

        if (empty($result['status'])) {
            $message = isset($result['message']) ? $result['message'] : 'Paystack request failed.';
            log_message('error', 'Paystack online admission request failed. HTTP ' . $http_code . ' Message: ' . $message);
            return array('status' => false, 'message' => $message);
        }

        return $result;
    }

}
