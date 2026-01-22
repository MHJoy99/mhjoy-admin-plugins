<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function () {

    // ==================== 1. LEGACY ENDPOINTS (Preserved for React) ====================

    // Redeem Code
    register_rest_route('custom/v1', '/wallet/redeem', array(
        'methods' => 'POST',
        'callback' => 'mhjoy_wallet_redeem_api',
        'permission_callback' => '__return_true'
    ));

    // Get Balance (The critical one)
    register_rest_route('custom/v1', '/wallet/balance', array(
        'methods' => 'GET',
        'callback' => 'mhjoy_wallet_get_balance',
        'permission_callback' => '__return_true'
    ));

    // Daily Reward
    register_rest_route('custom/v1', '/wallet/daily-reward', array(
        'methods' => 'POST',
        'callback' => 'mhjoy_wallet_daily_reward',
        'permission_callback' => '__return_true'
    ));

    // Transaction History
    register_rest_route('custom/v1', '/wallet/history', array(
        'methods' => 'GET',
        'callback' => 'mhjoy_wallet_get_history',
        'permission_callback' => '__return_true'
    ));

    // Leaderboards
    register_rest_route('custom/v1', '/wallet/leaderboard', array(
        'methods' => 'GET',
        'callback' => 'mhjoy_wallet_leaderboard',
        'permission_callback' => '__return_true'
    ));
    register_rest_route('custom/v1', '/wallet/leaderboard-enhanced', array(
        'methods' => 'GET',
        'callback' => 'mhjoy_wallet_leaderboard_enhanced',
        'permission_callback' => '__return_true'
    ));

    // ==================== 2. NEW V4 ENDPOINTS (Additions) ====================

    // Spin the Wheel
    register_rest_route('custom/v1', '/wallet/spin', array(
        'methods' => 'POST',
        'callback' => 'mhjoy_wallet_spin_api',
        'permission_callback' => '__return_true'
    ));

    // Full Dashboard (Balance + Tier + Spin Status)
    register_rest_route('custom/v1', '/wallet/dashboard', array(
        'methods' => 'GET',
        'callback' => 'mhjoy_wallet_dashboard_api',
        'permission_callback' => '__return_true'
    ));
    // Register this inside the rest_api_init hook
    register_rest_route('custom/v1', '/wallet/apply-referral', [
        'methods' => 'POST',
        'callback' => 'mhjoy_api_apply_referral',
        'permission_callback' => '__return_true'
    ]);
});

// ==================== CALLBACK FUNCTIONS ====================

/**
 * 1. REDEEM API (Full Logic)
 */

// Callback function
function mhjoy_api_apply_referral($req)
{
    $p = $req->get_json_params();
    $email = sanitize_email($p['user_email'] ?? '');
    $code = sanitize_text_field($p['code'] ?? ''); // 🎯 FIX: Removed strtoupper

    if (empty($email) || empty($code)) {
        return new WP_Error('missing_data', 'Email and code are required.', ['status' => 400]);
    }

    $result = mhjoy_apply_referral_code($email, $code);

    if (is_wp_error($result)) {
        // 🎯 FIX: Return the specific error with the status code to prevent 500 error
        return $result;
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'MHJoyGamersHub Partner linked! You are now supporting your friend.'
    ], 200);
}
function mhjoy_wallet_redeem_api($request)
{
    global $wpdb;
    $table_codes = $wpdb->prefix . 'mhjoy_gift_codes';
    $table_balance = $wpdb->prefix . 'mhjoy_wallet_balance';
    $table_redemptions = $wpdb->prefix . 'mhjoy_gift_code_redemptions';

    $data = $request->get_json_params();
    $code = isset($data['code']) ? strtoupper(sanitize_text_field($data['code'])) : '';
    $email = isset($data['user_email']) ? sanitize_email($data['user_email']) : '';
    $device_fp = isset($data['device_fingerprint']) ? sanitize_text_field($data['device_fingerprint']) : null;

    if (empty($code) || empty($email)) {
        return new WP_Error('missing_params', 'Code and email required', array('status' => 400));
    }

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $code_prefix = substr($code, 0, 6);

    // Fraud Check: Prefix Limit
    $email_campaign_used = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_redemptions WHERE user_email = %s AND code_prefix = %s",
        $email,
        $code_prefix
    ));

    if ($email_campaign_used > 0) {
        return new WP_Error('campaign_limit', 'Already redeemed this campaign!', array('status' => 403));
    }

    $wpdb->query('START TRANSACTION');
    try {
        // Lock Code
        $gift = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_codes WHERE code = %s FOR UPDATE", $code));

        if (!$gift || $gift->status !== 'active') {
            $wpdb->query('ROLLBACK');
            return new WP_Error('invalid_code', 'Invalid or Redeemed Code', array('status' => 404));
        }

        // --- NEW: HANDLE TOKEN VS CASH ---
        $is_token = isset($gift->type) && $gift->type === 'token';
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_balance WHERE user_email = %s", $email));
        
        if ($is_token) {
            // Token Logic
            $new_tokens = ($existing ? ($existing->vault_token_balance ?? 0) : 0) + intval($gift->amount);
            if ($existing) {
                $wpdb->update($table_balance, ['vault_token_balance' => $new_tokens], ['user_email' => $email]);
            } else {
                $wpdb->insert($table_balance, ['user_email' => $email, 'balance' => 0, 'vault_token_balance' => $new_tokens]);
            }
            // Log as 'spin_reward' (or 'token_gift') so it shows in Vault Log. 
            // Using 'admin_adjustment' type source logic for vault log compatibility:
            // Vault Log Query: source IN ('vault_redemption', 'spin_reward', 'admin_adjustment', 'token_gift')
            mhjoy_log_transaction($email, 'credit', 0, 'token_gift', "Redeemed Code for " . intval($gift->amount) . " Tokens", $existing ? $existing->balance : 0);
            
            $msg = intval($gift->amount) . ' 💎 Tokens added!';
            $new_bal_display = $new_tokens;
        } else {
            // Cash Logic (Standard)
            $new_balance = $existing ? round(floatval($existing->balance) + floatval($gift->amount), 2) : round(floatval($gift->amount), 2);
            if ($existing) {
                $wpdb->update($table_balance, ['balance' => $new_balance], ['user_email' => $email]);
            } else {
                $wpdb->insert($table_balance, ['user_email' => $email, 'balance' => $new_balance]);
            }
            
            mhjoy_log_transaction($email, 'credit', $gift->amount, 'redeem', 'Code: ' . $code, $new_balance);
            
            $msg = '৳' . number_format($gift->amount, 2) . ' added!';
            $new_bal_display = floatval($new_balance);
        }

        // Log Redemption
        $wpdb->insert($table_redemptions, [
            'code' => $code,
            'code_prefix' => $code_prefix,
            'user_email' => $email,
            'device_fingerprint' => $device_fp,
            'ip_address' => $ip_address,
            'amount' => $gift->amount
        ]);

        // Mark Used
        $wpdb->update(
            $table_codes,
            ['status' => 'redeemed', 'redeemed_by' => $email, 'redeemed_at' => current_time('mysql')],
            ['id' => $gift->id]
        );

        // Trigger Fraud Analysis
        if (function_exists('mhjoy_analyze_fraud_risk')) {
            mhjoy_analyze_fraud_risk($email, $device_fp, $ip_address);
        }

        $wpdb->query('COMMIT');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $msg,
            'new_balance' => $new_bal_display,
            'is_token' => $is_token
        ), 200);
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('redemption_failed', 'Redemption error', array('status' => 500));
    }
}

/**
 * 2. GET BALANCE API (Restored)
 */
function mhjoy_wallet_get_balance($request)
{
    global $wpdb;
    $table_balance = $wpdb->prefix . 'mhjoy_wallet_balance';
    $email = sanitize_email($request->get_param('user_email'));

    $balance = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $table_balance WHERE user_email = %s", $email));

    return new WP_REST_Response(array(
        'balance' => $balance ? floatval($balance) : 0.00
    ), 200);
}

/**
 * 3. DAILY REWARD API (Freemium Logic)
 */
function mhjoy_wallet_daily_reward($request)
{
    global $wpdb;
    $email = sanitize_email($request->get_json_params()['user_email']);
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // 1. GATEKEEPER: Is this a paid customer?
    $customer_orders = wc_get_orders([
        'billing_email' => $email,
        'limit' => 1,
        'status' => ['completed', 'processing'],
        'return' => 'ids'
    ]);
    $is_vip = !empty($customer_orders);

    // 🛑 TRIAL LIMIT CHECK FOR FREE USERS
    if (!$is_vip && function_exists('mhjoy_get_total_free_earned')) {
        if (mhjoy_get_total_free_earned($email) >= 50) {
            return new WP_Error('locked', '৳50 Trial Limit reached! Buy something to unlock more.', ['status' => 403]);
        }
    }

    $wpdb->query('START TRANSACTION');
    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_bal WHERE user_email = %s FOR UPDATE", $email));

    if ($user && $user->last_daily_claim && strpos($user->last_daily_claim, date('Y-m-d')) !== false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('claimed', 'Already claimed today', ['status' => 400]);
    }

    $streak = $user ? $user->streak + 1 : 1;

    // Reward Logic (Freemium) - SWITCHED TO VAULT TOKENS
    if ($is_vip) {
        $reward = ($streak % 7 == 0) ? 50 : rand(5, 15);
        $msg = ($streak % 7 == 0) ? "🎉 WEEKLY JACKPOT! $reward 💎" : "Daily reward: $reward 💎";
    } else {
        $reward = ($streak % 7 == 0) ? 10 : 2;
        $msg = "Free Tier: $reward 💎. Buy something to unlock 5x rewards!";
    }

    if ($user) {
        // Update Vault Tokens
        $new_tokens = ($user->vault_token_balance ?? 0) + $reward;
        $wpdb->update($t_bal, ['vault_token_balance' => $new_tokens, 'streak' => $streak, 'last_daily_claim' => current_time('mysql')], ['user_email' => $email]);
    } else {
        $wpdb->insert($t_bal, ['user_email' => $email, 'balance' => 0, 'vault_token_balance' => $reward, 'streak' => 1, 'last_daily_claim' => current_time('mysql')]);
    }

    // Log as 'daily_reward' for Vault Log
    // Amount is 0 (cash), but we put tokens in description for visual log
    mhjoy_log_transaction($email, 'credit', 0, 'daily_reward', "Daily Reward: $reward Vault Tokens", ($user ? $user->balance : 0));
    $wpdb->query('COMMIT');

    return [
        'success' => true,
        'amount' => $reward, // Frontend can interpret this as tokens if we update frontend
        'streak' => $streak,
        'message' => $msg, // Message now has 💎
        'is_vip' => $is_vip
    ];
}
/**
 * 4. HISTORY API
 */
/**
 * 4. HISTORY API (Full Bank Statement Version)
 * Now pulls from the transaction ledger, not just gift codes.
 */
function mhjoy_wallet_get_history($request)
{
    global $wpdb;
    $table_transactions = $wpdb->prefix . 'mhjoy_wallet_transactions';
    $email = sanitize_email($request->get_param('user_email'));

    if (empty($email)) {
        return new WP_Error('no_email', 'Email required', ['status' => 400]);
    }

    // Pull ALL fund movements for this user
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            created_at as date,
            type,
            amount,
            source,
            reference as description,
            balance_after as balance
         FROM $table_transactions 
         WHERE user_email = %s 
         ORDER BY created_at DESC 
         LIMIT 30",
        $email
    ));

    // Ensure numeric values are formatted for the frontend .toFixed(2)
    $formatted_history = array_map(function ($item) {
        return [
            'date' => $item->date,
            'type' => $item->type,
            'amount' => (float) $item->amount,
            'source' => strtoupper($item->source),
            'description' => $item->description,
            'balance' => (float) $item->balance
        ];
    }, $history ?: []);

    return new WP_REST_Response($formatted_history, 200);
}

/**
 * 5. LEADERBOARD API
 */
function mhjoy_wallet_leaderboard($request)
{
    global $wpdb;
    $table_balance = $wpdb->prefix . 'mhjoy_wallet_balance';
    $leaders = $wpdb->get_results("SELECT user_email, balance FROM $table_balance ORDER BY balance DESC LIMIT 50");

    $result = array_map(function ($u) {
        $parts = explode('@', $u->user_email);
        $masked = substr($parts[0], 0, 3) . '***';
        return ['user_email' => $masked, 'balance' => floatval($u->balance)];
    }, $leaders);
    return new WP_REST_Response($result, 200);
}

function mhjoy_wallet_leaderboard_enhanced($request)
{
    global $wpdb;

    // 🚀 CTO FIX: Use the cache! Only delete manually if you need a refresh.
    $cached_leaderboard = get_transient('mhjoy_hall_of_fame_cache');
    if ($cached_leaderboard !== false) {
        return new WP_REST_Response($cached_leaderboard, 200);
    }

    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';

    $leaders = $wpdb->get_results("
        SELECT 
            s.user_email, 
            COALESCE(b.balance, 0) as balance,
            COALESCE(s.total_spent, 0) as total_spent,
            COALESCE(s.total_orders, 0) as total_orders,
            s.last_order_date
        FROM $t_stats s 
        LEFT JOIN $t_bal b ON s.user_email = b.user_email 
        WHERE s.total_spent > 0
        ORDER BY s.total_spent DESC 
        LIMIT 50
    ");

    $current_year = date('Y');
    $current_month = date('m');

    $result = array_map(function ($u) use ($current_year, $current_month) {
        $parts = explode('@', $u->user_email);
        $masked = substr($parts[0], 0, 3) . '***';

        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['completed', 'processing'],
            'billing_email' => $u->user_email,
            'date_created' => "$current_year-$current_month-01...$current_year-$current_month-31",
            'return' => 'ids'
        ]);

        $this_month_spent = 0;
        foreach ($orders as $oid) {
            $o = wc_get_order($oid);
            if ($o)
                $this_month_spent += (float) $o->get_total();
        }

        return [
            'user_email' => (string) $masked,
            'total_orders' => (int) $u->total_orders,
            'total_spent' => (float) $u->total_spent,
            'this_month_orders' => (int) count($orders),
            'this_month_spent' => (float) $this_month_spent,
            'last_order_date' => !empty($u->last_order_date) ? (string) $u->last_order_date : ''
        ];
    }, $leaders ?: []);

    // Cache for 1 hour to protect CPU
    set_transient('mhjoy_hall_of_fame_cache', $result, HOUR_IN_SECONDS);

    return new WP_REST_Response($result, 200);
}

// ==================== NEW V4 CALLBACKS ====================

/**
 * 6. SPIN API
 */
function mhjoy_wallet_spin_api($request)
{
    $p = $request->get_json_params();
    $email = sanitize_email($p['user_email']);
    $is_premium = !empty($p['is_premium']);

    // Call Core Logic
    if (function_exists('mhjoy_process_spin_logic')) {
        $result = mhjoy_process_spin_logic($email, $is_premium);
        if (is_wp_error($result))
            return $result;
        return new WP_REST_Response($result, 200);
    }
    return new WP_Error('system_error', 'Core logic missing', ['status' => 500]);
}

/**
 * 7. DASHBOARD API (Combined)
 * This is the function causing the Warning - NOW PATCHED
 */
function mhjoy_wallet_dashboard_api($request)
{
    global $wpdb;
    $email = sanitize_email($request->get_param('user_email'));
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';

    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_bal WHERE user_email = %s", $email));

    // 1. CALCULATE PARTNER STATS
    // Count total friends invited
    $friends_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t_bal WHERE referred_by = %s",
        $email
    ));

    // Count friends who crossed the ৳300 milestone
    $milestones_cleared = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t_stats 
         WHERE user_email IN (SELECT user_email FROM $t_bal WHERE referred_by = %s)
         AND total_spent >= 300",
        $email
    ));

    // 2. TIER & PROGRESS LOGIC
    $tier_slug = 'bronze';
    $next_tier = 'Elite Partner';
    $friends_needed = 11 - $friends_count;
    $commission_rate = 0.5;

    if ($friends_count >= 31) {
        $tier_slug = 'platinum';
        $next_tier = 'Max Level';
        $friends_needed = 0;
        $commission_rate = 1.5;
    } elseif ($friends_count >= 11) {
        $tier_slug = 'gold';
        $next_tier = 'Legendary Partner';
        $friends_needed = 31 - $friends_count;
        $commission_rate = 1.0;
    }

    $tier_map = [
        'bronze' => 'MHJoyGamersHub Standard Partner',
        'silver' => 'MHJoyGamersHub Elite Partner', // Logic uses gold for 11+, but map is here for safety
        'gold' => 'MHJoyGamersHub Elite Partner',
        'platinum' => 'MHJoyGamersHub Legendary Partner'
    ];

    // 3. TRANSACTION TOTALS (For the "Passive Income" counter)
    $total_earned = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM {$wpdb->prefix}mhjoy_wallet_transactions 
         WHERE user_email = %s AND source = 'referral' AND type = 'credit'",
        $email
    )) ?: 0.00;

    return new WP_REST_Response([
        'balance' => (float) round($wallet->balance ?? 0, 2),
        'streak' => (int) ($wallet->streak ?? 0),
        'referral_code' => $wallet->referral_code ?? mhjoy_generate_referral_code($email),
        'spin_available' => !isset($wallet->spin_claimed_today) || (int) $wallet->spin_claimed_today == 0,
        'premium_spins_balance' => (int) ($wallet->premium_spins_balance ?? 0),

        // NEW PARTNER INTELLIGENCE DATA
        'partner_stats' => [
            'current_tier' => $tier_map[$tier_slug],
            'tier_slug' => $tier_slug,
            'commission_rate' => $commission_rate,
            'total_earned' => round($total_earned, 2),
            'friends_invited' => $friends_count,
            'milestones_done' => $milestones_cleared,
            'next_tier_name' => $next_tier,
            'friends_to_next' => max(0, $friends_needed),
            'progress_percent' => ($tier_slug === 'platinum') ? 100 : round(($friends_count / ($friends_count + $friends_needed)) * 100)
        ],

        'tasks' => [
            ['id' => 'login', 'name' => 'Daily Login', 'reward' => 1, 'completed' => ($wallet && strpos($wallet->last_daily_claim ?? '', date('Y-m-d')) !== false)],
            ['id' => 'spin', 'name' => 'Daily Spin', 'reward' => 'varies', 'completed' => (isset($wallet->spin_claimed_today) && $wallet->spin_claimed_today > 0)]
        ]
    ], 200);
}
?>