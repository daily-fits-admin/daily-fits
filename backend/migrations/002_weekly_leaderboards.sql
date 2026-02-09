-- Weekly leaderboards table
-- Stores pre-calculated weekly aggregate scores
CREATE TABLE IF NOT EXISTS weekly_leaderboards (
    week_start DATE NOT NULL,           -- Sunday of the week (ISO week start)
    week_end DATE NOT NULL,             -- Saturday of the week
    playfab_id TEXT NOT NULL,
    total_score INTEGER NOT NULL,       -- Sum of daily scores
    days_participated INTEGER NOT NULL, -- How many days played
    average_score REAL NOT NULL,        -- Average score per day played
    best_daily_score INTEGER,           -- Highest score in the week
    best_daily_date DATE,               -- Date of highest score
    position INTEGER,                   -- Rank in weekly leaderboard (0-based)
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (week_start, playfab_id),
    FOREIGN KEY (playfab_id) REFERENCES players(playfab_id)
);

-- Index for querying by week
CREATE INDEX IF NOT EXISTS idx_weekly_leaderboards_week ON weekly_leaderboards(week_start, week_end);

-- Index for querying by player
CREATE INDEX IF NOT EXISTS idx_weekly_leaderboards_player ON weekly_leaderboards(playfab_id);

-- Index for ranking queries
CREATE INDEX IF NOT EXISTS idx_weekly_leaderboards_score ON weekly_leaderboards(week_start, total_score DESC);
