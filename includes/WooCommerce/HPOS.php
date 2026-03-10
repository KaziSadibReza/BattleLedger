<?php
namespace BattleLedger\WooCommerce;

/**
 * High-Performance Order Storage (HPOS) support
 */
class HPOS {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
    }
    
    /**
     * Initialize HPOS support
     */
    public function init() {
        // Check if HPOS is enabled
        if ($this->is_hpos_enabled()) {
            // Use HPOS-compatible methods
            add_action('woocommerce_new_order', [$this, 'handle_new_order'], 10, 1);
        } else {
            // Use legacy post-based methods
            add_action('woocommerce_checkout_order_processed', [$this, 'handle_new_order'], 10, 1);
        }
    }
    
    /**
     * Check if HPOS is enabled
     */
    private function is_hpos_enabled() {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }
    
    /**
     * Handle new order
     */
    public function handle_new_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Process tournament-related orders
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if ($product && $product->get_type() === 'tournament_entry') {
                $this->process_tournament_entry($order, $item);
            }
        }
    }
    
    /**
     * Process tournament entry
     */
    private function process_tournament_entry($order, $item) {
        $tournament_id = $item->get_meta('tournament_id');
        
        if (!$tournament_id) {
            return;
        }
        
        // Register participant
        global $wpdb;
        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        
        $wpdb->insert($participants_table, [
            'tournament_id' => $tournament_id,
            'user_id' => $order->get_customer_id(),
            'status' => 'registered',
        ]);
    }
}
