<?php

/**
 * Shared guards and relays for the JSON API endpoints under api/v1, so each
 * endpoint logs configuration, authentication and update events the same way.
 */

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/log-stream.php';
require_once __DIR__ . '/remote-json-proxy.php';
require_once __DIR__ . '/user-scope.php';

/**
 * Terminates the request with HTTP 500 when a required configuration file is missing.
 *
 * The file itself must still be required by the caller, at file scope, so the
 * variables it defines stay global.
 *
 * @param string $path     Path to the configuration file.
 * @param string $endpoint Endpoint name, used in the log entry.
 * @return void
 */
function ensureConfigurationFileExists(string $path, string $endpoint): void
{
    if (file_exists($path) === true) {
        return;
    }

    logCritical(
        "Configuration file {file} not found",
        [
            'file' => basename($path),
            'endpoint' => $endpoint,
        ],
        'configuration'
    );
    http_response_code(500);
    die(json_encode(['error' => 'Configuration file not found']));
}

/**
 * Terminates the request with HTTP 401 when the user is not signed in.
 *
 * @param bool   $isAuthenticated Whether the current session is authenticated.
 * @param string $endpoint        Endpoint name, used in the log entry.
 * @param string $errorMessage    Error message returned to the client.
 * @return void
 */
function ensureAuthenticated(
    bool $isAuthenticated,
    string $endpoint,
    string $errorMessage = 'User not authenticated.'
): void {
    if ($isAuthenticated === true) {
        return;
    }

    logNotice("Rejected unauthenticated request to {endpoint}", ['endpoint' => $endpoint], 'auth');
    http_response_code(401);
    echo json_encode(['error' => $errorMessage]);
    exit();
}

/**
 * Lists the top-level field names of an update request body, for logging.
 * Only the names are returned, never the values.
 *
 * @param mixed $body The decoded request body.
 * @return string Comma-separated field names, or an empty string.
 */
function describeUpdatedFields(mixed $body): string
{
    return is_array($body) ? implode(', ', array_keys($body)) : '';
}

/**
 * Logs a request by the signed-in user to add or remove an integration.
 * The level follows the upstream status, so failed attempts are not logged as changes.
 *
 * @param string      $action   "add" or "remove".
 * @param int|null    $userId   The GitHub user id.
 * @param string|null $provider The integration provider.
 * @param int         $httpCode The upstream HTTP status code.
 * @return void
 */
function logIntegrationChange(string $action, ?int $userId, ?string $provider, int $httpCode): void
{
    logEvent(
        getLogLevelForHttpStatus($httpCode),
        "User {userId} requested to {action} integration {provider}: HTTP {httpCode}",
        [
            'userId' => $userId,
            'action' => $action,
            'provider' => $provider,
            'httpCode' => $httpCode,
        ],
        'integrations'
    );
}

/**
 * Relays the signed-in user's PUT body to the upstream API, logs the outcome
 * and terminates the request with the upstream status and body.
 *
 * @param string $url      The upstream URL (already scoped to the user).
 * @param string $resource Human-readable resource name, e.g. "profile".
 * @param array  $headers  Extra request headers, e.g. the API key.
 * @return void
 */
function relayUserUpdate(string $url, string $resource, array $headers): void
{
    $body = json_decode(file_get_contents('php://input'), true);
    $body = is_array($body) ? $body : [];
    $result = sendJsonToUpstream($url, 'PUT', $body, $headers);

    logInfo(
        "User {userId} updated {resource} ({fields}): HTTP {httpCode}",
        [
            'userId' => getCurrentUserId(),
            'resource' => $resource,
            'fields' => describeUpdatedFields($body),
            'httpCode' => $result['httpCode'],
        ],
        'account'
    );

    http_response_code($result['httpCode'] > 0 ? $result['httpCode'] : 502);
    echo json_encode($result['decoded']);
    exit();
}
