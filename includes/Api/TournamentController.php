<?php
namespace BattleLedger\Api;

use BattleLedger\Wallet\WalletManager;

/**
 * Tournament API controller
 */
class TournamentController {

    /**
     * Register routes
     */
    public static function register_routes() {
        register_rest_route('battle-ledger/v1', '/tournaments', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_tournaments'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'create_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [__CLASS__, 'update_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'delete_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'update_status'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)/duplicate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'duplicate_tournament'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)/winners', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'set_winners'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        /* ── Participant routes ──────────────────────────────── */
        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)/participants', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_participants'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'add_participant'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        register_rest_route('battle-ledger/v1', '/tournaments/(?P<id>\d+)/participants/(?P<pid>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'remove_participant'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        /* ── User search (for adding participants) ──────────── */
        register_rest_route('battle-ledger/v1', '/users/search', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'search_users'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);
    }

    /* -----------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_tournaments';
    }

    /**
     * Hydrate a raw DB row — decode JSON, cast types.
     */
    private static function hydrate(object $row): array {
        return [
            'id'               => (int) $row->id,
            'name'             => $row->name,
            'slug'             => $row->slug,
            'description'      => $row->description ?? '',
            'game_type'        => $row->game_type ?? '',
            'status'           => $row->status,
            'start_date'       => $row->start_date,
            'end_date'         => $row->end_date,
            'max_participants' => (int) $row->max_participants,
            'entry_fee'        => (float) $row->entry_fee,
            'prize_pool'       => (float) $row->prize_pool,
            'settings'         => json_decode($row->settings ?? '{}', true) ?: [],
            'created_by'       => (int) ($row->created_by ?? 0),
            'created_at'       => $row->created_at,
            'updated_at'       => $row->updated_at,
            'participant_count' => (int) ($row->participant_count ?? 0),
        ];
    }

    /**
     * Generate a unique slug
     */
    private static function unique_slug(string $slug, int $exclude_id = 0): string {
        global $wpdb;
        $table = self::table();
        $base  = sanitize_title($slug);
        $try   = $base;
        $i     = 1;

        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND id != %d LIMIT 1",
                $try,
                $exclude_id
            ));
            if (!$existing) {
                return $try;
            }
            $try = $base . '-' . $i++;
        }
    }

    /* -----------------------------------------------------------------
     * Endpoints
     * ----------------------------------------------------------------- */

    /**
     * GET /tournaments — paginated, searchable, filterable by status
     */
    public static function get_tournaments(\WP_REST_Request $request) {
        global $wpdb;

        $page     = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = max(1, min(100, (int) ($request->get_param('per_page') ?? 10)));
        $offset   = ($page - 1) * $per_page;
        $search   = sanitize_text_field($request->get_param('search') ?? '');
        $status   = sanitize_text_field($request->get_param('status') ?? '');
        $live     = (bool) $request->get_param('live');

        $table        = self::table();
        $participants = $wpdb->prefix . 'bl_tournament_participants';

        $where = ['1=1'];

        if ($live) {
            // Show only active tournaments on the Live page.
            $where[] = "t.status = 'active'";
        }

        if ($search) {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(t.name LIKE %s OR t.description LIKE %s)', $like, $like);
        }

        if ($status && $status !== 'all') {
            $where[] = $wpdb->prepare('t.status = %s', $status);
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT t.*, COALESCE(pc.cnt, 0) AS participant_count
                FROM $table t
                LEFT JOIN (
                    SELECT tournament_id, COUNT(*) AS cnt
                    FROM $participants
                    GROUP BY tournament_id
                ) pc ON pc.tournament_id = t.id
                WHERE $where_sql
                ORDER BY t.created_at DESC
                LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page, $offset));

        $count_sql = "SELECT COUNT(*) FROM $table t WHERE $where_sql";
        $total     = (int) $wpdb->get_var($count_sql);

        $tournaments = array_map([__CLASS__, 'hydrate'], $rows);

        return rest_ensure_response([
            'tournaments' => $tournaments,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    /**
     * GET /tournaments/{id}
     */
    public static function get_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = self::table();
        $participants = $wpdb->prefix . 'bl_tournament_participants';

        $sql = "SELECT t.*, COALESCE(pc.cnt, 0) AS participant_count
                FROM $table t
                LEFT JOIN (
                    SELECT tournament_id, COUNT(*) AS cnt
                    FROM $participants
                    WHERE tournament_id = %d
                    GROUP BY tournament_id
                ) pc ON pc.tournament_id = t.id
                WHERE t.id = %d
                LIMIT 1";

        $row = $wpdb->get_row($wpdb->prepare($sql, $id, $id));

        if (!$row) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        return rest_ensure_response(self::hydrate($row));
    }

    /**
     * POST /tournaments
     */
    public static function create_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $params = $request->get_json_params();
        $name   = sanitize_text_field($params['name'] ?? '');

        if (empty($name)) {
            return new \WP_Error('missing_name', __('Tournament name is required', 'battle-ledger'), ['status' => 400]);
        }

        $slug = self::unique_slug($params['slug'] ?? $name);

        $data = [
            'name'             => $name,
            'slug'             => $slug,
            'description'      => wp_kses_post($params['description'] ?? ''),
            'game_type'        => sanitize_text_field($params['game_type'] ?? ''),
            'status'           => sanitize_text_field($params['status'] ?? 'deactive'),
            'start_date'       => sanitize_text_field($params['start_date'] ?? ''),
            'end_date'         => sanitize_text_field($params['end_date'] ?? ''),
            'max_participants' => intval($params['max_participants'] ?? 0),
            'entry_fee'        => floatval($params['entry_fee'] ?? 0),
            'prize_pool'       => floatval($params['prize_pool'] ?? 0),
            'settings'         => wp_json_encode($params['settings'] ?? []),
            'created_by'       => get_current_user_id(),
        ];

        $wpdb->insert(self::table(), $data);
        $id = $wpdb->insert_id;

        if (!$id) {
            return new \WP_Error('db_error', __('Could not create tournament', 'battle-ledger'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success'       => true,
            'tournament_id' => $id,
            'message'       => __('Tournament created successfully', 'battle-ledger'),
        ]);
    }

    /**
     * PUT /tournaments/{id}
     */
    public static function update_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id     = (int) $request->get_param('id');
        $params = $request->get_json_params();
        $table  = self::table();

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status FROM $table WHERE id = %d", $id));
        if (!$row) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        $data = [];

        $text_fields = ['name', 'description', 'game_type', 'status', 'start_date', 'end_date'];
        foreach ($text_fields as $field) {
            if (isset($params[$field])) {
                $data[$field] = ($field === 'description')
                    ? wp_kses_post($params[$field])
                    : sanitize_text_field($params[$field]);
            }
        }

        if (isset($params['slug'])) {
            $data['slug'] = self::unique_slug($params['slug'], $id);
        } elseif (isset($params['name'])) {
            $data['slug'] = self::unique_slug($params['name'], $id);
        }

        $int_fields = ['max_participants'];
        foreach ($int_fields as $field) {
            if (isset($params[$field])) {
                $data[$field] = intval($params[$field]);
            }
        }

        $float_fields = ['entry_fee', 'prize_pool'];
        foreach ($float_fields as $field) {
            if (isset($params[$field])) {
                $data[$field] = floatval($params[$field]);
            }
        }

        if (isset($params['settings'])) {
            $data['settings'] = wp_json_encode($params['settings']);
        }

        // When re-activating via full update, reset participants & clear old winners
        $new_status = $data['status'] ?? '';
        if ($new_status === 'active' && $row->status !== 'active') {
            $wpdb->delete($wpdb->prefix . 'bl_tournament_participants', ['tournament_id' => $id], ['%d']);
            // Strip stale winners from settings (new cycle = new winners)
            if (isset($data['settings'])) {
                $s = json_decode($data['settings'], true) ?: [];
                unset($s['winners']);
                $data['settings'] = wp_json_encode($s);
            }
        }

        if (!empty($data)) {
            $wpdb->update($table, $data, ['id' => $id]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Tournament updated successfully', 'battle-ledger'),
        ]);
    }

    /**
     * DELETE /tournaments/{id}
     */
    public static function delete_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = self::table();

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $id));
        if (!$exists) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        $wpdb->delete($wpdb->prefix . 'bl_tournament_participants', ['tournament_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'bl_tournament_logs', ['tournament_id' => $id]);
        $wpdb->delete($table, ['id' => $id]);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Tournament deleted successfully', 'battle-ledger'),
        ]);
    }

    /**
     * POST /tournaments/{id}/status — Quick status change
     *
     * When activating (→ 'active'), all existing participants are cleared
     * so the tournament starts fresh.
     */
    public static function update_status(\WP_REST_Request $request) {
        global $wpdb;

        $id     = (int) $request->get_param('id');
        $params = $request->get_json_params();
        $status = sanitize_text_field($params['status'] ?? '');

        $valid = ['active', 'deactive', 'draft', 'registration', 'cancelled'];
        if (!in_array($status, $valid, true)) {
            return new \WP_Error('invalid_status', __('Invalid status', 'battle-ledger'), ['status' => 400]);
        }

        $table = self::table();
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $id));
        if (!$exists) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        // When re-activating, reset participants & clear old winners
        if ($status === 'active') {
            $wpdb->delete($wpdb->prefix . 'bl_tournament_participants', ['tournament_id' => $id], ['%d']);
            // Strip stale winners from settings
            $current = $wpdb->get_var($wpdb->prepare("SELECT settings FROM $table WHERE id = %d", $id));
            $s = json_decode($current ?: '{}', true) ?: [];
            if (isset($s['winners'])) {
                unset($s['winners']);
                $wpdb->update($table, ['settings' => wp_json_encode($s)], ['id' => $id]);
            }
        }

        $wpdb->update($table, ['status' => $status], ['id' => $id]);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Status updated', 'battle-ledger'),
        ]);
    }

    /**
     * POST /tournaments/{id}/duplicate
     */
    public static function duplicate_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = self::table();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        $new_name = $row->name . ' (Copy)';
        $new_slug = self::unique_slug($new_name);

        $wpdb->insert($table, [
            'name'             => $new_name,
            'slug'             => $new_slug,
            'description'      => $row->description,
            'game_type'        => $row->game_type,
            'status'           => 'draft',
            'start_date'       => $row->start_date,
            'end_date'         => $row->end_date,
            'max_participants' => $row->max_participants,
            'entry_fee'        => $row->entry_fee,
            'prize_pool'       => $row->prize_pool,
            'settings'         => $row->settings,
            'created_by'       => get_current_user_id(),
        ]);

        $new_id = $wpdb->insert_id;

        return rest_ensure_response([
            'success'       => true,
            'tournament_id' => $new_id,
            'message'       => __('Tournament duplicated', 'battle-ledger'),
        ]);
    }

    /**
     * POST /tournaments/{id}/winners — set 1st/2nd/3rd winners after tournament ends.
     *
     * Creates a snapshot in the finished-tournaments table.  The original
     * tournament is *not* changed — it stays alive and can be run again.
     */
    public static function set_winners(\WP_REST_Request $request) {
        global $wpdb;

        $id      = (int) $request->get_param('id');
        $winners = $request->get_param('winners');

        if (!$winners || !is_array($winners)) {
            return new \WP_Error('invalid_data', __('Winners data is required', 'battle-ledger'), ['status' => 400]);
        }

        $table = self::table();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        // Build sanitised winners — supports first/second/third + place_N keys
        $winners_clean = [
            'first'  => sanitize_text_field($winners['first'] ?? ''),
            'second' => sanitize_text_field($winners['second'] ?? ''),
            'third'  => sanitize_text_field($winners['third'] ?? ''),
        ];
        // Dynamic placements beyond 3rd
        foreach ($winners as $key => $name) {
            if (preg_match('/^place_(\d+)$/', $key, $m)) {
                $winners_clean[$key] = sanitize_text_field($name);
            }
        }

        // Kill counts keyed by display_name from the request
        $kill_counts_raw = $request->get_param('kill_counts');
        $kill_counts = [];
        if (is_array($kill_counts_raw)) {
            foreach ($kill_counts_raw as $name => $count) {
                $kill_counts[sanitize_text_field($name)] = max(0, (int) $count);
            }
        }

        // Tournament settings — check for prize_per_kill
        $tournament_settings = json_decode($row->settings ?: '{}', true) ?: [];
        $prize_per_kill = (float) ($tournament_settings['prize_per_kill'] ?? 0);

        // Snapshot current participants
        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        $parts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.display_name, u.user_email
             FROM $participants_table p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             WHERE p.tournament_id = %d ORDER BY p.registered_at ASC",
            $id
        ));

        $participants_snapshot = array_map(function ($p) use ($kill_counts) {
            $name = $p->display_name ?? 'Unknown';
            return [
                'user_id'       => (int) $p->user_id,
                'display_name'  => $name,
                'user_email'    => $p->user_email ?? '',
                'phone'         => UserController::get_phone((int) $p->user_id),
                'team_name'     => $p->team_name ?? '',
                'status'        => $p->status,
                'rank'          => $p->rank ? (int) $p->rank : null,
                'score'         => (float) $p->score,
                'slots'         => (int) ($p->slots ?? 1),
                'kills'         => (int) ($kill_counts[$name] ?? 0),
                'registered_at' => $p->registered_at,
            ];
        }, $parts ?: []);

        $participant_count = count($participants_snapshot);

        // ── Prize distribution ──────────────────────────────────
        // Build a map: display_name → placement amount
        $prize_pool   = (float) $row->prize_pool;
        $prize_dist   = $tournament_settings['prize_distribution'] ?? [];
        $placement_map = []; // name → amount from placement

        if (!empty($prize_dist) && is_array($prize_dist)) {
            // Dynamic prize distribution configured by admin
            foreach ($prize_dist as $pd) {
                $place  = (int) ($pd['place'] ?? 0);
                $amount = (float) ($pd['amount'] ?? 0);
                if ($place <= 0 || $amount <= 0) continue;

                // Resolve winner name for this placement
                $winner_name = '';
                if ($place === 1) $winner_name = $winners_clean['first'] ?? '';
                elseif ($place === 2) $winner_name = $winners_clean['second'] ?? '';
                elseif ($place === 3) $winner_name = $winners_clean['third'] ?? '';
                else $winner_name = $winners_clean["place_{$place}"] ?? '';

                if ($winner_name) {
                    $placement_map[$winner_name] = ($placement_map[$winner_name] ?? 0) + $amount;
                }
            }
        } else {
            // Legacy: 1st place gets entire prize pool
            if (!empty($winners_clean['first']) && $prize_pool > 0) {
                $placement_map[$winners_clean['first']] = $prize_pool;
            }
        }

        $payouts = [];

        foreach ($participants_snapshot as $p) {
            $user_id  = (int) $p['user_id'];
            $name     = $p['display_name'];
            $kills    = (int) $p['kills'];

            $total = 0;
            $desc_parts = [];

            // Placement prize
            if (isset($placement_map[$name]) && $placement_map[$name] > 0) {
                $total += $placement_map[$name];
                $desc_parts[] = sprintf('Placement prize $%.2f', $placement_map[$name]);
            }

            // Kill prize
            if ($prize_per_kill > 0 && $kills > 0) {
                $kill_total = $kills * $prize_per_kill;
                $total += $kill_total;
                $desc_parts[] = sprintf('%d kills × $%.2f = $%.2f', $kills, $prize_per_kill, $kill_total);
            }

            if ($total > 0 && $user_id > 0) {
                $description = sprintf(
                    'Tournament prize: %s — %s',
                    $row->name,
                    implode(' + ', $desc_parts)
                );

                WalletManager::credit(
                    $user_id,
                    $total,
                    $description,
                    WalletManager::TYPE_PRIZE,
                    'tournament',
                    $id,
                    get_current_user_id()
                );

                $payouts[] = [
                    'user_id' => $user_id,
                    'name'    => $name,
                    'amount'  => $total,
                ];
            }
        }

        // Insert snapshot into finished-tournaments table
        $finished_table = $wpdb->prefix . 'bl_finished_tournaments';
        $wpdb->insert($finished_table, [
            'tournament_id'     => $id,
            'name'              => $row->name,
            'slug'              => $row->slug,
            'description'       => $row->description,
            'game_type'         => $row->game_type,
            'start_date'        => $row->start_date,
            'end_date'          => $row->end_date,
            'max_participants'  => (int) $row->max_participants,
            'entry_fee'         => (float) $row->entry_fee,
            'prize_pool'        => $prize_pool,
            'settings'          => $row->settings,
            'participants'      => wp_json_encode($participants_snapshot),
            'participant_count' => $participant_count,
            'winners'           => wp_json_encode($winners_clean),
            'finished_by'       => get_current_user_id(),
        ]);

        $finished_id = $wpdb->insert_id;

        if (!$finished_id) {
            return new \WP_Error('db_error', __('Could not save finished tournament', 'battle-ledger'), ['status' => 500]);
        }

        // ── Tournament lifecycle reset ──────────────────────────
        $clean_settings = $tournament_settings;
        unset($clean_settings['winners']);
        unset($clean_settings['room_id']);
        unset($clean_settings['room_password']);

        $wpdb->update($table, [
            'status'     => 'deactive',
            'start_date' => null,
            'end_date'   => null,
            'entry_fee'  => 0,
            'prize_pool' => 0,
            'settings'   => wp_json_encode($clean_settings),
        ], ['id' => $id]);

        // Clear all participants — they belong to the finished snapshot now
        $wpdb->delete($wpdb->prefix . 'bl_tournament_participants', ['tournament_id' => $id], ['%d']);

        return rest_ensure_response([
            'success'     => true,
            'finished_id' => $finished_id,
            'payouts'     => $payouts,
            'message'     => __('Winners saved & prizes distributed. Tournament has been deactivated and reset for future use.', 'battle-ledger'),
        ]);
    }

    /* -----------------------------------------------------------------
     * Participant endpoints
     * ----------------------------------------------------------------- */

    /**
     * GET /tournaments/{id}/participants
     */
    public static function get_participants(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = $wpdb->prefix . 'bl_tournament_participants';
        $users = $wpdb->users;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, u.display_name, u.user_email
             FROM $table p
             LEFT JOIN $users u ON u.ID = p.user_id
             WHERE p.tournament_id = %d
             ORDER BY p.registered_at DESC",
            $id
        ));

        $participants = array_map(function ($r) {
            $meta = json_decode($r->metadata ?? '{}', true) ?: [];
            return [
                'id'            => (int) $r->id,
                'tournament_id' => (int) $r->tournament_id,
                'user_id'       => (int) $r->user_id,
                'display_name'  => $r->display_name ?? 'Unknown',
                'user_email'    => $r->user_email ?? '',
                'phone'         => UserController::get_phone((int) $r->user_id),
                'team_name'     => $r->team_name ?? '',
                'status'        => $r->status,
                'rank'          => $r->rank ? (int) $r->rank : null,
                'score'         => (float) $r->score,
                'slots'         => (int) ($r->slots ?? 1),
                'players'       => $meta['players'] ?? [],
                'registered_at' => $r->registered_at,
            ];
        }, $rows ?: []);

        return rest_ensure_response([
            'success'      => true,
            'participants' => $participants,
        ]);
    }

    /**
     * POST /tournaments/{id}/participants — admin manually adds a user
     */
    public static function add_participant(\WP_REST_Request $request) {
        global $wpdb;

        $tournament_id = (int) $request->get_param('id');
        $user_id       = (int) $request->get_param('user_id');
        $team_name     = sanitize_text_field($request->get_param('team_name') ?? '');

        if (!$user_id) {
            return new \WP_Error('missing_user', __('User ID is required', 'battle-ledger'), ['status' => 400]);
        }

        // Verify tournament exists
        $tournament = $wpdb->get_row($wpdb->prepare(
            "SELECT id, max_participants FROM " . self::table() . " WHERE id = %d",
            $tournament_id
        ));
        if (!$tournament) {
            return new \WP_Error('not_found', __('Tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        // Verify user exists
        $user = get_userdata($user_id);
        if (!$user) {
            return new \WP_Error('user_not_found', __('User not found', 'battle-ledger'), ['status' => 404]);
        }

        // Check if already joined
        $table    = $wpdb->prefix . 'bl_tournament_participants';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE tournament_id = %d AND user_id = %d",
            $tournament_id, $user_id
        ));
        if ($existing) {
            return new \WP_Error('already_joined', __('User is already in this tournament', 'battle-ledger'), ['status' => 409]);
        }

        // Check max participants
        if ($tournament->max_participants > 0) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE tournament_id = %d",
                $tournament_id
            ));
            if ($count >= $tournament->max_participants) {
                return new \WP_Error('full', __('Tournament is full', 'battle-ledger'), ['status' => 400]);
            }
        }

        $wpdb->insert($table, [
            'tournament_id' => $tournament_id,
            'user_id'       => $user_id,
            'team_name'     => $team_name,
            'status'        => 'registered',
        ], ['%d', '%d', '%s', '%s']);

        return rest_ensure_response([
            'success' => true,
            'id'      => $wpdb->insert_id,
            'message' => sprintf(__('Added %s to tournament', 'battle-ledger'), $user->display_name),
        ]);
    }

    /**
     * DELETE /tournaments/{id}/participants/{pid}
     */
    public static function remove_participant(\WP_REST_Request $request) {
        global $wpdb;

        $tournament_id = (int) $request->get_param('id');
        $pid           = (int) $request->get_param('pid');
        $table         = $wpdb->prefix . 'bl_tournament_participants';

        $deleted = $wpdb->delete($table, [
            'id'            => $pid,
            'tournament_id' => $tournament_id,
        ], ['%d', '%d']);

        if (!$deleted) {
            return new \WP_Error('not_found', __('Participant not found', 'battle-ledger'), ['status' => 404]);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Participant removed', 'battle-ledger'),
        ]);
    }

    /**
     * GET /users/search?q=... — search WordPress users by name or email
     */
    public static function search_users(\WP_REST_Request $request) {
        $q = sanitize_text_field($request->get_param('q') ?? '');

        if (strlen($q) < 2) {
            return rest_ensure_response(['success' => true, 'users' => []]);
        }

        $user_query = new \WP_User_Query([
            'search'         => '*' . $q . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number'         => 20,
            'orderby'        => 'display_name',
        ]);

        $users = array_map(function ($u) {
            return [
                'id'           => $u->ID,
                'display_name' => $u->display_name,
                'user_email'   => $u->user_email,
                'phone'        => UserController::get_phone($u->ID),
                'avatar'       => get_avatar_url($u->ID, ['size' => 32]),
            ];
        }, $user_query->get_results());

        return rest_ensure_response([
            'success' => true,
            'users'   => $users,
        ]);
    }
}
