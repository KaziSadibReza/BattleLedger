<?php
/**
 * Authentication Shortcodes
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class Shortcode {
    
    /**
     * Initialize shortcodes
     */
    public static function init(): void {
        add_shortcode('battleledger_auth', [self::class, 'render_auth']);
        add_shortcode('battleledger_login', [self::class, 'render_login']);
        add_shortcode('battleledger_signup', [self::class, 'render_signup']);
        add_shortcode('battleledger_user_profile', [self::class, 'render_user_profile']);
        add_shortcode('battleledger_logout', [self::class, 'render_logout']);
        add_shortcode('battleledger_dashboard', [self::class, 'render_dashboard']);
    }
    
    /**
     * Render auth component (combined login/signup with modal)
     * 
     * @param array $atts Shortcode attributes
     */
    public static function render_auth(array $atts = []): string {
        $atts = shortcode_atts([
            'mode' => 'both', // login, signup, both
            'popup' => 'false', // true = show as trigger button, false = inline
            'button_text' => 'Log In / Sign Up',
            'redirect' => '',
            'class' => '',
        ], $atts, 'battleledger_auth');
        
        $container_id = 'bl-auth-' . wp_unique_id();
        
        return self::render_react_container($container_id, 'auth', [
            'mode' => $atts['mode'],
            'popup' => $atts['popup'] === 'true',
            'buttonText' => $atts['button_text'],
            'redirect' => $atts['redirect'] ?: AuthSettings::get('login_redirect', ''),
            'className' => $atts['class'],
            'googleEnabled' => AuthSettings::is_google_configured(),
            'otpEnabled' => AuthSettings::is_otp_enabled(),
        ]);
    }
    
    /**
     * Render login form only
     */
    public static function render_login(array $atts = []): string {
        $atts = shortcode_atts([
            'redirect' => '',
            'class' => '',
        ], $atts, 'battleledger_login');
        
        $container_id = 'bl-login-' . wp_unique_id();
        
        return self::render_react_container($container_id, 'login', [
            'redirect' => $atts['redirect'] ?: AuthSettings::get('login_redirect', ''),
            'className' => $atts['class'],
            'googleEnabled' => AuthSettings::is_google_configured(),
            'otpEnabled' => AuthSettings::is_otp_enabled(),
        ]);
    }
    
    /**
     * Render signup form only
     */
    public static function render_signup(array $atts = []): string {
        $atts = shortcode_atts([
            'redirect' => '',
            'class' => '',
        ], $atts, 'battleledger_signup');
        
        $container_id = 'bl-signup-' . wp_unique_id();
        
        return self::render_react_container($container_id, 'signup', [
            'redirect' => $atts['redirect'] ?: AuthSettings::get('login_redirect', ''),
            'className' => $atts['class'],
            'googleEnabled' => AuthSettings::is_google_configured(),
        ]);
    }
    
    /**
     * Render user profile dropdown
     */
    public static function render_user_profile(array $atts = []): string {
        $atts = shortcode_atts([
            'show_avatar' => 'true',
            'show_name' => 'true',
            'dashboard_link' => '',
            'class' => '',
        ], $atts, 'battleledger_user_profile');
        
        $container_id = 'bl-profile-' . wp_unique_id();
        
        return self::render_react_container($container_id, 'profile', [
            'showAvatar' => $atts['show_avatar'] === 'true',
            'showName' => $atts['show_name'] === 'true',
            'dashboardLink' => $atts['dashboard_link'] ?: AuthSettings::get('dashboard_url', ''),
            'className' => $atts['class'],
        ]);
    }
    
    /**
     * Render logout button
     */
    public static function render_logout(array $atts = []): string {
        $atts = shortcode_atts([
            'text' => 'Sign Out',
            'redirect' => '',
            'class' => '',
        ], $atts, 'battleledger_logout');
        
        if (!is_user_logged_in()) {
            return '';
        }
        
        $container_id = 'bl-logout-' . wp_unique_id();
        
        return self::render_react_container($container_id, 'logout', [
            'text' => $atts['text'],
            'redirect' => $atts['redirect'] ?: AuthSettings::get('logout_redirect', home_url('/')),
            'className' => $atts['class'],
        ]);
    }
    
    /**
     * Render user dashboard
     */
    public static function render_dashboard(array $atts = []): string {
        $atts = shortcode_atts([
            'add_funds_link' => '',
            'tournaments_link' => '',
            'transactions_link' => '',
            'logout_redirect' => '',
            'class' => '',
        ], $atts, 'battleledger_dashboard');
        
        // Dashboard requires login
        if (!is_user_logged_in()) {
            $login_url = \BattleLedger\Core\PageInstaller::get_page_url('login');
            return sprintf(
                '<div class="bl-dashboard-login-required"><p>%s</p><a href="%s" class="bl-btn bl-btn-primary">%s</a></div>',
                esc_html__('Please log in to view your dashboard.', 'battle-ledger'),
                esc_url($login_url),
                esc_html__('Log In', 'battle-ledger')
            );
        }
        
        $container_id = 'bl-dashboard-' . wp_unique_id();
        
        return self::render_dashboard_container($container_id, [
            'addFundsLink' => $atts['add_funds_link'],
            'tournamentsLink' => $atts['tournaments_link'],
            'transactionsLink' => $atts['transactions_link'],
            'logoutRedirect' => $atts['logout_redirect'] ?: AuthSettings::get('logout_redirect', home_url('/')),
            'className' => $atts['class'],
        ]);
    }
    
    /**
     * Render Dashboard container with props
     */
    private static function render_dashboard_container(string $id, array $props): string {
        // Ensure dashboard scripts are enqueued
        if (!wp_script_is('battleledger-dashboard', 'enqueued')) {
            if (wp_script_is('battleledger-vite-client-dashboard', 'registered')) {
                wp_enqueue_script('battleledger-vite-client-dashboard');
            }
            wp_enqueue_script('battleledger-dashboard');
            wp_enqueue_style('battleledger-dashboard');
        }
        
        // Add current user data
        $user = wp_get_current_user();
        $google_picture = get_user_meta($user->ID, '_bl_google_picture', true);
        
        $props['currentUser'] = [
            'id' => $user->ID,
            'email' => $user->user_email,
            'displayName' => $user->display_name,
            'avatar' => $google_picture ?: get_avatar_url($user->ID, ['size' => 200]),
        ];
        
        $props['nonce'] = Security::create_nonce();
        $props['apiUrl'] = rest_url();
        $props['currency'] = \BattleLedger\Wallet\WalletManager::get_currency();
        $props['currencySymbol'] = \BattleLedger\Wallet\WalletManager::get_currency_symbol();
        
        // Add form settings for consistent colors
        $form_settings = self::get_form_settings();
        $props['formSettings'] = $form_settings;
        
        $encoded_props = esc_attr(wp_json_encode($props));
        
        // Generate CSS variables for dashboard (same as auth form)
        $css_vars = self::generate_dashboard_css_variables($form_settings);
        
        return sprintf(
            '<style>%s</style><div id="%s" class="battleledger-dashboard-container" data-props="%s"></div>',
            $css_vars,
            esc_attr($id),
            $encoded_props
        );
    }
    
    /**
     * Generate CSS variables for dashboard from form settings + frontend appearance settings
     */
    private static function generate_dashboard_css_variables(array $settings): string {
        // Read from the dedicated frontend appearance option (shared with Live Tournaments)
        $appearance_defaults = [
            'primaryColor'       => '#6366f1',
            'primaryHoverColor'  => '#4f46e5',
            'successColor'       => '#10b981',
            'warningColor'       => '#f59e0b',
            'dangerColor'        => '#ef4444',
            'backgroundColor'    => '#f8fafc',
            'surfaceColor'       => '#ffffff',
            'borderColor'        => '#e2e8f0',
            'textColor'          => '#0f172a',
            'textSecondaryColor' => '#475569',
            'textMutedColor'     => '#94a3b8',
        ];
        $appearance = get_option('battle_ledger_frontend_appearance', []);
        $a = wp_parse_args($appearance, $appearance_defaults);
        
        $css = '.battleledger-dashboard-container {';
        
        // Primary / accent from frontend appearance
        $css .= '--bl-primary: '           . esc_attr($a['primaryColor']) . ';';
        $css .= '--bl-primary-hover: '     . esc_attr($a['primaryHoverColor']) . ';';
        $css .= '--bl-primary-dark: '      . esc_attr($a['primaryHoverColor']) . ';';
        $css .= '--bl-primary-light: '     . self::hex_to_rgba($a['primaryColor'], 0.1) . ';';
        $css .= '--bl-dash-primary: '      . esc_attr($a['primaryColor']) . ';';
        $css .= '--bl-dash-primary-light: ' . self::hex_to_rgba($a['primaryColor'], 0.1) . ';';
        $css .= '--bl-dash-primary-dark: '  . esc_attr($a['primaryHoverColor']) . ';';
        
        // Status colors
        $css .= '--bl-success: '       . esc_attr($a['successColor']) . ';';
        $css .= '--bl-success-light: ' . self::hex_to_rgba($a['successColor'], 0.08) . ';';
        $css .= '--bl-danger: '        . esc_attr($a['dangerColor']) . ';';
        $css .= '--bl-danger-light: '  . self::hex_to_rgba($a['dangerColor'], 0.08) . ';';
        $css .= '--bl-warning: '       . esc_attr($a['warningColor']) . ';';
        $css .= '--bl-warning-light: ' . self::hex_to_rgba($a['warningColor'], 0.08) . ';';
        
        // Surface / text
        $css .= '--bl-bg-color: '       . esc_attr($a['backgroundColor']) . ';';
        $css .= '--bl-surface: '        . esc_attr($a['surfaceColor']) . ';';
        $css .= '--bl-border: '         . esc_attr($a['borderColor']) . ';';
        $css .= '--bl-text-primary: '   . esc_attr($a['textColor']) . ';';
        $css .= '--bl-text-secondary: ' . esc_attr($a['textSecondaryColor']) . ';';
        
        $css .= '}';
        
        return $css;
    }
    
    /**
     * Render React container with props
     */
    private static function render_react_container(string $id, string $component, array $props): string {
        // Ensure frontend scripts are enqueued
        if (!wp_script_is('battleledger-frontend', 'enqueued')) {
            // Check if dev mode (Vite client exists)
            if (wp_script_is('battleledger-vite-client-frontend', 'registered')) {
                wp_enqueue_script('battleledger-vite-client-frontend');
            }
            wp_enqueue_script('battleledger-frontend');
            wp_enqueue_style('battleledger-frontend');
        }
        
        // Add current user data if logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $google_picture = get_user_meta($user->ID, '_bl_google_picture', true);
            
            $props['currentUser'] = [
                'id' => $user->ID,
                'email' => $user->user_email,
                'displayName' => $user->display_name,
                'avatar' => $google_picture ?: get_avatar_url($user->ID, ['size' => 96]),
            ];
        }
        
        $props['nonce'] = Security::create_nonce();
        $props['apiUrl'] = rest_url('battle-ledger/v1/auth');
        $props['currency'] = \BattleLedger\Wallet\WalletManager::get_currency();
        $props['currencySymbol'] = \BattleLedger\Wallet\WalletManager::get_currency_symbol();
        
        // Add form customization settings
        $form_settings = self::get_form_settings();
        $props['formSettings'] = $form_settings;
        
        $encoded_props = esc_attr(wp_json_encode($props));
        
        // Generate CSS variables from form settings
        $css_vars = self::generate_css_variables($form_settings);
        
        return sprintf(
            '<style>%s</style><div id="%s" class="battleledger-auth-container" data-component="%s" data-props="%s"></div>',
            $css_vars,
            esc_attr($id),
            esc_attr($component),
            $encoded_props
        );
    }
    
    /**
     * Get form customization settings
     */
    private static function get_form_settings(): array {
        $defaults = [
            'colors' => [
                'primaryColor' => '#6366f1',
                'primaryDarkColor' => '#4f46e5',
                'accentColor' => '#8b5cf6',
                'backgroundColor' => '#f8fafc',
                'surfaceColor' => '#ffffff',
                'borderColor' => '#e2e8f0',
                'textPrimaryColor' => '#0f172a',
                'textSecondaryColor' => '#475569',
                'textMutedColor' => '#94a3b8',
                'successColor' => '#10b981',
                'errorColor' => '#ef4444',
            ],
            'typography' => [
                'fontFamily' => 'system-ui, -apple-system, sans-serif',
                'headingFontSize' => '26px',
                'bodyFontSize' => '15px',
                'borderRadius' => '12px',
            ],
            'labels' => [
                'signInTitle' => 'Welcome back',
                'signInSubtitle' => 'Sign in to your account',
                'signUpTitle' => 'Create an account',
                'signUpSubtitle' => 'Get started with BattleLedger',
                'emailLabel' => 'Email',
                'passwordLabel' => 'Password',
                'signInButtonText' => 'Sign In',
                'signUpButtonText' => 'Create Account',
            ],
            'features' => [
                'showLogo' => false,
                'logoUrl' => '',
                'showSocialLogin' => false,
                'showOtpLogin' => true,
                'showPasswordLogin' => true,
                'defaultLoginMethod' => 'otp',
            ],
        ];
        
        $saved = get_option('battle_ledger_form_settings', []);
        
        return array_replace_recursive($defaults, $saved);
    }
    
    /**
     * Generate CSS variables from form settings
     */
    private static function generate_css_variables(array $settings): string {
        $colors = $settings['colors'] ?? [];
        $typography = $settings['typography'] ?? [];
        
        $css = '.battleledger-auth-container {';
        
        // Colors
        if (!empty($colors['primaryColor'])) {
            $css .= '--bl-auth-primary: ' . esc_attr($colors['primaryColor']) . ';';
            $css .= '--bl-primary: ' . esc_attr($colors['primaryColor']) . ';';
            // Generate derived colors from primary
            $css .= '--bl-primary-shadow: ' . self::hex_to_rgba($colors['primaryColor'], 0.35) . ';';
            $css .= '--bl-primary-subtle: ' . self::hex_to_rgba($colors['primaryColor'], 0.1) . ';';
            $css .= '--bl-primary-light: ' . self::hex_to_rgba($colors['primaryColor'], 0.15) . ';';
        }
        if (!empty($colors['primaryDarkColor'])) {
            $css .= '--bl-auth-primary-dark: ' . esc_attr($colors['primaryDarkColor']) . ';';
            $css .= '--bl-primary-dark: ' . esc_attr($colors['primaryDarkColor']) . ';';
            $css .= '--bl-primary-hover: ' . esc_attr($colors['primaryDarkColor']) . ';';
        }
        if (!empty($colors['accentColor'])) {
            $css .= '--bl-auth-accent: ' . esc_attr($colors['accentColor']) . ';';
            $css .= '--bl-accent: ' . esc_attr($colors['accentColor']) . ';';
        }
        if (!empty($colors['backgroundColor'])) {
            $css .= '--bl-auth-bg: ' . esc_attr($colors['backgroundColor']) . ';';
        }
        if (!empty($colors['surfaceColor'])) {
            $css .= '--bl-auth-surface: ' . esc_attr($colors['surfaceColor']) . ';';
        }
        if (!empty($colors['borderColor'])) {
            $css .= '--bl-auth-border: ' . esc_attr($colors['borderColor']) . ';';
        }
        if (!empty($colors['textPrimaryColor'])) {
            $css .= '--bl-auth-text: ' . esc_attr($colors['textPrimaryColor']) . ';';
        }
        if (!empty($colors['textSecondaryColor'])) {
            $css .= '--bl-auth-text-secondary: ' . esc_attr($colors['textSecondaryColor']) . ';';
        }
        if (!empty($colors['textMutedColor'])) {
            $css .= '--bl-auth-text-muted: ' . esc_attr($colors['textMutedColor']) . ';';
        }
        if (!empty($colors['successColor'])) {
            $css .= '--bl-auth-success: ' . esc_attr($colors['successColor']) . ';';
        }
        if (!empty($colors['errorColor'])) {
            $css .= '--bl-auth-error: ' . esc_attr($colors['errorColor']) . ';';
        }
        
        // Typography
        if (!empty($typography['fontFamily'])) {
            $css .= '--bl-auth-font: ' . esc_attr($typography['fontFamily']) . ';';
        }
        if (!empty($typography['headingFontSize'])) {
            $css .= '--bl-auth-heading-size: ' . esc_attr($typography['headingFontSize']) . ';';
        }
        if (!empty($typography['bodyFontSize'])) {
            $css .= '--bl-auth-body-size: ' . esc_attr($typography['bodyFontSize']) . ';';
        }
        if (!empty($typography['borderRadius'])) {
            $css .= '--bl-auth-radius: ' . esc_attr($typography['borderRadius']) . ';';
        }
        
        $css .= '}';
        
        return $css;
    }
    
    /**
     * Convert hex color to rgba
     */
    private static function hex_to_rgba(string $hex, float $alpha = 1.0): string {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
