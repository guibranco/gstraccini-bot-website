<?php
/**
 * API endpoint for infinite scroll (paged issues and pull requests)
 */
require_once "includes/github-api.php";

$token = checkAuth();

session_start();
$_SESSION['last_api_call'] = time();
session_write_close();

if (!isset($_GET['page']) || !is_numeric($_GET['page']) || $_GET['page'] < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid page parameter']);
    exit();
}

$page = intval($_GET['page']);

$response = loadData('https://api.github.com/issues?per_page=50&page=' . $page, $token);
$issues = $response["body"] ?? [];

$openPullRequests = [];
$openIssues = [];

if (is_array($issues) && count($issues) > 0) {
    foreach ($issues as $issue) {
        $issueData = formatIssueData($issue);
        
        if (isset($issue['pull_request'])) {
            $openPullRequests[] = $issueData;
        } else {
            $openIssues[] = $issueData;
        }
    }
}

$data = [
    'openPullRequests' => $openPullRequests,
    'openIssues' => $openIssues
];

sendJsonResponse($data);
