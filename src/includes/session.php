<?php

$cookie_lifetime = 604800;
session_set_cookie_params([
    'lifetime' => $cookie_lifetime,
    'path' => '/',
    'domain' => 'bot.straccini.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'LAX'
]);
session_start();
$isAuthenticated = isset($_SESSION['user']) === true && isset($_SESSION['token']) === true;
