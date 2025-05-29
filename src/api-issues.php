<?php
/**
 * API endpoint for listing assigned GitHub issues
 */
require_once "includes/github-api.php";

$cacheKey = "issues";
$token = checkAuth();
$_SESSION['last_api_call'] = time();
session_write_close();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token);

if ($issues === false || !is_array($issues)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch issues from GitHub API']);
    exit();
}

$openIssues = [];
if (is_array($issues) && count($issues) > 0) {
    foreach ($issues as $issue) {
        // Skip pull requests
        if (isset($issue['pull_request'])) {
            continue;
        }

        $openIssues[] = formatIssueData($issue);
    }
}

$data = [
    'openIssues' => $openIssues
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data);
