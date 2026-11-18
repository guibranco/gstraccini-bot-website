<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/constants.php';

class GitHubApiUrlTest extends TestCase
{
    public function testRelativePathGoesToTheProxy(): void
    {
        $this->assertSame(GITHUB_API_BASE_URL . '/user', gitHubApiUrl('/user'));
        $this->assertSame(GITHUB_API_BASE_URL . '/issues?per_page=100', gitHubApiUrl('issues?per_page=100'));
    }

    public function testAbsoluteGitHubApiUrlIsMovedToTheProxy(): void
    {
        $this->assertSame(
            GITHUB_API_BASE_URL . '/repos/guibranco/repo/pulls/1',
            gitHubApiUrl('https://api.github.com/repos/guibranco/repo/pulls/1')
        );
        $this->assertSame(GITHUB_API_BASE_URL . '?a=1', gitHubApiUrl('https://api.github.com?a=1'));
    }

    public function testProxyAndOtherUrlsAreLeftUnchanged(): void
    {
        $proxied = GITHUB_API_BASE_URL . '/issues?page=2';
        $this->assertSame($proxied, gitHubApiUrl($proxied));
        $this->assertSame('https://github.com/login/oauth/access_token', gitHubApiUrl('https://github.com/login/oauth/access_token'));
        $this->assertSame('https://api.github.com.evil.example/x', gitHubApiUrl('https://api.github.com.evil.example/x'));
    }
}
