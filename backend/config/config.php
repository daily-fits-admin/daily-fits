<?php
/**
 * Configuration file for Fights in Tight Spaces Daily Leaderboard Collector
 * 
 * This file loads environment variables and provides configuration constants.
 * Never commit .env file to version control.
 */

// Load environment variables from .env file if it exists
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
            }
        }
    }
}

// PlayFab API Configuration
define('PLAYFAB_BASE_URL', getenv('PLAYFAB_BASE_URL') ?: 'https://d155a.playfabapi.com');
define('PLAYFAB_SESSION_TOKEN', getenv('PLAYFAB_SESSION_TOKEN') ?: '');

// Database Configuration
define('DB_TYPE', getenv('DB_TYPE') ?: 'sqlite');

// Convert relative paths to absolute paths
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/../../data/fits.db';
if ($dbPath[0] !== '/') {
    // Relative path - make it absolute from the repo root
    $dbPath = __DIR__ . '/../../' . ltrim($dbPath, './');
}
define('DB_PATH', $dbPath);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'fits_leaderboard');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Logging Configuration
$logPath = getenv('LOG_PATH') ?: __DIR__ . '/../../data/fits.log';
if ($logPath[0] !== '/') {
    // Relative path - make it absolute from the repo root
    $logPath = __DIR__ . '/../../' . ltrim($logPath, './');
}
define('LOG_PATH', $logPath);
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO'); // DEBUG, INFO, WARNING, ERROR

// API Configuration
define('API_MAX_RESULTS_PER_PAGE', 50);
define('API_REQUEST_DELAY_MS', 100); // Delay between API calls in milliseconds

// Day mapping (as used in PlayFab statistic names)
define('DAY_MAPPING', [
    1 => 'Mon',
    2 => 'Tues',    // 4 letters (not Tue!)
    3 => 'Wed',
    4 => 'Thurs',   // 5 letters (not Thu!)
    5 => 'Fri',
    6 => 'Sat',
    7 => 'Sun'
]);

/**
 * Get the PlayFab statistic name for a given date
 * 
 * @param DateTime $date The date to get the statistic name for
 * @return string The statistic name (e.g., "DailyPlay_Mon")
 */
function getStatisticName(DateTime $date): string {
    $dayOfWeek = (int)$date->format('N'); // 1 (Monday) through 7 (Sunday)
    $dayName = DAY_MAPPING[$dayOfWeek];
    return "DailyPlay_{$dayName}";
}

/**
 * Validate that required configuration is present
 * 
 * @return array Array of validation errors (empty if valid)
 */
function validateConfig(): array {
    $errors = [];
    
    if (empty(PLAYFAB_SESSION_TOKEN)) {
        $errors[] = 'PLAYFAB_SESSION_TOKEN is not set';
    }
    
    if (DB_TYPE === 'sqlite' && !is_writable(dirname(DB_PATH))) {
        $errors[] = 'Database directory is not writable: ' . dirname(DB_PATH);
    }
    
    if (!is_writable(dirname(LOG_PATH))) {
        $errors[] = 'Log directory is not writable: ' . dirname(LOG_PATH);
    }
    
    return $errors;
}
