<?php
require_once "includes/session.php";

if (isset($_GET['state']) === false || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: index.php?error=Invalid+state+parameter');
    exit();
}

if (isset($_GET['code']) === false) {
    header('Location: index.php?error=Authorization+code+not+found');
    exit();
}

require_once "github.secrets.php";

$code = $_GET['code'];
$tokenUrl = 'https://github.com/login/oauth/access_token';
$postFields = [
    'client_id' => $gitHubClientId,
    'client_secret' => $gitHubClientSecret,
    'code' => $code,
    'redirect_uri' => $gitHubRedirectUri
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'User-Agent: GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)']);
$response = curl_exec($ch);
curl_close($ch);

$content = json_decode($response, true);
$token = $content['access_token'];

if ($token === null || empty($token) === true) {
    header('Location: index.php?error=Unable+to+retrieve+access+token');
    exit();
}

function getGitHub($url, $token)
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

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = json_decode(substr($response, $headerSize), true);

    curl_close($ch);

    return ["headers" => $headers, "body" => $body];
}


$apiUrlUser = 'https://api.github.com/user';
$userResponse = getGitHub($apiUrlUser, $token);

if (isset($userResponse["body"]["message"])) {
    header("Location: signin.php?error=" . urlencode($userResponse["body"]["message"]));
    exit();
}

if (isset($userResponse["body"]['name']) === true && preg_match('/^(\w+)(?:\s+[\w\s]+)?\s+(\w+)$/', $userResponse["body"]['name'], $matches)) {
    $userResponse["body"]['first_name'] = $matches[1];
    $userResponse["body"]['last_name'] = $matches[2];
}

$_SESSION['token'] = $token;
$_SESSION['user'] = $userResponse["body"];

$apiUrlInstallations = 'https://api.github.com/user/installations';
$installationsResponse = getGitHub($apiUrlInstallations, $token);

if(isset($installationsResponse["body"]["message"])) {
    header("Location: signin.php?error=" . urlencode($installationsResponse["body"]["message"]));
    exit();
}
$_SESSION['installations'] = $installationsResponse["body"];

$apiUrlOrganizations = 'https://api.github.com/users/' . $_SESSION['user']['login'] . '/orgs';
$organizationsResponse = getGitHub($apiUrlOrganizations, $token);

if(isset($organizationsResponse["body"]["message"])) {
    header("Location: signin.php?error=" . urlencode($organizationsResponse["body"]["message"]));
    exit();
}

$_SESSION['organizations'] = $organizationsResponse["body"];

$redirectUrl = $_SESSION['redirectUrl'] ?? 'dashboard.php';
$_SESSION['redirectUrl'] = null;
header("Location: {$redirectUrl}");
exit();
