#!/usr/bin/env php
<?php
/**
 * CLI script to fetch daily leaderboard data from Fights in Tight Spaces
 * 
 * Usage:
 *   php fetch_daily.php [options]
 * 
 * Options:
 *   --date=YYYY-MM-DD    Fetch leaderboard for specific date (default: today)
 *   --execute            Actually execute HTTP requests (dry-run by default)
 *   --init-db            Initialize database schema before fetching
 *   --help               Show this help message
 * 
 * Examples:
 *   php fetch_daily.php                        # Dry-run for today
 *   php fetch_daily.php --execute              # Execute for today
 *   php fetch_daily.php --date=2026-01-20      # Dry-run for specific date
 *   php fetch_daily.php --execute --date=2026-01-20  # Execute for specific date
 */

// Load configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/PlayFabClient.php';
require_once __DIR__ . '/../src/LeaderboardFetcher.php';

// Parse command line arguments
$options = getopt('', ['date:', 'from:', 'to:', 'execute', 'init-db', 'quiet', 'help']);

// Show help
if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Validate configuration
$configErrors = validateConfig();
if (!empty($configErrors)) {
    echo "Configuration errors:\n";
    foreach ($configErrors as $error) {
        echo "  - {$error}\n";
    }
    echo "\nPlease check your .env file or environment variables.\n";
    exit(1);
}

// Determine date(s)
$datesToFetch = [];
if (isset($options['from'])) {
    $from = new DateTime($options['from']);
    $to = isset($options['to']) ? new DateTime($options['to']) : new DateTime();
    $period = new DatePeriod($from, new DateInterval('P1D'), (clone $to)->modify('+1 day'));
    foreach ($period as $d) {
        $datesToFetch[] = $d->format('Y-m-d');
    }
} else {
    $date = isset($options['date']) ? new DateTime($options['date']) : new DateTime();
    $datesToFetch[] = $date->format('Y-m-d');
}

$quietMode = isset($options['quiet']);

// Check if we should execute requests
$executeRequests = isset($options['execute']);

// Initialize components
$logger = new Logger(LOG_PATH, LOG_LEVEL);
$logger->info('=== Fetch Daily Script Started ===', [
    'dates' => $datesToFetch,
    'execute_requests' => $executeRequests
]);

// Initialize database
$dbConfig = [];
if (DB_TYPE === 'sqlite') {
    $dbConfig = ['path' => DB_PATH];
} else {
    $dbConfig = [
        'host' => DB_HOST,
        'dbname' => DB_NAME,
        'user' => DB_USER,
        'pass' => DB_PASS
    ];
}

try {
    $db = new Database(DB_TYPE, $dbConfig, $logger);
    
    // Initialize database schema if requested
    if (isset($options['init-db'])) {
        $logger->info('Initializing database schema');
        $migrationPath = __DIR__ . '/../migrations/001_init.sql';
        if ($db->runMigrations($migrationPath)) {
            echo "Database schema initialized successfully.\n";
        } else {
            echo "Failed to initialize database schema. Check logs for details.\n";
            exit(1);
        }
    }
    
} catch (Exception $e) {
    $logger->error('Database initialization failed', ['error' => $e->getMessage()]);
    echo "Database error: {$e->getMessage()}\n";
    exit(1);
}

// Initialize PlayFab client
$client = new PlayFabClient(
    PLAYFAB_BASE_URL,
    PLAYFAB_SESSION_TOKEN,
    $logger,
    $executeRequests
);

// Initialize fetcher
$fetcher = new LeaderboardFetcher(
    $client,
    $db,
    $logger,
    API_MAX_RESULTS_PER_PAGE,
    API_REQUEST_DELAY_MS
);

// Display execution mode
// Loop over requested dates
$overallSuccess = true;
$overallSummary = ['runs' => []];
foreach ($datesToFetch as $statDate) {
    $dateObj = new DateTime($statDate);
    $statisticName = getStatisticName($dateObj);

    if (!$quietMode) {
        if (!$executeRequests) {
            echo "\nDRY-RUN MODE: no HTTP requests will be executed. Use --execute to run.\n\n";
        }
        echo "Fetch Details:\n";
        echo "  Date:           {$statDate}\n";
        echo "  Statistic:      {$statisticName}\n";
        echo "  Execute:        " . ($executeRequests ? 'YES' : 'NO (dry-run)') . "\n";
        echo "  Database:       " . DB_PATH . "\n";
        echo "  Log file:       " . LOG_PATH . "\n\n";
        if ($executeRequests) {
            echo "Starting leaderboard fetch...\n\n";
        }
        echo "Fetching leaderboard...\n";
    }

    $summary = $fetcher->fetchLeaderboard($statisticName, $statDate);

    $overallSummary['runs'][] = array_merge(['stat_date' => $statDate, 'statistic_name' => $statisticName], $summary);
    if (!$summary['success']) {
        $overallSuccess = false;
    }

    if (!$quietMode) {
        echo "\nFetch Summary for {$statDate}:\n";
        echo "  Success:        " . ($summary['success'] ? 'YES' : 'NO') . "\n";
        if (isset($summary['dry_run']) && $summary['dry_run']) {
            echo "  Mode:           DRY-RUN (no data fetched)\n";
        } else {
            echo "  Total entries:  {$summary['total_entries']}\n";
            echo "  Players updated: {$summary['players_updated']}\n";
            echo "  Scores updated:  {$summary['scores_updated']}\n";
        }
        echo "\n";
    }

    $logger->info('=== Fetch Daily Run Completed ===', $summary + ['stat_date' => $statDate, 'statistic_name' => $statisticName]);
}

$logger->info('=== Fetch Daily Script Completed ===', ['success' => $overallSuccess, 'runs' => $overallSummary['runs']]);

if ($overallSuccess) {
    if (!$quietMode) echo "✓ All requested fetches completed successfully.\n";
    exit(0);
} else {
    if (!$quietMode) echo "✗ Some fetches failed. Check logs for details.\n";
    exit(1);
}

/**
 * Display help message
 */
function showHelp(): void {
    echo <<<HELP
Fights in Tight Spaces - Daily Leaderboard Fetcher

Usage:
  php fetch_daily.php [options]

Options:
  --date=YYYY-MM-DD    Fetch leaderboard for specific date (default: today)
    --from=YYYY-MM-DD    Fetch a range of dates starting from this date
    --to=YYYY-MM-DD      End date for a range (used with --from)
  --execute            Actually execute HTTP requests (dry-run by default)
  --init-db            Initialize database schema before fetching
    --quiet              Suppress verbose console output (logs still written)
  --help               Show this help message

Examples:
  php fetch_daily.php
      Run in dry-run mode for today's leaderboard
      
  php fetch_daily.php --execute
      Fetch today's leaderboard (executes HTTP requests)
      
  php fetch_daily.php --date=2026-01-20
      Run in dry-run mode for a specific date
      
  php fetch_daily.php --execute --date=2026-01-20
      Fetch leaderboard for a specific date
      
  php fetch_daily.php --execute --from=2026-01-20 --to=2026-01-25
      Fetch and store leaderboards for a date range (inclusive)

  php fetch_daily.php --execute --from=2026-01-20 --to=2026-01-25 --quiet
      Same as above but suppress console output (useful for automation)

  php fetch_daily.php --init-db
      Initialize the database schema before running

Notes:
  - By default, the script runs in DRY-RUN mode and logs API calls without executing them
  - Use --execute to actually make HTTP requests to PlayFab
  - Ensure PLAYFAB_SESSION_TOKEN is set in .env file
  - Session tokens expire periodically and must be refreshed from game session

HELP;
}
