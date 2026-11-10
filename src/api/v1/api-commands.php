<?php
require_once "../../includes/api-guards.php";

ensureConfigurationFileExists("../../webhook.secrets.php", basename(__FILE__));
require_once "../../webhook.secrets.php";

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

proxyJsonFromUpstream($gstracciniApiUrl . "v1/commands/?format=json", 'commands');
