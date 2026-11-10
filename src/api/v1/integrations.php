<?php
/**
 * API endpoint for listing, adding, and removing the signed-in user's
 * third-party integration API keys.
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');

$userId = getCurrentUserId();
$segments = array_values(array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))));
$resourceIndex = array_search('integrations', $segments, true);
$providerSegment = $resourceIndex !== false ? ($segments[$resourceIndex + 1] ?? null) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $url = appendUserIdParam($gstracciniApiUrl."v1/integrations/", $userId);
    $result = sendJsonToUpstream($url, 'POST', $body, ["X-Api-Key: $gstracciniApiKey"]);
    logIntegrationChange('added', $userId, $body['provider'] ?? null, $result['httpCode']);
    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($providerSegment === null || $providerSegment === '') {
        logNotice(
            "Integration removal by user {userId} rejected: missing provider",
            ['userId' => $userId],
            'integrations'
        );
        http_response_code(400);
        echo json_encode(['error' => 'Missing provider']);
        exit();
    }

    $url = appendUserIdParam($gstracciniApiUrl."v1/integrations/".rawurlencode($providerSegment)."/", $userId);
    $result = sendJsonToUpstream($url, 'DELETE', null, ["X-Api-Key: $gstracciniApiKey"]);
    logIntegrationChange('removed', $userId, $providerSegment, $result['httpCode']);
    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}

$url = appendUserIdParam($gstracciniApiUrl."v1/integrations/", $userId);
proxyJsonFromUpstream($url, 'integrations', ["X-Api-Key: $gstracciniApiKey"]);
