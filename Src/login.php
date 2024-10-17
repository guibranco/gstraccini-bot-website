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
$redirect_uri = urlencode($gitHubRedirectUri); 
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$authorizeUrl = "https://github.com/login/oauth/authorize?client_id={$gitHubClientId}&redirect_uri={$redirect_uri}&state={$state}";
header("Location: $authorizeUrl");
exit();
