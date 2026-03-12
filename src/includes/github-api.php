<?php
/**
 * GitHub API Helper Functions
 *
 * Contains utility functions for interacting with the GitHub API.
 */

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

    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "User-Agent: GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28",
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        error_log("cURL error for $url: " . curl_error($curl));
        curl_close($curl);
        return null;
    }

    $httpCode   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

    if ($httpCode >= 400) {
        $errorBody = trim(substr($response, $headerSize));
        $decoded   = json_decode($errorBody, true);
        $reason    = $decoded['message'] ?? $errorBody;
        error_log("GitHub API error: HTTP $httpCode for URL $url | Reason: $reason");
        curl_close($curl);
        return null;
    }
    $header     = substr($response, 0, $headerSize);
    $body       = json_decode(substr($response, $headerSize), true);

    curl_close($curl);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error for $url: " . json_last_error_msg());
        return null;
    }

    return ["headers" => $header, "body" => $body];
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
function fetchAllGitHubPages($url, $token, $maxPages = 2)
{
    $results        = [];
    $pagesFetched   = 0;
    $anySuccess     = false;

    do {
        $result = loadData($url, $token);

        if ($result === null || !isset($result["body"]) || $result["body"] === null) {
            return $anySuccess ? $results : false;
        }

        $anySuccess = true;
        $results      = array_merge($results, $result["body"]);
        $pagesFetched++;

        $url = getNextPageUrl($result["headers"]);
    } while ($url && $pagesFetched < $maxPages);

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
        error_log("Missing head info in pull request for: " . ($issueData['url'] ?? 'unknown'));
        return $issueData;
    }

    $repoUrl = $pullRequest["body"]["head"]["repo"]["url"];
    $sha     = $pullRequest["body"]["head"]["sha"] ?? $pullRequest["body"]["head"]["ref"];

    $state = loadData($repoUrl . "/commits/" . urlencode($sha) . "/status", $token);

    if ($state !== null && isset($state["body"]["state"])) {
        $issueData["state"] = $state["body"]["state"];

        $issueData["is_valid_pr"] = (
            $state["body"]["state"] === "success" &&
            ($issueData["mergeable"] ?? false) === true &&
            in_array($issueData["mergeable_state"] ?? '', ["clean", "unstable"], true)
        );
    }

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
 * @param int    $ttl      Time to live in seconds (default 180)
 * @return bool True if a valid cache was found and response sent, false otherwise
 */
function getCache($cacheKey = 'data', $ttl = 180)
{
    if (
        !isset($_GET['page']) &&        // only infinite-scroll should bypass cache
        isset($_SESSION[$cacheKey]['last_api_call']) &&
        $_SESSION[$cacheKey]['last_api_call'] > (time() - $ttl)
    ) {
        sendJsonResponse(
            $_SESSION[$cacheKey]["data"],
            $_SESSION[$cacheKey]['last_api_call'],
            "hit",
            $ttl
        );
        return true;
    }

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
