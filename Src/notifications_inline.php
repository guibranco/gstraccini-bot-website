<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

echo json_encode(['error' => 'Failed to fetch notifications.']);
