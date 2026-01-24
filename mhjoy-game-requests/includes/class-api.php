<?php
/**
 * REST API Endpoints
 * Provides all API endpoints for the React frontend
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_API {
    
    private $rawg_api;
    private $vote_handler;
    private $analytics;
    private $license_manager;
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        
        $this->rawg_api = new MHJoy_GR_Rawg_API();
        $this->vote_handler = new MHJoy_GR_Vote_Handler();
        $this->analytics = new MHJoy_GR_Analytics();
        $this->license_manager = new MHJoy_GR_License_Manager();
    }
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        $namespace = 'mhjoy/v1';
        
        // Public endpoints
        register_rest_route($namespace, '/games/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_games'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/games/list', [
            'methods' => 'GET',
            'callback' => [$this, 'get_games_list'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/games/completed', [
            'methods' => 'GET',
            'callback' => [$this, 'get_completed_games'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/games/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_game_details'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/games/vote', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_vote'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/games/validate-license', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_license'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route($namespace, '/analytics/track', [
            'methods' => 'POST',
            'callback' => [$this, 'track_analytics'],
            'permission_callback' => '__return_true'
        ]);
        
        // Admin endpoints
        register_rest_route($namespace, '/admin/games/fulfill', [
            'methods' => 'POST',
            'callback' => [$this, 'fulfill_game'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        register_rest_route($namespace, '/admin/games/bulk-action', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_action'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        register_rest_route($namespace, '/admin/analytics/dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics_dashboard'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        register_rest_route($namespace, '/admin/licenses/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate_licenses'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
    }
    
    /**
     * Search games via Rawg API
     */
    public function search_games($request) {
        $query = $request->get_param('q');
        $page = $request->get_param('page') ?: 1;
        $page_size = $request->get_param('page_size') ?: 10;
        
        // Track search analytics
        $security = new MHJoy_GR_Security();
        $this->analytics->track_event('search', [
            'search_query' => $query,
            'ip_address' => $security->get_client_ip(),
            'user_agent' => $security->get_user_agent()
        ]);
        
        $results = $this->rawg_api->search_games($query, $page, $page_size);
        
        if (is_wp_error($results)) {
            return $results;
        }
        
        return new WP_REST_Response($results, 200);
    }
    
    /**
     * Get list of all active game requests
     */
    public function get_games_list($request) {
        global $wpdb;
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        
        $sort = $request->get_param('sort') ?: 'votes'; // votes, newest, alphabetical
        $limit = $request->get_param('limit') ?: 50;
        $offset = $request->get_param('offset') ?: 0;
        
        $order_by = 'weighted_score DESC';
        if ($sort === 'newest') {
            $order_by = 'g.created_at DESC';
        } elseif ($sort === 'alphabetical') {
            $order_by = 'g.name ASC';
        }
        
        $games = $wpdb->get_results($wpdb->prepare("
            SELECT 
                g.*,
                COUNT(v.id) as total_votes,
                SUM(CASE WHEN v.voter_type = 'regular' THEN 1 ELSE 0 END) as regular_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 1 ELSE 0 END) as pro_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table_games g
            LEFT JOIN $table_votes v ON g.id = v.game_id
            WHERE g.status = 'active'
            GROUP BY g.id
            ORDER BY $order_by
            LIMIT %d OFFSET %d
        ", $limit, $offset));
        
        // Get voters for each game (last 5)
        foreach ($games as &$game) {
            $game->voters = $this->vote_handler->get_voters($game->id, 5, 0);
        }
        
        return new WP_REST_Response($games, 200);
    }
    
    /**
     * Get completed/fulfilled games
     */
    public function get_completed_games($request) {
        global $wpdb;
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        
        $limit = $request->get_param('limit') ?: 50;
        $offset = $request->get_param('offset') ?: 0;
        
        $games = $wpdb->get_results($wpdb->prepare("
            SELECT 
                g.*,
                COUNT(v.id) as total_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table_games g
            LEFT JOIN $table_votes v ON g.id = v.game_id
            WHERE g.status = 'completed'
            GROUP BY g.id
            ORDER BY g.completed_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
        
        return new WP_REST_Response($games, 200);
    }
    
    /**
     * Get single game details with all voters
     */
    public function get_game_details($request) {
        global $wpdb;
        $game_id = $request->get_param('id');
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        
        $game = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_games WHERE id = %d", $game_id));
        
        if (!$game) {
            return new WP_Error('not_found', 'Game not found', ['status' => 404]);
        }
        
        $vote_counts = MHJoy_GR_Database::get_vote_count($game_id);
        $voters = $this->vote_handler->get_voters($game_id, 100, 0);
        
        $game->vote_counts = $vote_counts;
        $game->voters = $voters;
        
        // Track view
        $security = new MHJoy_GR_Security();
        $this->analytics->track_event('game_view', [
            'game_id' => $game_id,
            'ip_address' => $security->get_client_ip()
        ]);
        
        return new WP_REST_Response($game, 200);
    }
    
    /**
     * Submit a vote
     */
    public function submit_vote($request) {
        $params = $request->get_json_params();
        
        $result = $this->vote_handler->process_vote($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Validate license code
     */
    public function validate_license($request) {
        $params = $request->get_json_params();
        $code = isset($params['code']) ? $params['code'] : '';
        
        $result = $this->license_manager->validate_code($code);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response(['valid' => true, 'message' => 'License code is valid'], 200);
    }
    
    /**
     * Track analytics event
     */
    public function track_analytics($request) {
        $params = $request->get_json_params();
        $event_type = isset($params['event_type']) ? $params['event_type'] : '';
        
        $security = new MHJoy_GR_Security();
        $this->analytics->track_event($event_type, array_merge($params, [
            'ip_address' => $security->get_client_ip(),
            'user_agent' => $security->get_user_agent()
        ]));
        
        return new WP_REST_Response(['success' => true], 200);
    }
    
    /**
     * Admin: Fulfill a game request
     */
    public function fulfill_game($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $game_id = isset($params['game_id']) ? $params['game_id'] : 0;
        
        $table = $wpdb->prefix . 'mhjoy_request_games';
        $updated = $wpdb->update($table, [
            'status' => 'completed',
            'completed_at' => current_time('mysql')
        ], ['id' => $game_id]);
        
        if ($updated === false) {
            return new WP_Error('update_failed', 'Failed to update game status', ['status' => 500]);
        }
        
        return new WP_REST_Response(['success' => true, 'message' => 'Game marked as fulfilled'], 200);
    }
    
    /**
     * Admin: Bulk actions
     */
    public function bulk_action($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $action = isset($params['action']) ? $params['action'] : '';
        $game_ids = isset($params['game_ids']) ? $params['game_ids'] : [];
        
        if (empty($game_ids) || !is_array($game_ids)) {
            return new WP_Error('invalid_data', 'Game IDs required', ['status' => 400]);
        }
        
        $table = $wpdb->prefix . 'mhjoy_request_games';
        $ids_placeholder = implode(',', array_fill(0, count($game_ids), '%d'));
        
        if ($action === 'fulfill') {
            $wpdb->query($wpdb->prepare("
                UPDATE $table SET status = 'completed', completed_at = %s 
                WHERE id IN ($ids_placeholder)
            ", array_merge([current_time('mysql')], $game_ids)));
        } elseif ($action === 'delete') {
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($ids_placeholder)", $game_ids));
        }
        
        return new WP_REST_Response(['success' => true, 'message' => 'Bulk action completed'], 200);
    }
    
    /**
     * Admin: Get analytics dashboard
     */
    public function get_analytics_dashboard($request) {
        $stats = $this->analytics->get_dashboard_stats();
        return new WP_REST_Response($stats, 200);
    }
    
    /**
     * Admin: Generate license codes
     */
    public function generate_licenses($request) {
        $params = $request->get_json_params();
        $count = isset($params['count']) ? (int) $params['count'] : 1;
        
        if ($count < 1 || $count > 100) {
            return new WP_Error('invalid_count', 'Count must be between 1 and 100', ['status' => 400]);
        }
        
        $codes = $this->license_manager->generate_codes($count);
        
        return new WP_REST_Response(['success' => true, 'codes' => $codes], 200);
    }
    
    /**
     * Check if user has admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
}
