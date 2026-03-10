<?php
/**
 * Google OAuth Authentication Handler
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class GoogleAuth {
    
    /**
     * Google OAuth URLs
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    
    /**
     * OAuth scopes
     */
    const SCOPES = ['openid', 'email', 'profile'];
    
    /**
     * State transient prefix
     */
    const STATE_PREFIX = 'bl_google_state_';
    
    /**
     * Generate OAuth authorization URL
     */
    public static function get_auth_url(string $redirect_uri = '', array $extra_params = []): ?string {
        if (!AuthSettings::is_google_configured()) {
            return null;
        }
        
        $client_id = AuthSettings::get('google_client_id');
        
        if (empty($redirect_uri)) {
            $redirect_uri = self::get_callback_url();
        }
        
        // Generate state token for CSRF protection
        $state = Security::generate_token(32);
        self::store_state($state);
        
        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
        ];
        
        $params = array_merge($params, $extra_params);
        
        return self::AUTH_URL . '?' . http_build_query($params);
    }
    
    /**
     * Get callback URL
     */
    public static function get_callback_url(): string {
        return rest_url('battle-ledger/v1/auth/google/callback');
    }
    
    /**
     * Store state token
     */
    private static function store_state(string $state): void {
        $expiry = 10 * MINUTE_IN_SECONDS;
        set_transient(self::STATE_PREFIX . $state, [
            'created' => time(),
            'ip' => Security::get_client_ip(),
        ], $expiry);
    }
    
    /**
     * Verify state token
     */
    public static function verify_state(string $state): bool {
        $data = get_transient(self::STATE_PREFIX . $state);
        
        if (!$data) {
            return false;
        }
        
        // Clean up state token
        delete_transient(self::STATE_PREFIX . $state);
        
        return true;
    }
    
    /**
     * Exchange authorization code for tokens
     */
    public static function exchange_code(string $code, string $redirect_uri = ''): array {
        if (!AuthSettings::is_google_configured()) {
            return [
                'success' => false,
                'error' => 'Google OAuth is not configured.',
            ];
        }
        
        $client_id = AuthSettings::get('google_client_id');
        $client_secret = AuthSettings::get('google_client_secret');
        
        if (empty($redirect_uri)) {
            $redirect_uri = self::get_callback_url();
        }
        
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code',
            ],
        ]);
        
        if (is_wp_error($response)) {
            Security::log_auth_event('google_token_exchange_error', 0, [
                'error' => $response->get_error_message(),
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to connect to Google. Please try again.',
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            Security::log_auth_event('google_token_exchange_error', 0, [
                'error' => $body['error'],
                'description' => $body['error_description'] ?? '',
            ]);
            
            return [
                'success' => false,
                'error' => $body['error_description'] ?? 'Failed to authenticate with Google.',
            ];
        }
        
        if (empty($body['access_token'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from Google.',
            ];
        }
        
        return [
            'success' => true,
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? null,
            'expires_in' => $body['expires_in'] ?? 3600,
            'token_type' => $body['token_type'] ?? 'Bearer',
            'id_token' => $body['id_token'] ?? null,
        ];
    }
    
    /**
     * Get user info from Google
     */
    public static function get_user_info(string $access_token): array {
        $response = wp_remote_get(self::USERINFO_URL, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);
        
        if (is_wp_error($response)) {
            Security::log_auth_event('google_userinfo_error', 0, [
                'error' => $response->get_error_message(),
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to fetch user information.',
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            Security::log_auth_event('google_userinfo_error', 0, [
                'error' => $body['error'],
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to fetch user information.',
            ];
        }
        
        if (empty($body['email'])) {
            return [
                'success' => false,
                'error' => 'Email not provided by Google.',
            ];
        }
        
        return [
            'success' => true,
            'google_id' => $body['sub'] ?? '',
            'email' => $body['email'],
            'email_verified' => $body['email_verified'] ?? false,
            'name' => $body['name'] ?? '',
            'given_name' => $body['given_name'] ?? '',
            'family_name' => $body['family_name'] ?? '',
            'picture' => $body['picture'] ?? '',
            'locale' => $body['locale'] ?? '',
        ];
    }
    
    /**
     * Refresh access token
     */
    public static function refresh_token(string $refresh_token): array {
        if (!AuthSettings::is_google_configured()) {
            return [
                'success' => false,
                'error' => 'Google OAuth is not configured.',
            ];
        }
        
        $client_id = AuthSettings::get('google_client_id');
        $client_secret = AuthSettings::get('google_client_secret');
        
        $response = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 30,
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Failed to refresh token.',
            ];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return [
                'success' => false,
                'error' => $body['error_description'] ?? 'Failed to refresh token.',
            ];
        }
        
        return [
            'success' => true,
            'access_token' => $body['access_token'],
            'expires_in' => $body['expires_in'] ?? 3600,
        ];
    }
    
    /**
     * Revoke token
     */
    public static function revoke_token(string $token): bool {
        $response = wp_remote_post(self::REVOKE_URL, [
            'timeout' => 30,
            'body' => [
                'token' => $token,
            ],
        ]);
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    /**
     * Authenticate user with Google
     * Creates new user if doesn't exist, or logs in existing user
     */
    public static function authenticate_user(array $google_user): array {
        $email = Security::sanitize_email($google_user['email']);
        
        // Check if user exists
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Update Google metadata
            update_user_meta($user->ID, '_bl_google_id', $google_user['google_id']);
            update_user_meta($user->ID, '_bl_google_picture', $google_user['picture']);
            update_user_meta($user->ID, '_bl_last_google_login', current_time('mysql'));
            
            Security::log_auth_event('google_login', $user->ID, [
                'google_id' => $google_user['google_id'],
            ]);
            
            return [
                'success' => true,
                'user_id' => $user->ID,
                'is_new' => false,
            ];
        }
        
        // Create new user
        $username = self::generate_username($google_user['email'], $google_user['name']);
        $password = wp_generate_password(24, true, true);
        
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $google_user['name'] ?: $username,
            'first_name' => $google_user['given_name'] ?? '',
            'last_name' => $google_user['family_name'] ?? '',
            'role' => 'subscriber',
        ]);
        
        if (is_wp_error($user_id)) {
            Security::log_auth_event('google_registration_error', 0, [
                'email' => $email,
                'error' => $user_id->get_error_message(),
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to create user account.',
            ];
        }
        
        // Store Google metadata
        update_user_meta($user_id, '_bl_google_id', $google_user['google_id']);
        update_user_meta($user_id, '_bl_google_picture', $google_user['picture']);
        update_user_meta($user_id, '_bl_registered_via', 'google');
        update_user_meta($user_id, '_bl_email_verified', true);
        update_user_meta($user_id, '_bl_registered_at', current_time('mysql'));
        
        Security::log_auth_event('google_registration', $user_id, [
            'google_id' => $google_user['google_id'],
        ]);
        
        // Fire action for new user registration
        do_action('battleledger_user_registered', $user_id, 'google', $google_user);
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'is_new' => true,
        ];
    }
    
    /**
     * Generate unique username from email or name
     */
    private static function generate_username(string $email, string $name = ''): string {
        // Try name first
        if (!empty($name)) {
            $base = sanitize_user(strtolower(str_replace(' ', '', $name)), true);
        } else {
            // Use email prefix
            $base = sanitize_user(strtolower(explode('@', $email)[0]), true);
        }
        
        // Ensure minimum length
        if (strlen($base) < 3) {
            $base = 'user_' . $base;
        }
        
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
}
