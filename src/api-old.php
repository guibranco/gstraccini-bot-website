<?php
/**
 * API endpoint for old style API view (mix of assigned issues and pull requests and repositories)
 */
require_once "includes/github-api.php";

$token = checkAuth();
$_SESSION['last_api_call'] = time();
session_write_close();

$cache = getCache('data');
if ($cache !== false) {
    exit();
}

$issues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token);
$repositories = fetchAllGitHubPages('https://api.github.com/user/repos?per_page=100', $token);

$openPullRequests = [];
$openIssues = [];
$formattedRepositories = [];
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

if (is_array($repositories) && count($repositories) > 0) {
    foreach ($repositories as $repo) {
        $formattedRepositories[] = formatRepositoryData($repo);
    }
}

sort($formattedRepositories);

$data = [
    'openPullRequests' => $openPullRequests,
    'openIssues' => $openIssues,
    'repositories' => $formattedRepositories
];

session_start();
setCache($data);
session_write_close();
sendJsonResponse($data);
