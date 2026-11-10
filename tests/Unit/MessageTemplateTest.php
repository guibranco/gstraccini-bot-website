<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/log-stream.php';

class MessageTemplateTest extends TestCase
{
    private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * Placeholders are replaced with their property values.
     */
    public function testRenderReplacesPlaceholders(): void
    {
        $message = renderMessageTemplate(
            'Remote call to {url} returned HTTP {httpCode}',
            ['url' => 'https://api.github.com/user', 'httpCode' => 200]
        );

        $this->assertSame('Remote call to https://api.github.com/user returned HTTP 200', $message);
    }

    /**
     * A placeholder without a matching property is kept as-is.
     */
    public function testRenderKeepsUnknownPlaceholders(): void
    {
        $this->assertSame('User {username} logged in', renderMessageTemplate('User {username} logged in', []));
    }

    /**
     * Properties that are not in the template do not change the message.
     */
    public function testRenderIgnoresExtraProperties(): void
    {
        $this->assertSame('Cache miss', renderMessageTemplate('Cache miss', ['cacheKey' => 'dashboard']));
    }

    /**
     * Null, booleans and arrays are rendered as readable text.
     */
    public function testRenderFormatsNonStringValues(): void
    {
        $message = renderMessageTemplate(
            '{none} {yes} {no} {list} {map}',
            ['none' => null, 'yes' => true, 'no' => false, 'list' => [1, 2], 'map' => ['a' => 'b/c']]
        );

        $this->assertSame('null true false [1,2] {"a":"b/c"}', $message);
    }

    /**
     * Values containing braces or regex back-references are inserted literally.
     */
    public function testRenderInsertsValuesLiterally(): void
    {
        $message = renderMessageTemplate('Login failed: {reason}', ['reason' => 'bad {state} $1 \\1']);

        $this->assertSame('Login failed: bad {state} $1 \\1', $message);
    }

    /**
     * The trace id is a UUID v4 that stays the same for the whole request.
     */
    public function testTraceIdIsStableUuidV4(): void
    {
        $traceId = getLogTraceId();

        $this->assertMatchesRegularExpression(self::UUID_V4_PATTERN, $traceId);
        $this->assertSame($traceId, getLogTraceId());
    }

    /**
     * Maps HTTP status codes to log levels.
     *
     * @return array<string, array{int, string}>
     */
    public static function httpStatusProvider(): array
    {
        return [
            'ok' => [200, 'info'],
            'redirect' => [302, 'info'],
            'client error' => [401, 'warning'],
            'server error' => [502, 'error'],
            'no response' => [0, 'error'],
        ];
    }

    /**
     * Each HTTP status class maps to the expected log level.
     *
     * @param int    $httpCode The HTTP status code.
     * @param string $expected The expected log level.
     */
    #[DataProvider('httpStatusProvider')]
    public function testLogLevelForHttpStatus(int $httpCode, string $expected): void
    {
        $this->assertSame($expected, getLogLevelForHttpStatus($httpCode));
    }

    /**
     * Without LogStream, info is dropped and warnings still reach the error log.
     */
    #[RunInSeparateProcess]
    public function testWarningsAreMirroredToErrorLog(): void
    {
        $errorLog = sys_get_temp_dir() . '/message-template-test-' . bin2hex(random_bytes(8)) . '.log';
        $missingSecrets = sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(8)) . '.secrets.php';
        ini_set('error_log', $errorLog);

        try {
            $this->assertNull(getLogStream($missingSecrets));

            logInfo('Cache miss for {cacheKey}', ['cacheKey' => 'dashboard'], 'cache');
            logWarning(
                'Remote call to {url} returned HTTP {httpCode}',
                ['url' => 'https://example.com', 'httpCode' => 503],
                'remote-calls'
            );

            $contents = file_exists($errorLog) ? file_get_contents($errorLog) : '';
            $this->assertStringNotContainsString('Cache miss', $contents);
            $this->assertStringContainsString(
                '[warning] [remote-calls] Remote call to https://example.com returned HTTP 503',
                $contents
            );
        } finally {
            if (file_exists($errorLog)) {
                unlink($errorLog);
            }
        }
    }
}
