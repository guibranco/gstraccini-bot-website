<?php
require_once "includes/session.php";
require_once "includes/constants.php";
require_once "github.secrets.php";
require_once "webhook.secrets.php";

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
            'User-Agent: ' . getUserAgent(),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        $response = $this->makeCurlRequest(self::GITHUB_TOKEN_URL, $postData, $headers);
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token']) || empty($tokenData['access_token'])) {
            logError(
                "GitHub did not return an access token: {githubError}",
                ['githubError' => $tokenData['error'] ?? 'unknown', 'githubErrorDescription' => $tokenData['error_description'] ?? null],
                'auth'
            );
            throw new Exception('Failed to retrieve access token from GitHub');
        }

        logDebug("Exchanged OAuth code for an access token with scope {scope}", ['scope' => $tokenData['scope'] ?? null], 'auth');
        
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
            "User-Agent: " . getUserAgent(),
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
            logWarning(
                "Could not fetch installations for {username}: {error}",
                ['username' => $userLogin, 'error' => $e->getMessage()],
                'auth'
            );
            $installations = [];
        }
        
        try {
            $organizationsResponse = $this->makeGitHubApiRequest("/users/{$userLogin}/orgs", $token);
            $organizations = $organizationsResponse['body'];
        } catch (Exception $e) {
            // Organizations might be private or unavailable
            logWarning(
                "Could not fetch organizations for {username}: {error}",
                ['username' => $userLogin, 'error' => $e->getMessage()],
                'auth'
            );
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
            'User-Agent: ' . getUserAgent(),
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
        $durationMs = (int) round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);
        $error = curl_error($curl);
        $method = $postData !== null ? 'POST' : 'GET';

        curl_close($curl);

        if ($response === false) {
            logError(
                "Remote {method} {url} failed after {durationMs} ms: {error}",
                ['method' => $method, 'url' => $url, 'durationMs' => $durationMs, 'error' => $error],
                'remote-calls'
            );
            throw new Exception("cURL Error: {$error}");
        }

        if ($httpCode >= 400) {
            logWarning(
                "Remote {method} {url} returned HTTP {httpCode} in {durationMs} ms",
                ['method' => $method, 'url' => $url, 'httpCode' => $httpCode, 'durationMs' => $durationMs],
                'remote-calls'
            );
            throw new Exception("HTTP Error {$httpCode}: Request failed");
        }

        logInfo(
            "Remote {method} {url} returned HTTP {httpCode} in {durationMs} ms",
            ['method' => $method, 'url' => $url, 'httpCode' => $httpCode, 'durationMs' => $durationMs],
            'remote-calls'
        );

        return $response;
    }

    /**
     * Redirects to error page with message
     */
    public function redirectWithError(string $message): void
    {
        logWarning("Login failed: {reason}", ['reason' => $message], 'auth');

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

        logInfo(
            "User {username} logged in with {installations} installation(s) and {organizations} organization(s)",
            [
                'username' => $userData['login'] ?? 'unknown',
                'userId' => $userData['id'] ?? null,
                'installations' => count($installations),
                'organizations' => count($organizations),
            ],
            'auth'
        );

        $redirectUrl = $_SESSION['redirectUrl'] ?? 'dashboard.php';
        unset($_SESSION['redirectUrl']);
        logDebug("Redirecting {username} to {redirectUrl} after login", ['username' => $userData['login'] ?? 'unknown', 'redirectUrl' => $redirectUrl], 'auth');

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
        logError(
            "Token sync webhook for {username} failed: {error}",
            ['username' => $userData['user']['login'] ?? 'unknown', 'error' => $e->getMessage()],
            'auth'
        );
    }
    
    // Complete login process
    $oauthHandler->completeLogin(
        $accessToken,
        $userData['user'],
        $userData['installations'],
        $userData['organizations']
    );
    
} catch (Exception $e) {
    logError("OAuth callback failed: {error}", ['error' => $e->getMessage(), 'exception' => get_class($e)], 'auth');
    
    if (isset($oauthHandler)) {
        $oauthHandler->redirectWithError($e->getMessage());
    } else {
        header('Location: signin.php?error=' . urlencode('Authentication failed'));
        exit();
    }
}