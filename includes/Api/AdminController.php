<?php
namespace BattleLedger\Api;

/**
 * Admin API controller
 */
class AdminController {
    
    /**
     * Register routes
     */
    public static function register_routes() {
        register_rest_route('battle-ledger/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'get_settings'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'update_settings'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);
        
        register_rest_route('battle-ledger/v1', '/dashboard', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_dashboard_data'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
        
        // Stats endpoint (alias for dashboard)
        register_rest_route('battle-ledger/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_dashboard_data'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
    }
    
    /**
     * Get settings
     */
    public static function get_settings($request) {
        return rest_ensure_response([
            'theme_mode' => get_option('battle_ledger_theme_mode', 'light'),
            'enable_cache' => get_option('battle_ledger_enable_cache', true),
            'cache_duration' => get_option('battle_ledger_cache_duration', 3600),
            'enable_redis' => get_option('battle_ledger_enable_redis', false),
            'min_deposit_amount' => (float) get_option('battle_ledger_min_deposit_amount', 0),
            'min_withdraw_amount' => (float) get_option('battle_ledger_min_withdraw_amount', 0),
        ]);
    }
    
    /**
     * Update settings
     */
    public static function update_settings($request) {
        $params = $request->get_json_params();
        
        $allowed_settings = [
            'theme_mode',
            'enable_cache',
            'cache_duration',
            'enable_redis',
            'min_deposit_amount',
            'min_withdraw_amount',
        ];
        
        foreach ($allowed_settings as $setting) {
            if (isset($params[$setting])) {
                // Ensure numeric values are stored as floats where appropriate
                if (in_array($setting, ['min_deposit_amount', 'min_withdraw_amount'])) {
                    update_option("battle_ledger_{$setting}", floatval($params[$setting]));
                } else {
                    update_option("battle_ledger_{$setting}", $params[$setting]);
                }
            }
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Settings updated successfully', 'battle-ledger'),
        ]);
    }
    
    /**
     * Get dashboard data
     */
    public static function get_dashboard_data($request) {
        global $wpdb;
        
        $tournaments_table = $wpdb->prefix . 'bl_tournaments';
        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        
        $stats = [
            'total_tournaments' => $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table"),
            'active_tournaments' => $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table WHERE status = 'active'"),
            'total_participants' => $wpdb->get_var("SELECT COUNT(*) FROM $participants_table"),
            'upcoming_tournaments' => $wpdb->get_var("SELECT COUNT(*) FROM $tournaments_table WHERE status = 'upcoming'"),
        ];
        
        return rest_ensure_response($stats);
    }
}
