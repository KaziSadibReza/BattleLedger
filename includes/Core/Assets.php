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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_head', [$this, 'output_pwa_meta_tags'], 2);
        add_action('wp_enqueue_scripts', [$this, 'strip_theme_assets_for_landing_shell'], 99999);
        add_action('wp_print_styles', [$this, 'strip_theme_assets_for_landing_shell'], 1);
        add_action('wp_print_scripts', [$this, 'strip_theme_assets_for_landing_shell'], 1);
        add_action('template_redirect', [$this, 'serve_pwa_assets']);
        add_filter('script_loader_tag', [$this, 'add_module_type_to_scripts'], 10, 2);
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
            'battleledger-landing',
            'battleledger-vite-client-landing',
            'battleledger-landing-shell',
            'battleledger-vite-client-landing-shell',
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
                'currency' => \BattleLedger\Wallet\WalletManager::get_currency(),
                'currencySymbol' => \BattleLedger\Wallet\WalletManager::get_currency_symbol(),
                'currencyPosition' => get_option('woocommerce_currency_pos', 'left'),
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
                        'currency' => \BattleLedger\Wallet\WalletManager::get_currency(),
                        'currencySymbol' => \BattleLedger\Wallet\WalletManager::get_currency_symbol(),
                        'currencyPosition' => get_option('woocommerce_currency_pos', 'left'),
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

            // Register landing scripts for dev
            wp_register_script(
                'battleledger-landing',
                $dev_server . '/src/frontend-landing/frontend-landing.tsx',
                [],
                null,
                true
            );

            wp_register_script(
                'battleledger-vite-client-landing',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );

            wp_register_style('battleledger-landing', false);

            // Register landing-shell scripts for dev (header/footer only shell)
            wp_register_script(
                'battleledger-landing-shell',
                $dev_server . '/src/frontend-landing-shell/frontend-landing-shell.tsx',
                [],
                null,
                true
            );

            wp_register_script(
                'battleledger-vite-client-landing-shell',
                $dev_server . '/@vite/client',
                [],
                null,
                true
            );

            wp_register_style('battleledger-landing-shell', false);

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
            $frontend_entry_key = null;
            if (isset($manifest['src/frontend-auth-login/frontend.tsx'])) {
                $frontend_entry = $manifest['src/frontend-auth-login/frontend.tsx'];
                $frontend_entry_key = 'src/frontend-auth-login/frontend.tsx';
            } elseif (isset($manifest['src/AK/frontend.tsx'])) {
                $frontend_entry = $manifest['src/AK/frontend.tsx'];
                $frontend_entry_key = 'src/AK/frontend.tsx';
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
                
                if ($frontend_entry_key) {
                    $frontend_css_files = $this->collect_manifest_css_files(
                        $manifest,
                        $frontend_entry_key
                    );

                    foreach ($frontend_css_files as $index => $css_file) {
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
                
                $dashboard_css_files = $this->collect_manifest_css_files(
                    $manifest,
                    'src/frontend-dashboard/frontend-dashboard.tsx'
                );

                foreach ($dashboard_css_files as $index => $css_file) {
                    wp_register_style(
                        'battleledger-dashboard' . ($index > 0 ? '-' . $index : ''),
                        BATTLE_LEDGER_ASSETS_URL . $css_file,
                        [],
                        BATTLE_LEDGER_VERSION
                    );
                }
            }

            // Register landing bundle
            if (isset($manifest['src/frontend-landing/frontend-landing.tsx'])) {
                $landing_entry = $manifest['src/frontend-landing/frontend-landing.tsx'];

                if (isset($landing_entry['file'])) {
                    wp_register_script(
                        'battleledger-landing',
                        BATTLE_LEDGER_ASSETS_URL . $landing_entry['file'],
                        [],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                }

                $landing_css_files = $this->collect_manifest_css_files(
                    $manifest,
                    'src/frontend-landing/frontend-landing.tsx'
                );

                foreach ($landing_css_files as $index => $css_file) {
                    wp_register_style(
                        'battleledger-landing' . ($index > 0 ? '-' . $index : ''),
                        BATTLE_LEDGER_ASSETS_URL . $css_file,
                        [],
                        BATTLE_LEDGER_VERSION
                    );
                }
            }

            // Register landing-shell bundle
            if (isset($manifest['src/frontend-landing-shell/frontend-landing-shell.tsx'])) {
                $landing_shell_entry = $manifest['src/frontend-landing-shell/frontend-landing-shell.tsx'];

                if (isset($landing_shell_entry['file'])) {
                    wp_register_script(
                        'battleledger-landing-shell',
                        BATTLE_LEDGER_ASSETS_URL . $landing_shell_entry['file'],
                        [],
                        BATTLE_LEDGER_VERSION,
                        true
                    );
                }

                $landing_shell_css_files = $this->collect_manifest_css_files(
                    $manifest,
                    'src/frontend-landing-shell/frontend-landing-shell.tsx'
                );

                foreach ($landing_shell_css_files as $index => $css_file) {
                    wp_register_style(
                        'battleledger-landing-shell' . ($index > 0 ? '-' . $index : ''),
                        BATTLE_LEDGER_ASSETS_URL . $css_file,
                        [],
                        BATTLE_LEDGER_VERSION
                    );
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

                $live_tournaments_css_files = $this->collect_manifest_css_files(
                    $manifest,
                    'src/frontend-live-tournaments/frontend-live-tournaments.tsx'
                );

                foreach ($live_tournaments_css_files as $index => $css_file) {
                    wp_register_style(
                        'battleledger-live-tournaments' . ($index > 0 ? '-' . $index : ''),
                        BATTLE_LEDGER_ASSETS_URL . $css_file,
                        [],
                        BATTLE_LEDGER_VERSION
                    );
                }
            }
        }

        // Ensure plugin shell assets are available in <head> for BattleLedger pages.
        // Shortcode execution can happen after wp_head, which is too late for styles.
        $this->enqueue_plugin_shell_assets_if_needed();
    }

    /**
     * Enqueue all registered style handles for a base prefix.
     * Example: battleledger-landing, battleledger-landing-1, battleledger-landing-2...
     */
    private function enqueue_style_group(string $base_handle): void {
        if (wp_style_is($base_handle, 'registered')) {
            wp_enqueue_style($base_handle);
        }

        for ($index = 1; $index <= 20; $index++) {
            $handle = $base_handle . '-' . $index;
            if (!wp_style_is($handle, 'registered')) {
                break;
            }

            wp_enqueue_style($handle);
        }
    }

    /**
     * Enqueue landing/shell assets early when plugin-only shell is active.
     */
    private function enqueue_plugin_shell_assets_if_needed(): void {
        if (!is_singular('page') || !PageInstaller::is_landing_plugin_shell_enabled()) {
            return;
        }

        $page_id = (int) get_queried_object_id();
        if ($page_id <= 0 || !PageInstaller::is_battleledger_page($page_id)) {
            return;
        }

        if (PageInstaller::is_landing_page($page_id)) {
            if (wp_script_is('battleledger-vite-client-landing', 'registered')) {
                wp_enqueue_script('battleledger-vite-client-landing');
            }

            if (wp_script_is('battleledger-landing', 'registered')) {
                wp_enqueue_script('battleledger-landing');
            }

            $this->enqueue_style_group('battleledger-landing');
        }

        if ($page_id === PageInstaller::get_page_id('login')) {
            if (wp_script_is('battleledger-vite-client-frontend', 'registered')) {
                wp_enqueue_script('battleledger-vite-client-frontend');
            }

            if (wp_script_is('battleledger-frontend', 'registered')) {
                wp_enqueue_script('battleledger-frontend');
            }

            $this->enqueue_style_group('battleledger-frontend');
        }

        if ($page_id === PageInstaller::get_page_id('dashboard')) {
            if (wp_script_is('battleledger-vite-client-dashboard', 'registered')) {
                wp_enqueue_script('battleledger-vite-client-dashboard');
            }

            if (wp_script_is('battleledger-dashboard', 'registered')) {
                wp_enqueue_script('battleledger-dashboard');
            }

            $this->enqueue_style_group('battleledger-dashboard');
        }

        if (wp_script_is('battleledger-vite-client-landing-shell', 'registered')) {
            wp_enqueue_script('battleledger-vite-client-landing-shell');
        }

        if (wp_script_is('battleledger-landing-shell', 'registered')) {
            wp_enqueue_script('battleledger-landing-shell');

            $runtime_props = $this->get_landing_runtime_props($page_id);
            wp_add_inline_script(
                'battleledger-landing-shell',
                'window.battleLedgerLandingProps = ' . wp_json_encode($runtime_props) . ';',
                'before'
            );
        }

        $this->enqueue_style_group('battleledger-landing-shell');
    }

    /**
     * Build runtime landing props used by header/footer shell rendering.
     */
    private function get_landing_runtime_props(int $page_id): array {
        $login_url = PageInstaller::get_page_url('login');
        if (!$login_url) {
            $login_url = wp_login_url(get_permalink($page_id) ?: home_url('/'));
        }

        $dashboard_url = PageInstaller::get_page_url('dashboard');
        if (!$dashboard_url) {
            $dashboard_url = home_url('/');
        }

        $page_type = 'shell';
        if ($page_id === PageInstaller::get_page_id('login')) {
            $page_type = 'login';
        } elseif ($page_id === PageInstaller::get_page_id('dashboard')) {
            $page_type = 'dashboard';
        } elseif ($page_id === PageInstaller::get_page_id('landing')) {
            $page_type = 'landing';
        }

        return [
            'apiUrl' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'pluginUrl' => BATTLE_LEDGER_PLUGIN_URL,
            'homeUrl' => home_url('/'),
            'landingUrl' => PageInstaller::get_page_url('landing'),
            'isLoggedIn' => is_user_logged_in(),
            'loginUrl' => $login_url,
            'dashboardUrl' => $dashboard_url,
            'authButtonHtml' => do_shortcode('[battleledger_auth popup="true" button_text="Login"]'),
            'pageType' => $page_type,
        ];
    }

    /**
     * Output PWA manifest/meta tags for BattleLedger frontend pages.
     */
    public function output_pwa_meta_tags(): void {
        if (is_admin() || !is_singular('page')) {
            return;
        }

        $page_id = (int) get_queried_object_id();
        if ($page_id <= 0 || !PageInstaller::is_battleledger_page($page_id)) {
            return;
        }

        $manifest_args = [
            'battleledger_pwa_manifest' => '1',
        ];

        $site_icon_id = (int) get_option('site_icon');
        if ($site_icon_id > 0) {
            // Change the manifest URL when the selected Site Icon changes.
            $manifest_args['icon'] = (string) $site_icon_id;
        }

        $site_title = trim((string) get_option('blogname'));
        if ($site_title !== '') {
            // Refresh manifest URL when Site Title changes so install prompt metadata updates.
            $manifest_args['title'] = substr(md5($site_title), 0, 10);
        }

        $manifest_url = add_query_arg($manifest_args, home_url('/'));

        echo '<link rel="manifest" href="' . esc_url($manifest_url) . '" />' . "\n";
        echo '<meta name="theme-color" content="#0b1220" />' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes" />' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";

        $apple_icon = get_site_icon_url(180);
        if ($apple_icon) {
            echo '<link rel="apple-touch-icon" href="' . esc_url($apple_icon) . '" />' . "\n";
        }
    }

    /**
     * Serve dynamic PWA assets from same-origin root URLs.
     */
    public function serve_pwa_assets(): void {
        if (is_admin()) {
            return;
        }

        if (isset($_GET['battleledger_pwa_manifest'])) {
            nocache_headers();
            header('Content-Type: application/manifest+json; charset=utf-8');
            echo wp_json_encode($this->get_pwa_manifest_payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_GET['battleledger_pwa_sw'])) {
            nocache_headers();
            header('Content-Type: application/javascript; charset=utf-8');

            $home_url = home_url('/');
            $manifest_path = BATTLE_LEDGER_PLUGIN_DIR . 'assets/.vite/manifest.json';
            $build_fingerprint = file_exists($manifest_path)
                ? (string) filemtime($manifest_path)
                : (string) BATTLE_LEDGER_VERSION;
            $cache_name = 'battleledger-pwa-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $build_fingerprint);

            echo "const CACHE_NAME = '" . esc_js($cache_name) . "';\n";
            echo "const OFFLINE_FALLBACK = '" . esc_js($home_url) . "';\n";
            echo "self.addEventListener('install', (event) => {\n";
            echo "  event.waitUntil((async () => {\n";
            echo "    self.skipWaiting();\n";
            echo "    const cache = await caches.open(CACHE_NAME);\n";
            echo "    await cache.add(OFFLINE_FALLBACK).catch(() => null);\n";
            echo "  })());\n";
            echo "});\n";
            echo "self.addEventListener('activate', (event) => {\n";
            echo "  event.waitUntil((async () => {\n";
            echo "    await self.clients.claim();\n";
            echo "    const keys = await caches.keys();\n";
            echo "    await Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)));\n";
            echo "  })());\n";
            echo "});\n";
            echo "self.addEventListener('fetch', (event) => {\n";
            echo "  if (event.request.method !== 'GET') return;\n";
            echo "  const requestUrl = new URL(event.request.url);\n";
            echo "  if (requestUrl.origin !== self.location.origin) return;\n";
            echo "  if (requestUrl.searchParams.has('battleledger_pwa_manifest') || requestUrl.searchParams.has('battleledger_pwa_sw')) return;\n";
            echo "  if (requestUrl.pathname.includes('/wp-json/') || requestUrl.pathname.includes('admin-ajax.php')) return;\n";
            echo "  if (event.request.mode !== 'navigate') return;\n";
            echo "  event.respondWith(fetch(event.request).catch(async () => (await caches.match(OFFLINE_FALLBACK)) || Response.error()));\n";
            echo "});\n";

            exit;
        }
    }

    /**
     * Build manifest payload for install prompt support.
     */
    private function get_pwa_manifest_payload(): array {
        $landing_url = PageInstaller::get_page_url('landing') ?: home_url('/');
        $site_name = trim((string) get_option('blogname'));
        if ($site_name === '') {
            $site_name = 'BattleLedger';
        }

        $short_name = $site_name;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($short_name) > 12) {
                $short_name = mb_substr($short_name, 0, 12);
            }
        } elseif (strlen($short_name) > 12) {
            $short_name = substr($short_name, 0, 12);
        }

        $site_tagline = trim((string) get_option('blogdescription'));
        $description = $site_tagline !== ''
            ? $site_tagline
            : 'Compete in tournaments and win real prizes.';

        $icons = [];

        $icon_192 = get_site_icon_url(192);
        $icon_512 = get_site_icon_url(512);

        if ($icon_192) {
            $icons[] = [
                'src' => esc_url_raw($icon_192),
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        if ($icon_512) {
            $icons[] = [
                'src' => esc_url_raw($icon_512),
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        if (empty($icons)) {
            $fallback_icon = BATTLE_LEDGER_PLUGIN_URL . 'assets/assets/esport-hero.png';
            $icons[] = [
                'src' => esc_url_raw($fallback_icon),
                'sizes' => 'any',
                'type' => 'image/png',
                'purpose' => 'any',
            ];
        }

        return [
            'id' => trailingslashit($landing_url),
            'name' => $site_name,
            'short_name' => $short_name,
            'description' => $description,
            'start_url' => add_query_arg('source', 'pwa', $landing_url),
            'scope' => home_url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0b1220',
            'theme_color' => '#0b1220',
            'icons' => $icons,
        ];
    }

    /**
     * Remove theme CSS/JS when plugin-only landing shell is active.
     */
    public function strip_theme_assets_for_landing_shell(): void {
        if (!$this->should_strip_theme_assets_for_landing_shell()) {
            return;
        }

        $this->dequeue_theme_styles();
        $this->dequeue_theme_scripts();
    }

    /**
     * Whether current request is using plugin-only landing shell.
     */
    private function should_strip_theme_assets_for_landing_shell(): bool {
        if (is_admin() || !is_singular('page')) {
            return false;
        }

        $page_id = (int) get_queried_object_id();
        if ($page_id <= 0) {
            return false;
        }

        return PageInstaller::is_landing_plugin_shell_enabled() && PageInstaller::is_battleledger_page($page_id);
    }

    /**
     * Check if an asset src belongs to the active theme (parent or child).
     */
    private function is_theme_asset_src(string $src): bool {
        $src = trim($src);
        if ($src === '') {
            return false;
        }

        if (str_starts_with($src, '//')) {
            $src = (is_ssl() ? 'https:' : 'http:') . $src;
        }

        if (str_starts_with($src, '/')) {
            $src = home_url($src);
        }

        $template_uri = untrailingslashit(get_template_directory_uri());
        $stylesheet_uri = untrailingslashit(get_stylesheet_directory_uri());

        return str_contains($src, '/wp-content/themes/')
            || str_starts_with($src, $template_uri)
            || str_starts_with($src, $stylesheet_uri);
    }

    /**
     * Dequeue theme-owned styles on landing shell pages.
     */
    private function dequeue_theme_styles(): void {
        global $wp_styles;

        if (!($wp_styles instanceof \WP_Styles)) {
            return;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            if (str_starts_with($handle, 'battleledger-')) {
                continue;
            }

            $registered = $wp_styles->registered[$handle] ?? null;
            $src = ($registered && isset($registered->src)) ? (string) $registered->src : '';

            if ($this->is_theme_asset_src($src)) {
                wp_dequeue_style($handle);
            }
        }

        // Common theme/global style handles that may not expose a theme src directly.
        wp_dequeue_style('global-styles');
        wp_dequeue_style('classic-theme-styles');
        wp_dequeue_style('wp-block-library-theme');
    }

    /**
     * Dequeue theme-owned scripts on landing shell pages.
     */
    private function dequeue_theme_scripts(): void {
        global $wp_scripts;

        if (!($wp_scripts instanceof \WP_Scripts)) {
            return;
        }

        foreach ((array) $wp_scripts->queue as $handle) {
            if (str_starts_with($handle, 'battleledger-')) {
                continue;
            }

            $registered = $wp_scripts->registered[$handle] ?? null;
            $src = ($registered && isset($registered->src)) ? (string) $registered->src : '';

            if ($this->is_theme_asset_src($src)) {
                wp_dequeue_script($handle);
            }
        }
    }

    /**
     * Collect all CSS files for a Vite manifest entry, including imported chunks.
     */
    private function collect_manifest_css_files(array $manifest, string $entry_key): array {
        $css_files = [];
        $visited = [];

        $walk = function (string $key) use (&$walk, $manifest, &$css_files, &$visited): void {
            if (isset($visited[$key])) {
                return;
            }

            $visited[$key] = true;

            if (!isset($manifest[$key]) || !is_array($manifest[$key])) {
                return;
            }

            $entry = $manifest[$key];

            if (!empty($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $css_file) {
                    if (is_string($css_file) && $css_file !== '') {
                        $css_files[] = $css_file;
                    }
                }
            }

            if (!empty($entry['imports']) && is_array($entry['imports'])) {
                foreach ($entry['imports'] as $import_key) {
                    if (is_string($import_key) && $import_key !== '') {
                        $walk($import_key);
                    }
                }
            }
        };

        $walk($entry_key);

        return array_values(array_unique($css_files));
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
