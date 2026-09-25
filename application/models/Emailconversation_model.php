<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Emailconversation_model extends CI_Model
{
    protected $conversationTable = 'email_conversations';
    protected $messageTable = 'email_conversation_messages';
    protected $lastIncomingMessageCreated = false;

    public function isReady()
    {
        return $this->db->table_exists($this->conversationTable)
            && $this->db->table_exists($this->messageTable)
            && $this->db->table_exists('incoming_emails');
    }

    public function getConversations($filters = array())
    {
        $this->db->select($this->conversationTable . '.*, CONCAT_WS(" ", staff.name, staff.surname) as created_by_name');
        $this->db->from($this->conversationTable);
        $this->db->join('staff', 'staff.id = ' . $this->conversationTable . '.created_by_staff_id', 'left');

        $folder = isset($filters['folder']) ? strtolower(trim((string) $filters['folder'])) : 'all';
        if ($folder === 'inbox') {
            $this->db->where($this->conversationTable . '.incoming_count >', 0);
        } elseif ($folder === 'sent') {
            $this->db->where($this->conversationTable . '.outgoing_count >', 0);
        } elseif ($folder === 'unread') {
            $this->db->where($this->conversationTable . '.unread_count >', 0);
        }

        if (!empty($filters['q'])) {
            $query = trim((string) $filters['q']);
            $this->db->group_start();
            $this->db->like($this->conversationTable . '.conversation_number', $query);
            $this->db->or_like($this->conversationTable . '.participant_name', $query);
            $this->db->or_like($this->conversationTable . '.participant_email', $query);
            $this->db->or_like($this->conversationTable . '.subject', $query);
            $this->db->group_end();
        }

        $this->db->order_by($this->conversationTable . '.last_message_at', 'desc');
        $this->db->order_by($this->conversationTable . '.id', 'desc');

        return $this->db->get()->result_array();
    }

    public function getCounts()
    {
        return array(
            'all' => $this->countConversations(),
            'inbox' => $this->countConversations('incoming_count >', 0),
            'sent' => $this->countConversations('outgoing_count >', 0),
            'unread' => $this->countConversations('unread_count >', 0),
        );
    }

    protected function countConversations($field = '', $value = null)
    {
        if ($field !== '') {
            $this->db->where($field, $value);
        }

        return (int) $this->db->count_all_results($this->conversationTable);
    }

    public function get($id)
    {
        return $this->db->select($this->conversationTable . '.*, CONCAT_WS(" ", staff.name, staff.surname) as created_by_name')
            ->from($this->conversationTable)
            ->join('staff', 'staff.id = ' . $this->conversationTable . '.created_by_staff_id', 'left')
            ->where($this->conversationTable . '.id', (int) $id)
            ->get()
            ->row_array();
    }

    public function getMessages($conversationId)
    {
        $rows = $this->db->where('email_conversation_id', (int) $conversationId)
            ->order_by('created_at', 'asc')
            ->order_by('id', 'asc')
            ->get($this->messageTable)
            ->result_array();

        foreach ($rows as $index => $row) {
            $rows[$index]['recipients'] = $this->decodeJson($row['recipients_json']);
            $rows[$index]['attachment_names'] = $this->decodeJson($row['attachment_names_json']);
        }

        return $rows;
    }

    public function markRead($conversationId)
    {
        $this->db->where('id', (int) $conversationId)->update($this->conversationTable, array(
            'unread_count' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ));

        return $this->db->affected_rows() >= 0;
    }

    public function createOutgoingConversation($data)
    {
        $participantEmail = $this->normalizeEmail(isset($data['participant_email']) ? $data['participant_email'] : '');
        if ($participantEmail === '') {
            return false;
        }

        $participantName = $this->normalizeName(isset($data['participant_name']) ? $data['participant_name'] : '');
        if ($participantName === '') {
            $participantName = $participantEmail;
        }

        $now = date('Y-m-d H:i:s');
        $temporaryNumber = 'TMP-' . strtoupper(substr(sha1(uniqid('', true)), 0, 12));
        $payload = array(
            'conversation_number' => $temporaryNumber,
            'participant_name' => $participantName,
            'participant_email' => $participantEmail,
            'subject' => $this->normalizeSubject(isset($data['subject']) ? $data['subject'] : ''),
            'inbound_address' => $this->normalizeEmail(isset($data['inbound_address']) ? $data['inbound_address'] : ''),
            'created_by_staff_id' => !empty($data['created_by_staff_id']) ? (int) $data['created_by_staff_id'] : null,
            'last_outgoing_message_id' => null,
            'last_incoming_message_id' => null,
            'last_message_direction' => 'outgoing',
            'incoming_count' => 0,
            'outgoing_count' => 0,
            'unread_count' => 0,
            'last_message_at' => $now,
            'last_incoming_at' => null,
            'last_outgoing_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $this->db->insert($this->conversationTable, $payload);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            return false;
        }

        $conversationNumber = 'MAIL-' . str_pad($id, 6, '0', STR_PAD_LEFT);
        $this->db->where('id', $id)->update($this->conversationTable, array(
            'conversation_number' => $conversationNumber,
            'updated_at' => $now,
        ));

        return $this->get($id);
    }

    public function addOutgoingMessage($conversationId, $data)
    {
        $conversation = $this->get($conversationId);
        if (empty($conversation)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $messageId = $this->normalizeMessageId(isset($data['message_id']) ? $data['message_id'] : '');
        $attachmentNames = isset($data['attachment_names']) && is_array($data['attachment_names'])
            ? array_values(array_filter(array_map('basename', $data['attachment_names'])))
            : array();
        $payload = array(
            'email_conversation_id' => (int) $conversationId,
            'incoming_email_id' => null,
            'direction' => 'outgoing',
            'sender_staff_id' => !empty($data['sender_staff_id']) ? (int) $data['sender_staff_id'] : null,
            'sender_name' => isset($data['sender_name']) ? $this->normalizeName($data['sender_name']) : null,
            'sender_email' => isset($data['sender_email']) ? $this->normalizeEmail($data['sender_email']) : null,
            'recipients_json' => $this->encodeJson(isset($data['recipients']) ? $data['recipients'] : array($conversation['participant_email'])),
            'subject' => isset($data['subject']) ? $this->normalizeSubject($data['subject']) : $conversation['subject'],
            'body_text' => isset($data['body_text']) ? $data['body_text'] : null,
            'body_html' => isset($data['body_html']) ? $data['body_html'] : null,
            'message_id' => $messageId !== '' ? $messageId : null,
            'in_reply_to' => isset($data['in_reply_to']) ? $this->normalizeMessageId($data['in_reply_to']) : null,
            'references_header' => isset($data['references_header']) ? $this->normalizeReferencesHeader($data['references_header']) : null,
            'attachment_count' => count($attachmentNames),
            'attachment_names_json' => $this->encodeJson($attachmentNames),
            'delivery_status' => isset($data['delivery_status']) ? $data['delivery_status'] : 'sent',
            'error_message' => isset($data['error_message']) ? $data['error_message'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        );

        $this->db->trans_start();
        $this->db->insert($this->messageTable, $payload);
        $insertId = (int) $this->db->insert_id();
        $this->db->set('outgoing_count', 'outgoing_count + 1', false)
            ->where('id', (int) $conversationId)
            ->update($this->conversationTable, array(
                'last_outgoing_message_id' => $messageId !== '' ? $messageId : null,
                'last_message_direction' => 'outgoing',
                'last_message_at' => $now,
                'last_outgoing_at' => $now,
                'updated_at' => $now,
            ));
        $this->db->trans_complete();

        return $this->db->trans_status() ? $insertId : false;
    }

    public function processIncomingEmail($incomingEmailId, $inboundAddress = '')
    {
        $this->lastIncomingMessageCreated = false;
        $incomingEmailId = (int) $incomingEmailId;
        $incoming = $this->getIncomingEmail($incomingEmailId);
        if (empty($incoming)) {
            return false;
        }

        $existing = $this->db->where('incoming_email_id', $incomingEmailId)
            ->get($this->messageTable)
            ->row_array();
        if (!empty($existing)) {
            $this->markIncomingEmail($incomingEmailId, 'correspondence', null);
            return (int) $existing['email_conversation_id'];
        }

        if (strtolower((string) $incoming['sns_type']) !== 'notification'
            || strtolower((string) $incoming['ses_notification_type']) !== 'received') {
            return false;
        }

        $headers = $incoming['headers'];
        $fromHeader = $this->getHeader($headers, 'from');
        $sourceHeader = $fromHeader !== '' ? $fromHeader : $incoming['source'];
        $participantEmail = $this->normalizeEmail($this->extractEmailAddress($sourceHeader));
        $participantName = $this->extractEmailName($sourceHeader);
        if ($participantEmail === '') {
            $this->markIncomingEmail($incomingEmailId, 'error', 'Could not determine sender email address.');
            return false;
        }
        if ($participantName === '') {
            $participantName = $participantEmail;
        }

        $subject = $this->normalizeSubject($incoming['subject']);
        $messageId = $this->getRfcMessageId($incoming, $headers);
        $conversation = $this->findConversationForIncoming($headers, $participantEmail);
        $messageDate = $this->resolveMessageDate($incoming, date('Y-m-d H:i:s'));
        $now = date('Y-m-d H:i:s');

        $this->db->trans_start();
        if (empty($conversation)) {
            $conversation = $this->createOutgoingConversation(array(
                'participant_name' => $participantName,
                'participant_email' => $participantEmail,
                'subject' => $this->baseSubject($subject),
                'inbound_address' => $inboundAddress,
                'created_by_staff_id' => null,
            ));
        }

        if (empty($conversation)) {
            $this->db->trans_complete();
            $this->markIncomingEmail($incomingEmailId, 'error', 'Could not create the shared email conversation.');
            return false;
        }

        $conversationId = (int) $conversation['id'];
        $messagePayload = array(
            'email_conversation_id' => $conversationId,
            'incoming_email_id' => $incomingEmailId,
            'direction' => 'incoming',
            'sender_staff_id' => null,
            'sender_name' => $participantName,
            'sender_email' => $participantEmail,
            'recipients_json' => $this->encodeJson(!empty($incoming['recipients']) ? $incoming['recipients'] : $incoming['destinations']),
            'subject' => $subject,
            'body_text' => !empty($incoming['body_text']) ? $incoming['body_text'] : $this->htmlToText($incoming['body_html']),
            'body_html' => !empty($incoming['body_html']) ? $incoming['body_html'] : null,
            'message_id' => $messageId !== '' ? $messageId : null,
            'in_reply_to' => $this->normalizeMessageId($this->getHeader($headers, 'in-reply-to')),
            'references_header' => $this->normalizeReferencesHeader($this->getHeaderAsString($headers, 'references')),
            'attachment_count' => (int) $incoming['attachment_count'],
            'attachment_names_json' => $this->encodeJson($incoming['attachment_names']),
            'delivery_status' => 'received',
            'error_message' => null,
            'created_at' => $messageDate,
            'updated_at' => $now,
        );

        $this->db->insert($this->messageTable, $messagePayload);
        $this->db->set('incoming_count', 'incoming_count + 1', false)
            ->set('unread_count', 'unread_count + 1', false)
            ->where('id', $conversationId)
            ->update($this->conversationTable, array(
                'last_incoming_message_id' => $messageId !== '' ? $messageId : null,
                'last_message_direction' => 'incoming',
                'last_message_at' => $messageDate,
                'last_incoming_at' => $messageDate,
                'updated_at' => $now,
            ));
        $this->markIncomingEmail($incomingEmailId, 'correspondence', null);
        $this->db->trans_complete();

        $success = $this->db->trans_status();
        $this->lastIncomingMessageCreated = $success;

        return $success ? $conversationId : false;
    }

    public function wasLastIncomingMessageCreated()
    {
        return $this->lastIncomingMessageCreated === true;
    }

    public function formatReplySubject($conversation)
    {
        return 'Re: ' . $this->baseSubject(isset($conversation['subject']) ? $conversation['subject'] : 'School correspondence');
    }

    public function buildReplyHeaders($conversationId)
    {
        $messages = $this->getMessages($conversationId);
        $messageIds = array();
        $lastIncomingId = '';

        foreach ($messages as $message) {
            if (empty($message['message_id'])) {
                continue;
            }
            $normalized = $this->normalizeMessageId($message['message_id']);
            if ($normalized === '') {
                continue;
            }
            $messageIds[] = $normalized;
            if ($message['direction'] === 'incoming') {
                $lastIncomingId = $normalized;
            }
        }

        $messageIds = array_slice(array_values(array_unique($messageIds)), -50);
        return array(
            'in_reply_to' => $lastIncomingId !== '' ? $lastIncomingId : (!empty($messageIds) ? end($messageIds) : ''),
            'references_header' => !empty($messageIds) ? implode(' ', $messageIds) : '',
        );
    }

    public function buildOutgoingMessageId($conversationNumber, $fromEmail = '')
    {
        $domain = 'schoollift.local';
        $fromEmail = $this->normalizeEmail($fromEmail);
        if ($fromEmail !== '') {
            $parts = explode('@', $fromEmail, 2);
            $candidate = preg_replace('/[^a-z0-9.-]/i', '', $parts[1]);
            if ($candidate !== '') {
                $domain = $candidate;
            }
        }

        $random = function_exists('random_bytes')
            ? bin2hex(random_bytes(6))
            : substr(sha1(uniqid('', true)), 0, 12);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $conversationNumber), '-'));

        return '<mail-' . $slug . '-' . time() . '-' . $random . '@' . $domain . '>';
    }

    protected function findConversationForIncoming($headers, $participantEmail)
    {
        $referenceIds = array_merge(
            $this->extractMessageIds($this->getHeader($headers, 'in-reply-to')),
            $this->extractMessageIds($this->getHeader($headers, 'references'))
        );
        $referenceIds = array_slice(array_values(array_unique(array_filter($referenceIds))), -50);
        if (empty($referenceIds)) {
            return array();
        }

        $message = $this->db->where_in('message_id', $referenceIds)
            ->order_by('id', 'desc')
            ->get($this->messageTable)
            ->row_array();
        if (!empty($message)) {
            $conversation = $this->get($message['email_conversation_id']);
            if ($this->participantMatches($conversation, $participantEmail)) {
                return $conversation;
            }
        }

        $this->db->group_start();
        $this->db->where_in('last_outgoing_message_id', $referenceIds);
        $this->db->or_where_in('last_incoming_message_id', $referenceIds);
        $this->db->group_end();
        $conversation = $this->db->get($this->conversationTable)->row_array();

        return $this->participantMatches($conversation, $participantEmail) ? $conversation : array();
    }

    protected function participantMatches($conversation, $participantEmail)
    {
        if (empty($conversation)) {
            return false;
        }

        $expected = $this->normalizeEmail(isset($conversation['participant_email']) ? $conversation['participant_email'] : '');
        $actual = $this->normalizeEmail($participantEmail);

        return $expected !== '' && $actual !== '' && hash_equals($expected, $actual);
    }

    protected function getIncomingEmail($id)
    {
        $row = $this->db->where('id', (int) $id)->get('incoming_emails')->row_array();
        if (empty($row)) {
            return array();
        }

        $row['destinations'] = $this->decodeJson($row['destinations_json']);
        $row['recipients'] = $this->decodeJson($row['recipients_json']);
        $row['headers'] = $this->decodeJson($row['headers_json']);
        $row['attachment_names'] = $this->decodeJson($row['attachment_names_json']);

        return $row;
    }

    protected function markIncomingEmail($id, $status, $errorMessage = null)
    {
        $this->db->where('id', (int) $id)->update('incoming_emails', array(
            'status' => $status,
            'error_message' => $errorMessage,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function getRfcMessageId($incoming, $headers)
    {
        $messageId = $this->getHeader($headers, 'message-id');
        if ($messageId === '') {
            $messageId = isset($incoming['ses_message_id']) ? $incoming['ses_message_id'] : '';
        }

        return $this->normalizeMessageId($messageId);
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

    protected function getHeader($headers, $name)
    {
        if (!is_array($headers)) {
            return '';
        }

        $name = strtolower((string) $name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? reset($value) : (string) $value;
            }
        }

        return '';
    }

    protected function getHeaderAsString($headers, $name)
    {
        $value = $this->getHeader($headers, $name);
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    protected function extractEmailAddress($value)
    {
        $value = $this->decodeHeaderValue((string) $value);
        if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $value, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function extractEmailName($value)
    {
        $value = trim($this->decodeHeaderValue((string) $value));
        if (preg_match('/^(.*)<[^>]+>/', $value, $matches)) {
            return $this->normalizeName($matches[1]);
        }

        $email = $this->extractEmailAddress($value);
        return $email !== '' ? $this->normalizeName(str_replace($email, '', $value)) : '';
    }

    protected function normalizeName($value)
    {
        $value = preg_replace('/[\r\n]+/', ' ', strip_tags((string) $value));
        $value = trim($value, " \t\n\r\0\x0B<>\"'");

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 191, 'UTF-8')
            : substr($value, 0, 191);
    }

    protected function normalizeEmail($email)
    {
        $email = strtolower(trim((string) $email));
        return strlen($email) <= 191 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    protected function normalizeSubject($subject)
    {
        $subject = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $this->decodeHeaderValue((string) $subject)));
        if ($subject === '') {
            return 'School correspondence';
        }

        return function_exists('mb_substr')
            ? mb_substr($subject, 0, 255, 'UTF-8')
            : substr($subject, 0, 255);
    }

    protected function baseSubject($subject)
    {
        $subject = $this->normalizeSubject($subject);
        do {
            $previous = $subject;
            $subject = trim(preg_replace('/^(re|fw|fwd):\s*/i', '', $subject));
        } while ($subject !== $previous);

        return $subject !== '' ? $subject : 'School correspondence';
    }

    protected function normalizeMessageId($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/<[^<>\s]+>/', $value, $matches)) {
            $value = $matches[0];
        } elseif (!preg_match('/^[^<>\s]+$/', $value)) {
            return '';
        }

        $value = strtolower($value);
        return strlen($value) <= 255 ? $value : '';
    }

    protected function extractMessageIds($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return array();
        }

        $ids = array();
        if (preg_match_all('/<[^<>\s]+>/', $value, $matches)) {
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

    protected function normalizeReferencesHeader($value)
    {
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, 65535);
    }

    protected function decodeHeaderValue($value)
    {
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode((string) $value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) {
                return $decoded;
            }
        }

        return (string) $value;
    }

    protected function htmlToText($html)
    {
        $html = (string) $html;
        return $html !== '' ? trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) : null;
    }

    protected function encodeJson($value)
    {
        if (empty($value)) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? null : $json;
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
