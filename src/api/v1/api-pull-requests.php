<?php
/**
 * API endpoint for listing assigned GitHub pull requests with CI and Git mergeable status
 */

require_once 'api-common.php';

$cacheKey = "pull-requests";
$token    = checkAuth();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$issues  = fetchAssignedIssues($token);
$results = processIssuesAndPullRequests($issues, $token);

$data = [
    'openPullRequests' => $results['pullRequests'],
];

cacheAndRespond($data, $cacheKey);
