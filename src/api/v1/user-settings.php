<?php
/**
 * API endpoint for reading and updating the signed-in user's bot behavior
 * toggles (auto-merge, auto-review, label creation, etc.).
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');

$url = appendUserIdParam($gstracciniApiUrl."v1/user-settings/", getCurrentUserId());

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    relayUserUpdate($url, 'user settings', ["X-Api-Key: $gstracciniApiKey"]);
}

proxyJsonFromUpstream($url, 'user settings', ["X-Api-Key: $gstracciniApiKey"]);
