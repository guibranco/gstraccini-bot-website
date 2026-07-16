<?php

namespace GuiBranco\GstracciniBotWebsite\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/github-api.php';
require_once __DIR__ . '/../../src/includes/color-utils.php';
require_once __DIR__ . '/../../src/api/v1/api-common.php';

/**
 * Exercises the same pipeline issues.php/pull-requests.php rely on to turn a
 * raw GitHub API payload into the grouped, render-ready structure: fetching
 * helpers, PR/issue separation, and label color contrast.
 */
class IssuesPipelineIntegrationTest extends TestCase
{
    public function testRawGitHubPayloadIsGroupedAndLabelColorsAreReadable(): void
    {
        $rawIssues = [
            [
                'title' => 'Fix crash on login',
                'repository' => [
                    'id' => 100,
                    'name' => 'my-repo',
                    'full_name' => 'guibranco/my-repo',
                    'owner' => ['login' => 'guibranco'],
                ],
                'html_url' => 'https://github.com/guibranco/my-repo/issues/1',
                'labels' => [
                    ['color' => 'ff0000', 'description' => 'Something is broken', 'name' => 'bug'],
                ],
                'created_at' => '2026-01-01T00:00:00Z',
            ],
            [
                'title' => 'Add dark theme',
                'repository' => [
                    'id' => 200,
                    'name' => 'another-repo',
                    'full_name' => 'guibranco/another-repo',
                    'owner' => ['login' => 'other-owner'],
                ],
                'html_url' => 'https://github.com/guibranco/another-repo/issues/2',
                'labels' => [
                    ['color' => 'ffffff', 'description' => null, 'name' => 'enhancement'],
                ],
                'created_at' => '2026-01-02T00:00:00Z',
            ],
        ];

        $result = processIssuesAndPullRequests($rawIssues, 'fake-token');
        $this->assertCount(2, $result['issues']);
        $this->assertCount(0, $result['pullRequests']);

        $groupedIssues = [];
        foreach ($result['issues'] as $issue) {
            $groupedIssues[$issue['owner']][] = $issue;
        }

        $this->assertArrayHasKey('guibranco', $groupedIssues);
        $this->assertArrayHasKey('other-owner', $groupedIssues);
        $this->assertCount(1, $groupedIssues['guibranco']);
        $this->assertCount(1, $groupedIssues['other-owner']);

        $bugLabel = $groupedIssues['guibranco'][0]['labels'][0];
        $enhancementLabel = $groupedIssues['other-owner'][0]['labels'][0];

        $this->assertSame('#fff', luminance($bugLabel['color']));
        $this->assertSame('#000', luminance($enhancementLabel['color']));
    }
}
