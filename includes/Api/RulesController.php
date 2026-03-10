<?php
/**
 * Rules Engine REST API Controller
 *
 * CRUD for game rule configurations stored in bl_game_rules.
 *
 * @package BattleLedger
 * @since   1.0.0
 */

namespace BattleLedger\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class RulesController {

    /**
     * API namespace
     */
    const NAMESPACE = 'battle-ledger/v1';

    /* ------------------------------------------------------------------
     * Route registration
     * ----------------------------------------------------------------*/

    public static function register_routes(): void {
        // List all rules
        register_rest_route(self::NAMESPACE, '/rules', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_rules'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create_rule'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        // Single rule CRUD
        register_rest_route(self::NAMESPACE, '/rules/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_rule'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [self::class, 'update_rule'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'delete_rule'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        // Toggle active state
        register_rest_route(self::NAMESPACE, '/rules/(?P<id>\d+)/toggle', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'toggle_active'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        // Duplicate a rule
        register_rest_route(self::NAMESPACE, '/rules/(?P<id>\d+)/duplicate', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'duplicate_rule'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        // Reorder rules
        register_rest_route(self::NAMESPACE, '/rules/reorder', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'reorder_rules'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        // Public: get active rules (for frontend / tournament creation)
        register_rest_route(self::NAMESPACE, '/rules/active', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_active_rules'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------*/

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_game_rules';
    }

    /**
     * Decode JSON columns of a raw DB row into arrays.
     */
    private static function hydrate($row): array {
        $json_cols = ['all_maps', 'all_team_modes', 'all_player_counts', 'player_fields', 'available_settings', 'game_modes'];
        $data = (array) $row;
        foreach ($json_cols as $col) {
            $data[$col] = json_decode($data[$col] ?? '[]', true) ?: [];
        }
        $data['id']        = (int) $data['id'];
        $data['is_active']  = (bool) $data['is_active'];
        $data['sort_order'] = (int) $data['sort_order'];
        return $data;
    }

    /**
     * Sanitise & prepare writeable columns from request params.
     */
    private static function prepare_data(array $params): array {
        $data = [];

        if (isset($params['game_name'])) {
            $data['game_name'] = sanitize_text_field($params['game_name']);
        }
        if (isset($params['slug'])) {
            $data['slug'] = sanitize_title($params['slug']);
        } elseif (isset($params['game_name']) && !isset($params['slug'])) {
            $data['slug'] = sanitize_title($params['game_name']);
        }
        if (isset($params['game_icon'])) {
            $data['game_icon'] = esc_url_raw($params['game_icon']);
        }
        if (isset($params['game_image'])) {
            $data['game_image'] = esc_url_raw($params['game_image']);
        }
        if (isset($params['is_active'])) {
            $data['is_active'] = $params['is_active'] ? 1 : 0;
        }
        if (isset($params['sort_order'])) {
            $data['sort_order'] = (int) $params['sort_order'];
        }

        // JSON columns — store as encoded strings
        $json_cols = ['all_maps', 'all_team_modes', 'all_player_counts', 'player_fields', 'available_settings', 'game_modes'];
        foreach ($json_cols as $col) {
            if (isset($params[$col])) {
                $data[$col] = wp_json_encode($params[$col]);
            }
        }

        $data['updated_at'] = current_time('mysql');

        return $data;
    }

    /* ------------------------------------------------------------------
     * Endpoints
     * ----------------------------------------------------------------*/

    /**
     * GET /rules — list all rules ordered by sort_order
     */
    public static function get_rules(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY sort_order ASC, id ASC');
        $rules = array_map([self::class, 'hydrate'], $rows);

        return new WP_REST_Response([
            'success' => true,
            'rules'   => $rules,
            'total'   => count($rules),
        ], 200);
    }

    /**
     * GET /rules/{id}
     */
    public static function get_rule(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id  = (int) $request->get_param('id');
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));

        if (!$row) {
            return new WP_REST_Response(['success' => false, 'message' => 'Rule not found.'], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'rule'    => self::hydrate($row),
        ], 200);
    }

    /**
     * POST /rules — create a new game rule
     */
    public static function create_rule(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $params = $request->get_json_params();

        if (empty($params['game_name'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Game name is required.'], 400);
        }

        $data = self::prepare_data($params);
        $data['created_at'] = current_time('mysql');

        // Ensure unique slug
        $data['slug'] = self::unique_slug($data['slug'] ?? sanitize_title($params['game_name']));

        // Default sort_order = max + 1
        if (!isset($data['sort_order'])) {
            $max = (int) $wpdb->get_var('SELECT MAX(sort_order) FROM ' . self::table());
            $data['sort_order'] = $max + 1;
        }

        $wpdb->insert(self::table(), $data);
        $new_id = $wpdb->insert_id;

        if (!$new_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Failed to create rule.'], 500);
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $new_id));

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Game rule created successfully.',
            'rule'    => self::hydrate($row),
        ], 201);
    }

    /**
     * PUT /rules/{id}
     */
    public static function update_rule(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id     = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$existing) {
            return new WP_REST_Response(['success' => false, 'message' => 'Rule not found.'], 404);
        }

        $data = self::prepare_data($params);

        // If slug changed, ensure uniqueness
        if (isset($data['slug']) && $data['slug'] !== $existing->slug) {
            $data['slug'] = self::unique_slug($data['slug'], $id);
        }

        $wpdb->update(self::table(), $data, ['id' => $id]);

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Game rule updated successfully.',
            'rule'    => self::hydrate($row),
        ], 200);
    }

    /**
     * DELETE /rules/{id}
     */
    public static function delete_rule(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $existing = $wpdb->get_row($wpdb->prepare('SELECT game_name FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$existing) {
            return new WP_REST_Response(['success' => false, 'message' => 'Rule not found.'], 404);
        }

        $wpdb->delete(self::table(), ['id' => $id]);

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf('"%s" deleted successfully.', $existing->game_name),
        ], 200);
    }

    /**
     * POST /rules/{id}/toggle
     */
    public static function toggle_active(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $row = $wpdb->get_row($wpdb->prepare('SELECT id, is_active, game_name FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$row) {
            return new WP_REST_Response(['success' => false, 'message' => 'Rule not found.'], 404);
        }

        $new_state = $row->is_active ? 0 : 1;
        $wpdb->update(self::table(), ['is_active' => $new_state, 'updated_at' => current_time('mysql')], ['id' => $id]);

        return new WP_REST_Response([
            'success'   => true,
            'is_active' => (bool) $new_state,
            'message'   => sprintf('%s %s.', $row->game_name, $new_state ? 'activated' : 'deactivated'),
        ], 200);
    }

    /**
     * POST /rules/{id}/duplicate
     */
    public static function duplicate_rule(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));
        if (!$row) {
            return new WP_REST_Response(['success' => false, 'message' => 'Rule not found.'], 404);
        }

        $data = (array) $row;
        unset($data['id']);
        $data['game_name']  = $data['game_name'] . ' (Copy)';
        $data['slug']       = self::unique_slug(sanitize_title($data['game_name']));
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $max = (int) $wpdb->get_var('SELECT MAX(sort_order) FROM ' . self::table());
        $data['sort_order'] = $max + 1;

        $wpdb->insert(self::table(), $data);
        $new_id = $wpdb->insert_id;

        $new_row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $new_id));

        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf('"%s" duplicated.', $row->game_name),
            'rule'    => self::hydrate($new_row),
        ], 201);
    }

    /**
     * POST /rules/reorder  body: { ids: [3, 1, 2] }
     */
    public static function reorder_rules(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $ids = $request->get_param('ids');

        if (!is_array($ids)) {
            return new WP_REST_Response(['success' => false, 'message' => 'ids array required.'], 400);
        }

        foreach ($ids as $order => $id) {
            $wpdb->update(self::table(), ['sort_order' => (int) $order], ['id' => (int) $id]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Order updated.',
        ], 200);
    }

    /**
     * GET /rules/active — public, returns only active rules
     */
    public static function get_active_rules(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $rows  = $wpdb->get_results('SELECT * FROM ' . self::table() . ' WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        $rules = array_map([self::class, 'hydrate'], $rows);

        return new WP_REST_Response([
            'success' => true,
            'rules'   => $rules,
        ], 200);
    }

    /* ------------------------------------------------------------------
     * Unique slug helper
     * ----------------------------------------------------------------*/

    private static function unique_slug(string $slug, int $exclude_id = 0): string {
        global $wpdb;
        $table    = self::table();
        $original = $slug;
        $counter  = 1;

        while (true) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE slug = %s AND id != %d LIMIT 1",
                $slug,
                $exclude_id
            ));
            if (!$existing) {
                break;
            }
            $slug = $original . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /* ------------------------------------------------------------------
     * Default seed data (called by Installer on activation)
     * ----------------------------------------------------------------*/

    public static function get_default_seeds(): array {
        return [
            // ── Free Fire ──
            [
                'game_name'  => 'Free Fire',
                'slug'       => 'free-fire',
                'game_icon'  => '',
                'game_image' => '',
                'all_maps'   => [
                    ['id' => 'bermuda',   'name' => 'Bermuda'],
                    ['id' => 'nexterra',  'name' => 'Nexterra'],
                    ['id' => 'alpine',    'name' => 'Alpine'],
                    ['id' => 'purgatory', 'name' => 'Purgatory'],
                    ['id' => 'kalahari',  'name' => 'Kalahari'],
                    ['id' => 'solara',    'name' => 'Solara'],
                ],
                'all_team_modes' => [
                    ['id' => 'solo',  'name' => 'Solo',  'playersPerTeam' => 1],
                    ['id' => 'duo',   'name' => 'Duo',   'playersPerTeam' => 2],
                    ['id' => 'squad', 'name' => 'Squad', 'playersPerTeam' => 4],
                    ['id' => '6v6',   'name' => '6v6',   'playersPerTeam' => 6],
                ],
                'all_player_counts' => [48, 32, 24, 20, 12, 8, 6, 4, 2],
                'player_fields' => [
                    ['id' => 'uid', 'name' => 'Player UID', 'type' => 'number', 'placeholder' => 'Enter your Free Fire UID', 'required' => true, 'validation' => '^[0-9]{6,12}$'],
                    ['id' => 'ign', 'name' => 'In-Game Name', 'type' => 'text', 'placeholder' => 'Enter your IGN', 'required' => true],
                ],
                'available_settings' => [
                    ['id' => 'gun-attributes',  'name' => 'Gun Attributes',    'enabled' => false],
                    ['id' => 'character-skills', 'name' => 'Character Skills', 'enabled' => false],
                    ['id' => 'pets',             'name' => 'Pets',             'enabled' => false],
                    ['id' => 'loadouts',         'name' => 'Loadouts/Airdrops', 'enabled' => true],
                    ['id' => 'emulator-block',   'name' => 'Emulator Block',   'enabled' => true],
                ],
                'game_modes' => [
                    [
                        'id'                  => 'battle-royale',
                        'name'                => 'Battle Royale',
                        'allowedMaps'         => ['bermuda', 'nexterra', 'alpine', 'purgatory', 'kalahari'],
                        'allowedTeamModes'    => ['solo', 'duo', 'squad'],
                        'allowedPlayerCounts' => [48, 32, 24, 20, 12],
                        'settings'            => [
                            ['id' => 'gun-attributes',  'name' => 'Gun Attributes',    'enabled' => false],
                            ['id' => 'character-skills', 'name' => 'Character Skills', 'enabled' => false],
                            ['id' => 'pets',             'name' => 'Pets',             'enabled' => false],
                            ['id' => 'loadouts',         'name' => 'Loadouts/Airdrops', 'enabled' => true],
                            ['id' => 'emulator-block',   'name' => 'Emulator Block',   'enabled' => true],
                        ],
                    ],
                    [
                        'id'                  => 'clash-squad',
                        'name'                => 'Clash Squad',
                        'allowedMaps'         => ['bermuda', 'kalahari'],
                        'allowedTeamModes'    => ['squad'],
                        'allowedPlayerCounts' => [8],
                        'settings'            => [
                            ['id' => 'gun-attributes',  'name' => 'Gun Attributes',    'enabled' => false],
                            ['id' => 'character-skills', 'name' => 'Character Skills', 'enabled' => false],
                            ['id' => 'pets',             'name' => 'Pets',             'enabled' => false],
                            ['id' => 'loadouts',         'name' => 'Loadouts/Airdrops', 'enabled' => true],
                            ['id' => 'emulator-block',   'name' => 'Emulator Block',   'enabled' => true],
                        ],
                    ],
                    [
                        'id'                  => 'lone-wolf',
                        'name'                => 'Lone Wolf',
                        'allowedMaps'         => ['bermuda', 'alpine'],
                        'allowedTeamModes'    => ['solo', 'duo'],
                        'allowedPlayerCounts' => [2, 4],
                        'settings'            => [
                            ['id' => 'gun-attributes',  'name' => 'Gun Attributes',    'enabled' => false],
                            ['id' => 'character-skills', 'name' => 'Character Skills', 'enabled' => false],
                            ['id' => 'pets',             'name' => 'Pets',             'enabled' => false],
                            ['id' => 'loadouts',         'name' => 'Loadouts/Airdrops', 'enabled' => true],
                            ['id' => 'emulator-block',   'name' => 'Emulator Block',   'enabled' => true],
                        ],
                    ],
                ],
            ],

            // ── PUBG Mobile ──
            [
                'game_name'  => 'PUBG Mobile',
                'slug'       => 'pubg-mobile',
                'game_icon'  => '',
                'game_image' => '',
                'all_maps'   => [
                    ['id' => 'erangel', 'name' => 'Erangel'],
                    ['id' => 'miramar', 'name' => 'Miramar'],
                    ['id' => 'sanhok',  'name' => 'Sanhok'],
                    ['id' => 'vikendi', 'name' => 'Vikendi'],
                    ['id' => 'livik',   'name' => 'Livik'],
                    ['id' => 'karakin', 'name' => 'Karakin'],
                ],
                'all_team_modes' => [
                    ['id' => 'solo',  'name' => 'Solo',  'playersPerTeam' => 1],
                    ['id' => 'duo',   'name' => 'Duo',   'playersPerTeam' => 2],
                    ['id' => 'squad', 'name' => 'Squad', 'playersPerTeam' => 4],
                ],
                'all_player_counts' => [100, 64, 32, 16, 8, 4],
                'player_fields' => [
                    ['id' => 'character-id', 'name' => 'Character ID', 'type' => 'number', 'placeholder' => 'Enter your Character ID', 'required' => true],
                    ['id' => 'username',     'name' => 'Username',     'type' => 'text',   'placeholder' => 'Enter your username',     'required' => true],
                ],
                'available_settings' => [
                    ['id' => 'gyroscope',      'name' => 'Gyroscope Allowed', 'enabled' => true],
                    ['id' => 'triggers',       'name' => 'Triggers Allowed',  'enabled' => false],
                    ['id' => 'emulator-block', 'name' => 'Emulator Block',    'enabled' => true],
                    ['id' => 'voice-chat',     'name' => 'Voice Chat',        'enabled' => true],
                ],
                'game_modes' => [
                    [
                        'id'                  => 'classic',
                        'name'                => 'Classic',
                        'allowedMaps'         => ['erangel', 'miramar', 'sanhok', 'vikendi', 'livik'],
                        'allowedTeamModes'    => ['solo', 'duo', 'squad'],
                        'allowedPlayerCounts' => [100, 64],
                        'settings'            => [
                            ['id' => 'gyroscope',      'name' => 'Gyroscope Allowed', 'enabled' => true],
                            ['id' => 'triggers',       'name' => 'Triggers Allowed',  'enabled' => false],
                            ['id' => 'emulator-block', 'name' => 'Emulator Block',    'enabled' => true],
                            ['id' => 'voice-chat',     'name' => 'Voice Chat',        'enabled' => true],
                        ],
                    ],
                    [
                        'id'                  => 'arena',
                        'name'                => 'Arena',
                        'allowedMaps'         => ['livik', 'karakin'],
                        'allowedTeamModes'    => ['solo', 'duo'],
                        'allowedPlayerCounts' => [16, 8],
                        'settings'            => [
                            ['id' => 'gyroscope',      'name' => 'Gyroscope Allowed', 'enabled' => true],
                            ['id' => 'triggers',       'name' => 'Triggers Allowed',  'enabled' => false],
                            ['id' => 'emulator-block', 'name' => 'Emulator Block',    'enabled' => true],
                            ['id' => 'voice-chat',     'name' => 'Voice Chat',        'enabled' => true],
                        ],
                    ],
                    [
                        'id'                  => 'tdm',
                        'name'                => 'Team Deathmatch',
                        'allowedMaps'         => ['erangel'],
                        'allowedTeamModes'    => ['squad'],
                        'allowedPlayerCounts' => [8],
                        'settings'            => [
                            ['id' => 'gyroscope',      'name' => 'Gyroscope Allowed', 'enabled' => true],
                            ['id' => 'triggers',       'name' => 'Triggers Allowed',  'enabled' => false],
                            ['id' => 'emulator-block', 'name' => 'Emulator Block',    'enabled' => true],
                            ['id' => 'voice-chat',     'name' => 'Voice Chat',        'enabled' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
