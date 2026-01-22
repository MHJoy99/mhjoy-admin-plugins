<?php
/**
 * Plugin Name: MHJoy Vault System
 * Description: The Kingpin's Vault - Manages 'Vault Tokens' and the Reward Store.
 * Version: 1.0.0
 * Author: MHJoy Team
 */

if (!defined('ABSPATH')) exit;

class MHJoy_Vault_System {

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_vault_meta_boxes']);
        add_action('save_post', [$this, 'save_vault_meta']);
        add_action('rest_api_init', [$this, 'register_api_endpoints']);
        
        // MAJESTIC UPGRADES (V2)
        require_once plugin_dir_path(__FILE__) . 'includes/api-v2.php';
        require_once plugin_dir_path(__FILE__) . 'includes/majestic-console.php';
    }

    /**
     * 1. Register 'Vault Item' Custom Post Type
     */
    public function register_cpt() {
        register_post_type('vault_item', [
            'labels' => [
                'name' => 'Vault Items',
                'singular_name' => 'Vault Item',
                'add_new_item' => 'Add New Reward',
                'edit_item' => 'Edit Reward',
                'all_items' => 'Vault Inventory'
            ],
            'public' => false,  // Not viewable on frontend as a page
            'show_ui' => true,  // Show in Admin
            'show_in_rest' => true, // Headless ready
            'menu_icon' => 'dashicons-shield',
            'supports' => ['title', 'editor', 'thumbnail'],
            'rewrite' => false
        ]);
    }

    /**
     * 2. Add Metaboxes for Config (Cost, Value, Min Spend)
     */
    public function add_vault_meta_boxes() {
        add_meta_box('vault_config', 'Vault Config', [$this, 'render_meta_box'], 'vault_item', 'normal', 'high');
    }

    public function render_meta_box($post) {
        $cost = get_post_meta($post->ID, '_vault_cost', true);
        $discount_amount = get_post_meta($post->ID, '_vault_discount_amount', true);
        $min_spend = get_post_meta($post->ID, '_vault_min_spend', true);
        $rarity = get_post_meta($post->ID, '_vault_rarity', true);
        ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <p>
                <label><strong>Token Cost 💎</strong></label><br>
                <input type="number" name="vault_cost" value="<?php echo esc_attr($cost); ?>" style="width:100%">
            </p>
            <p>
                <label><strong>Discount Amount (৳)</strong></label><br>
                <input type="number" name="vault_discount_amount" value="<?php echo esc_attr($discount_amount); ?>" style="width:100%">
            </p>
            <p>
                <label><strong>Min Spend Req (৳)</strong></label><br>
                <input type="number" name="vault_min_spend" value="<?php echo esc_attr($min_spend); ?>" style="width:100%">
            </p>
            <p>
                <label><strong>Rarity</strong></label><br>
                <select name="vault_rarity" style="width:100%">
                    <option value="rare" <?php selected($rarity, 'rare'); ?>>Rare (Blue)</option>
                    <option value="epic" <?php selected($rarity, 'epic'); ?>>Epic (Purple)</option>
                    <option value="legendary" <?php selected($rarity, 'legendary'); ?>>Legendary (Gold)</option>
                </select>
            </p>
            <p style="grid-column: span 2;">
                <label><strong>Linked Coupon Template (Optional)</strong></label><br>
                <input type="text" name="vault_template_id" value="<?php echo esc_attr(get_post_meta($post->ID, '_vault_template_id', true)); ?>" style="width:100%" placeholder="Enter a WooCommerce Coupon Code to use as a template (e.g. TEMPLATE-500)">
                <span class="description" style="font-size:12px; color:#666;">If set, the system will CLONE this coupon (including exclude products, categories, limits) for the user. If empty, it uses the basic discount above.</span>
            </p>
            <p style="grid-column: span 2;">
                <label><strong>Detailed Description (HTML Allowed)</strong></label><br>
                <textarea name="vault_detailed_desc" rows="4" style="width:100%" placeholder="Enter a detailed description of what the user gets..."><?php echo esc_textarea(get_post_meta($post->ID, '_vault_detailed_desc', true)); ?></textarea>
                <span class="description" style="font-size:12px; color:#666;">This will be shown in the detail modal. You can use HTML for formatting.</span>
            </p>
            <p style="grid-column: span 2;">
                <label><strong>How to Use / Use Case</strong></label><br>
                <textarea name="vault_use_case" rows="4" style="width:100%" placeholder="Explain how to use this coupon or what it's best for..."><?php echo esc_textarea(get_post_meta($post->ID, '_vault_use_case', true)); ?></textarea>
                <span class="description" style="font-size:12px; color:#666;">Instructions or tips for using this reward effectively.</span>
            </p>
        </div>
        <?php
    }

    public function save_vault_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        // Regular fields (no HTML)
        $text_fields = ['vault_cost', 'vault_discount_amount', 'vault_min_spend', 'vault_rarity', 'vault_template_id'];
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // HTML fields (allow safe HTML)
        $html_fields = ['vault_detailed_desc', 'vault_use_case'];
        foreach ($html_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, wp_kses_post($_POST[$field]));
            }
        }
    }

    /**
     * 3. API Endpoints (Placeholder)
     */
    public function register_api_endpoints() {
        // GET /mhjoy/v1/vault-items
        register_rest_route('mhjoy/v1', '/vault-items', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_items'],
            'permission_callback' => '__return_true'
        ]);

        // POST /mhjoy/v1/vault/redeem
        register_rest_route('mhjoy/v1', '/vault/redeem', [
            'methods' => 'POST',
            'callback' => [$this, 'api_redeem_item'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function api_redeem_item($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $vault_item_id = isset($params['vault_item_id']) ? $params['vault_item_id'] : $request->get_param('vault_item_id');
        $user_email = isset($params['user_email']) ? $params['user_email'] : $request->get_param('user_email');

        if (empty($vault_item_id)) {
            return new WP_Error('missing_param', "Vault Item ID is required. Received: " . print_r($params, true), ['status' => 400]);
        }
        if (empty($user_email)) {
             return new WP_Error('missing_param', "User Email is required. Received: " . print_r($params, true), ['status' => 400]);
        }


        // 🛡️ TIME-BASED VAULT LIMITS with Admin Override
        
        // Check if user has admin override (unlimited access)
        $has_override = get_user_meta(get_user_by('email', $user_email)->ID ?? 0, 'vault_unlimited_override', true);
        
        if (!$has_override) {
            // Get user's purchase history
            $customer_orders = wc_get_orders([
                'billing_email' => $user_email,
                'status' => ['completed', 'processing'],
                'return' => 'ids'
            ]);
            
            // Calculate total spent
            $total_spent = 0;
            foreach ($customer_orders as $order_id) {
                $order = wc_get_order($order_id);
                $total_spent += $order->get_total();
            }
            
            // Determine user tier
            $is_premium_vip = ($total_spent >= 1000); // ৳1000+ = Premium VIP
            $is_vip = !empty($customer_orders); // Any purchase = VIP
            
            // Get last redemption date
            $last_redemption = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(created_at) FROM {$wpdb->prefix}mhjoy_wallet_transactions 
                 WHERE user_email = %s AND source = 'vault_redemption'",
                $user_email
            ));
            
            // Apply cooldown based on tier
            if (!$is_premium_vip) {
                $cooldown_days = $is_vip ? 7 : 30; // VIP: 7 days, Free: 30 days
                
                if ($last_redemption) {
                    $hours_since = (strtotime('now') - strtotime($last_redemption)) / 3600;
                    $days_since = floor($hours_since / 24);
                    $cooldown_hours = $cooldown_days * 24;
                    
                    if ($hours_since < $cooldown_hours) {
                        $hours_remaining = ceil($cooldown_hours - $hours_since);
                        $days_remaining = ceil($hours_remaining / 24);
                        
                        $tier_name = $is_vip ? 'VIP' : 'Free';
                        return new WP_Error('cooldown_active', 
                            "⏰ Cooldown active! {$tier_name} users can redeem once every {$cooldown_days} days. Next redemption available in {$days_remaining} days.", 
                            ['status' => 429]
                        );
                    }
                }
            }
            // Premium VIP (৳1000+) has no cooldown - unlimited access
        }
        // Users with admin override also have unlimited access


        // 1. Get Item Cost & Template
        $cost = (int) get_post_meta($vault_item_id, '_vault_cost', true);
        $template_code = get_post_meta($vault_item_id, '_vault_template_id', true);
        
        // 2. Check Balance
        $balance = (int) $wpdb->get_var($wpdb->prepare("SELECT vault_token_balance FROM {$wpdb->prefix}mhjoy_wallet_balance WHERE user_email = %s", $user_email));
        if ($balance < $cost) {
            return new WP_Error('insufficient_funds', "Not enough tokens. Required: $cost, Balance: $balance", ['status' => 402]);
        }


        // 3. GENERATE COUPON
        $new_code = 'VAULT-' . strtoupper(bin2hex(random_bytes(3)));
        
        if ($template_code) {
            // CLONE STRATEGY
            $template = new WC_Coupon($template_code);
            if (!$template->get_id()) {
                return new WP_Error('config_error', 'Template coupon not found', ['status' => 500]);
            }
            
            $coupon = new WC_Coupon();
            $coupon->set_code($new_code);
            
            // MERGE STRATEGY: 
            // 1. Use Vault Config Amount if set, otherwise fallback to Template Amount
            $vault_discount = (float) get_post_meta($vault_item_id, '_vault_discount_amount', true);
            $final_amount = ($vault_discount > 0) ? $vault_discount : $template->get_amount();
            $coupon->set_amount($final_amount);
            
            // 2. Use Vault Config Min Spend if set, otherwise fallback to Template
            $vault_min = (float) get_post_meta($vault_item_id, '_vault_min_spend', true);
            $final_min = ($vault_min > 0) ? $vault_min : $template->get_minimum_amount();
            $coupon->set_minimum_amount($final_min);

            $coupon->set_discount_type($template->get_discount_type());
            $coupon->set_description("Redeemed from Vault (Template: $template_code)");
            
            // Usage Limits (Forced overrides for Vault)
            $coupon->set_usage_limit(1);
            $coupon->set_usage_limit_per_user(1);
            $coupon->set_email_restrictions([$user_email]);

            // Copy Constraints from Template
            $coupon->set_individual_use($template->get_individual_use());
            $coupon->set_product_ids($template->get_product_ids());
            $coupon->set_excluded_product_ids($template->get_excluded_product_ids());
            $coupon->set_product_categories($template->get_product_categories());
            $coupon->set_excluded_product_categories($template->get_excluded_product_categories());
            $coupon->set_minimum_amount($template->get_minimum_amount());
            $coupon->set_maximum_amount($template->get_maximum_amount());
            $coupon->set_exclude_sale_items($template->get_exclude_sale_items());
            $coupon->set_free_shipping($template->get_free_shipping());
            
            $coupon->save();

        } else {
            // FALLBACK STRATEGY (Simple Fixed Amount)
            $discount = get_post_meta($vault_item_id, '_vault_discount_amount', true);
            $min_spend = get_post_meta($vault_item_id, '_vault_min_spend', true);
            
            $coupon = new WC_Coupon();
            $coupon->set_code($new_code);
            $coupon->set_discount_type('fixed_cart');
            $coupon->set_amount($discount);
            $coupon->set_minimum_amount($min_spend);
            $coupon->set_individual_use(true);
            $coupon->set_usage_limit(1);
            $coupon->set_usage_limit_per_user(1);
            $coupon->set_email_restrictions([$user_email]);
            $coupon->save();
        }

        // 4. DEDUCT TOKENS
        $wpdb->update(
            "{$wpdb->prefix}mhjoy_wallet_balance",
            ['vault_token_balance' => $balance - $cost],
            ['user_email' => $user_email]
        );

        // 5. LOG TRANSACTION
        if (function_exists('mhjoy_log_transaction')) {
             mhjoy_log_transaction($user_email, 'debit', 0, 'vault_redemption', "Unlocked coupon: $new_code for $cost Tokens", $balance - $cost);
        }

        return new WP_REST_Response([
            'success' => true,
            'coupon_code' => $new_code,
            'message' => 'Unlocked! Coupon code applied.',
            'new_balance' => $balance - $cost
        ], 200);
    }

    public function api_get_items() {
        $args = [
            'post_type' => 'vault_item',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        $posts = get_posts($args);
        $data = [];

        foreach ($posts as $post) {
            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'description' => $post->post_content, // Or excerpt
                'cost' => (int) get_post_meta($post->ID, '_vault_cost', true),
                'discount' => (int) get_post_meta($post->ID, '_vault_discount_amount', true),
                'min_spend' => (int) get_post_meta($post->ID, '_vault_min_spend', true),
                'rarity' => get_post_meta($post->ID, '_vault_rarity', true) ?: 'rare',
                'detailed_desc' => get_post_meta($post->ID, '_vault_detailed_desc', true) ?: '',
                'use_case' => get_post_meta($post->ID, '_vault_use_case', true) ?: '',
            ];
        }

        return new WP_REST_Response($data, 200);
    }
}

new MHJoy_Vault_System();

/**
 * 4. SYSTEM WIDE: User Profile Integration
 * Allows Admins to edit Wallet/Token balance from the standard WP User Profile.
 */
add_action('show_user_profile', 'mhjoy_vault_user_profile_fields');
add_action('edit_user_profile', 'mhjoy_vault_user_profile_fields');

function mhjoy_vault_user_profile_fields($user) {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT balance, vault_token_balance FROM {$wpdb->prefix}mhjoy_wallet_balance WHERE user_email = %s", $user->user_email));
    $balance = $row ? $row->balance : 0;
    $tokens = $row ? $row->vault_token_balance : 0;
    ?>
    <h3>💎 MHJoy Wallet & Vault</h3>
    <table class="form-table">
        <tr>
            <th><label for="mhjoy_wallet_balance">Wallet Balance (৳)</label></th>
            <td>
                <input type="number" name="mhjoy_wallet_balance" id="mhjoy_wallet_balance" value="<?php echo esc_attr($balance); ?>" class="regular-text" step="0.01">
                <p class="description">Main currency balance.</p>
            </td>
        </tr>
        <tr>
            <th><label for="mhjoy_vault_tokens">Vault Tokens (💎)</label></th>
            <td>
                <input type="number" name="mhjoy_vault_tokens" id="mhjoy_vault_tokens" value="<?php echo esc_attr($tokens); ?>" class="regular-text">
                <p class="description">Tokens used in the Vault Store.</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'mhjoy_vault_save_user_profile_fields');
add_action('edit_user_profile_update', 'mhjoy_vault_save_user_profile_fields');

function mhjoy_vault_save_user_profile_fields($user_id) {
    if (!current_user_can('manage_options')) return;
    
    global $wpdb;
    $user = get_userdata($user_id);
    $email = $user->user_email;
    $table = $wpdb->prefix . 'mhjoy_wallet_balance';
    
    // Check if values changed
    if (isset($_POST['mhjoy_wallet_balance']) && isset($_POST['mhjoy_vault_tokens'])) {
        $new_bal = floatval($_POST['mhjoy_wallet_balance']);
        $new_tok = intval($_POST['mhjoy_vault_tokens']);
        
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_email = %s", $email));
        
        if ($existing) {
            $old_bal = floatval($existing->balance);
            $old_tok = intval($existing->vault_token_balance);
            
            if ($old_bal !== $new_bal || $old_tok !== $new_tok) {
                $wpdb->update($table, ['balance' => $new_bal, 'vault_token_balance' => $new_tok], ['user_email' => $email]);
                
                // Audit Log
                if (function_exists('mhjoy_log_transaction')) {
                    mhjoy_log_transaction($email, 'credit', 0, 'admin_adjustment', "Admin Profile Update: ৳$new_bal | $new_tok Tokens", $new_bal);
                }
            }
        } else {
            // Create user row if missing
            $wpdb->insert($table, ['user_email' => $email, 'balance' => $new_bal, 'vault_token_balance' => $new_tok]);
        }
    }
}
