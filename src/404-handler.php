<?php

require_once "includes/session.php";

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$invalidPath = urlencode(ltrim($requestUri, '/'));
header("Location: /dashboard.php?error=404&path=" . $invalidPath, true, 302);
exit();