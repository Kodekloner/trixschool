<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Incomingemail_model extends CI_Model
{
    protected $table = 'incoming_emails';

    public function getBySnsMessageId($sns_message_id)
    {
        return $this->db->where('sns_message_id', $sns_message_id)->get($this->table)->row_array();
    }

    public function saveFromWebhook(array $data)
    {
        $sns_message_id = isset($data['sns_message_id']) ? trim((string) $data['sns_message_id']) : '';
        $now            = date('Y-m-d H:i:s');

        $payload = array(
            'sns_type'              => isset($data['sns_type']) ? $data['sns_type'] : null,
            'sns_message_id'        => $sns_message_id !== '' ? $sns_message_id : null,
            'sns_topic_arn'         => isset($data['sns_topic_arn']) ? $data['sns_topic_arn'] : null,
            'ses_notification_type' => isset($data['ses_notification_type']) ? $data['ses_notification_type'] : null,
            'ses_message_id'        => isset($data['ses_message_id']) ? $data['ses_message_id'] : null,
            'source'                => isset($data['source']) ? $data['source'] : null,
            'subject'               => isset($data['subject']) ? $data['subject'] : null,
            'destinations_json'     => $this->encodeJson(isset($data['destinations']) ? $data['destinations'] : array()),
            'recipients_json'       => $this->encodeJson(isset($data['recipients']) ? $data['recipients'] : array()),
            'headers_json'          => $this->encodeJson(isset($data['headers']) ? $data['headers'] : array()),
            'mail_timestamp'        => isset($data['mail_timestamp']) ? $data['mail_timestamp'] : null,
            'receipt_timestamp'     => isset($data['receipt_timestamp']) ? $data['receipt_timestamp'] : null,
            'action_type'           => isset($data['action_type']) ? $data['action_type'] : null,
            's3_bucket'             => isset($data['s3_bucket']) ? $data['s3_bucket'] : null,
            's3_object_key'         => isset($data['s3_object_key']) ? $data['s3_object_key'] : null,
            'spam_status'           => isset($data['spam_status']) ? $data['spam_status'] : null,
            'virus_status'          => isset($data['virus_status']) ? $data['virus_status'] : null,
            'dkim_status'           => isset($data['dkim_status']) ? $data['dkim_status'] : null,
            'spf_status'            => isset($data['spf_status']) ? $data['spf_status'] : null,
            'dmarc_status'          => isset($data['dmarc_status']) ? $data['dmarc_status'] : null,
            'body_text'             => isset($data['body_text']) ? $data['body_text'] : null,
            'body_html'             => isset($data['body_html']) ? $data['body_html'] : null,
            'attachment_count'      => isset($data['attachment_count']) ? (int) $data['attachment_count'] : 0,
            'attachment_names_json' => $this->encodeJson(isset($data['attachment_names']) ? $data['attachment_names'] : array()),
            'raw_content'           => isset($data['raw_content']) ? $data['raw_content'] : null,
            'raw_payload'           => isset($data['raw_payload']) ? $data['raw_payload'] : null,
            'status'                => isset($data['status']) ? $data['status'] : 'received',
            'error_message'         => isset($data['error_message']) ? $data['error_message'] : null,
            'updated_at'            => $now,
        );

        if ($sns_message_id !== '') {
            $existing = $this->getBySnsMessageId($sns_message_id);
            if (!empty($existing)) {
                $this->db->where('id', $existing['id'])->update($this->table, $payload);
                return (int) $existing['id'];
            }
        }

        $payload['created_at'] = $now;
        $this->db->insert($this->table, $payload);
        return (int) $this->db->insert_id();
    }

    protected function encodeJson($value)
    {
        if (empty($value)) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($json === false) ? null : $json;
    }
}

