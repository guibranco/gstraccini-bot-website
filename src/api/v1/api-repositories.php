<?php
/**
 * API endpoint for listing GitHub repositories
 */

$cacheKey = "repositories";
$token = checkAuth();

$cache = getCache($cacheKey);
if ($cache !== false) {
    exit();
}

$repositories = fetchAllGitHubPages('https://api.github.com/user/repos?per_page=100', $token);

$formattedRepositories = [];
if (is_array($repositories) && count($repositories) > 0) {
    foreach ($repositories as $repo) {
        $formattedRepositories[] = formatRepositoryData($repo);
    }
}

usort($formattedRepositories, function ($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$data = [
    'repositories' => $formattedRepositories
];

session_start();
setCache($data, $cacheKey);
session_write_close();
sendJsonResponse($data, time());
