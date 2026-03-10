<?php
namespace BattleLedger\Api;

/**
 * Finished Tournaments API controller.
 *
 * Finished tournaments are immutable snapshots created every time winners
 * are assigned to a main tournament. The original tournament stays alive
 * and can be run again — each finish produces a new record here.
 */
class FinishedTournamentController {

    /* -----------------------------------------------------------------
     * Routes
     * ----------------------------------------------------------------- */

    public static function register_routes() {
        register_rest_route('battle-ledger/v1', '/finished-tournaments', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_finished_tournaments'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/finished-tournaments/(?P<id>\d+)', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_finished_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'delete_finished_tournament'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);
    }

    /* -----------------------------------------------------------------
     * Helpers
     * ----------------------------------------------------------------- */

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bl_finished_tournaments';
    }

    /**
     * Hydrate a raw DB row into a frontend-friendly array.
     */
    private static function hydrate(object $row): array {
        $settings     = json_decode($row->settings ?? '{}', true) ?: [];
        $winners      = json_decode($row->winners ?? '{}', true) ?: [];
        $participants = json_decode($row->participants ?? '[]', true) ?: [];

        return [
            'id'                => (int) $row->id,
            'tournament_id'     => (int) $row->tournament_id,
            'name'              => $row->name,
            'slug'              => $row->slug,
            'description'       => $row->description ?? '',
            'game_type'         => $row->game_type ?? '',
            'start_date'        => $row->start_date,
            'end_date'          => $row->end_date,
            'max_participants'  => (int) $row->max_participants,
            'entry_fee'         => (float) $row->entry_fee,
            'prize_pool'        => (float) $row->prize_pool,
            'settings'          => $settings,
            'participants'      => $participants,
            'participant_count' => (int) $row->participant_count,
            'winners'           => $winners,
            'finished_by'       => (int) ($row->finished_by ?? 0),
            'finished_at'       => $row->finished_at,
        ];
    }

    /* -----------------------------------------------------------------
     * Endpoints
     * ----------------------------------------------------------------- */

    /**
     * GET /finished-tournaments — list all finished tournament snapshots.
     */
    public static function get_finished_tournaments(\WP_REST_Request $request) {
        global $wpdb;

        $per_page = max(1, min(200, (int) ($request->get_param('per_page') ?? 100)));
        $search   = sanitize_text_field($request->get_param('search') ?? '');
        $table    = self::table();

        $where = ['1=1'];

        if ($search) {
            $like    = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(name LIKE %s OR description LIKE %s)', $like, $like);
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY finished_at DESC LIMIT %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $per_page));

        $tournaments = array_map([__CLASS__, 'hydrate'], $rows ?: []);

        return rest_ensure_response([
            'success'     => true,
            'tournaments' => $tournaments,
            'total'       => count($tournaments),
        ]);
    }

    /**
     * GET /finished-tournaments/{id}
     */
    public static function get_finished_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = self::table();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

        if (!$row) {
            return new \WP_Error('not_found', __('Finished tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        return rest_ensure_response(self::hydrate($row));
    }

    /**
     * DELETE /finished-tournaments/{id}
     */
    public static function delete_finished_tournament(\WP_REST_Request $request) {
        global $wpdb;

        $id    = (int) $request->get_param('id');
        $table = self::table();

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE id = %d", $id));
        if (!$exists) {
            return new \WP_Error('not_found', __('Finished tournament not found', 'battle-ledger'), ['status' => 404]);
        }

        $wpdb->delete($table, ['id' => $id], ['%d']);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Finished tournament deleted successfully', 'battle-ledger'),
        ]);
    }
}
