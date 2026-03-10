<?php
namespace BattleLedger\Api;

use BattleLedger\Core\Installer;

/**
 * Diagnostic / Migration API controller
 */
class DiagnosticController {

    /**
     * Register REST routes
     */
    public static function register_routes() {
        register_rest_route('battle-ledger/v1', '/diagnostic/status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_status'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/diagnostic/migrate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'run_migration'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/diagnostic/fix-orphaned', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'fix_orphaned_withdrawals'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
    }

    /**
     * Get full diagnostic status: tables, versions, orphaned data, debug log tail
     */
    public static function get_status(\WP_REST_Request $request) {
        global $wpdb;

        $prefix = $wpdb->prefix;

        // ── Expected Tables ──
        $expected_tables = [
            'bl_tournaments',
            'bl_tournament_participants',
            'bl_matches',
            'bl_tournament_logs',
            'bl_wallets',
            'bl_wallet_transactions',
            'bl_withdrawal_requests',
            'bl_game_rules',
        ];

        $tables = [];
        foreach ($expected_tables as $short) {
            $full = $prefix . $short;
            $exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full)) === $full);
            $row_count = null;
            if ($exists) {
                $row_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$full`");
            }
            $tables[] = [
                'name'      => $short,
                'full_name' => $full,
                'exists'    => $exists,
                'rows'      => $row_count,
            ];
        }

        // ── Versions ──
        $db_version_stored  = get_option('battle_ledger_db_version', '0');
        $db_version_target  = Installer::DB_VERSION;
        $plugin_version     = defined('BATTLE_LEDGER_VERSION') ? BATTLE_LEDGER_VERSION : 'unknown';

        // ── Orphaned withdrawal transactions (debit with no matching request) ──
        $orphaned = [];
        $wr_table = $prefix . 'bl_withdrawal_requests';
        $tx_table = $prefix . 'bl_wallet_transactions';
        $wr_exists = ($wpdb->get_var("SHOW TABLES LIKE '$wr_table'") === $wr_table);

        if ($wr_exists) {
            // Transactions of type 'withdrawal' with no matching withdrawal request
            $orphaned = $wpdb->get_results(
                "SELECT t.id, t.user_id, t.amount, t.description, t.created_at, u.display_name
                 FROM $tx_table t
                 LEFT JOIN $wr_table wr ON wr.transaction_id = t.id
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                 WHERE t.type = 'withdrawal' AND wr.id IS NULL
                 AND t.description NOT LIKE '%refunded%'
                 ORDER BY t.created_at DESC
                 LIMIT 50"
            );
        } else {
            // Table doesn't exist — ALL withdrawal transactions are orphaned
            $orphaned = $wpdb->get_results(
                "SELECT t.id, t.user_id, t.amount, t.description, t.created_at, u.display_name
                 FROM $tx_table t
                 LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID
                 WHERE t.type = 'withdrawal'
                 AND t.description NOT LIKE '%refunded%'
                 ORDER BY t.created_at DESC
                 LIMIT 50"
            );
        }

        // Format orphaned
        $orphaned_data = array_map(function ($row) {
            return [
                'transaction_id' => (int) $row->id,
                'user_id'        => (int) $row->user_id,
                'display_name'   => $row->display_name ?? 'Unknown',
                'amount'         => floatval($row->amount),
                'description'    => $row->description,
                'created_at'     => $row->created_at,
            ];
        }, $orphaned);

        // ── Debug Log (last 30 lines) ──
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $debug_lines = [];
        if (file_exists($debug_log_path)) {
            $lines = file($debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // Only show BattleLedger-related log entries to avoid exposing sensitive data
            $bl_lines = array_filter($lines, fn($l) => stripos($l, 'battle') !== false || stripos($l, 'bl_') !== false);
            $debug_lines = array_slice($bl_lines, -30);
        }

        // ── WP / PHP / MySQL info ──
        $environment = [
            'php_version'   => PHP_VERSION,
            'wp_version'    => get_bloginfo('version'),
            'mysql_version' => $wpdb->db_version(),
            'wp_debug'      => defined('WP_DEBUG') && WP_DEBUG,
            'wp_debug_log'  => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'woocommerce'   => class_exists('\WooCommerce') ? \WC()->version : false,
            'db_prefix'     => $prefix,
        ];

        return rest_ensure_response([
            'tables'          => $tables,
            'db_version'      => $db_version_stored,
            'db_version_target' => $db_version_target,
            'needs_migration' => version_compare($db_version_stored, $db_version_target, '<'),
            'plugin_version'  => $plugin_version,
            'orphaned_withdrawals' => $orphaned_data,
            'environment'     => $environment,
            'debug_log'       => $debug_lines,
        ]);
    }

    /**
     * Run database migration (create/update all tables)
     */
    public static function run_migration(\WP_REST_Request $request) {
        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Database/Schema.php';
        \BattleLedger\Database\Schema::create_tables();

        // Seed default game rules if the table is empty
        Installer::seed_default_game_rules();

        // Update version
        update_option('battle_ledger_db_version', Installer::DB_VERSION);

        // Verify tables after migration
        global $wpdb;
        $prefix = $wpdb->prefix;
        $expected = [
            'bl_tournaments',
            'bl_tournament_participants',
            'bl_matches',
            'bl_tournament_logs',
            'bl_wallets',
            'bl_wallet_transactions',
            'bl_withdrawal_requests',
            'bl_game_rules',
        ];

        $results = [];
        foreach ($expected as $short) {
            $full = $prefix . $short;
            $results[$short] = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full)) === $full);
        }

        $all_ok = !in_array(false, $results, true);

        return rest_ensure_response([
            'success'     => $all_ok,
            'message'     => $all_ok
                ? 'All tables created/verified successfully.'
                : 'Some tables could not be created. Check debug log.',
            'tables'      => $results,
            'db_version'  => Installer::DB_VERSION,
        ]);
    }

    /**
     * Fix orphaned withdrawal transactions — refund them back to user wallets.
     */
    public static function fix_orphaned_withdrawals(\WP_REST_Request $request) {
        global $wpdb;

        $prefix   = $wpdb->prefix;
        $wr_table = $prefix . 'bl_withdrawal_requests';
        $tx_table = $prefix . 'bl_wallet_transactions';
        $wr_exists = ($wpdb->get_var("SHOW TABLES LIKE '$wr_table'") === $wr_table);

        // Find orphaned withdrawal transactions (exclude already-refunded ones)
        if ($wr_exists) {
            $orphaned = $wpdb->get_results(
                "SELECT t.id, t.user_id, t.amount
                 FROM $tx_table t
                 LEFT JOIN $wr_table wr ON wr.transaction_id = t.id
                 WHERE t.type = 'withdrawal' AND wr.id IS NULL
                 AND t.description NOT LIKE '%refunded%'"
            );
        } else {
            $orphaned = $wpdb->get_results(
                "SELECT t.id, t.user_id, t.amount
                 FROM $tx_table t
                 WHERE t.type = 'withdrawal'
                 AND t.description NOT LIKE '%refunded%'"
            );
        }

        if (empty($orphaned)) {
            return rest_ensure_response([
                'success' => true,
                'message' => 'No orphaned withdrawal transactions found.',
                'refunded' => 0,
            ]);
        }

        require_once BATTLE_LEDGER_PLUGIN_DIR . 'includes/Wallet/WalletManager.php';
        $refunded = 0;

        foreach ($orphaned as $tx) {
            $amount = abs(floatval($tx->amount));
            $result = \BattleLedger\Wallet\WalletManager::credit(
                (int) $tx->user_id,
                $amount,
                sprintf('Admin refund: orphaned withdrawal TX #%d', $tx->id),
                \BattleLedger\Wallet\WalletManager::TYPE_REFUND,
                'transaction',
                (int) $tx->id
            );

            if (!is_wp_error($result)) {
                // Mark the original transaction description so it's clear it was fixed
                $wpdb->update($tx_table, [
                    'description' => $wpdb->get_var($wpdb->prepare(
                        "SELECT description FROM $tx_table WHERE id = %d", $tx->id
                    )) . ' (orphaned — refunded by admin)',
                ], ['id' => $tx->id]);
                $refunded++;
            }
        }

        return rest_ensure_response([
            'success'  => true,
            'message'  => sprintf('Refunded %d orphaned withdrawal transaction(s).', $refunded),
            'refunded' => $refunded,
        ]);
    }
}
