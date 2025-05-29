<?php
/**
 * API endpoint for listing assigned GitHub issues
 */
require_once "includes/github-api.php";

$cacheKey = "issues";
$token = checkAuth();

session_start();
$_SESSION['last_api_call'] = time();
session_write_close();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token);

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
