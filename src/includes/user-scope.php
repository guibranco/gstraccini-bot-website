<?php
/**
 * Helpers for scoping upstream GStraccini API calls to the signed-in user's
 * GitHub id, per the gstraccini-bot-service OpenAPI spec (userId is the only
 * scoping param the upstream API accepts; it resolves installations itself).
 */


/**
 * Reads the signed-in user's GitHub id from the session.
 *
 * @return int|null The user id, or null when unavailable.
 */
function getCurrentUserId(): ?int
{
    if (isset($_SESSION['user']['id']) === true) {
        return (int) $_SESSION['user']['id'];
    }

    return null;
}//end getCurrentUserId()


/**
 * Appends the current user's id to an upstream URL as a query string.
 *
 * @param string   $url    The base upstream URL (no query string).
 * @param int|null $userId The GitHub user id to scope the request to.
 * @return string The URL with the userId query string appended.
 */
function appendUserIdParam(string $url, ?int $userId): string
{
    return $url.'?'.http_build_query(['userId' => $userId]);
}//end appendUserIdParam()
