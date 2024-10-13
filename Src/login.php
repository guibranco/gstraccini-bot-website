<?php
require_once "github.secrets.php";
$authorizeUrl = "https://github.com/login/oauth/authorize?client_id=$gitHubClientId&redirect_uri=" . urlencode($gitHubRedirectUri);
header("Location: $authorizeUrl");
exit();
