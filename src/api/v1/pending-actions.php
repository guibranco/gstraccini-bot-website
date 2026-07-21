<?php
/**
 * API endpoint for the signed-in user's unread pending actions, scoped to
 * their GitHub user id.
 */

if (file_exists("../../webhook.secrets.php") === false) {
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

$url = appendUserIdParam($gstracciniApiUrl."v1/pending-actions/", getCurrentUserId());

proxyJsonFromUpstream($url, 'pending actions', ["X-Api-Key: $gstracciniApiKey"]);
