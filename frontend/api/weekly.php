<?php
/**
 * API endpoint to fetch weekly leaderboard data
 * 
 * Query Parameters:
 *   week - YYYY-MM-DD format (any date in the week, default: current week)
 *   list - Return list of available weeks (optional)
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
    
    // Check if requesting list of available weeks
    if (isset($_GET['list'])) {
        $stmt = $db->query('
            SELECT DISTINCT 
                week_start, 
                week_end,
                COUNT(*) as player_count,
                MAX(total_score) as top_score
            FROM weekly_leaderboards
            GROUP BY week_start
            ORDER BY week_start DESC
        ');
        
        $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $weeks
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Get week parameter (any date in the week)
    $dateStr = $_GET['week'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD.');
    }
    
    $date = new DateTime($dateStr);
    
    // Calculate Sunday of the week
    $weekStart = clone $date;
    $weekStart->modify('sunday this week');
    if ($weekStart > $date) {
        $weekStart->modify('-7 days');
    }
    
    $weekStartStr = $weekStart->format('Y-m-d');
    
    // Fetch weekly leaderboard data with player info
    $stmt = $db->prepare('
        SELECT 
            wl.week_start,
            wl.week_end,
            wl.position,
            wl.total_score,
            wl.days_participated,
            wl.average_score,
            wl.best_daily_score,
            wl.best_daily_date,
            p.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id
        FROM weekly_leaderboards wl
        JOIN players p ON wl.playfab_id = p.playfab_id
        WHERE wl.week_start = :week_start
        ORDER BY wl.position ASC
    ');
    
    $stmt->execute(['week_start' => $weekStartStr]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get metadata
    $weekEnd = clone $weekStart;
    $weekEnd->modify('+6 days');
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'week_start' => $weekStartStr,
            'week_end' => $weekEnd->format('Y-m-d'),
            'count' => count($data),
            'requested_date' => $dateStr
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
