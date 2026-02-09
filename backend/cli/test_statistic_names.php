#!/usr/bin/env php
<?php
/**
 * Test different statistic name formats for Tuesday and Thursday
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/PlayFabClient.php';

$logger = new Logger(LOG_PATH, 'DEBUG');
$client = new PlayFabClient(
    PLAYFAB_BASE_URL,
    PLAYFAB_SESSION_TOKEN,
    $logger,
    true
);

// Test different formats for Tuesday (Jan 22)
$testNames = [
    'DailyPlay_Tue',
    'DailyPlay_Tuesday',
    'DailyPlay_TUES',
    'DailyPlay_TUE',
    'Daily_Tue',
    'Daily_Tuesday',
    'Tue',
    'Tuesday'
];

echo "Testing Tuesday (2026-01-22) statistic names:\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($testNames as $name) {
    echo "Testing: {$name}... ";
    $response = $client->getLeaderboard($name, 0, 1);
    
    if ($response && isset($response['data']['Leaderboard'])) {
        $count = count($response['data']['Leaderboard']);
        if ($count > 0) {
            echo "✅ FOUND! {$count} entries\n";
            echo "  Version: " . ($response['data']['Version'] ?? 'null') . "\n";
            break;
        } else {
            echo "❌ Empty\n";
        }
    } else {
        echo "❌ Failed/null\n";
    }
    usleep(100000); // 100ms delay
}

echo "\nTesting Thursday (2026-01-16) statistic names:\n";
echo str_repeat("=", 70) . "\n\n";

$testNames = [
    'DailyPlay_Thu',
    'DailyPlay_Thursday',
    'DailyPlay_THUR',
    'DailyPlay_THURS',
    'DailyPlay_THU',
    'Daily_Thu',
    'Daily_Thursday',
    'Thu',
    'Thursday'
];

foreach ($testNames as $name) {
    echo "Testing: {$name}... ";
    $response = $client->getLeaderboard($name, 0, 1);
    
    if ($response && isset($response['data']['Leaderboard'])) {
        $count = count($response['data']['Leaderboard']);
        if ($count > 0) {
            echo "✅ FOUND! {$count} entries\n";
            echo "  Version: " . ($response['data']['Version'] ?? 'null') . "\n";
            break;
        } else {
            echo "❌ Empty\n";
        }
    } else {
        echo "❌ Failed/null\n";
    }
    usleep(100000);
}
