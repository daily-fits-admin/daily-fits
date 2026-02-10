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
            $acctPlatform = $account['Platform'] ?? '';
            $acctUserId = $account['PlatformUserId'] ?? '';

            // Prefer explicit GOG platform
            if (strtoupper($acctPlatform) === 'GOG') {
                $platform = 'GOG';
                $platformUserId = $acctUserId ?: null;
                break;
            }

            // Some entries come back as Platform='Custom' but have a prefixed PlatformUserId like "[GOG]..."
            if (stripos($acctUserId, '[GOG]') !== false) {
                $platform = 'GOG';
                // strip the [GOG] prefix if present
                $platformUserId = trim(str_ireplace('[GOG]', '', $acctUserId));
                break;
            }
        }
        
        // Fallback to first linked account if GOG not found
        if ($platform === null && !empty($linkedAccounts)) {
            $first = $linkedAccounts[0];
            $firstPlatform = $first['Platform'] ?? null;
            $firstUserId = $first['PlatformUserId'] ?? null;

            // Handle Common custom prefixes
            if ($firstUserId && stripos($firstUserId, '[GOG]') !== false) {
                $platform = 'GOG';
                $platformUserId = trim(str_ireplace('[GOG]', '', $firstUserId));
            } else {
                $platform = $firstPlatform;
                $platformUserId = $firstUserId;
            }
        }
        
        // Sanitize display name and platform user id to remove control chars
        $displayNameRaw = $entry['DisplayName'] ?? $profile['DisplayName'] ?? null;
        $displayName = $this->sanitizeString($displayNameRaw);
        $platformUserId = $this->sanitizeString($platformUserId);

        return [
            'playfab_id' => $entry['PlayFabId'],
            'display_name' => $displayName,
            'platform' => $platform,
            'platform_user_id' => $platformUserId,
            'first_seen' => date('Y-m-d'),
            'last_seen' => date('Y-m-d')
        ];
    }

    /**
     * Sanitize a string by trimming, removing control characters and applying
     * Unicode normalization (if available).
     *
     * @param string|null $s
     * @return string|null
     */
    private function sanitizeString(?string $s): ?string {
        if ($s === null) return null;
        $s = trim($s);
        // Remove C0 control chars and DEL
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', '', $s);
        // Apply Unicode normalization (if ext-intl available)
        if (class_exists('Normalizer')) {
            $s = Normalizer::normalize($s, Normalizer::FORM_KC);
        }
        return $s === '' ? null : $s;
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
