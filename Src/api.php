<?php
session_start();

$data = ['error' => 'Unauthorized'];
if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    echo json_encode($data);
}

function loadData($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $_SESSION['token'],
        'User-Agent: GStraccini-bot-website/1.0'
    ]);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$token = $_SESSION['token'];

$repos = 'https://api.github.com/user/repos?per_page=100';
$reposData = loadData($repos);
$repositories = [];
if ($reposData) {
    $repos = json_decode($reposData, true);
    foreach ($repos as $repo) {
        $repositories[] = [
            'name' => $repo['name'],
            'full_name' => $repo['full_name'],
            'url' => $repo['html_url'],
            'stars' => $repo['stargazers_count'],
            'forks' => $repo['forks_count'],
            'issues' => $repo['open_issues_count']
        ];
    }
}

sort($repositories);

$issues = "https://api.github.com/issues?per_page=100";
$issuesData = loadData($issues);


$openPullRequests = [];
$openIssues = [];
if ($issuesData) {
    $issues = json_decode($issuesData, true);
    foreach ($issues as $issue) {
        $issueData = [
            'title' => $issue['title'],
            'url' => $issue['html_url'],
            'created_at' => $issue['created_at']
        ];
        
        if (isset($issue['pull_request']) === true) {
            $openPullRequests[] = $issueData;
        } else {
            $openIssues[] = $issueData;
        }
    }
}

$data = [
    'openPullRequests' => $openPullRequests,
    'openIssues' => $openIssues,
    'repositories' => $repositories
];

$_SESSION['data'] = $data;
echo json_encode($data);
