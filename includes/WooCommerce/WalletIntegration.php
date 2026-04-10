<?php
/**
 * WooCommerce Wallet Integration Hooks
 * Handles automatic wallet crediting on successful payments
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\WooCommerce;

use BattleLedger\Api\SettingsController;
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

        // Keep pending deposit visibility in sync across non-completed states
        add_action('woocommerce_order_status_pending', [$this, 'track_pending_wallet_deposit'], 10, 1);
        add_action('woocommerce_order_status_on-hold', [$this, 'track_pending_wallet_deposit'], 10, 1);
        add_action('woocommerce_order_status_processing', [$this, 'track_pending_wallet_deposit'], 10, 1);

        // Handle terminal non-success statuses for wallet deposit orders
        add_action('woocommerce_order_status_failed', [$this, 'handle_non_completed_wallet_deposit'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_non_completed_wallet_deposit'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'handle_non_completed_wallet_deposit'], 10, 1);
        
        // Protect against order deletion — log it if a credited deposit order is trashed/deleted
        add_action('woocommerce_before_trash_order', [$this, 'protect_deposit_order'], 10, 1);
        add_action('before_delete_post', [$this, 'protect_deposit_order'], 10, 1);
    }

    /**
     * Ensure pending deposit entry exists while order is not completed yet.
     */
    public function track_pending_wallet_deposit($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        if ($order->get_meta('_is_wallet_deposit') !== 'yes') {
            return;
        }

        if ($order->get_meta('_wallet_deposit_processed') === 'yes') {
            return;
        }

        $user_id = (int) $order->get_meta('_wallet_user_id');
        $amount = floatval($order->get_meta('_wallet_amount'));

        if ($user_id <= 0 || $amount <= 0) {
            return;
        }

        $status = (string) $order->get_status();
        $payment_title = $order->get_payment_method_title() ?: 'WooCommerce';
        $description = sprintf(
            __('Pending deposit via %s (Order #%d)', 'battle-ledger'),
            $payment_title,
            $order_id
        );

        WalletManager::record_pending_deposit(
            $user_id,
            $amount,
            $description,
            $order_id
        );

        WalletManager::set_pending_deposit_status(
            $order_id,
            $status,
            sprintf(
                __('Deposit via %s (Order #%d) - %s', 'battle-ledger'),
                $payment_title,
                $order_id,
                ucfirst(str_replace('-', ' ', $status))
            )
        );
    }

    /**
     * Keep pending deposit record visible and mark status when order ends non-success.
     */
    public function handle_non_completed_wallet_deposit($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        if ($order->get_meta('_is_wallet_deposit') !== 'yes') {
            return;
        }

        if ($order->get_meta('_wallet_deposit_processed') === 'yes') {
            // Do not auto-reverse a completed wallet credit.
            return;
        }

        $user_id = (int) $order->get_meta('_wallet_user_id');
        $amount = floatval($order->get_meta('_wallet_amount'));

        if ($user_id <= 0 || $amount <= 0) {
            return;
        }

        $status = (string) $order->get_status();
        $payment_title = $order->get_payment_method_title() ?: 'WooCommerce';
        $description = sprintf(
            __('Deposit via %s (Order #%d) - %s', 'battle-ledger'),
            $payment_title,
            $order_id,
            ucfirst(str_replace('-', ' ', $status))
        );

        $updated = WalletManager::set_pending_deposit_status($order_id, $status, $description);
        if (!$updated) {
            WalletManager::record_pending_deposit($user_id, $amount, $description, $order_id);
            WalletManager::set_pending_deposit_status($order_id, $status, $description);
        }

        \BattleLedger\Core\NotificationManager::user_deposit_status_update($user_id, $amount, $status);

        $order->add_order_note(sprintf(
            'Wallet deposit not credited because order status is now "%s".',
            $order->get_status()
        ));
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

                // Notify admin feed + user feed (also triggers push for the user feed)
                $dep_user = get_userdata((int) $user_id);
                \BattleLedger\Core\NotificationManager::wallet_deposit(
                    $dep_user ? $dep_user->display_name : 'User #' . (int) $user_id,
                    $amount,
                    WalletManager::get_currency()
                );
                \BattleLedger\Core\NotificationManager::user_deposit_confirmed(
                    (int) $user_id,
                    $amount,
                    WalletManager::get_currency()
                );
                
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

        $dashboard_url = \BattleLedger\Core\PageInstaller::get_page_url('dashboard');
        if (!$dashboard_url) {
            $dashboard_url = home_url('/');
        }

        $user_name = !empty($user->display_name) ? $user->display_name : $user->user_login;
        $amount_formatted = html_entity_decode(strip_tags(wc_price($amount)));

        $replacements = [
            '{{user_name}}' => $user_name,
            '{{user_email}}' => $user->user_email,
            '{{deposit_amount}}' => $amount_formatted,
            '{{order_id}}' => (string) $order_id,
            '{{wallet_url}}' => $dashboard_url,
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{current_year}}' => gmdate('Y'),
        ];

        $template = SettingsController::get_email_template('wallet_deposit_confirmed');
        if (is_array($template) && !empty($template['enabled'])) {
            $subject = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $template['header']['subject'] ?? 'Wallet Deposit Confirmed - {{site_name}}'
            );

            $message = SettingsController::build_email_html($template, $replacements);

            wp_mail(
                $user->user_email,
                $subject,
                $message,
                ['Content-Type: text/html; charset=UTF-8']
            );
            return;
        }

        // Fallback to plain text if template is disabled or unavailable.
        $fallback_subject = str_replace('{{site_name}}', get_bloginfo('name'), 'Wallet Deposit Confirmed - {{site_name}}');
        $fallback_message = sprintf(
            "Hello %s,\n\nYour wallet deposit has been successfully completed!\n\nAmount: %s\nOrder ID: #%d\n\nYou can now use these funds for tournament entries and other activities.\n\nThank you for using BattleLedger!",
            $user_name,
            $amount_formatted,
            $order_id
        );

        wp_mail($user->user_email, $fallback_subject, $fallback_message);
    }
}
