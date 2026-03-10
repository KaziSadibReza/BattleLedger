<?php
/**
 * Wallet Manager - Core wallet functionality
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Wallet;

if (!defined('ABSPATH')) {
    exit;
}

class WalletManager {
    
    private static $instance = null;
    
    /**
     * Transaction types
     */
    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';
    const TYPE_REFUND = 'refund';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_ADMIN_CREDIT = 'admin_credit';
    const TYPE_ADMIN_DEBIT = 'admin_debit';
    const TYPE_WOOCOMMERCE = 'woocommerce';
    const TYPE_PRIZE = 'prize';
    const TYPE_ENTRY_FEE = 'entry_fee';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_DEPOSIT_PENDING = 'deposit_pending';
    
    /**
     * Get the active currency code from WooCommerce.
     */
    public static function get_currency(): string {
        return function_exists('get_woocommerce_currency')
            ? get_woocommerce_currency()
            : get_option('woocommerce_currency', 'USD');
    }

    /**
     * Format a price using WooCommerce (with symbol + decimals) or plain fallback.
     */
    public static function format_price(float $amount): string {
        if (function_exists('wc_price')) {
            return html_entity_decode(strip_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
        }
        return self::get_currency_symbol() . number_format($amount, 2);
    }

    /**
     * Get the currency symbol.
     */
    public static function get_currency_symbol(): string {
        if (function_exists('get_woocommerce_currency_symbol')) {
            return html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
        }
        return '$';
    }

    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize hooks if needed
    }
    
    /**
     * Get or create wallet for a user
     */
    public static function get_wallet($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallets';
        
        $wallet = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if (!$wallet) {
            // Create wallet for user
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'balance' => 0.00,
                'currency' => self::get_currency(),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            
            $wallet = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d",
                $user_id
            ));
        }
        
        return $wallet;
    }
    
    /**
     * Get wallet by ID
     */
    public static function get_wallet_by_id($wallet_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallets';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $wallet_id
        ));
    }
    
    /**
     * Get user's wallet balance
     */
    public static function get_balance($user_id) {
        $wallet = self::get_wallet($user_id);
        return $wallet ? floatval($wallet->balance) : 0.00;
    }
    
    /**
     * Credit amount to wallet (add money)
     */
    public static function credit($user_id, $amount, $description = '', $type = self::TYPE_CREDIT, $reference_type = null, $reference_id = null, $created_by = null) {
        global $wpdb;
        
        $amount = abs(floatval($amount));
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Amount must be greater than 0', 'battle-ledger'));
        }
        
        $wallet = self::get_wallet($user_id);
        if (!$wallet) {
            return new \WP_Error('wallet_not_found', __('Wallet not found', 'battle-ledger'));
        }
        
        if ($wallet->status !== 'active') {
            return new \WP_Error('wallet_inactive', __('Wallet is not active', 'battle-ledger'));
        }
        
        // Atomic credit — prevents race conditions
        $table = $wpdb->prefix . 'bl_wallets';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET balance = balance + %f, updated_at = %s WHERE id = %d",
            $amount,
            current_time('mysql'),
            $wallet->id
        ));
        $new_balance = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM $table WHERE id = %d",
            $wallet->id
        ));
        
        // Record transaction
        $transaction_id = self::record_transaction(
            $wallet->id,
            $user_id,
            $type,
            $amount,
            $new_balance,
            $description,
            $reference_type,
            $reference_id,
            $created_by
        );
        
        do_action('battleledger_wallet_credited', $user_id, $amount, $new_balance, $transaction_id);
        
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'new_balance' => $new_balance,
        ];
    }
    
    /**
     * Debit amount from wallet (remove money)
     */
    public static function debit($user_id, $amount, $description = '', $type = self::TYPE_DEBIT, $reference_type = null, $reference_id = null, $created_by = null) {
        global $wpdb;
        
        $amount = abs(floatval($amount));
        if ($amount <= 0) {
            return new \WP_Error('invalid_amount', __('Amount must be greater than 0', 'battle-ledger'));
        }
        
        $wallet = self::get_wallet($user_id);
        if (!$wallet) {
            return new \WP_Error('wallet_not_found', __('Wallet not found', 'battle-ledger'));
        }
        
        if ($wallet->status !== 'active') {
            return new \WP_Error('wallet_inactive', __('Wallet is not active', 'battle-ledger'));
        }
        
        if (floatval($wallet->balance) < $amount) {
            return new \WP_Error('insufficient_balance', __('Insufficient wallet balance', 'battle-ledger'));
        }
        
        // Atomic debit — prevents race conditions and overdraft
        $table = $wpdb->prefix . 'bl_wallets';
        $rows = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET balance = balance - %f, updated_at = %s WHERE id = %d AND balance >= %f",
            $amount,
            current_time('mysql'),
            $wallet->id,
            $amount
        ));
        if ($rows === 0) {
            return new \WP_Error('insufficient_balance', __('Insufficient wallet balance', 'battle-ledger'));
        }
        $new_balance = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM $table WHERE id = %d",
            $wallet->id
        ));
        
        // Record transaction
        $transaction_id = self::record_transaction(
            $wallet->id,
            $user_id,
            $type,
            -$amount, // Negative for debit
            $new_balance,
            $description,
            $reference_type,
            $reference_id,
            $created_by
        );
        
        do_action('battleledger_wallet_debited', $user_id, $amount, $new_balance, $transaction_id);
        
        return [
            'success' => true,
            'transaction_id' => $transaction_id,
            'new_balance' => $new_balance,
        ];
    }
    
    /**
     * Record a transaction
     */
    private static function record_transaction($wallet_id, $user_id, $type, $amount, $balance_after, $description, $reference_type, $reference_id, $created_by) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'bl_wallet_transactions',
            [
                'wallet_id' => $wallet_id,
                'user_id' => $user_id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $balance_after,
                'description' => $description,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'created_by' => $created_by ?: get_current_user_id(),
                'created_at' => current_time('mysql'),
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Check if a CREDIT transaction already exists for a given reference.
     * Used to prevent duplicate wallet credits for the same order.
     * Only checks for real credits (type != 'deposit_pending').
     *
     * @param string $reference_type  e.g. 'order'
     * @param int    $reference_id    e.g. WooCommerce order ID
     * @return bool
     */
    public static function has_transaction_for_reference(string $reference_type, int $reference_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reference_type = %s AND reference_id = %d AND amount > 0 AND type != %s",
            $reference_type,
            $reference_id,
            self::TYPE_DEPOSIT_PENDING
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Record a pending deposit in transaction history.
     * This does NOT add money to the wallet — it's a placeholder so the user
     * can see the deposit is in progress.
     *
     * @param int    $user_id
     * @param float  $amount     The expected deposit amount
     * @param string $description
     * @param int    $order_id   WooCommerce order ID
     * @return int|false  Transaction ID or false
     */
    public static function record_pending_deposit($user_id, $amount, $description, $order_id) {
        global $wpdb;
        
        $wallet = self::get_wallet($user_id);
        if (!$wallet) {
            return false;
        }
        
        // Don't create duplicate pending records for the same order
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE reference_type = 'order' AND reference_id = %d AND type = %s",
            $order_id,
            self::TYPE_DEPOSIT_PENDING
        ));
        
        if (intval($existing) > 0) {
            return false;
        }
        
        // Record with amount = expected amount but balance_after stays the same (no balance change)
        $wpdb->insert(
            $table,
            [
                'wallet_id' => $wallet->id,
                'user_id' => $user_id,
                'type' => self::TYPE_DEPOSIT_PENDING,
                'amount' => abs(floatval($amount)),
                'balance_after' => floatval($wallet->balance),
                'description' => $description,
                'reference_type' => 'order',
                'reference_id' => $order_id,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ]
        );
        
        return $wpdb->insert_id ?: false;
    }
    
    /**
     * Remove pending deposit record for an order.
     * Called when the order completes and a real credit is created.
     *
     * @param int $order_id
     */
    public static function remove_pending_deposit($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $wpdb->delete($table, [
            'reference_type' => 'order',
            'reference_id' => $order_id,
            'type' => self::TYPE_DEPOSIT_PENDING,
        ]);
    }
    
    /**
     * Get transactions for a user
     */
    public static function get_transactions($user_id, $args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'type' => '',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "WHERE user_id = %d";
        $params = [$user_id];
        
        if (!empty($args['type'])) {
            $where .= " AND type = %s";
            $params[] = $args['type'];
        }
        
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql = $wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at $order LIMIT %d OFFSET %d",
            array_merge($params, [$args['per_page'], $offset])
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get all transactions (admin)
     */
    public static function get_all_transactions($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'type' => '',
            'user_id' => 0,
            'order' => 'DESC',
            'search' => '',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($args['type'])) {
            $where .= " AND t.type = %s";
            $params[] = $args['type'];
        }
        
        if (!empty($args['user_id'])) {
            $where .= " AND t.user_id = %d";
            $params[] = $args['user_id'];
        }
        
        $users_table = $wpdb->users;
        
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $sql = "SELECT t.*, u.user_email, u.display_name 
                FROM $table t 
                LEFT JOIN $users_table u ON t.user_id = u.ID 
                $where 
                ORDER BY t.created_at $order 
                LIMIT %d OFFSET %d";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $transactions = $wpdb->get_results($sql);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table t $where";
        if (!empty($args['type']) || !empty($args['user_id'])) {
            $count_params = array_slice($params, 0, -2); // Remove limit/offset
            if (!empty($count_params)) {
                $count_sql = $wpdb->prepare($count_sql, $count_params);
            }
        }
        $total = $wpdb->get_var($count_sql);
        
        return [
            'transactions' => $transactions,
            'total' => (int) $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
        ];
    }
    
    /**
     * Get all wallets (admin)
     */
    public static function get_all_wallets($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_wallets';
        $users_table = $wpdb->users;
        
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'order_by' => 'created_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        $where = "WHERE 1=1";
        $params = [];
        
        if (!empty($args['status'])) {
            $where .= " AND w.status = %s";
            $params[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where .= " AND (u.user_email LIKE %s OR u.display_name LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $allowed_order_by = ['created_at', 'balance', 'updated_at'];
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT w.*, u.user_email, u.display_name 
                FROM $table w 
                LEFT JOIN $users_table u ON w.user_id = u.ID 
                $where 
                ORDER BY w.$order_by $order 
                LIMIT %d OFFSET %d";
        
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        $wallets = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        // Get total count
        $count_params = array_slice($params, 0, -2);
        $count_sql = "SELECT COUNT(*) FROM $table w LEFT JOIN $users_table u ON w.user_id = u.ID $where";
        if (!empty($count_params)) {
            $count_sql = $wpdb->prepare($count_sql, $count_params);
        }
        $total = $wpdb->get_var($count_sql);
        
        return [
            'wallets' => $wallets,
            'total' => (int) $total,
            'page' => $args['page'],
            'per_page' => $args['per_page'],
        ];
    }
    
    /**
     * Update wallet status
     */
    public static function update_status($user_id, $status) {
        global $wpdb;
        
        $allowed_statuses = ['active', 'suspended', 'closed'];
        if (!in_array($status, $allowed_statuses)) {
            return new \WP_Error('invalid_status', __('Invalid wallet status', 'battle-ledger'));
        }
        
        $wallet = self::get_wallet($user_id);
        if (!$wallet) {
            return new \WP_Error('wallet_not_found', __('Wallet not found', 'battle-ledger'));
        }
        
        $wpdb->update(
            $wpdb->prefix . 'bl_wallets',
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $wallet->id]
        );
        
        do_action('battleledger_wallet_status_changed', $user_id, $status, $wallet->status);
        
        return true;
    }
    
    /**
     * Get wallet statistics (admin dashboard)
     */
    public static function get_statistics() {
        global $wpdb;
        $wallets_table = $wpdb->prefix . 'bl_wallets';
        $transactions_table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $total_balance = $wpdb->get_var("SELECT SUM(balance) FROM $wallets_table WHERE status = 'active'");
        $total_wallets = $wpdb->get_var("SELECT COUNT(*) FROM $wallets_table");
        $active_wallets = $wpdb->get_var("SELECT COUNT(*) FROM $wallets_table WHERE status = 'active'");
        
        $today_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $transactions_table WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        $today_credits = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM $transactions_table WHERE DATE(created_at) = %s AND amount > 0",
            current_time('Y-m-d')
        ));
        
        $today_debits = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(ABS(SUM(amount)), 0) FROM $transactions_table WHERE DATE(created_at) = %s AND amount < 0",
            current_time('Y-m-d')
        ));
        
        return [
            'total_balance' => floatval($total_balance),
            'total_wallets' => (int) $total_wallets,
            'active_wallets' => (int) $active_wallets,
            'today_transactions' => (int) $today_transactions,
            'today_credits' => floatval($today_credits),
            'today_debits' => floatval($today_debits),
            'currency' => self::get_currency(),
        ];
    }
    
    /**
     * Search users for wallet operations
     */
    public static function search_users($search, $limit = 10) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($search) . '%';
        
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, user_email, display_name 
             FROM {$wpdb->users} 
             WHERE user_email LIKE %s OR display_name LIKE %s OR user_login LIKE %s
             LIMIT %d",
            $search, $search, $search, $limit
        ));
        
        // Add wallet info
        foreach ($users as &$user) {
            $wallet = self::get_wallet($user->ID);
            $user->wallet_balance = $wallet ? floatval($wallet->balance) : 0;
            $user->wallet_status = $wallet ? $wallet->status : 'none';
        }
        
        return $users;
    }
}
