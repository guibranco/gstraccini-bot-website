<?php
session_start();

if (!isset($_SESSION['token'])) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

echo json_encode(['error' => 'Failed to fetch notifications.']);
