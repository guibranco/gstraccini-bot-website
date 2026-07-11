<?php

namespace GuiBranco\GstracciniBotWebsite\Integration;

use GuiBranco\Pancake\LogStream;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/log-stream.php';

class LogStreamIntegrationTest extends TestCase
{
    private string $secretsFile;

    protected function setUp(): void
    {
        $this->secretsFile = __DIR__ . '/../../src/logstream.secrets.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->secretsFile)) {
            unlink($this->secretsFile);
        }
    }

    #[RunInSeparateProcess]
    public function testGetLogStreamIsNullWhenSecretsFileIsMissing(): void
    {
        $this->assertFileDoesNotExist($this->secretsFile);
        $this->assertNull(getLogStream());
    }

    #[RunInSeparateProcess]
    public function testGetLogStreamIsNullWhenSecretsAreIncomplete(): void
    {
        file_put_contents($this->secretsFile, "<?php\n\$logStreamUrl = '';\n\$logStreamToken = '';\n");

        $this->assertNull(getLogStream());
    }

    #[RunInSeparateProcess]
    public function testGetLogStreamBuildsClientWhenSecretsArePresent(): void
    {
        file_put_contents(
            $this->secretsFile,
            "<?php\n\$logStreamUrl = 'https://logstream.example.com/';\n\$logStreamToken = 'test-token';\n"
        );

        $this->assertInstanceOf(LogStream::class, getLogStream());
    }
}
