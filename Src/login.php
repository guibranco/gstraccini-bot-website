<?php
require_once "includes/session.php";
require_once "github.secrets.php";

$redirect_uri = urlencode($gitHubRedirectUri);
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$authorizeUrl = "https://github.com/login/oauth/authorize?client_id={$gitHubClientId}&redirect_uri={$redirect_uri}&state={$state}";
header("Location: $authorizeUrl");
exit();
