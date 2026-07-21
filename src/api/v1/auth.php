<?php
/**
 * API endpoint proxying password login, TOTP 2FA, recovery codes, and
 * password reset to the upstream GStraccini API. Login/reset routes run
 * before a session exists (no auth required); account-security routes
 * (change password, TOTP setup, recovery codes) require the signed-in
 * user's session and are scoped to their GitHub user id.
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

header('Content-Type: application/json');

$routesRequiringSession = [
    'password',
    'totp/setup',
    'totp/enable',
    'totp/disable',
    'totp/status',
    'recovery-codes/generate',
];

$segments = array_values(array_filter(explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))));
$authIndex = array_search('auth', $segments, true);
$route = $authIndex !== false ? implode('/', array_slice($segments, $authIndex + 1)) : '';

if (in_array($route, $routesRequiringSession, true) && $isAuthenticated === false) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

$upstreamUrl = $gstracciniApiUrl."v1/auth/{$route}/";
if (in_array($route, $routesRequiringSession, true)) {
    $upstreamUrl = appendUserIdParam($upstreamUrl, getCurrentUserId());
}

$method = $_SERVER['REQUEST_METHOD'];
$body = $method === 'GET' ? null : (json_decode(file_get_contents('php://input'), true) ?? []);

if ($method === 'GET') {
    proxyJsonFromUpstream($upstreamUrl, "auth/{$route}", ["X-Api-Key: $gstracciniApiKey"]);
    exit();
}

$result = sendJsonToUpstream($upstreamUrl, $method, $body, ["X-Api-Key: $gstracciniApiKey"]);
http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
echo json_encode($result['decoded']);
