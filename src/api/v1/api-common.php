<?php
/**
 * Shared helpers used by api-dashboard.php and api-pull-requests.php.
 */

require_once __DIR__ . '/../../includes/pr-queue.php';

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
        logError("Failed to fetch assigned issues from the GitHub API", [], 'github');
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

    logDebug(
        "Processed {total} assigned item(s): {pullRequests} pull request(s) "
            . "({validPullRequests} valid, {reposProcessed} repository(ies) enriched) and {issues} issue(s)",
        [
            'total'             => count($issues),
            'pullRequests'      => count($openPullRequests),
            'issues'            => count($openIssues),
            'validPullRequests' => $validPRCount,
            'reposProcessed'    => count($processedRepos),
        ],
        'github'
    );

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

/**
 * Returns the user's assigned issues/PRs in slim form, cached in the session
 * for PR_QUEUE_ISSUES_CACHE_TTL seconds so the dashboard and the pull requests
 * page share a single GitHub call.
 *
 * Must be called after checkAuth() (the session is already closed then).
 *
 * @param  string $token GitHub API token.
 * @return array         Slim issue list (see prQueueSlimIssue()).
 */
function getAssignedIssuesCached(string $token): array
{
    $cacheKey = 'assigned-issues';
    $cached   = $_SESSION[$cacheKey] ?? null;

    if (is_array($cached) && ($cached['fetched_at'] ?? 0) > (time() - PR_QUEUE_ISSUES_CACHE_TTL)) {
        return $cached['items'];
    }

    $items = array_map('prQueueSlimIssue', fetchAssignedIssues($token));

    session_start();
    $_SESSION[$cacheKey] = ['items' => $items, 'fetched_at' => time()];
    session_write_close();

    return $items;
}

/**
 * Builds the queue-backed pull request payload, remembers which pull requests
 * this user may stream updates for, and stores the SSR snapshot.
 *
 * @return array Result of buildQueuedPullRequests().
 */
function loadQueuedAssignedItems(string $token): array
{
    $login   = $_SESSION['user']['login'] ?? 'unknown';
    $issues  = getAssignedIssuesCached($token);
    $results = buildQueuedPullRequests($issues, $token, $login);

    session_start();
    $_SESSION[PR_QUEUE_SESSION_KEYS] = $results['keys'];
    $_SESSION['pull-requests'] = [
        'data'          => ['openPullRequests' => $results['pullRequests']],
        'last_api_call' => time(),
    ];
    session_write_close();

    return $results;
}

/**
 * Stream descriptor sent to the browser when results are still on the way.
 */
function buildStreamDescriptor(array $results): ?array
{
    if ($results['pending'] === 0) {
        return null;
    }

    return [
        'url'   => '/api/v1/pull-requests/stream',
        'since' => $results['since'],
    ];
}
