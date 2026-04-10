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
        $pending_count = $this->get_pending_withdrawal_count();

        $menu_title = __('BattleLedger', 'battle-ledger');
        if ($pending_count > 0) {
            $menu_title .= sprintf(
                ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
                $pending_count
            );
        }

        add_menu_page(
            __('BattleLedger', 'battle-ledger'),
            $menu_title,
            'manage_battle_ledger',
            'battle-ledger',
            [$this, 'render_admin_page'],
            $this->get_menu_icon(),
            30
        );
    }

    /**
     * Get pending withdrawal requests count for admin menu badges.
     */
    private function get_pending_withdrawal_count(): int {
        global $wpdb;

        $table = $wpdb->prefix . 'bl_withdrawal_requests';

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
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
