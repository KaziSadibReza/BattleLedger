<?php
/**
 * Pages REST API Controller
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Api;

use BattleLedger\Core\PageInstaller;

if (!defined('ABSPATH')) {
    exit;
}

class PagesController {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'battle-ledger/v1';
    
    /**
     * Register REST API routes
     */
    public static function register_routes(): void {
        // Get pages configuration
        register_rest_route(self::NAMESPACE, '/pages', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_pages'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);
        
        // Recreate missing pages (register BEFORE the dynamic route)
        register_rest_route(self::NAMESPACE, '/pages/recreate', [
            'methods' => 'POST',
            'callback' => [self::class, 'recreate_pages'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);
        
        // Get all WordPress pages for selection (register BEFORE the dynamic route)
        register_rest_route(self::NAMESPACE, '/pages/available', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_available_pages'],
            'permission_callback' => [self::class, 'check_admin_permission'],
        ]);
        
        // Update page assignment (dynamic route - register LAST)
        register_rest_route(self::NAMESPACE, '/pages/(?P<key>[a-z_]+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_page'],
            'permission_callback' => [self::class, 'check_admin_permission'],
            'args' => [
                'key' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        // Only allow specific page keys, not 'recreate' or 'available'
                        return in_array($param, ['login', 'dashboard'], true);
                    }
                ],
                'page_id' => [
                    'required' => true,
                    'type' => 'integer',
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
     * Get pages configuration
     */
    public static function get_pages(\WP_REST_Request $request): \WP_REST_Response {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PageInstaller.php';
        
        $pages = PageInstaller::get_all_pages();
        
        return new \WP_REST_Response([
            'success' => true,
            'pages' => $pages,
        ], 200);
    }
    
    /**
     * Update page assignment
     */
    public static function update_page(\WP_REST_Request $request): \WP_REST_Response {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PageInstaller.php';
        
        $key = $request->get_param('key');
        $page_id = (int) $request->get_param('page_id');
        
        // Validate page exists if not 0
        if ($page_id > 0 && !get_post($page_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid page selected.',
            ], 400);
        }
        
        $result = PageInstaller::set_page_id($key, $page_id);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid page key.',
            ], 400);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Page updated successfully.',
            'pages' => PageInstaller::get_all_pages(),
        ], 200);
    }
    
    /**
     * Recreate missing pages
     */
    public static function recreate_pages(\WP_REST_Request $request): \WP_REST_Response {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PageInstaller.php';
        
        $results = PageInstaller::recreate_pages();
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Pages have been checked and recreated if missing.',
            'results' => $results,
            'pages' => PageInstaller::get_all_pages(),
        ], 200);
    }
    
    /**
     * Get available WordPress pages for selection
     */
    public static function get_available_pages(\WP_REST_Request $request): \WP_REST_Response {
        $pages = get_pages([
            'post_status' => ['publish', 'draft', 'private'],
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);
        
        $options = [];
        
        foreach ($pages as $page) {
            $options[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'status' => $page->post_status,
                'url' => get_permalink($page->ID),
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'pages' => $options,
        ], 200);
    }
}
