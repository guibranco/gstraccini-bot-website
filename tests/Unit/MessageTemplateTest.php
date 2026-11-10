<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/log-stream.php';

class MessageTemplateTest extends TestCase
{
    public function testRenderMessageTemplateReplacesPlaceholders(): void
    {
        $message = renderMessageTemplate(
            'Remote call to {url} returned HTTP {httpCode}',
            ['url' => 'https://api.github.com/user', 'httpCode' => 200]
        );

        $this->assertSame('Remote call to https://api.github.com/user returned HTTP 200', $message);
    }

    public function testRenderMessageTemplateLeavesUnknownPlaceholdersUntouched(): void
    {
        $this->assertSame('User {username} logged in', renderMessageTemplate('User {username} logged in', []));
    }

    public function testRenderMessageTemplateIgnoresExtraProperties(): void
    {
        $this->assertSame('Cache miss', renderMessageTemplate('Cache miss', ['cacheKey' => 'dashboard']));
    }

    public function testRenderMessageTemplateFormatsNonStringValues(): void
    {
        $message = renderMessageTemplate(
            '{none} {yes} {no} {list} {map}',
            ['none' => null, 'yes' => true, 'no' => false, 'list' => [1, 2], 'map' => ['a' => 'b/c']]
        );

        $this->assertSame('null true false [1,2] {"a":"b/c"}', $message);
    }

    public function testRenderMessageTemplateDoesNotReinterpretValues(): void
    {
        $message = renderMessageTemplate('Login failed: {reason}', ['reason' => 'bad {state} $1 \\1']);

        $this->assertSame('Login failed: bad {state} $1 \\1', $message);
    }

    public function testGetLogTraceIdIsAStableUuidV4(): void
    {
        $traceId = getLogTraceId();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $traceId);
        $this->assertSame($traceId, getLogTraceId());
    }

    #[RunInSeparateProcess]
    public function testLogEventIsANoOpWithoutLogStreamAndMirrorsWarningsToErrorLog(): void
    {
        $errorLog = sys_get_temp_dir() . '/message-template-test-' . bin2hex(random_bytes(8)) . '.log';
        ini_set('error_log', $errorLog);

        try {
            $this->assertNull(getLogStream(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(8)) . '.secrets.php'));

            logInfo('Cache miss for {cacheKey}', ['cacheKey' => 'dashboard'], 'cache');
            logWarning('Remote call to {url} returned HTTP {httpCode}', ['url' => 'https://example.com', 'httpCode' => 503], 'remote-calls');

            $contents = file_exists($errorLog) ? file_get_contents($errorLog) : '';
            $this->assertStringNotContainsString('Cache miss', $contents);
            $this->assertStringContainsString('[warning] [remote-calls] Remote call to https://example.com returned HTTP 503', $contents);
        } finally {
            if (file_exists($errorLog)) {
                unlink($errorLog);
            }
        }
    }
}
