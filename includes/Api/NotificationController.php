<?php
namespace BattleLedger\Api;

/**
 * Notification REST API controller
 *
 * Admin endpoints (require manage_battle_ledger):
 *   GET    /notifications              — List admin notifications (user_id=0)
 *   GET    /notifications/unread-count — Unread badge count
 *   POST   /notifications/mark-read   — Mark specific IDs as read
 *   POST   /notifications/mark-all-read — Mark all as read
 *   DELETE /notifications/{id}         — Delete single
 *   DELETE /notifications              — Bulk-delete
 *
 * User endpoints (require logged-in):
 *   GET    /user/notifications              — List current user's notifications
 *   GET    /user/notifications/unread-count — Unread badge count
 *   POST   /user/notifications/mark-read   — Mark specific IDs as read
 *   POST   /user/notifications/mark-all-read — Mark all as read
 *   DELETE /user/notifications/{id}         — Delete single
 */
class NotificationController {

    /* ──────────────────────────────────────────
       Route registration
       ────────────────────────────────────────── */

    public static function register_routes() {

        /* ── Admin routes ──────────────────────── */

        register_rest_route('battle-ledger/v1', '/notifications', [
            [
                'methods'             => 'GET',
                'callback'            => [__CLASS__, 'get_notifications'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [__CLASS__, 'bulk_delete'],
                'permission_callback' => [RestController::class, 'check_permissions'],
            ],
        ]);

        register_rest_route('battle-ledger/v1', '/notifications/unread-count', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'unread_count'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/notifications/mark-read', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'mark_read'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/notifications/mark-all-read', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'mark_all_read'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        register_rest_route('battle-ledger/v1', '/notifications/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'delete_notification'],
            'permission_callback' => [RestController::class, 'check_permissions'],
        ]);

        /* ── User routes ───────────────────────── */

        register_rest_route('battle-ledger/v1', '/user/notifications', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'user_get_notifications'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/user/notifications/unread-count', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'user_unread_count'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/user/notifications/mark-read', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'user_mark_read'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/user/notifications/mark-all-read', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'user_mark_all_read'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/user/notifications/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [__CLASS__, 'user_delete_notification'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /* ──────────────────────────────────────────
       GET /notifications
       ────────────────────────────────────────── */

    public static function get_notifications(\WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $offset   = ($page - 1) * $per_page;

        // Optional filters
        $type    = sanitize_text_field($request->get_param('type') ?? '');
        $is_read = $request->get_param('is_read');

        $where = ['1=1'];
        $values = [];

        // Admin-level notifications: user_id = 0 means "for all admins"
        $where[] = 'user_id = 0';

        if ($type) {
            $where[]  = 'type = %s';
            $values[] = $type;
        }
        if ($is_read !== null && $is_read !== '') {
            $where[]  = 'is_read = %d';
            $values[] = (int) $is_read;
        }

        $where_sql = implode(' AND ', $where);

        // Total count
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        $total = $values
            ? (int) $wpdb->get_var($wpdb->prepare($count_query, ...$values))
            : (int) $wpdb->get_var($count_query);

        // Fetch rows
        $data_query = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_values = array_merge($values, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($data_query, ...$all_values));

        $notifications = array_map([__CLASS__, 'format_row'], $rows ?: []);

        return new \WP_REST_Response([
            'notifications' => $notifications,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $per_page,
            'total_pages'   => (int) ceil($total / $per_page),
        ]);
    }

    /* ──────────────────────────────────────────
       GET /notifications/unread-count
       ────────────────────────────────────────── */

    public static function unread_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE user_id = 0 AND is_read = 0"
        );

        return new \WP_REST_Response(['count' => $count]);
    }

    /* ──────────────────────────────────────────
       POST /notifications/mark-read  { ids: [1,2,3] }
       ────────────────────────────────────────── */

    public static function mark_read(\WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $ids = $request->get_json_params()['ids'] ?? [];
        $ids = array_map('absint', (array) $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return new \WP_REST_Response(['message' => 'No IDs provided.'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET is_read = 1 WHERE id IN ($placeholders)",
            ...$ids
        ));

        return new \WP_REST_Response(['message' => 'Marked as read.']);
    }

    /* ──────────────────────────────────────────
       POST /notifications/mark-all-read
       ────────────────────────────────────────── */

    public static function mark_all_read() {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $wpdb->update($table, ['is_read' => 1], ['user_id' => 0, 'is_read' => 0]);

        return new \WP_REST_Response(['message' => 'All notifications marked as read.']);
    }

    /* ──────────────────────────────────────────
       DELETE /notifications/{id}
       ────────────────────────────────────────── */

    public static function delete_notification(\WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $id = (int) $request['id'];
        $wpdb->delete($table, ['id' => $id], ['%d']);

        return new \WP_REST_Response(['message' => 'Notification deleted.']);
    }

    /* ──────────────────────────────────────────
       DELETE /notifications  { ids: [1,2,3] }
       ────────────────────────────────────────── */

    public static function bulk_delete(\WP_REST_Request $request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $ids = $request->get_json_params()['ids'] ?? [];
        $ids = array_map('absint', (array) $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return new \WP_REST_Response(['message' => 'No IDs provided.'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id IN ($placeholders)",
            ...$ids
        ));

        return new \WP_REST_Response(['message' => 'Notifications deleted.']);
    }

    /* ══════════════════════════════════════════
       USER ENDPOINTS
       ══════════════════════════════════════════ */

    /* ──────────────────────────────────────────
       GET /user/notifications
       ────────────────────────────────────────── */

    public static function user_get_notifications(\WP_REST_Request $request) {
        global $wpdb;
        $table   = $wpdb->prefix . 'bl_notifications';
        $user_id = get_current_user_id();

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $offset   = ($page - 1) * $per_page;

        $type    = sanitize_text_field($request->get_param('type') ?? '');
        $is_read = $request->get_param('is_read');

        $where  = ['user_id = %d'];
        $values = [$user_id];

        if ($type) {
            $where[]  = 'type = %s';
            $values[] = $type;
        }
        if ($is_read !== null && $is_read !== '') {
            $where[]  = 'is_read = %d';
            $values[] = (int) $is_read;
        }

        $where_sql = implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $where_sql",
            ...$values
        ));

        $all_values = array_merge($values, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$all_values
        ));

        return new \WP_REST_Response([
            'notifications' => array_map([__CLASS__, 'format_row'], $rows ?: []),
            'total'         => $total,
            'page'          => $page,
            'per_page'      => $per_page,
            'total_pages'   => (int) ceil($total / $per_page),
        ]);
    }

    /* ──────────────────────────────────────────
       GET /user/notifications/unread-count
       ────────────────────────────────────────── */

    public static function user_unread_count() {
        global $wpdb;
        $table   = $wpdb->prefix . 'bl_notifications';
        $user_id = get_current_user_id();

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0",
            $user_id
        ));

        return new \WP_REST_Response(['count' => $count]);
    }

    /* ──────────────────────────────────────────
       POST /user/notifications/mark-read  { ids: [1,2,3] }
       ────────────────────────────────────────── */

    public static function user_mark_read(\WP_REST_Request $request) {
        global $wpdb;
        $table   = $wpdb->prefix . 'bl_notifications';
        $user_id = get_current_user_id();

        $ids = $request->get_json_params()['ids'] ?? [];
        $ids = array_map('absint', (array) $ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return new \WP_REST_Response(['message' => 'No IDs provided.'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET is_read = 1 WHERE user_id = %d AND id IN ($placeholders)",
            $user_id,
            ...$ids
        ));

        return new \WP_REST_Response(['message' => 'Marked as read.']);
    }

    /* ──────────────────────────────────────────
       POST /user/notifications/mark-all-read
       ────────────────────────────────────────── */

    public static function user_mark_all_read() {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $wpdb->update($table, ['is_read' => 1], [
            'user_id' => get_current_user_id(),
            'is_read' => 0,
        ]);

        return new \WP_REST_Response(['message' => 'All notifications marked as read.']);
    }

    /* ──────────────────────────────────────────
       DELETE /user/notifications/{id}
       ────────────────────────────────────────── */

    public static function user_delete_notification(\WP_REST_Request $request) {
        global $wpdb;
        $table   = $wpdb->prefix . 'bl_notifications';
        $user_id = get_current_user_id();
        $id      = (int) $request['id'];

        // Only delete if it belongs to this user
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE id = %d AND user_id = %d",
            $id,
            $user_id
        ));

        return new \WP_REST_Response(['message' => 'Notification deleted.']);
    }

    /* ──────────────────────────────────────────
       Helpers
       ────────────────────────────────────────── */

    private static function format_row($row): array {
        return [
            'id'         => (int) $row->id,
            'user_id'    => (int) $row->user_id,
            'type'       => $row->type,
            'title'      => $row->title,
            'message'    => $row->message,
            'icon'       => $row->icon,
            'link'       => $row->link,
            'is_read'    => (bool) $row->is_read,
            'metadata'   => $row->metadata ? json_decode($row->metadata, true) : null,
            'created_at' => $row->created_at,
        ];
    }
}
