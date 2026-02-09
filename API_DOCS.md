# Daily Fits API Documentation

**Base URL:** `http://localhost/daily-fits/frontend/api/`

---

## Endpoints

### 1. Daily Leaderboard

**Endpoint:** `GET /leaderboard.php`

**Description:** Retrieve daily leaderboard data for a specific date.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `date` | string | No | Today | Date in YYYY-MM-DD format |

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "stat_date": "2026-01-24",
      "statistic_name": "DailyPlay_Sat",
      "position": 0,
      "score": 6670,
      "playfab_id": "75CF47B738BDFCD5",
      "display_name": "ralack",
      "platform": "Steam",
      "platform_user_id": "76561197973553326",
      "first_seen": "2026-01-23",
      "last_seen": "2026-01-24"
    }
  ],
  "meta": {
    "date": "2026-01-24",
    "count": 84,
    "statistic_name": "DailyPlay_Sat",
    "fetched_at": "2026-01-24 12:34:56",
    "last_updated": "2026-01-24 12:34:56"
  }
}
```

**Example:**
```bash
curl "http://localhost/daily-fits/frontend/api/leaderboard.php?date=2026-01-23"
```

---

### 2. Weekly Leaderboard

**Endpoint:** `GET /weekly.php`

**Description:** Retrieve aggregated weekly leaderboard data. Weeks run Sunday to Saturday.

**Query Parameters:**
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `week` | string | No | Current week | Any date within the desired week (YYYY-MM-DD) |
| `list` | flag | No | - | Return list of available weeks instead |

**Response (Leaderboard):**
```json
{
  "success": true,
  "data": [
    {
      "week_start": "2026-01-18",
      "week_end": "2026-01-24",
      "position": 0,
      "total_score": 46005,
      "days_participated": 7,
      "average_score": 6572.14,
      "best_daily_score": 6670,
      "best_daily_date": "2026-01-23",
      "playfab_id": "75CF47B738BDFCD5",
      "display_name": "ralack",
      "platform": "Steam",
      "platform_user_id": "76561197973553326"
    }
  ],
  "meta": {
    "week_start": "2026-01-18",
    "week_end": "2026-01-24",
    "count": 244,
    "requested_date": "2026-01-20"
  }
}
```

**Response (List Available Weeks):**
```json
{
  "success": true,
  "data": [
    {
      "week_start": "2026-01-18",
      "week_end": "2026-01-24",
      "player_count": 244,
      "top_score": 46005
    },
    {
      "week_start": "2026-01-11",
      "week_end": "2026-01-17",
      "player_count": 140,
      "top_score": 12574
    }
  ]
}
```

**Examples:**
```bash
# Get current week
curl "http://localhost/daily-fits/frontend/api/weekly.php"

# Get specific week (any date in that week)
curl "http://localhost/daily-fits/frontend/api/weekly.php?week=2026-01-20"

# List all available weeks
curl "http://localhost/daily-fits/frontend/api/weekly.php?list=1"
```

---

## Data Types

### Player Object
```typescript
{
  playfab_id: string;        // Unique PlayFab ID
  display_name: string;      // Player display name
  platform: string;          // "Steam", "Custom" (GOG), "NintendoSwitch"
  platform_user_id: string;  // Platform-specific user ID
  first_seen: string;        // ISO date (YYYY-MM-DD)
  last_seen: string;         // ISO date (YYYY-MM-DD)
}
```

### Daily Score Object
```typescript
{
  stat_date: string;         // ISO date (YYYY-MM-DD)
  statistic_name: string;    // e.g., "DailyPlay_Mon"
  position: number;          // Rank (0-indexed)
  score: number;             // Player score
  ...player;                 // Player object fields
}
```

### Weekly Score Object
```typescript
{
  week_start: string;        // Sunday of week (YYYY-MM-DD)
  week_end: string;          // Saturday of week (YYYY-MM-DD)
  position: number;          // Rank (0-indexed)
  total_score: number;       // Sum of daily scores
  days_participated: number; // Days played (0-7)
  average_score: number;     // Average score per day played
  best_daily_score: number;  // Highest daily score
  best_daily_date: string;   // Date of best score (YYYY-MM-DD)
  ...player;                 // Player object fields
}
```

---

## Error Responses

All endpoints return errors in the following format:

```json
{
  "success": false,
  "error": "Error message description"
}
```

**HTTP Status Codes:**
- `200 OK` - Successful request
- `400 Bad Request` - Invalid parameters (e.g., bad date format)
- `500 Internal Server Error` - Server/database error

---

## Rate Limiting

Currently, no rate limiting is implemented. API is intended for local/personal use.

---

## CORS

CORS is enabled for all origins (`Access-Control-Allow-Origin: *`).

---

## Notes

- All dates are in `YYYY-MM-DD` format
- Position/rank values are 0-indexed (0 = 1st place)
- Weekly leaderboards are pre-calculated and stored in database
- Data must be collected via CLI tools before appearing in API
- Session tokens required for data collection are not needed for API access

---

## Future Endpoints (Planned)

- `GET /players.php` - Player profile and statistics
- `GET /monthly.php` - Monthly leaderboard aggregates
- `GET /stats.php` - Global statistics and trends
- `GET /player/{id}.php` - Individual player history

---

**Project:** Fights in Tight Spaces - Daily Leaderboard Collector  
**Repository:** https://github.com/Hatem-D/daily-fits (private)  
**Version:** 1.0.0  
**Last Updated:** January 25, 2026
