<?php
/**
 * Application-wide constants and helpers.
 */

const APP_REPOSITORY_URL = 'https://github.com/guibranco/gstraccini-bot-website';

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
