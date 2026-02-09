#!/usr/bin/env php
<?php
/**
 * Test GetLeaderboardAroundPlayer for Tuesday and Thursday
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';

$logger = new Logger(LOG_PATH, 'DEBUG');

// We need to get the user's PlayFabId from a successful request first
$url = PLAYFAB_BASE_URL . '/Client/GetLeaderboard';
$payload = ['StatisticName' => 'DailyPlay_Sat', 'StartPosition' => 0, 'MaxResultsCount' => 10];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Authorization: ' . PLAYFAB_SESSION_TOKEN
    ]
]);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);

// Find user's PlayFabId (should be position 1 = 2nd place based on user's report)
$userPlayFabId = null;
if (isset($data['data']['Leaderboard'][1])) {
    $userPlayFabId = $data['data']['Leaderboard'][1]['PlayFabId'];
    echo "Found your PlayFabId: {$userPlayFabId}\n";
    echo "Display Name: " . $data['data']['Leaderboard'][1]['DisplayName'] . "\n\n";
}

if (!$userPlayFabId) {
    echo "Could not determine your PlayFabId\n";
    exit(1);
}

// Now test GetLeaderboardAroundPlayer for Tuesday
echo "Testing Tuesday with GetLeaderboardAroundPlayer:\n";
echo str_repeat("=", 70) . "\n";

$url = PLAYFAB_BASE_URL . '/Client/GetLeaderboardAroundPlayer';
$payload = [
    'StatisticName' => 'DailyPlay_Tue',
    'PlayFabId' => $userPlayFabId,
    'MaxResultsCount' => 50
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Authorization: ' . PLAYFAB_SESSION_TOKEN
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
$data = json_decode($response, true);

if (isset($data['data']['Leaderboard'])) {
    $count = count($data['data']['Leaderboard']);
    echo "✅ Found {$count} entries!\n\n";
    
    if ($count > 0) {
        echo "Sample entries:\n";
        foreach (array_slice($data['data']['Leaderboard'], 0, 10) as $entry) {
            printf("#%-3d  %-30s  %6d pts\n", 
                $entry['Position'] + 1,
                $entry['DisplayName'] ?? 'Unknown',
                $entry['StatValue']
            );
        }
    }
} else {
    echo "❌ No data\n";
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}

echo "\n\nTesting Thursday with GetLeaderboardAroundPlayer:\n";
echo str_repeat("=", 70) . "\n";

$payload['StatisticName'] = 'DailyPlay_Thu';
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Authorization: ' . PLAYFAB_SESSION_TOKEN
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
$data = json_decode($response, true);

if (isset($data['data']['Leaderboard'])) {
    $count = count($data['data']['Leaderboard']);
    echo "✅ Found {$count} entries!\n\n";
    
    if ($count > 0) {
        echo "Sample entries:\n";
        foreach (array_slice($data['data']['Leaderboard'], 0, 10) as $entry) {
            printf("#%-3d  %-30s  %6d pts\n", 
                $entry['Position'] + 1,
                $entry['DisplayName'] ?? 'Unknown',
                $entry['StatValue']
            );
        }
    }
} else {
    echo "❌ No data\n";
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
}
