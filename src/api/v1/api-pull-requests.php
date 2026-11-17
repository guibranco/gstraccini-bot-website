<?php
/**
 * API endpoint for listing assigned GitHub pull requests with CI and Git mergeable status.
 *
 * CI state and mergeability are not fetched here any more: they come from the
 * local SQLite store, and stale/missing ones are queued to CloudAMQP for the
 * drain job (see includes/pr-queue.php). While results are pending, the payload
 * carries a "stream" descriptor the page uses to receive them live (SSE).
 */

require_once 'api-common.php';

$token   = checkAuth();
$results = loadQueuedAssignedItems($token, $_SESSION);

sendJsonResponse(buildPullRequestsPayload($results), time(), 'miss', 0);
