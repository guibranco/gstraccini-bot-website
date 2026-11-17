<?php
/**
 * API endpoint for dashboard view (mix of assigned issues and pull requests).
 *
 * Pull request enrichment is queue-backed (see includes/pr-queue.php); the
 * dashboard simply shows the latest stored state on each poll.
 */

require_once 'api-common.php';

$token   = checkAuth();
$results = loadQueuedAssignedItems($token);

sendJsonResponse([
    'openPullRequestsDashboard' => $results['pullRequests'],
    'openIssuesDashboard'       => $results['issues'],
    'pending'                   => $results['pending'],
], time(), 'miss', 0);
