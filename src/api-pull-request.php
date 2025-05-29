<?php
/**
 * API endpoint for listing assigned GitHub pull requests with CI and Git mergeable status
 */
require_once "includes/github-api.php";

$cacheKey = "pull-requests";
$token = checkAuth();

session_start();
$_SESSION['last_api_call'] = time();
session_write_close();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token);

if ($issues === false || !is_array($issues)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch pull requests from GitHub API']);
    exit();
}

$openPullRequests = [];
$validPRCount = 0;
$processedRepos = array();

if (is_array($issues) && count($issues) > 0) {
    foreach ($issues as $issue) {
        if (!isset($issue['pull_request'])) {
            continue;
        }
        
        $issueData = formatIssueData($issue);
        $repositoryId = $issue['repository']['id'];
        $repositoryProcessed = isset($processedRepos[$repositoryId]);

        if ($validPRCount < 10) {
            $pullRequest = loadData($issue['pull_request']['url'], $token);
            
            if ($pullRequest !== null && isset($pullRequest["body"]) === true && $pullRequest["body"] !== null) {
                $issueData = enrichPullRequestData($issueData, $pullRequest, $token);
                
                if (isset($issueData["is_valid_pr"]) && $issueData["is_valid_pr"] === true && !$repositoryProcessed && $validPRCount < 10) {
                    $validPRCount++;
                    $processedRepos[$repositoryId] = true;
                }
            }
        } else {
            $issueData["state"] = "skipped";
        }
        
        $openPullRequests[] = $issueData;
    }
}

$data = [
    'openPullRequests' => $openPullRequests
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data);
