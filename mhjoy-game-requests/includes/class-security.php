<?php
/**
 * Security Handler
 * Manages Cloudflare Turnstile validation, rate limiting, and anti-abuse measures
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_Security {
    
    private $turnstile_secret;
    private $rate_limit;
    
    public function __construct() {
        $this->turnstile_secret = get_option('mhjoy_gr_turnstile_secret_key', '');
        $this->rate_limit = get_option('mhjoy_gr_rate_limit', 10);
    }
    
    /**
     * Validate Cloudflare Turnstile token
     */
    public function validate_turnstile($token, $ip_address) {
        if (empty($this->turnstile_secret)) {
            // If Turnstile not configured, skip validation (dev mode)
            error_log('⚠️ Turnstile not configured - skipping validation');
            return true;
        }
        
        if (empty($token)) {
            return new WP_Error('missing_token', 'Turnstile token is required', ['status' => 400]);
        }
        
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'body' => [
                'secret' => $this->turnstile_secret,
                'response' => $token,
                'remoteip' => $ip_address
            ],
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            error_log('Turnstile validation error: ' . $response->get_error_message());
            return new WP_Error('turnstile_error', 'Failed to validate Turnstile token', ['status' => 500]);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['success']) || !$body['success']) {
            $error_codes = isset($body['error-codes']) ? implode(', ', $body['error-codes']) : 'unknown';
            error_log('Turnstile validation failed: ' . $error_codes);
            return new WP_Error('turnstile_failed', 'Turnstile validation failed', ['status' => 403]);
        }
        
        return true;
    }
    
    /**
     * Check rate limit for voting
     * Prevents spam by limiting votes per hour
     */
    /**
     * Check rate limit and BAN status
     */
    public function check_rate_limit($fingerprint, $ip_address, $user_id = null) {
        // First, check if permanently banned
        if ($this->is_banned($ip_address, $fingerprint, $user_id)) {
            return new WP_Error('banned', 'You have been permanently banned from voting due to abuse.', ['status' => 403]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_game_votes';
        
        // Check votes in last hour
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $vote_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table
            WHERE (fingerprint = %s OR ip_address = %s)
            AND voted_at > %s
        ", $fingerprint, $ip_address, $one_hour_ago));
        
        if ($vote_count >= $this->rate_limit) {
            return new WP_Error('rate_limit_exceeded', 
                sprintf('Rate limit exceeded. Maximum %d votes per hour allowed.', $this->rate_limit), 
                ['status' => 429]
            );
        }
        
        return true;
    }
    
    /**
     * Check if user is banned
     */
    public function is_banned($ip, $fingerprint, $user_id = null) {
        $banned_ips = get_option('mhjoy_gr_banned_ips', []);
        $banned_fps = get_option('mhjoy_gr_banned_fingerprints', []);
        $banned_users = get_option('mhjoy_gr_banned_users', []);
        
        if (in_array($ip, $banned_ips)) return true;
        if (in_array($fingerprint, $banned_fps)) return true;
        if ($user_id && in_array($user_id, $banned_users)) return true;
        
        return false;
    }
    
    /**
     * PERMANENTLY BAN A USER
     */
    public function ban_user($ip, $fingerprint, $user_id = null) {
        if ($ip) {
            $ips = get_option('mhjoy_gr_banned_ips', []);
            if (!in_array($ip, $ips)) {
                $ips[] = $ip;
                update_option('mhjoy_gr_banned_ips', $ips);
            }
        }
        
        if ($fingerprint) {
            $fps = get_option('mhjoy_gr_banned_fingerprints', []);
            if (!in_array($fingerprint, $fps)) {
                $fps[] = $fingerprint;
                update_option('mhjoy_gr_banned_fingerprints', $fps);
            }
        }
        
        if ($user_id) {
            $users = get_option('mhjoy_gr_banned_users', []);
            if (!in_array($user_id, $users)) {
                $users[] = $user_id;
                update_option('mhjoy_gr_banned_users', $users);
            }
        }
        
        return true;
    }
    
    /**
     * UNBAN A USER
     */
    public function unban_user($ip, $fingerprint, $user_id = null) {
        if ($ip) {
            $ips = get_option('mhjoy_gr_banned_ips', []);
            $ips = array_diff($ips, [$ip]);
            update_option('mhjoy_gr_banned_ips', array_values($ips));
        }
        
        if ($fingerprint) {
            $fps = get_option('mhjoy_gr_banned_fingerprints', []);
            $fps = array_diff($fps, [$fingerprint]);
            update_option('mhjoy_gr_banned_fingerprints', array_values($fps));
        }
        
        if ($user_id) {
            $users = get_option('mhjoy_gr_banned_users', []);
            $users = array_diff($users, [$user_id]);
            update_option('mhjoy_gr_banned_users', array_values($users));
        }
        
        return true;
    }
    
    /**
     * Sanitize voter name
     */
    public function sanitize_voter_name($name) {
        // Remove HTML tags
        $name = strip_tags($name);
        
        // Remove special characters except spaces, letters, numbers
        $name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        
        // Trim and limit length
        $name = trim(substr($name, 0, 100));
        
        // Default if empty
        if (empty($name)) {
            $name = 'Anonymous';
        }
        
        return $name;
    }
    
    /**
     * Hash fingerprint for privacy
     */
    public function hash_fingerprint($fingerprint) {
        return hash('sha256', $fingerprint . wp_salt());
    }
    
    /**
     * Get client IP address
     */
    public function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Get user agent
     */
    public function get_user_agent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
    }
}
