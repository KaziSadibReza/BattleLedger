<?php
/**
 * User Tournaments API Controller
 *
 * Returns tournaments the current user has participated in —
 * both active (from bl_tournament_participants) and finished
 * (from bl_finished_tournaments snapshots).
 *
 * @package BattleLedger
 */

namespace BattleLedger\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class UserTournamentController {

    public static function register_routes(): void {
        register_rest_route('battle-ledger/v1', '/user/my-tournaments', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_my_tournaments'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * GET /user/my-tournaments
     *
     * Query params: tab (active|finished|all), page, per_page
     *
     * Returns a unified list:
     * - active:   tournaments the user currently registered in
     * - finished: tournaments from snapshots where user was a participant
     */
    public static function get_my_tournaments(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;

        $user_id  = get_current_user_id();
        $tab      = sanitize_text_field($request->get_param('tab') ?? 'all');
        $page     = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = max(1, min(50, (int) ($request->get_param('per_page') ?? 20)));
        $offset   = ($page - 1) * $per_page;

        $tournaments_table  = $wpdb->prefix . 'bl_tournaments';
        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        $finished_table     = $wpdb->prefix . 'bl_finished_tournaments';
        $rules_table        = $wpdb->prefix . 'bl_game_rules';

        $items = [];
        $total = 0;

        /* ── Active tournaments ──────────────────────────────────── */
        if ($tab === 'active' || $tab === 'all') {
            $active_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT t.*, p.registered_at AS joined_at, p.slots AS my_slots,
                        p.team_name AS my_team_name, p.metadata AS my_metadata,
                        COALESCE(pc.cnt, 0) AS participant_count,
                        gr.game_name AS rule_game_name, gr.game_icon AS rule_game_icon, gr.game_image AS rule_game_image
                 FROM $participants_table p
                 INNER JOIN $tournaments_table t ON t.id = p.tournament_id
                 LEFT JOIN (
                     SELECT tournament_id, SUM(COALESCE(slots, 1)) AS cnt
                     FROM $participants_table GROUP BY tournament_id
                 ) pc ON pc.tournament_id = t.id
                 LEFT JOIN $rules_table gr ON gr.slug = t.game_type
                 WHERE p.user_id = %d
                 ORDER BY p.registered_at DESC",
                $user_id
            ));

            foreach ($active_rows ?: [] as $r) {
                $settings = json_decode($r->settings ?? '{}', true) ?: [];

                // Determine effective status
                $status = $r->status;
                if ($status === 'active' && !empty($r->end_date) && strtotime($r->end_date) < time()) {
                    $status = 'awaiting_results';
                }

                $room_id = trim((string) ($settings['room_id'] ?? ''));
                $room_password = trim((string) ($settings['room_password'] ?? ''));

                $items[] = [
                    'id'                => (int) $r->id,
                    'type'              => 'active',
                    'name'              => $r->name,
                    'game_type'         => $r->game_type ?? '',
                    'game_name'         => $r->rule_game_name ?? ($r->game_type ?? ''),
                    'game_icon'         => $r->rule_game_icon ?? '',
                    'game_image'        => $r->rule_game_image ?? '',
                    'status'            => $status,
                    'start_date'        => $r->start_date,
                    'end_date'          => $r->end_date,
                    'entry_fee'         => (float) $r->entry_fee,
                    'prize_pool'        => (float) $r->prize_pool,
                    'max_participants'  => (int) $r->max_participants,
                    'participant_count' => (int) $r->participant_count,
                    'joined_at'         => $r->joined_at,
                    'my_slots'          => (int) ($r->my_slots ?? 1),
                    'my_team_name'      => $r->my_team_name ?? '',
                    'settings'          => [
                        'game_mode'          => $settings['game_mode'] ?? '',
                        'map'                => $settings['map'] ?? '',
                        'team_mode'          => $settings['team_mode'] ?? '',
                        'prize_distribution' => $settings['prize_distribution'] ?? [],
                        'banner'             => $settings['banner'] ?? '',
                    ],
                    'room_id'           => $room_id !== '' ? $room_id : 'Loading',
                    'room_password'     => $room_password !== '' ? $room_password : 'Loading',
                    'winners'           => null,
                    'finished_at'       => null,
                ];
            }
        }

        /* ── Finished tournaments ─────────────────────────────────── */
        if ($tab === 'finished' || $tab === 'all') {
            $finished_rows = $wpdb->get_results(
                "SELECT * FROM $finished_table ORDER BY finished_at DESC"
            );

            foreach ($finished_rows ?: [] as $r) {
                $participants = json_decode($r->participants ?? '[]', true) ?: [];
                $found = false;

                foreach ($participants as $p) {
                    if ((int) ($p['user_id'] ?? 0) === $user_id) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    continue;
                }

                $settings = json_decode($r->settings ?? '{}', true) ?: [];
                $winners  = json_decode($r->winners ?? '{}', true) ?: [];

                // Look up game rule for finished tournament
                $game_rule = $wpdb->get_row($wpdb->prepare(
                    "SELECT game_name, game_icon, game_image FROM $rules_table WHERE slug = %s LIMIT 1",
                    $r->game_type ?? ''
                ));

                $items[] = [
                    'id'                => (int) $r->id,
                    'type'              => 'finished',
                    'name'              => $r->name,
                    'game_type'         => $r->game_type ?? '',
                    'game_name'         => $game_rule->game_name ?? ($r->game_type ?? ''),
                    'game_icon'         => $game_rule->game_icon ?? '',
                    'game_image'        => $game_rule->game_image ?? '',
                    'status'            => 'finished',
                    'start_date'        => $r->start_date,
                    'end_date'          => $r->end_date,
                    'entry_fee'         => (float) $r->entry_fee,
                    'prize_pool'        => (float) $r->prize_pool,
                    'max_participants'  => (int) $r->max_participants,
                    'participant_count' => (int) $r->participant_count,
                    'joined_at'         => $p['registered_at'] ?? $r->finished_at,
                    'my_slots'          => (int) ($p['slots'] ?? 1),
                    'my_team_name'      => $p['team_name'] ?? '',
                    'settings'          => [
                        'game_mode'          => $settings['game_mode'] ?? '',
                        'map'                => $settings['map'] ?? '',
                        'team_mode'          => $settings['team_mode'] ?? '',
                        'prize_distribution' => $settings['prize_distribution'] ?? [],
                        'banner'             => $settings['banner'] ?? '',
                    ],
                    'winners'           => $winners,
                    'finished_at'       => $r->finished_at,
                ];
            }
        }

        // Sort: active first (by joined_at DESC), then finished (by finished_at DESC)
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'active' ? -1 : 1;
            }
            $da = $a['type'] === 'finished' ? ($a['finished_at'] ?? '') : ($a['joined_at'] ?? '');
            $db = $b['type'] === 'finished' ? ($b['finished_at'] ?? '') : ($b['joined_at'] ?? '');
            return strcmp($db, $da);
        });

        $total = count($items);

        // Paginate
        $paged = array_slice($items, $offset, $per_page);

        return new WP_REST_Response([
            'success'      => true,
            'tournaments'  => $paged,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $per_page,
            'total_pages'  => (int) ceil($total / $per_page),
        ], 200);
    }
}
