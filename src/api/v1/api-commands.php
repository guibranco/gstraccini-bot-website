<?php
<?php

if (!file_exists("../../webhook.secrets.php")) {
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}
require_once "../../webhook.secrets.php";

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

$url = $gstracciniApiUrl."v1/commands?format=json";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)',
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($response === false || $curlError) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch commands: ' . $curlError]);
    exit();
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => "Upstream returned HTTP $httpCode"]);
    exit();
}

$decoded = json_decode($response);
if ($decoded === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid JSON from upstream']);
    exit();
}

echo json_encode($decoded);
