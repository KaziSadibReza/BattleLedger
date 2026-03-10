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
        
        // Get wallet balance
        $wallet = WalletManager::get_wallet($user_id);
        $wallet_balance = $wallet ? floatval($wallet->balance) : 0;
        $currency = $wallet ? $wallet->currency : get_option('woocommerce_currency', 'USD');
        
        // Get tournament stats
        $tournaments_table = $wpdb->prefix . 'bl_tournament_participants';
        
        $total_tournaments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tournaments_table WHERE user_id = %d",
            $user_id
        ));
        
        $active_tournaments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $tournaments_table tp
            INNER JOIN {$wpdb->prefix}bl_tournaments t ON tp.tournament_id = t.id
            WHERE tp.user_id = %d AND t.status = 'active'",
            $user_id
        ));
        
        // Get match stats
        $matches_table = $wpdb->prefix . 'bl_matches';
        
        $total_matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $matches_table m
            INNER JOIN $tournaments_table tp ON m.tournament_id = tp.tournament_id
            WHERE tp.user_id = %d AND (m.participant1_id = tp.id OR m.participant2_id = tp.id)",
            $user_id
        ));
        
        $upcoming_matches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $matches_table m
            INNER JOIN $tournaments_table tp ON m.tournament_id = tp.tournament_id
            WHERE tp.user_id = %d 
            AND (m.participant1_id = tp.id OR m.participant2_id = tp.id)
            AND m.status = 'scheduled'
            AND m.scheduled_at > NOW()",
            $user_id
        ));
        
        // Get recent transaction count (last 7 days)
        $transactions_table = $wpdb->prefix . 'bl_wallet_transactions';
        
        $recent_transactions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $transactions_table 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));
        
        return new WP_REST_Response([
            'wallet_balance' => $wallet_balance,
            'currency' => $currency,
            'total_tournaments' => intval($total_tournaments) ?: 0,
            'active_tournaments' => intval($active_tournaments) ?: 0,
            'total_matches' => intval($total_matches) ?: 0,
            'upcoming_matches' => intval($upcoming_matches) ?: 0,
            'recent_transactions' => intval($recent_transactions) ?: 0,
        ], 200);
    }
}
