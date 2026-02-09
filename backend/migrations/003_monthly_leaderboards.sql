-- Migration: Monthly leaderboard aggregates
-- Similar to weekly_leaderboards but for calendar months

CREATE TABLE IF NOT EXISTS monthly_leaderboards (
    month_start DATE,           -- First day of month (YYYY-MM-01)
    month_end DATE,             -- Last day of month
    playfab_id TEXT,
    position INTEGER,           -- Monthly rank (0-indexed)
    total_score INTEGER,        -- Sum of all daily scores in month
    days_participated INTEGER,  -- Count of days played in month
    average_score REAL,         -- AVG(daily score)
    best_daily_score INTEGER,   -- MAX(daily score)
    best_daily_date DATE,       -- Date of best score
    calculated_at TIMESTAMP,    -- When aggregate was computed
    PRIMARY KEY (month_start, playfab_id),
    FOREIGN KEY (playfab_id) REFERENCES players(playfab_id)
);

-- Index for faster queries
CREATE INDEX IF NOT EXISTS idx_monthly_position ON monthly_leaderboards(month_start, position);
CREATE INDEX IF NOT EXISTS idx_monthly_score ON monthly_leaderboards(month_start, total_score DESC);
