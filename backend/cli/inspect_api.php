#!/usr/bin/env php
<?php
/**
 * Try to get player statistics to see available stat names
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';

$logger = new Logger(LOG_PATH, 'DEBUG');

echo "Checking API response structure...\n\n";

// Try getting a known working leaderboard with full response details
$url = PLAYFAB_BASE_URL . '/Client/GetLeaderboard';
$payload = [
    'StatisticName' => 'DailyPlay_Fri',
    'StartPosition' => 0,
    'MaxResultsCount' => 1
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Authorization: ' . PLAYFAB_SESSION_TOKEN
    ],
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "Full API Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT);
echo "\n\nAvailable fields:\n";
if (isset($data['data'])) {
    foreach ($data['data'] as $key => $value) {
        echo "  - {$key}: " . gettype($value) . "\n";
    }
}
