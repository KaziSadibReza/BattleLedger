<?php
/**
 * WooCommerce Wallet Integration Hooks
 * Handles automatic wallet crediting on successful payments
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\WooCommerce;

use BattleLedger\Wallet\WalletManager;

if (!defined('ABSPATH')) {
    exit;
}

class WalletIntegration {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register hooks immediately if WC is loaded, otherwise defer to plugins_loaded
        if (class_exists('WooCommerce')) {
            $this->register_hooks();
        } else {
            add_action('plugins_loaded', [$this, 'register_hooks']);
        }
    }
    
    /**
     * Register WooCommerce order hooks for wallet deposit processing.
     * This is the safety net for external payment gateways and admin status changes.
     * Direct deposits from the dashboard are credited inline by WalletPaymentController.
     * 
     * IMPORTANT: Only credit on 'completed' — NOT 'processing'.
     * Admin must mark order as completed before funds are released.
     */
    public function register_hooks() {
        // Only credit wallet when order is marked as completed
        add_action('woocommerce_order_status_completed', [$this, 'process_wallet_deposit'], 10, 1);
        
        // Protect against order deletion — log it if a credited deposit order is trashed/deleted
        add_action('woocommerce_before_trash_order', [$this, 'protect_deposit_order'], 10, 1);
        add_action('before_delete_post', [$this, 'protect_deposit_order'], 10, 1);
    }
    
    /**
     * Prevent or log deletion of wallet deposit orders that have been credited.
     * This ensures money can't be "lost" if an admin trashes a completed deposit order.
     */
    public function protect_deposit_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $is_wallet_deposit = $order->get_meta('_is_wallet_deposit');
        if ($is_wallet_deposit !== 'yes') {
            return;
        }
        
        $already_processed = $order->get_meta('_wallet_deposit_processed');
        if ($already_processed === 'yes') {
            $user_id = $order->get_meta('_wallet_user_id');
            $amount = floatval($order->get_meta('_wallet_amount'));
            $transaction_id = $order->get_meta('_wallet_transaction_id');
            
            error_log(sprintf(
                'BattleLedger WARNING: Wallet deposit order #%d (User #%d, Amount: %s, Transaction #%s) is being trashed/deleted. Wallet balance was already credited — no automatic reversal.',
                $order_id,
                $user_id,
                wc_price($amount),
                $transaction_id
            ));
            
            $order->add_order_note(sprintf(
                'WARNING: This wallet deposit order is being trashed/deleted. Wallet was already credited (Transaction #%s). No automatic reversal performed.',
                $transaction_id
            ));
        }
    }
    
    /**
     * Process wallet deposit when order is completed.
     * Only fires on woocommerce_order_status_completed.
     */
    public function process_wallet_deposit($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        // Check if this is a wallet deposit order
        $is_wallet_deposit = $order->get_meta('_is_wallet_deposit');
        
        if ($is_wallet_deposit !== 'yes') {
            return;
        }
        
        // Check if already processed (order meta level)
        $already_processed = $order->get_meta('_wallet_deposit_processed');
        
        if ($already_processed === 'yes') {
            error_log(sprintf('BattleLedger: Order #%d already processed — skipping duplicate credit.', $order_id));
            return;
        }
        
        // Get wallet deposit details
        $user_id = $order->get_meta('_wallet_user_id');
        $amount = floatval($order->get_meta('_wallet_amount'));
        
        if (!$user_id || $amount <= 0) {
            return;
        }
        
        // DB-level duplicate check: verify no transaction already exists for this order
        if (WalletManager::has_transaction_for_reference('order', $order_id)) {
            error_log(sprintf('BattleLedger: Transaction already exists for order #%d — marking as processed.', $order_id));
            $order->add_meta_data('_wallet_deposit_processed', 'yes', true);
            $order->save();
            return;
        }
        
        // Get payment method title for the transaction description
        $payment_title = $order->get_payment_method_title() ?: 'WooCommerce';
        
        try {
            // Remove any pending deposit record — it will be replaced by the real credit
            WalletManager::remove_pending_deposit($order_id);
            
            // Credit wallet
            $result = WalletManager::credit(
                $user_id,
                $amount,
                sprintf(__('Wallet deposit via %s (Order #%d)', 'battle-ledger'), $payment_title, $order_id),
                WalletManager::TYPE_WOOCOMMERCE,
                'order',
                $order_id
            );
            
            if ($result['success']) {
                // Mark as processed
                $order->add_meta_data('_wallet_deposit_processed', 'yes', true);
                $order->add_meta_data('_wallet_transaction_id', $result['transaction_id'], true);
                $order->save();
                
                // Add order note
                $order->add_order_note(sprintf(
                    'Wallet credited with %s via %s (Transaction #%d)',
                    wc_price($amount),
                    $payment_title,
                    $result['transaction_id']
                ));
                
                // Send email notification to customer
                $this->send_deposit_confirmation_email($user_id, $amount, $order_id);
                
                error_log(sprintf(
                    'BattleLedger: Wallet credited for user #%d — %s via %s — Order #%d — Transaction #%d',
                    $user_id,
                    wc_price($amount),
                    $payment_title,
                    $order_id,
                    $result['transaction_id']
                ));
            } else {
                error_log('BattleLedger: Failed to credit wallet — ' . ($result['message'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            error_log('BattleLedger Wallet Credit Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Send deposit confirmation email
     */
    private function send_deposit_confirmation_email($user_id, $amount, $order_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }
        
        $subject = 'Wallet Deposit Confirmed - BattleLedger';
        $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        
        $message = sprintf(
            "Hello %s,\n\nYour wallet deposit has been successfully completed!\n\nAmount: %s\nOrder ID: #%d\n\nYou can now use these funds for tournament entries and other activities.\n\nThank you for using BattleLedger!",
            $user->display_name,
            html_entity_decode(strip_tags(wc_price($amount))),
            $order_id
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
}
