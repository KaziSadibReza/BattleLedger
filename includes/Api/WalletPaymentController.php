<?php
/**
 * Wallet Payment Gateway Controller
 * Integrates with WooCommerce payment gateways
 * Processes payments directly via gateway API - no checkout page redirect
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Api;

use BattleLedger\Wallet\WalletManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class WalletPaymentController {
    
    /**
     * Register routes
     */
    public static function register_routes(): void {
        // Get available payment gateways
        register_rest_route('battle-ledger/v1', '/wallet/payment-gateways', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_payment_gateways'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Process deposit
        register_rest_route('battle-ledger/v1', '/wallet/deposit', [
            'methods' => 'POST',
            'callback' => [self::class, 'process_deposit'],
            'permission_callback' => 'is_user_logged_in',
            'args' => [
                'amount' => [
                    'required' => true,
                    'type' => 'number',
                    'minimum' => 0.01,
                ],
                'payment_method' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        
        // Check order status (for polling after external payment)
        register_rest_route('battle-ledger/v1', '/wallet/order-status/(?P<order_id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'check_order_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Withdrawal route is now handled by WalletController::request_withdrawal()
        // (freeze-based flow — no immediate debit)
        
        // Get wallet stats
        register_rest_route('battle-ledger/v1', '/wallet/user/(?P<user_id>\d+)/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_wallet_stats'],
            'permission_callback' => [self::class, 'check_user_permission'],
        ]);
    }
    
    /**
     * Get available WooCommerce payment gateways
     */
    public static function get_payment_gateways(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response([
                'gateways' => [],
                'message' => 'WooCommerce is not active',
            ], 200);
        }
        
        $wc_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $gateways = [];
        
        foreach ($wc_gateways as $gateway_id => $gateway) {
            if ($gateway->enabled === 'yes') {
                $gateways[] = [
                    'id' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'enabled' => true,
                    'supports' => $gateway->supports,
                ];
            }
        }
        
        return new WP_REST_Response([
            'gateways' => $gateways,
        ], 200);
    }
    
    /**
     * Strip HTML from wc_price() output for use in REST/JSON responses.
     */
    private static function clean_price($amount): string {
        return html_entity_decode(strip_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Ensure WooCommerce session and cart are available in REST context.
     * Many gateways call WC()->cart->empty_cart() during process_payment.
     */
    private static function ensure_wc_context(): void {
        if (!did_action('woocommerce_loaded')) {
            return;
        }
        
        // Initialize customer session if not set
        if (is_null(WC()->session)) {
            WC()->initialize_session();
        }
        
        // Initialize cart if not loaded
        if (is_null(WC()->cart)) {
            WC()->initialize_cart();
        }
        
        // Set customer data from current user
        if (WC()->customer && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $customer = new \WC_Customer($user_id);
            WC()->customer = $customer;
        }
    }
    
    /**
     * Process wallet deposit via WooCommerce.
     * Processes payment directly through the gateway — no checkout page.
     * 
     * For direct gateways (COD, BACS, etc.): Payment processed inline, returns success.
     * For redirect gateways (PayPal, SSLCommerz, bKash): Returns redirect_url for the gateway.
     */
    public static function process_deposit(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('WooCommerce')) {
            // Fallback: credit wallet directly if WooCommerce is not available
            $user_id = get_current_user_id();
            $amount = floatval($request->get_param('amount'));
            
            if ($amount <= 0) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Invalid amount',
                ], 400);
            }
            
            $result = WalletManager::credit(
                $user_id,
                $amount,
                __('Direct wallet deposit', 'battle-ledger'),
                WalletManager::TYPE_CREDIT
            );
            
            if (is_wp_error($result)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $result->get_error_message(),
                ], 400);
            }

            // In-app notification: wallet deposit (admin)
            $dep_user = get_userdata($user_id);
            \BattleLedger\Core\NotificationManager::wallet_deposit(
                $dep_user ? $dep_user->display_name : 'User #' . $user_id,
                $amount
            );

            // In-app notification: deposit confirmed (user)
            \BattleLedger\Core\NotificationManager::user_deposit_confirmed(
                $user_id,
                $amount
            );

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Deposit completed successfully',
                'new_balance' => $result['new_balance'],
                'requires_redirect' => false,
            ], 200);
        }
        
        $user_id = get_current_user_id();
        $amount = floatval($request->get_param('amount'));
        $payment_method = sanitize_text_field($request->get_param('payment_method'));
        
        if ($amount <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid amount',
            ], 400);
        }
        
        try {
            // Ensure WC session/cart exist so gateways don't crash
            self::ensure_wc_context();
            
            // Validate payment gateway exists and is enabled
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (!isset($gateways[$payment_method])) {
                throw new \Exception(__('Selected payment method is not available', 'battle-ledger'));
            }
            $gateway = $gateways[$payment_method];
            
            // Create WooCommerce order for wallet deposit
            $order = wc_create_order([
                'customer_id' => $user_id,
                'status' => 'pending',
            ]);
            
            if (is_wp_error($order)) {
                throw new \Exception($order->get_error_message());
            }
            
            // Get or create wallet product
            $product = self::get_or_create_wallet_product();
            
            if (!$product) {
                throw new \Exception('Failed to create wallet deposit product');
            }
            
            // Add wallet deposit as order item
            $order->add_product($product, 1, [
                'subtotal' => $amount,
                'total' => $amount,
            ]);
            
            // Set payment method on order
            $order->set_payment_method($gateway);
            
            // Populate billing from user profile
            $user = get_userdata($user_id);
            $order->set_billing_email($user->user_email);
            $order->set_billing_first_name(get_user_meta($user_id, 'billing_first_name', true) ?: $user->first_name ?: $user->display_name);
            $order->set_billing_last_name(get_user_meta($user_id, 'billing_last_name', true) ?: $user->last_name ?: '');
            
            // Store wallet deposit metadata
            $order->add_meta_data('_is_wallet_deposit', 'yes');
            $order->add_meta_data('_wallet_user_id', $user_id);
            $order->add_meta_data('_wallet_amount', $amount);
            
            $order->calculate_totals();
            $order->save();
            
            // Add order note
            $order->add_order_note(sprintf(
                'Wallet deposit of %s via %s — processed from dashboard',
                wc_price($amount),
                $gateway->get_title()
            ));
            
            // Process payment through the gateway directly
            $result = $gateway->process_payment($order->get_id());
            
            if (!$result || $result['result'] !== 'success') {
                throw new \Exception(__('Payment gateway returned an error. Please try again.', 'battle-ledger'));
            }
            
            $redirect_url = $result['redirect'] ?? '';
            $site_url = home_url();
            
            // Determine if this is an internal redirect (payment completed) or external (user action needed)
            $is_internal_redirect = empty($redirect_url) 
                || strpos($redirect_url, $site_url) === 0 
                || strpos($redirect_url, 'order-received') !== false;
            
            if ($is_internal_redirect) {
                // Direct gateway (COD, BACS, Stripe, etc.) — processed successfully
                // Reload order to get the latest status after process_payment
                $order = wc_get_order($order->get_id());
                $order_status = $order->get_status();
                $order_id = $order->get_id();
                
                // Only credit wallet when order is fully completed (not 'processing')
                // COD/BACS set status to 'processing' — admin must mark 'completed' to release funds
                $paid_statuses = ['completed'];
                $pending_statuses = ['on-hold', 'pending', 'processing'];
                
                if (in_array($order_status, $paid_statuses, true)) {
                    // Payment confirmed — credit wallet directly (don't rely on hooks)
                    $already_processed = $order->get_meta('_wallet_deposit_processed');
                    
                    // Double-check at DB level too — prevent any duplicate credit
                    $db_has_transaction = WalletManager::has_transaction_for_reference('order', $order_id);
                    
                    if ($already_processed !== 'yes' && !$db_has_transaction) {
                        // Remove any pending deposit record first
                        WalletManager::remove_pending_deposit($order_id);
                        $credit_result = WalletManager::credit(
                            $user_id,
                            $amount,
                            sprintf(__('Wallet deposit via %s (Order #%d)', 'battle-ledger'), $gateway->get_title(), $order_id),
                            WalletManager::TYPE_WOOCOMMERCE,
                            'order',
                            $order_id
                        );
                        
                        if (is_wp_error($credit_result)) {
                            error_log('BattleLedger Deposit Credit Error: ' . $credit_result->get_error_message());
                            return new WP_REST_Response([
                                'success' => false,
                                'message' => __('Payment was successful but we could not credit your wallet. Please contact support with Order #', 'battle-ledger') . $order_id,
                                'order_id' => $order_id,
                                'order_status' => $order_status,
                                'requires_redirect' => false,
                            ], 500);
                        }
                        
                        // Mark order as wallet-processed to prevent double credit from hooks
                        $order->add_meta_data('_wallet_deposit_processed', 'yes', true);
                        $order->add_meta_data('_wallet_transaction_id', $credit_result['transaction_id'], true);
                        $order->save();
                        
                        $order->add_order_note(sprintf(
                            'Wallet credited with %s (Transaction #%d)',
                            wc_price($amount),
                            $credit_result['transaction_id']
                        ));
                        
                        $new_balance = $credit_result['new_balance'];
                    } else {
                        $new_balance = WalletManager::get_balance($user_id);
                    }
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => sprintf(
                            __('Deposit of %s successful! Funds have been added to your wallet.', 'battle-ledger'),
                            self::clean_price($amount)
                        ),
                        'order_id' => $order_id,
                        'order_status' => $order_status,
                        'new_balance' => $new_balance,
                        'wallet_credited' => true,
                        'requires_redirect' => false,
                    ], 200);
                    
                } elseif (in_array($order_status, $pending_statuses, true)) {
                    // Payment not yet confirmed — wallet will be credited when order status changes
                    $new_balance = WalletManager::get_balance($user_id);
                    
                    // Record pending deposit in transaction history so user can see it
                    WalletManager::record_pending_deposit(
                        $user_id,
                        $amount,
                        sprintf(__('Pending deposit via %s (Order #%d)', 'battle-ledger'), $gateway->get_title(), $order_id),
                        $order_id
                    );
                    
                    $clean_amount = self::clean_price($amount);
                    
                    $pending_messages = [
                        'processing' => sprintf(
                            __('Order #%d received via %s! Your wallet will be credited with %s once the order is marked as completed.', 'battle-ledger'),
                            $order_id,
                            $gateway->get_title(),
                            $clean_amount
                        ),
                        'on-hold' => sprintf(
                            __('Order #%d created via %s. Your wallet will be credited with %s once payment is confirmed.', 'battle-ledger'),
                            $order_id,
                            $gateway->get_title(),
                            $clean_amount
                        ),
                        'pending' => sprintf(
                            __('Order #%d created via %s. Awaiting payment of %s. Your wallet will be credited after payment.', 'battle-ledger'),
                            $order_id,
                            $gateway->get_title(),
                            $clean_amount
                        ),
                    ];
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => $pending_messages[$order_status] ?? sprintf(
                            __('Order #%d created with status: %s. Wallet will be updated once payment completes.', 'battle-ledger'),
                            $order_id,
                            ucfirst($order_status)
                        ),
                        'order_id' => $order_id,
                        'order_status' => $order_status,
                        'new_balance' => $new_balance,
                        'wallet_credited' => false,
                        'requires_redirect' => false,
                    ], 200);
                    
                } else {
                    // Unexpected status (failed, cancelled, etc.)
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => sprintf(
                            __('Payment was not successful. Order #%d status: %s. Please try again or contact support.', 'battle-ledger'),
                            $order_id,
                            ucfirst($order_status)
                        ),
                        'order_id' => $order_id,
                        'order_status' => $order_status,
                        'requires_redirect' => false,
                    ], 400);
                }
            } else {
                // External redirect gateway (PayPal, SSLCommerz, bKash, Nagad, etc.)
                return new WP_REST_Response([
                    'success' => true,
                    'message' => sprintf(
                        __('Redirecting to %s to complete payment...', 'battle-ledger'),
                        $gateway->get_title()
                    ),
                    'order_id' => $order->get_id(),
                    'requires_redirect' => true,
                    'redirect_url' => $redirect_url,
                    'gateway_title' => $gateway->get_title(),
                ], 200);
            }
            
        } catch (\Exception $e) {
            error_log('BattleLedger Deposit Error: ' . $e->getMessage());
            
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Check order status — used by frontend to poll after external payment
     */
    public static function check_order_status(WP_REST_Request $request): WP_REST_Response {
        $order_id = intval($request->get_param('order_id'));
        $user_id = get_current_user_id();
        
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'WooCommerce is not active',
            ], 400);
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }
        
        // Security: user can only check their own orders
        $order_user_id = $order->get_meta('_wallet_user_id');
        if (intval($order_user_id) !== $user_id && !current_user_can('manage_options')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }
        
        $status = $order->get_status();
        $is_paid = $status === 'completed';
        $is_pending = in_array($status, ['pending', 'on-hold', 'processing'], true);
        $new_balance = WalletManager::get_balance($user_id);
        
        return new WP_REST_Response([
            'success' => true,
            'order_id' => $order_id,
            'status' => $status,
            'is_paid' => $is_paid,
            'is_pending' => $is_pending,
            'new_balance' => $new_balance,
        ], 200);
    }
    
    // process_withdrawal() removed — withdrawal is now handled by
    // WalletController::request_withdrawal() with freeze-based flow.
    
    /**
     * Get wallet statistics for user
     */
    public static function get_wallet_stats(WP_REST_Request $request): WP_REST_Response {
        $user_id = intval($request->get_param('user_id'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        // Get total credits (includes 'woocommerce' type from wallet deposits)
        $total_credits = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table 
            WHERE user_id = %d 
            AND type IN ('credit', 'admin_credit', 'prize', 'refund', 'woocommerce')",
            $user_id
        )) ?: 0;
        
        // Get total debits
        $total_debits = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table 
            WHERE user_id = %d 
            AND type IN ('debit', 'admin_debit', 'entry_fee', 'withdrawal', 'purchase')",
            $user_id
        )) ?: 0;
        
        // Get total transactions
        $total_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        // Get pending transactions
        $pending_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE user_id = %d 
            AND metadata LIKE %s",
            $user_id,
            '%"status":"pending"%'
        ));
        
        // Get pending withdrawal total from withdrawal_requests table
        $wr_table = $wpdb->prefix . 'bl_withdrawal_requests';
        $pending_withdrawals = 0;
        $completed_withdrawals = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$wr_table'") === $wr_table) {
            $pending_withdrawals = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $wr_table WHERE user_id = %d AND status = 'pending'",
                $user_id
            )) ?: 0;
            $completed_withdrawals = $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM $wr_table WHERE user_id = %d AND status = 'completed'",
                $user_id
            )) ?: 0;
        }
        
        return new WP_REST_Response([
            'total_credits' => floatval($total_credits),
            'total_debits' => floatval($total_debits),
            'total_transactions' => intval($total_transactions),
            'pending_transactions' => intval($pending_transactions),
            'pending_withdrawals' => floatval($pending_withdrawals),
            'completed_withdrawals' => floatval($completed_withdrawals),
        ], 200);
    }
    
    /**
     * Get or create wallet deposit product
     */
    private static function get_or_create_wallet_product() {
        $product_id = get_option('battleledger_wallet_product_id');
        
        if ($product_id && get_post($product_id)) {
            return wc_get_product($product_id);
        }
        
        // Create virtual product for wallet deposits
        $product = new \WC_Product_Simple();
        $product->set_name('Wallet Deposit');
        $product->set_status('private');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->save();
        
        update_option('battleledger_wallet_product_id', $product->get_id());
        
        return $product;
    }
    
    /**
     * Check if user has permission to access their own wallet
     */
    public static function check_user_permission(WP_REST_Request $request): bool {
        $user_id = intval($request->get_param('user_id'));
        $current_user_id = get_current_user_id();
        
        // User can access own data, or admin can access any
        return $user_id === $current_user_id || current_user_can('manage_options');
    }
}
