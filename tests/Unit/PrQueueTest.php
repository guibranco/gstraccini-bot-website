<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/github-api.php';
require_once __DIR__ . '/../../src/includes/pr-queue.php';

class PrQueueTest extends TestCase
{
    private const NOW = 1_800_000_000;

    protected function setUp(): void
    {
        prQueueConfig([
            'rabbitMqUrl'   => 'amqp://user:pass@localhost/vhost',
            'encryptionKey' => base64_encode(str_repeat('k', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
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
}
