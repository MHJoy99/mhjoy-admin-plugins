<?php
/**
 * Analytics Tracker
 * Tracks all user interactions and generates dashboard statistics
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Analytics {
    
    /**
     * Track an analytics event
     */
    public function track_event($event_type, $data = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_game_analytics';
        
        $wpdb->insert($table, [
            'event_type' => $event_type,
            'game_id' => isset($data['game_id']) ? $data['game_id'] : null,
            'search_query' => isset($data['search_query']) ? substr($data['search_query'], 0, 255) : null,
            'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
            'fingerprint' => isset($data['fingerprint']) ? $data['fingerprint'] : null,
            'ip_address' => isset($data['ip_address']) ? $data['ip_address'] : null,
            'user_agent' => isset($data['user_agent']) ? substr($data['user_agent'], 0, 500) : null,
            'country_code' => isset($data['country_code']) ? $data['country_code'] : null,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Get rich activity logs with user and game details
     */
    public function get_activity_logs($limit = 50, $offset = 0, $event_type = null) {
        global $wpdb;
        $table_analytics = $wpdb->prefix . 'mhjoy_game_analytics';
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        
        $where = '';
        if ($event_type) {
            $where = $wpdb->prepare("WHERE a.event_type = %s", $event_type);
        }
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                a.*,
                g.name as game_name,
                g.background_image as game_image
            FROM $table_analytics a
            LEFT JOIN $table_games g ON a.game_id = g.id
            $where
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
        
        return $logs;
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        global $wpdb;
        $table_analytics = $wpdb->prefix . 'mhjoy_game_analytics';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        
        // Total votes
        $total_votes = $wpdb->get_var("SELECT COUNT(*) FROM $table_votes");
        
        // Votes today
        $today_start = date('Y-m-d 00:00:00');
        $votes_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_votes WHERE voted_at >= %s
        ", $today_start));
        
        // Votes this week
        $week_start = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $votes_this_week = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_votes WHERE voted_at >= %s
        ", $week_start));
        
        // Unique voters
        $unique_voters = $wpdb->get_var("
            SELECT COUNT(DISTINCT COALESCE(user_id, fingerprint)) FROM $table_votes
        ");
        
        // Pro vs Regular ratio
        $voter_breakdown = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN voter_type = 'regular' THEN 1 ELSE 0 END) as regular_count,
                SUM(CASE WHEN voter_type = 'pro' THEN 1 ELSE 0 END) as pro_count
            FROM $table_votes
        ");
        
        // Total games requested
        $total_games = $wpdb->get_var("SELECT COUNT(*) FROM $table_games WHERE status = 'active'");
        $completed_games = $wpdb->get_var("SELECT COUNT(*) FROM $table_games WHERE status = 'completed'");
        
        // Top voted games
        $top_games = $wpdb->get_results("
            SELECT 
                g.id,
                g.name,
                g.background_image,
                COUNT(v.id) as vote_count,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table_games g
            LEFT JOIN $table_votes v ON g.id = v.game_id
            WHERE g.status = 'active'
            GROUP BY g.id
            ORDER BY weighted_score DESC
            LIMIT 10
        ");
        
        // Most searched games (not voted)
        $top_searches = $wpdb->get_results("
            SELECT search_query, COUNT(*) as search_count
            FROM $table_analytics
            WHERE event_type = 'search' AND search_query IS NOT NULL
            GROUP BY search_query
            ORDER BY search_count DESC
            LIMIT 10
        ");
        
        // Page views
        $page_views_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_analytics 
            WHERE event_type = 'page_view' AND created_at >= %s
        ", $today_start));
        
        // Vote conversion rate (votes / page views)
        $conversion_rate = $page_views_today > 0 ? ($votes_today / $page_views_today) * 100 : 0;
        
        // Votes over time (last 30 days)
        $votes_timeline = $wpdb->get_results("
            SELECT 
                DATE(voted_at) as date,
                COUNT(*) as count
            FROM $table_votes
            WHERE voted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(voted_at)
            ORDER BY date ASC
        ");
        
        return [
            'total_votes' => (int) $total_votes,
            'votes_today' => (int) $votes_today,
            'votes_this_week' => (int) $votes_this_week,
            'unique_voters' => (int) $unique_voters,
            'regular_votes' => (int) $voter_breakdown->regular_count,
            'pro_votes' => (int) $voter_breakdown->pro_count,
            'total_games' => (int) $total_games,
            'completed_games' => (int) $completed_games,
            'top_games' => $top_games,
            'top_searches' => $top_searches,
            'page_views_today' => (int) $page_views_today,
            'conversion_rate' => round($conversion_rate, 2),
            'votes_timeline' => $votes_timeline
        ];
    }
    
    /**
     * Export analytics to CSV
     */
    public function export_to_csv($type = 'votes', $start_date = null, $end_date = null) {
        global $wpdb;
        
        if ($type === 'votes') {
            $table = $wpdb->prefix . 'mhjoy_game_votes';
            $query = "SELECT v.*, g.name as game_name FROM $table v 
                      LEFT JOIN {$wpdb->prefix}mhjoy_request_games g ON v.game_id = g.id";
        } else {
            $table = $wpdb->prefix . 'mhjoy_game_analytics';
            $query = "SELECT * FROM $table";
        }
        
        if ($start_date) {
            $query .= $wpdb->prepare(" WHERE created_at >= %s", $start_date);
        }
        if ($end_date) {
            $query .= $wpdb->prepare(" AND created_at <= %s", $end_date);
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (empty($results)) {
            return false;
        }
        
        // Generate CSV
        $csv = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($csv, array_keys($results[0]));
        
        // Data
        foreach ($results as $row) {
            fputcsv($csv, $row);
        }
        
        rewind($csv);
        $output = stream_get_contents($csv);
        fclose($csv);
        
        return $output;
    }
}
