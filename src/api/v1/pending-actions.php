<?php
/**
 * API endpoint for the signed-in user's unread pending actions, scoped to
 * their GitHub user id.
 */

require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));

require_once "../../webhook.secrets.php";
require_once "../../includes/session.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__));

header('Content-Type: application/json');

$url = appendUserIdParam($gstracciniApiUrl."v1/pending-actions/", getCurrentUserId());

proxyJsonFromUpstream($url, 'pending actions', ["X-Api-Key: $gstracciniApiKey"]);
