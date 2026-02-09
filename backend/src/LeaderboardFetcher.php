<?php
/**
 * LeaderboardFetcher class for the Fights in Tight Spaces Leaderboard Collector
 * 
 * Orchestrates fetching complete leaderboard data with pagination and data normalization.
 */

require_once __DIR__ . '/PlayFabClient.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class LeaderboardFetcher {
    private PlayFabClient $client;
    private Database $db;
    private Logger $logger;
    private int $maxResultsPerPage;
    private int $requestDelayMs;
    
    /**
     * Constructor
     * 
     * @param PlayFabClient $client PlayFab API client
     * @param Database $db Database instance
     * @param Logger $logger Logger instance
     * @param int $maxResultsPerPage Maximum results per API call
     * @param int $requestDelayMs Delay between requests in milliseconds
     */
    public function __construct(
        PlayFabClient $client,
        Database $db,
        Logger $logger,
        int $maxResultsPerPage = 50,
        int $requestDelayMs = 100
    ) {
        $this->client = $client;
        $this->db = $db;
        $this->logger = $logger;
        $this->maxResultsPerPage = $maxResultsPerPage;
        $this->requestDelayMs = $requestDelayMs;
    }
    
    /**
     * Fetch complete leaderboard for a given statistic
     * 
     * @param string $statisticName The statistic name (e.g., "DailyPlay_Mon")
     * @param string $statDate The date for the leaderboard (YYYY-MM-DD format)
     * @return array Summary of the fetch operation
     */
    public function fetchLeaderboard(string $statisticName, string $statDate): array {
        $this->logger->info('Starting leaderboard fetch', [
            'statistic_name' => $statisticName,
            'stat_date' => $statDate
        ]);
        
        $allEntries = [];
        $startPosition = 0;
        $hasMore = true;
        $totalFetched = 0;
        
        while ($hasMore) {
            $this->logger->debug('Fetching page', [
                'start_position' => $startPosition,
                'max_results' => $this->maxResultsPerPage
            ]);
            
            $response = $this->client->getLeaderboard(
                $statisticName,
                $startPosition,
                $this->maxResultsPerPage
            );
            
            if ($response === null) {
                $this->logger->error('Failed to fetch leaderboard page', [
                    'start_position' => $startPosition
                ]);
                break;
            }
            
            // Handle dry-run mode
            if (isset($response['dry_run']) && $response['dry_run'] === true) {
                $this->logger->info('Dry-run mode: No actual data fetched');
                return [
                    'success' => true,
                    'dry_run' => true,
                    'total_entries' => 0,
                    'players_updated' => 0,
                    'scores_updated' => 0
                ];
            }
            
            $leaderboard = $response['data']['Leaderboard'] ?? [];
            $pageSize = count($leaderboard);
            
            $this->logger->debug('Page fetched', ['entries' => $pageSize]);
            
            if ($pageSize === 0) {
                $hasMore = false;
                break;
            }
            
            $allEntries = array_merge($allEntries, $leaderboard);
            $totalFetched += $pageSize;
            $startPosition += $pageSize;
            
            // Check if we've reached the end
            if ($pageSize < $this->maxResultsPerPage) {
                $hasMore = false;
            }
            
            // Rate limiting
            if ($hasMore && $this->requestDelayMs > 0) {
                usleep($this->requestDelayMs * 1000);
            }
        }
        
        $this->logger->info('Fetch completed', ['total_entries' => $totalFetched]);
        
        // Store data in database
        $summary = $this->storeLeaderboardData($allEntries, $statisticName, $statDate);
        
        // Record the run
        $this->db->recordRun([
            'stat_date' => $statDate,
            'statistic_name' => $statisticName,
            'entry_count' => $totalFetched,
            'api_version' => 'v1'
        ]);
        
        return $summary;
    }
    
    /**
     * Store leaderboard entries in the database
     * 
     * @param array $entries Leaderboard entries from API
     * @param string $statisticName The statistic name
     * @param string $statDate The date for the leaderboard
     * @return array Summary of storage operation
     */
    private function storeLeaderboardData(array $entries, string $statisticName, string $statDate): array {
        $playersUpdated = 0;
        $scoresUpdated = 0;
        
        foreach ($entries as $entry) {
            // Extract player data
            $playerData = $this->normalizePlayerData($entry);
            if ($this->db->upsertPlayer($playerData)) {
                $playersUpdated++;
            }
            
            // Extract score data
            $scoreData = $this->normalizeScoreData($entry, $statisticName, $statDate);
            if ($this->db->upsertDailyScore($scoreData)) {
                $scoresUpdated++;
            }
        }
        
        $this->logger->info('Data stored', [
            'players_updated' => $playersUpdated,
            'scores_updated' => $scoresUpdated
        ]);
        
        return [
            'success' => true,
            'dry_run' => false,
            'total_entries' => count($entries),
            'players_updated' => $playersUpdated,
            'scores_updated' => $scoresUpdated
        ];
    }
    
    /**
     * Normalize player data from API response
     * 
     * @param array $entry Leaderboard entry
     * @return array Normalized player data
     */
    private function normalizePlayerData(array $entry): array {
        $profile = $entry['Profile'] ?? [];
        $linkedAccounts = $profile['LinkedAccounts'] ?? [];
        
        // Extract platform info (preferring GOG if available)
        $platform = null;
        $platformUserId = null;
        
        foreach ($linkedAccounts as $account) {
            if (isset($account['Platform']) && strtoupper($account['Platform']) === 'GOG') {
                $platform = 'GOG';
                $platformUserId = $account['PlatformUserId'] ?? null;
                break;
            }
        }
        
        // Fallback to first linked account if GOG not found
        if ($platform === null && !empty($linkedAccounts)) {
            $platform = $linkedAccounts[0]['Platform'] ?? null;
            $platformUserId = $linkedAccounts[0]['PlatformUserId'] ?? null;
        }
        
        return [
            'playfab_id' => $entry['PlayFabId'],
            'display_name' => $entry['DisplayName'] ?? $profile['DisplayName'] ?? null,
            'platform' => $platform,
            'platform_user_id' => $platformUserId,
            'first_seen' => date('Y-m-d'),
            'last_seen' => date('Y-m-d')
        ];
    }
    
    /**
     * Normalize score data from API response
     * 
     * @param array $entry Leaderboard entry
     * @param string $statisticName The statistic name
     * @param string $statDate The date for the score
     * @return array Normalized score data
     */
    private function normalizeScoreData(array $entry, string $statisticName, string $statDate): array {
        return [
            'stat_date' => $statDate,
            'statistic_name' => $statisticName,
            'position' => $entry['Position'],
            'playfab_id' => $entry['PlayFabId'],
            'score' => $entry['StatValue']
        ];
    }
}
