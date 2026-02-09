<?php
/**
 * API endpoint to fetch monthly leaderboard data
 * 
 * Query Parameters:
 *   month - YYYY-MM format (default: current month)
 *   list - Return list of available months (optional)
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
    
    // Check if requesting list of available months
    if (isset($_GET['list'])) {
        $stmt = $db->query('
            SELECT DISTINCT 
                month_start, 
                month_end,
                COUNT(*) as player_count,
                MAX(total_score) as top_score,
                SUM(days_participated) as total_days_played
            FROM monthly_leaderboards
            GROUP BY month_start
            ORDER BY month_start DESC
        ');
        
        $months = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $months
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Get month parameter
    $monthStr = $_GET['month'] ?? date('Y-m');
    
    // Validate month format
    if (!preg_match('/^\d{4}-\d{2}$/', $monthStr)) {
        throw new Exception('Invalid month format. Use YYYY-MM.');
    }
    
    $monthStartStr = $monthStr . '-01';
    
    // Fetch monthly leaderboard data with player info
    $stmt = $db->prepare('
        SELECT 
            ml.month_start,
            ml.month_end,
            ml.position,
            ml.total_score,
            ml.days_participated,
            ml.average_score,
            ml.best_daily_score,
            ml.best_daily_date,
            ml.calculated_at,
            p.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id
        FROM monthly_leaderboards ml
        JOIN players p ON ml.playfab_id = p.playfab_id
        WHERE ml.month_start = :month_start
        ORDER BY ml.position ASC
    ');
    
    $stmt->execute(['month_start' => $monthStartStr]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get metadata
    $monthDate = new DateTime($monthStartStr);
    $monthEnd = new DateTime($monthDate->format('Y-m-t'));
    $totalDaysInMonth = $monthEnd->diff($monthDate)->days + 1;
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'month_start' => $monthStartStr,
            'month_end' => $monthEnd->format('Y-m-d'),
            'month_name' => $monthDate->format('F Y'),
            'total_days' => $totalDaysInMonth,
            'count' => count($data),
            'requested_month' => $monthStr
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
