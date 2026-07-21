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
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false || $curlError) {
        getLogStream()?->error("Remote call failed: $url", ['error' => $curlError], 'remote-calls');
        http_response_code(502);
        echo json_encode(['error' => "Failed to fetch $resourceName: " . $curlError]);
        exit();
    }

    if ($httpCode !== 200) {
        getLogStream()?->warning("Remote call returned HTTP $httpCode: $url", ['httpCode' => $httpCode], 'remote-calls');
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

    getLogStream()?->info("Remote call succeeded: $url", ['httpCode' => $httpCode], 'remote-calls');

    echo json_encode($decoded);
}
