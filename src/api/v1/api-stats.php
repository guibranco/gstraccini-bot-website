<?php
/**
 * API endpoint for the signed-in user's usage statistics, scoped to their
 * GitHub user id.
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');
header('Cache-Control: private, max-age=300');

$url = appendUserIdParam($gstracciniApiUrl."v1/stats/", getCurrentUserId());

proxyJsonFromUpstream($url, 'stats', ["X-Api-Key: $gstracciniApiKey"]);
