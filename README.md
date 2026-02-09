# Fights in Tight Spaces - Daily Leaderboard Collector

An open-source community tool for collecting, storing, and analyzing daily leaderboard data from the game *Fights in Tight Spaces* (GOG version).

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

---

## 🚀 Project Overview (AI Context)

**Tech Stack:** PHP 8+ backend, Python 3.10+ analytics, SQLite database, Tabulator + Alpine.js frontend  
**Architecture:** CLI-based data collector → SQL storage → REST API → Web dashboard  
**Key Safety:** Dry-run by default, `--execute` flag required for HTTP requests  

**Core Components:**
- `backend/src/`: PlayFabClient, LeaderboardFetcher, Database, Logger (PHP classes)
- `backend/cli/fetch_daily.php`: Main CLI entry point with execution gating
- `frontend/`: Web dashboard (Tabulator tables + Alpine.js, ~60kb total)
- `frontend/api/`: REST API endpoints for data access
- `analytics/scripts/`: weekly_stats.py, monthly_stats.py, export_csv.py
- `backend/migrations/001_init.sql`: Schema (players, daily_scores, runs tables)
- `.env`: Config file (PLAYFAB_SESSION_TOKEN, DB_PATH, LOG_PATH)

**PlayFab API:** Base URL: `https://d155a.playfabapi.com`  
**Endpoints:** `/Client/GetLeaderboard` (paginated), `/Client/GetLeaderboardAroundPlayer`  
**Auth:** `X-Authorization` header with session token (expires, must refresh from game)  
**Statistic Names:** `DailyPlay_Mon`, `DailyPlay_Tues`, `DailyPlay_Wed`, `DailyPlay_Thurs`, `DailyPlay_Fri`, `DailyPlay_Sat`, `DailyPlay_Sun` (note: Tues=4 letters, Thurs=5 letters - inconsistent abbreviations!)

**Database Schema:**
- `players`: playfab_id (PK), display_name, platform, platform_user_id, first_seen, last_seen
- `daily_scores`: stat_date+playfab_id (composite PK), position, score, statistic_name
- `weekly_leaderboards`: week_start+playfab_id (composite PK), total_score, days_participated, average_score
- `runs`: Audit trail of fetch operations (id, stat_date, fetched_at, entry_count)

**REST API Endpoints:**
- `GET /api/leaderboard.php?date=YYYY-MM-DD` - Daily leaderboard data
- `GET /api/weekly.php?week=YYYY-MM-DD` - Weekly aggregated leaderboard
- `GET /api/weekly.php?list=1` - List available weeks
- See [API_DOCS.md](API_DOCS.md) for complete documentation

**Operational Rules:**
- NO automatic HTTP execution (requires explicit `--execute` flag)
- Session tokens are short-lived, must be captured from game session
- Idempotent operations (safe to re-run for same date)
- Rate limiting: 100ms delay between paginated API calls
- Comprehensive logging to `data/fits.log`

**Common Tasks:**
- Fetch daily data: `php backend/cli/fetch_daily.php --execute [--date=YYYY-MM-DD]`
- Calculate weekly: `php backend/cli/calculate_weekly.php --all`
- View dashboard: Open `http://localhost/daily-fits/frontend/` in browser
- API access: `curl http://localhost/daily-fits/frontend/api/leaderboard.php?date=2026-01-23`

**Repository:** https://github.com/Hatem-D/daily-fits (private)

**Documentation:** See `PROJECT_BRIEF.md` for comprehensive technical documentation and setup details.

---

## ⚠️ Important Disclaimer

**This is an unofficial, community-developed tool and is NOT affiliated with, endorsed by, or connected to the developers or publishers of *Fights in Tight Spaces*.**

This tool:
- Uses publicly accessible PlayFab API endpoints
- Requires a valid game session token obtained from legitimate gameplay
- Does not modify game files or interfere with game operation
- Stores leaderboard data locally for historical analysis
- Is provided as-is for educational and community purposes

**Use at your own risk. The developers of this tool are not responsible for any consequences of its use.**

---

## 🎯 Features

### Data Collection (PHP Backend)
- Fetch daily leaderboard data from PlayFab API
- Paginated data retrieval with rate limiting
- SQLite database storage (PostgreSQL/MySQL compatible)
- Player profile tracking with platform linking (GOG)
- Comprehensive logging system
- **Dry-run mode** by default - no HTTP requests without explicit approval

### Web Dashboard (Frontend)
- Interactive daily leaderboard tables with sorting/filtering
- Date selection for historical data viewing
- Real-time search by player name
- Responsive design with dark theme
- Platform icons (Steam, GOG, Nintendo Switch)
- Built with Tabulator.js + Alpine.js (~60kb total, no build step)

### Analytics (Python Scripts)
- Weekly statistics: champions, podium finishes, consistency rankings
- Monthly statistics: participation trends, score analysis
- CSV/JSON export for external analysis

---

## 📋 Requirements

### Backend (PHP)
- PHP 8.0 or higher
- PHP Extensions:
  - `pdo_sqlite` (for SQLite support)
  - `curl` (for HTTP requests)
  - `json`
- Web server (Apache/Nginx) with mod_rewrite

### Frontend (Web Dashboard)
- Modern web browser (Chrome, Firefox, Edge, Safari)
- No build tools required - uses CDN for dependencies
- JavaScript must be enabled

### Analytics (Python)
- Python 3.10 or higher
- Standard library only (no external dependencies required)
- Optional: pandas, matplotlib for advanced analysis

---

## 🚀 Quick Start

### 1. Clone the Repository

```bash
git clone <repository-url>
cd daily-fits
```

### 2. Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit .env and add your PlayFab session token
nano .env
```

**How to get your PlayFab session token:**
1. Launch *Fights in Tight Spaces* (GOG version)
2. Use browser developer tools or network monitoring tool (e.g., Wireshark, Fiddler)
3. Capture HTTP requests to `playfabapi.com`
4. Extract the `X-Authorization` header value
5. Copy the token to `.env` file

⚠️ **Security Notes:**
- Session tokens expire after your game session ends
- Obtain a fresh token before each data collection run
- **NEVER commit your `.env` file to version control**
- Keep your token private

### 3. Initialize Database

```bash
cd backend/cli
php fetch_daily.php --init-db
```

### 4. Fetch Leaderboard Data

**Dry-run mode (default - no HTTP requests):**
```bash
php fetch_daily.php
```

**Execute mode (actually fetches data):**
```bash
php fetch_daily.php --execute
```

**Fetch specific date:**
```bash
php fetch_daily.php --execute --date=2026-01-20
```

---

## 📁 Project Structure

```
daily-fits/
├── backend/
│   ├── config/
│   │   └── config.php              # Configuration loader
│   ├── cli/
│   │   └── fetch_daily.php         # CLI entry point
│   ├── src/
│   │   ├── PlayFabClient.php       # PlayFab API client
│   frontend/
│   ├── index.html                  # Main web dashboard
│   ├── api/
│   │   └── leaderboard.php         # REST API endpoint for leaderboard data
│   └── .htaccess                   # Web server configuration
│
├── │   ├── LeaderboardFetcher.php  # Data fetching orchestration
│   │   ├── Database.php            # Database abstraction
│   │   └── Logger.php              # Logging utility
│   └── migrations/
│       └── 001_init.sql            # Database schema
│
├── analytics/
│   ├── scripts/
│   │   ├── weekly_stats.py         # Weekly statistics calculator
│   │   ├── monthly_stats.py        # Monthly statistics calculator
│   │   └── export_csv.py           # Data export utility
│   └── requirements.txt            # Python dependencies
│
├── data/
│   ├── fits.db                     # SQLite database (created on first run)
│   └── fits.log                    # Application log file
│
├── .env.example                    # Environment template
├── README.md                       # This file
└── PROJECT_BRIEF.md                # Technical specification
```Web Dashboard

#### Access Dashboard
```bash
# Start your web server (Apache/Nginx)
# Navigate to: http://localhost/daily-fits/frontend/
```

**Features:**
- View daily leaderboards with interactive tables
- Sort by rank, score, player name, or platform
- Filter players in real-time using search
- Select any date to view historical data
- See player platform icons (Steam, GOG, Nintendo Switch)
- Medal emojis for top 3 positions (🥇🥈🥉)

### 

---

## 🔧 Usage

### Backend (Data Collection)

#### Fetch Today's Leaderboard
```bash
cd backend/cli
php fetch_daily.php --execute
```

#### Fetch Specific Date
```bash
php fetch_daily.php --execute --date=2026-01-15
```

#### Dry-Run Mode (Test Without Executing)
```bash
php fetch_daily.php
```

#### Command Line Options
- `--execute`: Execute HTTP requests (dry-run by default)
- `--date=YYYY-MM-DD`: Fetch for specific date (default: today)
- `--init-db`: Initialize database schema
- `--help`: Show help message

### Analytics (Data Analysis)

#### Weekly Statistics
```bash
cd analytics/scripts
python weekly_stats.py                    # Current week
python weekly_stats.py --week 2026-W03    # Specific week
python weekly_stats.py --output stats.json  # Save to file
```

#### Monthly Statistics
```bash
python monthly_stats.py                   # Current month
python monthly_stats.py --month 2026-01   # Specific month
python monthly_stats.py --output stats.json
```

#### Export Data
```bash
# Export daily scores to CSV
python export_csv.py --type scores --format csv

# Export player summary to JSON
python export_csv.py --type players --format json

# Export with date range
python export_csv.py --type scores --start-date 2026-01-01 --end-date 2026-01-31
```

---

## 🗄️ Database Schema

### `players` Table
Stores player profile information from PlayFab.

| Column | Type | Description |
|--------|------|-------------|
| `playfab_id` | TEXT | PlayFab player ID (primary key) |
| `display_name` | TEXT | Player display name |
| `platform` | TEXT | Platform (e.g., "GOG") |
| `platform_user_id` | TEXT | Platform-specific user ID |
| `first_seen` | DATE | First appearance in leaderboard |
| `last_seen` | DATE | Most recent appearance |

### `daily_scores` Table
Stores daily leaderboard entries.

| Column | Type | Description |
|--------|------|-------------|
| `stat_date` | DATE | Date of the leaderboard |
| `statistic_name` | TEXT | PlayFab statistic name (e.g., "DailyPlay_Mon") |
| `position` | INTEGER | Leaderboard position (0-indexed) |
| `playfab_id` | TEXT | Player ID (foreign key) |
| `score` | INTEGER | Player score |

### `runs` Table
Stores metadata about fetch operations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INTEGER | Run ID (auto-increment) |
| `stat_date` | DATE | Target date |
| `statistic_name` | TEXT | Statistic name |
| `fetched_at` | TIMESTAMP | Fetch timestamp |
| `entry_count` | INTEGER | Number of entries fetched |
| `api_version` | TEXT | API version used |

---

## 🔐 Security & Privacy

### Session Token Security
- Session tokens are short-lived and expire after game session
- Tokens must be obtained from legitimate gameplay
- Never share or commit tokens to version control
- Use `.gitignore` to exclude `.env` file

### Data Privacy
- Only publicly available leaderboard data is collected
- No personal information beyond what PlayFab publicly exposes
- Data is stored locally in your database
- No data is transmitted to third parties

### Best Practices
1. Keep your `.env` file secure and private
2. Refresh session token before each fetch operation
3. Monitor API requests in logs
4. Use dry-run mode to verify before execution
5. Respect rate limits and API usage policies

---

## 🛠️ Development

### Adding Custom Analytics

Create new Python scripts in `analytics/scripts/`:

```python
import sqlite3
import os

def get_database_path():
    return os.getenv('DB_PATH', '../../data/fits.db')

def main():
    db_path = get_database_path()
    conn = sqlite3.connect(db_path)
    # Your analysis here
    conn.close()

if __name__ == '__main__':
    main()
```

### Database Migrations

Add new migration files in `backend/migrations/`:

```sql
-- 002_add_feature.sql
ALTER TABLE players ADD COLUMN new_field TEXT;
```

Apply migrations:
```bash
php fetch_daily.php --init-db
```

### Logging

Configure log level in `.env`:
```env
LOG_LEVEL=DEBUG  # DEBUG, INFO, WARNING, ERROR
```

View logs:
```bash
tail -f data/fits.log
```

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Style
- **PHP**: Follow PSR-12 coding standards
- **Python**: Follow PEP 8 style guide
- Add comments for complex logic
- Update documentation for new features

---

## 📜 License

This project is licensed under the MIT License - see below for details.

```
MIT License

Copyright (c) 2026 Daily Fits Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 📞 Support

- **Issues**: [GitHub Issues](<repository-url>/issues)
- **Discussions**: [GitHub Discussions](<repository-url>/discussions)
- **Documentation**: See `PROJECT_BRIEF.md` for technical details

---

## 🙏 Acknowledgments

- *Fights in Tight Spaces* by Ground Shatter
- PlayFab API by Microsoft
- Community contributors and testers

---

**Built with ❤️ by the community, for the community.**
