<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal Amazon SNS signature validator for the central SES webhook.
 *
 * The repository's legacy AWS SDK does not include the standalone SNS
 * validator package, so this class implements the verification procedure
 * published by AWS without introducing a deployment-time dependency.
 */
class Snsmessagevalidator
{
    private $certificateFetcher;
    private $lastError = '';
    private $retryableFailure = false;

    public function __construct($params = array())
    {
        $params = is_array($params) ? $params : array();
        $this->certificateFetcher = isset($params['certificate_fetcher'])
            && is_callable($params['certificate_fetcher'])
            ? $params['certificate_fetcher']
            : array($this, 'downloadCertificate');
    }

    public function isValid(array $message)
    {
        $this->lastError = '';
        $this->retryableFailure = false;

        if (!extension_loaded('openssl') || !function_exists('openssl_verify')) {
            return $this->fail('The OpenSSL extension is required to verify SNS messages.', true);
        }

        $required = array(
            'Message',
            'MessageId',
            'Timestamp',
            'TopicArn',
            'Type',
            'Signature',
            'SignatureVersion',
            'SigningCertURL',
        );
        foreach ($required as $field) {
            if (!isset($message[$field]) || !is_scalar($message[$field]) || (string) $message[$field] === '') {
                return $this->fail('SNS message field is missing or invalid: ' . $field . '.');
            }
        }

        $type = (string) $message['Type'];
        if (!in_array($type, array('Notification', 'SubscriptionConfirmation', 'UnsubscribeConfirmation'), true)) {
            return $this->fail('Unsupported SNS message type.');
        }

        if ($type !== 'Notification') {
            foreach (array('SubscribeURL', 'Token') as $field) {
                if (!isset($message[$field]) || !is_scalar($message[$field]) || (string) $message[$field] === '') {
                    return $this->fail('SNS confirmation field is missing or invalid: ' . $field . '.');
                }
            }
        }

        $version = (string) $message['SignatureVersion'];
        if ($version !== '1' && $version !== '2') {
            return $this->fail('Unsupported SNS signature version.');
        }

        $certificateUrl = (string) $message['SigningCertURL'];
        if (!$this->isTrustedSnsUrl($certificateUrl, true)) {
            return $this->fail('SNS signing certificate URL is not trusted.');
        }

        $signature = base64_decode((string) $message['Signature'], true);
        if ($signature === false || $signature === '') {
            return $this->fail('SNS signature is not valid Base64.');
        }

        $certificate = call_user_func($this->certificateFetcher, $certificateUrl);
        if (!is_string($certificate) || $certificate === '' || strlen($certificate) > 65536) {
            return $this->fail('SNS signing certificate could not be downloaded.', true);
        }

        $publicKey = @openssl_pkey_get_public($certificate);
        if ($publicKey === false) {
            return $this->fail('SNS signing certificate does not contain a public key.', true);
        }

        $algorithm = $version === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256;
        $verified = @openssl_verify($this->getStringToSign($message), $signature, $publicKey, $algorithm);
        if (is_resource($publicKey) && function_exists('openssl_free_key')) {
            openssl_free_key($publicKey);
        }

        if ($verified === -1) {
            return $this->fail('SNS signature verification could not be completed.', true);
        }

        if ($verified !== 1) {
            return $this->fail('SNS message signature is invalid.');
        }

        return true;
    }

    public function getStringToSign(array $message)
    {
        $content = '';
        $fields = isset($message['Type']) && (string) $message['Type'] === 'Notification'
            ? array('Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type')
            : array('Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type');

        foreach ($fields as $field) {
            if (isset($message[$field]) && is_scalar($message[$field])) {
                $content .= $field . "\n" . (string) $message[$field] . "\n";
            }
        }

        return $content;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    /** Operational verifier failures should make Amazon SNS retry delivery. */
    public function isRetryableFailure()
    {
        return $this->retryableFailure === true;
    }

    /**
     * Accept only regional Amazon SNS HTTPS endpoints. Certificate URLs must
     * end in .pem; confirmation URLs are checked separately by the webhook.
     */
    public function isTrustedSnsUrl($url, $certificate = false)
    {
        $url = trim((string) $url);
        $parts = parse_url($url);
        if (!is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
            || strtolower($parts['scheme']) !== 'https'
            || !preg_match('/^sns\.[a-z0-9-]{3,}\.amazonaws\.com(?:\.cn)?$/i', $parts['host'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])) {
            return false;
        }

        if ($certificate) {
            return !isset($parts['query'])
                && !empty($parts['path'])
                && substr($parts['path'], -4) === '.pem';
        }

        return true;
    }

    protected function downloadCertificate($url)
    {
        if (!$this->isTrustedSnsUrl($url, true)) {
            return false;
        }

        if (function_exists('curl_init')) {
            $body = '';
            $tooLarge = false;
            $handle = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body, &$tooLarge) {
                    if (strlen($body) + strlen($chunk) > 65536) {
                        $tooLarge = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            );
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($handle, $options);
            $executed = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);

            return $executed !== false && !$tooLarge && $status === 200 ? $body : false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 8,
                'follow_location' => 0,
                'max_redirects' => 0,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $body = @file_get_contents($url, false, $context, 0, 65537);

        return is_string($body) && $body !== '' && strlen($body) <= 65536 ? $body : false;
    }

    private function fail($message, $retryable = false)
    {
        $this->lastError = (string) $message;
        $this->retryableFailure = (bool) $retryable;
        return false;
    }
}
