<?php

if (!file_exists("../../webhook.secrets.php")) {
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}
require_once "../../webhook.secrets.php";
require_once "../../includes/constants.php";
require_once "../../includes/log-stream.php";

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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => getUserAgent(),
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

    if ($status === 'Failure') {
        getLogStream()?->warning("Remote call unhealthy: $url", ['httpCode' => $httpCode], 'remote-calls');
    } else {
        getLogStream()?->debug("Remote call succeeded: $url", ['httpCode' => $httpCode], 'remote-calls');
    }

    return [
        'status' => $status,
        'lastUpdated' =>  $httpDate ?? 'Unknown',
    ];
}

try {
    $resultApi = checkServiceHealth($gstracciniApiUrl."v1/health");
} catch (Exception $e) {
    error_log("Health check handler failed: " . $e->getMessage());
    getLogStream()?->error("Remote call failed: {$gstracciniApiUrl}v1/health", ['error' => $e->getMessage()], 'remote-calls');
    $resultApi = [
        'status' => 'Failure',
        'lastUpdated' => gmdate('Y-m-d h:i A T')
    ];
}

try {
    $resultService = checkServiceHealth($webhooksServiceUrl);
} catch (Exception $e) {
    error_log("Health check handler failed: " . $e->getMessage());
    getLogStream()?->error("Remote call failed: {$webhooksServiceUrl}", ['error' => $e->getMessage()], 'remote-calls');
    $resultService = [
        'status' => 'Failure',
        'lastUpdated' => gmdate('Y-m-d h:i A T')
    ];
}

try {
    $resultProcessing = checkServiceHealth($webhooksProcessingUrl);
} catch (Exception $e) {
    error_log("Health check processor failed: " . $e->getMessage());
    getLogStream()?->error("Remote call failed: {$webhooksProcessingUrl}", ['error' => $e->getMessage()], 'remote-calls');
    $resultProcessing = [
        'status' => 'Failure',
        'lastUpdated' => gmdate('Y-m-d h:i A T')
    ];
}

try {
    $resultDocs = checkServiceHealth("https://docs.bot.straccini.com/docs/intro");
} catch (Exception $e) {
    error_log("Health check processor failed: " . $e->getMessage());
    getLogStream()?->error("Remote call failed: https://docs.bot.straccini.com/docs/intro", ['error' => $e->getMessage()], 'remote-calls');
    $resultDocs = [
        'status' => 'Failure',
        'lastUpdated' => gmdate('Y-m-d h:i A T')
    ];
}

$date = new DateTime('now', new DateTimeZone('GMT'));
$services = [
    [
        'name' => 'API',
        'status' => $resultApi["status"],
        'lastUpdated' =>  $resultApi["lastUpdated"]
    ],
    [
        'name' => 'Dashboard',
        'status' => 'Operational',
        'lastUpdated' => $date->format('Y-m-d h:i A T')
    ],
    [
        'name' => 'Documentation',
        'status' => 'Operational',
        'lastUpdated' => $resultDocs["lastUpdated"]
    ],
    [
        'name' => 'GitHub Integration (Service)',
        'status' => $resultService["status"],
        'lastUpdated' => $resultService["lastUpdated"]
    ],
    [
        'name' => 'GitHub Workflows',
        'status' => 'Operational',
        'lastUpdated' => $date->format('Y-m-d h:i A T')
    ],
    [
        'name' => 'Webhook Processing',
        'status' => $resultProcessing["status"],
        'lastUpdated' => $resultProcessing["lastUpdated"]
    ]
];

header('Content-Type: application/json');
echo json_encode($services);
