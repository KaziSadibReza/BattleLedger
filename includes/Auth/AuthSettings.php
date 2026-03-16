<?php
/**
 * Authentication Settings Handler
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class AuthSettings {
    
    /**
     * Option name for auth settings
     */
    const OPTION_NAME = 'battleledger_auth_settings';
    
    /**
     * Default settings
     */
    private static $defaults = [
        // Google OAuth
        'google_enabled' => false,
        'google_client_id' => '',
        'google_client_secret' => '',
        'google_redirect_uri' => '',
        
        // Email OTP
        'otp_enabled' => true,
        'otp_expiry_minutes' => 10,
        'otp_length' => 6,
        'otp_max_attempts' => 3,
        
        // Email Templates
        'otp_email_subject' => 'Your Verification Code - BattleLedger',
        'otp_email_template' => '',
        
        // Security
        'rate_limit_attempts' => 5,
        'rate_limit_window' => 15, // minutes
        'session_duration' => 14, // days
        
        // UI Settings
        'enable_google_one_tap' => false,
        'show_password_strength' => true,
        'require_terms_acceptance' => false,
        'terms_page_id' => 0,
        'privacy_page_id' => 0,
    ];
    
    /**
     * Get all settings
     */
    public static function get_all(): array {
        $settings = get_option(self::OPTION_NAME, []);
        return wp_parse_args($settings, self::$defaults);
    }
    
    /**
     * Get a specific setting
     */
    public static function get(string $key, $default = null) {
        $settings = self::get_all();
        return $settings[$key] ?? $default ?? (self::$defaults[$key] ?? null);
    }
    
    /**
     * Update settings
     */
    public static function update(array $settings): bool {
        $current = self::get_all();
        $sanitized = self::sanitize_settings($settings);
        $merged = wp_parse_args($sanitized, $current);
        
        return update_option(self::OPTION_NAME, $merged);
    }
    
    /**
     * Update a single setting
     */
    public static function set(string $key, $value): bool {
        $settings = self::get_all();
        $settings[$key] = $value;
        return update_option(self::OPTION_NAME, $settings);
    }
    
    /**
     * Sanitize settings
     */
    private static function sanitize_settings(array $settings): array {
        $sanitized = [];
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                // Boolean settings
                case 'google_enabled':
                case 'otp_enabled':
                case 'enable_google_one_tap':
                case 'show_password_strength':
                case 'require_terms_acceptance':
                    $sanitized[$key] = (bool) $value;
                    break;
                
                // Integer settings
                case 'otp_expiry_minutes':
                case 'otp_length':
                case 'otp_max_attempts':
                case 'rate_limit_attempts':
                case 'rate_limit_window':
                case 'session_duration':
                case 'terms_page_id':
                case 'privacy_page_id':
                    $sanitized[$key] = absint($value);
                    break;
                
                // URL settings
                case 'google_redirect_uri':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                
                // Text settings (sanitize)
                case 'google_client_id':
                case 'google_client_secret':
                case 'otp_email_subject':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                
                // HTML content (kses)
                case 'otp_email_template':
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if Google Auth is properly configured
     */
    public static function is_google_configured(): bool {
        return !empty(self::get('google_client_id'))
            && !empty(self::get('google_client_secret'));
    }
    
    /**
     * Check if OTP is enabled
     */
    public static function is_otp_enabled(): bool {
        return (bool) self::get('otp_enabled');
    }
    
    /**
     * Get Google OAuth redirect URI
     */
    public static function get_google_redirect_uri(): string {
        $custom = self::get('google_redirect_uri');
        if (!empty($custom)) {
            return $custom;
        }
        return home_url('/wp-json/battle-ledger/v1/auth/google/callback');
    }
    
    /**
     * Reset to defaults
     */
    public static function reset(): bool {
        return update_option(self::OPTION_NAME, self::$defaults);
    }
}
