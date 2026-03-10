<?php
/**
 * Template Handler for Auth Pages
 * 
 * Handles redirects and template modifications for auth pages
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Auth;

use BattleLedger\Core\PageInstaller;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateHandler {
    
    /**
     * Initialize template handler
     */
    public static function init(): void {
        add_action('template_redirect', [self::class, 'handle_redirects'], 20);
        add_filter('body_class', [self::class, 'add_body_classes']);
        add_action('wp_head', [self::class, 'add_meta_tags']);
    }
    
    /**
     * Handle page redirects
     */
    public static function handle_redirects(): void {
        // Skip admin, AJAX, and REST API requests
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST')) {
            return;
        }
        
        // Only run on singular pages
        if (!is_singular('page')) {
            return;
        }
        
        $current_page_id = get_queried_object_id();
        
        // Skip if no valid page ID
        if (!$current_page_id || $current_page_id <= 0) {
            return;
        }
        
        // Get BattleLedger page IDs
        $login_page_id = PageInstaller::get_page_id('login');
        
        // Skip if login page is not set up yet
        if (!$login_page_id) {
            return;
        }
        
        // If user is logged in and on login page, redirect to dashboard/home
        if (is_user_logged_in() && $current_page_id === $login_page_id && $login_page_id > 0) {
            $redirect_url = self::get_logged_in_redirect_url($login_page_id);
            
            // Prevent redirect to login page (infinite loop)
            if ($redirect_url && !self::is_same_page($redirect_url, $login_page_id)) {
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
    
    /**
     * Check if URL points to the same page
     */
    private static function is_same_page(string $url, int $page_id): bool {
        $url_page_id = url_to_postid($url);
        return $url_page_id === $page_id;
    }
    
    /**
     * Get redirect URL for logged in users
     */
    private static function get_logged_in_redirect_url(int $login_page_id): string {
        // Check for redirect_to parameter
        if (!empty($_GET['redirect_to'])) {
            $redirect = esc_url_raw(wp_unslash($_GET['redirect_to']));
            // Validate it's a local URL and not the login page
            if (wp_validate_redirect($redirect) && !self::is_same_page($redirect, $login_page_id)) {
                return $redirect;
            }
        }
        
        // Check settings for custom redirect
        $custom_redirect = AuthSettings::get('login_redirect', '');
        if (!empty($custom_redirect) && !self::is_same_page($custom_redirect, $login_page_id)) {
            return $custom_redirect;
        }
        
        // Fallback to admin dashboard for admins, home for others
        if (current_user_can('manage_options')) {
            return admin_url();
        }
        
        return home_url('/');
    }
    
    /**
     * Add body classes for BattleLedger pages
     */
    public static function add_body_classes(array $classes): array {
        $current_page_id = get_queried_object_id();
        
        if (PageInstaller::is_battleledger_page($current_page_id)) {
            $classes[] = 'battleledger-page';
            
            // Add specific page class
            $login_page_id = PageInstaller::get_page_id('login');
            
            if ($current_page_id === $login_page_id) {
                $classes[] = 'battleledger-login-page';
            }
        }
        
        return $classes;
    }
    
    /**
     * Add meta tags for auth pages
     */
    public static function add_meta_tags(): void {
        $current_page_id = get_queried_object_id();
        $login_page_id = PageInstaller::get_page_id('login');
        
        // Add noindex for login page if logged in (shouldn't happen but just in case)
        if ($current_page_id === $login_page_id && is_user_logged_in()) {
            echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
        }
    }
    
    /**
     * Check if current page is a BattleLedger page
     */
    public static function is_auth_page(): bool {
        $current_page_id = get_queried_object_id();
        $login_page_id = PageInstaller::get_page_id('login');
        
        return $current_page_id === $login_page_id;
    }
    
    /**
     * Get login URL with optional redirect
     */
    public static function get_login_url(?string $redirect = null): string {
        $login_url = PageInstaller::get_page_url('login');
        
        if (empty($login_url) || $login_url === home_url('/')) {
            $login_url = wp_login_url();
        }
        
        if ($redirect) {
            $login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);
        }
        
        return $login_url;
    }
    
    /**
     * Get logout URL with redirect
     */
    public static function get_logout_url(?string $redirect = null): string {
        if (empty($redirect)) {
            $redirect = AuthSettings::get('logout_redirect', home_url('/'));
        }
        
        return wp_logout_url($redirect);
    }
}
