<?php
/**
 * API endpoint for listing assigned GitHub issues
 */

$cacheKey = "issues";
$token = checkAuth();

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

foreach ($issues as $issue) {
    // Skip pull requests — they are served by the dedicated pull_requests endpoint
    if (isset($issue['pull_request'])) {
        continue;
    }

    $openIssues[] = formatIssueData($issue);
}

$data = [
    'openIssues' => $openIssues
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data, time());
