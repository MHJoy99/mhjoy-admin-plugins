<?php
/**
 * Plugin Name: MHJoy Game Request System
 * Plugin URI: https://mhjoygamershub.com
 * Description: Secure, API-backed game request and voting system with Rawg.io integration
 * Version: 1.0.0
 * Author: MHJoyGamersHub
 * Author URI: https://mhjoygamershub.com
 * License: GPL v2 or later
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin constants
define('MHJOY_GR_VERSION', '1.0.0');
define('MHJOY_GR_PATH', plugin_dir_path(__FILE__));
define('MHJOY_GR_URL', plugin_dir_url(__FILE__));

/**
 * Auto-loader for plugin classes
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'MHJoy_GR_') === 0) {
        $class_clean = str_replace('MHJoy_GR_', '', $class);
        $class_name = str_replace('_', '-', strtolower($class_clean));
        $file = MHJOY_GR_PATH . 'includes/class-' . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Initialize plugin
 */
add_action('plugins_loaded', function() {
    // Initialize components
    new MHJoy_GR_Database();
    new MHJoy_GR_API();
    new MHJoy_GR_Admin();
    new MHJoy_GR_Analytics();
});

/**
 * Activation hook - Create database tables
 */
register_activation_hook(__FILE__, function() {
    require_once MHJOY_GR_PATH . 'includes/class-database.php';
    MHJoy_GR_Database::create_tables();
    
    // Create default options
    add_option('mhjoy_gr_rawg_api_key', '');
    add_option('mhjoy_gr_turnstile_site_key', '');
    add_option('mhjoy_gr_turnstile_secret_key', '');
    add_option('mhjoy_gr_fingerprint_api_key', '');
    add_option('mhjoy_gr_rate_limit', 10); // Max votes per hour for guests
    add_option('mhjoy_gr_cache_duration', 3600); // 1 hour cache
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log activation
    error_log('✅ MHJoy Game Request System v' . MHJOY_GR_VERSION . ' activated');
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log deactivation
    error_log('⚠️ MHJoy Game Request System deactivated');
});
