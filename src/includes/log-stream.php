<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/constants.php';

use GuiBranco\Pancake\LogStream;

/**
 * Log levels that are also mirrored to the PHP error log, so problems stay
 * visible in the web server logs even when LogStream is unavailable.
 */
const LOG_LEVELS_MIRRORED_TO_ERROR_LOG = [
    'warning',
    'error',
    'critical',
];

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

    $logStreamServer = null;
    $logStreamApiKey = null;
    $logStreamApiToken = null;

    require $secretsFile;

    if (empty($logStreamServer) === true || empty($logStreamApiKey) === true || empty($logStreamApiToken) === true) {
        return null;
    }

    $instance = new LogStream(
        baseUrl: $logStreamServer,
        appKey: $logStreamApiKey,
        appId: getAppVersion(),
        authMode: LogStream::AUTH_API_KEY,
        apiKey: $logStreamApiKey,
        apiToken: $logStreamApiToken,
        userAgent: getUserAgent(),
    );

    return $instance;
}

/**
 * Returns the correlation id shared by every log entry of the current request.
 *
 * @return string A UUID v4, generated once per request.
 */
function getLogTraceId(): string
{
    static $traceId = null;

    if ($traceId === null) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $traceId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    return $traceId;
}

/**
 * Converts a property value into the text used when rendering a message template.
 *
 * @param  mixed $value The property value.
 * @return string The textual representation.
 */
function formatMessageTemplateValue(mixed $value): string
{
    if ($value === null) {
        return 'null';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_scalar($value) || $value instanceof Stringable) {
        return (string) $value;
    }

    $json = json_encode($value, (JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $json === false ? get_debug_type($value) : $json;
}

/**
 * Renders a message template ("Remote call to {url} failed") by replacing each
 * {placeholder} with the matching property. Placeholders without a matching
 * property are left untouched, so a typo is visible instead of silently lost.
 *
 * @param  string $template   The message template.
 * @param  array  $properties The template properties.
 * @return string The rendered message.
 */
function renderMessageTemplate(string $template, array $properties): string
{
    return preg_replace_callback(
        '/\{([A-Za-z_]\w*)\}/',
        static function (array $matches) use ($properties): string {
            if (array_key_exists($matches[1], $properties) === false) {
                return $matches[0];
            }

            return formatMessageTemplateValue($properties[$matches[1]]);
        },
        $template
    );
}

/**
 * Reduces a URL to its host and path, dropping the query string and fragment,
 * so tokens or one-time codes passed in a URL never reach the logs.
 *
 * @param string $url The URL (absolute or relative).
 * @return string The host and path, or "/" when there is no path.
 */
function sanitizeUrlForLog(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) && $path !== '' ? $path : '/';

    return is_string($host) ? $host . $path : $path;
}

/**
 * Bounds an untrusted input value before it is logged.
 *
 * @param mixed $value     The raw input value.
 * @param int   $maxLength The maximum length kept for strings.
 * @return string|null The value, truncated, or its type when it is not a string.
 */
function sanitizeInputForLog(mixed $value, int $maxLength = 50): ?string
{
    if ($value === null) {
        return null;
    }

    if (is_string($value) === false) {
        return get_debug_type($value);
    }

    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) . '…' : $value;
}

/**
 * Picks the log level for the outcome of an HTTP call: success and redirects
 * are informational, client errors are warnings, everything else is an error.
 *
 * @param int $httpCode The HTTP status code (0 when no response was received).
 * @return string The log level.
 */
function getLogLevelForHttpStatus(int $httpCode): string
{
    if ($httpCode >= 200 && $httpCode < 400) {
        return 'info';
    }

    return $httpCode >= 400 && $httpCode < 500 ? 'warning' : 'error';
}

/**
 * Writes a structured log entry using a message template.
 *
 * The template itself is kept constant (no string interpolation at the call
 * site), so every occurrence of the same event can be grouped and searched in
 * LogStream. The rendered message, the template and its properties are all sent:
 * the properties become the entry context, alongside "messageTemplate".
 *
 * Logging never throws: delivery failures are reported to the PHP error log only.
 *
 * @param string $level      One of debug|info|notice|warning|error|critical.
 * @param string $template   The message template, e.g. "User {username} logged in".
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag, e.g. "auth" or "remote-calls".
 */
function logEvent(string $level, string $template, array $properties = [], string $category = 'general'): void
{
    $message = renderMessageTemplate($template, $properties);

    if (in_array($level, LOG_LEVELS_MIRRORED_TO_ERROR_LOG, true)) {
        // Control characters are replaced so user input cannot forge extra log lines.
        $safeMessage = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $message);
        error_log("[{$level}] [{$category}] {$safeMessage}");
    }

    $logStream = getLogStream();
    if ($logStream === null) {
        return;
    }

    $context = array_merge(['messageTemplate' => $template], $properties);

    try {
        $logStream->log($logStream->makeEntry($message, $level, $category, $context, getLogTraceId()));
    } catch (Throwable $e) {
        error_log('LogStream delivery failed: ' . $e->getMessage());
    }
}

/**
 * Writes a DEBUG-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logDebug(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('debug', $template, $properties, $category);
}

/**
 * Writes an INFO-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logInfo(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('info', $template, $properties, $category);
}

/**
 * Writes a NOTICE-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logNotice(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('notice', $template, $properties, $category);
}

/**
 * Writes a WARNING-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logWarning(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('warning', $template, $properties, $category);
}

/**
 * Writes an ERROR-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logError(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('error', $template, $properties, $category);
}

/**
 * Writes a CRITICAL-level structured log entry. See logEvent().
 *
 * @param string $template   The message template.
 * @param array  $properties Values for the template placeholders plus any extra context.
 * @param string $category   Short grouping tag.
 * @return void
 */
function logCritical(string $template, array $properties = [], string $category = 'general'): void
{
    logEvent('critical', $template, $properties, $category);
}
