<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MHJoy Wallet Core Logic
 * Handles calculations, RNG, tiers, and business rules.
 */

// ==================== 1. SPIN-TO-WIN LOGIC ====================

function mhjoy_process_spin_logic($user_email, $is_premium = false)
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_spins = $wpdb->prefix . 'mhjoy_spin_history';

    $wpdb->query('START TRANSACTION');

    // 1. Get Wallet with Row Lock (FOR UPDATE is critical)
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_bal WHERE user_email = %s FOR UPDATE", $user_email));

    if (!$wallet) {
        $wpdb->insert($t_bal, ['user_email' => $user_email, 'balance' => 0]);
        $wallet = (object) ['balance' => 0, 'spin_claimed_today' => 0, 'total_spins' => 0, 'premium_spins_balance' => 0];
    }

    // 🛡️ REORDERED LOGIC: Determine Cost FIRST (Fixes "Free Spin but blocked by <10tk" bug)
    $cost = $is_premium ? 10 : 0;
    $used_free_spin = false;

    if ($is_premium && isset($wallet->premium_spins_balance) && $wallet->premium_spins_balance > 0) {
        $cost = 0;
        $used_free_spin = true;
    }

    if (!$is_premium && $wallet->spin_claimed_today >= 1) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('limit', 'Daily spin used', ['status' => 403]);
    }

    // 🛡️ TRIAL LIMIT CHECK
    if (!$is_premium) {
        $orders = wc_get_orders(['billing_email' => $user_email, 'limit' => 1, 'status' => ['completed', 'processing'], 'return' => 'ids']);
        if (empty($orders)) { // User has 0 orders
            if (mhjoy_get_total_free_earned($user_email) >= 50) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('locked', '৳50 Trial Limit reached! Make your first purchase to unlock more rewards.', ['status' => 403]);
            }
        }
    }

    if ($is_premium) {
        // Now check if they can afford `cost` (which might be 0)
        if ($wallet->balance < $cost) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('funds', 'Need ৳10', ['status' => 402]);
        }
        
        // 🛡️ ADMIN BYPASS: Allow unlimited spins for test/admin accounts
        $test_emails = ['mhjoypersonal@gmail.com', 'admin@mhjoygamershub.com'];
        $premium_today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_spins WHERE user_email = %s AND spin_date = CURDATE() AND is_premium = 1", $user_email));
        
        // If using free spin, bypass daily limit check? Or keep it? Usually free spins bypass limits.
        // Let's assume free spins bypass the "10 per day" limit or they count towards it?
        // Logic below counts it. Let's keep strict limit for now unless requested otherwise.
        if ($premium_today >= 10 && !in_array($user_email, $test_emails)) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('limit', 'Max 10 premium spins per day reached! Come back tomorrow!', ['status' => 429]);
        }
    }

    // 🎰 REWARD LOGIC: Awarding Vault Tokens 💎
    // Updated for Majestic Vault System (10x Value Scaling)
    $rand = rand(1, 1000);
    $reward_type = 'tokens';
    
    if ($is_premium) {
        if ($rand <= 350)
            $reward = 20;      // 35% - 20 Tokens
        elseif ($rand <= 700)
            $reward = 50;      // 35% - 50 Tokens
        elseif ($rand <= 900)
            $reward = 100;     // 20% - 100 Tokens
        elseif ($rand <= 990)
            $reward = 250;     // 9% - 250 Tokens
        else
            $reward = 1000;    // 1% - 1000 Tokens (Jackpot)
    } else {
        if ($rand <= 500)
            $reward = 1;       // 1 Token
        elseif ($rand <= 850)
            $reward = 5;       // 5 Tokens
        elseif ($rand <= 980)
            $reward = 10;      // 10 Tokens
        else
            $reward = 50;      // 50 Tokens
    }

    try {
        // Calculate New Token Balance
        $current_tokens = (int) ($wallet->vault_token_balance ?? 0);
        $new_token_bal = $current_tokens + $reward;
        
        // Cost is still in Cash (Balance)
        $new_cash_bal = round($wallet->balance - $cost, 2);
        
        $update_data = [
            'balance' => $new_cash_bal, // Deduct cost
            'vault_token_balance' => $new_token_bal, // Add tokens
            'spin_claimed_today' => 1, 
            'last_spin_date' => current_time('mysql')
        ];

        if ($used_free_spin) {
            $update_data['premium_spins_balance'] = $wallet->premium_spins_balance - 1;
        }

        $wpdb->update($t_bal, $update_data, ['user_email' => $user_email]);
        
        // Log Spin History
        $wpdb->insert($t_spins, [
            'user_email' => $user_email, 
            'spin_date' => current_time('Y-m-d'), 
            'spin_time' => current_time('H:i:s'), 
            'reward_amount' => $reward, 
            'is_premium' => $is_premium ? 1 : 0
        ]);

        if ($cost > 0)
            mhjoy_log_transaction($user_email, 'debit', $cost, 'spin', 'Premium Spin Cost', $new_cash_bal);
            
        if ($reward > 0)
            mhjoy_log_transaction($user_email, 'credit', 0, 'spin_reward', "Won $reward Vault Tokens", $new_cash_bal); // Amount 0 cash, desc says tokens

        $wpdb->query('COMMIT');
        
        return [
            'success' => true, 
            'reward' => $reward, 
            'new_balance' => $new_cash_bal, // Return cash balance for header update
            'vault_token_balance' => $new_token_bal, // Return new token balance
            'message' => "Won $reward Tokens!"
        ];
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Failed', ['status' => 500]);
    }
}


// ==================== 2. LOYALTY TIER LOGIC ====================

function mhjoy_calculate_tier($user_email)
{
    global $wpdb;

    // Get total spent from stats table
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';
    $stats = $wpdb->get_row($wpdb->prepare("SELECT total_spent FROM $t_stats WHERE user_email = %s", $user_email));

    $spent = $stats ? (float) $stats->total_spent : 0.00;

    // Tier Thresholds
    if ($spent >= 10000)
        return 'platinum'; // VIP King
    if ($spent >= 5000)
        return 'gold';      // Heavy Spender
    if ($spent >= 1000)
        return 'silver';    // Regular
    return 'bronze';                        // Newbie
}

function mhjoy_update_user_tier($user_email)
{
    global $wpdb;
    $new_tier = mhjoy_calculate_tier($user_email);
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // Only update if changed (save DB writes)
    $current = $wpdb->get_var($wpdb->prepare("SELECT loyalty_tier FROM $t_bal WHERE user_email = %s", $user_email));

    if ($current !== $new_tier) {
        $wpdb->update($t_bal, ['loyalty_tier' => $new_tier], ['user_email' => $user_email]);
        return $new_tier;
    }
    return $current;
}

// ==================== 3. FRAUD DETECTION LOGIC ====================

function mhjoy_analyze_fraud_risk($user_email, $device_fp, $ip_address)
{
    global $wpdb;
    $score = 0;
    $reasons = [];

    // Check 1: Multiple Accounts on Same Device
    $t_redemptions = $wpdb->prefix . 'mhjoy_gift_code_redemptions';
    $device_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT user_email) FROM $t_redemptions WHERE device_fingerprint = %s",
        $device_fp
    ));

    if ($device_count > 3) {
        $score += 50;
        $reasons[] = "Device used by $device_count accounts";
    }

    // Check 2: IP Spam
    $ip_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $t_redemptions WHERE ip_address = %s AND redeemed_at > NOW() - INTERVAL 1 HOUR",
        $ip_address
    ));

    if ($ip_count > 10) {
        $score += 40;
        $reasons[] = "High redemption velocity from IP";
    }

    // Decision
    $flag = 'clean';
    if ($score >= 80)
        $flag = 'blocked';
    elseif ($score >= 40)
        $flag = 'suspicious';

    if ($flag !== 'clean') {
        $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
        $wpdb->update(
            $t_bal,
            ['fraud_flag' => $flag, 'fraud_reason' => implode(', ', $reasons)],
            ['user_email' => $user_email]
        );
    }

    return ['score' => $score, 'flag' => $flag];
}

// ==================== 4. REFERRAL SYSTEM ====================

function mhjoy_generate_referral_code($user_email)
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // Check if exists
    $existing = $wpdb->get_var($wpdb->prepare("SELECT referral_code FROM $t_bal WHERE user_email = %s", $user_email));
    if ($existing)
        return $existing;

    // Generate: First 3 letters + Random Hex
    $prefix = strtoupper(substr($user_email, 0, 3));
    $code = $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 5));

    $wpdb->update($t_bal, ['referral_code' => $code], ['user_email' => $user_email]);
    return $code;
}
// ==================== 5. HEADLESS INTEGRATION (THE BRIDGE) ====================
// This answers the call from your MHJoy Headless API plugin

add_filter('mhjoy_wallet_apply_balance', 'mhjoy_wallet_execute_deduction', 10, 3);

function mhjoy_wallet_execute_deduction($amount, $email, $order)
{
    global $wpdb;
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if (empty($data['use_wallet']) || $data['use_wallet'] !== true)
        return 0;

    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT balance, fraud_flag FROM $t_bal WHERE user_email = %s", $email));

    if (!$wallet || $wallet->balance <= 0 || $wallet->fraud_flag === 'blocked')
        return 0;

    $order_total = (float) $order->get_total();
    $deduction = min($order_total, (float) $wallet->balance);

    if ($deduction <= 0)
        return 0;

    // 🎯 THE FIX: We DON'T update the DB balance here.
    // We just tag the order so we can deduct it ONLY after payment is successful.
    $order->update_meta_data('_pending_wallet_deduction', $deduction);

    // Add the visual discount to the order
    $item_fee = new WC_Order_Item_Fee();
    $item_fee->set_name('Wallet Balance Used');
    $item_fee->set_amount(-1 * $deduction);
    $item_fee->set_total(-1 * $deduction);
    $item_fee->set_tax_status('none');
    $order->add_item($item_fee);

    return $deduction;
}
// ==================== 🔗 ADVANCED REFERRAL ENGINE ====================

// 1. Hook into WooCommerce Order Completion
add_action('woocommerce_order_status_completed', 'mhjoy_process_referral_commissions', 10, 1);

function mhjoy_process_referral_commissions($order_id)
{
    global $wpdb;
    $order = wc_get_order($order_id);
    $referee_email = $order->get_billing_email();
    $order_total = (float) $order->get_total();
    if ($order_total < 100)
        return;

    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';

    $referee_data = $wpdb->get_row($wpdb->prepare("SELECT referred_by FROM $t_bal WHERE user_email = %s", $referee_email));
    if (!$referee_data || empty($referee_data->referred_by))
        return;
    $referrer_email = $referee_data->referred_by;

    $wpdb->query('START TRANSACTION');
    $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t_bal WHERE referred_by = %s", $referrer_email));
    $rate = ($count >= 31) ? 0.015 : (($count >= 11) ? 0.010 : 0.005);
    $commission = min($order_total * $rate, 50);

    $total_spent = $wpdb->get_var($wpdb->prepare("SELECT total_spent FROM $t_stats WHERE user_email = %s FOR UPDATE", $referee_email));
    $is_first_unlock = ($total_spent >= 300 && ($total_spent - $order_total) < 300);
    $payout = round($commission + ($is_first_unlock ? 15 : 0), 2);

    if ($payout > 0) {
        $referrer_wallet = $wpdb->get_row($wpdb->prepare("SELECT balance FROM $t_bal WHERE user_email = %s FOR UPDATE", $referrer_email));
        $new_bal = round($referrer_wallet->balance + $payout, 2);
        $wpdb->update($t_bal, ['balance' => $new_bal], ['user_email' => $referrer_email]);
        $note = $is_first_unlock ? "Milestone + Comm" : "Passive Comm";
        mhjoy_log_transaction($referrer_email, 'credit', $payout, 'referral', "$referee_email: $note", $new_bal);
    }
    $wpdb->query('COMMIT');
}

// 7. Endpoint to Link a Referral (When a user enters a code)
function mhjoy_apply_referral_code($referee_email, $code)
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // 🎯 FIX: Search for the code without forcing case-sensitivity
    $referrer = $wpdb->get_row($wpdb->prepare(
        "SELECT user_email FROM $t_bal WHERE referral_code = %s",
        sanitize_text_field($code)
    ));

    if (!$referrer) {
        return new WP_Error('invalid_code', 'This referral code does not exist.', ['status' => 400]);
    }

    // 🎯 FIX: Better message for Self-Referral
    if (strtolower($referrer->user_email) === strtolower($referee_email)) {
        return new WP_Error('self_referral', 'You cannot link to your own partner code!', ['status' => 400]);
    }

    // Check if referee already has a referrer
    $already_referred = $wpdb->get_var($wpdb->prepare("SELECT referred_by FROM $t_bal WHERE user_email = %s", $referee_email));
    if ($already_referred) {
        return new WP_Error('already_linked', 'Your account is already linked to a partner.', ['status' => 400]);
    }

    $wpdb->update($t_bal, ['referred_by' => $referrer->user_email], ['user_email' => $referee_email]);
    return true;
}
// THE SIMPLE TRIGGER
add_action('woocommerce_payment_complete', 'mhjoy_process_wallet_topup', 10, 1);

function mhjoy_process_wallet_topup($order_id)
{
    global $wpdb;
    $order = wc_get_order($order_id);

    // Check if it's a top-up and NOT already credited
    if (!$order || $order->get_meta('_is_wallet_topup') !== 'yes' || $order->get_meta('_credited') === 'yes')
        return;

    $email = $order->get_billing_email();
    $amount = (float) $order->get_total();

    // Bonus math
    // Bonus math - CHANGED TO SPINS
    // $bonus = ($amount >= 5000) ? 0.05 : (($amount >= 1000) ? 0.03 : (($amount >= 500) ? 0.02 : 0));
    // $total = round($amount + ($amount * $bonus), 2);
    
    // No more cash bonus
    $total = $amount; 
    
    // Calculate Free Spins (ULTRA-STRICT)
    $free_spins = 0;
    if ($amount >= 10000) $free_spins = 3;      // Hard Cap
    elseif ($amount >= 5000) $free_spins = 2;   // AOV Baseline
    elseif ($amount >= 1000) $free_spins = 1;   // Entry Level

    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $wpdb->query('START TRANSACTION');

    // Select premium_spins_balance too
    $curr_wallet = $wpdb->get_row($wpdb->prepare("SELECT balance, premium_spins_balance FROM $t_bal WHERE user_email = %s FOR UPDATE", $email));
    
    $curr_bal = $curr_wallet ? $curr_wallet->balance : 0;
    $curr_spins = $curr_wallet ? ($curr_wallet->premium_spins_balance ?? 0) : 0;

    $new_bal = round($curr_bal + $total, 2);
    $new_spins = $curr_spins + $free_spins;

    $wpdb->update($t_bal, ['balance' => $new_bal, 'premium_spins_balance' => $new_spins], ['user_email' => $email]);

    // Mark as done so we don't add money twice
    $order->update_meta_data('_credited', 'yes');
    $order->save();

    // Log the transaction
    // Log the transaction
    if (function_exists('mhjoy_log_transaction')) {
        mhjoy_log_transaction($email, 'credit', $total, 'topup', "Top-up Order #$order_id", $new_bal);
        if ($free_spins > 0) {
            mhjoy_log_transaction($email, 'gift', $free_spins, 'bonus', "Recharge Bonus: $free_spins Spins", $new_spins);
        }
    }

    $wpdb->query('COMMIT');
}

// 🎯 THE FINAL CHARGE: This fires only when money is confirmed
add_action('woocommerce_payment_complete', 'mhjoy_final_wallet_deduction_trigger', 5, 1);

function mhjoy_final_wallet_deduction_trigger($order_id)
{
    global $wpdb;
    $order = wc_get_order($order_id);

    // 1. Check if there is a pending deduction
    $deduction = (float) $order->get_meta('_pending_wallet_deduction');
    $is_processed = $order->get_meta('_wallet_deduction_done');

    if ($deduction <= 0 || $is_processed === 'yes')
        return;

    $email = $order->get_billing_email();
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    $wpdb->query('START TRANSACTION');

    // 2. Lock and take the money for real
    $current_bal = $wpdb->get_var($wpdb->prepare("SELECT balance FROM $t_bal WHERE user_email = %s FOR UPDATE", $email));

    // Safety check: if they spent the money in another tab while this order was pending
    $actual_deduction = min($current_bal, $deduction);
    $new_bal = round($current_bal - $actual_deduction, 2);

    $wpdb->update($t_bal, ['balance' => $new_bal], ['user_email' => $email]);

    // 3. Mark as done
    $order->update_meta_data('_wallet_deduction_done', 'yes');
    $order->add_order_note("💳 Wallet Finalized: Deducted ৳{$actual_deduction}. New Balance: ৳{$new_bal}");
    $order->save();

    // 📜 Log to Audit Ledger
    if (function_exists('mhjoy_log_transaction')) {
        mhjoy_log_transaction($email, 'debit', $actual_deduction, 'order', "Order #$order_id Finalized", $new_bal);
    }

    $wpdb->query('COMMIT');
}

/**
 * Helper: Calculate total free money earned by user
 */
// ==================== 8. MAJESTIC VAULT LOGIC (NEW) ====================

/**
 * Redeem Vault Tokens for a Coupon
 */
function mhjoy_redeem_vault_coupon($user_id, $vault_item_id)
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    
    // 1. Get User Balance
    $user = get_user_by('id', $user_id);
    if (!$user) return new WP_Error('invalid_user', 'User not found', ['status' => 404]);
    
    $wallet = $wpdb->get_row($wpdb->prepare("SELECT vault_token_balance, balance FROM $t_bal WHERE user_email = %s", $user->user_email));
    if (!$wallet) return new WP_Error('no_wallet', 'Wallet not found', ['status' => 404]);

    // 2. Get Vault Item Config
    $cost = (int) get_post_meta($vault_item_id, '_vault_cost', true);
    $discount = (float) get_post_meta($vault_item_id, '_vault_discount_amount', true);
    $min_spend = (float) get_post_meta($vault_item_id, '_vault_min_spend', true);
    
    // 3. Check Balance
    $current_tokens = (int) ($wallet->vault_token_balance ?? 0);
    if ($current_tokens < $cost) {
        return new WP_Error('insufficient_tokens', 'Not enough Vault Tokens', ['status' => 400]);
    }

    // 4. Create WooCommerce Coupon
    $coupon_code = 'VAULT-' . strtoupper(wp_generate_password(6, false));
    $coupon = new WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_amount($discount);
    $coupon->set_discount_type('fixed_cart'); // Fixed Amount Discount
    $coupon->set_minimum_amount($min_spend);
    $coupon->set_usage_limit(1);
    $coupon->set_expiry_date(strtotime('+30 days'));
    // Make it specific to user email? Optional.
    // $coupon->set_email_restrictions([$user->user_email]);
    $coupon->save();

    // 5. Deduct Tokens
    $wpdb->update($t_bal, ['vault_token_balance' => $current_tokens - $cost], ['user_email' => $user->user_email]);

    // 6. Log Transaction
    if (function_exists('mhjoy_log_transaction')) {
        mhjoy_log_transaction($user->user_email, 'debit', 0, 'vault_redemption', "Redeemed Item #$vault_item_id for $cost Tokens", $wallet->balance);
    }

    return [
        'success' => true,
        'coupon_code' => $coupon_code,
        'message' => "Vault Unlocked! Code: $coupon_code",
        'remaining_tokens' => $current_tokens - $cost
    ];
}

/**
 * Migration: Convert 'Free Money' to Tokens
 */
function mhjoy_migrate_wallet_to_tokens($user_email)
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_txn = $wpdb->prefix . 'mhjoy_wallet_transactions';

    // 1. Calculate Real Cash (Topups) vs Free Money
    $real_cash = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $t_txn WHERE user_email = %s AND type = 'credit' AND source IN ('topup', 'refund')", 
        $user_email
    )) ?: 0;
    
    $total_deductions = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $t_txn WHERE user_email = %s AND type = 'debit'", 
        $user_email
    )) ?: 0;

    // Remaining Real Cash = Total Real In - Total Spent
    // If they spent more than they deposited (used bonuses), Real Cash is 0.
    $current_real = max(0, $real_cash - $total_deductions);

    // Get Current Total Balance from DB
    $current_wallet = $wpdb->get_row($wpdb->prepare("SELECT balance, vault_token_balance FROM $t_bal WHERE user_email = %s", $user_email));
    
    if (!$current_wallet) return;

    $total_balance = (float) $current_wallet->balance;
    
    // Free Money = Everything else
    $free_money = max(0, $total_balance - $current_real);

    // If Free Money > 0, Convert it!
    if ($free_money > 0) {
        $tokens_to_add = floor($free_money * 10); // Rate: 1tk = 10 Tokens
        $new_token_balance = ($current_wallet->vault_token_balance ?? 0) + $tokens_to_add;

        // Force Balance to be ONLY Real Cash
        $wpdb->update($t_bal, [
            'balance' => $current_real,
            'vault_token_balance' => $new_token_balance
        ], ['user_email' => $user_email]);

        // Log it
        mhjoy_log_transaction($user_email, 'info', 0, 'migration', "Migrated ৳$free_money to $tokens_to_add Tokens", $current_real);
    }
}