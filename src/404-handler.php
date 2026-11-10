<?php

require_once "includes/session.php";

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$invalidPath = urlencode(ltrim($requestUri, '/'));
logNotice(
    "Page not found: {path}",
    [
        'path' => sanitizeUrlForLog($requestUri),
        'referer' => sanitizeUrlForLog(($_SERVER['HTTP_REFERER'] ?? '')),
    ],
    'http'
);
header("Location: /dashboard.php?error=404&path=" . $invalidPath, true, 302);
exit();