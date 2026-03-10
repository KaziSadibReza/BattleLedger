<?php
namespace BattleLedger\Admin;

/**
 * Settings management
 */
class Settings {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('battle_ledger_settings', 'battle_ledger_theme_mode');
        register_setting('battle_ledger_settings', 'battle_ledger_enable_cache');
        register_setting('battle_ledger_settings', 'battle_ledger_cache_duration');
        register_setting('battle_ledger_settings', 'battle_ledger_enable_redis');
    }
    
    /**
     * Get setting value
     */
    public static function get($key, $default = '') {
        return get_option("battle_ledger_{$key}", $default);
    }
    
    /**
     * Update setting value
     */
    public static function update($key, $value) {
        return update_option("battle_ledger_{$key}", $value);
    }
}
