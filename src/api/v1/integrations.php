<?php
/**
 * API endpoint for listing, adding, and removing the signed-in user's
 * third-party integration API keys.
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

$userId = getCurrentUserId();
$segments = array_values(array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))));
$resourceIndex = array_search('integrations', $segments, true);
$providerSegment = $resourceIndex !== false ? ($segments[$resourceIndex + 1] ?? null) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $url = appendUserIdParam($gstracciniApiUrl."v1/integrations/", $userId);
    $result = sendJsonToUpstream($url, 'POST', $body, ["X-Api-Key: $gstracciniApiKey"]);
    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($providerSegment === null || $providerSegment === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing provider']);
        exit();
    }

    $url = appendUserIdParam($gstracciniApiUrl."v1/integrations/".rawurlencode($providerSegment)."/", $userId);
    $result = sendJsonToUpstream($url, 'DELETE', null, ["X-Api-Key: $gstracciniApiKey"]);
    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}

$url = appendUserIdParam($gstracciniApiUrl."v1/integrations/", $userId);
proxyJsonFromUpstream($url, 'integrations', ["X-Api-Key: $gstracciniApiKey"]);
