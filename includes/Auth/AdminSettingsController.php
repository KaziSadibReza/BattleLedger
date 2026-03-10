<?php
/**
 * Authentication Admin Settings REST API
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class AdminSettingsController {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'battle-ledger/v1';
    
    /**
     * Register REST API routes
     */
    public static function register_routes(): void {
        // Get auth settings
        register_rest_route(self::NAMESPACE, '/settings/auth', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_settings'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);
        
        // Update auth settings
        register_rest_route(self::NAMESPACE, '/settings/auth', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_settings'],
            'permission_callback' => [self::class, 'check_admin_permission'],
            'args' => [
                'settings' => [
                    'required' => true,
                    'type' => 'object',
                ],
            ],
        ]);
    }
    
    /**
     * Check admin permission
     */
    public static function check_admin_permission(): bool {
        return current_user_can('manage_options');
    }
    
    /**
     * Get settings endpoint
     */
    public static function get_settings(\WP_REST_Request $request): \WP_REST_Response {
        $settings = AuthSettings::get_all();
        
        // Mask secret for security
        if (!empty($settings['google_client_secret'])) {
            $secret = $settings['google_client_secret'];
            if (strlen($secret) > 8) {
                $settings['google_client_secret'] = substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
            }
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'settings' => $settings,
        ], 200);
    }
    
    /**
     * Update settings endpoint
     */
    public static function update_settings(\WP_REST_Request $request): \WP_REST_Response {
        $new_settings = $request->get_param('settings');
        
        if (!is_array($new_settings)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid settings format.',
            ], 400);
        }
        
        // Get current settings to preserve masked secret
        $current_settings = AuthSettings::get_all();
        
        // If secret is masked (contains asterisks), keep the current one
        if (isset($new_settings['google_client_secret']) && strpos($new_settings['google_client_secret'], '*') !== false) {
            $new_settings['google_client_secret'] = $current_settings['google_client_secret'];
        }
        
        // Update settings
        $result = AuthSettings::update($new_settings);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to save settings.',
            ], 500);
        }
        
        Security::log_auth_event('settings_updated', get_current_user_id(), [
            'changed_keys' => array_keys($new_settings),
        ]);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings saved successfully.',
        ], 200);
    }
}
