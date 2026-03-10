<?php
/**
 * Security Handler for Authentication
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class Security {
    
    /**
     * Rate limit transient prefix
     */
    const RATE_LIMIT_PREFIX = 'bl_rate_limit_';
    
    /**
     * OTP transient prefix
     */
    const OTP_PREFIX = 'bl_otp_';
    
    /**
     * Nonce action for auth forms (must be 'wp_rest' for REST API compatibility)
     */
    const NONCE_ACTION = 'wp_rest';
    
    /**
     * Generate secure random token
     */
    public static function generate_token(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate OTP code
     */
    public static function generate_otp(): string {
        $length = AuthSettings::get('otp_length', 6);
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
    
    /**
     * Hash OTP for storage
     */
    public static function hash_otp(string $otp): string {
        return wp_hash_password($otp);
    }
    
    /**
     * Verify OTP
     */
    public static function verify_otp(string $otp, string $hash): bool {
        return wp_check_password($otp, $hash);
    }
    
    /**
     * Store OTP for email
     */
    public static function store_otp(string $email, string $otp): bool {
        $email_hash = md5(strtolower(trim($email)));
        $expiry = AuthSettings::get('otp_expiry_minutes', 10) * MINUTE_IN_SECONDS;
        
        $data = [
            'hash' => self::hash_otp($otp),
            'attempts' => 0,
            'created' => time(),
        ];
        
        return set_transient(self::OTP_PREFIX . $email_hash, $data, $expiry);
    }
    
    /**
     * Get stored OTP data
     */
    public static function get_otp_data(string $email): ?array {
        $email_hash = md5(strtolower(trim($email)));
        $data = get_transient(self::OTP_PREFIX . $email_hash);
        return $data ?: null;
    }
    
    /**
     * Validate OTP for email
     * 
     * @param bool $consume If true, delete OTP after validation. If false, mark as verified for later use.
     */
    public static function validate_otp(string $email, string $otp, bool $consume = false): array {
        $email_hash = md5(strtolower(trim($email)));
        $data = get_transient(self::OTP_PREFIX . $email_hash);
        
        if (!$data) {
            return [
                'valid' => false,
                'error' => 'OTP has expired. Please request a new code.',
            ];
        }
        
        // If already verified (for registration flow), just confirm it's valid
        if (!empty($data['verified'])) {
            if ($consume) {
                delete_transient(self::OTP_PREFIX . $email_hash);
            }
            return [
                'valid' => true,
                'error' => null,
            ];
        }
        
        $max_attempts = AuthSettings::get('otp_max_attempts', 3);
        
        if ($data['attempts'] >= $max_attempts) {
            delete_transient(self::OTP_PREFIX . $email_hash);
            return [
                'valid' => false,
                'error' => 'Too many failed attempts. Please request a new code.',
            ];
        }
        
        if (self::verify_otp($otp, $data['hash'])) {
            if ($consume) {
                // Delete OTP after consumption (e.g., login)
                delete_transient(self::OTP_PREFIX . $email_hash);
            } else {
                // Mark as verified but keep for registration
                $data['verified'] = true;
                $expiry = AuthSettings::get('otp_expiry_minutes', 10) * MINUTE_IN_SECONDS;
                $remaining = $expiry - (time() - $data['created']);
                set_transient(self::OTP_PREFIX . $email_hash, $data, max(1, $remaining));
            }
            return [
                'valid' => true,
                'error' => null,
            ];
        }
        
        // Increment attempts
        $data['attempts']++;
        $expiry = AuthSettings::get('otp_expiry_minutes', 10) * MINUTE_IN_SECONDS;
        $remaining = $expiry - (time() - $data['created']);
        set_transient(self::OTP_PREFIX . $email_hash, $data, max(1, $remaining));
        
        $remaining_attempts = $max_attempts - $data['attempts'];
        
        return [
            'valid' => false,
            'error' => sprintf('Invalid code. %d attempt(s) remaining.', $remaining_attempts),
        ];
    }
    
    /**
     * Delete OTP for email
     */
    public static function delete_otp(string $email): bool {
        $email_hash = md5(strtolower(trim($email)));
        return delete_transient(self::OTP_PREFIX . $email_hash);
    }
    
    /**
     * Check rate limit
     */
    public static function check_rate_limit(string $identifier, string $action = 'general'): array {
        $key = self::RATE_LIMIT_PREFIX . $action . '_' . md5($identifier);
        $data = get_transient($key);
        
        $max_attempts = AuthSettings::get('rate_limit_attempts', 5);
        $window = AuthSettings::get('rate_limit_window', 15) * MINUTE_IN_SECONDS;
        
        if (!$data) {
            return [
                'allowed' => true,
                'remaining' => $max_attempts - 1,
                'reset_time' => 0,
            ];
        }
        
        if ($data['attempts'] >= $max_attempts) {
            $reset_time = $data['first_attempt'] + $window - time();
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => max(0, $reset_time),
            ];
        }
        
        return [
            'allowed' => true,
            'remaining' => $max_attempts - $data['attempts'] - 1,
            'reset_time' => 0,
        ];
    }
    
    /**
     * Record rate limit attempt
     */
    public static function record_rate_limit(string $identifier, string $action = 'general'): void {
        $key = self::RATE_LIMIT_PREFIX . $action . '_' . md5($identifier);
        $data = get_transient($key);
        $window = AuthSettings::get('rate_limit_window', 15) * MINUTE_IN_SECONDS;
        
        if (!$data) {
            $data = [
                'attempts' => 1,
                'first_attempt' => time(),
            ];
        } else {
            $data['attempts']++;
        }
        
        $remaining = $window - (time() - $data['first_attempt']);
        set_transient($key, $data, max(1, $remaining));
    }
    
    /**
     * Clear rate limit
     */
    public static function clear_rate_limit(string $identifier, string $action = 'general'): void {
        $key = self::RATE_LIMIT_PREFIX . $action . '_' . md5($identifier);
        delete_transient($key);
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip(): string {
        // Only use REMOTE_ADDR by default — forwarded headers are trivially spoofable
        // and would allow attackers to bypass all rate limiting.
        $ip = !empty($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Verify nonce
     */
    public static function verify_nonce(string $nonce): bool {
        return wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
    }
    
    /**
     * Create nonce
     */
    public static function create_nonce(): string {
        return wp_create_nonce(self::NONCE_ACTION);
    }
    
    /**
     * Sanitize email
     */
    public static function sanitize_email(string $email): string {
        return sanitize_email(strtolower(trim($email)));
    }
    
    /**
     * Validate email format
     */
    public static function validate_email(string $email): bool {
        return is_email($email) !== false;
    }
    
    /**
     * Validate password strength
     */
    public static function validate_password(string $password): array {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Log authentication event
     */
    public static function log_auth_event(string $event, int $user_id = 0, array $data = []): void {
        $log_data = [
            'event' => $event,
            'user_id' => $user_id,
            'ip' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) 
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) 
                : '',
            'timestamp' => current_time('mysql'),
            'data' => $data,
        ];
        
        // Fire action for external logging
        do_action('battleledger_auth_event', $log_data);
        
        // Optional: Store in database or log file
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BattleLedger Auth: ' . wp_json_encode($log_data));
        }
    }
}
