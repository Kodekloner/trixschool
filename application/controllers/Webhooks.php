<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Webhooks extends CI_Controller {
	
    public function __construct() {
        parent::__construct();
        $this->load->model('incomingemail_model');
        $this->load->model('supportticket_model');
        $this->load->helper('security');
    }

    public function insta_webhook() {
        $insta_webhook = $this->paymentsetting_model->insta_webhooksalt();

        $data = $_POST;
        $mac_provided = $data['mac'];  // Get the MAC from the POST data
        unset($data['mac']);  // Remove the MAC key from the data.
        $ver = explode('.', phpversion());
        $major = (int) $ver[0];
        $minor = (int) $ver[1];
        if ($major >= 5 and $minor >= 4) {
            ksort($data, SORT_STRING | SORT_FLAG_CASE);
        } else {
            uksort($data, 'strcasecmp');
        }
// You can get the 'salt' from Instamojo's developers page(make sure to log in first): https://www.instamojo.com/developers
// Pass the 'salt' without <>
        $mac_calculated = hash_hmac("sha1", implode("|", $data), "$insta_webhook->salt");
        if ($mac_provided == $mac_calculated) {
            if ($data['status'] == "Credit") {
                // Payment was successful, mark it as successful in your database.
                // You can acess payment_request_id, purpose etc here. 
            } else {
                // Payment was unsuccessful, mark it as failed in your database.
                // You can acess payment_request_id, purpose etc here.
            }
        } else {
            echo "MAC mismatch";
        }
    }

    public function ses_inbound()
    {
        $this->config->load('incoming_email', true);

        $configured_token   = trim((string) $this->config->item('ses_inbound_webhook_token', 'incoming_email'));
        $allowed_topic_arn  = trim((string) $this->config->item('ses_inbound_allowed_topic_arn', 'incoming_email'));
        $auto_confirm       = (bool) $this->config->item('ses_inbound_auto_confirm', 'incoming_email');
        $provided_token     = trim((string) $this->input->get('token', true));
        $raw_payload        = file_get_contents('php://input');
        $decoded_payload    = json_decode($raw_payload, true);

        if ($this->input->method(true) !== 'POST') {
            return $this->respond_json(array('status' => 'error', 'message' => 'Method not allowed'), 405);
        }

        if ($configured_token !== '' && !hash_equals($configured_token, $provided_token)) {
            log_message('error', 'SES inbound webhook rejected: invalid token.');
            return $this->respond_json(array('status' => 'error', 'message' => 'Forbidden'), 403);
        }

        if (!is_array($decoded_payload)) {
            log_message('error', 'SES inbound webhook rejected: invalid JSON payload.');
            return $this->respond_json(array('status' => 'error', 'message' => 'Invalid payload'), 400);
        }

        list($sns_payload, $message_data) = $this->normalizeInboundPayload($decoded_payload);

        if ($allowed_topic_arn !== '' && isset($sns_payload['TopicArn']) && $sns_payload['TopicArn'] !== $allowed_topic_arn) {
            log_message('error', 'SES inbound webhook rejected: unexpected topic ARN ' . $sns_payload['TopicArn']);
            return $this->respond_json(array('status' => 'error', 'message' => 'Unexpected topic'), 403);
        }

        $message_type = isset($sns_payload['Type']) ? (string) $sns_payload['Type'] : '';

        $record = $this->buildIncomingEmailRecord($sns_payload, $message_data, $raw_payload);

        if ($message_type === 'SubscriptionConfirmation' && $auto_confirm && !empty($sns_payload['SubscribeURL'])) {
            $confirmed = $this->confirmSnsSubscription($sns_payload['SubscribeURL']);
            $record['status'] = $confirmed ? 'subscription_confirmed' : 'subscription_pending';
            if (!$confirmed) {
                $record['error_message'] = 'SNS subscription confirmation failed.';
            }
        } elseif ($message_type === 'UnsubscribeConfirmation') {
            $record['status'] = 'unsubscribed';
        }

        $record_id = $this->incomingemail_model->saveFromWebhook($record);
        $support_ticket_id = null;

        if (strtolower($message_type) === 'notification'
            && strtolower((string) $record['ses_notification_type']) === 'received'
            && $this->db->table_exists('support_tickets')
            && $this->db->table_exists('support_messages')) {
            $support_ticket_id = $this->supportticket_model->processIncomingEmail($record_id);
        }

        log_message('info', 'SES inbound webhook stored message #' . $record_id . ' (' . $message_type . ')');

        return $this->respond_json(array('status' => 'ok', 'id' => $record_id, 'support_ticket_id' => $support_ticket_id), 200);
    }

    protected function normalizeInboundPayload($decoded_payload)
    {
        if ($this->isSesNotificationPayload($decoded_payload)) {
            return array($this->buildSnsPayloadFromHeaders(), $decoded_payload);
        }

        $message_body = isset($decoded_payload['Message']) ? (string) $decoded_payload['Message'] : '';
        $message_data = json_decode($message_body, true);

        if (!is_array($message_data)) {
            $message_data = array();
        }

        return array($decoded_payload, $message_data);
    }

    protected function isSesNotificationPayload($payload)
    {
        return is_array($payload) && (isset($payload['notificationType']) || isset($payload['mail']) || isset($payload['receipt']));
    }

    protected function buildSnsPayloadFromHeaders()
    {
        $type = $this->getRequestHeader('X-Amz-Sns-Message-Type');
        if ($type === '') {
            $type = 'Notification';
        }

        return array(
            'Type'      => $type,
            'MessageId' => $this->nullableHeader('X-Amz-Sns-Message-Id'),
            'TopicArn'  => $this->nullableHeader('X-Amz-Sns-Topic-Arn'),
        );
    }

    protected function nullableHeader($name)
    {
        $value = $this->getRequestHeader($name);
        return $value !== '' ? $value : null;
    }

    protected function getRequestHeader($name)
    {
        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$server_key])) {
            return trim((string) $_SERVER[$server_key]);
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $header_name => $value) {
                if (strcasecmp($header_name, $name) === 0) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    protected function buildIncomingEmailRecord($sns_payload, $message_data, $raw_payload)
    {
        $mail         = (isset($message_data['mail']) && is_array($message_data['mail'])) ? $message_data['mail'] : array();
        $receipt      = (isset($message_data['receipt']) && is_array($message_data['receipt'])) ? $message_data['receipt'] : array();
        $action       = (isset($receipt['action']) && is_array($receipt['action'])) ? $receipt['action'] : array();
        $common       = (isset($mail['commonHeaders']) && is_array($mail['commonHeaders'])) ? $mail['commonHeaders'] : array();
        $headers      = $this->normalizeSesHeaders($mail, $common);
        $raw_content  = $this->normalizeRawEmailContent(isset($message_data['content']) ? (string) $message_data['content'] : '', $action);
        $parsed_email = $this->extractEmailBodies($raw_content);

        return array(
            'sns_type'              => isset($sns_payload['Type']) ? $sns_payload['Type'] : null,
            'sns_message_id'        => isset($sns_payload['MessageId']) ? $sns_payload['MessageId'] : null,
            'sns_topic_arn'         => isset($sns_payload['TopicArn']) ? $sns_payload['TopicArn'] : null,
            'ses_notification_type' => isset($message_data['notificationType']) ? $message_data['notificationType'] : null,
            'ses_message_id'        => isset($mail['messageId']) ? $mail['messageId'] : null,
            'source'                => isset($mail['source']) ? $mail['source'] : null,
            'subject'               => isset($common['subject']) ? $common['subject'] : null,
            'destinations'          => isset($mail['destination']) ? $mail['destination'] : array(),
            'recipients'            => isset($receipt['recipients']) ? $receipt['recipients'] : array(),
            'headers'               => $headers,
            'mail_timestamp'        => $this->normalizeTimestamp(isset($mail['timestamp']) ? $mail['timestamp'] : null),
            'receipt_timestamp'     => $this->normalizeTimestamp(isset($receipt['timestamp']) ? $receipt['timestamp'] : null),
            'action_type'           => isset($action['type']) ? $action['type'] : null,
            's3_bucket'             => isset($action['bucketName']) ? $action['bucketName'] : null,
            's3_object_key'         => isset($action['objectKey']) ? $action['objectKey'] : null,
            'spam_status'           => $this->getVerdictStatus($receipt, 'spamVerdict'),
            'virus_status'          => $this->getVerdictStatus($receipt, 'virusVerdict'),
            'dkim_status'           => $this->getVerdictStatus($receipt, 'dkimVerdict'),
            'spf_status'            => $this->getVerdictStatus($receipt, 'spfVerdict'),
            'dmarc_status'          => $this->getVerdictStatus($receipt, 'dmarcVerdict'),
            'body_text'             => $parsed_email['body_text'],
            'body_html'             => $parsed_email['body_html'],
            'attachment_count'      => count($parsed_email['attachment_names']),
            'attachment_names'      => $parsed_email['attachment_names'],
            'raw_content'           => $raw_content !== '' ? $raw_content : null,
            'raw_payload'           => $raw_payload,
            'status'                => 'received',
            'error_message'         => null,
        );
    }

    protected function normalizeRawEmailContent($content, $action)
    {
        $content = (string) $content;
        if (trim($content) === '') {
            return '';
        }

        $encoding = '';
        if (isset($action['encoding'])) {
            $encoding = strtolower(trim((string) $action['encoding']));
        }

        if ($encoding === 'base64') {
            $decoded = $this->decodeBase64Content($content);
            return ($decoded === false) ? $content : $decoded;
        }

        $decoded = $this->decodeBase64Content($content);
        if ($decoded !== false && $this->looksLikeMimeContent($decoded)) {
            return $decoded;
        }

        return $content;
    }

    protected function decodeBase64Content($content)
    {
        $compact = preg_replace('/\s+/', '', (string) $content);
        if ($compact === '') {
            return false;
        }

        return base64_decode($compact, true);
    }

    protected function looksLikeMimeContent($content)
    {
        $content = ltrim((string) $content);
        return (bool) preg_match('/^(Return-Path|Received|From|Date|Subject|To|Content-Type|MIME-Version|Message-ID):/i', $content);
    }

    protected function normalizeSesHeaders($mail, $common)
    {
        $headers = array();

        if (isset($mail['headers']) && is_array($mail['headers'])) {
            foreach ($mail['headers'] as $header) {
                if (!is_array($header) || empty($header['name'])) {
                    continue;
                }

                $name = strtolower(trim((string) $header['name']));
                $value = isset($header['value']) ? $header['value'] : '';

                if ($name === '') {
                    continue;
                }

                if (isset($headers[$name])) {
                    $headers[$name] .= "\n" . $value;
                } else {
                    $headers[$name] = $value;
                }
            }
        }

        foreach ($common as $name => $value) {
            $name = strtolower(trim((string) $name));
            if ($name !== '' && !isset($headers[$name])) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    protected function getVerdictStatus($receipt, $key)
    {
        if (isset($receipt[$key]) && is_array($receipt[$key]) && isset($receipt[$key]['status'])) {
            return $receipt[$key]['status'];
        }

        return null;
    }

    protected function normalizeTimestamp($value)
    {
        if (empty($value)) {
            return null;
        }

        $timestamp = strtotime($value);
        return ($timestamp === false) ? null : date('Y-m-d H:i:s', $timestamp);
    }

    protected function extractEmailBodies($raw_email)
    {
        $result = array(
            'body_text'        => null,
            'body_html'        => null,
            'attachment_names' => array(),
        );

        if (trim((string) $raw_email) === '') {
            return $result;
        }

        list($headers, $body) = $this->splitMimeEntity($raw_email);
        $parsed_headers       = $this->parseMimeHeaders($headers);
        $text_parts           = array();
        $html_parts           = array();
        $attachments          = array();

        $this->collectMimeParts($parsed_headers, $body, $text_parts, $html_parts, $attachments);

        if (!empty($text_parts)) {
            $result['body_text'] = trim(implode("\n\n", $text_parts));
        }

        if (!empty($html_parts)) {
            $result['body_html'] = trim(implode("\n", $html_parts));
        }

        $result['attachment_names'] = array_values(array_unique(array_filter($attachments)));

        return $result;
    }

    protected function splitMimeEntity($raw_entity)
    {
        $parts = preg_split("/\r\n\r\n|\n\n|\r\r/", $raw_entity, 2);
        $head  = isset($parts[0]) ? $parts[0] : '';
        $body  = isset($parts[1]) ? $parts[1] : '';

        return array($head, $body);
    }

    protected function parseMimeHeaders($raw_headers)
    {
        $headers      = array();
        $current_name = '';
        $lines        = preg_split("/\r\n|\n|\r/", (string) $raw_headers);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $current_name !== '') {
                $headers[$current_name] .= ' ' . trim($line);
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $current_name              = strtolower(trim($parts[0]));
            $headers[$current_name] = trim($parts[1]);
        }

        return $headers;
    }

    protected function collectMimeParts($headers, $body, &$text_parts, &$html_parts, &$attachments)
    {
        $content_type = isset($headers['content-type']) ? strtolower($headers['content-type']) : 'text/plain';

        if (strpos($content_type, 'multipart/') === 0) {
            $boundary = $this->extractMimeParameter(isset($headers['content-type']) ? $headers['content-type'] : '', 'boundary');
            if ($boundary === '') {
                return;
            }

            $segments = explode('--' . $boundary, (string) $body);
            foreach ($segments as $segment) {
                $segment = ltrim($segment, "\r\n");
                $segment = rtrim($segment, "\r\n");

                if ($segment === '' || $segment === '--') {
                    continue;
                }

                if (substr($segment, -2) === '--') {
                    $segment = substr($segment, 0, -2);
                }

                list($part_headers_raw, $part_body) = $this->splitMimeEntity($segment);
                $part_headers = $this->parseMimeHeaders($part_headers_raw);
                $this->collectMimeParts($part_headers, $part_body, $text_parts, $html_parts, $attachments);
            }

            return;
        }

        $disposition = isset($headers['content-disposition']) ? strtolower($headers['content-disposition']) : '';
        $filename    = $this->extractMimeParameter(isset($headers['content-disposition']) ? $headers['content-disposition'] : '', 'filename');

        if ($filename === '') {
            $filename = $this->extractMimeParameter(isset($headers['content-type']) ? $headers['content-type'] : '', 'name');
        }

        $decoded_body = $this->decodeMimeBody($body, isset($headers['content-transfer-encoding']) ? $headers['content-transfer-encoding'] : '');

        if ($filename !== '' || strpos($disposition, 'attachment') !== false) {
            $attachments[] = ($filename !== '') ? $filename : 'attachment';
            return;
        }

        if (strpos($content_type, 'text/html') === 0) {
            $html_parts[] = trim($decoded_body);
            return;
        }

        if (strpos($content_type, 'text/plain') === 0 || $content_type === '') {
            $text_parts[] = trim($decoded_body);
        }
    }

    protected function decodeMimeBody($body, $encoding)
    {
        $encoding = strtolower(trim((string) $encoding));

        if ($encoding === 'base64') {
            $decoded = base64_decode($body, true);
            return ($decoded === false) ? $body : $decoded;
        }

        if ($encoding === 'quoted-printable') {
            return quoted_printable_decode($body);
        }

        return $body;
    }

    protected function extractMimeParameter($header_value, $parameter_name)
    {
        if ($header_value === '') {
            return '';
        }

        $pattern = '/(?:^|;)\s*' . preg_quote($parameter_name, '/') . '\s*=\s*"([^"]+)"|(?:^|;)\s*' . preg_quote($parameter_name, '/') . '\s*=\s*([^;\s]+)/i';
        if (!preg_match($pattern, $header_value, $matches)) {
            return '';
        }

        if (!empty($matches[1])) {
            return trim($matches[1]);
        }

        return !empty($matches[2]) ? trim($matches[2]) : '';
    }

    protected function confirmSnsSubscription($subscribe_url)
    {
        $subscribe_url = trim((string) $subscribe_url);
        if ($subscribe_url === '' || stripos($subscribe_url, 'https://') !== 0) {
            return false;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($subscribe_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_exec($ch);
            $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code >= 200 && $http_code < 300;
        }

        $response = @file_get_contents($subscribe_url);
        return $response !== false;
    }

    protected function respond_json($payload, $status_code)
    {
        return $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload));
    }

}
