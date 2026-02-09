<?php
/**
 * PlayFabClient class for the Fights in Tight Spaces Leaderboard Collector
 * 
 * Handles HTTP communication with PlayFab API endpoints.
 * NO HTTP REQUESTS ARE EXECUTED AUTOMATICALLY - all requests are logged but not sent
 * unless explicitly enabled via the $executeRequests parameter.
 */

require_once __DIR__ . '/Logger.php';

class PlayFabClient {
    private string $baseUrl;
    private string $sessionToken;
    private Logger $logger;
    private bool $executeRequests;
    
    /**
     * Constructor
     * 
     * @param string $baseUrl PlayFab API base URL
     * @param string $sessionToken Session token (X-Authorization header)
     * @param Logger $logger Logger instance
     * @param bool $executeRequests Whether to actually execute HTTP requests
     */
    public function __construct(
        string $baseUrl, 
        string $sessionToken, 
        Logger $logger,
        bool $executeRequests = false
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->sessionToken = $sessionToken;
        $this->logger = $logger;
        $this->executeRequests = $executeRequests;
        
        if (!$executeRequests) {
            $this->logger->warning('PlayFabClient initialized in DRY-RUN mode - no HTTP requests will be executed');
        }
    }
    
    /**
     * Get leaderboard data from PlayFab
     * 
     * @param string $statisticName The statistic name (e.g., "DailyPlay_Mon")
     * @param int $startPosition Starting position for pagination
     * @param int $maxResults Maximum number of results to return
     * @return array|null The API response data, or null on failure
     */
    public function getLeaderboard(
        string $statisticName, 
        int $startPosition = 0, 
        int $maxResults = 50
    ): ?array {
        $endpoint = '/Client/GetLeaderboard';
        $payload = [
            'StatisticName' => $statisticName,
            'StartPosition' => $startPosition,
            'MaxResultsCount' => $maxResults,
            'ProfileConstraints' => [
                'ShowDisplayName' => true,
                'ShowLinkedAccounts' => true
            ]
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    /**
     * Get leaderboard around a specific player
     * 
     * @param string $statisticName The statistic name
     * @param string $playFabId The PlayFab player ID
     * @param int $maxResults Maximum number of results
     * @return array|null The API response data, or null on failure
     */
    public function getLeaderboardAroundPlayer(
        string $statisticName,
        string $playFabId,
        int $maxResults = 1
    ): ?array {
        $endpoint = '/Client/GetLeaderboardAroundPlayer';
        $payload = [
            'StatisticName' => $statisticName,
            'PlayFabId' => $playFabId,
            'MaxResultsCount' => $maxResults,
            'ProfileConstraints' => [
                'ShowDisplayName' => true,
                'ShowLinkedAccounts' => true
            ],
            'Version' => null
        ];
        
        return $this->makeRequest($endpoint, $payload);
    }
    
    /**
     * Make a POST request to the PlayFab API
     * 
     * @param string $endpoint API endpoint path
     * @param array $payload Request payload
     * @return array|null Response data or null on failure
     */
    private function makeRequest(string $endpoint, array $payload): ?array {
        $url = $this->baseUrl . $endpoint;
        
        $this->logger->info('API Request Intent', [
            'url' => $url,
            'payload' => $payload,
            'execute' => $this->executeRequests
        ]);
        
        // If not executing requests, return mock data
        if (!$this->executeRequests) {
            $this->logger->warning('DRY-RUN: Request not executed', ['endpoint' => $endpoint]);
            return [
                'code' => 200,
                'status' => 'OK',
                'data' => [
                    'Leaderboard' => [],
                    'Version' => 0
                ],
                'dry_run' => true
            ];
        }
        
        // Validate session token
        if (empty($this->sessionToken)) {
            $this->logger->error('Session token is empty - cannot make request');
            return null;
        }
        
        try {
            $ch = curl_init($url);
            
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Authorization: ' . $this->sessionToken
                ],
                CURLOPT_TIMEOUT => 30
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $this->logger->error('cURL error', ['error' => $curlError]);
                return null;
            }
            
            $this->logger->info('API Response received', ['http_code' => $httpCode]);
            
            if ($httpCode === 401) {
                $this->logger->error('Authentication failed - session token may be expired');
                return null;
            }
            
            if ($httpCode !== 200) {
                $this->logger->error('API request failed', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse JSON response', [
                    'error' => json_last_error_msg()
                ]);
                return null;
            }
            
            return $data;
            
        } catch (Exception $e) {
            $this->logger->error('Request exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
