<?php
require_once "includes/session.php";

if ($isAuthenticated === false) {
    http_response_code(401); 
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

echo json_encode(['error' => 'Failed to fetch notifications.']);
