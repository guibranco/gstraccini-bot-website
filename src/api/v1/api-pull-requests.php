<?php
/**
 * API endpoint for listing assigned GitHub pull requests with CI and Git mergeable status
 */

$cacheKey = "pull-requests";
$token = checkAuth();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

// Cap at 2 pages (200 items) to avoid exhausting the API rate limit.
$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token, 2);

if ($issues === false || !is_array($issues)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch pull requests from GitHub API']);
    exit();
}

$openPullRequests = [];
$validPRCount     = 0;
$processedRepos   = [];

foreach ($issues as $issue) {
    if (!isset($issue['pull_request'])) {
        continue;
    }

    $issueData           = formatIssueData($issue);
    $repositoryId        = $issue['repository']['id'];
    $repositoryProcessed = isset($processedRepos[$repositoryId]);

    if ($validPRCount < 10 && !$repositoryProcessed) {
        $pullRequest = loadData($issue['pull_request']['url'], $token);

        if (
            $pullRequest !== null
            && isset($pullRequest["body"])
            && $pullRequest["body"] !== null
        ) {
            $issueData = enrichPullRequestData($issueData, $pullRequest, $token);

            if (isset($issueData["is_valid_pr"]) && $issueData["is_valid_pr"] === true) {
                $validPRCount++;
            }
        }

        // Always mark the repo as processed after the first enrichment attempt
        // so subsequent PRs from the same repo are skipped without extra API calls.
        $processedRepos[$repositoryId] = true;
    } else {
        $issueData["state"] = "skipped";
    }

    $openPullRequests[] = $issueData;
}

$data = [
    'openPullRequests' => $openPullRequests
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data, time());
