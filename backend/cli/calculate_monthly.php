#!/usr/bin/env php
<?php
/**
 * Calculate and store monthly leaderboard aggregates
 * 
 * Usage:
 *   php calculate_monthly.php [options]
 * 
 * Options:
 *   --month=YYYY-MM      Calculate for specific month (default: current month)
 *   --all                Recalculate all months with available data
 *   --help               Show this help message
 */

require_once __DIR__ . '/../config/config.php';

// Parse arguments
$options = getopt('', ['month:', 'all', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

// Connect to database
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Database error: {$e->getMessage()}\n";
    exit(1);
}

// Apply migration if needed
echo "Checking database schema...\n";
$migrationPath = __DIR__ . '/../migrations/003_monthly_leaderboards.sql';
if (file_exists($migrationPath)) {
    try {
        $sql = file_get_contents($migrationPath);
        $db->exec($sql);
        echo "✓ Schema ready\n\n";
    } catch (Exception $e) {
        echo "Migration failed: {$e->getMessage()}\n";
        exit(1);
    }
}

if (isset($options['all'])) {
    // Calculate for all months with data
    calculateAllMonths($db);
} else {
    // Calculate for specific month
    if (isset($options['month'])) {
        $date = new DateTime($options['month'] . '-01');
    } else {
        $date = new DateTime();
    }
    calculateMonth($db, $date);
}

/**
 * Calculate monthly leaderboard for a specific month
 */
function calculateMonth(PDO $db, DateTime $date) {
    // Get first and last day of month
    $monthStart = new DateTime($date->format('Y-m-01'));
    $monthEnd = new DateTime($date->format('Y-m-t'));
    
    $monthStartStr = $monthStart->format('Y-m-d');
    $monthEndStr = $monthEnd->format('Y-m-d');
    $monthName = $monthStart->format('F Y');
    
    echo "Calculating monthly leaderboard\n";
    echo "  Month: {$monthName} ({$monthStartStr} to {$monthEndStr})\n";
    
    // Aggregate daily scores for this month
    $stmt = $db->prepare('
        SELECT 
            playfab_id,
            SUM(score) as total_score,
            COUNT(*) as days_participated,
            AVG(score) as average_score,
            MAX(score) as best_daily_score,
            (SELECT stat_date FROM daily_scores ds2 
             WHERE ds2.playfab_id = ds.playfab_id 
             AND ds2.stat_date BETWEEN :start1 AND :end1
             ORDER BY ds2.score DESC LIMIT 1) as best_daily_date
        FROM daily_scores ds
        WHERE stat_date BETWEEN :start2 AND :end2
        GROUP BY playfab_id
        ORDER BY total_score DESC
    ');
    
    $stmt->execute([
        'start1' => $monthStartStr,
        'end1' => $monthEndStr,
        'start2' => $monthStartStr,
        'end2' => $monthEndStr
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "  No data found for this month.\n";
        return;
    }
    
    echo "  Players found: " . count($results) . "\n";
    
    // Clear existing data for this month
    $deleteStmt = $db->prepare('
        DELETE FROM monthly_leaderboards 
        WHERE month_start = :month_start
    ');
    $deleteStmt->execute(['month_start' => $monthStartStr]);
    
    // Insert aggregated data with rankings
    $insertStmt = $db->prepare('
        INSERT INTO monthly_leaderboards 
        (month_start, month_end, playfab_id, total_score, days_participated, 
         average_score, best_daily_score, best_daily_date, position, calculated_at)
        VALUES (:month_start, :month_end, :playfab_id, :total_score, :days_participated,
                :average_score, :best_daily_score, :best_daily_date, :position, datetime("now"))
    ');
    
    $db->beginTransaction();
    
    foreach ($results as $position => $row) {
        $insertStmt->execute([
            'month_start' => $monthStartStr,
            'month_end' => $monthEndStr,
            'playfab_id' => $row['playfab_id'],
            'total_score' => $row['total_score'],
            'days_participated' => $row['days_participated'],
            'average_score' => $row['average_score'],
            'best_daily_score' => $row['best_daily_score'],
            'best_daily_date' => $row['best_daily_date'],
            'position' => $position
        ]);
    }
    
    $db->commit();
    
    $totalDays = $monthStart->diff($monthEnd)->days + 1;
    
    echo "  ✓ Monthly leaderboard calculated and stored\n";
    echo "    Top 3:\n";
    foreach (array_slice($results, 0, 3) as $i => $row) {
        $rank = $i + 1;
        $medals = ['🥇', '🥈', '🥉'];
        echo "    {$medals[$i]} #{$rank}: {$row['playfab_id']} - {$row['total_score']} points ({$row['days_participated']}/{$totalDays} days)\n";
    }
}

/**
 * Calculate for all months with available data
 */
function calculateAllMonths(PDO $db) {
    echo "Finding all months with data...\n\n";
    
    // Get date range of available data
    $stmt = $db->query('
        SELECT MIN(stat_date) as first_date, MAX(stat_date) as last_date 
        FROM daily_scores
    ');
    $range = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$range['first_date']) {
        echo "No data found in database.\n";
        return;
    }
    
    $firstDate = new DateTime($range['first_date']);
    $lastDate = new DateTime($range['last_date']);
    
    echo "Data range: {$range['first_date']} to {$range['last_date']}\n\n";
    
    // Find all months in range
    $monthDate = new DateTime($firstDate->format('Y-m-01'));
    
    $monthsCalculated = 0;
    while ($monthDate <= $lastDate) {
        calculateMonth($db, $monthDate);
        echo "\n";
        $monthDate->modify('+1 month');
        $monthsCalculated++;
    }
    
    echo "═══════════════════════════════════════\n";
    echo "✓ Calculated {$monthsCalculated} months\n";
}

function showHelp() {
    echo <<<HELP
Calculate Monthly Leaderboards

This script aggregates daily scores into monthly leaderboards.
Months run from the 1st to the last day of each calendar month.

Usage:
  php calculate_monthly.php [options]

Options:
  --month=YYYY-MM      Calculate for specific month
                       (default: current month)
  --all                Recalculate all months with available data
  --help               Show this help message

Examples:
  php calculate_monthly.php                    # Calculate current month
  php calculate_monthly.php --month=2026-01    # Calculate January 2026
  php calculate_monthly.php --all              # Calculate all available months

HELP;
}
