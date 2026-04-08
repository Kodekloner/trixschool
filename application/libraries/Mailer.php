<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Mailer {

    public $mail_config;
    private $sch_setting;

    private function maskValue($value) {
        $value = (string) $value;
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 2) . str_repeat('*', max(0, $length - 4)) . substr($value, -2);
    }
 
    public function __construct() {
        $this->CI = &get_instance();
        $this->CI->load->model('emailconfig_model');
        $this->CI->mail_config = $this->CI->emailconfig_model->getActiveEmail();
        $this->CI->load->model('setting_model');
        $this->sch_setting = $this->CI->setting_model->get();
    }
 
    public function send_mail($toemail, $subject, $body, $FILES = array(), $cc = "") {

        if (empty($this->CI->mail_config)) {
            log_message('error', 'Mailer send failed: no active email configuration found.');
            return false;
        }

        $mail = new PHPMailer();
        $mail->CharSet = 'UTF-8';
        $school_name = $this->sch_setting[0]['name'];
        $school_email = $this->sch_setting[0]['email'];
        $from_email   = filter_var($school_email, FILTER_VALIDATE_EMAIL) ? $school_email : '';
        $smtp_debug_output = array();

        if ($from_email === '' && filter_var($this->CI->mail_config->smtp_username, FILTER_VALIDATE_EMAIL)) {
            $from_email = $this->CI->mail_config->smtp_username;
        }

        if ($this->CI->mail_config->email_type == "smtp") {

            $mail->IsSMTP();
            $mail->SMTPDebug  = 2;
            $mail->Debugoutput = function ($str, $level) use (&$smtp_debug_output) {
                $debug_line = 'Mailer SMTP debug level ' . $level . ': ' . trim($str);
                $smtp_debug_output[] = $debug_line;
                log_message('error', $debug_line);
            };
            $mail->SMTPAuth   = ($this->CI->mail_config->smtp_auth != "") ? $this->CI->mail_config->smtp_auth : "";
            $mail->SMTPSecure = $this->CI->mail_config->ssl_tls;
            $mail->Host       = $this->CI->mail_config->smtp_server;
            $mail->Port       = $this->CI->mail_config->smtp_port;
            $mail->Username   = $this->CI->mail_config->smtp_username;
            $mail->Password   = $this->CI->mail_config->smtp_password;
            if ($from_email !== '') {
                $mail->SetFrom($from_email, $school_name);
                $mail->AddReplyTo($from_email, $school_name);
            }
        } else {
            $mail->isSMTP();
            $mail->Host        = 'localhost';
            $mail->SMTPAuth    = false;
            $mail->SMTPAutoTLS = false;
            $mail->Port        = 25;
            if ($from_email !== '') {
                $mail->SetFrom($from_email, $school_name);
                $mail->AddReplyTo($from_email, $school_name);
            }
        }
        if (!empty($FILES)) {
            if (isset($_FILES['files']) && !empty($_FILES['files'])) {
                $no_files = count($_FILES["files"]['name']);
                for ($i = 0; $i < $no_files; $i++) {
                    if ($_FILES["files"]["error"][$i] > 0) {
                        echo "Error: " . $_FILES["files"]["error"][$i] . "<br>";
                    } else {
                        $file_tmp = $_FILES["files"]["tmp_name"][$i];
                        $file_name = $_FILES["files"]["name"][$i];
                        $mail->AddAttachment($file_tmp, $file_name);
                    }
                }
            }
        }
        if ($cc != "") {

            $mail->AddCC($cc);
        }

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $body;
        $mail->AddAddress($toemail);
        if ($mail->Send()) {
            log_message(
                'info',
                'Mailer send success: to=' . $toemail .
                ', subject=' . $subject .
                ', host=' . $this->CI->mail_config->smtp_server .
                ', port=' . $this->CI->mail_config->smtp_port
            );
            return true;
        } else {
            log_message(
                'error',
                'Mailer send failed: to=' . $toemail .
                ', subject=' . $subject .
                ', host=' . $this->CI->mail_config->smtp_server .
                ', port=' . $this->CI->mail_config->smtp_port .
                ', secure=' . $this->CI->mail_config->ssl_tls .
                ', auth=' . $this->CI->mail_config->smtp_auth .
                ', username=' . $this->maskValue($this->CI->mail_config->smtp_username) .
                ', from=' . $from_email .
                ', error=' . $mail->ErrorInfo
            );

            if (!empty($smtp_debug_output)) {
                log_message('error', 'Mailer SMTP transcript: ' . implode(' | ', $smtp_debug_output));
            }
            return false;
        }
    }

}
