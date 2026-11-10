<?php
/**
 * GitHub API Helper Functions
 *
 * Contains utility functions for interacting with the GitHub API.
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/log-stream.php';

/**
 * Load data from the GitHub API
 *
 * @param string $url   The GitHub API URL
 * @param string $token The GitHub API token
 * @return array|null Response array with headers and body, or null on error
 */
function loadData($url, $token)
{
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_DNS_CACHE_TIMEOUT => 30,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLINFO_HEADER_OUT => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            "User-Agent: " . getUserAgent(),
            "Accept: application/vnd.github+json",
            "X-GitHub-Api-Version: 2022-11-28",
        ],
    ]);

    $response = curl_exec($curl);

    $durationMs = (int) round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);

    if (curl_errno($curl)) {
        $curlError = curl_error($curl);
        logError(
            "GitHub API call to {url} failed after {durationMs} ms: {error}",
            ['url' => $url, 'durationMs' => $durationMs, 'error' => $curlError],
            'remote-calls'
        );
        curl_close($curl);
        return null;
    }

    $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

    if ($httpCode >= 400) {
        $errorBody = trim(substr($response, $headerSize));
        $decoded   = json_decode($errorBody, true);
        $reason    = $decoded['message'] ?? $errorBody;
        $rawHeader = substr($response, 0, $headerSize);
        logWarning(
            "GitHub API call to {url} returned HTTP {httpCode} in {durationMs} ms: {reason}",
            [
                'url'                => $url,
                'httpCode'           => $httpCode,
                'durationMs'         => $durationMs,
                'reason'             => $reason,
                'rateLimitRemaining' => getResponseHeaderValue($rawHeader, 'x-ratelimit-remaining'),
            ],
            'remote-calls'
        );
        curl_close($curl);
        return null;
    }
    $header     = substr($response, 0, $headerSize);
    $body       = json_decode(substr($response, $headerSize), true);

    curl_close($curl);

    if (json_last_error() !== JSON_ERROR_NONE) {
        logError(
            "GitHub API call to {url} returned invalid JSON: {error}",
            ['url' => $url, 'httpCode' => $httpCode, 'error' => json_last_error_msg()],
            'remote-calls'
        );
        return null;
    }

    logInfo(
        "GitHub API call to {url} returned HTTP {httpCode} in {durationMs} ms",
        [
            'url'                => $url,
            'httpCode'           => $httpCode,
            'durationMs'         => $durationMs,
            'rateLimitRemaining' => getResponseHeaderValue($header, 'x-ratelimit-remaining'),
        ],
        'remote-calls'
    );

    return ["headers" => $header, "body" => $body];
}

/**
 * Reads a single header value from a raw HTTP response header block.
 *
 * @param string $rawHeaders The raw response headers.
 * @param string $name       The header name (case-insensitive).
 * @return string|null The header value, or null when absent.
 */
function getResponseHeaderValue(string $rawHeaders, string $name): ?string
{
    foreach (explode("\r\n", $rawHeaders) as $line) {
        $separator = strpos($line, ':');
        if ($separator !== false && strcasecmp(trim(substr($line, 0, $separator)), $name) === 0) {
            return trim(substr($line, $separator + 1));
        }
    }

    return null;
}

/**
 * Extract the next page URL from the GitHub API link header
 *
 * @param string $linkHeader The GitHub API link header
 * @return string|null Next page URL or null if not found
 */
function getNextPageUrl($linkHeader)
{
    if (preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Fetch all pages from a GitHub API endpoint up to a maximum number of pages.
 *
 * The $maxPages cap is essential: without it, accounts with hundreds of open
 * issues/PRs will paginate until the GitHub rate limit (60 or 5000 req/hr) is
 * exhausted, causing all subsequent requests to fail with HTTP 403.
 *
 * @param string $url      The GitHub API URL (without &page= param)
 * @param string $token    The GitHub API token
 * @param int    $maxPages Maximum pages to fetch (default 2 = 200 items)
 * @return array|false Combined results from all pages, or false on failure
 */
function fetchAllGitHubPages($url, $token, $maxPages = 3)
{
    $results        = [];
    $pagesFetched   = 0;
    $anySuccess     = false;

    do {
        $result = loadData($url, $token);

        if ($result === null || !isset($result["body"]) || $result["body"] === null) {
            logWarning(
                "Stopped paginating {url} after {pagesFetched} page(s) because a page failed to load",
                ['url' => $url, 'pagesFetched' => $pagesFetched, 'itemsFetched' => count($results)],
                'remote-calls'
            );
            return $anySuccess ? $results : false;
        }

        $anySuccess = true;
        $results      = array_merge($results, $result["body"]);
        $pagesFetched++;

        $url = getNextPageUrl($result["headers"]);
    } while ($url && $pagesFetched < $maxPages);

    if ($url) {
        logNotice(
            "Pagination cap of {maxPages} page(s) reached; {itemsFetched} item(s) returned, more are available",
            ['maxPages' => $maxPages, 'itemsFetched' => count($results), 'nextUrl' => $url],
            'remote-calls'
        );
    }

    return $anySuccess ? $results : false;
}

/**
 * Format issue data into a standardized array
 *
 * @param array $issue Raw issue data from GitHub API
 * @return array Formatted issue data
 */
function formatIssueData($issue)
{
    return [
        'title'      => $issue['title'],
        'repository' => $issue['repository']['name'],
        'full_name'  => $issue['repository']['full_name'],
        'url'        => $issue['html_url'],
        'owner'      => $issue['repository']['owner']['login'],
        'labels'     => array_map(function ($label) {
            return [
                'color'       => $label['color']       ?? null,
                'description' => $label['description'] ?? null,
                'name'        => $label['name']        ?? null,
            ];
        }, $issue['labels']),
        'created_at' => $issue['created_at'],
    ];
}

/**
 * Enrich base issue data with pull request CI status and mergeability.
 *
 * @param array  $issueData   Base issue data
 * @param array  $pullRequest Pull request response from loadData()
 * @param string $token       GitHub API token
 * @return array Issue data enriched with PR-specific fields
 */
function enrichPullRequestData($issueData, $pullRequest, $token)
{
    $issueData["mergeable"]       = $pullRequest["body"]["mergeable"]       ?? null;
    $issueData["mergeable_state"] = $pullRequest["body"]["mergeable_state"] ?? null;

    if (!isset($pullRequest["body"]["head"]) || $pullRequest["body"]["head"] === null) {
        logWarning(
            "Pull request {url} has no head information; skipping CI status lookup",
            ['url' => $issueData['url'] ?? 'unknown'],
            'github'
        );
        return $issueData;
    }

    $repoUrl = $pullRequest["body"]["head"]["repo"]["url"];
    $sha     = $pullRequest["body"]["head"]["sha"] ?? $pullRequest["body"]["head"]["ref"];

    $state = loadData($repoUrl . "/commits/" . urlencode($sha) . "/status", $token);

    if ($state === null || !isset($state["body"]["state"])) {
        logWarning(
            "Could not load CI status for pull request {url}",
            ['url' => $issueData['url'] ?? 'unknown', 'sha' => $sha],
            'github'
        );
        return $issueData;
    }

    $issueData["state"] = $state["body"]["state"];

    $issueData["is_valid_pr"] = (
        $state["body"]["state"] === "success" &&
        ($issueData["mergeable"] ?? false) === true &&
        in_array($issueData["mergeable_state"] ?? '', ["clean", "unstable"], true)
    );

    return $issueData;
}

/**
 * Format repository data into a standardized array
 *
 * @param array $repo Raw repository data from GitHub API
 * @return array Formatted repository data
 */
function formatRepositoryData($repo)
{
    return [
        'name'         => $repo['name'],
        'organization' => $repo['owner']['login'],
        'url'          => $repo['html_url'],
        'fork'         => $repo['fork'],
        'stars'        => $repo['stargazers_count'],
        'forks'        => $repo['forks'],
        'issues'       => $repo['open_issues_count'],
        'language'     => $repo['language'],
        'visibility'   => $repo['visibility'],
    ];
}

/**
 * Send JSON response with appropriate headers
 *
 * @param array  $data     Data to encode as JSON
 * @param int    $time     Timestamp when data was created
 * @param string $hitMiss  "hit" or "miss" for the X-Cache header
 * @param int    $expires  Cache lifetime in seconds
 */
function sendJsonResponse($data, $time, $hitMiss = "miss", $expires = 60)
{
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=' . $expires);
    header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
    header('X-Cache: ' . $hitMiss);

    echo json_encode($data);
}

/**
 * Check authentication and return the stored token.
 * Exits with HTTP 401 if not authenticated.
 *
 * @return string GitHub API token
 */
function checkAuth()
{
    global $isAuthenticated;
    require_once "session.php";

    if ($isAuthenticated === false) {
        logNotice(
            "Rejected unauthenticated API request to {path}",
            ['path' => $_SERVER['REQUEST_URI'] ?? null],
            'auth'
        );
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }

    $token = $_SESSION['token'];
    session_write_close();
    return $token;
}

/**
 * Check if cached data is available and still valid.
 * Sends the cached response directly if valid.
 *
 * @param string $cacheKey Session key for cached data
 * @param int    $ttl      Time to live in seconds (default 300)
 * @return bool True if a valid cache was found and response sent, false otherwise
 */
function getCache($cacheKey = 'data', $ttl = 300)
{
    if (
        !isset($_GET['page']) &&        // only infinite-scroll should bypass cache
        isset($_SESSION[$cacheKey]['last_api_call']) &&
        $_SESSION[$cacheKey]['last_api_call'] > (time() - $ttl)
    ) {
        logDebug(
            "Cache hit for {cacheKey} (age {ageSeconds} s, TTL {ttl} s)",
            ['cacheKey' => $cacheKey, 'ageSeconds' => time() - $_SESSION[$cacheKey]['last_api_call'], 'ttl' => $ttl],
            'cache'
        );
        sendJsonResponse(
            $_SESSION[$cacheKey]["data"],
            $_SESSION[$cacheKey]['last_api_call'],
            "hit",
            $ttl
        );
        return true;
    }

    logDebug("Cache miss for {cacheKey}", ['cacheKey' => $cacheKey], 'cache');

    return false;
}

/**
 * Save data to the session cache.
 *
 * @param array  $data     Data to cache
 * @param string $cacheKey Session key for cached data
 */
function setCache($data, $cacheKey = 'data')
{
    $_SESSION[$cacheKey] = [
        "data"          => $data,
        "last_api_call" => time(),
    ];
}
