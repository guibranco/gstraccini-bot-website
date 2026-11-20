<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/github-api.php';
require_once __DIR__ . '/../../src/includes/pr-queue.php';
require_once __DIR__ . '/../../src/api/v1/api-common.php';

class PrQueueTest extends TestCase
{
    private const NOW = 1_800_000_000;

    protected function setUp(): void
    {
        prQueueConfig([
            'rabbitMqUrl'   => 'amqp://localhost/vhost',
            'encryptionKey' => base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
            'jobsApiKey'    => 'job-key',
            'sqlitePath'    => ':memory:',
        ]);

        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        prQueueDb($pdo);
    }

    private function issue(int $number, bool $isPullRequest = true, string $updatedAt = '2026-11-01T00:00:00Z'): array
    {
        $issue = [
            'number'     => $number,
            'title'      => "Item $number",
            'html_url'   => "https://github.com/guibranco/repo/pull/$number",
            'created_at' => '2026-10-01T00:00:00Z',
            'updated_at' => $updatedAt,
            'labels'     => [],
            'repository' => [
                'id'        => 1,
                'name'      => 'repo',
                'full_name' => 'guibranco/repo',
                'owner'     => ['login' => 'guibranco'],
            ],
        ];

        if ($isPullRequest) {
            $issue['pull_request'] = ['url' => "https://api.github.com/repos/guibranco/repo/pulls/$number"];
        }

        return $issue;
    }

    public function testTokenRoundTripsThroughEncryption(): void
    {
        $encrypted = prQueueEncryptToken('gho_secret');

        $this->assertStringNotContainsString('gho_secret', $encrypted);
        $this->assertSame('gho_secret', prQueueDecryptToken($encrypted));
        $this->assertNull(prQueueDecryptToken('tampered'));
    }

    public function testFirstLoadQueuesAllPullRequestsInOneMessage(): void
    {
        $published = [];
        $result = buildQueuedPullRequests(
            [$this->issue(1), $this->issue(2), $this->issue(3, false)],
            'token',
            'guibranco',
            function (array $message) use (&$published) {
                $published[] = $message;
            },
            self::NOW
        );

        $this->assertCount(1, $published);
        $this->assertCount(2, $published[0]['items']);
        $this->assertSame(2, $result['pending']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('queued', $result['pullRequests'][0]['state']);
        $this->assertSame('guibranco/repo#1', $result['pullRequests'][0]['pr_key']);
    }

    public function testInFlightPullRequestsAreNotQueuedTwice(): void
    {
        $count = 0;
        $publisher = function () use (&$count) {
            $count++;
        };

        buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', $publisher, self::NOW);
        buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', $publisher, self::NOW + 60);

        $this->assertSame(1, $count);
    }

    public function testFreshResultIsServedFromStoreWithoutPublishing(): void
    {
        prQueueSaveResult('guibranco/repo#1', 'done', [
            'state' => 'success', 'mergeable' => true, 'mergeable_state' => 'clean', 'is_valid_pr' => true,
        ], '2026-11-01T00:00:00Z', self::NOW);

        $published = false;
        $result = buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', function () use (&$published) {
            $published = true;
        }, self::NOW + 60);

        $this->assertFalse($published);
        $this->assertSame(0, $result['pending']);
        $this->assertSame('success', $result['pullRequests'][0]['state']);
        $this->assertTrue($result['pullRequests'][0]['is_valid_pr']);
    }

    public function testUpdatedPullRequestIsRequeuedButKeepsLastKnownState(): void
    {
        prQueueSaveResult('guibranco/repo#1', 'done', [
            'state' => 'success', 'mergeable' => true, 'mergeable_state' => 'clean', 'is_valid_pr' => true,
        ], '2026-11-01T00:00:00Z', self::NOW);

        $published = false;
        $result = buildQueuedPullRequests(
            [$this->issue(1, true, '2026-11-02T00:00:00Z')],
            'token',
            'guibranco',
            function () use (&$published) {
                $published = true;
            },
            self::NOW + 60
        );

        $this->assertTrue($published);
        $this->assertSame(1, $result['pending']);
        $this->assertSame('success', $result['pullRequests'][0]['state']);
        $this->assertTrue($result['pullRequests'][0]['refreshing']);
    }

    public function testUnsettledResultExpiresSoonerThanSettledOne(): void
    {
        $pending = ['status' => 'done', 'issue_updated_at' => 'x', 'processed_at' => self::NOW,
            'data' => json_encode(['state' => 'pending', 'mergeable' => null]), 'queued_at' => 0];
        $settled = ['status' => 'done', 'issue_updated_at' => 'x', 'processed_at' => self::NOW,
            'data' => json_encode(['state' => 'success', 'mergeable' => true]), 'queued_at' => 0];

        $later = self::NOW + PR_QUEUE_TTL_UNSETTLED + 1;

        $this->assertTrue(prQueueNeedsRefresh($pending, 'x', $later));
        $this->assertFalse(prQueueNeedsRefresh($settled, 'x', $later));
    }

    public function testActionsOnlyPendingIsSettled(): void
    {
        // Repositories whose CI only uses check runs report "pending" with no statuses forever.
        $actionsOnly = ['status' => 'done', 'issue_updated_at' => 'x', 'processed_at' => self::NOW,
            'data' => json_encode(['state' => 'pending', 'mergeable' => true, 'status_total' => 0]), 'queued_at' => 0];
        $running     = ['status' => 'done', 'issue_updated_at' => 'x', 'processed_at' => self::NOW,
            'data' => json_encode(['state' => 'pending', 'mergeable' => true, 'status_total' => 2]), 'queued_at' => 0];

        $later = self::NOW + PR_QUEUE_TTL_UNSETTLED + 1;

        $this->assertFalse(prQueueNeedsRefresh($actionsOnly, 'x', $later));
        $this->assertTrue(prQueueNeedsRefresh($actionsOnly, 'x', self::NOW + PR_QUEUE_TTL_SETTLED + 1));
        $this->assertTrue(prQueueNeedsRefresh($running, 'x', $later));
        $this->assertFalse(prQueueIsSettled(['state' => 'pending', 'mergeable' => null, 'status_total' => 0]));
        $this->assertFalse(prQueueIsSettled(['state' => 'pending', 'mergeable' => true]));
    }

    public function testEnrichmentKeepsTheCommitStatusCount(): void
    {
        $pullRequest = ['body' => ['mergeable' => true, 'mergeable_state' => 'clean',
            'head' => ['sha' => 'abc', 'repo' => ['url' => 'https://api.github.com/repos/guibranco/repo']]]];
        $loader = fn () => ['body' => ['state' => 'pending', 'total_count' => 0]];

        $result = enrichPullRequestData(['url' => 'x'], $pullRequest, 'token', $loader);

        $this->assertSame('pending', $result['state']);
        $this->assertSame(0, $result['status_total']);
    }

    public function testLowQuotaPageLoadQueuesNothing(): void
    {
        $published = 0;
        $results = loadQueuedAssignedItems(
            'token',
            ['user' => ['login' => 'guibranco']],
            function () {
                recordGitHubRateLimit("x-ratelimit-remaining: 10\r\nx-ratelimit-reset: " . (time() + 600) . "\r\n", 200);
                return [$this->issue(1)];
            },
            fn () => null,
            function () use (&$published) {
                $published++;
            }
        );

        $this->assertSame(0, $published);
        $this->assertSame(0, $results['pending']);
    }

    public function testPublishFailureReleasesPullRequests(): void
    {
        $result = buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', function () {
            throw new \RuntimeException('broker down');
        }, self::NOW);

        $this->assertSame(0, $result['pending']);
        $this->assertSame('skipped', $result['pullRequests'][0]['state']);
        $this->assertTrue(prQueueNeedsRefresh(prQueueLoadRows(['guibranco/repo#1'])['guibranco/repo#1'], null, self::NOW));
    }

    public function testBatchIsCappedPerRequest(): void
    {
        $issues = [];
        for ($i = 1; $i <= PR_QUEUE_MAX_BATCH + 5; $i++) {
            $issues[] = $this->issue($i);
        }

        $message = null;
        buildQueuedPullRequests($issues, 'token', 'guibranco', function (array $m) use (&$message) {
            $message = $m;
        }, self::NOW);

        $this->assertCount(PR_QUEUE_MAX_BATCH, $message['items']);
    }

    public function testRateLimitedUserIsNotQueued(): void
    {
        prQueueSaveRateLimit('guibranco', 10, self::NOW + 600, self::NOW);

        $published = false;
        buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', function () use (&$published) {
            $published = true;
        }, self::NOW);

        $this->assertFalse($published);
    }

    public function testWorkerStoresResultsAndStreamSeesThem(): void
    {
        $message = null;
        $result = buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', function (array $m) use (&$message) {
            $message = $m;
        }, self::NOW);

        $calls = [];
        $fetch = function (string $url, string $token) use (&$calls) {
            $calls[] = [$url, $token];
            if (str_ends_with($url, '/pulls/1')) {
                return ['headers' => '', 'body' => [
                    'mergeable' => true,
                    'mergeable_state' => 'clean',
                    'head' => ['sha' => 'abc', 'repo' => ['url' => 'https://api.github.com/repos/guibranco/repo']],
                ]];
            }
            if (str_ends_with($url, '/commits/abc/status')) {
                return ['headers' => '', 'body' => ['state' => 'success']];
            }
            return null;
        };

        $processed = prQueueHandleMessage(json_encode($message), $fetch, self::NOW + 5);

        $this->assertSame(1, $processed);
        $this->assertSame('token', $calls[0][1]);

        $update = prQueueFetchUpdates($result['keys'], $result['since']);
        $this->assertSame(0, $update['pending']);
        $this->assertCount(1, $update['rows']);
        $this->assertSame('done', $update['rows'][0]['status']);
        $data = json_decode($update['rows'][0]['data'], true);
        $this->assertTrue($data['mergeable']);
        $this->assertSame('success', $data['state']);
        $this->assertTrue($data['is_valid_pr']);
    }

    public function testWorkerDiscardsExpiredMessagesAndReleasesRows(): void
    {
        $message = null;
        buildQueuedPullRequests([$this->issue(1)], 'token', 'guibranco', function (array $m) use (&$message) {
            $message = $m;
        }, self::NOW);

        $processed = prQueueHandleMessage(json_encode($message), function () {
            $this->fail('GitHub must not be called for an expired message');
        }, self::NOW + PR_QUEUE_MESSAGE_MAX_AGE + 1);

        $this->assertSame(0, $processed);
        $this->assertSame('released', prQueueLoadRows(['guibranco/repo#1'])['guibranco/repo#1']['status']);
    }

    private function fakeMessage(string $body, int &$acks): object
    {
        return new class ($body, $acks) {
            private string $body;
            private $acks;

            public function __construct(string $body, int &$acks)
            {
                $this->body = $body;
                $this->acks = &$acks;
            }

            public function getBody(): string
            {
                return $this->body;
            }

            public function ack(): void
            {
                $this->acks++;
            }
        };
    }

    public function testDrainProcessesAndAcksUntilIdle(): void
    {
        $acks  = 0;
        $queue = [$this->fakeMessage('a', $acks), $this->fakeMessage('b', $acks)];
        $time  = self::NOW;

        $result = prQueueDrainLoop(
            function () use (&$queue) {
                return array_shift($queue);
            },
            fn () => 3,
            10,
            50,
            function () use (&$time) {
                return $time;
            },
            function () use (&$time) {
                $time++;
            }
        );

        $this->assertSame(2, $result['messages']);
        $this->assertSame(6, $result['pull_requests']);
        $this->assertSame(2, $acks);
        $this->assertSame('idle', $result['stopped']);
        $this->assertSame(10, $result['duration_seconds']);
    }

    public function testDrainStopsAtMaxRuntimeAndAcksFailedBatches(): void
    {
        $acks = 0;
        $time = self::NOW;

        $result = prQueueDrainLoop(
            function () use (&$acks, &$time) {
                $time += 2;
                return $this->fakeMessage('boom', $acks);
            },
            function () {
                throw new \RuntimeException('store unavailable');
            },
            10,
            20,
            function () use (&$time) {
                return $time;
            }
        );

        $this->assertSame('max_runtime', $result['stopped']);
        $this->assertSame($acks, $result['messages']);
        $this->assertSame(0, $result['pull_requests']);
    }

    public function testSlimIssueKeepsOnlyWhatThePagesNeed(): void
    {
        $issue = $this->issue(7) + ['body' => str_repeat('x', 5000), 'user' => ['login' => 'someone']];
        $issue['labels'] = [['name' => 'bug', 'color' => 'f00', 'description' => 'Bug', 'id' => 1]];

        $slim = prQueueSlimIssue($issue);

        $this->assertArrayNotHasKey('body', $slim);
        $this->assertArrayNotHasKey('user', $slim);
        $this->assertSame(['color' => 'f00', 'description' => 'Bug', 'name' => 'bug'], $slim['labels'][0]);
        $this->assertSame('guibranco/repo#7', prQueueKey($slim));
        $this->assertArrayNotHasKey('pull_request', prQueueSlimIssue($this->issue(8, false)));
    }

    public function testReleaseKeepsLastKnownResult(): void
    {
        prQueueSaveResult('guibranco/repo#1', 'done', ['state' => 'success', 'mergeable' => true], 'x', self::NOW);
        prQueueMarkQueued(['guibranco/repo#1'], self::NOW + 10);

        prQueueRelease(['guibranco/repo#1']);

        $row = prQueueLoadRows(['guibranco/repo#1'])['guibranco/repo#1'];
        $this->assertSame('done', $row['status']);
        $this->assertSame('success', json_decode($row['data'], true)['state']);
    }

    public function testFreshnessRulesForFailedAndUnknownStatuses(): void
    {
        $failed = ['status' => 'failed', 'processed_at' => self::NOW, 'data' => null, 'queued_at' => 0, 'issue_updated_at' => null];

        $this->assertFalse(prQueueNeedsRefresh($failed, null, self::NOW + 60));
        $this->assertTrue(prQueueNeedsRefresh($failed, null, self::NOW + PR_QUEUE_TTL_FAILED + 1));
        $this->assertTrue(prQueueNeedsRefresh(['status' => 'released', 'data' => null] + $failed, null, self::NOW));
        $this->assertFalse(prQueueIsSettled(null));
        $this->assertFalse(prQueueIsSettled(['state' => 'success', 'mergeable' => null]));
    }

    public function testRateLimitClearsAfterReset(): void
    {
        prQueueSaveRateLimit('guibranco', 10, self::NOW + 60, self::NOW);

        $this->assertTrue(prQueueIsRateLimited('guibranco', self::NOW));
        $this->assertFalse(prQueueIsRateLimited('guibranco', self::NOW + 61));
        $this->assertFalse(prQueueIsRateLimited('someone-else', self::NOW));
    }

    public function testInvalidEncryptionKeyIsRejected(): void
    {
        prQueueConfig(['rabbitMqUrl' => '', 'encryptionKey' => 'short', 'jobsApiKey' => '', 'sqlitePath' => ':memory:']);

        $this->expectException(\PrQueueException::class);
        prQueueEncryptToken('token');
    }

    public function testPublishWithoutBrokerConfigurationFails(): void
    {
        prQueueConfig(['rabbitMqUrl' => '', 'encryptionKey' => '', 'jobsApiKey' => '', 'sqlitePath' => ':memory:']);

        $this->expectException(\PrQueueException::class);
        prQueuePublish(['v' => 1]);
    }

    public function testStreamPollSendsUserRowsAndFinishesWhenNothingIsInFlight(): void
    {
        $result = buildQueuedPullRequests([$this->issue(1), $this->issue(2)], 'token', 'guibranco', fn () => null, self::NOW);
        prQueueSaveResult('guibranco/other#9', 'done', ['state' => 'success'], 'x', self::NOW);

        prQueueSaveResult('guibranco/repo#1', 'done', ['state' => 'failure', 'mergeable' => false], 'x', self::NOW);
        $first = prQueueStreamPoll($result['keys'], $result['since']);

        $this->assertCount(1, $first['events']);
        $this->assertSame('guibranco/repo#1', $first['events'][0]['payload']['pr_key']);
        $this->assertSame('failure', $first['events'][0]['payload']['state']);
        $this->assertFalse($first['events'][0]['payload']['refreshing']);
        $this->assertFalse($first['done']);

        prQueueSaveResult('guibranco/repo#2', 'failed', null, 'x', self::NOW);
        $second = prQueueStreamPoll($result['keys'], $first['since']);

        $this->assertSame('skipped', $second['events'][0]['payload']['state']);
        $this->assertTrue($second['done']);
    }

    public function testStreamPollWithoutKeysIsDone(): void
    {
        $this->assertTrue(prQueueStreamPoll([], 0)['done']);
    }

    public function testStreamDescriptorOnlyWhilePending(): void
    {
        $this->assertNull(buildStreamDescriptor(['pending' => 0, 'since' => 4]));
        $this->assertSame(
            ['url' => '/api/v1/pull-requests/stream', 'since' => 4],
            buildStreamDescriptor(['pending' => 2, 'since' => 4])
        );
    }

    private function lockPath(): string
    {
        return sys_get_temp_dir() . '/pr-queue-test-' . getmypid() . '/drain.lock';
    }

    public function testDrainRequestRejectsWrongMethodAndKey(): void
    {
        $drain = fn () => $this->fail('must not drain');

        $this->assertSame(405, prQueueHandleDrainRequest('GET', 'job-key', $this->lockPath(), $drain)[0]);
        $this->assertSame(401, prQueueHandleDrainRequest('POST', 'wrong', $this->lockPath(), $drain)[0]);
        $this->assertSame(401, prQueueHandleDrainRequest('POST', '', $this->lockPath(), $drain)[0]);
    }

    public function testDrainRequestRunsDrainAndReportsResult(): void
    {
        [$status, $payload] = prQueueHandleDrainRequest('POST', 'job-key', $this->lockPath(), fn () => [
            'messages' => 1, 'pull_requests' => 3, 'duration_seconds' => 2, 'stopped' => 'idle',
        ]);

        $this->assertSame(200, $status);
        $this->assertSame('drained', $payload['status']);
        $this->assertSame(3, $payload['pull_requests']);
    }

    public function testDrainRequestReportsFailuresAndConcurrentRuns(): void
    {
        $failed = prQueueHandleDrainRequest('POST', 'job-key', $this->lockPath(), function () {
            throw new \PrQueueException('broker down');
        });
        $this->assertSame([500, ['error' => 'Drain failed']], $failed);

        $held = fopen($this->lockPath(), 'c');
        flock($held, LOCK_EX);
        try {
            $busy = prQueueHandleDrainRequest('POST', 'job-key', $this->lockPath(), fn () => $this->fail('must not drain'));
        } finally {
            flock($held, LOCK_UN);
            fclose($held);
        }
        $this->assertSame([200, ['status' => 'already_running']], $busy);
    }

    public function testAmqpUrlIsParsedWithDefaultsAndDecoding(): void
    {
        // Generated, so no credential-looking literal sits in the repository.
        $credential = bin2hex(random_bytes(4)) . '@/';
        $this->assertSame(
            ['host' => 'h.example', 'port' => 5672, 'user' => 'u', 'password' => $credential, 'vhost' => 'vh'],
            prQueueParseAmqpUrl('amqp://u:' . rawurlencode($credential) . '@h.example/vh')
        );
        $this->assertSame('/', prQueueParseAmqpUrl('amqp://h.example:5673')['vhost']);
        $this->assertSame(5673, prQueueParseAmqpUrl('amqp://h.example:5673/')['port']);

        $this->expectException(\PrQueueException::class);
        prQueueParseAmqpUrl('');
    }

    public function testSseFrameFormat(): void
    {
        $this->assertSame("id: 7\nevent: pr\ndata: {\"a\":1}\n\n", prQueueSseFrame('pr', ['a' => 1], 7));
        $this->assertSame("event: done\ndata: []\n\n", prQueueSseFrame('done', []));
    }

    public function testOpenDbCreatesDirectoryAndSchemaInWalMode(): void
    {
        $dir  = sys_get_temp_dir() . '/pr-queue-db-' . getmypid() . '-' . uniqid();
        $path = $dir . '/nested/pr-queue.sqlite';

        $pdo = prQueueOpenDb($path);

        $this->assertFileExists($path);
        $this->assertSame('wal', strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()));
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM pr_status')->fetchColumn());
    }

    public function testAssignedIssuesAreFetchedOnceAndCachedInSession(): void
    {
        $fetches = 0;
        $saved   = [];
        $fetch   = function () use (&$fetches) {
            $fetches++;
            return [$this->issue(1) + ['body' => 'long']];
        };
        $persist = function (array $values) use (&$saved) {
            $saved = $values + $saved;
        };

        $items = getAssignedIssuesCached('token', [], $fetch, $persist);
        $again = getAssignedIssuesCached('token', $saved, $fetch, $persist);

        $this->assertSame(1, $fetches);
        $this->assertSame($items, $again);
        $this->assertArrayNotHasKey('body', $items[0]);

        $expired = ['assigned-issues' => ['items' => [], 'fetched_at' => time() - PR_QUEUE_ISSUES_CACHE_TTL - 1]];
        getAssignedIssuesCached('token', $expired, $fetch, $persist);
        $this->assertSame(2, $fetches);
    }

    public function testLoadQueuedAssignedItemsStoresStreamKeysAndSnapshot(): void
    {
        $saved = [];
        $results = loadQueuedAssignedItems(
            'token',
            ['user' => ['login' => 'guibranco']],
            fn () => [$this->issue(1), $this->issue(2, false)],
            function (array $values) use (&$saved) {
                $saved = $values + $saved;
            },
            fn () => null
        );

        $this->assertSame(['guibranco/repo#1'], $saved[PR_QUEUE_SESSION_KEYS]);
        $this->assertCount(1, $saved['pull-requests']['data']['openPullRequests']);
        $this->assertArrayHasKey('assigned-issues', $saved);

        $prPayload = buildPullRequestsPayload($results);
        $this->assertSame(1, $prPayload['pending']);
        $this->assertSame('/api/v1/pull-requests/stream', $prPayload['stream']['url']);

        $dashboard = buildDashboardPayload($results);
        $this->assertCount(1, $dashboard['openPullRequestsDashboard']);
        $this->assertCount(1, $dashboard['openIssuesDashboard']);
    }

    public function testRateLimitHeadersAreRecorded(): void
    {
        recordGitHubRateLimit("HTTP/2 200\r\nx-ratelimit-remaining: 4321\r\nx-ratelimit-reset: 1800000600\r\n", 200);
        $this->assertSame(['remaining' => 4321, 'reset' => 1800000600, 'httpCode' => 200], getLastGitHubRateLimit());

        recordGitHubRateLimit("HTTP/2 500\r\n", 500);
        $this->assertSame(['remaining' => null, 'reset' => null, 'httpCode' => 500], getLastGitHubRateLimit());
    }

    public function testWorkerTracksRateLimitAndStopsBelowTheFloor(): void
    {
        $message = null;
        buildQueuedPullRequests([$this->issue(1), $this->issue(2)], 'token', 'guibranco', function (array $m) use (&$message) {
            $message = $m;
        }, self::NOW);

        $fetch = function () {
            recordGitHubRateLimit("x-ratelimit-remaining: 5\r\nx-ratelimit-reset: " . (self::NOW + 600) . "\r\n", 200);
            return null;
        };

        $processed = prQueueHandleMessage(json_encode($message), $fetch, self::NOW + 1);

        $this->assertSame(1, $processed);
        $rows = prQueueLoadRows(['guibranco/repo#1', 'guibranco/repo#2']);
        $this->assertSame('failed', $rows['guibranco/repo#1']['status']);
        $this->assertSame('released', $rows['guibranco/repo#2']['status']);
    }

    public function testMalformedAndUndecryptableMessagesAreDiscarded(): void
    {
        $this->assertSame(0, prQueueHandleMessage('not json'));

        prQueueMarkQueued(['guibranco/repo#1'], self::NOW);
        $message = json_encode([
            'v' => PR_QUEUE_MESSAGE_VERSION, 'login' => 'guibranco', 'created_at' => self::NOW, 'token' => 'garbage',
            'items' => [['key' => 'guibranco/repo#1', 'url' => 'u', 'updated_at' => null]],
        ]);

        $this->assertSame(0, prQueueHandleMessage($message, fn () => $this->fail('no GitHub call'), self::NOW));
        $this->assertSame('released', prQueueLoadRows(['guibranco/repo#1'])['guibranco/repo#1']['status']);
    }

    public function testConfigDefaultsWhenNoSecretsFile(): void
    {
        // An explicit missing path: the deploy workflow writes the real
        // src/queue.secrets.php before the tests run.
        $config = prQueueConfig(null, sys_get_temp_dir() . '/no-such-dir-' . uniqid() . '/queue.secrets.php');

        $this->assertSame('', $config['rabbitMqUrl']);
        $this->assertSame('', $config['jobsApiKey']);
        $this->assertStringEndsWith('/data/pr-queue.sqlite', $config['sqlitePath']);
    }

    public function testConfigIsLoadedFromTheSecretsFile(): void
    {
        $file = sys_get_temp_dir() . '/queue-secrets-' . getmypid() . '-' . uniqid() . '.php';
        file_put_contents($file, "<?php\n\$rabbitMqUrl = 'amqp://broker.example/vh';\n\$jobsApiKey = 'from-file';\n\$queueSqlitePath = '/tmp/custom.sqlite';\n");

        try {
            $config = prQueueConfig(null, $file);
        } finally {
            unlink($file);
        }

        $this->assertSame('amqp://broker.example/vh', $config['rabbitMqUrl']);
        $this->assertSame('from-file', $config['jobsApiKey']);
        $this->assertSame('', $config['encryptionKey']);
        $this->assertSame('/tmp/custom.sqlite', $config['sqlitePath']);
    }

    public function testDefaultDbOpensTheConfiguredFile(): void
    {
        $path = sys_get_temp_dir() . '/pr-queue-default-' . getmypid() . '-' . uniqid() . '/db.sqlite';
        prQueueConfig(['rabbitMqUrl' => '', 'encryptionKey' => '', 'jobsApiKey' => '', 'sqlitePath' => $path]);

        $pdo = prQueueDb(null, true);

        $this->assertSame($pdo, prQueueDb());
        $this->assertFileExists($path);
    }

    public function testReleasingNothingIsANoOp(): void
    {
        prQueueRelease([]);

        $this->assertSame(0, prQueueMaxSeq());
    }

    public function testDrainDeclaresConsumesAndClosesTheConnection(): void
    {
        $log = new \ArrayObject();
        $channel = new class ($log) {
            public function __construct(private \ArrayObject $log)
            {
            }

            public function queue_declare(string $queue): void
            {
                $this->log[] = 'declare:' . $queue;
            }

            public function basic_get(string $queue): ?object
            {
                $this->log[] = 'get';
                return null;
            }

            public function close(): void
            {
                $this->log[] = 'channel-close';
            }
        };
        $connection = new class ($channel, $log) {
            public function __construct(private object $channel, private \ArrayObject $log)
            {
            }

            public function channel(): object
            {
                return $this->channel;
            }

            public function close(): void
            {
                $this->log[] = 'connection-close';
            }
        };

        $server = null;
        $result = prQueueDrain(1, 5, function (array $s) use ($connection, &$server) {
            $server = $s;
            return $connection;
        });

        $this->assertSame('localhost', $server['host']);
        $this->assertSame('vhost', $server['vhost']);
        $this->assertSame(0, $result['messages']);
        $this->assertSame('declare:' . PR_QUEUE_NAME, $log[0]);
        $this->assertContains('get', $log->getArrayCopy());
        $this->assertSame(['channel-close', 'connection-close'], array_slice($log->getArrayCopy(), -2));
    }

    private function runJobsEndpoint(string $method, string $key): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_X_API_KEY'] = $key;

        ob_start();
        require __DIR__ . '/../../src/api/v1/jobs-pull-requests.php';
        $body = ob_get_clean();
        set_time_limit(0);

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_API_KEY']);

        return json_decode($body, true);
    }

    public function testJobsEndpointRejectsGetAndBadKeys(): void
    {
        $this->assertSame(['error' => 'Method not allowed'], $this->runJobsEndpoint('GET', 'job-key'));
        $this->assertSame(['error' => 'Unauthorized'], $this->runJobsEndpoint('POST', 'nope'));
    }

    public function testPublishSendsTheMessageToTheConfiguredBroker(): void
    {
        $published = [];
        $queue = new class ($published) {
            private $published;

            public function __construct(array &$published)
            {
                $this->published = &$published;
            }

            public function publish(string $queueName, string $body, bool $declareDlx): void
            {
                $this->published[] = [$queueName, json_decode($body, true), $declareDlx];
            }
        };

        $broker = null;
        prQueuePublish(['v' => 1, 'items' => []], function (string $url) use ($queue, &$broker) {
            $broker = $url;
            return $queue;
        });

        $this->assertSame('amqp://localhost/vhost', $broker);
        $this->assertSame([[PR_QUEUE_NAME, ['v' => 1, 'items' => []], false]], $published);
    }
}
