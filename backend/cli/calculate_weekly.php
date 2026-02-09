#!/usr/bin/env php
<?php
/**
 * Calculate and store weekly leaderboard aggregates
 * 
 * Usage:
 *   php calculate_weekly.php [options]
 * 
 * Options:
 *   --week=YYYY-MM-DD    Calculate for week containing this date (default: current week)
 *   --all                Recalculate all weeks with available data
 *   --help               Show this help message
 */

require_once __DIR__ . '/../config/config.php';

// Parse arguments
$options = getopt('', ['week:', 'all', 'help']);

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
$migrationPath = __DIR__ . '/../migrations/002_weekly_leaderboards.sql';
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
    // Calculate for all weeks with data
    calculateAllWeeks($db);
} else {
    // Calculate for specific week
    $date = isset($options['week']) ? new DateTime($options['week']) : new DateTime();
    calculateWeek($db, $date);
}

/**
 * Calculate weekly leaderboard for a specific week
 */
function calculateWeek(PDO $db, DateTime $date) {
    // Get Sunday of the week (week starts on Sunday)
    $weekStart = clone $date;
    $weekStart->modify('sunday this week');
    if ($weekStart > $date) {
        $weekStart->modify('-7 days');
    }
    
    // Get Saturday of the week
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    
    $weekStartStr = $weekStart->format('Y-m-d');
    $weekEndStr = $weekEnd->format('Y-m-d');
    
    echo "Calculating weekly leaderboard\n";
    echo "  Week: {$weekStartStr} to {$weekEndStr}\n";
    
    // Aggregate daily scores for this week
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
        'start1' => $weekStartStr,
        'end1' => $weekEndStr,
        'start2' => $weekStartStr,
        'end2' => $weekEndStr
    ]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "  No data found for this week.\n";
        return;
    }
    
    echo "  Players found: " . count($results) . "\n";
    
    // Clear existing data for this week
    $deleteStmt = $db->prepare('
        DELETE FROM weekly_leaderboards 
        WHERE week_start = :week_start
    ');
    $deleteStmt->execute(['week_start' => $weekStartStr]);
    
    // Insert aggregated data with rankings
    $insertStmt = $db->prepare('
        INSERT INTO weekly_leaderboards 
        (week_start, week_end, playfab_id, total_score, days_participated, 
         average_score, best_daily_score, best_daily_date, position)
        VALUES (:week_start, :week_end, :playfab_id, :total_score, :days_participated,
                :average_score, :best_daily_score, :best_daily_date, :position)
    ');
    
    $db->beginTransaction();
    
    foreach ($results as $position => $row) {
        $insertStmt->execute([
            'week_start' => $weekStartStr,
            'week_end' => $weekEndStr,
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
    
    echo "  ✓ Weekly leaderboard calculated and stored\n";
    echo "    Top 3:\n";
    foreach (array_slice($results, 0, 3) as $i => $row) {
        $rank = $i + 1;
        $medals = ['🥇', '🥈', '🥉'];
        echo "    {$medals[$i]} #{$rank}: {$row['playfab_id']} - {$row['total_score']} points ({$row['days_participated']} days)\n";
    }
}

/**
 * Calculate for all weeks with available data
 */
function calculateAllWeeks(PDO $db) {
    echo "Finding all weeks with data...\n\n";
    
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
    
    // Find all Sunday-to-Saturday weeks in range
    $weekStart = clone $firstDate;
    $weekStart->modify('sunday this week');
    if ($weekStart > $firstDate) {
        $weekStart->modify('-7 days');
    }
    
    $weeksCalculated = 0;
    while ($weekStart <= $lastDate) {
        calculateWeek($db, $weekStart);
        echo "\n";
        $weekStart->modify('+7 days');
        $weeksCalculated++;
    }
    
    echo "═══════════════════════════════════════\n";
    echo "✓ Calculated {$weeksCalculated} weeks\n";
}

function showHelp() {
    echo <<<HELP
Calculate Weekly Leaderboards

This script aggregates daily scores into weekly leaderboards.
Weeks run from Sunday to Saturday.

Usage:
  php calculate_weekly.php [options]

Options:
  --week=YYYY-MM-DD    Calculate for week containing this date
                       (default: current week)
  --all                Recalculate all weeks with available data
  --help               Show this help message

Examples:
  php calculate_weekly.php                    # Calculate current week
  php calculate_weekly.php --week=2026-01-20  # Calculate week of Jan 20
  php calculate_weekly.php --all              # Calculate all available weeks

HELP;
}
