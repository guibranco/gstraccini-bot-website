<?php

require_once "../../includes/constants.php";
require_once "../../includes/log-stream.php";

if (!file_exists("../../webhook.secrets.php")) {
    logCritical("Configuration file {file} not found", ['file' => 'webhook.secrets.php', 'endpoint' => basename(__FILE__)], 'configuration');
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}
require_once "../../webhook.secrets.php";

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
        $error = curl_error($curl);
        curl_close($curl);
        throw new Exception('cURL error: ' . $error);
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

    $properties = ['url' => $url, 'httpCode' => $httpCode, 'status' => $status];
    if ($status === 'Failure') {
        logWarning("Health check of {url} reported {status} (HTTP {httpCode})", $properties, 'health-checks');
    } else {
        logDebug("Health check of {url} reported {status} (HTTP {httpCode})", $properties, 'health-checks');
    }

    return [
        'status' => $status,
        'lastUpdated' =>  $httpDate ?? 'Unknown',
    ];
}

/**
 * Runs a health check, logging and reporting a failure when the request itself fails.
 *
 * @param string $serviceName Human-readable service name, used in the logs.
 * @param string $url         The health-check URL.
 * @return array The checkServiceHealth() result, or a failure result.
 */
function checkServiceHealthSafely(string $serviceName, string $url): array
{
    try {
        return checkServiceHealth($url);
    } catch (Exception $e) {
        logError(
            "Health check of {service} at {url} failed: {error}",
            ['service' => $serviceName, 'url' => $url, 'error' => $e->getMessage()],
            'health-checks'
        );

        return [
            'status' => 'Failure',
            'lastUpdated' => gmdate('Y-m-d h:i A T')
        ];
    }
}

$resultApi = checkServiceHealthSafely('API', $gstracciniApiUrl . "v1/health");
$resultService = checkServiceHealthSafely('GitHub Integration (Service)', $webhooksServiceUrl);
$resultProcessing = checkServiceHealthSafely('Webhook Processing', $webhooksProcessingUrl);
$resultDocs = checkServiceHealthSafely('Documentation', "https://docs.bot.straccini.com/docs/intro");

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
