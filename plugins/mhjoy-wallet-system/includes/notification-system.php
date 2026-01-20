<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MHJoy User Notification System
 * Handles internal alerts for the Headless Frontend.
 */

// 1. DATABASE SETUP
add_action('admin_init', 'mhjoy_create_notification_table');
function mhjoy_create_notification_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'mhjoy_notifications';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// 2. REGISTER API ROUTES
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/notifications', [
        'methods' => 'GET',
        'callback' => 'mhjoy_api_get_notifications',
        'permission_callback' => '__return_true' // 🎯 FIX: Allow Headless access
    ]);

    register_rest_route('custom/v1', '/notifications/mark-read', [
        'methods' => 'POST',
        'callback' => 'mhjoy_api_mark_notification_read',
        'permission_callback' => '__return_true' // 🎯 FIX: Allow Headless access
    ]);
});

// 3. CALLBACK: GET NOTIFICATIONS
function mhjoy_api_get_notifications($request)
{
    global $wpdb;
    $table = $wpdb->prefix . 'mhjoy_notifications';

    // 🎯 FIX: Get User ID from query param (matching your frontend call ?user_id=17)
    $user_id = absint($request->get_param('user_id'));

    if (!$user_id) {
        return new WP_Error('no_user', 'User ID required', ['status' => 400]);
    }

    // Get IDs of broadcasts this user has already read
    $read_broadcasts = get_user_meta($user_id, '_mhjoy_read_broadcasts', true) ?: [];

    // Fetch personal messages for this ID + Global Broadcasts (user_id = 0)
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, type, title, message, created_at as date, user_id, is_read 
         FROM $table 
         WHERE user_id = %d OR user_id = 0 
         ORDER BY created_at DESC LIMIT 50",
        $user_id
    ));

    $formatted = array_map(function ($n) use ($read_broadcasts) {
        return [
            'id' => (int) $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'message' => $n->message,
            'date' => $n->date,
            'is_read' => ($n->user_id == 0) ? in_array($n->id, $read_broadcasts) : ($n->is_read == 1)
        ];
    }, $results);

    return new WP_REST_Response($formatted, 200);
}

// 4. CALLBACK: MARK AS READ (Secure with User ID check)
function mhjoy_api_mark_notification_read($request)
{
    global $wpdb;
    $params = $request->get_json_params();
    $notif_id = absint($params['notification_id'] ?? 0);
    $user_id = absint($params['user_id'] ?? 0); // 🎯 FIX: Get user_id from body
    $table = $wpdb->prefix . 'mhjoy_notifications';

    if (!$notif_id || !$user_id) {
        return new WP_Error('missing_params', 'ID and User ID required', ['status' => 400]);
    }

    $notif = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM $table WHERE id = %d", $notif_id));
    if (!$notif)
        return new WP_Error('not_found', 'Not found', ['status' => 404]);

    if ($notif->user_id == 0) {
        $read_list = get_user_meta($user_id, '_mhjoy_read_broadcasts', true) ?: [];
        if (!in_array($notif_id, $read_list)) {
            $read_list[] = $notif_id;
            update_user_meta($user_id, '_mhjoy_read_broadcasts', $read_list);
        }
        $success = true;
    } else {
        // PERSONAL MESSAGE SECURITY: Ensure user_id matches the notification owner
        $success = $wpdb->update($table, ['is_read' => 1], ['id' => $notif_id, 'user_id' => $user_id]);
    }

    return new WP_REST_Response(['success' => !!$success], 200);
}


/**
 * Global Helper: Use this anywhere in backend to send a notification to a user
 */
function mhjoy_send_notification($user_id, $title, $message, $type = 'info')
{
    global $wpdb;
    return $wpdb->insert($wpdb->prefix . 'mhjoy_notifications', [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'created_at' => current_time('mysql')
    ]);
}