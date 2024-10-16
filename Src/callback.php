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

require_once "github.secrets.php";

if (isset($_GET['code']) === false) {
    header('Location: index.php?error=Authorization+code+not+found');
    exit();
}
 
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
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
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
    'Authorization: Bearer ' . $token,
    'User-Agent: GStraccini-bot-website/1.0'
]);

$userData = curl_exec($ch);
curl_close($ch);

$user = json_decode($userData, true);
session_regenerate_id(true);
$_SESSION['token'] = $token;
$_SESSION['user'] = $user;
header('Location: dashboard.php');
exit();
