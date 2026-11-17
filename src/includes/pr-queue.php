<?php
/**
 * Queue-backed pull request enrichment.
 *
 * Short-term measure to stop the website from draining the GitHub API quota
 * while the portal + API replacement is being built.
 *
 * Flow:
 *   1. The web request fetches the assigned issues list (one GitHub call,
 *      session-cached) and merges in whatever enrichment (CI state and
 *      mergeability) is already stored in the local SQLite file.
 *   2. Pull requests with no result, or a stale one, are marked as "queued" in
 *      SQLite and published to CloudAMQP as ONE batch message per request.
 *   3. loop_requests.sh on vinhedo3 (Toolshed) polls the queue depth through
 *      the CloudAMQP management API and, when messages are waiting, calls
 *      POST /api/v1/jobs/pull-requests. That job drains the queue here on the
 *      website host until it has been idle for a while, calling GitHub and
 *      writing each result to SQLite as soon as it has it.
 *   4. The browser listens on /api/v1/pull-requests/stream (SSE), which reads
 *      new SQLite rows and pushes them to the page one by one.
 *
 * Results are keyed per pull request (owner/repo#number), so polls and users
 * that see the same pull request share a single GitHub lookup.
 */

require_once __DIR__ . '/github-api.php';
require_once __DIR__ . '/log-stream.php';

use GuiBranco\Pancake\Queue\Queue;
use GuiBranco\Pancake\Queue\QueueOptions;

/**
 * Configuration or broker problems of the pull request queue.
 */
class PrQueueException extends RuntimeException
{
}

const PR_QUEUE_NAME            = 'gstraccini-website-pr-enrichment';
const PR_QUEUE_MESSAGE_VERSION = 1;
const PR_QUEUE_SESSION_KEYS    = 'pr-stream-keys';

/** Max pull requests published per web request; the rest go on later polls. */
const PR_QUEUE_MAX_BATCH = 40;
/** Seconds after which a "queued" pull request is assumed lost and re-queued. */
const PR_QUEUE_INFLIGHT_TIMEOUT = 600;
/** Messages older than this are discarded by the drain job (token may be stale). */
const PR_QUEUE_MESSAGE_MAX_AGE = 900;
/** Freshness of a result whose CI state and mergeability are final. */
const PR_QUEUE_TTL_SETTLED = 1800;
/** Freshness of a result with CI pending or mergeability still being computed. */
const PR_QUEUE_TTL_UNSETTLED = 180;
/** Wait before retrying a pull request whose lookup failed. */
const PR_QUEUE_TTL_FAILED = 600;
/** The drain job stops calling GitHub for a user below this remaining quota. */
const PR_QUEUE_RATE_LIMIT_FLOOR = 1000;
/** Seconds the raw assigned-issues list is cached in the session. */
const PR_QUEUE_ISSUES_CACHE_TTL = 120;
/** The drain job stops after this many seconds without a message... */
const PR_QUEUE_DRAIN_IDLE_SECONDS = 10;
/** ...or after this many seconds in total (loop_requests calls it again). */
const PR_QUEUE_DRAIN_MAX_SECONDS = 50;

// ─── Configuration ────────────────────────────────────────────────────────────

/**
 * Loads queue settings from queue.secrets.php (generated at deploy time).
 *
 * Expected variables:
 *   $rabbitMqUrl        CloudAMQP URL (amqp://user:pass@host/vhost)
 *   $queueEncryptionKey base64 of 32 random bytes (encrypts tokens in messages)
 *   $jobsApiKey         X-Api-Key loop_requests (vinhedo3) sends to the job
 *   $queueSqlitePath    optional path to the SQLite file
 *
 * @return array{rabbitMqUrl: string, encryptionKey: string, jobsApiKey: string, sqlitePath: string}
 */
function prQueueConfig(?array $override = null, bool $reload = false): array
{
    static $config = null;

    if ($override !== null || $reload) {
        $config = $override;
    }

    if ($config !== null) {
        return $config;
    }

    $rabbitMqUrl        = '';
    $queueEncryptionKey = '';
    $jobsApiKey         = '';
    $queueSqlitePath    = '';

    $secretsFile = __DIR__ . '/../queue.secrets.php';
    if (file_exists($secretsFile)) {
        require_once $secretsFile;
    }

    $config = [
        'rabbitMqUrl'   => (string) $rabbitMqUrl,
        'encryptionKey' => (string) $queueEncryptionKey,
        'jobsApiKey'    => (string) $jobsApiKey,
        'sqlitePath'    => $queueSqlitePath !== ''
            ? (string) $queueSqlitePath
            : __DIR__ . '/../data/pr-queue.sqlite',
    ];

    return $config;
}

// ─── SQLite store ─────────────────────────────────────────────────────────────

/**
 * Returns the shared SQLite connection, creating the schema on first use.
 *
 * @param PDO|null $override Inject a connection (tests use sqlite::memory:).
 * @param bool     $reset    Forget the current connection (tests).
 */
function prQueueDb(?PDO $override = null, bool $reset = false): PDO
{
    static $pdo = null;

    if ($reset) {
        $pdo = null;
    }

    if ($override !== null) {
        $pdo = $override;
        prQueueEnsureSchema($pdo);
        return $pdo;
    }

    if ($pdo !== null) {
        return $pdo;
    }

    $pdo = prQueueOpenDb(prQueueConfig()['sqlitePath']);

    return $pdo;
}

/**
 * Opens (creating if needed) the SQLite file in WAL mode with the schema.
 */
function prQueueOpenDb(string $path): PDO
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    prQueueEnsureSchema($pdo);

    return $pdo;
}

function prQueueEnsureSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS pr_status (
            pr_key           TEXT PRIMARY KEY,
            status           TEXT    NOT NULL,
            issue_updated_at TEXT,
            data             TEXT,
            queued_at        INTEGER NOT NULL DEFAULT 0,
            processed_at     INTEGER NOT NULL DEFAULT 0,
            seq              INTEGER NOT NULL DEFAULT 0
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pr_status_seq ON pr_status (seq)');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            login      TEXT PRIMARY KEY,
            remaining  INTEGER,
            reset_at   INTEGER,
            updated_at INTEGER NOT NULL
        )'
    );
}

/**
 * Loads stored rows for the given keys.
 *
 * @param string[] $keys
 * @return array<string, array> Rows indexed by pr_key.
 */
function prQueueLoadRows(array $keys): array
{
    $rows = [];

    foreach (array_chunk(array_values(array_unique($keys)), 400) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = prQueueDb()->prepare("SELECT * FROM pr_status WHERE pr_key IN ($placeholders)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['pr_key']] = $row;
        }
    }

    return $rows;
}

/**
 * Marks pull requests as queued without touching any previous result, so the
 * page keeps showing the last known state while the refresh is in flight.
 *
 * @param string[] $keys
 */
function prQueueMarkQueued(array $keys, int $now): void
{
    $pdo  = prQueueDb();
    $stmt = $pdo->prepare(
        "INSERT INTO pr_status (pr_key, status, queued_at) VALUES (?, 'queued', ?)
         ON CONFLICT (pr_key) DO UPDATE SET status = 'queued', queued_at = excluded.queued_at"
    );

    $pdo->beginTransaction();
    foreach ($keys as $key) {
        $stmt->execute([$key, $now]);
    }
    $pdo->commit();
}

/**
 * Releases queued pull requests that will not be processed (publish failed,
 * message expired, rate limit reached) so the next poll re-queues them.
 *
 * @param string[] $keys
 */
function prQueueRelease(array $keys): void
{
    if (empty($keys)) {
        return;
    }

    $pdo  = prQueueDb();
    $stmt = $pdo->prepare(
        "UPDATE pr_status
            SET status = CASE WHEN data IS NULL THEN 'released' ELSE 'done' END,
                processed_at = CASE WHEN data IS NULL THEN 0 ELSE processed_at END,
                seq = (SELECT IFNULL(MAX(seq), 0) + 1 FROM pr_status)
          WHERE pr_key = ? AND status = 'queued'"
    );

    foreach ($keys as $key) {
        $stmt->execute([$key]);
    }
}

/**
 * Stores the outcome of a pull request lookup and bumps its sequence number so
 * the SSE stream picks it up.
 *
 * @param string     $status "done" or "failed".
 * @param array|null $data   Enrichment fields (state, mergeable, ...).
 */
function prQueueSaveResult(string $key, string $status, ?array $data, ?string $issueUpdatedAt, int $now): void
{
    $stmt = prQueueDb()->prepare(
        'INSERT INTO pr_status (pr_key, status, issue_updated_at, data, processed_at, seq)
         VALUES (:key, :status, :updated, :data, :now, (SELECT IFNULL(MAX(seq), 0) + 1 FROM pr_status))
         ON CONFLICT (pr_key) DO UPDATE SET
            status           = excluded.status,
            issue_updated_at = excluded.issue_updated_at,
            data             = COALESCE(excluded.data, pr_status.data),
            processed_at     = excluded.processed_at,
            seq              = excluded.seq'
    );

    $stmt->execute([
        ':key'     => $key,
        ':status'  => $status,
        ':updated' => $issueUpdatedAt,
        ':data'    => $data === null ? null : json_encode($data),
        ':now'     => $now,
    ]);
}

function prQueueMaxSeq(): int
{
    return (int) prQueueDb()->query('SELECT IFNULL(MAX(seq), 0) FROM pr_status')->fetchColumn();
}

/**
 * Returns rows changed after $since that belong to $keys, the highest sequence
 * seen, and how many of $keys are still queued.
 *
 * @param string[] $keys
 * @return array{rows: list<array>, maxSeq: int, pending: int}
 */
function prQueueFetchUpdates(array $keys, int $since): array
{
    $wanted = array_flip($keys);
    $stmt   = prQueueDb()->prepare('SELECT * FROM pr_status WHERE seq > ? ORDER BY seq ASC LIMIT 500');
    $stmt->execute([$since]);

    $rows   = [];
    $maxSeq = $since;
    foreach ($stmt->fetchAll() as $row) {
        $maxSeq = max($maxSeq, (int) $row['seq']);
        if (isset($wanted[$row['pr_key']])) {
            $rows[] = $row;
        }
    }

    $pending = 0;
    foreach (prQueueLoadRows($keys) as $row) {
        if ($row['status'] === 'queued') {
            $pending++;
        }
    }

    return ['rows' => $rows, 'maxSeq' => $maxSeq, 'pending' => $pending];
}

function prQueueSaveRateLimit(string $login, ?int $remaining, ?int $resetAt, int $now): void
{
    $stmt = prQueueDb()->prepare(
        'INSERT INTO rate_limits (login, remaining, reset_at, updated_at) VALUES (?, ?, ?, ?)
         ON CONFLICT (login) DO UPDATE SET
            remaining = excluded.remaining, reset_at = excluded.reset_at, updated_at = excluded.updated_at'
    );
    $stmt->execute([$login, $remaining, $resetAt, $now]);
}

/**
 * True when the user's last known quota is below the floor and has not reset yet.
 */
function prQueueIsRateLimited(string $login, int $now): bool
{
    $stmt = prQueueDb()->prepare('SELECT remaining, reset_at FROM rate_limits WHERE login = ?');
    $stmt->execute([$login]);
    $row = $stmt->fetch();

    if ($row === false || $row['remaining'] === null) {
        return false;
    }

    return (int) $row['remaining'] < PR_QUEUE_RATE_LIMIT_FLOOR
        && (int) ($row['reset_at'] ?? 0) > $now;
}

// ─── Freshness rules ──────────────────────────────────────────────────────────

/**
 * A result is settled when CI finished and GitHub computed mergeability.
 */
function prQueueIsSettled(?array $data): bool
{
    if ($data === null) {
        return false;
    }

    return in_array(($data['state'] ?? null), ['success', 'failure', 'error'], true)
        && ($data['mergeable'] ?? null) !== null;
}

/**
 * Decides whether a pull request needs a new GitHub lookup.
 */
function prQueueNeedsRefresh(?array $row, ?string $issueUpdatedAt, int $now): bool
{
    if ($row === null) {
        return true;
    }

    $data = $row['data'] !== null ? json_decode($row['data'], true) : null;

    switch ($row['status']) {
        case 'queued':
            return (int) $row['queued_at'] < ($now - PR_QUEUE_INFLIGHT_TIMEOUT);
        case 'done':
            if ($issueUpdatedAt !== null && $row['issue_updated_at'] !== $issueUpdatedAt) {
                return true;
            }
            $ttl = prQueueIsSettled($data) ? PR_QUEUE_TTL_SETTLED : PR_QUEUE_TTL_UNSETTLED;
            return (int) $row['processed_at'] < ($now - $ttl);
        case 'failed':
            return (int) $row['processed_at'] < ($now - PR_QUEUE_TTL_FAILED);
        default:
            return true;
    }
}

// ─── Token encryption ─────────────────────────────────────────────────────────

/**
 * Encrypts the user's GitHub token before it leaves the server. Only this host
 * (the website host) holds the key, so CloudAMQP never sees a usable token.
 */
function prQueueEncryptToken(string $token): string
{
    $key   = prQueueEncryptionKey();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    return base64_encode($nonce . sodium_crypto_secretbox($token, $nonce, $key));
}

function prQueueDecryptToken(string $payload): ?string
{
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }

    $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain  = sodium_crypto_secretbox_open($cipher, $nonce, prQueueEncryptionKey());

    return $plain === false ? null : $plain;
}

function prQueueEncryptionKey(): string
{
    $key = base64_decode(prQueueConfig()['encryptionKey'], true);

    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new PrQueueException('queueEncryptionKey must be base64 of 32 bytes');
    }

    return $key;
}

// ─── Publishing ───────────────────────────────────────────────────────────────

/**
 * Publishes one message to CloudAMQP (plain durable queue, no DLX, so the
 * drain job can declare it identically).
 */
function prQueuePublish(array $message, ?callable $queueFactory = null): void
{
    $url = prQueueConfig()['rabbitMqUrl'];
    if ($url === '') {
        throw new PrQueueException('rabbitMqUrl is not configured');
    }

    $queueFactory ??= static fn (string $broker) => new Queue([$broker], new QueueOptions(5.0, 10.0));
    $queueFactory($url)->publish(PR_QUEUE_NAME, json_encode($message), false);
}

// ─── Web-request side ─────────────────────────────────────────────────────────

/**
 * Keeps only the fields the website needs from a GitHub issue, so the raw list
 * can be cached in the session without the bodies.
 */
function prQueueSlimIssue(array $issue): array
{
    $slim = [
        'number'     => $issue['number'] ?? null,
        'title'      => $issue['title'] ?? '',
        'html_url'   => $issue['html_url'] ?? '',
        'created_at' => $issue['created_at'] ?? null,
        'updated_at' => $issue['updated_at'] ?? null,
        'labels'     => array_map(static fn ($label) => [
            'color'       => $label['color'] ?? null,
            'description' => $label['description'] ?? null,
            'name'        => $label['name'] ?? null,
        ], ($issue['labels'] ?? [])),
        'repository' => [
            'id'        => $issue['repository']['id'] ?? null,
            'name'      => $issue['repository']['name'] ?? '',
            'full_name' => $issue['repository']['full_name'] ?? '',
            'owner'     => ['login' => $issue['repository']['owner']['login'] ?? ''],
        ],
    ];

    if (isset($issue['pull_request']['url'])) {
        $slim['pull_request'] = ['url' => $issue['pull_request']['url']];
    }

    return $slim;
}

function prQueueKey(array $issue): string
{
    return ($issue['repository']['full_name'] ?? '') . '#' . ($issue['number'] ?? '');
}

/**
 * Applies stored enrichment fields to a formatted pull request.
 */
function prQueueApplyData(array $item, ?array $row): array
{
    $data = ($row !== null && $row['data'] !== null) ? json_decode($row['data'], true) : null;

    if (is_array($data)) {
        foreach (['state', 'mergeable', 'mergeable_state', 'is_valid_pr'] as $field) {
            if (array_key_exists($field, $data)) {
                $item[$field] = $data[$field];
            }
        }
    }

    return $item;
}

/**
 * Picks the pull requests that need a fresh lookup: never-seen first, then the
 * oldest results, capped at PR_QUEUE_MAX_BATCH.
 *
 * @param array<string, array> $prIssues Slim issues keyed by pr_key.
 * @param array<string, array> $rows     Stored rows keyed by pr_key.
 * @return array{batch: list<string>, candidates: int}
 */
function prQueueSelectBatch(array $prIssues, array $rows, string $login, int $now): array
{
    $candidates = [];
    foreach ($prIssues as $key => $issue) {
        $row = $rows[$key] ?? null;
        if (prQueueNeedsRefresh($row, $issue['updated_at'] ?? null, $now)) {
            $candidates[$key] = ($row === null || $row['data'] === null) ? 0 : (int) $row['processed_at'];
        }
    }
    asort($candidates);

    if (empty($candidates) || prQueueIsRateLimited($login, $now)) {
        return ['batch' => [], 'candidates' => count($candidates)];
    }

    return [
        'batch'      => array_slice(array_keys($candidates), 0, PR_QUEUE_MAX_BATCH),
        'candidates' => count($candidates),
    ];
}

/**
 * Marks the batch as queued and publishes it as one message. On failure the
 * pull requests are released so the next poll tries again.
 *
 * @param list<string>         $batch
 * @param array<string, array> $prIssues Slim issues keyed by pr_key.
 */
function prQueuePublishBatch(array $batch, array $prIssues, string $token, string $login, callable $publisher, int $now): void
{
    prQueueMarkQueued($batch, $now);

    $message = [
        'v'          => PR_QUEUE_MESSAGE_VERSION,
        'login'      => $login,
        'created_at' => $now,
        'token'      => prQueueEncryptToken($token),
        'items'      => array_map(static fn ($key) => [
            'key'        => $key,
            'url'        => $prIssues[$key]['pull_request']['url'],
            'html_url'   => $prIssues[$key]['html_url'] ?? '',
            'updated_at' => $prIssues[$key]['updated_at'] ?? null,
        ], $batch),
    ];

    try {
        $publisher($message);
        logInfo(
            'Queued {count} pull request(s) for enrichment',
            ['count' => count($batch), 'login' => $login],
            'queue'
        );
    } catch (Throwable $e) {
        prQueueRelease($batch);
        logError('Failed to publish pull request batch: {error}', ['error' => $e->getMessage()], 'queue');
    }
}

/**
 * Formats one pull request for the page, with its stored enrichment and a
 * "queued"/"skipped" placeholder state when there is none yet.
 */
function prQueueBuildItem(string $key, array $issue, ?array $row): array
{
    $item = formatIssueData($issue);
    $item['pr_key'] = $key;
    $item = prQueueApplyData($item, $row);

    $queued = ($row['status'] ?? null) === 'queued';
    if ($queued) {
        $item['refreshing'] = true;
    }
    $item['state'] ??= $queued ? 'queued' : 'skipped';

    return $item;
}

/**
 * Splits the assigned items into pull requests and issues, merges stored
 * enrichment, and queues the pull requests that need a fresh lookup.
 *
 * @param array         $issues    Slim issues (see prQueueSlimIssue()).
 * @param string        $token     The user's GitHub token.
 * @param string        $login     The user's GitHub login.
 * @param callable|null $publisher Override for prQueuePublish() (tests).
 * @return array{pullRequests: list<array>, issues: list<array>, pending: int, keys: list<string>, since: int}
 */
function buildQueuedPullRequests(array $issues, string $token, string $login, ?callable $publisher = null, ?int $now = null): array
{
    $now ??= time();

    $openIssues = [];
    $prIssues   = [];
    foreach ($issues as $issue) {
        if (isset($issue['pull_request'])) {
            $prIssues[prQueueKey($issue)] = $issue;
        } else {
            $openIssues[] = formatIssueData($issue);
        }
    }

    $keys      = array_keys($prIssues);
    $since     = prQueueMaxSeq();
    $selection = prQueueSelectBatch($prIssues, prQueueLoadRows($keys), $login, $now);

    if (!empty($selection['batch'])) {
        prQueuePublishBatch($selection['batch'], $prIssues, $token, $login, $publisher ?? 'prQueuePublish', $now);
    }

    $rows         = prQueueLoadRows($keys);
    $pullRequests = [];
    foreach ($prIssues as $key => $issue) {
        $pullRequests[] = prQueueBuildItem($key, $issue, $rows[$key] ?? null);
    }

    return [
        'pullRequests' => $pullRequests,
        'issues'       => $openIssues,
        'pending'      => count(array_filter($pullRequests, static fn ($pr) => !empty($pr['refreshing']))),
        'keys'         => $keys,
        'since'        => $since,
    ];
}

// ─── SSE stream ───────────────────────────────────────────────────────────────

/**
 * Formats one Server-Sent Events frame.
 */
function prQueueSseFrame(string $event, array $payload, ?int $id = null): string
{
    $frame = $id !== null ? 'id: ' . $id . "\n" : '';

    return $frame . 'event: ' . $event . "\n" . 'data: ' . json_encode($payload) . "\n\n";
}

/**
 * One poll of the SSE stream: the events to send for rows changed after
 * $since, the new cursor, and whether nothing is left in flight.
 *
 * @param string[] $keys The user's pull request keys.
 * @return array{events: list<array{id: int, payload: array}>, since: int, done: bool}
 */
function prQueueStreamPoll(array $keys, int $since): array
{
    if (empty($keys)) {
        return ['events' => [], 'since' => $since, 'done' => true];
    }

    $update = prQueueFetchUpdates($keys, $since);
    $events = [];
    foreach ($update['rows'] as $row) {
        $item = prQueueApplyData(['pr_key' => $row['pr_key']], $row);
        $item['refreshing'] = $row['status'] === 'queued';
        $item['state'] ??= $item['refreshing'] ? 'queued' : 'skipped';
        $events[] = ['id' => (int) $row['seq'], 'payload' => $item];
    }

    return ['events' => $events, 'since' => $update['maxSeq'], 'done' => $update['pending'] === 0];
}

// ─── Drain job (triggered by loop_requests on vinhedo3) ───────────────────────

/**
 * Processes one batch message: looks each pull request up on GitHub and stores
 * the result immediately so the SSE stream can push it to the browser.
 *
 * Never throws for per-item problems: failures are recorded and retried by a
 * later poll once PR_QUEUE_TTL_FAILED has passed.
 *
 * @return int Number of pull requests processed.
 */
function prQueueHandleMessage(string $body, ?callable $fetch = null, ?int $now = null): int
{
    $fetch   ??= 'loadData';
    $message = json_decode($body, true);

    if (!is_array($message) || ($message['v'] ?? null) !== PR_QUEUE_MESSAGE_VERSION || !is_array($message['items'] ?? null)) {
        logWarning('Discarding malformed pull request queue message', [], 'queue');
        return 0;
    }

    $keys  = array_column($message['items'], 'key');
    $login = (string) ($message['login'] ?? 'unknown');

    if ((int) ($message['created_at'] ?? 0) < (($now ?? time()) - PR_QUEUE_MESSAGE_MAX_AGE)) {
        prQueueRelease($keys);
        logNotice('Discarding expired batch of {count} pull request(s)', ['count' => count($keys)], 'queue');
        return 0;
    }

    $token = prQueueDecryptToken((string) ($message['token'] ?? ''));
    if ($token === null) {
        prQueueRelease($keys);
        logError('Could not decrypt the token of a pull request batch', ['login' => $login], 'queue');
        return 0;
    }

    $processed = 0;
    foreach ($message['items'] as $index => $item) {
        $time = $now ?? time();

        if (prQueueIsRateLimited($login, $time)) {
            prQueueRelease(array_slice($keys, $index));
            logNotice('Rate limit floor reached for {login}; released {count} pull request(s)', [
                'login' => $login,
                'count' => count($keys) - $index,
            ], 'queue');
            break;
        }

        prQueueProcessItem($item, $token, $login, $fetch, $time);
        $processed++;
    }

    return $processed;
}

/**
 * Looks one pull request up on GitHub (PR + commit status) and stores the
 * outcome: "done" with the enrichment fields, or "failed" to retry later.
 *
 * @param array{key: string, url: string, html_url?: string, updated_at?: string|null} $item
 */
function prQueueProcessItem(array $item, string $token, string $login, callable $fetch, int $time): void
{
    $pullRequest = $fetch($item['url'], $token);
    prQueueTrackRateLimit($login, $time);

    if ($pullRequest === null || !isset($pullRequest['body']) || !is_array($pullRequest['body'])) {
        prQueueSaveResult($item['key'], 'failed', null, $item['updated_at'] ?? null, $time);
        return;
    }

    $enriched = enrichPullRequestData(['url' => $item['html_url'] ?? $item['url']], $pullRequest, $token, $fetch);
    prQueueTrackRateLimit($login, $time);

    prQueueSaveResult($item['key'], 'done', [
        'state'           => $enriched['state'] ?? null,
        'mergeable'       => $enriched['mergeable'] ?? null,
        'mergeable_state' => $enriched['mergeable_state'] ?? null,
        'is_valid_pr'     => $enriched['is_valid_pr'] ?? false,
    ], $item['updated_at'] ?? null, $time);
}

function prQueueTrackRateLimit(string $login, int $now): void
{
    $limit = getLastGitHubRateLimit();
    if ($limit !== null && $limit['remaining'] !== null) {
        prQueueSaveRateLimit($login, $limit['remaining'], $limit['reset'], $now);
    }
}

/**
 * Pulls messages one at a time until the queue has been idle for
 * $idleSeconds or $maxSeconds have passed, handing each body to $handle and
 * acknowledging it afterwards (failures are recorded per item, so a message
 * is never redelivered in a loop).
 *
 * @param callable $next   Returns an object with getBody()/ack(), or null when the queue is empty.
 * @param callable $handle Receives the body, returns the number of pull requests processed.
 * @param callable|null $clock Returns the current time (tests).
 * @param callable|null $sleep Waits one poll interval (tests).
 * @return array{messages: int, pull_requests: int, duration_seconds: int, stopped: string}
 */
function prQueueDrainLoop(
    callable $next,
    callable $handle,
    int $idleSeconds,
    int $maxSeconds,
    ?callable $clock = null,
    ?callable $sleep = null
): array {
    $clock ??= 'time';
    $sleep ??= static fn () => usleep(500_000);

    $started  = $clock();
    $lastSeen = $started;
    $messages = 0;
    $prs      = 0;
    $stopped  = 'idle';

    while (true) {
        $now = $clock();
        if ($now - $started >= $maxSeconds) {
            $stopped = 'max_runtime';
            break;
        }
        if ($now - $lastSeen >= $idleSeconds) {
            break;
        }

        $message = $next();
        if ($message === null) {
            $sleep();
            continue;
        }

        try {
            $prs += $handle($message->getBody());
        } catch (Throwable $e) {
            // Store/crypto problem: in-flight rows time out and are re-queued by a later poll.
            logError('Pull request batch failed: {error}', ['error' => $e->getMessage()], 'queue');
        }

        $message->ack();
        $messages++;
        $lastSeen = $clock();
    }

    return [
        'messages'         => $messages,
        'pull_requests'    => $prs,
        'duration_seconds' => $clock() - $started,
        'stopped'          => $stopped,
    ];
}

/**
 * Splits an amqp:// URL into connection parameters (credentials and vhost
 * URL-decoded; default port 5672 and vhost "/").
 *
 * @return array{host: string, port: int, user: string, password: string, vhost: string}
 */
function prQueueParseAmqpUrl(string $amqpUrl): array
{
    $url = parse_url($amqpUrl);
    if ($url === false || empty($url['host'])) {
        throw new PrQueueException('rabbitMqUrl is not configured');
    }

    $vhostPath = $url['path'] ?? '/';

    return [
        'host'     => $url['host'],
        'port'     => (int) ($url['port'] ?? 5672),
        'user'     => rawurldecode($url['user'] ?? ''),
        'password' => rawurldecode($url['pass'] ?? ''),
        'vhost'    => ($vhostPath === '/' || $vhostPath === '') ? '/' : rawurldecode(substr($vhostPath, 1)),
    ];
}

/**
 * Connects to CloudAMQP and drains the pull request queue (see prQueueDrainLoop()).
 *
 * Uses basic_get + a short sleep rather than Pancake's Queue::consume(), whose
 * non-blocking wait loop spins the CPU while idle (bad on shared hosting).
 * The queue is declared exactly like Queue::publish(..., false) does.
 */
function prQueueDrain(
    int $idleSeconds = PR_QUEUE_DRAIN_IDLE_SECONDS,
    int $maxSeconds = PR_QUEUE_DRAIN_MAX_SECONDS,
    ?callable $connect = null
): array {
    $server     = prQueueParseAmqpUrl(prQueueConfig()['rabbitMqUrl']);
    $connection = ($connect ?? 'prQueueConnect')($server);

    try {
        $channel = $connection->channel();
        $channel->queue_declare(PR_QUEUE_NAME, false, true, false, false);

        $result = prQueueDrainLoop(
            static fn () => $channel->basic_get(PR_QUEUE_NAME),
            'prQueueHandleMessage',
            $idleSeconds,
            $maxSeconds
        );

        $channel->close();
    } finally {
        $connection->close();
    }

    return $result;
}

/**
 * Opens the AMQP connection used by prQueueDrain().
 *
 * @param array{host: string, port: int, user: string, password: string, vhost: string} $server
 */
function prQueueConnect(array $server): \PhpAmqpLib\Connection\AMQPStreamConnection
{
    return new \PhpAmqpLib\Connection\AMQPStreamConnection(
        $server['host'],
        $server['port'],
        $server['user'],
        $server['password'],
        $server['vhost'],
        false,
        'AMQPLAIN',
        null,
        'en_US',
        10.0,
        30.0
    );
}

/**
 * Handles POST /api/v1/jobs/pull-requests: method and X-Api-Key checks, a
 * single-drain lock, then the drain itself.
 *
 * @param callable $drain Returns the prQueueDrain() result.
 * @return array{0: int, 1: array} HTTP status and JSON payload.
 */
function prQueueHandleDrainRequest(string $method, string $providedKey, string $lockPath, callable $drain): array
{
    if ($method !== 'POST') {
        return [405, ['error' => 'Method not allowed']];
    }

    $expectedKey = prQueueConfig()['jobsApiKey'];
    if ($expectedKey === '' || !hash_equals($expectedKey, $providedKey)) {
        logNotice('Rejected pull request drain job call with a missing or invalid API key', [], 'queue');
        return [401, ['error' => 'Unauthorized']];
    }

    if (!is_dir(dirname($lockPath))) {
        mkdir(dirname($lockPath), 0700, true);
    }

    // One drain at a time: SQLite is single-writer and the batches would race.
    $lock = fopen($lockPath, 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return [200, ['status' => 'already_running']];
    }

    try {
        $result = $drain();
    } catch (Throwable $e) {
        logError('Pull request drain job failed: {error}', ['error' => $e->getMessage()], 'queue');
        return [500, ['error' => 'Drain failed']];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    logInfo(
        'Drained {messages} message(s), {pull_requests} pull request(s) in {duration_seconds} s ({stopped})',
        $result,
        'queue'
    );

    return [200, ['status' => 'drained'] + $result];
}
