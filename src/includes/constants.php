<?php
/**
 * Application-wide constants and helpers.
 */

const APP_REPOSITORY_URL = 'https://github.com/guibranco/gstraccini-bot-website';

/**
 * GitHub REST API root used for every API call: the github-api-proxy, which forwards to
 * api.github.com unchanged and records who called what.
 */
const GITHUB_API_BASE_URL = 'https://github-proxy.gstraccini.dev';

/**
 * The GitHub API root that response bodies (e.g. `pull_request.url`) and callers may still use.
 */
const GITHUB_API_UPSTREAM_URL = 'https://api.github.com';

/**
 * Builds a GitHub API URL on GITHUB_API_BASE_URL.
 *
 * Accepts a path relative to the API root ("/user", "issues?per_page=100") or an absolute
 * api.github.com URL (such as the `url` fields GitHub returns), which is moved onto the proxy.
 * Any other absolute URL is returned unchanged.
 *
 * @param string $urlOrPath An API path or an absolute URL.
 * @return string The URL to request.
 */
function gitHubApiUrl(string $urlOrPath): string
{
    $base = rtrim(GITHUB_API_BASE_URL, '/');

    if (preg_match('#^https?://#i', $urlOrPath) !== 1) {
        return $base . '/' . ltrim($urlOrPath, '/');
    }

    $upstream = GITHUB_API_UPSTREAM_URL;
    if (strcasecmp(substr($urlOrPath, 0, strlen($upstream)), $upstream) === 0) {
        $rest = substr($urlOrPath, strlen($upstream));
        if ($rest === '' || in_array($rest[0], ['/', '?'], true)) {
            return $base . $rest;
        }
    }

    return $urlOrPath;
}

/**
 * Reads the application version written to version.txt during deployment.
 *
 * @return string The application version, or "dev" if version.txt is not present.
 */
function getAppVersion(): string
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'dev';

    return $version;
}

/**
 * Builds the User-Agent string used for all outgoing HTTP requests.
 *
 * @return string The User-Agent string.
 */
function getUserAgent(): string
{
    return 'GStraccini-bot-website/' . getAppVersion() . ' (+' . APP_REPOSITORY_URL . ')';
}
