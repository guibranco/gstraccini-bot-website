<?php
/**
 * CLI worker: drains the pull request enrichment queue until it has been idle
 * for N seconds, then exits. Started by loop_requests.sh only when CloudAMQP
 * reports messages waiting.
 *
 * Usage: php process-pr-queue.php [idleSeconds=30]
 *
 * Uses basic_get + sleep instead of Pancake's Queue::consume(), whose
 * non-blocking wait loop spins the CPU while idle (bad on shared cPanel).
 * The queue is declared exactly like Queue::publish(..., false) does.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/pr-queue.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$idleSeconds = max(5, (int) ($argv[1] ?? 30));

$url = parse_url(prQueueConfig()['rabbitMqUrl']);
if (empty($url['host'])) {
    fwrite(STDERR, "rabbitMqUrl is not configured\n");
    exit(1);
}

$vhostPath = $url['path'] ?? '/';
$vhost     = ($vhostPath === '/' || $vhostPath === '') ? '/' : rawurldecode(substr($vhostPath, 1));

$connection = new AMQPStreamConnection(
    $url['host'],
    $url['port'] ?? 5672,
    rawurldecode($url['user'] ?? ''),
    rawurldecode($url['pass'] ?? ''),
    $vhost,
    false,
    'AMQPLAIN',
    null,
    'en_US',
    10.0,
    30.0
);
$channel = $connection->channel();
$channel->queue_declare(PR_QUEUE_NAME, false, true, false, false);

$started   = time();
$lastSeen  = time();
$messages  = 0;
$processed = 0;

while (time() - $lastSeen < $idleSeconds) {
    $message = $channel->basic_get(PR_QUEUE_NAME);

    if ($message === null) {
        sleep(1);
        continue;
    }

    try {
        $processed += prQueueHandleMessage($message->getBody());
    } catch (Throwable $e) {
        // Per-item failures are handled inside; this is a store/crypto problem.
        // Ack anyway: in-flight rows time out and are re-queued by the next poll.
        logError('Pull request batch failed: {error}', ['error' => $e->getMessage()], 'queue');
    }

    $message->ack();
    $messages++;
    $lastSeen = time();
}

$channel->close();
$connection->close();

$summary = sprintf(
    "[%s] processed %d message(s), %d pull request(s) in %ds\n",
    date('c'),
    $messages,
    $processed,
    time() - $started
);
echo $summary;
logInfo(trim($summary), [], 'queue');
