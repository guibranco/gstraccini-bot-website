<?php
/**
 * GitHub API Helper Functions
 * 
 * Contains utility functions for interacting with the GitHub API.
 */

/**
 * Load data from the GitHub API
 * 
 * @param string $url The GitHub API URL
 * @param string $token The GitHub API token
 * @return array|null Response array with headers and body or null on error
 */
function loadData($url, $token)
{
    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "User-Agent: GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28"
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        curl_close($curl);
        return null;
    }

    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = json_decode(substr($response, $headerSize), true);

    curl_close($curl);

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
 * Fetch all pages from a GitHub API endpoint
 * 
 * @param string $url The GitHub API URL
 * @param string $token The GitHub API token
 * @return array Combined results from all pages
 */
function fetchAllGitHubPages($url, $token)
{
    $results = [];

    do {
        $result = loadData($url, $token);
        if ($result === null || isset($result["body"]) === null) {
            break;
        }

        $results = array_merge($results, $result["body"]);
        $url = getNextPageUrl($result["headers"]);
    } while ($url);

    return $results;
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
        'title' => $issue['title'],
        'repository' => $issue['repository']['name'],
        'full_name' => $issue['repository']['full_name'],
        'url' => $issue['html_url'],
        'owner' => $issue['repository']['owner']['login'],
        'labels' => array_map(function ($label) {
            return [
                'color' => $label['color'] ?? null,
                'description' => $label['description'] ?? null,
                'name' => $label['name'] ?? null
            ];
        }, $issue['labels']),
        'created_at' => $issue['created_at']
    ];
}

/**
 * Get pull request additional data (CI status, mergeable state)
 * 
 * @param array $issueData Base issue data
 * @param array $pullRequest Pull request data from GitHub API
 * @param string $token GitHub API token
 * @return array Issue data with additional pull request info
 */
function enrichPullRequestData($issueData, $pullRequest, $token)
{
    $issueData["mergeable"] = $pullRequest["body"]["mergeable"] ?? null;
    $issueData["mergeable_state"] = $pullRequest["body"]["mergeable_state"] ?? null;
    
    if (isset($pullRequest["body"]["head"]) === true && $pullRequest["body"]["head"] !== null) {
        $repoUrl = $pullRequest["body"]["head"]["repo"]["url"];
        $branch = $pullRequest["body"]["head"]["ref"];
        $sha = $pullRequest["body"]["head"]["sha"] ?? $branch;
        $state = loadData(
            $repoUrl . "/commits/" . urlencode($sha) . "/status",
            $token
        );
    
        if ($state !== null && $state["body"] !== null && isset($state["body"]["state"])) {
            $issueData["state"] = $state["body"]["state"];
            
            $isValidPR = false;

            if ($state["body"]["state"] === "success" && 
                ($issueData["mergeable"] === true || $issueData["mergeable"] === null) && 
                ($issueData["mergeable_state"] === null || in_array($issueData["mergeable_state"], ["clean", "unstable"]))) {
                
                $isValidPR = true;
            }
            
            $issueData["is_valid_pr"] = $isValidPR;
        }
    } else {
        error_log("Missing head info in pull request");
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
        'name' => $repo['name'],
        'organization' => $repo['owner']['login'],
        'url' => $repo['html_url'],
        'fork' => $repo['fork'],
        'stars' => $repo['stargazers_count'],
        'forks' => $repo['forks'],
        'issues' => $repo['open_issues_count'],
        'language' => $repo['language'],
        'visibility' => $repo['visibility']
    ];
}

/**
 * Send JSON response with appropriate headers
 * 
 * @param array $data Data to be encoded as JSON
 * @param int $time Time data was create
 * @param string $hitMiss TO set the X-Cache header if it hit the cache or miss
 * @param int $expires Cache expiration time in seconds
 */
function sendJsonResponse($data, $time, $hitMiss = "miss", $expires = 60)
{
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=' . $expires);
    header('Pragma: cache');
    header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
    header("X-Cache: " . $hitMiss);
    
    echo json_encode($data);
}

/**
 * Check authentication and return token
 * 
 * @return string|false GitHub API token or false if not authenticated
 */
function checkAuth()
{
    require_once "session.php";
    
    if ($isAuthenticated === false) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    return $_SESSION['token'];
}

/**
 * Check if cached data is available and valid
 * 
 * @param string $cacheKey Session key for cached data
 * @param int $ttl Time to live in seconds
 * @return array|false Cached data or false if not available/valid
 */
function getCache($cacheKey = 'data', $ttl = 180)
{
    if (
        !isset($_GET['page']) &&
        !isset($_GET['dashboard']) &&
        isset($_SESSION[$cacheKey]) &&
        isset($_SESSION[$cacheKey]['last_api_call']) &&
        $_SESSION[$cacheKey]['last_api_call'] > (time() - $ttl)        
    ) {
        sendJsonResponse($_SESSION[$cacheKey]["data"], $_SESSION[$cacheKey]['last_api_call'], "hit", $ttl);
        return true;
    }
    
    return false;
}

/**
 * Save data to cache
 * 
 * @param array $data Data to be cached
 * @param string $cacheKey Session key for cached data
 */
function setCache($data, $cacheKey = 'data')
{
    $_SESSION[$cacheKey] = 
      [
        "data" => $data,
        "last_api_call" => time()
      ];
}
