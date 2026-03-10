<?php
namespace BattleLedger\WooCommerce;

/**
 * WooCommerce integration
 */
class Integration {
    
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
     * Initialize WooCommerce integration
     */
    public function init() {
        // Add custom product types, order meta, etc.
        add_filter('product_type_selector', [$this, 'add_tournament_product_type']);
    }
    
    /**
     * Add tournament product type
     */
    public function add_tournament_product_type($types) {
        $types['tournament_entry'] = __('Tournament Entry', 'battle-ledger');
        return $types;
    }
}
