<?php
$cookie_lifetime = 604800;
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => 'bot.straccini.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

if (!isset($_SESSION['token'])) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

echo json_encode(['error' => 'Failed to fetch notifications.']);
