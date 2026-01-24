<?php
/**
 * WordPress Admin Panel
 * Provides admin interface for managing game requests, analytics, and settings
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Admin {
    
    private $analytics;
    private $license_manager;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        
        $this->analytics = new MHJoy_GR_Analytics();
        $this->license_manager = new MHJoy_GR_License_Manager();
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            'Game Requests',
            'Game Requests',
            'manage_options',
            'mhjoy-game-requests',
            [$this, 'render_dashboard_page'],
            'dashicons-games',
            30
        );
        
        add_submenu_page(
            'mhjoy-game-requests',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'mhjoy-game-requests',
            [$this, 'render_dashboard_page']
        );
        
        add_submenu_page(
            'mhjoy-game-requests',
            'All Requests',
            'All Requests',
            'manage_options',
            'mhjoy-game-requests-all',
            [$this, 'render_all_requests_page']
        );
        
        add_submenu_page(
            'mhjoy-game-requests',
            'Completed',
            'Completed',
            'manage_options',
            'mhjoy-game-requests-completed',
            [$this, 'render_completed_page']
        );

        add_submenu_page(
            'mhjoy-game-requests',
            'System Logs',
            'System Logs',
            'manage_options',
            'mhjoy-game-requests-logs',
            [$this, 'render_logs_page']
        );
        
        add_submenu_page(
            'mhjoy-game-requests',
            'License Codes',
            'License Codes',
            'manage_options',
            'mhjoy-game-requests-licenses',
            [$this, 'render_licenses_page']
        );
        
        add_submenu_page(
            'mhjoy-game-requests',
            'Settings',
            'Settings',
            'manage_options',
            'mhjoy-game-requests-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mhjoy-game-requests') === false) {
            return;
        }
        
        wp_enqueue_style('mhjoy-gr-admin', MHJOY_GR_URL . 'admin/assets/admin.css', [], MHJOY_GR_VERSION);
        wp_enqueue_script('mhjoy-gr-admin', MHJOY_GR_URL . 'admin/assets/admin.js', ['jquery'], MHJOY_GR_VERSION, true);
        
        // Chart.js for analytics
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);
        
        wp_localize_script('mhjoy-gr-admin', 'mhjoyGR', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('mhjoy/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_rawg_api_key');
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_turnstile_site_key');
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_turnstile_secret_key');
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_fingerprint_api_key');
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_rate_limit');
        register_setting('mhjoy_gr_settings', 'mhjoy_gr_cache_duration');
    }
    
    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        $stats = $this->analytics->get_dashboard_stats();
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>🎮 Game Requests Dashboard</h1>
            
            <div class="mhjoy-gr-stats-grid">
                <div class="stat-card">
                    <h3>Total Votes</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_votes']); ?></div>
                    <div class="stat-meta">
                        <span class="today">Today: <?php echo $stats['votes_today']; ?></span>
                        <span class="week">This Week: <?php echo $stats['votes_this_week']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Unique Voters</h3>
                    <div class="stat-value"><?php echo number_format($stats['unique_voters']); ?></div>
                    <div class="stat-meta">
                        <span class="regular">Regular: <?php echo $stats['regular_votes']; ?></span>
                        <span class="pro">Pro: <?php echo $stats['pro_votes']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Game Requests</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_games']); ?></div>
                    <div class="stat-meta">
                        <span class="completed">Completed: <?php echo $stats['completed_games']; ?></span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>Conversion Rate</h3>
                    <div class="stat-value"><?php echo $stats['conversion_rate']; ?>%</div>
                    <div class="stat-meta">
                        <span>Page Views: <?php echo $stats['page_views_today']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="mhjoy-gr-charts">
                <div class="chart-container">
                    <h2>Votes Over Time (Last 30 Days)</h2>
                    <canvas id="votesChart"></canvas>
                </div>
            </div>
            
            <div class="mhjoy-gr-top-games">
                <h2>🏆 Top Voted Games</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Game</th>
                            <th>Total Votes</th>
                            <th>Weighted Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_games'] as $game): ?>
                        <tr>
                            <td>
                                <?php if ($game->background_image): ?>
                                <img src="<?php echo esc_url($game->background_image); ?>" 
                                     style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; vertical-align: middle;">
                                <?php endif; ?>
                                <strong><?php echo esc_html($game->name); ?></strong>
                            </td>
                            <td><?php echo number_format($game->vote_count); ?></td>
                            <td><strong><?php echo number_format($game->weighted_score); ?></strong></td>
                            <td>
                                <button class="button button-primary fulfill-game" data-game-id="<?php echo $game->id; ?>">
                                    ✅ Mark as Fulfilled
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mhjoy-gr-top-searches">
                <h2>🔍 Most Searched Games</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Search Query</th>
                            <th>Search Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['top_searches'] as $search): ?>
                        <tr>
                            <td><?php echo esc_html($search->search_query); ?></td>
                            <td><?php echo number_format($search->search_count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Votes timeline chart
            const ctx = document.getElementById('votesChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($d) { return $d->date; }, $stats['votes_timeline'])); ?>,
                    datasets: [{
                        label: 'Votes',
                        data: <?php echo json_encode(array_map(function($d) { return $d->count; }, $stats['votes_timeline'])); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render All Requests Page
     */
    public function render_all_requests_page() {
        global $wpdb;
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        
        $games = $wpdb->get_results("
            SELECT 
                g.*,
                COUNT(v.id) as total_votes,
                SUM(CASE WHEN v.voter_type = 'regular' THEN 1 ELSE 0 END) as regular_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 1 ELSE 0 END) as pro_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table_games g
            LEFT JOIN $table_votes v ON g.id = v.game_id
            WHERE g.status = 'active'
            GROUP BY g.id
            ORDER BY weighted_score DESC
        ");
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>All Game Requests</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select id="bulk-action-selector">
                        <option value="">Bulk Actions</option>
                        <option value="fulfill">Mark as Fulfilled</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button action" id="doaction">Apply</button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="select-all"></th>
                        <th>Game</th>
                        <th>Regular Votes</th>
                        <th>Pro Votes</th>
                        <th>Weighted Score</th>
                        <th>Date Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" class="game-checkbox" value="<?php echo $game->id; ?>">
                        </th>
                        <td>
                            <?php if ($game->background_image): ?>
                            <img src="<?php echo esc_url($game->background_image); ?>" 
                                 style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; vertical-align: middle;">
                            <?php endif; ?>
                            <strong><?php echo esc_html($game->name); ?></strong>
                            <?php if ($game->release_year): ?>
                            <br><small>(<?php echo $game->release_year; ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($game->regular_votes); ?></td>
                        <td><strong style="color: #d4af37;"><?php echo number_format($game->pro_votes); ?> ⭐</strong></td>
                        <td><strong style="font-size: 16px;"><?php echo number_format($game->weighted_score); ?></strong></td>
                        <td><?php echo date('M j, Y', strtotime($game->created_at)); ?></td>
                        <td>
                            <button class="button button-primary fulfill-game" data-game-id="<?php echo $game->id; ?>">
                                ✅ Fulfill
                            </button>
                            <button class="button delete-game" data-game-id="<?php echo $game->id; ?>">
                                🗑️ Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Completed Page
     */
    public function render_completed_page() {
        global $wpdb;
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        
        $games = $wpdb->get_results("
            SELECT 
                g.*,
                COUNT(v.id) as total_votes,
                SUM(CASE WHEN v.voter_type = 'pro' THEN 5 ELSE 1 END) as weighted_score
            FROM $table_games g
            LEFT JOIN $table_votes v ON g.id = v.game_id
            WHERE g.status = 'completed'
            GROUP BY g.id
            ORDER BY g.completed_at DESC
        ");
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>✅ Completed Requests</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Game</th>
                        <th>Total Votes</th>
                        <th>Weighted Score</th>
                        <th>Completed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                    <tr>
                        <td>
                            <?php if ($game->background_image): ?>
                            <img src="<?php echo esc_url($game->background_image); ?>" 
                                 style="width: 80px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; vertical-align: middle;">
                            <?php endif; ?>
                            <strong><?php echo esc_html($game->name); ?></strong>
                        </td>
                        <td><?php echo number_format($game->total_votes); ?></td>
                        <td><strong><?php echo number_format($game->weighted_score); ?></strong></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($game->completed_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render License Codes Page
     */
    public function render_licenses_page() {
        // Handle code generation
        if (isset($_POST['generate_codes']) && check_admin_referer('mhjoy_gr_generate_codes')) {
            $count = (int) $_POST['code_count'];
            $codes = $this->license_manager->generate_codes($count);
            echo '<div class="notice notice-success"><p>Generated ' . count($codes) . ' license codes!</p></div>';
        }
        
        $codes = $this->license_manager->get_all_codes(null, 100, 0);
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>⭐ Pro License Codes</h1>
            
            <div class="mhjoy-gr-license-generator">
                <h2>Generate New Codes</h2>
                <form method="post">
                    <?php wp_nonce_field('mhjoy_gr_generate_codes'); ?>
                    <input type="number" name="code_count" min="1" max="100" value="10" style="width: 100px;">
                    <button type="submit" name="generate_codes" class="button button-primary">Generate Codes</button>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Used By</th>
                        <th>Game Voted</th>
                        <th>Dates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($codes as $code): ?>
                    <tr>
                        <td>
                            <code style="font-size: 14px; font-weight: bold; background: #eee; padding: 3px 6px; border-radius: 3px;"><?php echo esc_html($code->code); ?></code>
                        </td>
                        <td>
                            <?php if ($code->status === 'active'): ?>
                            <span class="status-active" style="color: green; font-weight: bold;">✅ Active</span>
                            <?php else: ?>
                            <span class="status-used" style="color: #666; font-weight: bold;">🔒 Used</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($code->voter_user_id) {
                                $user_info = get_userdata($code->voter_user_id);
                                if ($user_info) {
                                    echo '<div style="display:flex; align-items:center; gap:8px;">';
                                    echo get_avatar($code->voter_user_id, 24);
                                    echo '<a href="' . get_edit_user_link($code->voter_user_id) . '"><strong>' . esc_html($user_info->display_name) . '</strong></a>';
                                    echo '</div>';
                                } else {
                                    echo 'User ID: ' . $code->voter_user_id;
                                }
                            } elseif ($code->voter_name) {
                                echo '<span style="color: #666;">Guest:</span> <strong>' . esc_html($code->voter_name) . '</strong>';
                            } else {
                                echo '<span style="color: #ccc;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($code->game_name): ?>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <?php if ($code->game_image): ?>
                                    <img src="<?php echo esc_url($code->game_image); ?>" style="width: 40px; height: 25px; object-fit: cover; border-radius: 3px;">
                                    <?php endif; ?>
                                    <span><?php echo esc_html($code->game_name); ?></span>
                                </div>
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 11px;">
                                <div>Created: <?php echo date('M j', strtotime($code->created_at)); ?></div>
                                <?php if ($code->used_at): ?>
                                <div style="color: #666;">Used: <?php echo date('M j, Y', strtotime($code->used_at)); ?></div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render System Logs Page
     */
    /**
     * Render System Logs Page
     */
    /**
     * Render System Logs Page
     */
    public function render_logs_page() {
        // Handle Ban/Unban Actions
        if (isset($_GET['action']) && check_admin_referer('mhjoy_gr_ban_user')) {
            $ip = isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : '';
            $fp = isset($_GET['fp']) ? sanitize_text_field($_GET['fp']) : '';
            $uid = isset($_GET['uid']) ? intval($_GET['uid']) : null;
            
            $security = new MHJoy_GR_Security();
            
            if ($_GET['action'] === 'ban') {
                $security->ban_user($ip, $fp, $uid);
                echo '<div class="notice notice-error is-dismissible"><p><strong>🛑 User has been PERMANENTLY BANNED on all layers.</strong></p></div>';
            } elseif ($_GET['action'] === 'unban') {
                $security->unban_user($ip, $fp, $uid);
                echo '<div class="notice notice-success is-dismissible"><p><strong>✅ User has been UNBANNED.</strong></p></div>';
            }
        }

        $logs = $this->analytics->get_activity_logs(100);
        $security = new MHJoy_GR_Security(); // For checking status
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>📜 System Activity Logs</h1>
            <p class="description">Real-time audit log of all searches, votes, and views.</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">Type</th>
                        <th>User / Guest</th>
                        <th>Activity</th>
                        <th>Details</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <?php 
                        $is_banned = $security->is_banned($log->ip_address, $log->fingerprint, $log->user_id);
                        $row_style = $is_banned ? 'background-color: #ffeeee;' : '';
                    ?>
                    <tr style="<?php echo $row_style; ?>">
                        <td style="font-size: 1.5em; text-align: center;">
                            <?php 
                            switch($log->event_type) {
                                case 'vote': echo '🗳️'; break;
                                case 'search': echo '🔍'; break;
                                case 'game_view': echo '👀'; break;
                                case 'page_view': echo '📄'; break;
                                default: echo '❓';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($log->user_id) {
                                $user_info = get_userdata($log->user_id);
                                if ($user_info) {
                                    echo '<div style="display:flex; align-items:center; gap:8px;">';
                                    echo get_avatar($log->user_id, 24);
                                    echo '<a href="' . get_edit_user_link($log->user_id) . '"><strong>' . esc_html($user_info->display_name) . '</strong></a>';
                                    echo '</div>';
                                } else {
                                    echo 'User ID: ' . $log->user_id;
                                }
                            } else {
                                echo '<span style="color: #666;">Guest</span>';
                                if ($log->ip_address) {
                                    echo '<br><small style="color: #999;">' . esc_html($log->ip_address) . '</small>';
                                }
                            }
                            ?>
                            <?php if ($is_banned): ?>
                                <span style="color:red; font-weight:bold; font-size:10px; border:1px solid red; padding:2px 4px; border-radius:3px;">BANNED</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($log->event_type === 'search') {
                                echo 'Searched for: <strong>' . esc_html($log->search_query) . '</strong>';
                            } elseif ($log->game_name) {
                                $action = $log->event_type === 'vote' ? 'Voted for' : 'Viewed';
                                echo $action . ': <strong>' . esc_html($log->game_name) . '</strong>';
                            } else {
                                echo ucfirst(str_replace('_', ' ', $log->event_type));
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($log->game_image): ?>
                            <img src="<?php echo esc_url($log->game_image); ?>" 
                                 style="width: 50px; height: 30px; object-fit: cover; border-radius: 4px;">
                            <?php elseif ($log->country_code): ?>
                                <span class="dashicons dashicons-globe"></span> <?php echo $log->country_code; ?>
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ago'; ?>
                            <br><small style="color: #999;"><?php echo date('M j, H:i', strtotime($log->created_at)); ?></small>
                        </td>
                        <td>
                            <?php if (!$is_banned): ?>
                                <?php 
                                $ban_url = wp_nonce_url(admin_url('admin.php?page=mhjoy-game-requests-logs&action=ban&ip=' . urlencode($log->ip_address) . '&fp=' . urlencode($log->fingerprint) . '&uid=' . $log->user_id), 'mhjoy_gr_ban_user');
                                ?>
                                <a href="<?php echo $ban_url; ?>" onclick="return confirm('Are you sure you want to PERMANENTLY BAN this user? This will block their IP and Device ID.');" class="button button-small button-link-delete" style="color: red; border-color: red;">
                                    🛑 Ban
                                </a>
                            <?php else: ?>
                                <?php 
                                $unban_url = wp_nonce_url(admin_url('admin.php?page=mhjoy-game-requests-logs&action=unban&ip=' . urlencode($log->ip_address) . '&fp=' . urlencode($log->fingerprint) . '&uid=' . $log->user_id), 'mhjoy_gr_ban_user');
                                ?>
                                <a href="<?php echo $unban_url; ?>" onclick="return confirm('Use with caution: This will restore access for this user.');" class="button button-small" style="color: green; border-color: green;">
                                    ✅ Unban
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap mhjoy-gr-admin">
            <h1>⚙️ Game Request Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('mhjoy_gr_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Rawg.io API Key</th>
                        <td>
                            <input type="text" name="mhjoy_gr_rawg_api_key" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_rawg_api_key')); ?>" 
                                   class="regular-text" placeholder="Enter your Rawg API key">
                            <p class="description">Get your API key from <a href="https://rawg.io/apidocs" target="_blank">rawg.io/apidocs</a></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Turnstile Site Key</th>
                        <td>
                            <input type="text" name="mhjoy_gr_turnstile_site_key" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_turnstile_site_key')); ?>" 
                                   class="regular-text" placeholder="Cloudflare Turnstile site key">
                            <p class="description">Frontend key for Cloudflare Turnstile</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Turnstile Secret Key</th>
                        <td>
                            <input type="password" name="mhjoy_gr_turnstile_secret_key" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_turnstile_secret_key')); ?>" 
                                   class="regular-text" placeholder="Cloudflare Turnstile secret key">
                            <p class="description">Backend secret for Cloudflare Turnstile validation</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">FingerprintJS API Key (Optional)</th>
                        <td>
                            <input type="text" name="mhjoy_gr_fingerprint_api_key" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_fingerprint_api_key')); ?>" 
                                   class="regular-text" placeholder="FingerprintJS API key">
                            <p class="description">Leave empty to use open-source version</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Rate Limit (votes/hour)</th>
                        <td>
                            <input type="number" name="mhjoy_gr_rate_limit" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_rate_limit', 10)); ?>" 
                                   min="1" max="100" class="small-text">
                            <p class="description">Maximum votes per hour for guest users</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Cache Duration (seconds)</th>
                        <td>
                            <input type="number" name="mhjoy_gr_cache_duration" 
                                   value="<?php echo esc_attr(get_option('mhjoy_gr_cache_duration', 3600)); ?>" 
                                   min="300" max="86400" class="small-text">
                            <p class="description">How long to cache Rawg API responses (default: 3600 = 1 hour)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
