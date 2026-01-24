<?php
/**
 * Rawg.io API Integration
 * Handles game search and data fetching from Rawg API
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Rawg_API {
    
    private $api_key;
    private $base_url = 'https://api.rawg.io/api';
    private $cache_duration = 3600; // 1 hour
    
    public function __construct() {
        $this->api_key = get_option('mhjoy_gr_rawg_api_key', '');
        $this->cache_duration = get_option('mhjoy_gr_cache_duration', 3600);
    }
    
    /**
     * Search for games
     */
    public function search_games($query, $page = 1, $page_size = 10) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Rawg API key not configured', ['status' => 500]);
        }
        
        if (empty($query) || strlen($query) < 2) {
            return new WP_Error('invalid_query', 'Search query must be at least 2 characters', ['status' => 400]);
        }
        
        // Check cache first
        $cache_key = 'mhjoy_gr_search_' . md5($query . $page . $page_size);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Make API request
        $url = add_query_arg([
            'key' => $this->api_key,
            'search' => $query,
            'page' => $page,
            'page_size' => $page_size,
            'ordering' => '-relevance'
        ], $this->base_url . '/games');
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'MHJoyGamersHub/1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', 'Rawg API returned error: ' . $code, ['status' => $code]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['results'])) {
            return new WP_Error('invalid_response', 'Invalid API response', ['status' => 500]);
        }
        
        // Format results
        $results = array_map(function($game) {
            return [
                'rawg_id' => $game['id'],
                'name' => $game['name'],
                'slug' => $game['slug'],
                'background_image' => $game['background_image'] ?? '',
                'release_year' => !empty($game['released']) ? date('Y', strtotime($game['released'])) : null,
                'platforms' => array_map(function($p) {
                    return $p['platform']['name'] ?? '';
                }, $game['platforms'] ?? [])
            ];
        }, $data['results']);
        
        // Cache results
        set_transient($cache_key, $results, $this->cache_duration);
        
        return $results;
    }
    
    /**
     * Get detailed game information
     */
    public function get_game_details($rawg_id) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'Rawg API key not configured', ['status' => 500]);
        }
        
        // Check cache
        $cache_key = 'mhjoy_gr_game_' . $rawg_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = add_query_arg([
            'key' => $this->api_key
        ], $this->base_url . '/games/' . $rawg_id);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'MHJoyGamersHub/1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', 'Rawg API returned error: ' . $code, ['status' => $code]);
        }
        
        $body = wp_remote_retrieve_body($response);
        $game = json_decode($body, true);
        
        if (!$game) {
            return new WP_Error('invalid_response', 'Invalid API response', ['status' => 500]);
        }
        
        $details = [
            'rawg_id' => $game['id'],
            'name' => $game['name'],
            'slug' => $game['slug'],
            'background_image' => $game['background_image'] ?? '',
            'release_year' => !empty($game['released']) ? date('Y', strtotime($game['released'])) : null,
            'description' => $game['description_raw'] ?? '',
            'metacritic' => $game['metacritic'] ?? null,
            'platforms' => array_map(function($p) {
                return $p['platform']['name'] ?? '';
            }, $game['platforms'] ?? [])
        ];
        
        // Cache for longer (24 hours)
        set_transient($cache_key, $details, DAY_IN_SECONDS);
        
        return $details;
    }
    
    /**
     * Clear all Rawg API caches
     */
    public function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mhjoy_gr_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mhjoy_gr_%'");
    }
}
