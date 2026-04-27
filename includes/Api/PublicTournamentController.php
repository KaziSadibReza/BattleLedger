<?php
namespace BattleLedger\Api;

use BattleLedger\Wallet\WalletManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Public Tournament API controller
 * 
 * Public/logged-in endpoints for frontend live-tournament browsing & joining.
 */
class PublicTournamentController {

    /**
     * Register routes
     */
    public static function register_routes() {

        // Public: list active tournaments with participant counts
        register_rest_route('battle-ledger/v1', '/public/tournaments', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_live_tournaments'],
            'permission_callback' => '__return_true',
        ]);

        // Public: single tournament detail
        register_rest_route('battle-ledger/v1', '/public/tournaments/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_tournament_detail'],
            'permission_callback' => '__return_true',
        ]);

        // Logged-in: join a tournament (wallet debit)
        register_rest_route('battle-ledger/v1', '/public/tournaments/(?P<id>\d+)/join', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'join_tournament'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Logged-in: check join status
        register_rest_route('battle-ledger/v1', '/public/tournaments/(?P<id>\d+)/join-status', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'check_join_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Logged-in: get wallet balance (lightweight)
        register_rest_route('battle-ledger/v1', '/public/wallet-balance', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_wallet_balance'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /* -----------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private static function tournaments_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_tournaments';
    }

    private static function participants_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_tournament_participants';
    }

    private static function rules_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_game_rules';
    }

    /**
     * Look up a game rule by slug (game_type).
     */
    private static function get_game_rule(string $game_type): ?array {
        if (empty($game_type)) return null;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::rules_table() . " WHERE slug = %s LIMIT 1",
            $game_type
        ));
        if (!$row) return null;

        $json_cols = ['all_maps', 'all_team_modes', 'all_player_counts', 'player_fields', 'available_settings', 'game_modes'];
        $data = (array) $row;
        foreach ($json_cols as $col) {
            $data[$col] = json_decode($data[$col] ?? '[]', true) ?: [];
        }
        return $data;
    }

    /**
     * Resolve how many player slots one registration occupies.
     *
     * @param array|null $game_rule  Hydrated game rule from get_game_rule().
     * @param string     $team_mode  Team mode id stored in tournament settings.
     * @return int  Number of slots (defaults to 1 for solo / unknown).
     */
    private static function get_team_mode_slots(?array $game_rule, string $team_mode): int {
        if (!$game_rule || empty($team_mode)) return 1;
        foreach ($game_rule['all_team_modes'] as $tm) {
            if ($tm['id'] === $team_mode) {
                return max(1, (int) ($tm['playersPerTeam'] ?? 1));
            }
        }
        return 1;
    }

    /**
     * Return the player identity fields required by a tournament.
     * Uses the tournament's settings.player_fields (ID list) to filter
     * the full field list from the game rule.  Falls back to all fields.
     */
    private static function resolve_player_fields(?array $game_rule, array $tournament_settings): array {
        if (!$game_rule || empty($game_rule['player_fields'])) return [];

        $all_fields = $game_rule['player_fields'];

        // If the tournament specifies a subset of field IDs, filter
        $selected_ids = $tournament_settings['player_fields'] ?? [];
        if (!empty($selected_ids) && is_array($selected_ids)) {
            $all_fields = array_values(array_filter($all_fields, function ($f) use ($selected_ids) {
                return in_array($f['id'], $selected_ids, true);
            }));
        }

        return $all_fields;
    }

    /**
     * Hydrate a raw DB row for public consumption (no admin-only data).
     */
    private static function hydrate_public(object $row): array {
        $settings = json_decode($row->settings ?? '{}', true) ?: [];

        // Compute effective status — active + past end_date → awaiting_results
        $status = $row->status;
        if ($status === 'active' && !empty($row->end_date) && strtotime($row->end_date) < time()) {
            $status = 'awaiting_results';
        }

        return [
            'id'               => (int) $row->id,
            'name'             => $row->name,
            'slug'             => $row->slug,
            'description'      => $row->description ?? '',
            'game_type'        => $row->game_type ?? '',
            'status'           => $status,
            'start_date'       => $row->start_date,
            'end_date'         => $row->end_date,
            'max_participants' => (int) $row->max_participants,
            'entry_fee'        => (float) $row->entry_fee,
            'prize_pool'       => (float) $row->prize_pool,
            'participant_count'=> (int) ($row->participant_count ?? 0),
            'settings'         => [
                'game_mode'          => $settings['game_mode'] ?? '',
                'map'                => $settings['map'] ?? '',
                'team_mode'          => $settings['team_mode'] ?? '',
                'winners'            => $settings['winners'] ?? null,
                'prize_per_kill'     => (float) ($settings['prize_per_kill'] ?? 0),
                'prize_distribution' => $settings['prize_distribution'] ?? [],
            ],
            'created_at'       => $row->created_at,
        ];
    }

    /* -----------------------------------------------------------------
     * Endpoints
     * ----------------------------------------------------------------- */

    /**
     * GET /public/tournaments — list active tournaments
     */
    public static function get_live_tournaments(WP_REST_Request $request) {
        global $wpdb;

        $page      = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page  = max(1, min(50, (int) ($request->get_param('per_page') ?? 50)));
        $offset    = ($page - 1) * $per_page;
        $game_type = sanitize_text_field($request->get_param('game_type') ?? '');
        $search    = sanitize_text_field($request->get_param('search') ?? '');

        $table        = self::tournaments_table();
        $participants = self::participants_table();

        $where = ["t.status = 'active'"];
        $args  = [];

        if ($game_type) {
            $where[] = 't.game_type = %s';
            $args[]  = $game_type;
        }
        if ($search) {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(t.name LIKE %s OR t.description LIKE %s)', $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM $table t WHERE $where_sql";
        $total     = count($args)
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$args))
            : (int) $wpdb->get_var($count_sql);

        $sql = "SELECT t.*, COALESCE(pc.cnt, 0) AS participant_count
                FROM $table t
                LEFT JOIN (
                    SELECT tournament_id, SUM(COALESCE(slots, 1)) AS cnt
                    FROM $participants
                    GROUP BY tournament_id
                ) pc ON pc.tournament_id = t.id
                WHERE $where_sql
                ORDER BY t.start_date ASC
                LIMIT %d OFFSET %d";

        $args[] = $per_page;
        $args[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));
        $tournaments = array_map([__CLASS__, 'hydrate_public'], $rows ?: []);

        return rest_ensure_response([
            'success'     => true,
            'tournaments' => $tournaments,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    /**
     * GET /public/tournaments/{id} — single tournament with participants list
     */
    public static function get_tournament_detail(WP_REST_Request $request) {
        global $wpdb;

        $id           = (int) $request->get_param('id');
        $table        = self::tournaments_table();
        $participants = self::participants_table();

        $sql = "SELECT t.*, COALESCE(pc.cnt, 0) AS participant_count
                FROM $table t
                LEFT JOIN (
                    SELECT tournament_id, SUM(COALESCE(slots, 1)) AS cnt
                    FROM $participants
                    WHERE tournament_id = %d
                    GROUP BY tournament_id
                ) pc ON pc.tournament_id = t.id
                WHERE t.id = %d AND t.status = 'active'
                LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, $id, $id));

        if (!$row) {
            return new WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        $tournament = self::hydrate_public($row);

        // Look up game rule for player fields & team mode slots
        $game_rule = self::get_game_rule($row->game_type ?? '');
        $settings  = json_decode($row->settings ?? '{}', true) ?: [];
        $team_mode = $settings['team_mode'] ?? '';

        $tournament['team_mode_slots'] = self::get_team_mode_slots($game_rule, $team_mode);
        $tournament['player_fields']   = self::resolve_player_fields($game_rule, $settings);

        // Resolve team mode display name
        $tournament['team_mode_name'] = '';
        if ($game_rule && $team_mode) {
            foreach ($game_rule['all_team_modes'] as $tm) {
                if ($tm['id'] === $team_mode) {
                    $tournament['team_mode_name'] = $tm['name'] ?? $team_mode;
                    break;
                }
            }
        }

        // Fetch participant list (display names only)
        $participant_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.user_id, p.team_name, p.status, p.slots, p.metadata, p.registered_at, u.display_name
             FROM $participants p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             WHERE p.tournament_id = %d
             ORDER BY p.registered_at ASC",
            $id
        ));

        $tournament['participants'] = array_map(function ($r) {
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            $players = $meta['players'] ?? [];
            return [
                'user_id'       => (int) $r->user_id,
                'display_name'  => $r->display_name ?? 'Unknown',
                'team_name'     => $r->team_name ?? '',
                'status'        => $r->status,
                'slots'         => (int) ($r->slots ?? 1),
                'registered_at' => $r->registered_at,
                'players'       => $players,
            ];
        }, $participant_rows ?: []);

        // If user is logged in, check join status
        $tournament['is_joined'] = false;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $joined  = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $participants WHERE tournament_id = %d AND user_id = %d",
                $id, $user_id
            ));
            $tournament['is_joined'] = (bool) $joined;

            // Expose room credentials only to joined users
            if ($joined) {
                $room_id = trim((string) ($settings['room_id'] ?? ''));
                $room_password = trim((string) ($settings['room_password'] ?? ''));
                $tournament['room_id']       = $room_id !== '' ? $room_id : 'Loading';
                $tournament['room_password'] = $room_password !== '' ? $room_password : 'Loading';
            }
        }

        return rest_ensure_response([
            'success'    => true,
            'tournament' => $tournament,
        ]);
    }

    /**
     * POST /public/tournaments/{id}/join — join tournament, debit wallet
     *
     * Accepts optional player_data: array of identity field sets
     * (one per team slot, e.g. 4 for Squad mode).
     */
    public static function join_tournament(WP_REST_Request $request) {
        global $wpdb;

        $tournament_id = (int) $request->get_param('id');
        $user_id       = get_current_user_id();
        $team_name     = sanitize_text_field($request->get_param('team_name') ?? '');
        $player_data   = $request->get_param('player_data'); // array of arrays

        // 1. Fetch tournament
        $table = self::tournaments_table();
        $tournament = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $tournament_id
        ));

        if (!$tournament) {
            return new WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        if ($tournament->status !== 'active') {
            return new WP_Error('not_active', __('This tournament is not currently accepting registrations', 'battle-ledger'), ['status' => 400]);
        }

        // Block joining if tournament end_date has passed
        if (!empty($tournament->end_date) && strtotime($tournament->end_date) < time()) {
            return new WP_Error('ended', __('This tournament has ended and is awaiting results', 'battle-ledger'), ['status' => 400]);
        }

        // 2. Resolve game rule, team mode slots, and required player fields
        $settings  = json_decode($tournament->settings ?? '{}', true) ?: [];
        $team_mode = $settings['team_mode'] ?? '';
        $game_rule = self::get_game_rule($tournament->game_type ?? '');
        $slots     = self::get_team_mode_slots($game_rule, $team_mode);
        $required_fields = self::resolve_player_fields($game_rule, $settings);

        // 3. Validate player_data
        if (!empty($required_fields)) {
            if (!is_array($player_data) || count($player_data) !== $slots) {
                return new WP_Error(
                    'invalid_player_data',
                    sprintf(
                        __('Player data must contain exactly %d player(s) for %s mode.', 'battle-ledger'),
                        $slots,
                        ucfirst($team_mode ?: 'solo')
                    ),
                    ['status' => 400, 'required_slots' => $slots]
                );
            }

            // Validate each player's fields
            foreach ($player_data as $idx => $player) {
                if (!is_array($player)) {
                    return new WP_Error(
                        'invalid_player_data',
                        sprintf(__('Player %d data is invalid.', 'battle-ledger'), $idx + 1),
                        ['status' => 400]
                    );
                }

                foreach ($required_fields as $field) {
                    $field_id  = $field['id'];
                    $value     = isset($player[$field_id]) ? trim((string) $player[$field_id]) : '';
                    $required  = !empty($field['required']);

                    if ($required && $value === '') {
                        return new WP_Error(
                            'missing_field',
                            sprintf(
                                __('Player %d: "%s" is required.', 'battle-ledger'),
                                $idx + 1,
                                $field['name']
                            ),
                            ['status' => 400, 'player_index' => $idx, 'field_id' => $field_id]
                        );
                    }

                    // Regex validation
                    if ($value !== '' && !empty($field['validation'])) {
                        $pattern = '/' . $field['validation'] . '/';
                        if (!preg_match($pattern, $value)) {
                            return new WP_Error(
                                'invalid_field',
                                sprintf(
                                    __('Player %d: "%s" has an invalid format.', 'battle-ledger'),
                                    $idx + 1,
                                    $field['name']
                                ),
                                ['status' => 400, 'player_index' => $idx, 'field_id' => $field_id]
                            );
                        }
                    }
                }
            }
        }

        // 4. Check duplicate
        $participants = self::participants_table();
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $participants WHERE tournament_id = %d AND user_id = %d",
            $tournament_id, $user_id
        ));
        if ($existing) {
            return new WP_Error('already_joined', __('You have already joined this tournament', 'battle-ledger'), ['status' => 409]);
        }

        // 5. Check capacity (slot-aware)
        if ($tournament->max_participants > 0) {
            $current_slots = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(COALESCE(slots, 1)), 0) FROM $participants WHERE tournament_id = %d",
                $tournament_id
            ));
            if (($current_slots + $slots) > (int) $tournament->max_participants) {
                return new WP_Error('full', __('Tournament is full — not enough slots remaining', 'battle-ledger'), ['status' => 400]);
            }
        }

        // 6. Handle entry fee
        $entry_fee = (float) $tournament->entry_fee;
        $transaction_id = null;

        if ($entry_fee > 0) {
            $balance = WalletManager::get_balance($user_id);

            if ($balance < $entry_fee) {
                return new WP_Error(
                    'insufficient_balance',
                    sprintf(
                        __('Insufficient wallet balance. Required: %s, Available: %s', 'battle-ledger'),
                        number_format($entry_fee, 2),
                        number_format($balance, 2)
                    ),
                    [
                        'status'   => 400,
                        'required' => $entry_fee,
                        'balance'  => $balance,
                        'shortfall'=> $entry_fee - $balance,
                    ]
                );
            }

            // Debit wallet
            $debit = WalletManager::debit(
                $user_id,
                $entry_fee,
                sprintf(__('Entry fee for tournament: %s', 'battle-ledger'), $tournament->name),
                WalletManager::TYPE_ENTRY_FEE,
                'tournament',
                $tournament_id
            );

            if (is_wp_error($debit)) {
                return $debit;
            }

            $transaction_id = $debit['transaction_id'];
        }

        // 7. Build metadata
        $metadata = [];
        if ($slots > 1) {
            $metadata['team_size'] = $slots;
        }
        if (!empty($player_data)) {
            // Sanitise player data values
            $clean_players = [];
            foreach ($player_data as $player) {
                $clean = [];
                foreach ($player as $k => $v) {
                    $clean[sanitize_text_field($k)] = sanitize_text_field($v);
                }
                $clean_players[] = $clean;
            }
            $metadata['players'] = $clean_players;
        }

        // 8. Insert participant
        $wpdb->insert($participants, [
            'tournament_id' => $tournament_id,
            'user_id'       => $user_id,
            'team_name'     => $team_name,
            'status'        => 'registered',
            'slots'         => $slots,
            'metadata'      => !empty($metadata) ? wp_json_encode($metadata) : null,
        ], ['%d', '%d', '%s', '%s', '%d', '%s']);

        $participant_id = $wpdb->insert_id;

        if (!$participant_id) {
            // Refund if insert failed
            if ($entry_fee > 0 && $transaction_id) {
                WalletManager::credit(
                    $user_id,
                    $entry_fee,
                    sprintf(__('Refund: failed to join tournament %s', 'battle-ledger'), $tournament->name),
                    WalletManager::TYPE_REFUND,
                    'tournament',
                    $tournament_id
                );
            }
            return new WP_Error('db_error', __('Failed to join tournament', 'battle-ledger'), ['status' => 500]);
        }

        // Notify admin: user registered for tournament
        $joining_user = get_userdata($user_id);
        \BattleLedger\Core\NotificationManager::participant_registered(
            $joining_user ? $joining_user->display_name : 'User #' . $user_id,
            ['id' => $tournament_id, 'name' => $tournament->name]
        );

        // Notify user: you joined the tournament
        \BattleLedger\Core\NotificationManager::user_tournament_joined(
            $user_id,
            ['id' => $tournament_id, 'name' => $tournament->name]
        );

        // Check if tournament is now full
        if ($tournament->max_participants > 0) {
            $total_slots = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(COALESCE(slots, 1)), 0) FROM $participants WHERE tournament_id = %d",
                $tournament_id
            ));
            if ($total_slots >= (int) $tournament->max_participants) {
                \BattleLedger\Core\NotificationManager::tournament_full(['id' => $tournament_id, 'name' => $tournament->name]);
            }
        }

        $new_balance = WalletManager::get_balance($user_id);

        return rest_ensure_response([
            'success'        => true,
            'message'        => __('Successfully joined the tournament!', 'battle-ledger'),
            'participant_id' => $participant_id,
            'entry_fee'      => $entry_fee,
            'new_balance'    => $new_balance,
            'slots'          => $slots,
        ]);
    }

    /**
     * GET /public/tournaments/{id}/join-status
     */
    public static function check_join_status(WP_REST_Request $request) {
        global $wpdb;

        $tournament_id = (int) $request->get_param('id');
        $user_id       = get_current_user_id();
        $participants  = self::participants_table();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, registered_at FROM $participants WHERE tournament_id = %d AND user_id = %d",
            $tournament_id, $user_id
        ));

        return rest_ensure_response([
            'success'   => true,
            'is_joined' => (bool) $row,
            'status'    => $row ? $row->status : null,
        ]);
    }

    /**
     * GET /public/wallet-balance — lightweight balance check
     */
    public static function get_wallet_balance(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $balance = WalletManager::get_balance($user_id);

        return rest_ensure_response([
            'success' => true,
            'balance' => (float) $balance,
        ]);
    }
}
