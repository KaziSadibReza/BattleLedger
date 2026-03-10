<?php
namespace BattleLedger\Core;

/**
 * Asset management class
 */
class Assets {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);        add_filter('script_loader_tag', [$this, 'add_module_type_to_scripts'], 10, 2);
    }
    
    /**
     * Add type="module" to Vite scripts
     */
    public function add_module_type_to_scripts($tag, $handle) {
        $module_handles = [
            'battleledger-frontend',
            'battleledger-vite-client-frontend', 
            'battleledger-dashboard',
            'battleledger-vite-client-dashboard',
            'battleledger-live-tournaments',
            'battleledger-vite-client-live-tournaments',
            'battleledger-admin',
            'battleledger-vite-client',
            'battle-ledger-admin',
            'battle-ledger-vite-client'
        ];
        if (in_array($handle, $module_handles)) {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on BattleLedger admin pages
        if (strpos($hook, 'battle-ledger') === false) {
            return;
        }
        
        // Enqueue WordPress Media Library for logo upload
        wp_enqueue_media();
        
        // Check if dev server is running
        $dev_server = 'http://localhost:5173';
        $is_dev = $this->is_dev_server_running($dev_server);
        
        if ($is_dev) {
            // Development mode - load from Vite dev server
            
            // Enqueue WordPress dependencies for api-fetch
            wp_enqueue_script('wp-api-fetch');
            
            wp_enqueue_script(
                'battle-ledger-vite-client',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );
            
            wp_enqueue_script(
                'battle-ledger-admin',
                $dev_server . '/src/main.tsx',
                ['wp-api-fetch'],
                null,
                true
            );
            
            // Add module type
            add_filter('script_loader_tag', function($tag, $handle) {
                if (in_array($handle, ['battle-ledger-vite-client', 'battle-ledger-admin'])) {
                    return str_replace('<script ', '<script type="module" ', $tag);
                }
                return $tag;
            }, 10, 2);
            
            // Localize script
            wp_localize_script('battle-ledger-admin', 'battleLedgerData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url(),
                'restNamespace' => 'battle-ledger/v1',
                'nonce' => wp_create_nonce('wp_rest'),
                'pluginUrl' => BATTLE_LEDGER_PLUGIN_URL,
                'currentUser' => wp_get_current_user()->ID,
                'isWooCommerceActive' => class_exists('WooCommerce'),
                'isDev' => true,
            ]);
        } else {
            // Production mode - load from built assets
            $manifest_path = BATTLE_LEDGER_PLUGIN_DIR . 'assets/.vite/manifest.json';
            
            if (!file_exists($manifest_path)) {
                return;
            }
            
            $manifest = json_decode(file_get_contents($manifest_path), true);
            
            if (isset($manifest['src/main.tsx'])) {
                $main_entry = $manifest['src/main.tsx'];
                
                // Enqueue main JS
                if (isset($main_entry['file'])) {
                    wp_enqueue_script(
                        'battle-ledger-admin',
                        BATTLE_LEDGER_ASSETS_URL . $main_entry['file'],
                        ['wp-api-fetch'],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                    
                    // Localize script
                    wp_localize_script('battle-ledger-admin', 'battleLedgerData', [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'restUrl' => rest_url(),
                        'restNamespace' => 'battle-ledger/v1',
                        'nonce' => wp_create_nonce('wp_rest'),
                        'pluginUrl' => BATTLE_LEDGER_PLUGIN_URL,
                        'currentUser' => wp_get_current_user()->ID,
                        'isWooCommerceActive' => class_exists('WooCommerce'),
                        'isDev' => false,
                    ]);
                }
                
                // Enqueue main CSS
                if (isset($main_entry['css'])) {
                    foreach ($main_entry['css'] as $css_file) {
                        wp_enqueue_style(
                            'battle-ledger-admin',
                            BATTLE_LEDGER_ASSETS_URL . $css_file,
                            [],
                            BATTLE_LEDGER_VERSION
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Check if dev server is running
        $dev_server = 'http://localhost:5173';
        $is_dev = $this->is_dev_server_running($dev_server);
        
        if ($is_dev) {
            // Development mode - load from Vite dev server
            wp_register_script(
                'battleledger-frontend',
                $dev_server . '/src/frontend-auth-login/frontend.tsx',
                [],
                null,
                true
            );
            
            // Also need Vite client for HMR
            wp_register_script(
                'battleledger-vite-client-frontend',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );
            
            // Register empty style (Vite injects CSS)
            wp_register_style('battleledger-frontend', false);
            
            // Register dashboard scripts for dev
            wp_register_script(
                'battleledger-dashboard',
                $dev_server . '/src/frontend-dashboard/frontend-dashboard.tsx',
                [],
                null,
                true
            );
            
            wp_register_script(
                'battleledger-vite-client-dashboard',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );
            
            wp_register_style('battleledger-dashboard', false);

            // Register live-tournaments scripts for dev
            wp_register_script(
                'battleledger-live-tournaments',
                $dev_server . '/src/frontend-live-tournaments/frontend-live-tournaments.tsx',
                [],
                null,
                true
            );

            wp_register_script(
                'battleledger-vite-client-live-tournaments',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );

            wp_register_style('battleledger-live-tournaments', false);
        } else {
            // Production mode - load from built assets
            $manifest_path = BATTLE_LEDGER_PLUGIN_DIR . 'assets/.vite/manifest.json';
            
            if (!file_exists($manifest_path)) {
                return;
            }
            
            $manifest = json_decode(file_get_contents($manifest_path), true);
            
            // Register frontend auth bundle (check both old and new paths)
            $frontend_entry = null;
            if (isset($manifest['src/frontend-auth-login/frontend.tsx'])) {
                $frontend_entry = $manifest['src/frontend-auth-login/frontend.tsx'];
            } elseif (isset($manifest['src/AK/frontend.tsx'])) {
                $frontend_entry = $manifest['src/AK/frontend.tsx'];
            }
            
            if ($frontend_entry) {
                if (isset($frontend_entry['file'])) {
                    wp_register_script(
                        'battleledger-frontend',
                        BATTLE_LEDGER_ASSETS_URL . $frontend_entry['file'],
                        [],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                }
                
                if (isset($frontend_entry['css'])) {
                    foreach ($frontend_entry['css'] as $index => $css_file) {
                        wp_register_style(
                            'battleledger-frontend' . ($index > 0 ? '-' . $index : ''),
                            BATTLE_LEDGER_ASSETS_URL . $css_file,
                            [],
                            BATTLE_LEDGER_VERSION
                        );
                    }
                }
            }
            
            // Register dashboard bundle
            if (isset($manifest['src/frontend-dashboard/frontend-dashboard.tsx'])) {
                $dashboard_entry = $manifest['src/frontend-dashboard/frontend-dashboard.tsx'];
                
                if (isset($dashboard_entry['file'])) {
                    wp_register_script(
                        'battleledger-dashboard',
                        BATTLE_LEDGER_ASSETS_URL . $dashboard_entry['file'],
                        [],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                }
                
                if (isset($dashboard_entry['css'])) {
                    foreach ($dashboard_entry['css'] as $index => $css_file) {
                        wp_register_style(
                            'battleledger-dashboard' . ($index > 0 ? '-' . $index : ''),
                            BATTLE_LEDGER_ASSETS_URL . $css_file,
                            [],
                            BATTLE_LEDGER_VERSION
                        );
                    }
                }
            }

            // Register live-tournaments bundle
            if (isset($manifest['src/frontend-live-tournaments/frontend-live-tournaments.tsx'])) {
                $lt_entry = $manifest['src/frontend-live-tournaments/frontend-live-tournaments.tsx'];

                if (isset($lt_entry['file'])) {
                    wp_register_script(
                        'battleledger-live-tournaments',
                        BATTLE_LEDGER_ASSETS_URL . $lt_entry['file'],
                        [],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                }

                if (isset($lt_entry['css'])) {
                    foreach ($lt_entry['css'] as $index => $css_file) {
                        wp_register_style(
                            'battleledger-live-tournaments' . ($index > 0 ? '-' . $index : ''),
                            BATTLE_LEDGER_ASSETS_URL . $css_file,
                            [],
                            BATTLE_LEDGER_VERSION
                        );
                    }
                }
            }
        }
    }
    
    /**
     * Check if dev server is running
     */
    private function is_dev_server_running($url) {
        // Simple check - try to connect to the server
        $handle = @fsockopen('localhost', 5173, $errno, $errstr, 1);
        if ($handle) {
            fclose($handle);
            return true;
        }
        return false;
    }
    
    /**
     * Get asset URL from manifest
     */
    public static function get_asset_url($entry) {
        $manifest_path = BATTLE_LEDGER_PLUGIN_DIR . 'assets/.vite/manifest.json';
        
        if (!file_exists($manifest_path)) {
            return '';
        }
        
        $manifest = json_decode(file_get_contents($manifest_path), true);
        
        if (isset($manifest[$entry]['file'])) {
            return BATTLE_LEDGER_ASSETS_URL . $manifest[$entry]['file'];
        }
        
        return '';
    }
}
