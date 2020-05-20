<?php

function sendMail($payload)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, getenv('MAILER_ENDPOINT') . 'api');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 30000);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 30000);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($status != 200)
    {
        echo "\nError while sending mail. Response: " . $result . ". Payload: " . json_encode($payload) . "\n";
    }
    return $result;
}
