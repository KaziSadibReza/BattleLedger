<?php
/**
 * Dashboard Stats API Controller
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Api;

use BattleLedger\Wallet\WalletManager;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

class DashboardController {

    /**
     * Determine whether a user appears in a finished tournament snapshot.
     *
     * @param string|null $participants_json
     * @param int         $user_id
     */
    private static function user_in_finished_snapshot($participants_json, $user_id): bool {
        if (empty($participants_json)) {
            return false;
        }

        $participants = json_decode((string) $participants_json, true);
        if (!is_array($participants)) {
            return false;
        }

        foreach ($participants as $participant) {
            if ((int) ($participant['user_id'] ?? 0) === (int) $user_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map wallet transaction type to a dashboard activity label and tone.
     *
     * @return array{label:string,tone:string}
     */
    private static function map_transaction_activity($type, $amount, $description): array {
        $type = (string) $type;
        $amount = (float) $amount;
        $description = (string) $description;

        $map = [
            'credit' => ['label' => 'Wallet credit', 'tone' => 'success'],
            'admin_credit' => ['label' => 'Admin credit', 'tone' => 'success'],
            'woocommerce' => ['label' => 'Wallet deposit', 'tone' => 'success'],
            'deposit_pending' => ['label' => 'Deposit pending', 'tone' => 'warning'],
            'prize' => ['label' => 'Tournament prize', 'tone' => 'success'],
            'debit' => ['label' => 'Wallet debit', 'tone' => 'danger'],
            'entry_fee' => ['label' => 'Tournament entry fee', 'tone' => 'warning'],
            'withdrawal' => ['label' => 'Withdrawal', 'tone' => 'danger'],
            'refund' => ['label' => 'Refund', 'tone' => 'info'],
            'purchase' => ['label' => 'Purchase', 'tone' => 'warning'],
        ];

        if ($type === 'withdrawal' && $description !== '') {
            if (strpos($description, '(pending)') !== false) {
                return ['label' => 'Pending withdrawal', 'tone' => 'warning'];
            }

            if (strpos($description, '(cancelled') !== false) {
                return ['label' => 'Withdrawal cancelled', 'tone' => 'info'];
            }
        }

        if (isset($map[$type])) {
            return $map[$type];
        }

        if ($amount > 0) {
            return ['label' => 'Wallet credit', 'tone' => 'success'];
        }

        if ($amount < 0) {
            return ['label' => 'Wallet debit', 'tone' => 'danger'];
        }

        return ['label' => 'Wallet activity', 'tone' => 'info'];
    }
    
    /**
     * Register routes
     */
    public static function register_routes(): void {
        register_rest_route('battle-ledger/v1', '/user/dashboard-stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_dashboard_stats'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }
    
    /**
     * Get dashboard statistics for current user
     */
    public static function get_dashboard_stats(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        global $wpdb;

        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        $tournaments_table = $wpdb->prefix . 'bl_tournaments';
        $transactions_table = $wpdb->prefix . 'bl_wallet_transactions';
        $finished_table = $wpdb->prefix . 'bl_finished_tournaments';
        
        // Get wallet balance
        $wallet = WalletManager::get_wallet($user_id);
        $wallet_balance = $wallet ? floatval($wallet->balance) : 0;
        $currency = $wallet ? $wallet->currency : WalletManager::get_currency();
        
        // Active and current tournament stats
        $active_tournaments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.tournament_id)
             FROM $participants_table p
             INNER JOIN $tournaments_table t ON p.tournament_id = t.id
             WHERE p.user_id = %d AND t.status = 'active'",
            $user_id
        ));

        $current_tournaments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT tournament_id)
             FROM $participants_table
             WHERE user_id = %d",
            $user_id
        ));

        // Finished tournaments are snapshotted in bl_finished_tournaments,
        // and their participant rows are removed from bl_tournament_participants.
        $finished_rows = $wpdb->get_results(
            "SELECT id, name, participants, finished_at
             FROM $finished_table
             ORDER BY finished_at DESC"
        );

        $finished_tournaments = 0;
        $recent_finished = 0;
        $finished_activity = [];
        $seven_days_ago = time() - (7 * DAY_IN_SECONDS);

        foreach ($finished_rows ?: [] as $finished_row) {
            if (!self::user_in_finished_snapshot($finished_row->participants ?? '', $user_id)) {
                continue;
            }

            $finished_tournaments++;

            $finished_at_ts = strtotime((string) ($finished_row->finished_at ?? ''));
            if ($finished_at_ts && $finished_at_ts >= $seven_days_ago) {
                $recent_finished++;
            }

            if (!empty($finished_row->finished_at)) {
                $finished_activity[] = [
                    'id' => 'finished-' . (int) $finished_row->id,
                    'type' => 'tournament_finished',
                    'label' => 'Tournament finished',
                    'description' => sprintf('Results published for %s', (string) ($finished_row->name ?? 'a tournament')),
                    'created_at' => $finished_row->finished_at,
                    'tone' => 'success',
                ];
            }
        }

        $total_tournaments = intval($current_tournaments) + intval($finished_tournaments);
        
        // Get match stats
        $matches_table = $wpdb->prefix . 'bl_matches';
        
        $total_matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $matches_table m
            INNER JOIN $participants_table tp ON m.tournament_id = tp.tournament_id
            WHERE tp.user_id = %d AND (m.participant1_id = tp.id OR m.participant2_id = tp.id)",
            $user_id
        ));
        
        $upcoming_matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $matches_table m
            INNER JOIN $participants_table tp ON m.tournament_id = tp.tournament_id
            WHERE tp.user_id = %d 
            AND (m.participant1_id = tp.id OR m.participant2_id = tp.id)
            AND m.status = 'scheduled'
            AND m.scheduled_at > NOW()",
            $user_id
        ));
        
        // Get recent transaction count (last 7 days)
        $recent_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $transactions_table 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));

        $recent_tournament_joins = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $participants_table
             WHERE user_id = %d
             AND registered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));

        // Build recent activity feed (latest transactions + joins + finished events)
        $transaction_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, type, amount, description, created_at
             FROM $transactions_table
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT 8",
            $user_id
        ));

        $transaction_activity = [];
        foreach ($transaction_rows ?: [] as $row) {
            $amount = (float) ($row->amount ?? 0);
            $meta = self::map_transaction_activity($row->type ?? '', $amount, $row->description ?? '');

            $transaction_activity[] = [
                'id' => 'tx-' . (int) $row->id,
                'type' => 'transaction',
                'label' => $meta['label'],
                'description' => !empty($row->description) ? (string) $row->description : 'Wallet transaction',
                'created_at' => $row->created_at,
                'tone' => $meta['tone'],
                'amount' => $amount,
            ];
        }

        $join_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.tournament_id, p.registered_at, t.name
             FROM $participants_table p
             INNER JOIN $tournaments_table t ON p.tournament_id = t.id
             WHERE p.user_id = %d
             ORDER BY p.registered_at DESC
             LIMIT 8",
            $user_id
        ));

        $join_activity = [];
        foreach ($join_rows ?: [] as $row) {
            if (empty($row->registered_at)) {
                continue;
            }

            $join_activity[] = [
                'id' => 'join-' . (int) $row->tournament_id . '-' . (string) $row->registered_at,
                'type' => 'tournament_joined',
                'label' => 'Joined tournament',
                'description' => sprintf('You joined %s', (string) ($row->name ?? 'a tournament')),
                'created_at' => $row->registered_at,
                'tone' => 'info',
            ];
        }

        $recent_activity = array_merge($transaction_activity, $join_activity, $finished_activity);
        usort($recent_activity, static function ($a, $b): int {
            $a_ts = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
            $b_ts = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

            if ($a_ts === $b_ts) {
                return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
            }

            return $b_ts <=> $a_ts;
        });
        $recent_activity = array_values(array_slice($recent_activity, 0, 8));

        $recent_activity_count = intval($recent_transactions) + intval($recent_tournament_joins) + intval($recent_finished);
        
        return new WP_REST_Response([
            'wallet_balance' => $wallet_balance,
            'currency' => $currency,
            'total_tournaments' => intval($total_tournaments) ?: 0,
            'active_tournaments' => intval($active_tournaments) ?: 0,
            'total_matches' => intval($total_matches) ?: 0,
            'upcoming_matches' => intval($upcoming_matches) ?: 0,
            'recent_transactions' => intval($recent_transactions) ?: 0,
            'recent_activity_count' => $recent_activity_count,
            'recent_activity' => $recent_activity,
        ], 200);
    }
}
