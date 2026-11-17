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
 *
 * Responses (JSON):
 *   200 {status: "drained", messages, pull_requests, duration_seconds, stopped: "idle"|"max_runtime"}
 *   200 {status: "already_running"} — another drain holds the lock
 *   401 / 405 / 500 on bad key, wrong method or a broker/configuration error
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/pr-queue.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

/**
 * Sends a JSON response and stops.
 */
function jobRespond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    jobRespond(405, ['error' => 'Method not allowed']);
}

$expectedKey = prQueueConfig()['jobsApiKey'];
$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');

if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
    logNotice('Rejected pull request drain job call with a missing or invalid API key', [], 'queue');
    jobRespond(401, ['error' => 'Unauthorized']);
}

// Finish the drain even if loop_requests gives up waiting.
ignore_user_abort(true);
set_time_limit(PR_QUEUE_DRAIN_MAX_SECONDS + 40);

// One drain at a time: SQLite is single-writer and the batches would race.
$lockPath = dirname(prQueueConfig()['sqlitePath']) . '/drain.lock';
if (!is_dir(dirname($lockPath))) {
    mkdir(dirname($lockPath), 0700, true);
}
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    jobRespond(200, ['status' => 'already_running']);
}

try {
    $result = prQueueDrain();
} catch (Throwable $e) {
    logError('Pull request drain job failed: {error}', ['error' => $e->getMessage()], 'queue');
    jobRespond(500, ['error' => 'Drain failed']);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

logInfo(
    'Drained {messages} message(s), {pull_requests} pull request(s) in {duration_seconds} s ({stopped})',
    $result,
    'queue'
);

jobRespond(200, ['status' => 'drained'] + $result);
