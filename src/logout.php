<?php
require_once "includes/session.php";

$username = $_SESSION['user']['login'] ?? null;
getLogStream()?->info(
    $username !== null ? "User logged out: {$username}" : "User logged out",
    ['username' => $username],
    'auth'
);

$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: index.php');
exit();
