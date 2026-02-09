<?php
/**
 * API endpoint to fetch all-time aggregate statistics
 * 
 * Returns all-time leaderboard and comprehensive player statistics
 * aggregated across all daily scores in the database.
 * 
 * Returns JSON:
 *   {
 *     "success": true,
 *     "data": [...],
 *     "meta": {...}
 *   }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Load configuration
require_once __DIR__ . '/../../backend/config/config.php';

try {
    // Connect to database
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Aggregate all-time statistics for each player
    $stmt = $db->query('
        SELECT 
            ds.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id,
            p.first_seen,
            p.last_seen,
            COUNT(DISTINCT ds.stat_date) as total_days_played,
            SUM(ds.score) as total_score,
            AVG(ds.score) as average_score,
            MAX(ds.score) as best_daily_score,
            MIN(ds.score) as worst_daily_score,
            (SELECT stat_date FROM daily_scores ds2 
             WHERE ds2.playfab_id = ds.playfab_id 
             ORDER BY ds2.score DESC LIMIT 1) as best_day_date,
            (SELECT COUNT(DISTINCT stat_date) FROM daily_scores) as total_days_available
        FROM daily_scores ds
        JOIN players p ON ds.playfab_id = p.playfab_id
        GROUP BY ds.playfab_id
        ORDER BY total_score DESC
    ');
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add position/rank to each player
    foreach ($data as $i => $player) {
        $data[$i]['position'] = $i;
    }
    
    // Get overall statistics
    $metaStmt = $db->query('
        SELECT 
            COUNT(DISTINCT playfab_id) as total_players,
            COUNT(DISTINCT stat_date) as total_days,
            MIN(stat_date) as first_date,
            MAX(stat_date) as last_date,
            SUM(score) as cumulative_score,
            AVG(score) as average_daily_score,
            MAX(score) as highest_score_ever
        FROM daily_scores
    ');
    
    $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate date range
    if ($meta['first_date'] && $meta['last_date']) {
        $firstDate = new DateTime($meta['first_date']);
        $lastDate = new DateTime($meta['last_date']);
        $dateRange = $firstDate->diff($lastDate)->days + 1;
    } else {
        $dateRange = 0;
    }
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'total_players' => (int)$meta['total_players'],
            'total_days_with_data' => (int)$meta['total_days'],
            'date_range_days' => $dateRange,
            'first_date' => $meta['first_date'],
            'last_date' => $meta['last_date'],
            'cumulative_score' => (int)$meta['cumulative_score'],
            'average_daily_score' => round((float)$meta['average_daily_score'], 2),
            'highest_score_ever' => (int)$meta['highest_score_ever'],
            'count' => count($data),
            'generated_at' => date('Y-m-d H:i:s')
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
