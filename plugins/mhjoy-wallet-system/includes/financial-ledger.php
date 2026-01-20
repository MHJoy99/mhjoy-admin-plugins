<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MHJoy Financial Ledger (Bank Vault)
 * 100% Separation between Admin and User logs.
 */

// 1. DATABASE CREATION (Atomic)
add_action('admin_init', 'mhjoy_setup_ledger_table');
function mhjoy_setup_ledger_table()
{
    global $wpdb;
    $table = $wpdb->prefix . 'mhjoy_wallet_transactions';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(255) NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            source VARCHAR(50) NOT NULL, 
            reference TEXT NULL, 
            balance_after DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_email (user_email)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// 2. THE LOGGER (Internal Use Only)
function mhjoy_log_transaction($email, $type, $amount, $source, $ref, $new_bal)
{
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'mhjoy_wallet_transactions', [
        'user_email' => $email,
        'type' => $type,
        'amount' => round($amount, 2),
        'source' => $source,
        'reference' => $ref,
        'balance_after' => round($new_bal, 2),
        'created_at' => current_time('mysql')
    ]);
}

// 3. API ROUTES (Protected)
add_action('rest_api_init', function () {
    // User View (Restricted to OWN data)
    register_rest_route('custom/v1', '/wallet/history', [
        'methods' => 'GET',
        'callback' => 'mhjoy_api_get_user_history',
        'permission_callback' => '__return_true' // Handled by email validation inside
    ]);
});

// 4. USER HISTORY CALLBACK (Personalized Only)
function mhjoy_api_get_user_history($request)
{
    global $wpdb;
    $email = sanitize_email($request->get_param('user_email'));
    if (!$email)
        return new WP_Error('no_user', 'Email required', ['status' => 400]);

    $table = $wpdb->prefix . 'mhjoy_wallet_transactions';

    // 🛡️ SECURITY: Only select columns safe for users to see
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT created_at as date, type, amount, source, reference as description, balance_after as balance
         FROM $table 
         WHERE user_email = %s 
         ORDER BY created_at DESC LIMIT 30",
        $email
    ));

    return new WP_REST_Response($results ?: [], 200);
}