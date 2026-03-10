<?php
namespace BattleLedger\Api;

/**
 * REST API controller base
 */
class RestController {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Admin routes
        AdminController::register_routes();
        
        // Tournament routes
        TournamentController::register_routes();
        
        // Settings routes (Form Customization & Email Templates)
        SettingsController::register_routes();
        
        // Rules routes
        RulesController::register_routes();
        
        // Diagnostic / Migration routes
        DiagnosticController::register_routes();
    }
    
    /**
     * Check if user can manage plugin
     */
    public static function check_permissions() {
        return current_user_can('manage_battle_ledger');
    }
}
