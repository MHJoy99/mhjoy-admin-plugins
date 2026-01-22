<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register Menu
add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        'Majestic Console',
        '💎 Majestic Console',
        'manage_woocommerce',
        'majestic-vault',
        'mhjoy_render_majestic_console'
    );
}, 99);

function mhjoy_render_majestic_console() {
    global $wpdb;
    
    // Process Actions
    $message = mhjoy_majestic_handle_actions();

    // Tables
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_txns = $wpdb->prefix . 'mhjoy_wallet_transactions';
    $t_spins = $wpdb->prefix . 'mhjoy_spin_history';

    // Stats
    $total_tokens = $wpdb->get_var("SELECT SUM(vault_token_balance) FROM $t_bal") ?: 0;
    $total_wallet = $wpdb->get_var("SELECT SUM(balance) FROM $t_bal") ?: 0;
    $daily_spins = $wpdb->get_var("SELECT COUNT(*) FROM $t_spins WHERE spin_date = CURDATE()") ?: 0;
    $vault_activity = $wpdb->get_var("SELECT COUNT(*) FROM $t_txns WHERE source = 'vault_redemption'");

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
    ?>
    <div class="wrap mhjoy-majestic-wrap">
        <style>
            :root { --mj-bg: #0f172a; --mj-card: #1e293b; --mj-border: #334155; --mj-text: #f8fafc; --mj-gold: #f59e0b; --mj-primary: #8b5cf6; }
            .mhjoy-majestic-wrap { background: var(--mj-bg); color: var(--mj-text); padding: 25px; min-height: 100vh; font-family: 'Inter', sans-serif; box-sizing: border-box; margin-left: -20px; }
            .mj-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--mj-border); padding-bottom: 20px; margin-bottom: 30px; }
            .mj-header h1 { color: white !important; font-size: 2rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin: 0; }
            
            .mj-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
            .mj-card { background: var(--mj-card); border: 1px solid var(--mj-border); border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
            .mj-card .val { font-size: 2rem; font-weight: 800; color: white; display: flex; align-items: baseline; gap: 5px; }
            .mj-card .label { color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 5px; }
            
            .mj-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
            .mj-tab { padding: 10px 20px; border-radius: 8px; background: var(--mj-card); color: #94a3b8; text-decoration: none; border: 1px solid var(--mj-border); font-weight: 600; display: flex; align-items: center; gap: 8px; }
            .mj-tab:hover, .mj-tab.active { background: var(--mj-primary); color: white; border-color: var(--mj-primary); }

            .mj-table { width: 100%; border-collapse: collapse; background: var(--mj-card); border-radius: 12px; overflow: hidden; }
            .mj-table th { background: #020617; padding: 15px; text-align: left; color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; }
            .mj-table td { padding: 15px; border-top: 1px solid var(--mj-border); color: #e2e8f0; }
            .mj-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
            
            .mj-btn { background: var(--mj-primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
            .mj-input { background: #020617; border: 1px solid var(--mj-border); color: white; padding: 8px; border-radius: 6px; width: 100%; box-sizing: border-box; }
        </style>

        <div class="mj-header">
            <h1><span>💎</span> Majestic Console</h1>
            <div>Designed for Agents</div>
        </div>

        <?php echo $message; ?>

        <div class="mj-grid">
            <div class="mj-card" style="border-top: 4px solid var(--mj-gold);">
                <div class="label">Vault Reserves</div>
                <div class="val text-gold"><?php echo number_format($total_tokens); ?> <span style="font-size: 1rem; color: var(--mj-gold);">💎</span></div>
            </div>
            <div class="mj-card" style="border-top: 4px solid var(--mj-primary);">
                <div class="label">User Liquidity</div>
                <div class="val">৳<?php echo number_format($total_wallet, 2); ?></div>
            </div>
            <div class="mj-card">
                <div class="label">Daily Spins</div>
                <div class="val"><?php echo number_format($daily_spins); ?></div>
            </div>
            <div class="mj-card">
                <div class="label">Redemptions</div>
                <div class="val"><?php echo number_format($vault_activity); ?></div>
            </div>
        </div>

        <div class="mj-tabs">
            <a href="?page=majestic-vault&tab=overview" class="mj-tab <?php echo $tab == 'overview' ? 'active' : ''; ?>">👥 Users</a>
            <a href="?page=majestic-vault&tab=vault_history" class="mj-tab <?php echo $tab == 'vault_history' ? 'active' : ''; ?>">💎 Vault Log</a>
            <a href="?page=majestic-vault&tab=history" class="mj-tab <?php echo $tab == 'history' ? 'active' : ''; ?>">📜 Audit</a>
            <a href="?page=majestic-vault&tab=bulk" class="mj-tab <?php echo $tab == 'bulk' ? 'active' : ''; ?>">📦 Bulk Codes</a>
        </div>

        <div class="mj-content">
            <?php
            switch($tab) {
                case 'vault_history': mhjoy_majestic_vault_log(); break;
                case 'history': mhjoy_majestic_audit_log(); break;
                case 'bulk': mhjoy_majestic_bulk_gen(); break;
                default: mhjoy_majestic_users(); break;
            }
            ?>
        </div>
    </div>
    <?php
}

function mhjoy_majestic_users() {
    global $wpdb;
    $s = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $sort = isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : 'balance';
    
    $sql = "SELECT * FROM {$wpdb->prefix}mhjoy_wallet_balance";
    if ($s) $sql .= $wpdb->prepare(" WHERE user_email LIKE %s", "%$s%");
    
    // Sort logic
    if ($sort === 'tokens') {
        $sql .= " ORDER BY vault_token_balance DESC";
    } else {
        $sql .= " ORDER BY balance DESC";
    }
    
    $sql .= " LIMIT 50";
    $users = $wpdb->get_results($sql);
    ?>
    <div style="background: var(--mj-card); padding: 15px; margin-bottom: 20px; border-radius: 12px; display: flex; justify-content: space-between;">
        <h3 style="margin:0; color:white;">User Database</h3>
        <form method="post" style="display:flex; gap:10px;">
            <input name="s" class="mj-input" placeholder="Search..." value="<?php echo esc_attr($s); ?>" style="width:200px;">
            <select name="sort" class="mj-input" style="width:150px;">
                <option value="balance" <?php selected($sort, 'balance'); ?>>Sort by Money (৳)</option>
                <option value="tokens" <?php selected($sort, 'tokens'); ?>>Sort by Tokens (💎)</option>
            </select>
            <button class="mj-btn">Apply</button>
        </form>
    </div>
    <table class="mj-table">
        <thead><tr><th>Email</th><th>Balance</th><th>Tokens</th><th>Tier</th><th>Vault Access</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach($users as $u): 
                $wp_user = get_user_by('email', $u->user_email);
                $has_unlimited = $wp_user ? get_user_meta($wp_user->ID, 'vault_unlimited_override', true) : false;
            ?>
            <tr>
                <td><strong><?php echo $u->user_email; ?></strong></td>
                <td style="color: #4ade80; font-weight:bold;">৳<?php echo number_format($u->balance, 2); ?></td>
                <td style="color: #fbbf24; font-weight:bold;"><?php echo number_format($u->vault_token_balance ?? 0); ?> 💎</td>
                <td><span class="mj-badge" style="background: #334155; color: white;"><?php echo $u->loyalty_tier; ?></span></td>
                <td>
                    <?php if ($has_unlimited): ?>
                        <span class="mj-badge" style="background: #10b981; color: white;">🔓 UNLIMITED</span>
                    <?php else: ?>
                        <span class="mj-badge" style="background: #ef4444; color: white;">⏰ COOLDOWN</span>
                    <?php endif; ?>
                </td>
                <td style="display:flex; gap:5px;">
                    <button class="mj-btn" style="padding: 4px 8px; font-size: 0.75rem; background: #334155;" onclick="editCash('<?php echo $u->user_email; ?>', <?php echo $u->balance; ?>)">Edit ৳</button>
                    <button class="mj-btn" style="padding: 4px 8px; font-size: 0.75rem; background: #b45309;" onclick="editTokens('<?php echo $u->user_email; ?>', <?php echo $u->vault_token_balance ?? 0; ?>)">Edit 💎</button>
                    <button class="mj-btn" style="padding: 4px 8px; font-size: 0.75rem; background: <?php echo $has_unlimited ? '#dc2626' : '#10b981'; ?>;" onclick="toggleUnlimited('<?php echo $u->user_email; ?>', <?php echo $has_unlimited ? 'true' : 'false'; ?>)">
                        <?php echo $has_unlimited ? '🔒 Revoke' : '🔓 Grant'; ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Quick Edit JS -->
    <script>
        function editCash(email, bal) {
            let nBal = prompt("Update Wallet Balance (৳) for " + email, bal);
            if (nBal === null) return;
            postAction(email, 'balance', nBal);
        }
        function editTokens(email, tok) {
            let nTok = prompt("Update Vault Tokens (💎) for " + email, tok);
            if (nTok === null) return;
            postAction(email, 'tokens', nTok);
        }
        function toggleUnlimited(email, currentStatus) {
            const action = currentStatus ? 'revoke' : 'grant';
            const msg = currentStatus 
                ? `Revoke unlimited vault access for ${email}? They will return to cooldown limits.`
                : `Grant unlimited vault access to ${email}? They can redeem anytime with no cooldown.`;
            
            if (!confirm(msg)) return;
            postAction(email, 'vault_unlimited', action);
        }
        function postAction(email, type, val) {
            let f = document.createElement('form'); f.method = 'POST';
            f.innerHTML = `
                <input type="hidden" name="action_quick_edit_v2" value="1">
                <input type="hidden" name="email" value="${email}">
                <input type="hidden" name="type" value="${type}">
                <input type="hidden" name="value" value="${val}">
            `;
            document.body.appendChild(f);
            f.submit();
        }
    </script>
    <?php
}

function mhjoy_majestic_vault_log() {
    global $wpdb;
    // Show both Earnings (Spins, Gifts) and Spending (Redemptions)
    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mhjoy_wallet_transactions WHERE source IN ('vault_redemption', 'spin_reward', 'admin_adjustment', 'token_gift', 'daily_reward') ORDER BY created_at DESC LIMIT 50");
    echo '<table class="mj-table"><thead><tr><th>Time</th><th>User</th><th>Activity</th><th>Tokens</th></tr></thead><tbody>';
    foreach($logs as $l) {
        $msg = strtolower($l->reference);
        $is_token_related = strpos($msg, 'token') !== false;
        
        // Skip purely cash logs in this view
        if (!$is_token_related && $l->source == 'admin_adjustment' && $l->amount > 0) continue;

        $is_gain = in_array($l->source, ['spin_reward', 'admin_adjustment', 'token_gift', 'daily_reward']) && (strpos($msg, 'won') !== false || strpos($msg, 'credit') !== false || strpos($msg, 'added') !== false || strpos($msg, 'to') !== false || strpos($msg, 'daily') !== false);
        // Special case: "Redeemed" is a loss, "Unlocked" is a loss (redemption)
        if (strpos($msg, 'redeem') !== false || strpos($msg, 'unlocked') !== false) $is_gain = false;

        $color = $is_gain ? '#4ade80' : '#fbbf24'; 
        $prefix = $is_gain ? '+' : '-';
        
        // Regex to catch "100 Tokens", "Tokens to 190", "Tokens: 50"
        if (preg_match('/(\d+)\s*(?:Vault\s+)?Tokens|Tokens\s*(?:to|:)?\s*(\d+)/i', $l->reference, $matches)) {
            $amt = !empty($matches[1]) ? $matches[1] : $matches[2];
        } else {
            $amt = '?';
        }
        
        echo "<tr><td>{$l->created_at}</td><td>{$l->user_email}</td><td>{$l->reference}</td><td style='color:$color; font-weight:bold;'>$prefix$amt 💎</td></tr>";
    }
    echo '</tbody></table>';
}

function mhjoy_majestic_audit_log() {
    global $wpdb;
    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}mhjoy_wallet_transactions ORDER BY created_at DESC LIMIT 50");
    echo '<table class="mj-table"><thead><tr><th>Time</th><th>User</th><th>Type</th><th>Amount</th><th>Source</th></tr></thead><tbody>';
    foreach($logs as $l) {
        $c = $l->type == 'credit' ? '#4ade80' : '#f87171';
        
        // Smart Amount Display
        if (strpos(strtolower($l->reference), 'token') !== false) {
             // Try to extract token amount (Robust Regex)
             if (preg_match('/(\d+)\s*(?:Vault\s+)?Tokens|Tokens\s*(?:to|:)?\s*(\d+)/i', $l->reference, $matches)) {
                 $val = !empty($matches[1]) ? $matches[1] : $matches[2];
                 $amt = "$val 💎";
             } else {
                 $amt = "Token Activity"; 
             }
        } else {
            $amt = '৳' . number_format($l->amount, 2);
        }
        
        echo "<tr><td>{$l->created_at}</td><td>{$l->user_email}</td><td><span class='mj-badge' style='background:$c; color:black;'>{$l->type}</span></td><td>$amt</td><td>{$l->source}</td></tr>";
    }
    echo '</tbody></table>';
}

function mhjoy_majestic_bulk_gen() {
    ?>
    <div style="background: var(--mj-card); padding: 30px; border-radius: 12px; max-width: 500px; margin: 0 auto; border: 1px solid var(--mj-primary);">
        <h3 style="margin-top:0; text-align:center; color:white;">📦 Bulk Code Generator</h3>
        <form method="post">
            <input type="hidden" name="action_bulk_gen" value="1">
            <div style="margin-bottom: 15px;">
                <label style="color: #94a3b8; display:block; margin-bottom:5px;">Reward Type</label>
                <select name="reward_type" class="mj-input">
                    <option value="cash">💰 Wallet Funds (BDT)</option>
                    <option value="token">💎 Vault Tokens</option>
                </select>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="color: #94a3b8; display:block; margin-bottom:5px;">Amount per Code</label>
                <input type="number" name="amount" class="mj-input" required placeholder="100">
            </div>
            <div style="margin-bottom: 15px;">
                <label style="color: #94a3b8; display:block; margin-bottom:5px;">Quantity</label>
                <input type="number" name="qty" class="mj-input" required max="500" placeholder="50">
            </div>
            <div style="margin-bottom: 25px;">
                <label style="color: #94a3b8; display:block; margin-bottom:5px;">Prefix (Optional)</label>
                <input type="text" name="prefix" class="mj-input" placeholder="PROMO-">
            </div>
            <button class="mj-btn" style="width:100%; padding: 12px;">Generate</button>
        </form>
    </div>
    <?php
}

function mhjoy_majestic_handle_actions() {
    global $wpdb;
    if (isset($_POST['action_quick_edit_v2'])) {
        $email = sanitize_email($_POST['email']);
        $type = sanitize_key($_POST['type']);
        $val = floatval($_POST['value']);
        
        $table = $wpdb->prefix.'mhjoy_wallet_balance';
        $curr = $wpdb->get_row($wpdb->prepare("SELECT balance, vault_token_balance FROM $table WHERE user_email = %s", $email));
        
        if ($type === 'balance') {
            $wpdb->update($table, ['balance' => $val], ['user_email' => $email]);
            $msg = "Admin adjusted Balance to ৳$val";
            mhjoy_log_transaction($email, 'credit', 0, 'admin_adjustment', $msg, $val);
        } elseif ($type === 'tokens') {
            $wpdb->update($table, ['vault_token_balance' => (int)$val], ['user_email' => $email]);
            $msg = "Admin adjusted Tokens to $val 💎";
            mhjoy_log_transaction($email, 'credit', 0, 'admin_adjustment', $msg, $curr ? $curr->balance : 0);
        } elseif ($type === 'vault_unlimited') {
            $wp_user = get_user_by('email', $email);
            if ($wp_user) {
                if ($val === 'grant') {
                    update_user_meta($wp_user->ID, 'vault_unlimited_override', true);
                    return '<div class="notice notice-success"><p>✅ Granted unlimited vault access to ' . $email . '</p></div>';
                } else {
                    delete_user_meta($wp_user->ID, 'vault_unlimited_override');
                    return '<div class="notice notice-success"><p>🔒 Revoked unlimited vault access from ' . $email . '</p></div>';
                }
            }
        }
        
        return '<div class="notice notice-success"><p>Updated!</p></div>';
    }
    if (isset($_POST['action_bulk_gen'])) {
        $amt = floatval($_POST['amount']);
        $qty = intval($_POST['qty']);
        $type = sanitize_key($_POST['reward_type']) ?: 'cash';
        $pfx = sanitize_text_field($_POST['prefix']) ?: 'BULK-';
        
        // Auto-prefix for tokens if not set
        if($type === 'token' && $_POST['prefix'] === '') $pfx = 'TOK-';

        for($i=0; $i<$qty; $i++) {
            $code = $pfx . strtoupper(bin2hex(random_bytes(3)));
            $wpdb->insert($wpdb->prefix.'mhjoy_gift_codes', ['code'=>$code, 'amount'=>$amt, 'type'=>$type]);
        }
        return '<div class="notice notice-success"><p>Generated '.$qty.' '.$type.' codes!</p></div>';
    }
    return '';
}
