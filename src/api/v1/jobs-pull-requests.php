<?php
/**
 * POST /api/v1/jobs/pull-requests — drains the pull request enrichment queue.
 *
 * Called by loop_requests.sh on vinhedo3 (guibranco/Toolshed, "Domain C")
 * only when the CloudAMQP management API reports messages waiting. Runs here,
 * on the website host, because the results go to the local SQLite store that
 * the pages and the SSE stream read.
 *
 * Auth: X-Api-Key must match $jobsApiKey from queue.secrets.php.
 * Responses: see prQueueHandleDrainRequest().
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/pr-queue.php';

// Finish the drain even if loop_requests gives up waiting.
ignore_user_abort(true);
set_time_limit(PR_QUEUE_DRAIN_MAX_SECONDS + 40);

[$status, $payload] = prQueueHandleDrainRequest(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    (string) ($_SERVER['HTTP_X_API_KEY'] ?? ''),
    dirname(prQueueConfig()['sqlitePath']) . '/drain.lock',
    'prQueueDrain'
);

http_response_code($status);
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($status === 405) {
    header('Allow: POST');
}
echo json_encode($payload);
