<?php
require_once "includes/session.php";
require_once "github.secrets.php";

try {
    $redirect_uri = urlencode($gitHubRedirectUri);
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $scope = urlencode('repo user:email');
    $authorizeUrl = "https://github.com/login/oauth/authorize?client_id={$gitHubClientId}&redirect_uri={$redirect_uri}&state={$state}&scope={$scope}";
    logInfo("Redirecting to GitHub OAuth authorization with scope {scope}", ['scope' => 'repo user:email'], 'auth');
    header("Location: $authorizeUrl");
    exit();
} catch (Exception $e) {
    logError("Failed to generate OAuth state: {error}", ['error' => $e->getMessage()], 'auth');
    header("Location: index.php?error=Failed+to+generate+OAuth+state");
    exit();
}