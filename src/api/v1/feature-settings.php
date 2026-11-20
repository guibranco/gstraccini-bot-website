<?php
/**
 * API endpoint for reading and updating the bot's opt-in features per GitHub
 * account and per repository (e.g. requesting a version bump decision on
 * feature pull requests).
 *
 *   GET /api/v1/account-settings                    accounts of the user's installations
 *   PUT /api/v1/account-settings/{accountId}        {"requestVersionBump": true|false}
 *   GET /api/v1/repository-settings[?accountId=]    repositories of the user's installations
 *   PUT /api/v1/repository-settings/{repositoryId}  {"requestVersionBump": true|false|null}
 *
 * The upstream API checks the account / repository belongs to one of the
 * signed-in user's installations.
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');

$resource = $_GET['resource'] ?? '';
if (in_array($resource, ['account-settings', 'repository-settings'], true) === false) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit();
}

$id = $_GET['id'] ?? null;
if ($id !== null && ctype_digit((string) $id) === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit();
}

$headers = ["X-Api-Key: $gstracciniApiKey"];
$resourceName = str_replace('-', ' ', $resource);

if ($id === null) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'Method Not Allowed']);
        exit();
    }

    $query = ['userId' => getCurrentUserId()];
    if ($resource === 'repository-settings' && isset($_GET['accountId']) && ctype_digit((string) $_GET['accountId'])) {
        $query['accountId'] = (int) $_GET['accountId'];
    }

    proxyJsonFromUpstream($gstracciniApiUrl."v1/{$resource}/?".http_build_query($query), $resourceName, $headers);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    header('Allow: PUT');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$url = appendUserIdParam($gstracciniApiUrl."v1/{$resource}/".((int) $id)."/", getCurrentUserId());
relayUserUpdate($url, $resourceName, $headers);
