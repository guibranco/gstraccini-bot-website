<?php
/**
 * API endpoint for dashboard view (mix of assigned issues and pull requests)
 */
require_once "includes/github-api.php";

$cacheKey = "dashboard";
$token = checkAuth();
$_SESSION['last_api_call'] = time();
session_write_close();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$response = loadData('https://api.github.com/issues?per_page=100&page=1', $token);
$issues = $response["body"] ?? [];

$openPullRequests = [];
$openIssues = [];
$validPRCount = 0;
$processedRepos = array();

if (is_array($issues) && count($issues) > 0) {
    foreach ($issues as $issue) {
        $issueData = formatIssueData($issue);

        if (isset($issue['pull_request'])) {
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
        } else {
            $openIssues[] = $issueData;
        }
    }
}

$data = [
    'openPullRequestsDashboard' => $openPullRequests,
    'openIssuesDashboard' => $openIssues,
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data);
