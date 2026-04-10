<?php
/**
 * Authentication REST API Controller
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class AuthController {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'battle-ledger/v1';
    
    /**
     * Register REST API routes
     */
    public static function register_routes(): void {
        // Send OTP
        register_rest_route(self::NAMESPACE, '/auth/otp/send', [
            'methods' => 'POST',
            'callback' => [self::class, 'send_otp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => 'verification',
                    'enum' => ['verification', 'login', 'password_reset'],
                ],
            ],
        ]);
        
        // Verify OTP
        register_rest_route(self::NAMESPACE, '/auth/otp/verify', [
            'methods' => 'POST',
            'callback' => [self::class, 'verify_otp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'otp' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Register with OTP
        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods' => 'POST',
            'callback' => [self::class, 'register'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'otp' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'display_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // OTP Login
        register_rest_route(self::NAMESPACE, '/auth/otp/login', [
            'methods' => 'POST',
            'callback' => [self::class, 'otp_login'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'otp' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        
        // Email/Password Login
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods' => 'POST',
            'callback' => [self::class, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                ],
                'remember' => [
                    'required' => false,
                    'type' => 'boolean',
                    'default' => false,
                ],
            ],
        ]);
        
        // Logout
        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods' => 'POST',
            'callback' => [self::class, 'logout'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Get current user
        register_rest_route(self::NAMESPACE, '/auth/me', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_current_user'],
            'permission_callback' => '__return_true',
        ]);
        
        // Google OAuth URL
        register_rest_route(self::NAMESPACE, '/auth/google/url', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_google_auth_url'],
            'permission_callback' => '__return_true',
        ]);
        
        // Google OAuth callback
        register_rest_route(self::NAMESPACE, '/auth/google/callback', [
            'methods' => 'GET',
            'callback' => [self::class, 'google_callback'],
            'permission_callback' => '__return_true',
        ]);
        
        // Check email exists
        register_rest_route(self::NAMESPACE, '/auth/check-email', [
            'methods' => 'POST',
            'callback' => [self::class, 'check_email'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);
        
        // Update profile
        register_rest_route(self::NAMESPACE, '/auth/profile', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_profile'],
            'permission_callback' => 'is_user_logged_in',
            'args' => [
                'display_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'first_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'last_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }
    
    /**
     * Send OTP endpoint
     */
    public static function send_otp(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        $type = $request->get_param('type');
        
        $result = OTPManager::send_otp($email, $type);
        
        if (!$result['success']) {
            $status = 400;
            if (!empty($result['error_code']) && in_array($result['error_code'], ['rate_limited', 'resend_cooldown'], true)) {
                $status = 429;
            }

            $payload = [
                'success' => false,
                'message' => $result['error'],
            ];

            if (!empty($result['error_code'])) {
                $payload['error_code'] = $result['error_code'];
            }
            if (isset($result['retry_after'])) {
                $payload['retry_after'] = (int) $result['retry_after'];
            }

            return new \WP_REST_Response($payload, $status);
        }
        
        $payload = [
            'success' => true,
            'message' => $result['message'],
            'expires_in' => $result['expires_in'],
        ];

        if (isset($result['resend_cooldown'])) {
            $payload['resend_cooldown'] = (int) $result['resend_cooldown'];
        }

        return new \WP_REST_Response($payload, 200);
    }
    
    /**
     * Verify OTP endpoint
     */
    public static function verify_otp(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        $otp = $request->get_param('otp');
        
        $result = OTPManager::verify_otp($email, $otp);
        
        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }
    
    /**
     * Register endpoint
     */
    public static function register(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        $otp = $request->get_param('otp');
        $password = $request->get_param('password');
        $display_name = $request->get_param('display_name');
        
        // Verify OTP and consume it (one-time use for registration)
        $verify = OTPManager::verify_otp($email, $otp, true);
        
        if (!$verify['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $verify['error'],
            ], 400);
        }
        
        // Check if user exists
        if (email_exists($email)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'An account with this email already exists.',
            ], 400);
        }
        
        // Validate password
        $password_check = Security::validate_password($password);
        if (!$password_check['valid']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $password_check['errors'][0],
                'errors' => $password_check['errors'],
            ], 400);
        }
        
        // Generate username
        $username = self::generate_username($email, $display_name);
        
        // Create user
        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $display_name ?: $username,
            'role' => 'subscriber',
        ]);
        
        if (is_wp_error($user_id)) {
            Security::log_auth_event('registration_error', 0, [
                'email' => $email,
                'error' => $user_id->get_error_message(),
            ]);
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create account. Please try again.',
            ], 500);
        }
        
        // Set user meta
        update_user_meta($user_id, '_bl_registered_via', 'email');
        update_user_meta($user_id, '_bl_email_verified', true);
        update_user_meta($user_id, '_bl_registered_at', current_time('mysql'));
        
        Security::log_auth_event('registration', $user_id, [
            'via' => 'email',
        ]);
        
        // Log user in
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        do_action('battleledger_user_registered', $user_id, 'email', [
            'email' => $email,
            'display_name' => $display_name,
        ]);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Account created successfully.',
            'user' => self::format_user_data(get_user_by('ID', $user_id)),
        ], 201);
    }
    
    /**
     * OTP Login endpoint
     */
    public static function otp_login(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        $otp = $request->get_param('otp');
        
        $result = OTPManager::otp_login($email, $otp);
        
        if (!$result['success']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }
        
        // Set auth cookie
        wp_set_current_user($result['user_id']);
        wp_set_auth_cookie($result['user_id'], true);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Logged in successfully.',
            'user' => self::format_user_data(get_user_by('ID', $result['user_id'])),
        ], 200);
    }
    
    /**
     * Email/Password Login endpoint
     */
    public static function login(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $remember = $request->get_param('remember');
        
        // Rate limit check
        $rate_check = Security::check_rate_limit(Security::get_client_ip(), 'login');
        if (!$rate_check['allowed']) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => sprintf(
                    'Too many login attempts. Please wait %d minutes.',
                    ceil($rate_check['reset_time'] / 60)
                ),
            ], 429);
        }
        
        $user = get_user_by('email', $email);
        
        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            Security::record_rate_limit(Security::get_client_ip(), 'login');
            Security::log_auth_event('login_failed', 0, [
                'email' => $email,
            ]);
            
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }
        
        // Clear rate limit on success
        Security::clear_rate_limit(Security::get_client_ip(), 'login');
        
        // Set auth cookie
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember);
        
        Security::log_auth_event('login', $user->ID, [
            'via' => 'email',
        ]);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Logged in successfully.',
            'user' => self::format_user_data($user),
        ], 200);
    }
    
    /**
     * Logout endpoint
     */
    public static function logout(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        
        wp_logout();
        
        Security::log_auth_event('logout', $user_id);
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }
    
    /**
     * Get current user endpoint
     */
    public static function get_current_user(\WP_REST_Request $request): \WP_REST_Response {
        if (!is_user_logged_in()) {
            return new \WP_REST_Response([
                'success' => true,
                'logged_in' => false,
                'user' => null,
            ], 200);
        }
        
        $user = wp_get_current_user();
        
        return new \WP_REST_Response([
            'success' => true,
            'logged_in' => true,
            'user' => self::format_user_data($user),
        ], 200);
    }
    
    /**
     * Get Google Auth URL endpoint
     */
    public static function get_google_auth_url(\WP_REST_Request $request): \WP_REST_Response {
        if (!AuthSettings::is_google_configured()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Google authentication is not configured.',
            ], 400);
        }
        
        $url = GoogleAuth::get_auth_url();
        
        return new \WP_REST_Response([
            'success' => true,
            'url' => $url,
        ], 200);
    }
    
    /**
     * Google OAuth callback endpoint
     */
    public static function google_callback(\WP_REST_Request $request): void {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');
        
        $redirect_url = Shortcode::get_form_redirect('loginRedirect', '') ?: home_url('/');
        $error_redirect = \BattleLedger\Core\PageInstaller::get_page_url('login') ?: home_url('/');
        
        // Handle error from Google
        if ($error) {
            Security::log_auth_event('google_auth_error', 0, [
                'error' => $error,
            ]);
            
            wp_redirect(add_query_arg('auth_error', 'google_denied', $error_redirect));
            exit;
        }
        
        // Verify state
        if (!$state || !GoogleAuth::verify_state($state)) {
            Security::log_auth_event('google_state_invalid', 0);
            
            wp_redirect(add_query_arg('auth_error', 'invalid_state', $error_redirect));
            exit;
        }
        
        // Exchange code for tokens
        $token_result = GoogleAuth::exchange_code($code);
        
        if (!$token_result['success']) {
            wp_redirect(add_query_arg('auth_error', 'token_exchange', $error_redirect));
            exit;
        }
        
        // Get user info
        $user_info = GoogleAuth::get_user_info($token_result['access_token']);
        
        if (!$user_info['success']) {
            wp_redirect(add_query_arg('auth_error', 'user_info', $error_redirect));
            exit;
        }
        
        // Authenticate user
        $auth_result = GoogleAuth::authenticate_user($user_info);
        
        if (!$auth_result['success']) {
            wp_redirect(add_query_arg('auth_error', 'authentication', $error_redirect));
            exit;
        }
        
        // Log user in
        wp_set_current_user($auth_result['user_id']);
        wp_set_auth_cookie($auth_result['user_id'], true);
        
        // Redirect
        if ($auth_result['is_new']) {
            $redirect_url = add_query_arg('welcome', '1', $redirect_url);
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Check email exists endpoint
     */
    public static function check_email(\WP_REST_Request $request): \WP_REST_Response {
        $email = $request->get_param('email');
        
        if (!Security::validate_email($email)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid email format.',
            ], 400);
        }
        
        $exists = email_exists($email);
        
        return new \WP_REST_Response([
            'success' => true,
            'exists' => (bool) $exists,
        ], 200);
    }
    
    /**
     * Update profile endpoint
     */
    public static function update_profile(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        
        $updates = [];
        
        if ($request->has_param('display_name')) {
            $updates['display_name'] = $request->get_param('display_name');
        }
        
        if ($request->has_param('first_name')) {
            $updates['first_name'] = $request->get_param('first_name');
        }
        
        if ($request->has_param('last_name')) {
            $updates['last_name'] = $request->get_param('last_name');
        }
        
        if (empty($updates)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'No updates provided.',
            ], 400);
        }
        
        $updates['ID'] = $user_id;
        
        $result = wp_update_user($updates);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update profile.',
            ], 500);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => self::format_user_data(get_user_by('ID', $user_id)),
        ], 200);
    }
    
    /**
     * Format user data for response
     */
    private static function format_user_data(\WP_User $user): array {
        $google_picture = get_user_meta($user->ID, '_bl_google_picture', true);
        $avatar_url = $google_picture ?: get_avatar_url($user->ID, ['size' => 96]);
        
        return [
            'id' => $user->ID,
            'email' => $user->user_email,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar' => $avatar_url,
            'registered_via' => get_user_meta($user->ID, '_bl_registered_via', true) ?: 'unknown',
            'roles' => $user->roles,
        ];
    }
    
    /**
     * Generate unique username
     */
    private static function generate_username(string $email, string $display_name = ''): string {
        if (!empty($display_name)) {
            $base = sanitize_user(strtolower(str_replace(' ', '', $display_name)), true);
        } else {
            $base = sanitize_user(strtolower(explode('@', $email)[0]), true);
        }
        
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
