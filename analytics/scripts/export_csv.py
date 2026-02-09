#!/usr/bin/env python3
"""
CSV/JSON Export Tool for Fights in Tight Spaces Leaderboard Data

Exports leaderboard data to CSV or JSON format for external analysis.

Usage:
    python export_csv.py [--format FORMAT] [--start-date YYYY-MM-DD] [--end-date YYYY-MM-DD] [--output OUTPUT_FILE]
"""

import sqlite3
import argparse
import csv
import json
from datetime import datetime
from typing import List, Dict
import os


def get_database_path() -> str:
    """Get the database path from environment or use default."""
    return os.getenv('DB_PATH', '../../data/fits.db')


def export_daily_scores(db_path: str, start_date: str = None, end_date: str = None) -> List[Dict]:
    """
    Export daily scores from the database.
    
    Args:
        db_path: Path to SQLite database
        start_date: Optional start date filter (YYYY-MM-DD)
        end_date: Optional end date filter (YYYY-MM-DD)
    
    Returns:
        List of score records
    """
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    query = """
        SELECT 
            ds.stat_date,
            ds.statistic_name,
            ds.position,
            ds.score,
            ds.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id
        FROM daily_scores ds
        JOIN players p ON ds.playfab_id = p.playfab_id
    """
    
    params = []
    conditions = []
    
    if start_date:
        conditions.append("ds.stat_date >= ?")
        params.append(start_date)
    
    if end_date:
        conditions.append("ds.stat_date <= ?")
        params.append(end_date)
    
    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    
    query += " ORDER BY ds.stat_date, ds.position"
    
    cursor.execute(query, params)
    
    records = []
    for row in cursor.fetchall():
        records.append({
            'stat_date': row['stat_date'],
            'statistic_name': row['statistic_name'],
            'position': row['position'],
            'score': row['score'],
            'playfab_id': row['playfab_id'],
            'display_name': row['display_name'],
            'platform': row['platform'],
            'platform_user_id': row['platform_user_id']
        })
    
    conn.close()
    return records


def export_player_summary(db_path: str) -> List[Dict]:
    """
    Export player summary statistics.
    
    Args:
        db_path: Path to SQLite database
    
    Returns:
        List of player summary records
    """
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT 
            p.playfab_id,
            p.display_name,
            p.platform,
            p.platform_user_id,
            p.first_seen,
            p.last_seen,
            COUNT(ds.stat_date) as total_days_played,
            COUNT(CASE WHEN ds.position = 0 THEN 1 END) as first_place_count,
            COUNT(CASE WHEN ds.position <= 2 THEN 1 END) as podium_count,
            COUNT(CASE WHEN ds.position <= 9 THEN 1 END) as top_10_count,
            AVG(ds.score) as average_score,
            MAX(ds.score) as max_score,
            MIN(ds.score) as min_score
        FROM players p
        LEFT JOIN daily_scores ds ON p.playfab_id = ds.playfab_id
        GROUP BY p.playfab_id
        ORDER BY total_days_played DESC, average_score DESC
    """)
    
    records = []
    for row in cursor.fetchall():
        records.append({
            'playfab_id': row['playfab_id'],
            'display_name': row['display_name'],
            'platform': row['platform'],
            'platform_user_id': row['platform_user_id'],
            'first_seen': row['first_seen'],
            'last_seen': row['last_seen'],
            'total_days_played': row['total_days_played'],
            'first_place_count': row['first_place_count'],
            'podium_count': row['podium_count'],
            'top_10_count': row['top_10_count'],
            'average_score': round(row['average_score'], 2) if row['average_score'] else 0,
            'max_score': row['max_score'],
            'min_score': row['min_score']
        })
    
    conn.close()
    return records


def write_csv(records: List[Dict], output_path: str) -> None:
    """
    Write records to CSV file.
    
    Args:
        records: List of record dictionaries
        output_path: Output file path
    """
    if not records:
        print("No records to export")
        return
    
    with open(output_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.DictWriter(f, fieldnames=records[0].keys())
        writer.writeheader()
        writer.writerows(records)


def write_json(records: List[Dict], output_path: str) -> None:
    """
    Write records to JSON file.
    
    Args:
        records: List of record dictionaries
        output_path: Output file path
    """
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(records, f, indent=2)


def main():
    parser = argparse.ArgumentParser(
        description='Export Fights in Tight Spaces leaderboard data'
    )
    parser.add_argument(
        '--format',
        choices=['csv', 'json'],
        default='csv',
        help='Output format (default: csv)'
    )
    parser.add_argument(
        '--type',
        choices=['scores', 'players'],
        default='scores',
        help='Data type to export: scores (daily leaderboard) or players (summary)'
    )
    parser.add_argument(
        '--start-date',
        help='Start date for scores export (YYYY-MM-DD)',
        default=None
    )
    parser.add_argument(
        '--end-date',
        help='End date for scores export (YYYY-MM-DD)',
        default=None
    )
    parser.add_argument(
        '--output',
        help='Output file path (default: auto-generated)',
        default=None
    )
    
    args = parser.parse_args()
    
    # Get database path
    db_path = get_database_path()
    
    if not os.path.exists(db_path):
        print(f"Error: Database not found at {db_path}")
        return 1
    
    # Determine output filename
    if args.output:
        output_path = args.output
    else:
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        output_path = f"export_{args.type}_{timestamp}.{args.format}"
    
    print(f"Exporting {args.type} data to {output_path}...")
    
    # Export data
    if args.type == 'scores':
        records = export_daily_scores(db_path, args.start_date, args.end_date)
        print(f"Exported {len(records)} score records")
    else:
        records = export_player_summary(db_path)
        print(f"Exported {len(records)} player records")
    
    # Write to file
    if args.format == 'csv':
        write_csv(records, output_path)
    else:
        write_json(records, output_path)
    
    print(f"✓ Export completed successfully")
    print(f"  Output: {output_path}")
    print(f"  Records: {len(records)}")
    
    return 0


if __name__ == '__main__':
    exit(main())
