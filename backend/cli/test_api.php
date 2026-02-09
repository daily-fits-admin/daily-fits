#!/usr/bin/env php
<?php
/**
 * Quick API test script - fetches just 1 leaderboard entry to verify token
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/PlayFabClient.php';

// Check configuration
$configErrors = validateConfig();
if (!empty($configErrors)) {
    echo "Configuration errors:\n";
    foreach ($configErrors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

// Determine today's statistic name
$today = new DateTime();
$statisticName = getStatisticName($today);

echo "API Test - Single Request\n";
echo "=========================\n";
echo "Date: " . $today->format('Y-m-d') . "\n";
echo "Statistic: {$statisticName}\n";
echo "Token: " . substr(PLAYFAB_SESSION_TOKEN, 0, 20) . "...\n";
echo "\n";
echo "Fetching first 5 entries only...\n\n";

// Initialize logger and client
$logger = new Logger(LOG_PATH, 'DEBUG');
$client = new PlayFabClient(
    PLAYFAB_BASE_URL,
    PLAYFAB_SESSION_TOKEN,
    $logger,
    true  // Execute requests
);

// Fetch just 5 entries
$response = $client->getLeaderboard($statisticName, 0, 5);

if ($response === null) {
    echo "❌ API request failed!\n";
    echo "Check the log file for details: " . LOG_PATH . "\n";
    exit(1);
}

// Check for dry-run
if (isset($response['dry_run'])) {
    echo "⚠️  Still in dry-run mode (this shouldn't happen)\n";
    exit(1);
}

// Parse response
if (!isset($response['data']['Leaderboard'])) {
    echo "❌ Unexpected response format\n";
    echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$leaderboard = $response['data']['Leaderboard'];

echo "✅ API request successful!\n\n";
echo "Retrieved " . count($leaderboard) . " entries:\n";
echo str_repeat("-", 70) . "\n";

foreach ($leaderboard as $entry) {
    $position = $entry['Position'] + 1;  // Convert from 0-indexed to 1-indexed
    $displayName = $entry['DisplayName'] ?? 'Unknown';
    $score = $entry['StatValue'];
    $playFabId = substr($entry['PlayFabId'], 0, 16) . '...';
    
    printf("#%-3d  %-30s  %6d pts  (ID: %s)\n", 
        $position, 
        $displayName, 
        $score, 
        $playFabId
    );
}

echo str_repeat("-", 70) . "\n";
echo "\n✅ Token is valid and working!\n";
echo "You can now run the full fetch with: php fetch_daily.php --execute\n";
