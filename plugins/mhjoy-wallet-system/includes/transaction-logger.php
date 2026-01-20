<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MHJoy Transaction Ledger
 * Records every single movement of funds (Credit/Debit).
 */

// 1. AUTO-CREATE TABLE ON LOAD (Self-Healing)
add_action('admin_init', 'mhjoy_create_transaction_table');

function mhjoy_create_transaction_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'mhjoy_wallet_transactions';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_email VARCHAR(255) NOT NULL,
            type ENUM('credit', 'debit', 'gift') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            source VARCHAR(50) NOT NULL, 
            reference VARCHAR(255) NULL, 
            balance_after DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_email (user_email),
            KEY type (type)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } else {
        // Update existing table to add 'gift' to ENUM if it doesn't exist
        $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN type ENUM('credit', 'debit', 'gift') NOT NULL");
    }
}

// 2. THE LOGGING FUNCTION (Global Helper)
// Call this whenever money moves!
function mhjoy_log_transaction($email, $type, $amount, $source, $ref = '', $new_balance = 0)
{
    global $wpdb;
    $table = $wpdb->prefix . 'mhjoy_wallet_transactions';

    $wpdb->insert($table, [
        'user_email' => $email,
        'type' => $type,      // 'credit' (add) or 'debit' (spend)
        'amount' => $amount,
        'source' => $source,    // 'spin', 'daily', 'redeem', 'order', 'admin'
        'reference' => $ref,       // e.g. 'Order #1234', 'Code JOY50'
        'balance_after' => $new_balance,
        'created_at' => current_time('mysql')
    ]);
}
?>