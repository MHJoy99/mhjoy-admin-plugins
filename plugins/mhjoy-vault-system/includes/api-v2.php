<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register V2 Routes
add_action('rest_api_init', function () {
    // 1. New Spin Endpoint (Awards Tokens 💎)
    register_rest_route('mhjoy/v1', '/spin', [
        'methods' => 'POST',
        'callback' => 'mhjoy_v2_spin_api',
        'permission_callback' => '__return_true' // Public but internally secured
    ]);

    // 2. New Dashboard Endpoint (Includes Tokens)
    register_rest_route('mhjoy/v1', '/wallet/dashboard', [
        'methods' => 'GET',
        'callback' => 'mhjoy_v2_wallet_dashboard',
        'permission_callback' => '__return_true'
    ]);
});

/**
 * 🎰 V2 Spin Logic: Awards Vault Tokens 💎
 */
function mhjoy_v2_spin_api($request) {
    global $wpdb;
    $params = $request->get_json_params();
    $email = sanitize_email($params['user_email'] ?? '');
    $is_premium = isset($params['is_premium']) && $params['is_premium'];

    if (!is_email($email)) return new WP_Error('invalid_email', 'Invalid Email', ['status' => 400]);

    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_spins = $wpdb->prefix . 'mhjoy_spin_history';

    // Transaction & Lock
    $wpdb->query('START TRANSACTION');
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_bal WHERE user_email = %s FOR UPDATE", $email));

    if (!$wallet) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('no_wallet', 'Wallet not found', ['status' => 404]);
    }

    // Cost & Limits
    $cost = $is_premium ? 10 : 0; // High Stakes Cost: ৳10 matching Frontend
    $today = date('Y-m-d');
    
    // Check Limits
    if ($is_premium) {
        if (($wallet->premium_spins_balance ?? 0) <= 0 && $wallet->balance < $cost) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient_funds', 'Not enough balance or premium spins', ['status' => 402]);
        }
    } else {
        if ($wallet->last_spin_date && strpos($wallet->last_spin_date, $today) !== false) {
             // Check if user has free spin available from topup
             if (($wallet->spin_claimed_today ?? 0) >= 1) { // Basic daily limit
                 $wpdb->query('ROLLBACK');
                 return new WP_Error('limit_reached', 'Daily spin already claimed', ['status' => 403]);
             }
        }
    }

    // 🎲 RNG LOGIC (Tokens 💎)
    $rand = rand(1, 1000);
    $reward = 0;

    if ($is_premium) {
        if ($rand <= 350) $reward = 20;      // 35% - 20 Tokens
        elseif ($rand <= 700) $reward = 50;  // 35% - 50 Tokens
        elseif ($rand <= 900) $reward = 100; // 20% - 100 Tokens
        elseif ($rand <= 990) $reward = 250; // 9% - 250 Tokens
        else $reward = 1000;                 // 1% - 1000 Tokens!
    } else {
        if ($rand <= 500) $reward = 1;       // 50% - 1 Token
        elseif ($rand <= 850) $reward = 5;   // 35% - 5 Tokens
        elseif ($rand <= 980) $reward = 10;  // 13% - 10 Tokens
        else $reward = 50;                   // 2% - 50 Tokens
    }

    // Apply Changes
    $new_balance = $wallet->balance;
    $premium_spins = $wallet->premium_spins_balance ?? 0;

    if ($is_premium) {
        if ($premium_spins > 0) {
            $premium_spins--;
        } else {
            $new_balance -= $cost;
            // Log Debit
            mhjoy_log_transaction($email, 'debit', $cost, 'spin_cost', 'Premium Spin Cost', $new_balance);
        }
    }

    $current_tokens = (int) ($wallet->vault_token_balance ?? 0);
    $new_tokens = $current_tokens + $reward;

    // Update DB
    $wpdb->update($t_bal, [
        'balance' => $new_balance,
        'vault_token_balance' => $new_tokens,
        'premium_spins_balance' => $premium_spins,
        'spin_claimed_today' => 1,
        'last_spin_date' => current_time('mysql')
    ], ['user_email' => $email]);

    // Record Spin History
    $wpdb->insert($t_spins, [
        'user_email' => $email,
        'reward_amount' => $reward, // Stored as number, context implies tokens now
        'is_premium' => $is_premium ? 1 : 0,
        'created_at' => current_time('mysql')
    ]);

    // Log Reward (0 Cash, but we put reward in generated msg)
    mhjoy_log_transaction($email, 'credit', 0, 'spin_reward', "Won $reward Vault Tokens 💎", $new_balance);

    $wpdb->query('COMMIT');

    return [
        'success' => true,
        'reward' => $reward,
        'message' => "You won $reward Vault Tokens!",
        'balance' => $new_balance,
        'vault_tokens' => $new_tokens
    ];
}

/**
 * 📊 V2 Dashboard: Combined View
 */
function mhjoy_v2_wallet_dashboard($request) {
    global $wpdb;
    $email = sanitize_email($request->get_param('user_email'));
    if (!$email) return new WP_Error('no_email', 'Email required', ['status' => 400]);

    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mhjoy_wallet_balance WHERE user_email = %s", $email));

    if (!$wallet) return [
        'balance' => 0.00, 
        'vault_token_balance' => 0, 
        'spin_available' => false
    ];

    // Determine Tier Logic (Simplified replication from v1)
    $tiers = ['unranked' => 0, 'bronze' => 500, 'silver' => 5000, 'gold' => 20000, 'platinum' => 100000];
    $spent = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_spent) FROM {$wpdb->prefix}mhjoy_user_statistics WHERE user_email = %s", $email)) ?: 0;
    
    // Check tier
    $current_tier = 'unranked';
    foreach (array_reverse($tiers) as $name => $threshold) {
        if ($spent >= $threshold) { $current_tier = $name; break; }
    }

    return [
        'balance' => (float) round($wallet->balance, 2),
        'vault_token_balance' => (int) ($wallet->vault_token_balance ?? 0), // 💎 KEY FIELD
        'streak' => (int) $wallet->streak,
        'tier' => $current_tier,
        'spin_available' => !isset($wallet->last_spin_date) || strpos($wallet->last_spin_date, date('Y-m-d')) === false,
        'premium_spins_balance' => (int) ($wallet->premium_spins_balance ?? 0),
        'referral_code' => $wallet->referral_code
    ];
}
