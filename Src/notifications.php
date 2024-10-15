<?php
session_start();

if (!isset($_SESSION['token'])) {
    echo json_encode(['error' => 'User not authenticated.']);
    exit();
}

$githubApiUrl = 'https://api.github.com/notifications';
$token = $_SESSION['token'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $githubApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'GStraccini-bot-webssite/1.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: token ' . $token,
    'Accept: application/vnd.github.v3+json'
]);

$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(['error' => 'Failed to fetch notifications.']);
    exit();
}

curl_close($ch);

$notifications = json_decode($response, true);
if (isset($notifications['message']) && $notifications['message'] == 'Bad credentials') {
    echo json_encode(['error' => 'Invalid GitHub token.']);
    exit();
}

echo json_encode($notifications);
