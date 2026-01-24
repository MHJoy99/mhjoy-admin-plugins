<?php
/**
 * License Code Manager
 * Handles generation and validation of Pro voter license codes
 */

if (!defined('ABSPATH')) exit;

class MHJoy_GR_License_Manager {
    
    /**
     * Generate license codes in bulk
     */
    public function generate_codes($count = 1) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        $generated = [];
        
        for ($i = 0; $i < $count; $i++) {
            $code = $this->generate_unique_code();
            
            $inserted = $wpdb->insert($table, [
                'code' => $code,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ]);
            
            if ($inserted) {
                $generated[] = $code;
            }
        }
        
        return $generated;
    }
    
    /**
     * Generate a unique license code
     */
    private function generate_unique_code() {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        
        do {
            // Format: MHJOY-XXXX-XXXX-XXXX
            $part1 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $part2 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $part3 = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            $code = "MHJOY-{$part1}-{$part2}-{$part3}";
            
            // Check if code already exists
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE code = %s", $code));
        } while ($exists > 0);
        
        return $code;
    }
    
    /**
     * Validate a license code
     */
    public function validate_code($code) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        
        $license = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table WHERE code = %s
        ", strtoupper(trim($code))));
        
        if (!$license) {
            return new WP_Error('invalid_code', 'License code not found', ['status' => 404]);
        }
        
        if ($license->status !== 'active') {
            return new WP_Error('code_used', 'License code has already been used', ['status' => 400]);
        }
        
        return true;
    }
    
    /**
     * Bind license code to a user (mark as used)
     */
    public function bind_code_to_user($code, $user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        
        $updated = $wpdb->update($table, [
            'status' => 'used',
            'user_id' => $user_id,
            'used_at' => current_time('mysql')
        ], [
            'code' => strtoupper(trim($code))
        ]);
        
        return $updated !== false;
    }
    
    /**
     * Check if user has an active pro license
     */
    public function get_user_license($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        
        $license = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE user_id = %d AND status = 'used'
            ORDER BY used_at DESC
            LIMIT 1
        ", $user_id));
        
        return $license;
    }
    
    /**
     * Get all license codes with stats and usage details
     */
    public function get_all_codes($status = null, $limit = 100, $offset = 0) {
        global $wpdb;
        $table_licenses = $wpdb->prefix . 'mhjoy_license_codes';
        $table_votes = $wpdb->prefix . 'mhjoy_game_votes';
        $table_games = $wpdb->prefix . 'mhjoy_request_games';
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE l.status = %s", $status);
        }
        
        $codes = $wpdb->get_results($wpdb->prepare("
            SELECT 
                l.*,
                v.voter_name,
                v.user_id as voter_user_id,
                g.name as game_name,
                g.background_image as game_image
            FROM $table_licenses l
            LEFT JOIN $table_votes v ON l.code = v.license_code
            LEFT JOIN $table_games g ON v.game_id = g.id
            $where
            ORDER BY l.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
        
        return $codes;
    }
    
    /**
     * Delete expired or unused codes
     */
    public function cleanup_codes($days_old = 90) {
        global $wpdb;
        $table = $wpdb->prefix . 'mhjoy_license_codes';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM $table 
            WHERE status = 'active' AND created_at < %s
        ", $cutoff_date));
        
        return $deleted;
    }
}
