<?php

namespace App\Service;

class TwilioSmsService
{
    private string $sid;
    private string $token;
    private string $from;
    private ?string $messagingServiceSid;

    public function __construct(string $sid, string $token, string $from, ?string $messagingServiceSid = null)
    {
        $this->sid = $sid;
        $this->token = $token;
        $this->from = $from;
        $this->messagingServiceSid = $messagingServiceSid;
    }

    public function sendSms(string $to, string $body): bool
    {
        $result = $this->sendSmsDebug($to, $body);
        return !empty($result['ok']);
    }

    /**
     * Send SMS and return debug details: ok, httpCode, response, error
     * Useful for diagnosing failures from Twilio.
     */
    public function sendSmsDebug(string $to, string $body): array
    {
        $sid = $this->sid;
        $token = $this->token;
        $from = $this->from;
        $messagingServiceSid = $this->messagingServiceSid;

        if (!$sid || !$token) {
            $msg = 'Twilio credentials missing';
            error_log($msg);
            return ['ok' => false, 'httpCode' => null, 'resp' => null, 'error' => $msg];
        }

        // Check that we have either From or MessagingServiceSid
        if (!$from && !$messagingServiceSid) {
            $msg = 'Either TWILIO_FROM or TWILIO_MESSAGING_SERVICE_SID must be configured';
            error_log($msg);
            return ['ok' => false, 'httpCode' => null, 'resp' => null, 'error' => $msg];
        }

        $url = sprintf('https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json', $sid);

        $data_array = [
            'To' => $to,
            'Body' => $body,
        ];

        // Use MessagingServiceSid if available, otherwise use From
        if ($messagingServiceSid) {
            $data_array['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $data_array['From'] = $from;
        }

        $data = http_build_query($data_array);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $sid . ':' . $token);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        // Ensure cURL uses a CA bundle if PHP config doesn't provide one at runtime.
        $ca = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
        if ($ca && file_exists($ca)) {
            curl_setopt($ch, CURLOPT_CAINFO, $ca);
        } else {
            $fallback = 'C:\\php8.2\\extras\\ssl\\cacert.pem';
            if (file_exists($fallback)) {
                curl_setopt($ch, CURLOPT_CAINFO, $fallback);
            }
        }

        $resp = curl_exec($ch);
        $curlErr = null;
        if ($resp === false) {
            $curlErr = curl_error($ch);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp !== false && $httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'httpCode' => $httpCode, 'resp' => $resp, 'error' => null];
        }

        $errMsg = $curlErr ?? ('HTTP ' . ($httpCode ?: 'N/A'));
        error_log('Twilio send failed: ' . $errMsg . ' resp: ' . ($resp ?? ''));
        return ['ok' => false, 'httpCode' => $httpCode, 'resp' => $resp, 'error' => $errMsg];
    }
}
