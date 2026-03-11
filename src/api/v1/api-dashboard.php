<?php
/**
 * API endpoint for dashboard view (mix of assigned issues and pull requests)
 */

$cacheKey = "dashboard";
$token = checkAuth();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

// Cap at 2 pages (200 items) to avoid exhausting the API rate limit on large accounts.
// fetchAllGitHubPages must accept an optional $maxPages argument (see github-api.php).
$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token, 2);

if ($issues === false || !is_array($issues)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch issues from GitHub API']);
    exit();
}

$openPullRequests = [];
$openIssues      = [];
$validPRCount    = 0;
$processedRepos  = [];

foreach ($issues as $issue) {
    $issueData = formatIssueData($issue);

    if (!isset($issue['pull_request'])) {
        $openIssues[] = $issueData;
        continue;
    }

    $repositoryId       = $issue['repository']['id'];
    $repositoryProcessed = isset($processedRepos[$repositoryId]);

    // Only fetch PR detail when we still need more valid PRs AND this repo
    // has not been processed yet.  Without both guards the old code made an
    // API call for every PR from an already-seen repo, wasting rate limit.
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

        // Mark the repo as processed regardless of whether the PR was valid.
        // The old code only marked it on valid PRs, so invalid PRs caused
        // repeated re-fetches of the same repo on every subsequent iteration.
        $processedRepos[$repositoryId] = true;
    } else {
        $issueData["state"] = "skipped";
    }

    $openPullRequests[] = $issueData;

    // Stop enriching as soon as we have reached the cap; remaining items are
    // already appended with state="skipped" above so we can break early.
    if ($validPRCount >= 10) {
        // Collect leftover PRs without further API calls
        // (they will simply carry their basic formatIssueData shape)
        // We do NOT break so that openIssues is still fully populated.
    }
}

$data = [
    'openPullRequestsDashboard' => $openPullRequests,
    'openIssuesDashboard'       => $openIssues,
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data, time());
