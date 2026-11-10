<?php
/**
 * API endpoint for the signed-in user's recent activity feed, scoped to
 * their GitHub user id.
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');
header('Cache-Control: private, max-age=60');

$url = appendUserIdParam($gstracciniApiUrl."v1/recent-activities/", getCurrentUserId());

proxyJsonFromUpstream($url, 'recent activities', ["X-Api-Key: $gstracciniApiKey"]);
