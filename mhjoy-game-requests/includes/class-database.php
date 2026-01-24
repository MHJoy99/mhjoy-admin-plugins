<?php
/**
 * Database Schema Manager
 * Creates and manages all database tables for the game request system
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Database {
    
    public function __construct() {
        // Database is created on plugin activation
    }
    
    /**
     * Create all required database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Table 1: Game Requests
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        $sql_games = "CREATE TABLE $table_games (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rawg_id INT UNSIGNED NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            background_image TEXT,
            release_year YEAR,
            status ENUM('active', 'completed') DEFAULT 'active',
            completed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_rawg_id (rawg_id),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_games);
        
        // Table 2: Game Votes
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        $sql_votes = "CREATE TABLE $table_votes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            game_id BIGINT UNSIGNED NOT NULL,
            voter_type ENUM('regular', 'pro') DEFAULT 'regular',
            voter_name VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            fingerprint VARCHAR(64) NULL,
            license_code VARCHAR(50) NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_game_id (game_id),
            INDEX idx_voter_type (voter_type),
            INDEX idx_user_id (user_id),
            INDEX idx_fingerprint (fingerprint),
            INDEX idx_voted_at (voted_at)
        ) $charset_collate;";
        dbDelta($sql_votes);
        
        // Table 3: Analytics Events
        $table_analytics = $wpdb->prefix . 'mhjoy_game_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type ENUM('page_view', 'search', 'vote', 'game_view') NOT NULL,
            game_id BIGINT UNSIGNED NULL,
            search_query VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NULL,
            fingerprint VARCHAR(64) NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            country_code CHAR(2) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_game_id (game_id),
            INDEX idx_created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_analytics);
        
        // Table 4: License Codes (for Pro voters)
        $table_licenses = $wpdb->prefix . 'mhjoy_license_codes';
        $sql_licenses = "CREATE TABLE $table_licenses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NULL,
            status ENUM('active', 'used', 'expired') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            used_at DATETIME NULL,
            INDEX idx_code (code),
            INDEX idx_status (status)
        ) $charset_collate;";
        dbDelta($sql_licenses);
        
        error_log('✅ MHJoy Game Request tables created successfully');
    }
    
    /**
     * Get vote count for a game with weighted calculation
     * Regular votes = 1x, Pro votes = 5x
     */
    public static function get_vote_count($game_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_game_votes';
        
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_votes,
                SUM(CASE WHEN voter_type = 'regular' THEN 1 ELSE 0 END) as regular_votes,
                SUM(CASE WHEN voter_type = 'pro' THEN 1 ELSE 0 END) as pro_votes,
                SUM(CASE WHEN voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table
            WHERE game_id = %d
        ", $game_id));
        
        return [
            'total_votes' => (int) $result->total_votes,
            'regular_votes' => (int) $result->regular_votes,
            'pro_votes' => (int) $result->pro_votes,
            'weighted_score' => (int) $result->weighted_score
        ];
    }
    
    /**
     * Check if user has already voted for a game
     */
    public static function has_voted($game_id, $user_id = null, $fingerprint = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_game_votes';
        
        if ($user_id) {
            // Check by user ID (for logged-in users)
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $table 
                WHERE game_id = %d AND user_id = %d
            ", $game_id, $user_id));
        } elseif ($fingerprint) {
            // Check by fingerprint (for guests)
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $table 
                WHERE game_id = %d AND fingerprint = %s
            ", $game_id, $fingerprint));
        } else {
            return false;
        }
        
        return $count > 0;
    }
}
