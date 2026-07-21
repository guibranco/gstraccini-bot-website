<?php
/**
 * API endpoint for the signed-in user's pending actions, scoped to their
 * GitHub user id and GitHub App installation ids.
 */

if (!file_exists("../../webhook.secrets.php")) {
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}
require_once "../../webhook.secrets.php";
require_once "../../includes/constants.php";
require_once "../../includes/log-stream.php";
require_once "../../includes/remote-json-proxy.php";
require_once "../../includes/session.php";
require_once "../../includes/user-scope.php";

if ($isAuthenticated === false) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

header('Content-Type: application/json');

$url = appendUserScopeParams($gstracciniApiUrl . "v1/pending-actions/");

proxyJsonFromUpstream($url, 'pending actions');
