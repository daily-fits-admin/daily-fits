#!/usr/bin/env python3
"""
Monthly Statistics Calculator for Fights in Tight Spaces Leaderboard Data

Computes monthly statistics including:
- Monthly champions
- Consistency rankings
- Score progression
- Participation trends

Usage:
    python monthly_stats.py [--month YYYY-MM] [--output OUTPUT_FILE]
"""

import sqlite3
import argparse
from datetime import datetime
from collections import defaultdict
from typing import Dict, List, Tuple
import json
import os
import calendar


def get_database_path() -> str:
    """Get the database path from environment or use default."""
    return os.getenv('DB_PATH', '../../data/fits.db')


def get_month_date_range(year: int, month: int) -> Tuple[str, str]:
    """
    Get start and end dates for a given month.
    
    Args:
        year: Year number
        month: Month number (1-12)
    
    Returns:
        Tuple of (start_date, end_date) in YYYY-MM-DD format
    """
    first_day = f'{year}-{month:02d}-01'
    last_day_num = calendar.monthrange(year, month)[1]
    last_day = f'{year}-{month:02d}-{last_day_num:02d}'
    
    return (first_day, last_day)


def calculate_monthly_stats(db_path: str, start_date: str, end_date: str) -> Dict:
    """
    Calculate monthly statistics from the database.
    
    Args:
        db_path: Path to SQLite database
        start_date: Start date (YYYY-MM-DD)
        end_date: End date (YYYY-MM-DD)
    
    Returns:
        Dictionary containing monthly statistics
    """
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    # Get all scores for the month
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
    top_10_counts = defaultdict(int)
    participation_counts = defaultdict(int)
    total_scores = defaultdict(list)
    daily_participants = defaultdict(set)
    
    for row in scores:
        playfab_id = row['playfab_id']
        position = row['position']
        score = row['score']
        stat_date = row['stat_date']
        
        # Track participation
        participation_counts[playfab_id] += 1
        daily_participants[stat_date].add(playfab_id)
        
        # Track scores
        total_scores[playfab_id].append(score)
        
        # Track rankings
        if position == 0:
            first_place_counts[playfab_id] += 1
        if position <= 2:
            podium_counts[playfab_id] += 1
        if position <= 9:
            top_10_counts[playfab_id] += 1
    
    # Build player name mapping
    player_names = {}
    cursor.execute("SELECT playfab_id, display_name FROM players")
    for row in cursor.fetchall():
        player_names[row['playfab_id']] = row['display_name']
    
    # Get total days with data
    cursor.execute("""
        SELECT COUNT(DISTINCT stat_date) as day_count
        FROM daily_scores
        WHERE stat_date BETWEEN ? AND ?
    """, (start_date, end_date))
    
    total_days = cursor.fetchone()['day_count']
    
    conn.close()
    
    # Format results
    results = {
        'month_period': {
            'start_date': start_date,
            'end_date': end_date
        },
        'monthly_champion': None,
        'top_champions': [],
        'top_podium_finishes': [],
        'top_10_finishes': [],
        'most_consistent': [],
        'participation_stats': {
            'total_unique_players': len(participation_counts),
            'total_days_tracked': total_days,
            'avg_daily_participants': sum(len(p) for p in daily_participants.values()) / max(len(daily_participants), 1)
        },
        'score_statistics': {}
    }
    
    # Determine monthly champion
    if first_place_counts:
        top_champions = sorted(first_place_counts.items(), key=lambda x: x[1], reverse=True)
        results['monthly_champion'] = {
            'playfab_id': top_champions[0][0],
            'display_name': player_names.get(top_champions[0][0], 'Unknown'),
            'first_place_count': top_champions[0][1],
            'win_percentage': (top_champions[0][1] / total_days * 100) if total_days > 0 else 0
        }
        
        results['top_champions'] = [
            {
                'playfab_id': pid,
                'display_name': player_names.get(pid, 'Unknown'),
                'first_place_count': count,
                'win_percentage': (count / total_days * 100) if total_days > 0 else 0
            }
            for pid, count in top_champions[:10]
        ]
    
    # Top podium finishers
    if podium_counts:
        top_podium = sorted(podium_counts.items(), key=lambda x: x[1], reverse=True)
        results['top_podium_finishes'] = [
            {
                'playfab_id': pid,
                'display_name': player_names.get(pid, 'Unknown'),
                'podium_count': count,
                'podium_percentage': (count / total_days * 100) if total_days > 0 else 0
            }
            for pid, count in top_podium[:10]
        ]
    
    # Top 10 finishers
    if top_10_counts:
        top_10 = sorted(top_10_counts.items(), key=lambda x: x[1], reverse=True)
        results['top_10_finishes'] = [
            {
                'playfab_id': pid,
                'display_name': player_names.get(pid, 'Unknown'),
                'top_10_count': count
            }
            for pid, count in top_10[:20]
        ]
    
    # Most consistent players
    if participation_counts:
        consistent = []
        for pid, days_played in participation_counts.items():
            if days_played >= 5 and pid in total_scores:  # At least 5 days
                scores_list = total_scores[pid]
                avg_score = sum(scores_list) / len(scores_list)
                max_score = max(scores_list)
                min_score = min(scores_list)
                
                consistent.append({
                    'playfab_id': pid,
                    'display_name': player_names.get(pid, 'Unknown'),
                    'days_played': days_played,
                    'average_score': int(avg_score),
                    'max_score': max_score,
                    'min_score': min_score,
                    'consistency_rating': days_played * avg_score
                })
        
        consistent.sort(key=lambda x: x['consistency_rating'], reverse=True)
        results['most_consistent'] = consistent[:15]
    
    # Overall score statistics
    if total_scores:
        all_scores = [s for scores in total_scores.values() for s in scores]
        results['score_statistics'] = {
            'total_scores_recorded': len(all_scores),
            'average_score': int(sum(all_scores) / len(all_scores)),
            'highest_score': max(all_scores),
            'lowest_score': min(all_scores)
        }
    
    return results


def main():
    parser = argparse.ArgumentParser(
        description='Calculate monthly statistics for Fights in Tight Spaces leaderboard'
    )
    parser.add_argument(
        '--month',
        help='Month to analyze in YYYY-MM format (default: current month)',
        default=None
    )
    parser.add_argument(
        '--output',
        help='Output file path (JSON format)',
        default=None
    )
    
    args = parser.parse_args()
    
    # Determine month
    if args.month:
        year, month = map(int, args.month.split('-'))
    else:
        today = datetime.now()
        year = today.year
        month = today.month
    
    start_date, end_date = get_month_date_range(year, month)
    month_name = calendar.month_name[month]
    
    print(f"Calculating monthly statistics for {month_name} {year}")
    print(f"Date range: {start_date} to {end_date}")
    print()
    
    # Get database path
    db_path = get_database_path()
    
    if not os.path.exists(db_path):
        print(f"Error: Database not found at {db_path}")
        return 1
    
    # Calculate statistics
    stats = calculate_monthly_stats(db_path, start_date, end_date)
    
    # Display results
    print("=" * 70)
    print(f"MONTHLY STATISTICS - {month_name.upper()} {year}")
    print("=" * 70)
    print()
    
    if stats['monthly_champion']:
        champ = stats['monthly_champion']
        print(f"🏆 Monthly Champion: {champ['display_name']}")
        print(f"   First place wins: {champ['first_place_count']} ({champ['win_percentage']:.1f}%)")
        print()
    
    print("Top 10 - Monthly Champions:")
    for i, player in enumerate(stats['top_champions'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - "
              f"{player['first_place_count']:2d} wins ({player['win_percentage']:5.1f}%)")
    print()
    
    print("Top 10 - Podium Finishes (Top 3):")
    for i, player in enumerate(stats['top_podium_finishes'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - "
              f"{player['podium_count']:2d} podiums ({player['podium_percentage']:5.1f}%)")
    print()
    
    print("Most Consistent Players:")
    for i, player in enumerate(stats['most_consistent'], 1):
        print(f"  {i:2d}. {player['display_name']:30s} - "
              f"{player['days_played']:2d} days, avg {player['average_score']:5d} pts")
    print()
    
    print("Participation Statistics:")
    pstats = stats['participation_stats']
    print(f"  Total unique players: {pstats['total_unique_players']}")
    print(f"  Days tracked: {pstats['total_days_tracked']}")
    print(f"  Average daily participants: {pstats['avg_daily_participants']:.1f}")
    print()
    
    if stats['score_statistics']:
        sstats = stats['score_statistics']
        print("Score Statistics:")
        print(f"  Total scores recorded: {sstats['total_scores_recorded']}")
        print(f"  Average score: {sstats['average_score']}")
        print(f"  Highest score: {sstats['highest_score']}")
        print(f"  Lowest score: {sstats['lowest_score']}")
        print()
    
    # Save to file if requested
    if args.output:
        with open(args.output, 'w') as f:
            json.dump(stats, f, indent=2)
        print(f"✓ Results saved to {args.output}")
    
    return 0


if __name__ == '__main__':
    exit(main())
