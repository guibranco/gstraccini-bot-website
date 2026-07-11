<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/constants.php';

class ConstantsTest extends TestCase
{
    public function testGetAppVersionDefaultsToDevWhenVersionFileIsMissing(): void
    {
        $this->assertSame('dev', getAppVersion());
    }

    public function testGetUserAgentContainsVersionAndRepositoryUrl(): void
    {
        $userAgent = getUserAgent();

        $this->assertStringStartsWith('GStraccini-bot-website/', $userAgent);
        $this->assertStringContainsString(getAppVersion(), $userAgent);
        $this->assertStringContainsString(APP_REPOSITORY_URL, $userAgent);
    }
}
