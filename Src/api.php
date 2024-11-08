<?php
$expires = 60;
$cookie_lifetime = 604800;
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => 'bot.straccini.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

$data = ['error' => 'Unauthorized'];
if (!isset($_SESSION['user']) || !isset($_SESSION['token'])) {
    echo json_encode($data);
}

if (!isset($_GET['page']) && 
    !isset($_GET['dashboard']) &&
    isset($_SESSION['last_api_call']) && 
    $_SESSION['last_api_call'] > (time() - 180) &&
    isset($_SESSION['data'])
) {
    $time = $_SESSION['last_api_call'];
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=' . $expires);
    header('Pragma: cache');
    header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
    header("X-Cache: hit");
    echo json_encode($_SESSION['data']);
    exit();
}

$time = time();
$_SESSION['last_api_call'] = $time;
$token = $_SESSION['token'];
session_write_close();

function loadData($url, $token)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "User-Agent: GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)",
        "Accept: application/vnd.github+json",
        "X-GitHub-Api-Version: 2022-11-28"
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = json_decode(substr($response, $headerSize), true);

    curl_close($ch);

    return ["headers" => $header, "body" => $body];
}

function fetchAllGitHubPages($url, $token)
{
    $results = [];

    do {
        $result = loadData($url, $token);
        if ($result === null || isset($result["body"]) === null) {
            break;
        }

        $results = array_merge($results, $result["body"]);
        $url = getNextPageUrl($result["headers"]);
    } while ($url);

    return $results;
}

function getNextPageUrl($link_header)
{
    if (preg_match('/<([^>]+)>; rel="next"/', $link_header, $matches)) {
        return $matches[1];
    }
    return null;
}

if (isset($_GET['page'])) {
    $responseIssues = loadData('https://api.github.com/issues?per_page=50&page=' . intval($_GET['page']), $token)["body"];
    $responseRepositories = null;
} else if (isset($_GET['dashboard'])) {
    $responseIssues = loadData('https://api.github.com/issues?per_page=50&page=1', $token)["body"];
    $responseRepositories = null;
} else {
    $responseIssues = fetchAllGitHubPages('https://api.github.com/issues?per_page=100', $token);
    $responseRepositories = fetchAllGitHubPages('https://api.github.com/user/repos?per_page=100', $token);
}

$openPullRequests = [];
$openIssues = [];
$repositories = [];

$count = 0;

if ($responseIssues !== null && is_array($responseIssues) === true && count($responseIssues) > 0) {
    foreach ($responseIssues as $issue) {
        $issueData = [
            'title' => $issue['title'],
            'repository' => $issue['repository']['name'],
            'full_name' => $issue['repository']['full_name'],
            'url' => $issue['html_url'],
            'created_at' => $issue['created_at']
        ];

        if (isset($issue['pull_request']) === true && isset($_GET['page']) === false) {
            if ($count < 10) {
                $count++;
                $pullRequest = loadData($issue['pull_request']['url'], $token);
                if ($pullRequest !== null && $pullRequest["body"] !== null) {
                    $repoUrl = $pullRequest["body"]["head"]["repo"]["url"];
                    $branch = $pullRequest["body"]["head"]["ref"];
                    $state = loadData($repoUrl . "/commits/" . urlencode($branch) . "/status", $token);
                    if ($state !== null && $state["body"] !== null && isset($state["body"]["state"])) {
                        $issueData["state"] = $state["body"]["state"];
                    }
                }
            }
            $openPullRequests[] = $issueData;
        } else {
            $openIssues[] = $issueData;
        }
    }
}

if ($responseRepositories !== null && is_array($responseRepositories) === true && count($responseRepositories) > 0) {
    foreach ($responseRepositories as $repo) {
        $repositories[] = [
            'name' => $repo['name'],
            'organization' => $repo['owner']['login'],
            'url' => $repo['html_url'],
            'fork' => $repo['fork'],
            'stars' => $repo['stargazers_count'],
            'forks' => $repo['forks'],
            'issues' => $repo['open_issues_count'],
            'language' => $repo['language'],
            'visibility' => $repo['visibility']
        ];
    }
}

sort($repositories);

if (isset($_GET['dashboard'])) {
    $data = [
        'openPullRequestsDashboard' => $openPullRequests,
        'openPullRequestsDashboard' => $openIssues,
    ];    
} else {
    $data = [
        'openPullRequests' => $openPullRequests,
        'openIssues' => $openIssues,
        'repositories' => $repositories
    ];
}

session_start();
$time = time();
header('Content-Type: application/json');
header('Cache-Control: public, max-age=' . $expires);
header('Pragma: cache');
header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
header("X-Cache: miss");
if (!isset($_GET['page']) && !isset($_GET['dashboard'])) {
    $_SESSION['data'] = $data;
    $_SESSION['last_api_call'] = $time;
}
echo json_encode($data);
