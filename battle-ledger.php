<?php
/**
 * Plugin Name: BattleLedger
 * Plugin URI: https://github.com/KaziSadibReza
 * Description: Game Tournament Manager - Comprehensive tournament management system with WooCommerce integration
 * Version: 1.0.0
 * Author: Kazi Sadib Reza
 * Author URI: https://github.com/KaziSadibReza
 * Text Domain: battle-ledger
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BATTLE_LEDGER_VERSION', '1.0.0');
define('BATTLE_LEDGER_PLUGIN_FILE', __FILE__);
define('BATTLE_LEDGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BATTLE_LEDGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BATTLE_LEDGER_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BATTLE_LEDGER_ASSETS_URL', BATTLE_LEDGER_PLUGIN_URL . 'assets/');

/**
 * Check plugin requirements before activation and during runtime.
 * Shows a styled admin notice if requirements are not met.
 */
function battle_ledger_check_requirements() {
    $errors = [];

    // PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = sprintf(
            'PHP 8.0 or higher is required. You are running PHP %s.',
            PHP_VERSION
        );
    }

    // WordPress version
    global $wp_version;
    if (version_compare($wp_version, '6.0', '<')) {
        $errors[] = sprintf(
            'WordPress 6.0 or higher is required. You are running WordPress %s.',
            $wp_version
        );
    }

    // Required PHP extensions
    $required_extensions = [
        'openssl'  => 'OpenSSL (required for push notifications & encryption)',
        'json'     => 'JSON (required for REST API)',
        'mbstring' => 'Mbstring (required for string handling)',
    ];
    foreach ($required_extensions as $ext => $label) {
        if (!extension_loaded($ext)) {
            $errors[] = sprintf('PHP extension <strong>%s</strong> is not installed.', $label);
        }
    }

    // MySQL / MariaDB
    global $wpdb;
    if (isset($wpdb->db_version)) {
        $db_version = $wpdb->db_version();
        if (version_compare($db_version, '5.7', '<')) {
            $errors[] = sprintf(
                'MySQL 5.7+ or MariaDB 10.3+ is required. You are running %s.',
                $db_version
            );
        }
    }

    // WooCommerce required
    if (!class_exists('WooCommerce')) {
        $errors[] = '<strong>WooCommerce</strong> plugin must be installed and activated. BattleLedger requires WooCommerce for payments, wallet deposits, and tournament entries.';
    }

    // Vite build assets
    $manifest = BATTLE_LEDGER_PLUGIN_DIR . 'assets/.vite/manifest.json';
    if (!file_exists($manifest)) {
        $errors[] = 'Production assets are missing (<code>assets/.vite/manifest.json</code>). Please run <code>pnpm build</code> or <code>npm run build</code> inside the plugin directory.';
    }

    return $errors;
}

/**
 * Prevent activation if requirements are not met.
 */
function battle_ledger_activation_check() {
    $errors = battle_ledger_check_requirements();
    if (!empty($errors)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;max-width:600px;margin:40px auto;padding:32px;background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);border-left:4px solid #dc3545;">'
            . '<h2 style="margin:0 0 16px;color:#1e1e1e;font-size:22px;">⚠️ BattleLedger — Requirements Not Met</h2>'
            . '<p style="color:#555;margin:0 0 16px;font-size:14px;">The plugin cannot be activated because your server does not meet the minimum requirements:</p>'
            . '<ul style="margin:0 0 20px;padding-left:20px;color:#333;font-size:14px;line-height:2;">'
            . '<li>' . implode('</li><li>', $errors) . '</li>'
            . '</ul>'
            . '<p style="color:#777;font-size:13px;margin:0;">Please resolve the issues above and try activating again.</p>'
            . '</div>',
            'BattleLedger — Requirements Not Met',
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, 'battle_ledger_activation_check');

/**
 * Show an admin notice if requirements degrade after activation.
 */
function battle_ledger_admin_requirement_notice() {
    // Skip the assets check on runtime — only enforce it during activation
    $errors = battle_ledger_check_requirements();
    // Remove the assets-missing warning at runtime (dev server may be in use)
    $errors = array_filter($errors, fn($e) => strpos($e, 'manifest.json') === false);

    if (empty($errors)) {
        return;
    }
    ?>
    <div class="notice notice-error" style="border-left-color:#dc3545;padding:16px 20px;">
        <h3 style="margin:0 0 8px;color:#1e1e1e;">⚠️ BattleLedger — System Requirements Issue</h3>
        <ul style="margin:0;padding-left:18px;line-height:1.8;">
            <?php foreach ($errors as $err): ?>
                <li><?php echo wp_kses_post($err); ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="margin:8px 0 0;color:#777;font-size:13px;">The plugin may not function correctly until these are resolved.</p>
    </div>
    <?php
}
add_action('admin_notices', 'battle_ledger_admin_requirement_notice');

/**
 * Add "Dashboard" action link to the plugins list page.
 */
function battle_ledger_plugin_action_links($links) {
    $dashboard_url = admin_url('admin.php?page=battle-ledger');
    $dashboard_link = '<a href="' . esc_url($dashboard_url) . '" style="font-weight:600;">Dashboard</a>';
    array_unshift($links, $dashboard_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'battle_ledger_plugin_action_links');

// Require Composer autoloader if it exists
if (file_exists(BATTLE_LEDGER_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once BATTLE_LEDGER_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main BattleLedger Class
 */
final class BattleLedger {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init']);
        add_action('init', ['BattleLedger\Core\Installer', 'maybe_upgrade']);
        
        // Declare WooCommerce HPOS compatibility
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/Installer.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/Assets.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/Cache.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PageInstaller.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/NotificationManager.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PushNotificationManager.php';
        
        // Database
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Database/Schema.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Database/QueryBuilder.php';
        
        // Admin
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Admin/Menu.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Admin/Settings.php';
        
        // API
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/RestController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/AdminController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/TournamentController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/PagesController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/SettingsController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/UserController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/WalletPaymentController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/DashboardController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/DiagnosticController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/RulesController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/PublicTournamentController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/FinishedTournamentController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/UserTournamentController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/NotificationController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/PushController.php';
        
        // WooCommerce (always include — classes handle WC absence gracefully)
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/WooCommerce/Integration.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/WooCommerce/HPOS.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/WooCommerce/WalletIntegration.php';
        
        // Frontend
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Frontend/Shortcodes.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Frontend/TemplateLoader.php';
        
        // Authentication
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/AuthSettings.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/Security.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/GoogleAuth.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/OTPManager.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/AuthController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/AdminSettingsController.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/Shortcode.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Auth/TemplateHandler.php';
        
        // Wallet
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Wallet/WalletManager.php';
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Wallet/WalletController.php';
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Core
        BattleLedger\Core\Assets::instance();
        BattleLedger\Core\Cache::instance();
        BattleLedger\Core\PageInstaller::init();
        
        // Admin
        if (is_admin()) {
            BattleLedger\Admin\Menu::instance();
            BattleLedger\Admin\Settings::instance();
        }
        
        // API
        BattleLedger\Api\RestController::instance();
        
        // WooCommerce (always init — WalletIntegration defers hooks if WC not loaded yet)
        BattleLedger\WooCommerce\WalletIntegration::instance();
        if ($this->is_woocommerce_active()) {
            BattleLedger\WooCommerce\Integration::instance();
            BattleLedger\WooCommerce\HPOS::instance();
        }
        
        // Frontend
        BattleLedger\Frontend\Shortcodes::instance();
        BattleLedger\Frontend\TemplateLoader::instance();
        
        // Authentication
        BattleLedger\Auth\Shortcode::init();
        BattleLedger\Auth\TemplateHandler::init();
        add_action('rest_api_init', [BattleLedger\Auth\AuthController::class, 'register_routes']);
        add_action('rest_api_init', [BattleLedger\Auth\AdminSettingsController::class, 'register_routes']);
        add_action('rest_api_init', [BattleLedger\Api\PagesController::class, 'register_routes']);
        add_action('rest_api_init', [BattleLedger\Api\UserController::class, 'register_routes']);
        add_action('rest_api_init', [BattleLedger\Api\DashboardController::class, 'register_routes']);
        
        // Wallet
        BattleLedger\Wallet\WalletManager::instance();
        add_action('rest_api_init', [BattleLedger\Api\WalletPaymentController::class, 'register_routes']);
        add_action('rest_api_init', [BattleLedger\Wallet\WalletController::class, 'register_routes']);

        // Public tournament API (frontend browsing & joining)
        add_action('rest_api_init', [BattleLedger\Api\PublicTournamentController::class, 'register_routes']);

        // Finished tournaments API (separate snapshots)
        add_action('rest_api_init', [BattleLedger\Api\FinishedTournamentController::class, 'register_routes']);

        // User-facing my-tournaments API
        add_action('rest_api_init', [BattleLedger\Api\UserTournamentController::class, 'register_routes']);

        // Notifications API
        add_action('rest_api_init', [BattleLedger\Api\NotificationController::class, 'register_routes']);

        // Push Notifications API
        add_action('rest_api_init', [BattleLedger\Api\PushController::class, 'register_routes']);
        
        // Flush rewrite rules on init if needed (after plugin update/activation)
        add_action('init', function() {
            if (get_transient('battleledger_flush_rewrite_rules')) {
                flush_rewrite_rules();
                delete_transient('battleledger_flush_rewrite_rules');
            }
        }, 99);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        do_action('battle_ledger_init');
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'battle-ledger',
            false,
            dirname(BATTLE_LEDGER_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/Installer.php';
        BattleLedger\Core\Installer::activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/Installer.php';
        BattleLedger\Core\Installer::deactivate();
    }
    
    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );
        }
    }
    
    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
}

/**
 * Initialize the plugin
 */
function battle_ledger() {
    return BattleLedger::instance();
}

// Start the plugin
battle_ledger();
