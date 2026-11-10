<?php
/**
 * Main API router that directs requests to the appropriate endpoint
 */
require_once "../../includes/session.php";
require_once "../../includes/github-api.php";
require_once "../../includes/api-guards.php";

ensureAuthenticated($isAuthenticated, basename(__FILE__), 'Unauthorized');

if (isset($_GET['repositories'])) {
    require_once "api-repositories.php";
} elseif (isset($_GET['issues'])) {
    require_once "api-issues.php";
} elseif (isset($_GET['pull_requests'])) {
    require_once "api-pull-requests.php";
} elseif (isset($_GET['dashboard'])) {
    require_once "api-dashboard.php";
} elseif (isset($_GET['page'])) {
    require_once "api-infinite-scroll.php";
} else {
    require_once "api-dashboard.php";
}
