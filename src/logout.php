<?php
require_once "includes/session.php";
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
session_regenerate_id();
header('Location: index.php');
exit();
