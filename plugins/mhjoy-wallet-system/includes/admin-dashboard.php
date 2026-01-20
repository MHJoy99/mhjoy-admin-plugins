<?php
if (!defined('ABSPATH')) {
    exit;
}

// ==================== ADMIN MENU REGISTRATION ====================
add_action('admin_menu', 'mhjoy_wallet_register_menu', 99);

function mhjoy_wallet_register_menu()
{
    add_submenu_page(
        'woocommerce',
        'MHJoy Wallet',
        '💰 Wallet & Loyalty',
        'manage_woocommerce',
        'mhjoy-wallet',
        'mhjoy_wallet_render_dashboard'
    );
}

// ==================== MAIN DASHBOARD RENDERER ====================
function mhjoy_wallet_render_dashboard()
{
    global $wpdb;

    // Define Tables
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_codes = $wpdb->prefix . 'mhjoy_gift_codes';
    $t_spins = $wpdb->prefix . 'mhjoy_spin_history';
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';

    // Handle Actions (POST)
    $message = mhjoy_handle_admin_actions();

    // Current Tab
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';

    // Fetch Global Stats
    $total_wallet = $wpdb->get_var("SELECT SUM(balance) FROM $t_bal") ?: 0;
    $total_spins_today = $wpdb->get_var("SELECT COUNT(*) FROM $t_spins WHERE spin_date = CURDATE()") ?: 0;
    $fraud_users = $wpdb->get_var("SELECT COUNT(*) FROM $t_bal WHERE fraud_flag != 'clean'");
    $active_codes = $wpdb->get_var("SELECT COUNT(*) FROM $t_codes WHERE status = 'active'");

    ?>
    <div class="wrap mhjoy-wallet-wrap">
        <h1 class="wp-heading-inline">🏆 MHJoy Wallet System v4.5</h1>
        <p class="description">Enterprise Loyalty, Gamification & Payment Engine</p>

        <?php echo $message; ?>

        <!-- STATS CARDS -->
        <div class="mhjoy-stats-grid">
            <div class="mhjoy-card card-blue">
                <h3>Total User Funds</h3>
                <div class="number">৳<?php echo number_format($total_wallet, 2); ?></div>
                <div class="sub">Across all accounts</div>
            </div>
            <div class="mhjoy-card card-purple">
                <h3>Spins Today</h3>
                <div class="number"><?php echo number_format($total_spins_today); ?></div>
                <div class="sub">Daily Engagement</div>
            </div>
            <div class="mhjoy-card card-green">
                <h3>Active Gift Codes</h3>
                <div class="number"><?php echo number_format($active_codes); ?></div>
                <div class="sub">Ready to redeem</div>
            </div>
            <div class="mhjoy-card card-red">
                <h3>Fraud Alerts</h3>
                <div class="number"><?php echo number_format($fraud_users); ?></div>
                <div class="sub">Suspicious Accounts</div>
            </div>
        </div>

        <!-- TABS -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=mhjoy-wallet&tab=overview"
                class="nav-tab <?php echo $tab == 'overview' ? 'nav-tab-active' : ''; ?>">👥 Users & Tiers</a>
            <a href="?page=mhjoy-wallet&tab=codes" class="nav-tab <?php echo $tab == 'codes' ? 'nav-tab-active' : ''; ?>">🎁
                Gift Codes</a>
            <a href="?page=mhjoy-wallet&tab=bulk" class="nav-tab <?php echo $tab == 'bulk' ? 'nav-tab-active' : ''; ?>">📦
                Bulk Generator</a>
            <a href="?page=mhjoy-wallet&tab=spins" class="nav-tab <?php echo $tab == 'spins' ? 'nav-tab-active' : ''; ?>">🎰
                Spin History</a>
            <a href="?page=mhjoy-wallet&tab=history"
                class="nav-tab <?php echo $tab == 'history' ? 'nav-tab-active' : ''; ?>">📜 Audit Logs</a>
            <a href="?page=mhjoy-wallet&tab=partners"
                class="nav-tab <?php echo $tab == 'partners' ? 'nav-tab-active' : ''; ?>">🤝 Partner Program</a>
            <a href="?page=mhjoy-wallet&tab=analytics"
                class="nav-tab <?php echo $tab == 'analytics' ? 'nav-tab-active' : ''; ?>">📊 User Analytics</a>
            <a href="?page=mhjoy-wallet&tab=alerts"
                class="nav-tab <?php echo $tab == 'alerts' ? 'nav-tab-active' : ''; ?>">🔔 Send Alerts</a>
        </h2>

        <div class="mhjoy-tab-content">
            <?php
            switch ($tab) {
                case 'codes':
                    mhjoy_render_codes_tab();
                    break;
                case 'bulk':
                    mhjoy_render_bulk_tab();
                    break;
                case 'spins':
                    mhjoy_render_spins_tab();
                    break;
                // ADD THIS CASE BELOW
                case 'history':
                    mhjoy_render_history_tab();
                    break;
                // --- ADD THIS CASE BELOW ---
                case 'partners':
                    mhjoy_render_partners_tab();
                    break;
                // END ADDITION 
                case 'analytics':
                    mhjoy_render_analytics_tab();
                    break;
                case 'alerts':
                    mhjoy_render_alerts_tab();
                    break;
                default:
                    mhjoy_render_users_tab();
                    break; // Overview
            }
            ?>
        </div>

        <!-- MODALS -->
        <?php mhjoy_render_modals(); ?>

        <!-- CSS -->
        <style>
            .mhjoy-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin: 20px 0;
            }

            .mhjoy-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                border-left: 5px solid #ccc;
            }

            .mhjoy-card h3 {
                margin: 0 0 5px 0;
                color: #64748b;
                font-size: 13px;
                text-transform: uppercase;
            }

            .mhjoy-card .number {
                font-size: 28px;
                font-weight: bold;
                color: #1e293b;
            }

            .mhjoy-card .sub {
                font-size: 12px;
                color: #94a3b8;
            }

            .card-blue {
                border-color: #3b82f6;
            }

            .card-purple {
                border-color: #8b5cf6;
            }

            .card-green {
                border-color: #10b981;
            }

            .card-red {
                border-color: #ef4444;
            }

            .mhjoy-badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: bold;
                color: white;
            }

            .badge-platinum {
                background: #64748b;
            }

            .badge-gold {
                background: #f59e0b;
            }

            .badge-silver {
                background: #94a3b8;
            }

            .badge-bronze {
                background: #b45309;
            }

            .badge-blocked {
                background: #ef4444;
            }

            .badge-clean {
                background: #10b981;
            }

            .mhjoy-modal {
                display: none;
                position: fixed;
                z-index: 9999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
            }

            .mhjoy-modal-content {
                background: white;
                margin: 10% auto;
                padding: 30px;
                border-radius: 8px;
                width: 500px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            }
        </style>

        <!-- JS -->
        <script>
            function openUserModal(email, balance, tier, flag) {
                document.getElementById('edit_user_email').value = email;
                document.getElementById('edit_balance').value = balance;
                document.getElementById('edit_tier').value = tier;
                document.getElementById('edit_flag').value = flag;
                document.getElementById('userModal').style.display = 'block';
            }
            function closeUserModal() { document.getElementById('userModal').style.display = 'none'; }
            window.onclick = function (event) { if (event.target == document.getElementById('userModal')) closeUserModal(); }
        </script>
    </div>
    <?php
}

// ==================== TAB: USERS ====================
function mhjoy_render_users_tab()
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // Search
    $search = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
    $sql = "SELECT * FROM $t_bal";
    if ($search)
        $sql .= $wpdb->prepare(" WHERE user_email LIKE %s", '%' . $wpdb->esc_like($search) . '%');
    $sql .= " ORDER BY balance DESC LIMIT 50";

    $users = $wpdb->get_results($sql);
    ?>
    <div
        style="background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; display: flex; justify-content: space-between;">
        <form method="post">
            <input type="text" name="s" placeholder="Search email..." value="<?php echo esc_attr($search); ?>">
            <button class="button">Search</button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User Email</th>
                <th>Balance</th>
                <th>Loyalty Tier</th>
                <th>Streak</th>
                <th>Spins Total</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $tier_cls = 'badge-' . $u->loyalty_tier;
                $flag_cls = 'badge-' . $u->fraud_flag;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($u->user_email); ?></strong><br><small><?php echo $u->referral_code ? 'Ref: ' . $u->referral_code : ''; ?></small>
                    </td>
                    <td style="color: #10b981; font-weight: bold;">৳<?php echo number_format($u->balance, 2); ?></td>
                    <td><span class="mhjoy-badge <?php echo $tier_cls; ?>"><?php echo strtoupper($u->loyalty_tier); ?></span>
                    </td>
                    <td><?php echo $u->streak; ?> 🔥</td>
                    <td><?php echo $u->total_spins; ?></td>
                    <td><span class="mhjoy-badge <?php echo $flag_cls; ?>"><?php echo strtoupper($u->fraud_flag); ?></span></td>
                    <td>
                        <button class="button button-small"
                            onclick="openUserModal('<?php echo $u->user_email; ?>', <?php echo $u->balance; ?>, '<?php echo $u->loyalty_tier; ?>', '<?php echo $u->fraud_flag; ?>')">Edit</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ==================== TAB: CODES ====================
function mhjoy_render_codes_tab()
{
    global $wpdb;
    $t_codes = $wpdb->prefix . 'mhjoy_gift_codes';
    $codes = $wpdb->get_results("SELECT * FROM $t_codes ORDER BY created_at DESC LIMIT 50");
    ?>
    <div style="background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; border-left: 4px solid #3b82f6;">
        <h3 style="margin-top:0;">Create Single Code</h3>
        <form method="post" style="display: flex; gap: 10px; align-items: center;">
            <?php wp_nonce_field('mhjoy_gen_code'); ?>
            <input type="text" name="code" placeholder="JOY2025" required>
            <input type="number" name="amount" placeholder="500" step="0.01" required>
            <button type="submit" name="action_generate_code" class="button button-primary">Generate Code</button>
        </form>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Code</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Redeemed By</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($codes as $c): ?>
                <tr>
                    <td><code><?php echo esc_html($c->code); ?></code></td>
                    <td>৳<?php echo number_format($c->amount, 2); ?></td>
                    <td><?php echo $c->status == 'active' ? '<span style="color:green">Active</span>' : '<span style="color:gray">Redeemed</span>'; ?>
                    </td>
                    <td><?php echo $c->redeemed_by ?: '—'; ?></td>
                    <td><?php echo $c->created_at; ?></td>
                    <td>
                        <?php if ($c->status == 'active'): ?>
                            <form method="post" onsubmit="return confirm('Delete code?');" style="display:inline;">
                                <?php wp_nonce_field('mhjoy_del_code'); ?>
                                <input type="hidden" name="code_id" value="<?php echo $c->id; ?>">
                                <button type="submit" name="action_delete_code" class="button button-link-delete">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ==================== TAB: BULK ====================
function mhjoy_render_bulk_tab()
{
    ?>
    <div
        style="background: white; padding: 40px; max-width: 600px; margin: 20px auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h2 style="text-align: center; margin-bottom: 30px;">📦 Mass Code Generator</h2>
        <form method="post">
            <?php wp_nonce_field('mhjoy_bulk'); ?>
            <p>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Amount per Code (৳)</label>
                <input type="number" name="bulk_amount" style="width: 100%; padding: 8px;" placeholder="100.00" required>
            </p>
            <p>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Number of Codes</label>
                <input type="number" name="bulk_qty" style="width: 100%; padding: 8px;" placeholder="50" min="1" max="1000"
                    required>
            </p>
            <p>
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Prefix (Optional)</label>
                <input type="text" name="bulk_prefix" style="width: 100%; padding: 8px;" placeholder="PROMO-">
            </p>
            <hr style="margin: 20px 0;">
            <button type="submit" name="action_bulk_generate" class="button button-primary button-hero"
                style="width: 100%;">🚀 Generate Batch</button>
        </form>
    </div>
    <?php
}

// ==================== TAB: SPINS ====================
function mhjoy_render_spins_tab()
{
    global $wpdb;
    $t_spins = $wpdb->prefix . 'mhjoy_spin_history';
    $spins = $wpdb->get_results("SELECT * FROM $t_spins ORDER BY created_at DESC LIMIT 100");
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Reward</th>
                <th>Type</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($spins as $s): ?>
                <tr>
                    <td><?php echo esc_html($s->user_email); ?></td>
                    <td style="font-weight:bold; color: #d97706;">৳<?php echo number_format($s->reward_amount, 2); ?></td>
                    <td><?php echo $s->is_premium ? '🌟 PREMIUM' : 'Free Daily'; ?></td>
                    <td><?php echo $s->created_at; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// ==================== ACTION HANDLER ====================
function mhjoy_handle_admin_actions()
{
    global $wpdb;
    $msg = '';

    // 1. Single Code
    if (isset($_POST['action_generate_code']) && check_admin_referer('mhjoy_gen_code')) {
        $code = strtoupper(sanitize_text_field($_POST['code']));
        $amt = floatval($_POST['amount']);
        if ($amt > 0 && $code) {
            $wpdb->insert($wpdb->prefix . 'mhjoy_gift_codes', ['code' => $code, 'amount' => $amt]);
            $msg = '<div class="notice notice-success"><p>✅ Code Created!</p></div>';
        }
    }

    // 2. Bulk Code
    if (isset($_POST['action_bulk_generate']) && check_admin_referer('mhjoy_bulk')) {
        $qty = intval($_POST['bulk_qty']);
        $amt = floatval($_POST['bulk_amount']);
        $pfx = strtoupper(sanitize_text_field($_POST['bulk_prefix']));

        if ($qty > 0 && $amt > 0) {
            $vals = [];
            for ($i = 0; $i < $qty; $i++) {
                $c = $pfx . strtoupper(bin2hex(random_bytes(4)));
                $wpdb->insert($wpdb->prefix . 'mhjoy_gift_codes', ['code' => $c, 'amount' => $amt]);
            }
            $msg = '<div class="notice notice-success"><p>✅ Generated ' . $qty . ' codes!</p></div>';
        }
    }

    // 3. Edit User
    if (isset($_POST['action_edit_user']) && check_admin_referer('mhjoy_edit_user')) {
        $email = sanitize_email($_POST['email']);
        $bal = floatval($_POST['balance']);
        $tier = sanitize_text_field($_POST['tier']);
        $flag = sanitize_text_field($_POST['flag']);

        // Get old balance before updating
        $old_bal = $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM {$wpdb->prefix}mhjoy_wallet_balance WHERE user_email = %s",
            $email
        ));

        $wpdb->update(
            $wpdb->prefix . 'mhjoy_wallet_balance',
            ['balance' => $bal, 'loyalty_tier' => $tier, 'fraud_flag' => $flag],
            ['user_email' => $email]
        );

        // LOGGING: Only log if the balance actually changed
        if ($old_bal !== null && (float) $bal !== (float) $old_bal) {
            $diff = $bal - $old_bal;
            $type = ($diff > 0) ? 'credit' : 'debit';
            mhjoy_log_transaction($email, $type, abs($diff), 'admin', 'Manual Edit', $bal);
        }

        $msg = '<div class="notice notice-success"><p>✅ User updated and log entry created!</p></div>';
    }

    // 4. Delete Code
    if (isset($_POST['action_delete_code']) && check_admin_referer('mhjoy_del_code')) {
        $id = intval($_POST['code_id']);
        $wpdb->delete($wpdb->prefix . 'mhjoy_gift_codes', ['id' => $id]);
        $msg = '<div class="notice notice-success"><p>🗑️ Code deleted.</p></div>';
    }
    // 5. Fire New Alert
    if (isset($_POST['mhjoy_fire_alert']) && check_admin_referer('mhjoy_send_alert_action')) {
        $target = sanitize_text_field($_POST['alert_target']);
        $type = sanitize_text_field($_POST['alert_type']);
        $title = sanitize_text_field($_POST['alert_title']);
        $msg_text = sanitize_textarea_field($_POST['alert_msg']);
        $uid = ($target === 'all') ? 0 : (get_user_by('email', $target)->ID ?? null);

        if ($uid !== null) {
            mhjoy_send_notification($uid, $title, $msg_text, $type);
            $msg = '<div class="notice notice-success"><p>🚀 Alert Dispatched!</p></div>';
        } else {
            $msg = '<div class="notice notice-error"><p>❌ User not found.</p></div>';
        }
    }

    // 6. Update Existing Alert (The "Edit" Feature)
    if (isset($_POST['mhjoy_update_alert']) && check_admin_referer('mhjoy_edit_alert_action')) {
        $id = intval($_POST['notif_id']);
        $wpdb->update($wpdb->prefix . 'mhjoy_notifications', [
            'title' => sanitize_text_field($_POST['edit_title']),
            'message' => sanitize_textarea_field($_POST['edit_msg']),
            'type' => sanitize_text_field($_POST['edit_type'])
        ], ['id' => $id]);
        $msg = '<div class="notice notice-success"><p>📝 Alert updated successfully!</p></div>';
    }

    // 7. Revoke/Delete
    if (isset($_POST['mhjoy_delete_alert']) && check_admin_referer('mhjoy_del_alert_action')) {
        $wpdb->delete($wpdb->prefix . 'mhjoy_notifications', ['id' => intval($_POST['notif_id'])]);
        $msg = '<div class="notice notice-success"><p>🗑️ Alert revoked.</p></div>';
    }

    return $msg;
}



// ==================== MODALS ====================
function mhjoy_render_modals()
{
    ?>
    <div id="userModal" class="mhjoy-modal">
        <div class="mhjoy-modal-content">
            <h2>Edit User</h2>
            <form method="post">
                <?php wp_nonce_field('mhjoy_edit_user'); ?>
                <input type="hidden" name="action_edit_user" value="1">

                <label>Email (Read Only)</label>
                <input type="text" id="edit_user_email" name="email" class="widefat" readonly
                    style="background:#f0f0f1; margin-bottom:10px;">

                <label>Balance (৳)</label>
                <input type="number" id="edit_balance" name="balance" class="widefat" step="0.01"
                    style="margin-bottom:10px;">

                <label>Loyalty Tier</label>
                <select id="edit_tier" name="tier" class="widefat" style="margin-bottom:10px;">
                    <option value="bronze">Bronze</option>
                    <option value="silver">Silver</option>
                    <option value="gold">Gold</option>
                    <option value="platinum">Platinum</option>
                </select>

                <label>Fraud Status</label>
                <select id="edit_flag" name="flag" class="widefat" style="margin-bottom:20px;">
                    <option value="clean">Clean (Active)</option>
                    <option value="suspicious">Suspicious</option>
                    <option value="blocked">Blocked</option>
                </select>

                <div style="display:flex; justify-content:space-between;">
                    <button type="button" onclick="closeUserModal()" class="button">Cancel</button>
                    <button type="submit" class="button button-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// ==================== NEW HISTORY TAB ====================
function mhjoy_render_history_tab()
{
    global $wpdb;
    $table = $wpdb->prefix . 'mhjoy_wallet_transactions';

    // Search by Email
    $search = isset($_POST['ls']) ? sanitize_text_field($_POST['ls']) : '';
    $sql = "SELECT * FROM $table";
    if ($search)
        $sql .= $wpdb->prepare(" WHERE user_email LIKE %s OR reference LIKE %s", "%$search%", "%$search%");
    $sql .= " ORDER BY created_at DESC LIMIT 100";

    $logs = $wpdb->get_results($sql);
    ?>
    <div style="background:white; padding:20px; border-radius:8px; margin-top:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">📜 Global Master Ledger</h2>
            <form method="post">
                <input type="text" name="ls" placeholder="Search user email..." value="<?php echo esc_attr($search); ?>">
                <button class="button button-primary">Filter Logs</button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User Email</th> <!-- Admins see this, Users don't -->
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Source</th>
                    <th>Ref</th>
                    <th>Balance After</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l):
                    $color = ($l->type == 'credit') ? '#10b981' : '#ef4444';
                    ?>
                    <tr>
                        <td><small><?php echo $l->created_at; ?></small></td>
                        <td><strong><?php echo $l->user_email; ?></strong></td>
                        <td
                            style="color:white; background:<?php echo $color; ?>; padding:2px 6px; border-radius:4px; font-size:10px;">
                            <?php echo strtoupper($l->type); ?>
                        </td>
                        <td style="font-weight:bold;">৳<?php echo number_format($l->amount, 2); ?></td>
                        <td><?php echo strtoupper($l->source); ?></td>
                        <td><?php echo esc_html($l->reference); ?></td>
                        <td>৳<?php echo number_format($l->balance_after, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
// ==================== 🤝 PARTNER PROGRAM DASHBOARD ====================
function mhjoy_render_partners_tab()
{
    global $wpdb;
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';
    $t_logs = $wpdb->prefix . 'mhjoy_wallet_transactions';

    // 1. GLOBAL STATS
    $total_commission = $wpdb->get_var("SELECT SUM(amount) FROM $t_logs WHERE source = 'referral'") ?: 0;
    $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $t_bal WHERE referred_by IS NOT NULL AND referred_by != ''");
    $active_partners = $wpdb->get_var("SELECT COUNT(DISTINCT referred_by) FROM $t_bal WHERE referred_by != ''");

    // 2. PARTNER TIER BREAKDOWN
    $partner_stats = $wpdb->get_results("
        SELECT referred_by as partner_email, COUNT(*) as friend_count 
        FROM $t_bal 
        WHERE referred_by != '' 
        GROUP BY referred_by 
        ORDER BY friend_count DESC 
        LIMIT 10
    ");

    ?>
    <!-- Partner Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
        <div
            style="background: #fff; padding: 20px; border-left: 5px solid #8b5cf6; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h4 style="margin:0; color:#64748b;">TOTAL COMMISSIONS PAID</h4>
            <p style="font-size: 28px; font-weight: bold; margin:0; color: #8b5cf6;">
                ৳<?php echo number_format($total_commission, 2); ?></p>
            <small>Money given to referrers</small>
        </div>
        <div
            style="background: #fff; padding: 20px; border-left: 5px solid #06b6d4; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h4 style="margin:0; color:#64748b;">SUCCESSFUL REUITS</h4>
            <p style="font-size: 28px; font-weight: bold; margin:0; color: #06b6d4;">
                <?php echo number_format($total_referrals); ?>
            </p>
            <small>Users brought by friends</small>
        </div>
        <div
            style="background: #fff; padding: 20px; border-left: 5px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h4 style="margin:0; color:#64748b;">TOTAL PARTNERS</h4>
            <p style="font-size: 28px; font-weight: bold; margin:0; color: #10b981;">
                <?php echo number_format($active_partners); ?>
            </p>
            <small>Users actively referring others</small>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
        <!-- Top Partners List -->
        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h3>🏆 Top Partners (By Friend Count)</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Partner Email</th>
                        <th>Friends Joined</th>
                        <th>Current Tier</th>
                        <th>Earnings (Est)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partner_stats as $p):
                        // Determine Tier
                        $tier = 'Partner (0.5%)';
                        $color = '#64748b';
                        if ($p->friend_count >= 31) {
                            $tier = 'Joy Legend (1.5%)';
                            $color = '#8b5cf6';
                        } elseif ($p->friend_count >= 11) {
                            $tier = 'Elite Partner (1.0%)';
                            $color = '#06b6d4';
                        }

                        $earnings = $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM $t_logs WHERE user_email = %s AND source = 'referral'", $p->partner_email)) ?: 0;
                        ?>
                        <tr>
                            <td><strong><?php echo $p->partner_email; ?></strong></td>
                            <td><?php echo $p->friend_count; ?> Friends</td>
                            <td><span
                                    style="color:white; background:<?php echo $color; ?>; padding:2px 8px; border-radius:12px; font-size:10px;"><?php echo $tier; ?></span>
                            </td>
                            <td style="color:green; font-weight:bold;">৳<?php echo number_format($earnings, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Referral Payouts -->
        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h3>💸 Recent Payouts</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Partner</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $recent_payouts = $wpdb->get_results("SELECT created_at, user_email, amount FROM $t_logs WHERE source = 'referral' ORDER BY created_at DESC LIMIT 10");
                    foreach ($recent_payouts as $rp): ?>
                        <tr>
                            <td style="font-size: 11px;"><?php echo date('M d, H:i', strtotime($rp->created_at)); ?></td>
                            <td style="font-size: 11px;"><?php echo $rp->user_email; ?></td>
                            <td style="font-weight:bold; color:green;">+৳<?php echo number_format($rp->amount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
// ==================== 📊 USER ANALYTICS TAB ====================
function mhjoy_render_analytics_tab()
{
    global $wpdb;
    $t_stats = $wpdb->prefix . 'mhjoy_user_statistics';
    $t_bal = $wpdb->prefix . 'mhjoy_wallet_balance';

    // Search Logic
    $search = isset($_POST['as']) ? sanitize_text_field($_POST['as']) : '';
    $sql = "SELECT s.*, b.loyalty_tier, b.balance 
            FROM $t_stats s 
            LEFT JOIN $t_bal b ON s.user_email = b.user_email";

    if ($search) {
        $sql .= $wpdb->prepare(" WHERE s.user_email LIKE %s OR s.ip_addresses LIKE %s", "%$search%", "%$search%");
    }

    $sql .= " ORDER BY s.total_spent DESC LIMIT 100";
    $data = $wpdb->get_results($sql);

    ?>
    <div style="background:white; padding:20px; margin:20px 0; border-radius:8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;">📈 Deep User Analytics</h2>
            <form method="post">
                <input type="text" name="as" placeholder="Search by Email or IP..." value="<?php echo esc_attr($search); ?>"
                    style="width:250px;">
                <button class="button button-primary">Filter Data</button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:20%;">User Email</th>
                    <th>Total Spent</th>
                    <th>Orders</th>
                    <th>Last Purchase</th>
                    <th>Known IPs</th>
                    <th>Loyalty Tier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$data): ?>
                    <tr>
                        <td colspan="6">No analytics data found. Run the "Sync History" script if this is empty.</td>
                    </tr>
                <?php else:
                    foreach ($data as $row):
                        $ips = array_filter(explode(',', $row->ip_addresses));
                        $last_ip = end($ips) ?: '—';
                        $ip_count = count($ips);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->user_email); ?></strong></td>
                            <td style="color:#10b981; font-weight:bold;">৳<?php echo number_format($row->total_spent, 2); ?></td>
                            <td><?php echo $row->total_orders; ?> buys</td>
                            <td><small><?php echo $row->last_order_date ?: '—'; ?></small></td>
                            <td>
                                <code><?php echo esc_html($last_ip); ?></code>
                                <?php if ($ip_count > 1): ?>
                                    <span
                                        style="background:#eee; padding:2px 5px; border-radius:10px; font-size:9px;">+<?php echo $ip_count - 1; ?>
                                        more</span>
                                <?php endif; ?>
                            </td>
                            <td><span
                                    class="mhjoy-badge <?php echo $row->loyalty_tier; ?>"><?php echo strtoupper($row->loyalty_tier); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div style="margin-top:20px; padding:15px; background:#f0f9ff; border-radius:6px; color:#0369a1; font-size:12px;">
            <strong>💡 CTO Pro-Tip:</strong> This table combines WooCommerce sales history with Wallet activity. Use this to
            spot your highest-value customers (Whales) and monitor for suspicious IP switching.
        </div>
    </div>
    <?php
}
// ==================== 🔔 NOTIFICATION SENDER TAB ====================
function mhjoy_render_alerts_tab()
{
    global $wpdb;
    $table = $wpdb->prefix . 'mhjoy_notifications';

    // Advanced Stats for Management
    $total_broadcasts = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE user_id = 0");
    $personal_alerts = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE user_id > 0");

    // Search & Filter
    $search = isset($_POST['alert_search']) ? sanitize_text_field($_POST['alert_search']) : '';
    $query = "SELECT * FROM $table";
    if ($search)
        $query .= $wpdb->prepare(" WHERE title LIKE %s OR message LIKE %s", "%$search%", "%$search%");
    $query .= " ORDER BY created_at DESC LIMIT 50";
    $sent_alerts = $wpdb->get_results($query);

    ?>
    <div class="alert-management-grid" style="display: flex; gap: 25px; margin-top: 20px;">

        <!-- SIDEBAR: SENDER CONSOLE -->
        <div style="flex: 1; max-width: 400px;">
            <div
                style="background: #1e293b; color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin:0 0 20px 0; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:24px;">📡</span> Dispatch Center
                </h3>
                <form method="post">
                    <?php wp_nonce_field('mhjoy_send_alert_action'); ?>
                    <div style="margin-bottom:15px;">
                        <label
                            style="display:block; font-size:11px; text-transform:uppercase; opacity:0.7;">Recipient</label>
                        <input type="text" name="alert_target" placeholder="email or 'all'"
                            style="width:100%; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:10px; border-radius:6px;"
                            required>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.7;">Type</label>
                        <select name="alert_type"
                            style="width:100%; background:#0f172a; border:1px solid rgba(255,255,255,0.2); color:white; padding:10px; border-radius:6px;">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="error">Error (Red)</option>
                        </select>
                    </div>
                    <div style="margin-bottom:15px;">
                        <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.7;">Subject</label>
                        <input type="text" name="alert_title"
                            style="width:100%; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:10px; border-radius:6px;"
                            required>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.7;">Message</label>
                        <textarea name="alert_msg" rows="4"
                            style="width:100%; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:white; padding:10px; border-radius:6px;"
                            required></textarea>
                    </div>
                    <button type="submit" name="mhjoy_fire_alert"
                        style="width:100%; padding:12px; background:#3b82f6; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">
                        🔥 Fire Notification</button>
                </form>
            </div>
        </div>

        <!-- MAIN: THE INTELLIGENCE LOG -->
        <div style="flex: 2;">
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h3 style="margin:0;">📜 Message Management Console</h3>
                    <form method="post" style="display:flex; gap:5px;">
                        <input type="text" name="alert_search" placeholder="Search content..."
                            value="<?php echo esc_attr($search); ?>">
                        <button class="button">Search</button>
                    </form>
                </div>

                <table class="wp-list-table widefat fixed striped" style="border:none;">
                    <thead>
                        <tr>
                            <th style="width:140px;">Timestamp</th>
                            <th style="width:120px;">Audience</th>
                            <th>Alert Details</th>
                            <th style="width:100px;">Read Rate</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sent_alerts as $a):
                            $is_b = ($a->user_id == 0);
                            // Read Stats Logic
                            if ($is_b) {
                                $read_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}usermeta WHERE meta_key = '_mhjoy_read_broadcasts' AND meta_value LIKE %s", '%"' . $a->id . '"%'));
                                $total_users = count_users()['total_users'];
                                $stat_label = "$read_count / $total_users";
                            } else {
                                $stat_label = ($a->is_read) ? '✅ Read' : '⏳ Sent';
                            }
                            $type_color = ($a->type == 'error') ? '#ef4444' : (($a->type == 'success') ? '#10b981' : '#3b82f6');
                            ?>
                            <tr>
                                <td><small><?php echo date('M d, H:i', strtotime($a->created_at)); ?></small></td>
                                <td>
                                    <span
                                        style="background:<?php echo $is_b ? '#6366f1' : '#64748b'; ?>; color:white; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:bold; display:block; margin-bottom:5px; text-align:center;">
                                        <?php echo $is_b ? '📢 BROADCAST' : '👤 PRIVATE'; ?>
                                    </span>
                                    <?php if (!$is_b):
                                        $user_info = get_userdata($a->user_id);
                                        if ($user_info): ?>
                                            <div style="font-size:11px; color:#1e293b; word-break:break-all;">
                                                <?php echo esc_html($user_info->user_email); ?>
                                            </div>
                                        <?php else: ?>
                                            <small style="color:#ef4444;">User Deleted</small>
                                        <?php endif;
                                    endif; ?>
                                </td>
                                <td>
                                    <div style="border-left: 3px solid <?php echo $type_color; ?>; padding-left: 10px;">
                                        <strong><?php echo esc_html($a->title); ?></strong><br>
                                        <small><?php echo wp_trim_words($a->message, 10); ?></small>
                                    </div>
                                </td>
                                <td><small><?php echo $stat_label; ?></small></td>
                                <td>
                                    <div style="display:flex; gap:10px;">
                                        <a href="#" style="color:#3b82f6; text-decoration:none;"
                                            onclick="openEditNotif(<?php echo $a->id; ?>, '<?php echo esc_js($a->title); ?>', '<?php echo esc_js($a->message); ?>', '<?php echo $a->type; ?>'); return false;">Edit</a>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <?php wp_nonce_field('mhjoy_del_alert_action'); ?>
                                            <input type="hidden" name="notif_id" value="<?php echo $a->id; ?>">
                                            <button name="mhjoy_delete_alert" class="button-link"
                                                style="color:#ef4444;">Revoke</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editNotifModal"
        style="display:none; position:fixed; z-index:10001; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6);">
        <div style="background:white; margin:10% auto; padding:30px; width:500px; border-radius:12px;">
            <h3>✏️ Edit Active Alert</h3>
            <form method="post">
                <?php wp_nonce_field('mhjoy_edit_alert_action'); ?>
                <input type="hidden" id="edit_notif_id" name="notif_id">
                <p><label>Type</label><br>
                    <select name="edit_type" id="edit_notif_type" class="widefat">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </p>
                <p><label>Headline</label><input type="text" name="edit_title" id="edit_notif_title" class="widefat"></p>
                <p><label>Message</label><textarea name="edit_msg" id="edit_notif_msg" rows="5" class="widefat"></textarea>
                </p>
                <div style="text-align:right;">
                    <button type="button" class="button"
                        onclick="document.getElementById('editNotifModal').style.display='none'">Cancel</button>
                    <button type="submit" name="mhjoy_update_alert" class="button button-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditNotif(id, title, msg, type) {
            document.getElementById('edit_notif_id').value = id;
            document.getElementById('edit_notif_title').value = title;
            document.getElementById('edit_notif_msg').value = msg;
            document.getElementById('edit_notif_type').value = type;
            document.getElementById('editNotifModal').style.display = 'block';
        }
    </script>
    <?php
}
?>