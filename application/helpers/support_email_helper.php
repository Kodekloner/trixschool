<?php

defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('schoollift_support_normalize_email')) {
    /**
     * Return one normalized mailbox address or an empty string.
     */
    function schoollift_support_normalize_email($email)
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('schoollift_support_normalize_subject')) {
    /**
     * Keep mail subjects on one line and leave room for the ticket marker.
     */
    function schoollift_support_normalize_subject($subject, $maximumLength = 220)
    {
        $subject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $subject);
        $subject = trim(preg_replace('/\s+/u', ' ', $subject));
        $maximumLength = max(1, (int) $maximumLength);

        if (function_exists('mb_substr')) {
            return mb_substr($subject, 0, $maximumLength, 'UTF-8');
        }

        return substr($subject, 0, $maximumLength);
    }
}

if (!function_exists('schoollift_support_thread_subject')) {
    /**
     * Add the marker used by the SES inbound processor to reconnect replies.
     */
    function schoollift_support_thread_subject($subject, $ticketNumber)
    {
        $subject = preg_replace('/\s*\[Ticket\s*#\s*[A-Z0-9-]+\]\s*/i', ' ', (string) $subject);
        $subject = schoollift_support_normalize_subject($subject);
        if ($subject === '') {
            $subject = 'School message';
        }

        $ticketNumber = strtoupper(preg_replace('/[^A-Z0-9-]/i', '', (string) $ticketNumber));
        return '[Ticket #' . $ticketNumber . '] ' . $subject;
    }
}

if (!function_exists('schoollift_support_inbound_address')) {
    /**
     * Build the tenant's public SES address without trusting a path or port.
     */
    function schoollift_support_inbound_address($localPart, $host)
    {
        $localPart = strtolower(trim((string) $localPart));
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = preg_replace('/^www\./i', '', $host);

        if (!preg_match('/^[a-z0-9._%+\-]+$/', $localPart)
            || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
            return '';
        }

        return schoollift_support_normalize_email($localPart . '@' . $host);
    }
}

if (!function_exists('schoollift_support_configured_inbound_address')) {
    /** Only trust a request host that is also a configured tenant DB group. */
    function schoollift_support_configured_inbound_address($localPart, $host)
    {
        $address = schoollift_support_inbound_address($localPart, $host);
        if ($address === '' || !defined('APPPATH')) {
            return '';
        }

        $domain = substr(strrchr($address, '@'), 1);
        $active_group = 'default';
        $query_builder = true;
        $db = array();
        $database_configs = array();
        include APPPATH . 'config/database.php';

        $groups = array();
        foreach (array($database_configs, $db) as $collection) {
            if (!is_array($collection)) {
                continue;
            }
            foreach ($collection as $name => $settings) {
                if (is_array($settings) && !empty($settings['dbdriver'])) {
                    $groups[strtolower(trim((string) $name))] = true;
                }
            }
        }

        return isset($groups[$domain]) ? $address : '';
    }
}

if (!function_exists('schoollift_support_html_to_text')) {
    function schoollift_support_html_to_text($html)
    {
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", (string) $html);
        $html = preg_replace('/<\/(p|div|li|h[1-6])\s*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\r\n?|\n/u", "\n", $text);

        return trim(preg_replace('/[ \t]+/u', ' ', $text));
    }
}

if (!function_exists('schoollift_support_append_ticket_note')) {
    function schoollift_support_append_ticket_note($html, $ticketNumber, $inboundAddress)
    {
        $ticketNumber = htmlspecialchars((string) $ticketNumber, ENT_QUOTES, 'UTF-8');
        $inboundAddress = htmlspecialchars((string) $inboundAddress, ENT_QUOTES, 'UTF-8');

        return trim((string) $html)
            . '<hr style="border:0;border-top:1px solid #dddddd;margin:20px 0 10px;">'
            . '<p style="color:#666666;font-size:12px;margin:0;">Ticket: '
            . $ticketNumber
            . ($inboundAddress !== '' ? ' &middot; Reply to ' . $inboundAddress : '')
            . '</p>';
    }
}

if (!function_exists('schoollift_support_notification_recipient_allowed')) {
    /**
     * Never forward an alert back into the tenant's SES inbox.
     */
    function schoollift_support_notification_recipient_allowed($email, $inboundAddress)
    {
        $email = schoollift_support_normalize_email($email);
        $inboundAddress = schoollift_support_normalize_email($inboundAddress);

        return $email !== '' && ($inboundAddress === '' || !hash_equals($inboundAddress, $email));
    }
}

if (!function_exists('schoollift_support_notification_html')) {
    /**
     * Deliberately omit the message body so lock-screen alerts disclose less.
     */
    function schoollift_support_notification_html(array $ticket, $ticketUrl, $schoolName = '')
    {
        $escape = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };

        $ticketNumber = isset($ticket['ticket_number']) ? $ticket['ticket_number'] : '';
        $senderName = !empty($ticket['requester_name']) ? $ticket['requester_name'] : $ticket['requester_email'];
        $subject = isset($ticket['subject']) ? $ticket['subject'] : 'New email';

        return '<div style="font-family:Arial,sans-serif;color:#222222;line-height:1.5;">'
            . '<h2 style="margin:0 0 14px;">New school email received</h2>'
            . ($schoolName !== '' ? '<p style="margin:0 0 12px;"><strong>' . $escape($schoolName) . '</strong></p>' : '')
            . '<p style="margin:0 0 6px;"><strong>Ticket:</strong> ' . $escape($ticketNumber) . '</p>'
            . '<p style="margin:0 0 6px;"><strong>From:</strong> ' . $escape($senderName) . '</p>'
            . '<p style="margin:0 0 16px;"><strong>Subject:</strong> ' . $escape($subject) . '</p>'
            . '<p style="margin:0 0 18px;"><a href="' . $escape($ticketUrl) . '" style="background:#337ab7;color:#ffffff;padding:10px 16px;text-decoration:none;border-radius:3px;">Open secure inbox</a></p>'
            . '<p style="color:#777777;font-size:12px;margin:0;">This is an automatic alert. Sign in to SchoolLift to read and reply to the message.</p>'
            . '</div>';
    }
}
