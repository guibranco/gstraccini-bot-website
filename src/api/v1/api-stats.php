<?php
/**
 * API endpoint for GStraccini-Bot usage statistics (public, unauthenticated)
 */

if (!file_exists("../../webhook.secrets.php")) {
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}
require_once "../../webhook.secrets.php";
require_once "../../includes/constants.php";
require_once "../../includes/log-stream.php";
require_once "../../includes/remote-json-proxy.php";

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

proxyJsonFromUpstream($gstracciniApiUrl . "v1/stats/", 'stats');
