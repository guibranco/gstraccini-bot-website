<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/constants.php';

use GuiBranco\Pancake\LogStream;

/**
 * Builds (and caches) the LogStream client used to ship real-time logs.
 *
 * @param  string|null $secretsFile Overrides the secrets file path (used by tests
 *                                  to avoid touching the real, environment-provided file).
 * @return LogStream|null Null when logstream.secrets.php is missing or incomplete,
 *                         so logging stays a no-op instead of breaking the app.
 */
function getLogStream(?string $secretsFile = null): ?LogStream
{
    static $instance = null;
    static $initialized = false;

    if ($initialized) {
        return $instance;
    }
    $initialized = true;

    $secretsFile ??= __DIR__ . '/../logstream.secrets.php';
    if (!file_exists($secretsFile)) {
        return null;
    }

    require $secretsFile;

    if (empty($logStreamUrl) || empty($logStreamToken)) {
        return null;
    }

    $instance = new LogStream(
        baseUrl: $logStreamUrl,
        appKey: 'gstraccini-bot-website',
        appId: getAppVersion(),
        authMode: LogStream::AUTH_BEARER,
        apiSecret: $logStreamToken,
        userAgent: getUserAgent(),
    );

    return $instance;
}
