<?php
/**
 * Plugin Name: MHJoy Gamepass Manager
 * Description: REST API endpoints for Ultimate Gamepass configuration
 * Version: 1.0.0
 * Author: MHJoy Gamers Hub
 */

if (!defined('ABSPATH')) {
    exit;
}

class MHJoy_Gamepass_API {
    
    const OPTION_KEY = 'mhjoy_gamepass_config';
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        // GET endpoint - public (anyone can view tier configuration)
        register_rest_route('mhjoy/v1', '/gamepass/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => '__return_true'
        ]);
        
        // POST endpoint - admin only (update configuration)
        register_rest_route('mhjoy/v1', '/gamepass/config', [
            'methods' => 'POST',
            'callback' => [$this, 'update_config'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'priorities' => [
                    'required' => true,
                    'type' => 'object'
                ],
                'tiers' => [
                    'required' => true,
                    'type' => 'object'
                ]
            ]
        ]);
    }
    
    /**
     * GET /wp-json/mhjoy/v1/gamepass/config
     * Returns the current gamepass configuration
     */
    public function get_config($request) {
        $config = get_option(self::OPTION_KEY, [
            'priorities' => new stdClass(),
            'tiers' => new stdClass()
        ]);
        
        // Ensure empty objects are returned as objects, not arrays
        if (empty($config['priorities'])) {
            $config['priorities'] = new stdClass();
        }
        if (empty($config['tiers'])) {
            $config['tiers'] = new stdClass();
        }
        
        return rest_ensure_response([
            'success' => true,
            'data' => $config
        ]);
    }
    
    /**
     * POST /wp-json/mhjoy/v1/gamepass/config
     * Updates the gamepass configuration
     */
    public function update_config($request) {
        $priorities = $request->get_param('priorities');
        $tiers = $request->get_param('tiers');
        
        $config = [
            'priorities' => $priorities,
            'tiers' => $tiers,
            'updated_at' => current_time('mysql'),
            'updated_by' => get_current_user_id()
        ];
        
        $result = update_option(self::OPTION_KEY, $config);
        
        return rest_ensure_response([
            'success' => $result,
            'message' => $result ? 'Configuration updated successfully' : 'Failed to update configuration',
            'data' => $config
        ]);
    }
    
    /**
     * Check if current user is an administrator
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
}

// Initialize the API
new MHJoy_Gamepass_API();
