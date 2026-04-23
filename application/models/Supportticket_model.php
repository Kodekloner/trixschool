<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Supportticket_model extends CI_Model
{
    protected $ticket_table  = 'support_tickets';
    protected $message_table = 'support_messages';

    public function getTickets($filters = array())
    {
        $this->db->select($this->ticket_table . '.*, CONCAT_WS(" ", staff.name, staff.surname) as assigned_staff_name');
        $this->db->from($this->ticket_table);
        $this->db->join('staff', 'staff.id = ' . $this->ticket_table . '.assigned_staff_id', 'left');

        if (!empty($filters['status'])) {
            $this->db->where($this->ticket_table . '.status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $this->db->where($this->ticket_table . '.priority', $filters['priority']);
        }

        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $this->db->group_start();
            $this->db->like($this->ticket_table . '.ticket_number', $q);
            $this->db->or_like($this->ticket_table . '.subject', $q);
            $this->db->or_like($this->ticket_table . '.requester_name', $q);
            $this->db->or_like($this->ticket_table . '.requester_email', $q);
            $this->db->group_end();
        }

        $this->db->order_by($this->ticket_table . '.last_message_at', 'desc');
        $this->db->order_by($this->ticket_table . '.id', 'desc');

        return $this->db->get()->result_array();
    }

    public function getCounts()
    {
        $counts = array(
            'open'     => 0,
            'pending'  => 0,
            'resolved' => 0,
            'closed'   => 0,
        );

        $rows = $this->db->select('status, COUNT(*) as total')
            ->from($this->ticket_table)
            ->group_by('status')
            ->get()
            ->result_array();

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function get($id)
    {
        return $this->db->select($this->ticket_table . '.*, CONCAT_WS(" ", staff.name, staff.surname) as assigned_staff_name')
            ->from($this->ticket_table)
            ->join('staff', 'staff.id = ' . $this->ticket_table . '.assigned_staff_id', 'left')
            ->where($this->ticket_table . '.id', (int) $id)
            ->get()
            ->row_array();
    }

    public function getByTicketNumber($ticket_number)
    {
        return $this->db->where('ticket_number', strtoupper(trim((string) $ticket_number)))
            ->get($this->ticket_table)
            ->row_array();
    }

    public function getMessages($ticket_id)
    {
        return $this->db->where('support_ticket_id', (int) $ticket_id)
            ->order_by('created_at', 'asc')
            ->order_by('id', 'asc')
            ->get($this->message_table)
            ->result_array();
    }

    public function updateTicket($id, $data)
    {
        $allowed = array('status', 'priority', 'assigned_staff_id', 'closed_at');
        $payload = array();

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (empty($payload)) {
            return false;
        }

        $payload['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', (int) $id)->update($this->ticket_table, $payload);

        return $this->db->affected_rows() >= 0;
    }

    public function delete($id)
    {
        $id = (int) $id;
        $this->db->trans_start();
        $this->db->where('support_ticket_id', $id)->delete($this->message_table);
        $this->db->where('id', $id)->delete($this->ticket_table);
        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    public function addOutgoingReply($ticket_id, $data)
    {
        $ticket = $this->get($ticket_id);
        if (empty($ticket)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $payload = array(
            'support_ticket_id'     => (int) $ticket_id,
            'incoming_email_id'     => null,
            'direction'             => 'outgoing',
            'sender_type'           => 'staff',
            'sender_staff_id'       => !empty($data['sender_staff_id']) ? (int) $data['sender_staff_id'] : null,
            'sender_name'           => isset($data['sender_name']) ? $data['sender_name'] : null,
            'sender_email'          => isset($data['sender_email']) ? $data['sender_email'] : null,
            'recipients_json'       => $this->encodeJson(isset($data['recipients']) ? $data['recipients'] : array()),
            'subject'               => isset($data['subject']) ? $data['subject'] : $ticket['subject'],
            'body_text'             => isset($data['body_text']) ? $data['body_text'] : null,
            'body_html'             => isset($data['body_html']) ? $data['body_html'] : null,
            'message_id'            => isset($data['message_id']) ? $this->normalizeMessageId($data['message_id']) : null,
            'in_reply_to'           => isset($data['in_reply_to']) ? $this->normalizeMessageId($data['in_reply_to']) : null,
            'references_header'     => isset($data['references_header']) ? $data['references_header'] : null,
            'attachment_count'      => 0,
            'attachment_names_json' => null,
            'delivery_status'       => isset($data['delivery_status']) ? $data['delivery_status'] : 'sent',
            'error_message'         => isset($data['error_message']) ? $data['error_message'] : null,
            'created_at'            => $now,
            'updated_at'            => $now,
        );

        $this->db->insert($this->message_table, $payload);
        $message_id = (int) $this->db->insert_id();

        $ticket_update = array(
            'last_message_at'       => $now,
            'last_staff_message_at' => $now,
            'last_outgoing_message_id' => !empty($payload['message_id']) ? $payload['message_id'] : null,
            'updated_at'            => $now,
        );

        if ($payload['delivery_status'] === 'sent' && !in_array($ticket['status'], array('closed', 'resolved'), true)) {
            $ticket_update['status'] = 'pending';
        }

        $this->db->where('id', (int) $ticket_id)->update($this->ticket_table, $ticket_update);

        return $message_id;
    }

    public function processIncomingEmail($incoming_email_id)
    {
        $incoming_email_id = (int) $incoming_email_id;
        $incoming = $this->getIncomingEmail($incoming_email_id);

        if (empty($incoming)) {
            return false;
        }

        $existing_message = $this->db->where('incoming_email_id', $incoming_email_id)
            ->get($this->message_table)
            ->row_array();

        if (!empty($existing_message)) {
            $this->markIncomingEmail($incoming_email_id, 'ticketed', null);
            return (int) $existing_message['support_ticket_id'];
        }

        if (strtolower((string) $incoming['sns_type']) !== 'notification' ||
            strtolower((string) $incoming['ses_notification_type']) !== 'received') {
            return false;
        }

        $headers       = $incoming['headers'];
        $subject       = $this->normalizeSubject($incoming['subject']);
        $from_header   = $this->getHeader($headers, 'from');
        $source_header = !empty($from_header) ? $from_header : $incoming['source'];
        $requester_email = $this->normalizeEmail($this->extractEmailAddress($source_header));
        $requester_name  = $this->extractEmailName($source_header);

        if ($requester_email === '') {
            $this->markIncomingEmail($incoming_email_id, 'error', 'Could not determine sender email address.');
            return false;
        }

        if ($requester_name === '') {
            $requester_name = $requester_email;
        }

        $rfc_message_id = $this->getRfcMessageId($incoming, $headers);
        $ticket         = $this->findTicketForIncoming($subject, $headers);
        $now            = date('Y-m-d H:i:s');

        $this->db->trans_start();

        if (empty($ticket)) {
            $ticket = $this->createTicket(array(
                'requester_name'          => $requester_name,
                'requester_email'         => $requester_email,
                'subject'                 => $subject,
                'message_id'              => $rfc_message_id,
                'incoming_email_id'       => $incoming_email_id,
                'last_incoming_email_id'  => $incoming_email_id,
                'last_message_at'         => $this->resolveMessageDate($incoming, $now),
                'last_customer_message_at'=> $this->resolveMessageDate($incoming, $now),
            ));
        }

        $ticket_id = (int) $ticket['id'];

        $message_payload = array(
            'support_ticket_id'     => $ticket_id,
            'incoming_email_id'     => $incoming_email_id,
            'direction'             => 'incoming',
            'sender_type'           => 'requester',
            'sender_staff_id'       => null,
            'sender_name'           => $requester_name,
            'sender_email'          => $requester_email,
            'recipients_json'       => $this->encodeJson(!empty($incoming['recipients']) ? $incoming['recipients'] : $incoming['destinations']),
            'subject'               => $subject,
            'body_text'             => !empty($incoming['body_text']) ? $incoming['body_text'] : $this->htmlToText($incoming['body_html']),
            'body_html'             => !empty($incoming['body_html']) ? $incoming['body_html'] : null,
            'message_id'            => $rfc_message_id,
            'in_reply_to'           => $this->normalizeMessageId($this->getHeader($headers, 'in-reply-to')),
            'references_header'     => $this->getHeaderAsString($headers, 'references'),
            'attachment_count'      => (int) $incoming['attachment_count'],
            'attachment_names_json' => $this->encodeJson($incoming['attachment_names']),
            'delivery_status'       => 'received',
            'error_message'         => null,
            'created_at'            => $this->resolveMessageDate($incoming, $now),
            'updated_at'            => $now,
        );

        $this->db->insert($this->message_table, $message_payload);

        $ticket_status = isset($ticket['status']) ? $ticket['status'] : 'open';
        if (in_array($ticket_status, array('closed', 'resolved'), true)) {
            $ticket_status = 'open';
        }

        $ticket_update = array(
            'requester_name'            => $requester_name,
            'requester_email'           => $requester_email,
            'last_incoming_email_id'    => $incoming_email_id,
            'last_message_at'           => $message_payload['created_at'],
            'last_customer_message_at'  => $message_payload['created_at'],
            'status'                    => $ticket_status,
            'updated_at'                => $now,
        );

        if (empty($ticket['message_id']) && !empty($rfc_message_id)) {
            $ticket_update['message_id'] = $rfc_message_id;
        }

        $this->db->where('id', $ticket_id)->update($this->ticket_table, $ticket_update);
        $this->markIncomingEmail($incoming_email_id, 'ticketed', null);
        $this->db->trans_complete();

        return $this->db->trans_status() ? $ticket_id : false;
    }

    public function formatReplySubject($ticket)
    {
        $subject = isset($ticket['subject']) ? $ticket['subject'] : '';
        $subject = preg_replace('/\s*\[Ticket\s*#\s*[A-Z0-9-]+\]\s*/i', ' ', $subject);
        $subject = trim(preg_replace('/^(re|fw|fwd):\s*/i', '', $subject));

        if ($subject === '') {
            $subject = 'Support Request';
        }

        return 'Re: [Ticket #' . $ticket['ticket_number'] . '] ' . $subject;
    }

    public function buildReplyHeaders($ticket_id)
    {
        $messages = $this->getMessages($ticket_id);
        $message_ids = array();
        $last_incoming_id = '';

        foreach ($messages as $message) {
            if (!empty($message['message_id'])) {
                $message_ids[] = $this->normalizeMessageId($message['message_id']);
                if ($message['direction'] === 'incoming') {
                    $last_incoming_id = $this->normalizeMessageId($message['message_id']);
                }
            }
        }

        $message_ids = array_values(array_unique(array_filter($message_ids)));

        return array(
            'in_reply_to'       => $last_incoming_id !== '' ? $last_incoming_id : (!empty($message_ids) ? end($message_ids) : ''),
            'references_header' => !empty($message_ids) ? implode(' ', $message_ids) : '',
        );
    }

    public function buildOutgoingMessageId($ticket_number, $from_email = '')
    {
        $domain = 'schoollift.local';
        $from_email = trim((string) $from_email);

        if (strpos($from_email, '@') !== false) {
            $parts = explode('@', $from_email);
            $domain = preg_replace('/[^a-z0-9.-]/i', '', end($parts));
        }

        $random = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : substr(sha1(uniqid('', true)), 0, 8);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $ticket_number));

        return '<support-' . trim($slug, '-') . '-' . time() . '-' . $random . '@' . $domain . '>';
    }

    protected function createTicket($data)
    {
        $now = date('Y-m-d H:i:s');
        $temp_ticket_number = 'TMP-' . strtoupper(substr(sha1(uniqid('', true)), 0, 12));

        $payload = array(
            'ticket_number'             => $temp_ticket_number,
            'source'                    => 'email',
            'requester_name'            => isset($data['requester_name']) ? $data['requester_name'] : null,
            'requester_email'           => isset($data['requester_email']) ? $data['requester_email'] : null,
            'requester_phone'           => null,
            'subject'                   => isset($data['subject']) ? $data['subject'] : 'Support Request',
            'status'                    => 'open',
            'priority'                  => 'normal',
            'assigned_staff_id'         => null,
            'incoming_email_id'         => isset($data['incoming_email_id']) ? (int) $data['incoming_email_id'] : null,
            'last_incoming_email_id'    => isset($data['last_incoming_email_id']) ? (int) $data['last_incoming_email_id'] : null,
            'message_id'                => isset($data['message_id']) ? $data['message_id'] : null,
            'last_outgoing_message_id'  => null,
            'last_message_at'           => isset($data['last_message_at']) ? $data['last_message_at'] : $now,
            'last_customer_message_at'  => isset($data['last_customer_message_at']) ? $data['last_customer_message_at'] : $now,
            'last_staff_message_at'     => null,
            'opened_at'                 => $now,
            'closed_at'                 => null,
            'created_at'                => $now,
            'updated_at'                => $now,
        );

        $this->db->insert($this->ticket_table, $payload);
        $id = (int) $this->db->insert_id();
        $ticket_number = 'SUP-' . str_pad($id, 6, '0', STR_PAD_LEFT);

        $this->db->where('id', $id)->update($this->ticket_table, array(
            'ticket_number' => $ticket_number,
            'updated_at'    => $now,
        ));

        return $this->get($id);
    }

    protected function findTicketForIncoming($subject, $headers)
    {
        $ticket_number = $this->extractTicketNumber($subject);
        if ($ticket_number !== '') {
            $ticket = $this->getByTicketNumber($ticket_number);
            if (!empty($ticket)) {
                return $ticket;
            }
        }

        $reference_ids = array_merge(
            $this->extractMessageIds($this->getHeader($headers, 'in-reply-to')),
            $this->extractMessageIds($this->getHeader($headers, 'references'))
        );

        $reference_ids = array_values(array_unique(array_filter($reference_ids)));
        if (empty($reference_ids)) {
            return array();
        }

        $message = $this->db->where_in('message_id', $reference_ids)
            ->order_by('id', 'desc')
            ->get($this->message_table)
            ->row_array();

        if (!empty($message)) {
            return $this->get($message['support_ticket_id']);
        }

        $ticket = $this->db->where_in('message_id', $reference_ids)
            ->or_where_in('last_outgoing_message_id', $reference_ids)
            ->get($this->ticket_table)
            ->row_array();

        return !empty($ticket) ? $ticket : array();
    }

    protected function extractTicketNumber($subject)
    {
        $subject = (string) $subject;

        if (preg_match('/\[Ticket\s*#\s*([A-Z0-9-]+)\]/i', $subject, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/\b(SUP-\d{3,})\b/i', $subject, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    protected function getIncomingEmail($id)
    {
        $row = $this->db->where('id', (int) $id)->get('incoming_emails')->row_array();

        if (empty($row)) {
            return array();
        }

        $row['destinations']     = $this->decodeJson($row['destinations_json']);
        $row['recipients']       = $this->decodeJson($row['recipients_json']);
        $row['headers']          = $this->decodeJson($row['headers_json']);
        $row['attachment_names'] = $this->decodeJson($row['attachment_names_json']);

        return $row;
    }

    protected function markIncomingEmail($id, $status, $error_message = null)
    {
        $this->db->where('id', (int) $id)->update('incoming_emails', array(
            'status'        => $status,
            'error_message' => $error_message,
            'updated_at'    => date('Y-m-d H:i:s'),
        ));
    }

    protected function getRfcMessageId($incoming, $headers)
    {
        $message_id = $this->getHeader($headers, 'message-id');
        if (empty($message_id)) {
            $message_id = $this->getHeader($headers, 'messageId');
        }
        if (empty($message_id)) {
            $message_id = isset($incoming['ses_message_id']) ? $incoming['ses_message_id'] : '';
        }

        return $this->normalizeMessageId($message_id);
    }

    protected function resolveMessageDate($incoming, $fallback)
    {
        if (!empty($incoming['mail_timestamp'])) {
            return $incoming['mail_timestamp'];
        }

        if (!empty($incoming['receipt_timestamp'])) {
            return $incoming['receipt_timestamp'];
        }

        return $fallback;
    }

    protected function normalizeSubject($subject)
    {
        $subject = trim($this->decodeHeaderValue((string) $subject));
        return $subject !== '' ? $subject : 'Support Request';
    }

    protected function getHeader($headers, $name)
    {
        if (!is_array($headers)) {
            return '';
        }

        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return $value;
            }
        }

        return '';
    }

    protected function getHeaderAsString($headers, $name)
    {
        $value = $this->getHeader($headers, $name);
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }

    protected function extractEmailAddress($value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = $this->decodeHeaderValue((string) $value);
        if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $value, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function extractEmailName($value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = $this->decodeHeaderValue((string) $value);
        $value = trim($value);

        if (preg_match('/^(.*)<[^>]+>/', $value, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"'");
        }

        $email = $this->extractEmailAddress($value);
        if ($email !== '') {
            return trim(str_replace($email, '', $value), " \t\n\r\0\x0B<>\"'");
        }

        return '';
    }

    protected function normalizeEmail($email)
    {
        $email = strtolower(trim((string) $email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    protected function normalizeMessageId($value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/<[^>]+>/', $value, $matches)) {
            return strtolower($matches[0]);
        }

        return strtolower($value);
    }

    protected function extractMessageIds($value)
    {
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        $value = (string) $value;
        if ($value === '') {
            return array();
        }

        $ids = array();
        if (preg_match_all('/<[^>]+>/', $value, $matches)) {
            foreach ($matches[0] as $match) {
                $ids[] = $this->normalizeMessageId($match);
            }
        } else {
            foreach (preg_split('/\s+/', $value) as $part) {
                $ids[] = $this->normalizeMessageId($part);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function decodeHeaderValue($value)
    {
        $value = (string) $value;
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $value;
    }

    protected function htmlToText($html)
    {
        $html = (string) $html;
        if ($html === '') {
            return null;
        }

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    protected function encodeJson($value)
    {
        if (empty($value)) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($json === false) ? null : $json;
    }

    protected function decodeJson($value)
    {
        if (empty($value)) {
            return array();
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : array();
    }
}
