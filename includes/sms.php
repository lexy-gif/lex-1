<?php

function sms_env($key, $default = '')
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function normalize_phone_number($phone)
{
    if (!is_string($phone)) return '';
    $phone = preg_replace('/[\s\-\(\)]/', '', trim($phone));
    if (preg_match('/^0([17][0-9]{8})$/D',$phone,$match)) return '+254'.$match[1];
    if (preg_match('/^\+?254([17][0-9]{8})$/D',$phone,$match)) return '+254'.$match[1];
    return '';
}

function send_africastalking_sms($phone, $message)
{
    $enabled = strtolower(sms_env('AFRICASTALKING_SMS_ENABLED', 'false'));
    if (!in_array($enabled, array('1', 'true', 'yes'), true)) {
        return array(
            'success' => false,
            'skipped' => true,
            'message' => 'SMS sending is disabled. Set AFRICASTALKING_SMS_ENABLED=true to enable it.'
        );
    }

    $username = sms_env('AFRICASTALKING_USERNAME');
    $apiKey = sms_env('AFRICASTALKING_API_KEY');
    $senderId = sms_env('AFRICASTALKING_SENDER_ID');
    $environment = strtolower(sms_env('AFRICASTALKING_ENV', 'sandbox'));
    $recipient = normalize_phone_number($phone);

    if ($username === '' || $apiKey === '') {
        return array('success' => false, 'message' => 'Africa\'s Talking username or API key is missing.');
    }

    if ($recipient === '') {
        return array('success' => false, 'message' => 'Parent phone number is missing.');
    }

    $endpoint = $environment === 'live'
        ? 'https://api.africastalking.com/version1/messaging'
        : 'https://api.sandbox.africastalking.com/version1/messaging';

    $payload = array(
        'username' => $username,
        'to' => $recipient,
        'message' => $message
    );

    if ($senderId !== '') {
        $payload['from'] = $senderId;
    }

    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'apiKey: ' . $apiKey
            )),
            'content' => http_build_query($payload),
            'timeout' => 20,
            'ignore_errors' => true
        )
    );

    $response = @file_get_contents($endpoint, false, stream_context_create($options));
    if ($response === false) {
        return array('success' => false, 'uncertain' => true, 'message' => 'No confirmed response from Africa\'s Talking. Check provider logs before retrying.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return array('success' => false, 'uncertain' => true, 'message' => 'Unexpected SMS API response. Check provider logs before retrying.');
    }

    $recipientStatus = isset($decoded['SMSMessageData']['Recipients'][0])
        ? $decoded['SMSMessageData']['Recipients'][0]
        : array();

    $status = isset($recipientStatus['status']) ? strtolower($recipientStatus['status']) : '';
    $success = (int)($recipientStatus['statusCode']??0)===101 && in_array($status, array('success', 'sent', 'submitted'), true);

    return array(
        'success' => $success,
        'uncertain' => !$recipientStatus,
        'reference' => $recipientStatus['messageId']??null,
        'message' => isset($decoded['SMSMessageData']['Message']) ? $decoded['SMSMessageData']['Message'] : 'SMS request completed.',
        'response' => $decoded
    );
}
