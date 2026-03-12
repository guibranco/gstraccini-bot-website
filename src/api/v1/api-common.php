<?php
/**
 * Shared helpers used by api-dashboard.php and api-pull-requests.php.
 */

/**
 * Fetches all assigned GitHub issues, validates the response, and terminates
 * with a 500 error when the upstream call fails.
 *
 * @param  string $token GitHub API token.
 * @return array         Raw issue/PR list from the GitHub API.
 */
function fetchAssignedIssues(string $token): array
{
    $issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token, 1);

    if ($issues === false || !is_array($issues)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch issues from GitHub API']);
        exit();
    }

    return $issues;
}

/**
 * Processes a raw GitHub issues list and separates it into enriched pull
 * requests and plain issues.
 *
 * Pull-request detail is fetched only when:
 *   - fewer than $maxValidPRs valid PRs have been collected, AND
 *   - no PR from the same repository has already been processed.
 *
 * @param  array  $issues       Raw items returned by fetchAssignedIssues().
 * @param  string $token        GitHub API token.
 * @param  int    $maxValidPRs  Maximum number of valid PRs to enrich (default 10).
 * @return array{
 *     pullRequests: list<array>,
 *     issues:       list<array>,
 * }
 */
function processIssuesAndPullRequests(array $issues, string $token, int $maxValidPRs = 10): array
{
    $openPullRequests = [];
    $openIssues       = [];
    $validPRCount     = 0;
    $processedRepos   = [];

    foreach ($issues as $issue) {
        $issueData = formatIssueData($issue);

        if (!isset($issue['pull_request'])) {
            $openIssues[] = $issueData;
            continue;
        }

        $repositoryId        = $issue['repository']['id'];
        $repositoryProcessed = isset($processedRepos[$repositoryId]);

        if ($validPRCount < $maxValidPRs && !$repositoryProcessed) {
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

    return [
        'pullRequests' => $openPullRequests,
        'issues'       => $openIssues,
    ];
}

/**
 * Persists $data in the session cache and sends it as a JSON response.
 *
 * @param array  $data     Payload to cache and return.
 * @param string $cacheKey Session cache key.
 */
function cacheAndRespond(array $data, string $cacheKey): void
{
    session_start();
    setCache($data, $cacheKey);
    session_write_close();
    sendJsonResponse($data, time());
}
