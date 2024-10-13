<?php
session_start();

require_once "github.secrets.php";

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $tokenUrl = 'https://github.com/login/oauth/access_token';
    $postFields = [
        'client_id' => $gitHubClientId,
        'client_secret' => $gitHubClientSecret,
        'code' => $code,
        'redirect_uri' => $gitHubRedirectUri
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $token = json_decode($response, true)['access_token'];

    if ($token) {
        $apiUrl = 'https://api.github.com/user';

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'User-Agent: GStraccini-bot-website/1.0'
        ]);

        $userData = curl_exec($ch);
        curl_close($ch);

        $user = json_decode($userData, true);
        $_SESSION['user'] = $user;
        header('Location: dashboard.php');
        exit();
    } else {
        echo 'Error: Unable to retrieve access token';
    }
} else {
    echo 'Error: Authorization code not found';
}
