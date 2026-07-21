<?php
/**
 * API endpoint for reading and updating the signed-in user's bot behavior
 * toggles (auto-merge, auto-review, label creation, etc.).
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

$url = appendUserIdParam($gstracciniApiUrl."v1/user-settings/", getCurrentUserId());

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $result = sendJsonToUpstream($url, 'PUT', $body, ["X-Api-Key: $gstracciniApiKey"]);
    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}

proxyJsonFromUpstream($url, 'user settings', ["X-Api-Key: $gstracciniApiKey"]);
