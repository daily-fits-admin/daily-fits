#!/usr/bin/env python3
"""
Weekly Statistics Calculator for Fights in Tight Spaces Leaderboard Data

Computes weekly statistics including:
- Weekly winners (most frequent #1 positions)
- Top 3 consistency (podium finishes)
- Total participation counts
- Average scores

Usage:
    python weekly_stats.py [--week YYYY-WW] [--output OUTPUT_FILE]
"""

import sqlite3
import argparse
from datetime import datetime, timedelta
from collections import defaultdict
from typing import Dict, List, Tuple
import json
import os


def get_database_path() -> str:
    """Get the database path from environment or use default."""
    return os.getenv('DB_PATH', '../../data/fits.db')


def get_week_date_range(year: int, week: int) -> Tuple[str, str]:
    """
    Get start and end dates for a given ISO week.
    
    Args:
        year: Year number
        week: ISO week number (1-53)
    
    Returns:
        Tuple of (start_date, end_date) in YYYY-MM-DD format
    """
    # Get the first day of the ISO week
    first_day = datetime.strptime(f'{year}-W{week:02d}-1', '%Y-W%W-%w')
    last_day = first_day + timedelta(days=6)
    
    return (first_day.strftime('%Y-%m-%d'), last_day.strftime('%Y-%m-%d'))


def calculate_weekly_stats(db_path: str, start_date: str, end_date: str) -> Dict:
    """
    Calculate weekly statistics from the database.
    
    Args:
        db_path: Path to SQLite database
        start_date: Start date (YYYY-MM-DD)
        end_date: End date (YYYY-MM-DD)
    
    Returns:
        Dictionary containing weekly statistics
    """
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    # Get all scores for the week
    cursor.execute("""
        SELECT 
            ds.stat_date,
            ds.position,
            ds.score,
            ds.playfab_id,
            p.display_name
        FROM daily_scores ds
        JOIN players p ON ds.playfab_id = p.playfab_id
        WHERE ds.stat_date BETWEEN ? AND ?
        ORDER BY ds.stat_date, ds.position
    """, (start_date, end_date))
    
    scores = cursor.fetchall()
    
    # Calculate statistics
    first_place_counts = defaultdict(int)
    podium_counts = defaultdict(int)  # Top 3
    participation_counts = defaultdict(int)
    total_scores = defaultdict(list)
    daily_winners = {}
    
    for row in scores:
        playfab_id = row['playfab_id']
        display_name = row['display_name']
        position = row['position']
        score = row['score']
        stat_date = row['stat_date']
        
        # Track participation
        participation_counts[playfab_id] = participation_counts.get(playfab_id, 0) + 1
        
        # Track scores
        if playfab_id not in total_scores:
            total_scores[playfab_id] = []
        total_scores[playfab_id].append(score)
        
        # Track first place
        if position == 0:  # Position 0 is first place
            first_place_counts[playfab_id] += 1
            daily_winners[stat_date] = {
                'playfab_id': playfab_id,
                'display_name': display_name,
                'score': score
            }
        
        # Track podium (top 3)
        if position <= 2:
            podium_counts[playfab_id] += 1
    
    # Build player name mapping
    player_names = {}
    cursor.execute("SELECT playfab_id, display_name FROM players")
    for row in cursor.fetchall():
        player_names[row['playfab_id']] = row['display_name']
    
    conn.close()
    
    # Format results
    results = {
        'week_period': {
            'start_date': start_date,
            'end_date': end_date
        },
        'daily_winners': daily_winners,
        'weekly_champion': None,
        'top_first_places': [],
        'top_podium_finishes': [],
        'most_consistent': [],
        'total_unique_players': len(participation_counts)
    }
    
    # Determine weekly champion (most first places)
    if first_place_counts:
        top_first = sorted(first_place_counts.items(), key=lambda x: x[1], reverse=True)
        results['weekly_champion'] = {
            'playfab_id': top_first[0][0],
            'display_name': player_names.get(top_first[0][0], 'Unknown'),
            'first_place_count': top_first[0][1]
        }
        
        results['top_first_places'] = [
            {
                'playfab_id': pid,
                'display_name': player_names.get(pid, 'Unknown'),
                'count': count
            }
            for pid, count in top_first[:10]
        ]
    
    # Top podium finishers
    if podium_counts:
        top_podium = sorted(podium_counts.items(), key=lambda x: x[1], reverse=True)
        results['top_podium_finishes'] = [
            {
                'playfab_id': pid,
                'display_name': player_names.get(pid, 'Unknown'),
                'count': count
            }
            for pid, count in top_podium[:10]
        ]
    
    # Most consistent (played all days and good average)
    if participation_counts:
        consistent = []
        for pid, days_played in participation_counts.items():
            if days_played >= 3 and pid in total_scores:  # At least 3 days
                avg_score = sum(total_scores[pid]) / len(total_scores[pid])
                consistent.append({
                    'playfab_id': pid,
                    'display_name': player_names.get(pid, 'Unknown'),
                    'days_played': days_played,
                    'average_score': int(avg_score)
                })
        
        consistent.sort(key=lambda x: (x['days_played'], x['average_score']), reverse=True)
        results['most_consistent'] = consistent[:10]
    
    return results


def main():
    parser = argparse.ArgumentParser(
        description='Calculate weekly statistics for Fights in Tight Spaces leaderboard'
    )
    parser.add_argument(
        '--week',
        help='Week to analyze in YYYY-WW format (default: current week)',
        default=None
    )
    parser.add_argument(
        '--output',
        help='Output file path (JSON format)',
        default=None
    )
    
    args = parser.parse_args()
    
    # Determine week
    if args.week:
        year, week = map(int, args.week.split('-W'))
    else:
        today = datetime.now()
        year = today.year
        week = int(today.strftime('%W'))
    
    start_date, end_date = get_week_date_range(year, week)
    
    print(f"Calculating weekly statistics for week {year}-W{week:02d}")
    print(f"Date range: {start_date} to {end_date}")
    print()
    
    # Get database path
    db_path = get_database_path()
    
    if not os.path.exists(db_path):
        print(f"Error: Database not found at {db_path}")
        return 1
    
    # Calculate statistics
    stats = calculate_weekly_stats(db_path, start_date, end_date)
    
    # Display results
    print("=" * 60)
    print("WEEKLY STATISTICS")
    print("=" * 60)
    print()
    
    if stats['weekly_champion']:
        champ = stats['weekly_champion']
        print(f"🏆 Weekly Champion: {champ['display_name']}")
        print(f"   First place wins: {champ['first_place_count']}")
        print()
    
    print("Daily Winners:")
    for date, winner in sorted(stats['daily_winners'].items()):
        print(f"  {date}: {winner['display_name']} ({winner['score']} points)")
    print()
    
    print("Top 10 - First Place Finishes:")
    for i, player in enumerate(stats['top_first_places'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - {player['count']} wins")
    print()
    
    print("Top 10 - Podium Finishes (Top 3):")
    for i, player in enumerate(stats['top_podium_finishes'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - {player['count']} podiums")
    print()
    
    print("Most Consistent Players:")
    for i, player in enumerate(stats['most_consistent'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - "
              f"{player['days_played']} days, avg {player['average_score']} pts")
    print()
    
    print(f"Total unique players: {stats['total_unique_players']}")
    print()
    
    # Save to file if requested
    if args.output:
        with open(args.output, 'w') as f:
            json.dump(stats, f, indent=2)
        print(f"✓ Results saved to {args.output}")
    
    return 0


if __name__ == '__main__':
    exit(main())
