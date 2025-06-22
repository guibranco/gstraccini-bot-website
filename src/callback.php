<?php
require_once "includes/session.php";
require_once "github.secrets.php";
require_once "webhook.secrets.php";

use Exception;

/**
 * GitHub OAuth Callback Handler
 * 
 * This script handles the OAuth callback from GitHub, exchanges the authorization code
 * for an access token, fetches user data, and stores it in the session.
 */

class GitHubOAuthHandler 
{
    private const GITHUB_TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const GITHUB_API_BASE = 'https://api.github.com';
    private const USER_AGENT = 'GStraccini-bot-website/1.0 (+https://github.com/guibranco/gstraccini-bot-website)';
    
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $webhookUrl;
    private string $webhookSecret;
    
    public function __construct(string $clientId, string $clientSecret, string $redirectUri, string $webhookUrl, string $webhookSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->webhookUrl = $webhookUrl;
        $this->webhookSecret = $webhookSecret;
    }
    
    /**
     * Validates the OAuth state parameter to prevent CSRF attacks
     */
    public function validateState(): bool
    {
        return isset($_GET['state'], $_SESSION['oauth_state']) 
            && $_GET['state'] === $_SESSION['oauth_state'];
    }
    
    /**
     * Exchanges authorization code for access token
     */
    public function exchangeCodeForToken(string $code): array
    {
        $postData = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri
        ]);
        
        $headers = [
            'Accept: application/json',
            'User-Agent: ' . self::USER_AGENT,
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $response = $this->makeCurlRequest(self::GITHUB_TOKEN_URL, $postData, $headers);
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token']) || empty($tokenData['access_token'])) {
            throw new Exception('Failed to retrieve access token from GitHub');
        }
        
        return $tokenData;
    }
    
    /**
     * Makes authenticated requests to GitHub API
     */
    public function makeGitHubApiRequest(string $endpoint, string $token): array
    {
        $url = self::GITHUB_API_BASE . $endpoint;
        $headers = [
            "Authorization: Bearer {$token}",
            "User-Agent: " . self::USER_AGENT,
            "Accept: application/vnd.github+json",
            "X-GitHub-Api-Version: 2022-11-28"
        ];
        
        $response = $this->makeCurlRequest($url, null, $headers, true);
        $headerSize = curl_getinfo($this->lastCurlHandle, CURLINFO_HEADER_SIZE);
        
        $headers = substr($response, 0, $headerSize);
        $body = json_decode(substr($response, $headerSize), true);
        
        if (isset($body['message'])) {
            throw new Exception("GitHub API Error: " . $body['message']);
        }
        
        return ['headers' => $headers, 'body' => $body];
    }
    
    /**
     * Fetches all required user data from GitHub API in parallel-like fashion
     */
    public function fetchUserData(string $token): array
    {
        // Fetch user profile
        $userResponse = $this->makeGitHubApiRequest('/user', $token);
        $userData = $userResponse['body'];
        
        // Parse first and last name if available
        if (isset($userData['name']) && !empty($userData['name'])) {
            $userData = $this->parseUserName($userData);
        }
        
        $userLogin = $userData['login'] ?? '';
        if (empty($userLogin)) {
            throw new Exception('Unable to retrieve user login from GitHub');
        }
        
        // Fetch installations and organizations
        try {
            $installationsResponse = $this->makeGitHubApiRequest('/user/installations', $token);
            $installations = $installationsResponse['body'];
        } catch (Exception $e) {
            // Installations might not be available for all users
            $installations = [];
        }
        
        try {
            $organizationsResponse = $this->makeGitHubApiRequest("/users/{$userLogin}/orgs", $token);
            $organizations = $organizationsResponse['body'];
        } catch (Exception $e) {
            // Organizations might be private or unavailable
            $organizations = [];
        }
        
        return [
            'user' => $userData,
            'installations' => $installations,
            'organizations' => $organizations
        ];
    }
    
    /**
     * Parses user's full name into first and last name
     */
    private function parseUserName(array $userData): array
    {
        if (preg_match('/^(\w+)(?:\s+[\w\s]+)?\s+(\w+)$/', $userData['name'], $matches)) {
            $userData['first_name'] = $matches[1];
            $userData['last_name'] = $matches[2];
        }
        return $userData;
    }
    
    /**
     * Updates token data via webhook
     */
    public function updateTokenData(array $tokenData, array $userData, array $installationData): void
    {
        $payload = [
            'token' => $tokenData,
            'user' => $userData,
            'installations' => $installationData
        ];
        
        $headers = [
            'Content-Type: application/json',
            'User-Agent: ' . self::USER_AGENT,
            'Authorization: token ' . $this->webhookSecret
        ];
        
        $this->makeCurlRequest($this->webhookUrl, json_encode($payload), $headers);
    }
    
    private $lastCurlHandle;
    
    /**
     * Makes HTTP requests using cURL with proper error handling
     */
    private function makeCurlRequest(string $url, ?string $postData = null, array $headers = [], bool $includeHeaders = false): string
    {
        $curl = curl_init($url);
        
        if (!$curl) {
            throw new Exception('Failed to initialize cURL');
        }
        
        $this->lastCurlHandle = $curl;
        
        // Basic cURL options
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers
        ]);
        
        if ($postData !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        }
        
        if ($includeHeaders) {
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($response === false) {
            throw new Exception("cURL Error: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new Exception("HTTP Error {$httpCode}: Request failed");
        }
        
        return $response;
    }
    
    /**
     * Redirects to error page with message
     */
    public function redirectWithError(string $message): void
    {
        $encodedMessage = urlencode($message);
        header("Location: signin.php?error={$encodedMessage}");
        exit();
    }
    
    /**
     * Stores user data in session and redirects to destination
     */
    public function completeLogin(string $token, array $userData, array $installations, array $organizations): void
    {
        $_SESSION['token'] = $token;
        $_SESSION['user'] = $userData;
        $_SESSION['installations'] = $installations;
        $_SESSION['organizations'] = $organizations;
        
        $redirectUrl = $_SESSION['redirectUrl'] ?? 'dashboard.php';
        unset($_SESSION['redirectUrl']);
        
        session_regenerate_id(true); // More secure - delete old session
        
        header("Location: {$redirectUrl}");
        exit();
    }
}

// Main execution
try {
    $oauthHandler = new GitHubOAuthHandler(
        $gitHubClientId,
        $gitHubClientSecret, 
        $gitHubRedirectUri,
        $webhookUrl,
        $webhookSecret
    );
    
    // Validate state parameter
    if (!$oauthHandler->validateState()) {
        $oauthHandler->redirectWithError('Invalid state parameter');
    }
    
    // Check for authorization code
    if (!isset($_GET['code']) || empty($_GET['code'])) {
        $oauthHandler->redirectWithError('Authorization code not found');
    }
    
    $authCode = $_GET['code'];
    
    // Exchange code for token
    $tokenData = $oauthHandler->exchangeCodeForToken($authCode);
    $accessToken = $tokenData['access_token'];
    
    // Fetch user data from GitHub
    $userData = $oauthHandler->fetchUserData($accessToken);
    
    // Update token data via webhook (fire and forget)
    try {
        $oauthHandler->updateTokenData(
            $tokenData, 
            $userData['user'], 
            $userData['installations']
        );
    } catch (Exception $e) {
        // Log webhook error but don't fail the login process
        error_log("Webhook update failed: " . $e->getMessage());
    }
    
    // Complete login process
    $oauthHandler->completeLogin(
        $accessToken,
        $userData['user'],
        $userData['installations'],
        $userData['organizations']
    );
    
} catch (Exception $e) {
    error_log("OAuth callback error: " . $e->getMessage());
    
    if (isset($oauthHandler)) {
        $oauthHandler->redirectWithError($e->getMessage());
    } else {
        header('Location: signin.php?error=' . urlencode('Authentication failed'));
        exit();
    }
}