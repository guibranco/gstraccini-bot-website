<?php
/**
 * Helpers for scoping upstream GStraccini API calls to the signed-in user's
 * GitHub id and GitHub App installation ids.
 *
 * NOTE: the upstream API does not accept these params yet; the query string
 * shape here (userId / repeated installationIds) will be revisited once the
 * gstraccini-bot-service OpenAPI spec is available.
 */

/**
 * Reads the signed-in user's GitHub id from the session.
 *
 * @return int|null The user id, or null when unavailable.
 */
function getCurrentUserId(): ?int
{
    return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
}

/**
 * Reads the signed-in user's GitHub App installation ids from the session.
 *
 * @return int[] List of installation ids.
 */
function getCurrentInstallationIds(): array
{
    $installations = $_SESSION['installations']['installations'] ?? [];

    return array_map('intval', array_column($installations, 'id'));
}

/**
 * Appends the current user's id and installation ids (plus any extra params)
 * to an upstream URL as a query string.
 *
 * @param string $url          The base upstream URL (no query string).
 * @param array  $extraParams  Additional query params to include, e.g. ['filter' => 'unread'].
 * @return string               The URL with the scoped query string appended.
 */
function appendUserScopeParams(string $url, array $extraParams = []): string
{
    $params = array_filter(
        array_merge(['userId' => getCurrentUserId()], $extraParams),
        static fn ($value) => $value !== null && $value !== ''
    );

    $queryParts = [];
    if ($params !== []) {
        $queryParts[] = http_build_query($params);
    }
    foreach (getCurrentInstallationIds() as $installationId) {
        $queryParts[] = 'installationIds=' . urlencode((string) $installationId);
    }

    return $url . '?' . implode('&', $queryParts);
}
