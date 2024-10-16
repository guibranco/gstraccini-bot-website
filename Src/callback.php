<?php
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
 
$apiUrl = 'https://api.github.com/user';

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "User-Agent: GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)",
    "Accept: application/vnd.github+json",
    "X-GitHub-Api-Version: 2022-11-28"
]);

$userData = curl_exec($ch);
curl_close($ch);

$user = json_decode($userData, true);
$_SESSION['token'] = $token;
$_SESSION['user'] = $user;
header('Location: dashboard.php');
exit();
