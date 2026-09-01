<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Supportemailnotifier
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /** Queue one idempotent alert per opted-in, currently authorized staff member. */
    public function queueIncoming($ticketId, $incomingEmailId, $schoolDomain, $inboundAddress)
    {
        $result = array('eligible' => 0, 'queued' => 0, 'duplicate' => 0, 'failed' => 0);
        $ticketId = (int) $ticketId;
        $incomingEmailId = (int) $incomingEmailId;
        if ($ticketId <= 0 || $incomingEmailId <= 0) {
            return $result;
        }

        $this->CI->load->helper('support_email');
        $this->CI->load->model('supportnotification_model');
        if (!$this->CI->supportnotification_model->queueIsReady()) {
            return $result;
        }

        if (!isset($this->CI->supportticket_model)) {
            $this->CI->load->model('supportticket_model');
        }
        if (empty($this->CI->supportticket_model->get($ticketId))) {
            return $result;
        }

        $schoolDomain = strtolower(trim((string) $schoolDomain));
        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $schoolDomain)) {
            log_message('error', 'Support email alert skipped: invalid school domain.');
            return $result;
        }

        $seen = array();
        foreach ($this->CI->supportnotification_model->getAuthorizedRecipients() as $recipient) {
            $email = schoollift_support_normalize_email($recipient['email']);
            if (!schoollift_support_notification_recipient_allowed($email, $inboundAddress)
                || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;
            $result['eligible']++;

            $deliveryId = $this->CI->supportnotification_model->reserveDelivery(array(
                'notification_id'   => (int) $recipient['id'],
                'incoming_email_id' => $incomingEmailId,
                'support_ticket_id' => $ticketId,
                'school_domain'     => $schoolDomain,
                'inbound_address'   => $inboundAddress,
                'recipient_email'   => $email,
            ));
            if ($deliveryId > 0) {
                $result['queued']++;
            } elseif ($deliveryId < 0) {
                $result['failed']++;
            } else {
                $result['duplicate']++;
            }
        }

        return $result;
    }

    /** Drain a bounded batch. The protected tenant cron calls this method. */
    public function processQueue($limit = 20)
    {
        $result = array('selected' => 0, 'sent' => 0, 'retried' => 0, 'failed' => 0, 'cancelled' => 0);
        $this->CI->load->helper('support_email');
        $this->CI->load->model('supportnotification_model');
        if (!$this->CI->supportnotification_model->queueIsReady()) {
            return $result;
        }
        if (!isset($this->CI->supportticket_model)) {
            $this->CI->load->model('supportticket_model');
        }

        $deliveries = $this->CI->supportnotification_model->getPendingDeliveries($limit);
        $result['selected'] = count($deliveries);
        if (empty($deliveries)) {
            return $result;
        }

        $authorized = array();
        foreach ($this->CI->supportnotification_model->getAuthorizedRecipients() as $recipient) {
            $authorized[(int) $recipient['id']] = schoollift_support_normalize_email($recipient['email']);
        }

        $this->CI->load->model('setting_model');
        $settings = $this->CI->setting_model->get();
        $schoolName = !empty($settings[0]['name']) ? $settings[0]['name'] : '';
        $this->CI->load->library('mailer');

        foreach ($deliveries as $delivery) {
            $deliveryId = (int) $delivery['id'];
            if (!$this->CI->supportnotification_model->claimDelivery($deliveryId)) {
                continue;
            }

            $email = schoollift_support_normalize_email($delivery['recipient_email']);
            $notificationId = (int) $delivery['notification_id'];
            $domain = strtolower(trim((string) $delivery['school_domain']));
            $inbound = schoollift_support_normalize_email($delivery['inbound_address']);
            if (!isset($authorized[$notificationId])
                || $authorized[$notificationId] !== $email
                || !schoollift_support_notification_recipient_allowed($email, $inbound)
                || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain)) {
                $this->CI->supportnotification_model->cancelQueuedDelivery(
                    $deliveryId,
                    'Recipient is no longer active, authorized, or valid.'
                );
                $result['cancelled']++;
                continue;
            }

            $ticket = $this->CI->supportticket_model->get((int) $delivery['support_ticket_id']);
            if (empty($ticket)) {
                $this->CI->supportnotification_model->cancelQueuedDelivery($deliveryId, 'Support ticket no longer exists.');
                $result['cancelled']++;
                continue;
            }

            $ticketUrl = 'https://' . $domain . '/admin/support/view/' . (int) $ticket['id'];
            $body = schoollift_support_notification_html($ticket, $ticketUrl, $schoolName);
            $subject = schoollift_support_normalize_subject(
                'New school email received - ' . $ticket['ticket_number'],
                250
            );
            $sent = $this->CI->mailer->send_mail($email, $subject, $body, array(), '', array(
                'is_html' => true,
                'smtp_timeout' => 10,
                'custom_headers' => array(
                    'Auto-Submitted' => 'auto-generated',
                    'X-Auto-Response-Suppress' => 'All',
                    'X-SchoolLift-Notification' => 'support-email',
                ),
            ));
            $error = $sent ? '' : $this->CI->mailer->get_last_error();
            $attemptCount = (int) $delivery['attempt_count'] + 1;
            $this->CI->supportnotification_model->completeQueuedDelivery(
                $deliveryId,
                $attemptCount,
                $sent,
                $this->CI->mailer->get_last_message_id(),
                $error
            );
            $this->CI->supportnotification_model->recordDelivery($notificationId, $sent, $error);

            if ($sent) {
                $result['sent']++;
            } elseif ($attemptCount < 3) {
                $result['retried']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }
}
