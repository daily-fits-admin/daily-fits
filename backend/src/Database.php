<?php
/**
 * Database class for the Fights in Tight Spaces Leaderboard Collector
 * 
 * Provides database abstraction and data persistence methods.
 */

require_once __DIR__ . '/Logger.php';

class Database {
    private PDO $pdo;
    private Logger $logger;
    
    /**
     * Constructor
     * 
     * @param string $type Database type (sqlite or mysql/pgsql)
     * @param array $config Database configuration
     * @param Logger $logger Logger instance
     */
    public function __construct(string $type, array $config, Logger $logger) {
        $this->logger = $logger;
        
        try {
            if ($type === 'sqlite') {
                $dbPath = $config['path'];
                
                // Ensure database directory exists
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                
                $this->pdo = new PDO("sqlite:{$dbPath}");
                $this->logger->info('Connected to SQLite database', ['path' => $dbPath]);
            } else {
                // PostgreSQL or MySQL
                $dsn = "{$type}:host={$config['host']};dbname={$config['dbname']}";
                $this->pdo = new PDO($dsn, $config['user'], $config['pass']);
                $this->logger->info('Connected to database', ['type' => $type, 'host' => $config['host']]);
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Run migrations to initialize database schema
     * 
     * @param string $migrationPath Path to the SQL migration file
     * @return bool True on success
     */
    public function runMigrations(string $migrationPath): bool {
        try {
            $sql = file_get_contents($migrationPath);
            $this->pdo->exec($sql);
            $this->logger->info('Migrations completed successfully', ['path' => $migrationPath]);
            return true;
        } catch (PDOException $e) {
            $this->logger->error('Migration failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Insert or update a player record
     * 
     * @param array $player Player data
     * @return bool True on success
     */
    public function upsertPlayer(array $player): bool {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO players (playfab_id, display_name, platform, platform_user_id, first_seen, last_seen)
                VALUES (:playfab_id, :display_name, :platform, :platform_user_id, :first_seen, :last_seen)
                ON CONFLICT(playfab_id) DO UPDATE SET
                    display_name = :display_name,
                    platform = :platform,
                    platform_user_id = :platform_user_id,
                    last_seen = :last_seen
            ');
            
            $stmt->execute([
                ':playfab_id' => $player['playfab_id'],
                ':display_name' => $player['display_name'] ?? null,
                ':platform' => $player['platform'] ?? null,
                ':platform_user_id' => $player['platform_user_id'] ?? null,
                ':first_seen' => $player['first_seen'] ?? date('Y-m-d'),
                ':last_seen' => $player['last_seen'] ?? date('Y-m-d')
            ]);
            
            return true;
        } catch (PDOException $e) {
            $this->logger->error('Failed to upsert player', [
                'playfab_id' => $player['playfab_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Insert or replace a daily score entry
     * 
     * @param array $score Score data
     * @return bool True on success
     */
    public function upsertDailyScore(array $score): bool {
        try {
            $stmt = $this->pdo->prepare('
                INSERT OR REPLACE INTO daily_scores (stat_date, statistic_name, position, playfab_id, score)
                VALUES (:stat_date, :statistic_name, :position, :playfab_id, :score)
            ');
            
            $stmt->execute([
                ':stat_date' => $score['stat_date'],
                ':statistic_name' => $score['statistic_name'],
                ':position' => $score['position'],
                ':playfab_id' => $score['playfab_id'],
                ':score' => $score['score']
            ]);
            
            return true;
        } catch (PDOException $e) {
            $this->logger->error('Failed to upsert daily score', [
                'stat_date' => $score['stat_date'],
                'playfab_id' => $score['playfab_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Record a fetch run
     * 
     * @param array $run Run metadata
     * @return int|null The run ID, or null on failure
     */
    public function recordRun(array $run): ?int {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO runs (stat_date, statistic_name, entry_count, api_version)
                VALUES (:stat_date, :statistic_name, :entry_count, :api_version)
            ');
            
            $stmt->execute([
                ':stat_date' => $run['stat_date'],
                ':statistic_name' => $run['statistic_name'],
                ':entry_count' => $run['entry_count'] ?? 0,
                ':api_version' => $run['api_version'] ?? 'v1'
            ]);
            
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logger->error('Failed to record run', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Get PDO instance for custom queries
     * 
     * @return PDO The PDO instance
     */
    public function getPDO(): PDO {
        return $this->pdo;
    }
}
