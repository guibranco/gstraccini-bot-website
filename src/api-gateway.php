<?php
/**
 * Main API router that directs requests to the appropriate endpoint
 */
require_once "includes/session.php";

if ($isAuthenticated === false) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_GET['repositories'])) {
    require_once "api-repositories.php";
} else if (isset($_GET['issues'])) {
    require_once "api-issues.php";
} else if (isset($_GET['pull_requests'])) {
    require_once "api-pull-requests.php";
} else if (isset($_GET['dashboard'])) {
    require_once "api-dashboard.php";
} else if (isset($_GET['page'])) {
    require_once "api-infinite-scroll.php";
} else {
    require_once "api-old.php";
}
