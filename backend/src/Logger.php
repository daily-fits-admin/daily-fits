<?php
/**
 * Logger class for the Fights in Tight Spaces Leaderboard Collector
 * 
 * Provides simple file-based logging with configurable log levels.
 */

class Logger {
    private string $logPath;
    private string $logLevel;
    
    private const LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3
    ];
    
    /**
     * Constructor
     * 
     * @param string $logPath Path to the log file
     * @param string $logLevel Minimum log level (DEBUG, INFO, WARNING, ERROR)
     */
    public function __construct(string $logPath, string $logLevel = 'INFO') {
        $this->logPath = $logPath;
        $this->logLevel = strtoupper($logLevel);
        
        // Ensure log directory exists
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Log a debug message
     * 
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Log an info message
     * 
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }
    
    /**
     * Log an error message
     * 
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }
    
    /**
     * Write log entry to file
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log(string $level, string $message, array $context = []): void {
        // Check if this level should be logged
        if (self::LEVELS[$level] < self::LEVELS[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($this->logPath, $logEntry, FILE_APPEND);
    }
}
