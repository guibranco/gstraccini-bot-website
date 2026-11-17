<?php
/**
 * API endpoint for dashboard view (mix of assigned issues and pull requests).
 *
 * Pull request enrichment is queue-backed (see includes/pr-queue.php); the
 * dashboard simply shows the latest stored state on each poll.
 */

require_once 'api-common.php';

$token   = checkAuth();
$results = loadQueuedAssignedItems($token, $_SESSION);

sendJsonResponse(buildDashboardPayload($results), time(), 'miss', 0);
