<?php
/**
 * Vote Handler
 * Core voting logic with security, deduplication, and game management
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Vote_Handler {
    
    private $security;
    private $license_manager;
    private $analytics;
    
    public function __construct() {
        $this->security = new MHJoy_GR_Security();
        $this->license_manager = new MHJoy_GR_License_Manager();
        $this->analytics = new MHJoy_GR_Analytics();
    }
    
    /**
     * Process a vote submission
     */
    public function process_vote($vote_data) {
        global $wpdb;
        
        // Extract and validate data
        $game_data = isset($vote_data['game_data']) ? $vote_data['game_data'] : null;
        $voter_name = isset($vote_data['voter_name']) ? $vote_data['voter_name'] : '';
        $license_code = isset($vote_data['license_code']) ? $vote_data['license_code'] : '';
        $fingerprint = isset($vote_data['fingerprint']) ? $vote_data['fingerprint'] : '';
        $turnstile_token = isset($vote_data['turnstile_token']) ? $vote_data['turnstile_token'] : '';
        
        // Validate required fields
        if (!$game_data || !isset($game_data['rawg_id'])) {
            return new WP_Error('missing_game', 'Game data is required', ['status' => 400]);
        }
        
        if (empty($voter_name)) {
            return new WP_Error('missing_name', 'Voter name is required', ['status' => 400]);
        }
        
        // Get IP and user agent
        $ip_address = $this->security->get_client_ip();
        $user_agent = $this->security->get_user_agent();
        
        // Validate Turnstile token
        $turnstile_valid = $this->security->validate_turnstile($turnstile_token, $ip_address);
        if (is_wp_error($turnstile_valid)) {
            return $turnstile_valid;
        }
        
        // Sanitize voter name
        $voter_name = $this->security->sanitize_voter_name($voter_name);
        
        // Hash fingerprint for privacy
        $fingerprint_hash = $this->security->hash_fingerprint($fingerprint);
        
        // Get current user ID if logged in
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Check rate limit (for non-logged-in users)
        if (!$user_id) {
            $rate_check = $this->security->check_rate_limit($fingerprint_hash, $ip_address);
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
        }
        
        // Determine voter type and validate license if provided
        $voter_type = 'regular';
        if (!empty($license_code)) {
            $license_valid = $this->license_manager->validate_code($license_code);
            if (is_wp_error($license_valid)) {
                return $license_valid;
            }
            $voter_type = 'pro';
        }
        
        // Get or create game in database
        $game_id = $this->get_or_create_game($game_data);
        if (is_wp_error($game_id)) {
            return $game_id;
        }
        
        // Check if user already voted for this game
        $has_voted = MHJoy_GR_Database::has_voted($game_id, $user_id, $fingerprint_hash);
        if ($has_voted) {
            return new WP_Error('already_voted', 'You have already voted for this game', ['status' => 400]);
        }
        
        // Insert vote
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        $inserted = $wpdb->insert($table_votes, [
            'game_id' => $game_id,
            'voter_type' => $voter_type,
            'voter_name' => $voter_name,
            'user_id' => $user_id,
            'fingerprint' => $fingerprint_hash,
            'license_code' => !empty($license_code) ? strtoupper($license_code) : null,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'voted_at' => current_time('mysql')
        ]);
        
        if (!$inserted) {
            return new WP_Error('vote_failed', 'Failed to record vote', ['status' => 500]);
        }
        
        // If pro vote, bind license code to user
        if ($voter_type === 'pro' && !empty($license_code)) {
            $this->license_manager->bind_code_to_user($license_code, $user_id);
        }
        
        // Track analytics
        $this->analytics->track_event('vote', [
            'game_id' => $game_id,
            'user_id' => $user_id,
            'fingerprint' => $fingerprint_hash,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent
        ]);
        
        // Get updated vote counts
        $vote_counts = MHJoy_GR_Database::get_vote_count($game_id);
        
        return [
            'success' => true,
            'message' => $voter_type === 'pro' ? 'Pro vote recorded! (5x weight)' : 'Vote recorded!',
            'voter_type' => $voter_type,
            'vote_counts' => $vote_counts
        ];
    }
    
    /**
     * Get or create game in database
     * Race-safe using INSERT IGNORE
     */
    private function get_or_create_game($game_data) {
        global $wpdb;
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        
        $rawg_id = (int) $game_data['rawg_id'];
        
        // Check if game exists
        $existing_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table_games WHERE rawg_id = %d
        ", $rawg_id));
        
        if ($existing_id) {
            return (int) $existing_id;
        }
        
        // Insert new game (race-safe with IGNORE)
        $wpdb->query($wpdb->prepare("
            INSERT IGNORE INTO $table_games 
            (rawg_id, name, slug, background_image, release_year, status, created_at)
            VALUES (%d, %s, %s, %s, %s, 'active', %s)
        ", 
            $rawg_id,
            $game_data['name'],
            $game_data['slug'],
            isset($game_data['background_image']) ? $game_data['background_image'] : '',
            isset($game_data['release_year']) ? $game_data['release_year'] : null,
            current_time('mysql')
        ));
        
        // Get the ID (whether we just inserted or another request did)
        $game_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table_games WHERE rawg_id = %d
        ", $rawg_id));
        
        if (!$game_id) {
            return new WP_Error('game_creation_failed', 'Failed to create game record', ['status' => 500]);
        }
        
        return (int) $game_id;
    }
    
    /**
     * Get voters for a game
     */
    public function get_voters($game_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        
        $voters = $wpdb->get_results($wpdb->prepare("
            SELECT voter_name, voter_type, voted_at
            FROM $table_votes
            WHERE game_id = %d
            ORDER BY voted_at DESC
            LIMIT %d OFFSET %d
        ", $game_id, $limit, $offset));
        
        return array_map(function($voter) {
            return [
                'name' => $voter->voter_name,
                'type' => $voter->voter_type,
                'voted_at' => $voter->voted_at,
                'time_ago' => human_time_diff(strtotime($voter->voted_at), current_time('timestamp')) . ' ago'
            ];
        }, $voters);
    }
}
