<?php

require_once("secrets/webhook.secrets.php");

<?php

/**
 * Performs an HTTP GET request and checks the response.
 *
 * @param string $url The URL to send the GET request to.
 * @return array An associative array with 'status' (operational/failure) and 'http_date' (HTTP Date header value).
 * @throws Exception If the cURL request fails.
 */
function checkServiceHealth(string $url): array
{
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'GStraccini-Bot-Website/1.0',
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        throw new Exception('cURL error: ' . curl_error($curl));
    }

    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    $httpDate = null;
    foreach (explode("\r\n", $headers) as $headerLine) {
        if (stripos($headerLine, 'Date:') === 0) {
            $rawDate = trim(substr($headerLine, strlen('Date:')));
            $timestamp = strtotime($rawDate);
            if ($timestamp !== false) {
                $httpDate = gmdate('Y-m-d h:i A T', $timestamp);
            }
            
            break;
        }
    }

    curl_close($curl);

    $status = ($httpCode === 200 && strtolower(trim($body)) === 'healthy') ? 'Operational' : 'Failure';

    return [
        'status' => $status,
        'http_date' =>  $httpDate ?? 'Unknown',
    ];
}

$resultHandler = checkServiceHealth($webhooksApiUrl);
$resultProcessor = checkServiceHealth($webhookUrl . "/health");

$date = new DateTime('now', new DateTimeZone('UTC'));
$services = [
    [
        'name' => 'API',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'Dashboard',
        'status' => 'Operational',
        'lastUpdated' => $date->format('Y-m-d h:i A T')
    ],
    [
        'name' => 'Documentation',
        'status' => 'Maintenance',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'GitHub Integration (Service)',
        'status' => $resultHandler["status"],
        'lastUpdated' => $resultHandler["http_date"]
    ],
    [
        'name' => 'GitHub Workflows',
        'status' => 'Operational',
        'lastUpdated' => '2025-01-22 12:33 AM UTC'
    ],
    [
        'name' => 'Webhook Processing',
        'status' => $resultProcessor["status"],
        'lastUpdated' => $resultProcessor["http_date"]
    ]
];

header('Content-Type: application/json');
echo json_encode($services);
