<?php
namespace BattleLedger\Core;

/**
 * Plugin installer
 */
class Installer {
    
    const DB_VERSION = '1.5.0';
    
    /**
     * Check if DB schema needs updating and run create_tables if so.
     * Called on admin_init to auto-create new tables without deactivation.
     */
    public static function maybe_upgrade() {
        // Ensure required capabilities exist for all supported roles on every init.
        self::create_capabilities();
        self::create_pages();

        $current = get_option('battle_ledger_db_version', '0');
        if (version_compare($current, self::DB_VERSION, '<')) {
            self::create_tables();
            self::seed_default_game_rules();
            update_option('battle_ledger_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Run on plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_default_settings();
        self::create_capabilities();
        self::create_pages();
        self::seed_default_game_rules();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('battle_ledger_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'battle_ledger_daily_cleanup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set transient to flush again on next init (belt and suspenders)
        set_transient('battleledger_flush_rewrite_rules', true, 60);
        
        // Set activation flag
        update_option('battle_ledger_activated', time());
    }
    
    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('battle_ledger_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'battle_ledger_daily_cleanup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Database/Schema.php';
        \BattleLedger\Database\Schema::create_tables();
    }
    
    /**
     * Create default settings
     */
    private static function create_default_settings() {
        $defaults = [
            'theme_mode' => 'light',
            'enable_cache' => true,
            'cache_duration' => 3600,
            'enable_redis' => false,
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option("battle_ledger_{$key}") === false) {
                add_option("battle_ledger_{$key}", $value);
            }
        }
    }
    
    /**
     * Create custom capabilities
     */
    private static function create_capabilities() {
        $admin = get_role('administrator');
        $shop_manager = get_role('shop_manager');
        
        if ($admin) {
            $admin->add_cap('manage_battle_ledger');
            $admin->add_cap('manage_tournaments');
            $admin->add_cap('view_tournament_reports');
        }

        if ($shop_manager) {
            $shop_manager->add_cap('manage_battle_ledger');
            $shop_manager->add_cap('manage_tournaments');
            $shop_manager->add_cap('view_tournament_reports');
        }
    }
    
    /**
     * Seed default game rules (Free Fire & PUBG Mobile).
     * Safe to call multiple times — only inserts if the table is empty.
     */
    public static function seed_default_game_rules() {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_game_rules';

        // Only seed if table is empty
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count > 0) {
            return;
        }

        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Api/RulesController.php';
        $seeds = \BattleLedger\Api\RulesController::get_default_seeds();

        foreach ($seeds as $index => $seed) {
            $wpdb->insert($table, [
                'game_name'          => $seed['game_name'],
                'slug'               => $seed['slug'],
                'game_icon'          => $seed['game_icon'] ?? '',
                'game_image'         => $seed['game_image'] ?? '',
                'is_active'          => 1,
                'sort_order'         => $index,
                'all_maps'           => wp_json_encode($seed['all_maps']),
                'all_team_modes'     => wp_json_encode($seed['all_team_modes']),
                'all_player_counts'  => wp_json_encode($seed['all_player_counts']),
                'player_fields'      => wp_json_encode($seed['player_fields']),
                'available_settings' => wp_json_encode($seed['available_settings']),
                'game_modes'         => wp_json_encode($seed['game_modes']),
                'created_at'         => current_time('mysql'),
                'updated_at'         => current_time('mysql'),
            ]);
        }
    }

    /**
     * Create required pages (Login, My Account, Tournaments)
     */
    private static function create_pages() {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Core/PageInstaller.php';
        PageInstaller::create_pages();
    }
}
