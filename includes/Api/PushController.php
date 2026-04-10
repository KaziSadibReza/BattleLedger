<?php
namespace BattleLedger\Api;

use BattleLedger\Core\PushNotificationManager;

/**
 * Push Notification REST API controller
 *
 * Endpoints (all require logged-in user):
 *   GET    /push/vapid-key     — Get the VAPID public key for browser subscription
 *   POST   /push/subscribe     — Save a push subscription
 *   POST   /push/unsubscribe   — Remove a push subscription
 */
class PushController {

    public static function register_routes() {

        register_rest_route('battle-ledger/v1', '/push/vapid-key', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_vapid_key'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/push/subscribe', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'subscribe'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('battle-ledger/v1', '/push/unsubscribe', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'unsubscribe'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * GET /push/vapid-key — return the VAPID public key.
     */
    public static function get_vapid_key() {
        $public_key = PushNotificationManager::get_public_key();

        if ($public_key === '') {
            return new \WP_Error(
                'bl_push_vapid_unavailable',
                'Push notifications are currently unavailable. Server OpenSSL/VAPID key generation failed.',
                ['status' => 500]
            );
        }

        return new \WP_REST_Response([
            'publicKey' => $public_key,
        ]);
    }

    /**
     * POST /push/subscribe — save subscription for current user.
     * Body: { endpoint: string, keys: { p256dh: string, auth: string } }
     */
    public static function subscribe(\WP_REST_Request $request) {
        $body = $request->get_json_params();

        $subscription = [
            'endpoint' => $body['endpoint'] ?? '',
            'keys'     => [
                'p256dh' => $body['keys']['p256dh'] ?? '',
                'auth'   => $body['keys']['auth'] ?? '',
            ],
        ];

        $saved = PushNotificationManager::save_subscription(
            get_current_user_id(),
            $subscription
        );

        if (!$saved) {
            return new \WP_REST_Response(['message' => 'Invalid subscription data.'], 400);
        }

        return new \WP_REST_Response(['message' => 'Subscription saved.']);
    }

    /**
     * POST /push/unsubscribe — remove subscription for current user.
     * Body: { endpoint: string }
     */
    public static function unsubscribe(\WP_REST_Request $request) {
        $body     = $request->get_json_params();
        $endpoint = $body['endpoint'] ?? '';

        if (!$endpoint) {
            return new \WP_REST_Response(['message' => 'Endpoint required.'], 400);
        }

        PushNotificationManager::remove_subscription(
            get_current_user_id(),
            $endpoint
        );

        return new \WP_REST_Response(['message' => 'Subscription removed.']);
    }
}
