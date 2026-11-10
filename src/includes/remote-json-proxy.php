<?php
/**
 * Shared helper for API endpoints that simply proxy a JSON response from the
 * upstream GStraccini API (fetch, validate, and echo).
 */

/**
 * Fetches $url, validates the response, and echoes the decoded JSON back to
 * the client. Terminates the request with an error JSON body and the
 * appropriate HTTP status code when the upstream call fails.
 *
 * @param string $url          The upstream URL to fetch.
 * @param string $resourceName Human-readable resource name used in the fetch-failure error message (e.g. "commands", "stats").
 * @param array  $extraHeaders Additional request headers, e.g. ['X-Api-Key: secret'].
 */
function proxyJsonFromUpstream(string $url, string $resourceName, array $extraHeaders = []): void
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => getUserAgent(),
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $extraHeaders),
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $durationMs = (int) round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError) {
        logError(
            "Upstream GET {url} for {resource} failed after {durationMs} ms: {error}",
            ['url' => $url, 'resource' => $resourceName, 'durationMs' => $durationMs, 'error' => $curlError],
            'remote-calls'
        );
        http_response_code(502);
        echo json_encode(['error' => "Failed to fetch $resourceName: " . $curlError]);
        exit();
    }

    if ($httpCode !== 200) {
        logWarning(
            "Upstream GET {url} for {resource} returned HTTP {httpCode} in {durationMs} ms",
            ['url' => $url, 'resource' => $resourceName, 'httpCode' => $httpCode, 'durationMs' => $durationMs],
            'remote-calls'
        );
        http_response_code(502);
        echo json_encode(['error' => "Upstream returned HTTP $httpCode"]);
        exit();
    }

    $decoded = json_decode($response);
    if ($decoded === null) {
        logError(
            "Upstream GET {url} for {resource} returned invalid JSON: {error}",
            [
                'url' => $url,
                'resource' => $resourceName,
                'error' => json_last_error_msg(),
                'responseLength' => strlen($response),
            ],
            'remote-calls'
        );
        http_response_code(502);
        echo json_encode(['error' => 'Invalid JSON from upstream']);
        exit();
    }

    logInfo(
        "Upstream GET {url} for {resource} returned HTTP {httpCode} in {durationMs} ms",
        ['url' => $url, 'resource' => $resourceName, 'httpCode' => $httpCode, 'durationMs' => $durationMs],
        'remote-calls'
    );

    echo json_encode($decoded);
}

/**
 * Sends a JSON request (POST/PUT/DELETE) to the upstream GStraccini API and
 * returns its raw HTTP status code and decoded body, without assuming any
 * particular status code means success. Unlike proxyJsonFromUpstream(),
 * this never terminates the request itself — callers relay the upstream
 * status verbatim (e.g. a 401 from a login attempt is a real, expected
 * response, not a proxy failure).
 *
 * @param string $url The upstream URL to call.
 * @param string $method HTTP method: POST, PUT, or DELETE.
 * @param array|null $body Request body, JSON-encoded; null for no body.
 * @param array $extraHeaders Additional request headers, e.g. ['X-Api-Key: secret'].
 * @return array{httpCode: int, decoded: mixed, error: string|null}
 */
function sendJsonToUpstream(string $url, string $method, ?array $body, array $extraHeaders = []): array
{
    $curl = curl_init();
    $headers = array_merge(['Accept: application/json', 'Content-Type: application/json'], $extraHeaders);

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => getUserAgent(),
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $durationMs = (int) round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);
    $curlError = curl_error($curl);
    curl_close($curl);

    // Request bodies are intentionally never logged: they may carry passwords, codes or API keys.
    if ($response === false || $curlError) {
        logError(
            "Upstream {method} {url} failed after {durationMs} ms: {error}",
            ['method' => $method, 'url' => $url, 'durationMs' => $durationMs, 'error' => $curlError],
            'remote-calls'
        );
        return ['httpCode' => 502, 'decoded' => ['error' => "Upstream call failed: {$curlError}"], 'error' => $curlError];
    }

    $decoded = json_decode($response, true);
    $properties = ['method' => $method, 'url' => $url, 'httpCode' => $httpCode, 'durationMs' => $durationMs];

    // 4xx is relayed as-is (e.g. a wrong password), so only 5xx is worth a warning here.
    logEvent(
        $httpCode >= 500 ? 'warning' : 'info',
        "Upstream {method} {url} returned HTTP {httpCode} in {durationMs} ms",
        $properties,
        'remote-calls'
    );

    return ['httpCode' => $httpCode, 'decoded' => $decoded, 'error' => null];
}
