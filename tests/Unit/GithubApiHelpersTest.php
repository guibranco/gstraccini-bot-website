<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/github-api.php';

class GithubApiHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_GET = [];
    }

    public function testGetNextPageUrlExtractsNextLink(): void
    {
        $linkHeader = '<https://api.github.com/issues?page=2>; rel="next", <https://api.github.com/issues?page=5>; rel="last"';

        $this->assertSame('https://api.github.com/issues?page=2', getNextPageUrl($linkHeader));
    }

    public function testGetNextPageUrlReturnsNullWhenNoNextRelPresent(): void
    {
        $linkHeader = '<https://api.github.com/issues?page=1>; rel="prev"';

        $this->assertNull(getNextPageUrl($linkHeader));
    }

    public function testGetNextPageUrlReturnsNullForEmptyHeader(): void
    {
        $this->assertNull(getNextPageUrl(''));
    }

    public function testFormatIssueDataMapsExpectedFields(): void
    {
        $issue = [
            'title' => 'Something broke',
            'repository' => [
                'name' => 'my-repo',
                'full_name' => 'guibranco/my-repo',
                'owner' => ['login' => 'guibranco'],
            ],
            'html_url' => 'https://github.com/guibranco/my-repo/issues/1',
            'labels' => [
                ['color' => 'ff0000', 'description' => 'Bug report', 'name' => 'bug'],
            ],
            'created_at' => '2026-01-01T00:00:00Z',
        ];

        $formatted = formatIssueData($issue);

        $this->assertSame('Something broke', $formatted['title']);
        $this->assertSame('my-repo', $formatted['repository']);
        $this->assertSame('guibranco/my-repo', $formatted['full_name']);
        $this->assertSame('https://github.com/guibranco/my-repo/issues/1', $formatted['url']);
        $this->assertSame('guibranco', $formatted['owner']);
        $this->assertSame('2026-01-01T00:00:00Z', $formatted['created_at']);
        $this->assertSame(
            [['color' => 'ff0000', 'description' => 'Bug report', 'name' => 'bug']],
            $formatted['labels']
        );
    }

    public function testFormatIssueDataFillsMissingLabelFieldsWithNull(): void
    {
        $issue = [
            'title' => 'No label metadata',
            'repository' => [
                'name' => 'my-repo',
                'full_name' => 'guibranco/my-repo',
                'owner' => ['login' => 'guibranco'],
            ],
            'html_url' => 'https://github.com/guibranco/my-repo/issues/2',
            'labels' => [[]],
            'created_at' => '2026-01-01T00:00:00Z',
        ];

        $formatted = formatIssueData($issue);

        $this->assertSame([['color' => null, 'description' => null, 'name' => null]], $formatted['labels']);
    }

    public function testFormatRepositoryDataMapsExpectedFields(): void
    {
        $repo = [
            'name' => 'my-repo',
            'owner' => ['login' => 'guibranco'],
            'html_url' => 'https://github.com/guibranco/my-repo',
            'fork' => false,
            'stargazers_count' => 42,
            'forks' => 3,
            'open_issues_count' => 7,
            'language' => 'PHP',
            'visibility' => 'public',
        ];

        $formatted = formatRepositoryData($repo);

        $this->assertSame([
            'name' => 'my-repo',
            'organization' => 'guibranco',
            'url' => 'https://github.com/guibranco/my-repo',
            'fork' => false,
            'stars' => 42,
            'forks' => 3,
            'issues' => 7,
            'language' => 'PHP',
            'visibility' => 'public',
        ], $formatted);
    }

    public function testEnrichPullRequestDataWithoutHeadReturnsBaseFieldsOnly(): void
    {
        $issueData = ['title' => 'A PR', 'url' => 'https://github.com/guibranco/my-repo/pull/1'];
        $pullRequest = ['body' => ['mergeable' => true, 'mergeable_state' => 'clean']];

        $result = enrichPullRequestData($issueData, $pullRequest, 'fake-token');

        $this->assertTrue($result['mergeable']);
        $this->assertSame('clean', $result['mergeable_state']);
        $this->assertArrayNotHasKey('state', $result);
        $this->assertArrayNotHasKey('is_valid_pr', $result);
    }

    public function testEnrichPullRequestDataWithMissingBodyFieldsDefaultsToNull(): void
    {
        $issueData = ['title' => 'A PR'];
        $pullRequest = ['body' => []];

        $result = enrichPullRequestData($issueData, $pullRequest, 'fake-token');

        $this->assertNull($result['mergeable']);
        $this->assertNull($result['mergeable_state']);
    }

    public function testSendJsonResponseEchoesEncodedData(): void
    {
        ob_start();
        sendJsonResponse(['foo' => 'bar'], time());
        $output = ob_get_clean();

        $this->assertSame(['foo' => 'bar'], json_decode($output, true));
    }

    public function testGetCacheReturnsFalseWhenNothingCached(): void
    {
        $this->assertFalse(getCache('missing-key'));
    }

    public function testGetCacheReturnsFalseWhenCacheExpired(): void
    {
        $_SESSION['stale'] = ['data' => ['foo' => 'bar'], 'last_api_call' => time() - 1000];

        $this->assertFalse(getCache('stale', 300));
    }

    public function testGetCacheReturnsTrueAndEchoesDataWhenCacheIsFresh(): void
    {
        $_SESSION['fresh'] = ['data' => ['foo' => 'bar'], 'last_api_call' => time()];

        ob_start();
        $result = getCache('fresh', 300);
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertSame(['foo' => 'bar'], json_decode($output, true));
    }

    public function testGetCacheIgnoresCacheWhenPageParamIsSet(): void
    {
        $_GET['page'] = 2;
        $_SESSION['fresh'] = ['data' => ['foo' => 'bar'], 'last_api_call' => time()];

        $this->assertFalse(getCache('fresh', 300));
    }

    public function testSetCacheStoresDataWithTimestamp(): void
    {
        setCache(['foo' => 'bar'], 'my-key');

        $this->assertSame(['foo' => 'bar'], $_SESSION['my-key']['data']);
        $this->assertEqualsWithDelta(time(), $_SESSION['my-key']['last_api_call'], 2);
    }
}
