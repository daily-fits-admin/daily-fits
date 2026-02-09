-- Initial schema for Fights in Tight Spaces Daily Leaderboard Collector
-- Created: 2026-01-22

-- Table: players
-- Stores player profile information from PlayFab
CREATE TABLE IF NOT EXISTS players (
  playfab_id TEXT PRIMARY KEY,
  display_name TEXT,
  platform TEXT,
  platform_user_id TEXT,
  first_seen DATE,
  last_seen DATE
);

-- Index for faster lookups by display name
CREATE INDEX IF NOT EXISTS idx_players_display_name ON players(display_name);

-- Table: daily_scores
-- Stores daily leaderboard entries per player
CREATE TABLE IF NOT EXISTS daily_scores (
  stat_date DATE NOT NULL,
  statistic_name TEXT NOT NULL,
  position INTEGER NOT NULL,
  playfab_id TEXT NOT NULL,
  score INTEGER NOT NULL,
  PRIMARY KEY (stat_date, playfab_id),
  FOREIGN KEY (playfab_id) REFERENCES players(playfab_id) ON DELETE CASCADE
);

-- Index for faster queries by date
CREATE INDEX IF NOT EXISTS idx_daily_scores_date ON daily_scores(stat_date);

-- Index for faster queries by position (for podium/top rankings)
CREATE INDEX IF NOT EXISTS idx_daily_scores_position ON daily_scores(position);

-- Table: runs
-- Stores metadata about each fetch operation (future-proofing)
CREATE TABLE IF NOT EXISTS runs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  stat_date DATE NOT NULL,
  statistic_name TEXT NOT NULL,
  fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  entry_count INTEGER,
  api_version TEXT
);

-- Index for faster queries by date
CREATE INDEX IF NOT EXISTS idx_runs_date ON runs(stat_date);
