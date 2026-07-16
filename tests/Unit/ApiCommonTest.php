<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/github-api.php';
require_once __DIR__ . '/../../src/api/v1/api-common.php';

class ApiCommonTest extends TestCase
{
    private function makeIssue(int $repositoryId, bool $isPullRequest): array
    {
        $issue = [
            'title' => 'Sample',
            'repository' => [
                'id' => $repositoryId,
                'name' => 'my-repo',
                'full_name' => 'guibranco/my-repo',
                'owner' => ['login' => 'guibranco'],
            ],
            'html_url' => 'https://github.com/guibranco/my-repo/issues/1',
            'labels' => [],
            'created_at' => '2026-01-01T00:00:00Z',
        ];

        if ($isPullRequest) {
            $issue['pull_request'] = ['url' => 'https://api.github.com/repos/guibranco/my-repo/pulls/1'];
        }

        return $issue;
    }

    public function testPlainIssuesAreSeparatedFromPullRequests(): void
    {
        $issues = [$this->makeIssue(1, false)];

        $result = processIssuesAndPullRequests($issues, 'fake-token');

        $this->assertCount(1, $result['issues']);
        $this->assertCount(0, $result['pullRequests']);
    }

    public function testPullRequestsAreMarkedSkippedWhenMaxValidPRsIsZero(): void
    {
        $issues = [$this->makeIssue(2, true)];

        $result = processIssuesAndPullRequests($issues, 'fake-token', 0);

        $this->assertCount(0, $result['issues']);
        $this->assertCount(1, $result['pullRequests']);
        $this->assertSame('skipped', $result['pullRequests'][0]['state']);
    }

    public function testSecondPullRequestFromSameRepoIsSkippedWithoutExtraCalls(): void
    {
        $issues = [$this->makeIssue(3, true), $this->makeIssue(3, true)];

        $result = processIssuesAndPullRequests($issues, 'fake-token', 0);

        $this->assertCount(2, $result['pullRequests']);
        $this->assertSame('skipped', $result['pullRequests'][0]['state']);
        $this->assertSame('skipped', $result['pullRequests'][1]['state']);
    }
}
