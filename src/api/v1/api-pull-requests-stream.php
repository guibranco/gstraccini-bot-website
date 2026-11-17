<?php
/**
 * Server-Sent Events stream of pull request enrichment results.
 *
 * Reads the local SQLite store (no GitHub or CloudAMQP calls) and pushes each
 * result for the current user's pull requests as soon as the drain job writes it.
 * Each connection lives at most STREAM_LIFETIME seconds so it does not hold a
 * PHP worker for long; EventSource reconnects with Last-Event-ID and resumes.
 *
 * Events:
 *   pr   – {pr_key, state, mergeable, mergeable_state, is_valid_pr, refreshing}
 *   done – nothing left in flight for this user; the client closes the stream.
 */

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/github-api.php';
require_once __DIR__ . '/../../includes/pr-queue.php';

const STREAM_LIFETIME      = 25;
const STREAM_POLL_INTERVAL = 1;
const STREAM_HEARTBEAT     = 10;

checkAuth();

$keys  = $_SESSION[PR_QUEUE_SESSION_KEYS] ?? [];
$since = max((int) ($_GET['since'] ?? 0), (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0));

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
while (ob_get_level() > 0) {
    ob_end_flush();
}

/**
 * Writes one SSE frame and flushes it to the client.
 */
function sendEvent(string $event, array $payload, ?int $id = null): void
{
    if ($id !== null) {
        echo 'id: ' . $id . "\n";
    }
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    flush();
}

echo "retry: 3000\n\n";
flush();

$deadline      = time() + STREAM_LIFETIME;
$lastHeartbeat = time();

while (time() < $deadline && !connection_aborted()) {
    $poll = prQueueStreamPoll($keys, $since);

    foreach ($poll['events'] as $event) {
        sendEvent('pr', $event['payload'], $event['id']);
    }
    $since = $poll['since'];

    if ($poll['done']) {
        sendEvent('done', [], $since);
        exit();
    }

    if (time() - $lastHeartbeat >= STREAM_HEARTBEAT) {
        echo ": ping\n\n";
        flush();
        $lastHeartbeat = time();
    }

    sleep(STREAM_POLL_INTERVAL);
}
