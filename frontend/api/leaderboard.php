<?php
/**
 * API endpoint to fetch daily leaderboard data
 * 
 * Query Parameters:
 *   date - YYYY-MM-DD format (default: today)
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
    // Get date parameter
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format. Use YYYY-MM-DD.');
    }
    
    // Connect to database
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch leaderboard data with player info
    $stmt = $db->prepare('
        SELECT 
            ds.stat_date,
            ds.statistic_name,
            ds.position,
            ds.score,
            p.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id,
            p.first_seen,
            p.last_seen
        FROM daily_scores ds
        JOIN players p ON ds.playfab_id = p.playfab_id
        WHERE ds.stat_date = :date
        ORDER BY ds.position ASC
    ');
    
    $stmt->execute(['date' => $date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get metadata
    $metaStmt = $db->prepare('
        SELECT 
            statistic_name,
            fetched_at,
            entry_count
        FROM runs
        WHERE stat_date = :date
        ORDER BY fetched_at DESC
        LIMIT 1
    ');
    
    $metaStmt->execute(['date' => $date]);
    $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);
    
    // Response
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'date' => $date,
            'count' => count($data),
            'statistic_name' => $meta['statistic_name'] ?? null,
            'fetched_at' => $meta['fetched_at'] ?? null,
            'last_updated' => $meta ? date('Y-m-d H:i:s', strtotime($meta['fetched_at'])) : null
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
