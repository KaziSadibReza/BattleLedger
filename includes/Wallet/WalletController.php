<?php
/**
 * Wallet REST API Controller
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Wallet;

use BattleLedger\Api\RestController;

if (!defined('ABSPATH')) {
    exit;
}

class WalletController {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'battle-ledger/v1';
    
    /**
     * Register REST API routes
     */
    public static function register_routes(): void {
        // Get wallet statistics
        register_rest_route(self::NAMESPACE, '/wallet/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_stats'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
        
        // Get all wallets
        register_rest_route(self::NAMESPACE, '/wallets', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_wallets'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                ],
                'search' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'status' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'order_by' => [
                    'type' => 'string',
                    'default' => 'created_at',
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC',
                ],
            ],
        ]);
        
        // Get single wallet
        register_rest_route(self::NAMESPACE, '/wallet/(?P<user_id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_wallet'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        ]);
        
        // Credit wallet (add money)
        register_rest_route(self::NAMESPACE, '/wallet/credit', [
            'methods' => 'POST',
            'callback' => [self::class, 'credit_wallet'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'amount' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'description' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
        
        // Debit wallet (remove money)
        register_rest_route(self::NAMESPACE, '/wallet/debit', [
            'methods' => 'POST',
            'callback' => [self::class, 'debit_wallet'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'amount' => [
                    'required' => true,
                    'type' => 'number',
                ],
                'description' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
        
        // Update wallet status
        register_rest_route(self::NAMESPACE, '/wallet/(?P<user_id>\d+)/status', [
            'methods' => 'PUT',
            'callback' => [self::class, 'update_wallet_status'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
                'status' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['active', 'suspended', 'closed'],
                ],
            ],
        ]);
        
        // Get transactions
        register_rest_route(self::NAMESPACE, '/wallet/transactions', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_transactions'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 20,
                ],
                'user_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
                'type' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ]);
        
        // Search users
        register_rest_route(self::NAMESPACE, '/wallet/search-users', [
            'methods' => 'GET',
            'callback' => [self::class, 'search_users'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'search' => [
                    'required' => true,
                    'type' => 'string',
                ],
            ],
        ]);
        
        // Get current user's wallet (public endpoint for logged-in users)
        register_rest_route(self::NAMESPACE, '/wallet/my-wallet', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_my_wallet'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
        
        // Get current user's transactions (public endpoint for logged-in users)
        register_rest_route(self::NAMESPACE, '/wallet/my-transactions', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_my_transactions'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                ],
            ],
        ]);
        
        // Withdraw request (public endpoint for logged-in users)
        register_rest_route(self::NAMESPACE, '/wallet/withdraw', [
            'methods' => 'POST',
            'callback' => [self::class, 'request_withdrawal'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'args' => [
                'amount' => [
                    'required' => true,
                    'type' => 'number',
                ],
            ],
        ]);
        
        // ── Withdrawal Methods (Admin) ──────────────────────────────
        register_rest_route(self::NAMESPACE, '/wallet/withdrawal-methods', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_withdrawal_methods'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
        
        register_rest_route(self::NAMESPACE, '/wallet/withdrawal-methods', [
            'methods' => 'POST',
            'callback' => [self::class, 'save_withdrawal_methods'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
        
        // ── Withdrawal Requests (Admin) ─────────────────────────────
        register_rest_route(self::NAMESPACE, '/wallet/withdrawal-requests', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_withdrawal_requests'],
            'permission_callback' => [RestController::class, 'check_permissions'],
            'args' => [
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 20],
                'status'   => ['type' => 'string', 'default' => ''],
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/wallet/withdrawal-requests/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [self::class, 'complete_withdrawal'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
        
        register_rest_route(self::NAMESPACE, '/wallet/withdrawal-requests/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [self::class, 'cancel_withdrawal'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
    }
    
    /**
     * Get current user's wallet
     */
    public static function get_my_wallet(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $wallet = WalletManager::get_wallet($user_id);
        
        // Get WooCommerce currency
        $currency = 'USD';
        if (function_exists('get_woocommerce_currency')) {
            $currency = get_woocommerce_currency();
        }
        
        // Calculate frozen amount (pending withdrawals)
        $frozen = self::get_pending_withdrawal_total($user_id);
        
        if (!$wallet) {
            return rest_ensure_response([
                'id' => 0,
                'user_id' => $user_id,
                'balance' => '0.00',
                'available_balance' => '0.00',
                'frozen_balance' => '0.00',
                'currency' => $currency,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'has_wallet' => false,
            ]);
        }
        
        $balance = floatval($wallet->balance);
        $available = max(0, $balance - $frozen);
        
        return rest_ensure_response([
            'id' => (int) $wallet->id,
            'user_id' => (int) $wallet->user_id,
            'balance' => number_format($balance, 2, '.', ''),
            'available_balance' => number_format($available, 2, '.', ''),
            'frozen_balance' => number_format($frozen, 2, '.', ''),
            'currency' => $wallet->currency ?: $currency,
            'status' => $wallet->status,
            'created_at' => $wallet->created_at,
            'updated_at' => $wallet->updated_at,
            'has_wallet' => true,
        ]);
    }
    
    /**
     * Get wallet statistics
     */
    public static function get_stats(\WP_REST_Request $request) {
        $stats = WalletManager::get_statistics();
        return rest_ensure_response($stats);
    }
    
    /**
     * Get all wallets
     */
    public static function get_wallets(\WP_REST_Request $request) {
        $result = WalletManager::get_all_wallets([
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'search' => $request->get_param('search'),
            'status' => $request->get_param('status'),
            'order_by' => $request->get_param('order_by'),
            'order' => $request->get_param('order'),
        ]);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Get single wallet
     */
    public static function get_wallet(\WP_REST_Request $request) {
        $user_id = $request->get_param('user_id');
        $wallet = WalletManager::get_wallet($user_id);
        
        if (!$wallet) {
            return new \WP_Error('not_found', __('Wallet not found', 'battle-ledger'), ['status' => 404]);
        }
        
        // Get user info
        $user = get_userdata($user_id);
        $wallet->user_email = $user ? $user->user_email : '';
        $wallet->display_name = $user ? $user->display_name : '';
        $wallet->phone = $user ? \BattleLedger\Api\UserController::get_phone($user->ID) : '';
        
        // Get recent transactions
        $transactions = WalletManager::get_transactions($user_id, ['per_page' => 10]);
        
        return rest_ensure_response([
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }
    
    /**
     * Credit wallet
     */
    public static function credit_wallet(\WP_REST_Request $request) {
        $user_id = $request->get_param('user_id');
        $amount = floatval($request->get_param('amount'));
        $description = sanitize_text_field($request->get_param('description'));
        
        if (empty($description)) {
            $description = __('Admin credit', 'battle-ledger');
        }
        
        $result = WalletManager::credit(
            $user_id,
            $amount,
            $description,
            WalletManager::TYPE_ADMIN_CREDIT
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(__('Successfully added %s to wallet', 'battle-ledger'), function_exists('wc_price') ? wc_price($amount) : number_format($amount, 2)),
            'new_balance' => $result['new_balance'],
            'transaction_id' => $result['transaction_id'],
        ]);
    }
    
    /**
     * Debit wallet
     */
    public static function debit_wallet(\WP_REST_Request $request) {
        $user_id = $request->get_param('user_id');
        $amount = floatval($request->get_param('amount'));
        $description = sanitize_text_field($request->get_param('description'));
        
        if (empty($description)) {
            $description = __('Admin debit', 'battle-ledger');
        }
        
        $result = WalletManager::debit(
            $user_id,
            $amount,
            $description,
            WalletManager::TYPE_ADMIN_DEBIT
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf(__('Successfully deducted %s from wallet', 'battle-ledger'), function_exists('wc_price') ? wc_price($amount) : number_format($amount, 2)),
            'new_balance' => $result['new_balance'],
            'transaction_id' => $result['transaction_id'],
        ]);
    }
    
    /**
     * Update wallet status
     */
    public static function update_wallet_status(\WP_REST_Request $request) {
        $user_id = $request->get_param('user_id');
        $status = $request->get_param('status');
        
        $result = WalletManager::update_status($user_id, $status);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Wallet status updated', 'battle-ledger'),
        ]);
    }
    
    /**
     * Get transactions
     */
    public static function get_transactions(\WP_REST_Request $request) {
        $result = WalletManager::get_all_transactions([
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'user_id' => $request->get_param('user_id'),
            'type' => $request->get_param('type'),
        ]);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Search users
     */
    public static function search_users(\WP_REST_Request $request) {
        $search = $request->get_param('search');
        $users = WalletManager::search_users($search);
        
        return rest_ensure_response($users);
    }
    
    /**
     * Get current user's transactions
     */
    public static function get_my_transactions(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: $request->get_param('limit') ?: 10;
        $offset = ($page - 1) * $per_page;
        
        $wallet = WalletManager::get_wallet($user_id);
        
        if (!$wallet) {
            return rest_ensure_response([
                'transactions' => [],
                'total' => 0,
            ]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        // Get total count
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE wallet_id = %d",
            $wallet->id
        ));
        
        // Get paginated transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE wallet_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $wallet->id,
            $per_page,
            $offset
        ));
        
        $formatted = array_map(function($tx) {
            return [
                'id' => (int) $tx->id,
                'type' => $tx->type,
                'amount' => $tx->amount,
                'description' => $tx->description,
                'created_at' => $tx->created_at,
                'balance_after' => $tx->balance_after,
                'reference_type' => $tx->reference_type ?? null,
                'reference_id' => $tx->reference_id ? (int) $tx->reference_id : null,
                'created_by' => (int) $tx->created_by,
            ];
        }, $transactions);
        
        return rest_ensure_response([
            'transactions' => $formatted,
            'total' => (int) $total,
        ]);
    }
    
    /**
     * Request withdrawal — now stores in bl_withdrawal_requests + debits wallet immediately.
     */
    public static function request_withdrawal(\WP_REST_Request $request) {
        global $wpdb;
        
        $user_id   = get_current_user_id();
        $amount    = floatval($request->get_param('amount'));
        $method_id = sanitize_text_field($request->get_param('method') ?? '');
        $details   = $request->get_param('details');
        
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Invalid withdrawal amount', 'battle-ledger'), ['status' => 400]);
        }
        
        // Validate method exists in configured methods
        $methods = get_option('battleledger_withdrawal_methods', []);
        $method  = null;
        foreach ($methods as $m) {
            if ($m['id'] === $method_id && !empty($m['enabled'])) {
                $method = $m;
                break;
            }
        }
        
        if (!$method) {
            return new \WP_Error('invalid_method', __('Selected withdrawal method is not available', 'battle-ledger'), ['status' => 400]);
        }
        
        // Validate required fields
        if (!empty($method['fields'])) {
            foreach ($method['fields'] as $field) {
                if (!empty($field['required']) && empty($details[$field['key']])) {
                    return new \WP_Error(
                        'missing_field',
                        sprintf(__('%s is required', 'battle-ledger'), $field['label']),
                        ['status' => 400]
                    );
                }
            }
        }
        
        $wallet = WalletManager::get_wallet($user_id);
        
        if (!$wallet) {
            return new \WP_Error('no_wallet', __('No wallet found', 'battle-ledger'), ['status' => 404]);
        }
        
        if ($wallet->status !== 'active') {
            return new \WP_Error('wallet_inactive', __('Your wallet is not active', 'battle-ledger'), ['status' => 403]);
        }
        
        // Check AVAILABLE balance (actual balance minus already-pending withdrawals)
        $pending_total = self::get_pending_withdrawal_total($user_id);
        $available = floatval($wallet->balance) - $pending_total;
        
        if ($available < $amount) {
            return new \WP_Error('insufficient_balance', __('Insufficient available balance (some funds may be frozen for pending withdrawals)', 'battle-ledger'), ['status' => 400]);
        }
        
        // Sanitize details
        $safe_details = [];
        if (is_array($details)) {
            foreach ($details as $key => $val) {
                $safe_details[sanitize_text_field($key)] = sanitize_text_field($val);
            }
        }
        
        // Ensure withdrawal requests table exists (safety net — use raw SQL, not dbDelta)
        $table = $wpdb->prefix . 'bl_withdrawal_requests';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $charset_collate = $wpdb->get_charset_collate();
            $wpdb->query("CREATE TABLE IF NOT EXISTS $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                amount decimal(12,2) NOT NULL,
                method_id varchar(100) NOT NULL,
                method_name varchar(255) NOT NULL,
                method_details longtext,
                status varchar(20) NOT NULL DEFAULT 'pending',
                transaction_id bigint(20) unsigned,
                refund_transaction_id bigint(20) unsigned,
                admin_note text,
                processed_by bigint(20) unsigned,
                processed_at datetime,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;");
        }
        
        // Create withdrawal request — NO debit yet. Amount is frozen (held) until admin approves.
        $inserted = $wpdb->insert($table, [
            'user_id'        => $user_id,
            'amount'         => $amount,
            'method_id'      => $method_id,
            'method_name'    => $method['name'],
            'method_details' => wp_json_encode($safe_details),
            'status'         => 'pending',
            'transaction_id' => null, // No transaction yet — will be created on admin approval
            'created_at'     => current_time('mysql'),
        ]);
        
        if (!$inserted) {
            return new \WP_Error(
                'insert_failed',
                __('Could not create withdrawal request. Please try again.', 'battle-ledger'),
                ['status' => 500]
            );
        }
        
        $request_id = $wpdb->insert_id;
        
        // Notify admin
        $admin_email = get_option('admin_email');
        $user = get_userdata($user_id);
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD';

        // In-app notification: withdrawal requested (admin)
        \BattleLedger\Core\NotificationManager::withdrawal_requested(
            $user ? $user->display_name : 'User #' . $user_id,
            (float) $amount,
            $currency
        );

        // In-app notification: confirmation for the user
        \BattleLedger\Core\NotificationManager::user_withdrawal_submitted(
            $user_id,
            (float) $amount,
            $currency
        );

        wp_mail(
            $admin_email,
            sprintf('BattleLedger: New Withdrawal Request #%d', $request_id),
            sprintf(
                "User: %s (ID: %d)\nAmount: %s %s\nMethod: %s\n\nPlease review in BattleLedger > Wallets > Withdrawals.",
                $user->display_name,
                $user_id,
                number_format($amount, 2),
                $currency,
                $method['name']
            )
        );
        
        return rest_ensure_response([
            'success'     => true,
            'message'     => __('Withdrawal request submitted successfully. The amount is frozen until admin reviews your request.', 'battle-ledger'),
            'request_id'  => $request_id,
            'new_balance'  => floatval($wallet->balance), // Balance unchanged — amount is just frozen
            'frozen'       => $pending_total + $amount,
        ]);
    }
    
    /**
     * Get total pending (frozen) withdrawal amount for a user.
     */
    private static function get_pending_withdrawal_total(int $user_id): float {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_withdrawal_requests';
        
        // Safety: if table doesn't exist, return 0
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0.0;
        }
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $table WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));
        
        return floatval($total);
    }
    
    // ── Withdrawal Methods (Admin Settings) ──────────────────────────
    
    /**
     * Get configured withdrawal methods.
     * Available to all logged-in users (users need to see available methods).
     */
    public static function get_withdrawal_methods(\WP_REST_Request $request) {
        $methods = get_option('battleledger_withdrawal_methods', []);
        
        // Non-admins only see enabled methods (hide disabled ones + admin-only fields)
        if (!current_user_can('manage_options')) {
            $methods = array_values(array_filter($methods, function($m) {
                return !empty($m['enabled']);
            }));
        }
        
        return rest_ensure_response(['methods' => $methods]);
    }
    
    /**
     * Save withdrawal methods configuration (admin only).
     */
    public static function save_withdrawal_methods(\WP_REST_Request $request) {
        $methods = $request->get_param('methods');
        
        if (!is_array($methods)) {
            return new \WP_Error('invalid_data', 'Methods must be an array', ['status' => 400]);
        }
        
        // Sanitize
        $clean = [];
        foreach ($methods as $m) {
            $fields = [];
            if (!empty($m['fields']) && is_array($m['fields'])) {
                foreach ($m['fields'] as $f) {
                    $fields[] = [
                        'key'         => sanitize_key($f['key'] ?? ''),
                        'label'       => sanitize_text_field($f['label'] ?? ''),
                        'type'        => sanitize_text_field($f['type'] ?? 'text'),
                        'placeholder' => sanitize_text_field($f['placeholder'] ?? ''),
                        'required'    => !empty($f['required']),
                    ];
                }
            }
            
            $clean[] = [
                'id'           => sanitize_key($m['id'] ?? wp_generate_uuid4()),
                'name'         => sanitize_text_field($m['name'] ?? ''),
                'enabled'      => !empty($m['enabled']),
                'instructions' => wp_kses_post($m['instructions'] ?? ''),
                'fields'       => $fields,
            ];
        }
        
        update_option('battleledger_withdrawal_methods', $clean);
        
        return rest_ensure_response([
            'success' => true,
            'methods' => $clean,
            'message' => __('Withdrawal methods saved successfully', 'battle-ledger'),
        ]);
    }
    
    // ── Withdrawal Requests (Admin) ─────────────────────────────────
    
    /**
     * Get all withdrawal requests (admin).
     */
    public static function get_withdrawal_requests(\WP_REST_Request $request) {
        global $wpdb;
        
        $page     = max(1, intval($request->get_param('page')));
        $per_page = min(100, max(1, intval($request->get_param('per_page'))));
        $status   = sanitize_text_field($request->get_param('status') ?? '');
        $offset   = ($page - 1) * $per_page;
        
        $table  = $wpdb->prefix . 'bl_withdrawal_requests';
        $users  = $wpdb->users;
        
        $where  = 'WHERE 1=1';
        $params = [];
        
        if ($status) {
            $where .= ' AND r.status = %s';
            $params[] = $status;
        }
        
        // Count
        $count_sql = "SELECT COUNT(*) FROM $table r $where";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = (int) $wpdb->get_var($count_sql);
        
        // Fetch
        $params[] = $per_page;
        $params[] = $offset;
        
        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email
             FROM $table r
             LEFT JOIN $users u ON r.user_id = u.ID
             $where
             ORDER BY r.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        );
        
        $rows = $wpdb->get_results($sql);
        
        $requests = array_map(function($row) {
            return [
                'id'             => (int) $row->id,
                'user_id'        => (int) $row->user_id,
                'display_name'   => $row->display_name ?? 'Unknown',
                'user_email'     => $row->user_email ?? '',
                'phone'          => \BattleLedger\Api\UserController::get_phone((int) $row->user_id),
                'amount'         => floatval($row->amount),
                'method_id'      => $row->method_id,
                'method_name'    => $row->method_name,
                'method_details' => json_decode($row->method_details, true) ?: [],
                'status'         => $row->status,
                'admin_note'     => $row->admin_note,
                'processed_by'   => $row->processed_by ? (int) $row->processed_by : null,
                'processed_at'   => $row->processed_at,
                'created_at'     => $row->created_at,
            ];
        }, $rows);
        
        return rest_ensure_response([
            'requests' => $requests,
            'total'    => $total,
        ]);
    }
    
    /**
     * Complete a withdrawal request (admin).
     * NOW the actual debit happens — admin has approved and sent the money.
     */
    public static function complete_withdrawal(\WP_REST_Request $request) {
        global $wpdb;
        
        $id   = intval($request->get_param('id'));
        $note = sanitize_text_field($request->get_param('note') ?? '');
        $table = $wpdb->prefix . 'bl_withdrawal_requests';
        
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$row) {
            return new \WP_Error('not_found', 'Withdrawal request not found', ['status' => 404]);
        }
        
        if ($row->status !== 'pending') {
            return new \WP_Error('already_processed', sprintf('This request is already %s', $row->status), ['status' => 400]);
        }
        
        // NOW debit the wallet — admin approved the withdrawal
        $result = WalletManager::debit(
            $row->user_id,
            floatval($row->amount),
            sprintf(__('Withdrawal via %s (approved by admin)', 'battle-ledger'), $row->method_name),
            WalletManager::TYPE_WITHDRAWAL,
            'withdrawal_request',
            $id
        );
        
        if (is_wp_error($result)) {
            return new \WP_Error('debit_failed', $result->get_error_message(), ['status' => 500]);
        }
        
        // Mark as completed
        $wpdb->update($table, [
            'status'         => 'completed',
            'admin_note'     => $note,
            'transaction_id' => $result['transaction_id'],
            'processed_by'   => get_current_user_id(),
            'processed_at'   => current_time('mysql'),
        ], ['id' => $id]);

        // Notify the user that their withdrawal was approved
        \BattleLedger\Core\NotificationManager::withdrawal_approved(
            (int) $row->user_id,
            floatval($row->amount),
            function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD'
        );

        return rest_ensure_response([
            'success' => true,
            'message' => __('Withdrawal approved. Funds have been deducted from user wallet.', 'battle-ledger'),
        ]);
    }
    
    /**
     * Cancel a withdrawal request (admin).
     * Simply mark as cancelled — no refund needed since balance was never touched.
     * The frozen amount is automatically released.
     */
    public static function cancel_withdrawal(\WP_REST_Request $request) {
        global $wpdb;
        
        $id   = intval($request->get_param('id'));
        $note = sanitize_text_field($request->get_param('note') ?? '');
        $table = $wpdb->prefix . 'bl_withdrawal_requests';
        
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$row) {
            return new \WP_Error('not_found', 'Withdrawal request not found', ['status' => 404]);
        }
        
        if ($row->status !== 'pending') {
            return new \WP_Error('already_processed', sprintf('This request is already %s', $row->status), ['status' => 400]);
        }
        
        // Just mark as cancelled — no wallet changes needed, the frozen amount is released
        $wpdb->update($table, [
            'status'       => 'cancelled',
            'admin_note'   => $note,
            'processed_by' => get_current_user_id(),
            'processed_at' => current_time('mysql'),
        ], ['id' => $id]);

        // Notify the user that their withdrawal was rejected
        \BattleLedger\Core\NotificationManager::withdrawal_rejected(
            (int) $row->user_id,
            floatval($row->amount),
            function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            $note
        );
        
        // Get user's current balance to return
        $wallet = WalletManager::get_wallet($row->user_id);

        return rest_ensure_response([
            'success'     => true,
            'message'     => __('Withdrawal request cancelled. The frozen amount has been released back to user.', 'battle-ledger'),
            'new_balance' => $wallet ? floatval($wallet->balance) : 0,
        ]);
    }
}
