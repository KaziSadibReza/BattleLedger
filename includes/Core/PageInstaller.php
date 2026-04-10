<?php
/**
 * Page Installer - Creates required pages on plugin activation
 * 
 * Similar to WooCommerce page creation system
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Core;

if (!defined('ABSPATH')) {
    exit;
}

class PageInstaller {
    
    /**
     * Option name for storing page IDs
     */
    const PAGES_OPTION = 'battleledger_pages';

    /**
     * Option name for landing-page specific behavior
     */
    const LANDING_OPTION = 'battleledger_landing_options';
    
    /**
     * Default pages configuration
     */
    private static function get_pages_config(): array {
        return [
            'landing' => [
                'title' => __('Esports Landing', 'battle-ledger'),
                'content' => '<!-- wp:shortcode -->[battleledger_landing]<!-- /wp:shortcode -->',
                'option' => 'landing_page_id',
            ],
            'login' => [
                'title' => __('Login', 'battle-ledger'),
                'content' => '<!-- wp:shortcode -->[battleledger_auth]<!-- /wp:shortcode -->',
                'option' => 'login_page_id',
            ],
            'dashboard' => [
                'title' => __('Dashboard', 'battle-ledger'),
                'content' => '<!-- wp:shortcode -->[battleledger_dashboard]<!-- /wp:shortcode -->',
                'option' => 'dashboard_page_id',
            ],
        ];
    }
    
    /**
     * Create all required pages
     */
    public static function create_pages(): void {
        $pages = self::get_pages_config();
        $page_ids = get_option(self::PAGES_OPTION, []);
        
        foreach ($pages as $key => $page_config) {
            // Check if page already exists and is valid
            $existing_page_id = $page_ids[$page_config['option']] ?? 0;
            
            if ($existing_page_id && get_post_status($existing_page_id)) {
                // Page exists and is valid
                continue;
            }
            
            // Check if page with same content exists (maybe trashed or created manually)
            $existing = self::find_existing_page($page_config['content']);
            
            if ($existing) {
                // Restore if trashed
                if (get_post_status($existing) === 'trash') {
                    wp_untrash_post($existing);
                }
                $page_ids[$page_config['option']] = $existing;
                continue;
            }
            
            // Create new page
            $page_id = wp_insert_post([
                'post_title' => $page_config['title'],
                'post_content' => $page_config['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_author' => get_current_user_id() ?: 1,
                'comment_status' => 'closed',
            ]);
            
            if (!is_wp_error($page_id)) {
                $page_ids[$page_config['option']] = $page_id;
            }
        }
        
        update_option(self::PAGES_OPTION, $page_ids);
    }
    
    /**
     * Find existing page by shortcode content
     */
    private static function find_existing_page(string $content): ?int {
        global $wpdb;
        
        // Extract shortcode from content
        if (preg_match('/\[battleledger_\w+[^\]]*\]/', $content, $matches)) {
            $shortcode = $matches[0];
            
            $page_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'page' 
                AND post_content LIKE %s 
                AND post_status IN ('publish', 'draft', 'private', 'trash')
                LIMIT 1",
                '%' . $wpdb->esc_like($shortcode) . '%'
            ));
            
            return $page_id ? (int) $page_id : null;
        }
        
        return null;
    }
    
    /**
     * Get page ID by key
     */
    public static function get_page_id(string $key): int {
        $pages = get_option(self::PAGES_OPTION, []);
        $config = self::get_pages_config();
        
        if (isset($config[$key])) {
            return (int) ($pages[$config[$key]['option']] ?? 0);
        }
        
        return 0;
    }
    
    /**
     * Get page URL by key
     */
    public static function get_page_url(string $key): string {
        $page_id = self::get_page_id($key);
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        return home_url('/');
    }
    
    /**
     * Set page ID manually
     */
    public static function set_page_id(string $key, int $page_id): bool {
        $pages = get_option(self::PAGES_OPTION, []);
        $config = self::get_pages_config();
        
        if (isset($config[$key])) {
            $previous_page_id = (int) ($pages[$config[$key]['option']] ?? 0);

            // If landing page is currently used as homepage, keep front-page mapping in sync.
            if ($key === 'landing') {
                $show_on_front = get_option('show_on_front', 'posts');
                $current_front_page = (int) get_option('page_on_front', 0);

                if ($show_on_front === 'page' && $previous_page_id > 0 && $current_front_page === $previous_page_id) {
                    if ($page_id > 0) {
                        update_option('page_on_front', $page_id);
                    } else {
                        update_option('show_on_front', 'posts');
                        update_option('page_on_front', 0);
                    }
                }
            }

            $pages[$config[$key]['option']] = $page_id;
            return update_option(self::PAGES_OPTION, $pages);
        }
        
        return false;
    }
    
    /**
     * Get all page settings for admin
     */
    public static function get_all_pages(): array {
        $config = self::get_pages_config();
        $page_ids = get_option(self::PAGES_OPTION, []);
        $result = [];
        
        foreach ($config as $key => $page_config) {
            $page_id = $page_ids[$page_config['option']] ?? 0;
            
            $result[$key] = [
                'id' => $page_id,
                'title' => $page_config['title'],
                'url' => $page_id ? get_permalink($page_id) : '',
                'edit_url' => $page_id ? get_edit_post_link($page_id, 'raw') : '',
                'exists' => $page_id && get_post_status($page_id),
            ];
        }
        
        return $result;
    }

    /**
     * Get landing page options used in Authentication > Page Setup
     */
    public static function get_landing_options(): array {
        $landing_page_id = self::get_page_id('landing');
        $show_on_front = get_option('show_on_front', 'posts');
        $page_on_front = (int) get_option('page_on_front', 0);

        $saved = get_option(self::LANDING_OPTION, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        $plugin_shell_only = array_key_exists('plugin_shell_only', $saved)
            ? !empty($saved['plugin_shell_only'])
            : true;

        return [
            'set_as_homepage' => $landing_page_id > 0 && $show_on_front === 'page' && $page_on_front === $landing_page_id,
            'plugin_shell_only' => $plugin_shell_only,
            'landing_page_id' => $landing_page_id,
        ];
    }

    /**
     * Update landing page options
     */
    public static function update_landing_options(array $options): array {
        $landing_page_id = self::get_page_id('landing');
        $set_as_homepage = !empty($options['set_as_homepage']);
        $plugin_shell_only = !empty($options['plugin_shell_only']);

        if ($set_as_homepage) {
            if (!$landing_page_id || !get_post_status($landing_page_id)) {
                return [
                    'success' => false,
                    'message' => __('Please select a valid Landing page first.', 'battle-ledger'),
                    'options' => self::get_landing_options(),
                ];
            }

            update_option('show_on_front', 'page');
            update_option('page_on_front', $landing_page_id);

            if ((int) get_option('page_for_posts', 0) === $landing_page_id) {
                update_option('page_for_posts', 0);
            }
        } else {
            $current_front_page = (int) get_option('page_on_front', 0);
            if ($current_front_page === $landing_page_id) {
                update_option('show_on_front', 'posts');
                update_option('page_on_front', 0);
            }
        }

        update_option(self::LANDING_OPTION, [
            'plugin_shell_only' => $plugin_shell_only,
        ]);

        return [
            'success' => true,
            'message' => __('Landing page options updated.', 'battle-ledger'),
            'options' => self::get_landing_options(),
        ];
    }

    /**
     * Whether landing page should use plugin shell template (no theme layout)
     */
    public static function is_landing_plugin_shell_enabled(): bool {
        $saved = get_option(self::LANDING_OPTION, []);
        if (!is_array($saved) || !array_key_exists('plugin_shell_only', $saved)) {
            return true;
        }

        return !empty($saved['plugin_shell_only']);
    }

    /**
     * Check if a page is the configured landing page
     */
    public static function is_landing_page(?int $page_id = null): bool {
        if (!$page_id) {
            $page_id = get_queried_object_id();
        }

        return $page_id > 0 && $page_id === self::get_page_id('landing');
    }
    
    /**
     * Get all published pages for dropdown selection
     */
    public static function get_pages_for_select(): array {
        $pages = get_pages([
            'post_status' => ['publish', 'draft', 'private'],
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);
        
        $options = [
            0 => __('— Select a page —', 'battle-ledger'),
        ];
        
        foreach ($pages as $page) {
            $options[$page->ID] = $page->post_title;
        }
        
        return $options;
    }
    
    /**
     * Check if we're on a BattleLedger page
     */
    public static function is_battleledger_page(?int $page_id = null): bool {
        if (!$page_id) {
            $page_id = get_queried_object_id();
        }
        
        $pages = get_option(self::PAGES_OPTION, []);
        
        return in_array($page_id, array_values($pages), true);
    }
    
    /**
     * Recreate missing pages (for admin use)
     */
    public static function recreate_pages(): array {
        $results = [];
        $config = self::get_pages_config();
        $page_ids = get_option(self::PAGES_OPTION, []);
        
        foreach ($config as $key => $page_config) {
            $existing_page_id = $page_ids[$page_config['option']] ?? 0;
            
            // Check if page needs to be recreated
            if (!$existing_page_id || !get_post_status($existing_page_id)) {
                // Create new page
                $page_id = wp_insert_post([
                    'post_title' => $page_config['title'],
                    'post_content' => $page_config['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => get_current_user_id() ?: 1,
                    'comment_status' => 'closed',
                ]);
                
                if (!is_wp_error($page_id)) {
                    $page_ids[$page_config['option']] = $page_id;
                    $results[$key] = [
                        'created' => true,
                        'page_id' => $page_id,
                    ];
                } else {
                    $results[$key] = [
                        'created' => false,
                        'error' => $page_id->get_error_message(),
                    ];
                }
            } else {
                $results[$key] = [
                    'created' => false,
                    'exists' => true,
                    'page_id' => $existing_page_id,
                ];
            }
        }
        
        update_option(self::PAGES_OPTION, $page_ids);
        
        return $results;
    }
    
    /**
     * Initialize page installer hooks
     */
    public static function init(): void {
        add_filter('display_post_states', [self::class, 'add_page_states'], 10, 2);
    }
    
    /**
     * Add page state labels in admin pages list (like WooCommerce does)
     */
    public static function add_page_states(array $post_states, \WP_Post $post): array {
        $landing_page_id = self::get_page_id('landing');
        $login_page_id = self::get_page_id('login');
        $dashboard_page_id = self::get_page_id('dashboard');

        if ($landing_page_id && $post->ID === $landing_page_id) {
            $post_states['battleledger_landing_page'] = __('BattleLedger Landing Page', 'battle-ledger');
        }
        
        if ($login_page_id && $post->ID === $login_page_id) {
            $post_states['battleledger_login_page'] = __('BattleLedger Login Page', 'battle-ledger');
        }
        
        if ($dashboard_page_id && $post->ID === $dashboard_page_id) {
            $post_states['battleledger_dashboard_page'] = __('BattleLedger Dashboard Page', 'battle-ledger');
        }
        
        return $post_states;
    }
}
