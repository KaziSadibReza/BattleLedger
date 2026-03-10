<?php
namespace BattleLedger\Admin;

/**
 * Admin menu management
 */
class Menu {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }
    
    /**
     * Register admin menu
     */
    public function register_menu() {
        add_menu_page(
            __('BattleLedger', 'battle-ledger'),
            __('BattleLedger', 'battle-ledger'),
            'manage_options',
            'battle-ledger',
            [$this, 'render_admin_page'],
            $this->get_menu_icon(),
            30
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        echo '<div id="battle-ledger-root"></div>';
    }
    
    /**
     * Get menu icon (dashicons game controller)
     */
    private function get_menu_icon() {
        return 'dashicons-games';
    }
}
