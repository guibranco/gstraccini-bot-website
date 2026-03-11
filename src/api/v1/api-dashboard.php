<?php
/**
 * API endpoint for dashboard view (mix of assigned issues and pull requests)
 */

require_once 'api-common.php';

$cacheKey = "dashboard";
$token    = checkAuth();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$issues  = fetchAssignedIssues($token);
$results = processIssuesAndPullRequests($issues, $token);

$data = [
    'openPullRequestsDashboard' => $results['pullRequests'],
    'openIssuesDashboard'       => $results['issues'],
];

cacheAndRespond($data, $cacheKey);
