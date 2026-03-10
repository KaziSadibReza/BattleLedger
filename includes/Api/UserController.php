<?php
/**
 * User REST API Controller
 * 
 * @package BattleLedger
 * @since 1.0.0
 */

namespace BattleLedger\Api;

use BattleLedger\Auth\OTPManager;
use BattleLedger\Auth\Security;

if (!defined('ABSPATH')) {
    exit;
}

class UserController {
    
    const NAMESPACE = 'battle-ledger/v1';
    
    public static function register_routes(): void {
        $logged_in = function () { return is_user_logged_in(); };

        // Profile CRUD
        register_rest_route(self::NAMESPACE, '/user/profile', [
            ['methods' => 'GET',  'callback' => [self::class, 'get_profile'],    'permission_callback' => $logged_in],
            ['methods' => 'POST', 'callback' => [self::class, 'update_profile'], 'permission_callback' => $logged_in,
                'args' => [
                    'displayName' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'firstName'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'lastName'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                    'bio'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
                    'phone'       => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                ],
            ],
        ]);

        // Avatar upload / delete
        register_rest_route(self::NAMESPACE, '/user/avatar', [
            ['methods' => 'POST',   'callback' => [self::class, 'upload_avatar'], 'permission_callback' => $logged_in],
            ['methods' => 'DELETE', 'callback' => [self::class, 'delete_avatar'], 'permission_callback' => $logged_in],
        ]);

        // Email change — 3-step OTP flow
        register_rest_route(self::NAMESPACE, '/user/email/send-old-otp', [
            'methods' => 'POST', 'callback' => [self::class, 'send_old_email_otp'], 'permission_callback' => $logged_in,
        ]);
        register_rest_route(self::NAMESPACE, '/user/email/verify-old-otp', [
            'methods' => 'POST', 'callback' => [self::class, 'verify_old_email_otp'], 'permission_callback' => $logged_in,
            'args' => ['otp' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field']],
        ]);
        register_rest_route(self::NAMESPACE, '/user/email/send-new-otp', [
            'methods' => 'POST', 'callback' => [self::class, 'send_new_email_otp'], 'permission_callback' => $logged_in,
            'args' => ['newEmail' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email']],
        ]);
        register_rest_route(self::NAMESPACE, '/user/email/confirm', [
            'methods' => 'POST', 'callback' => [self::class, 'confirm_email_change'], 'permission_callback' => $logged_in,
            'args' => [
                'newEmail' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_email'],
                'otp'      => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Stats
        register_rest_route(self::NAMESPACE, '/user/stats', [
            'methods' => 'GET', 'callback' => [self::class, 'get_stats'], 'permission_callback' => $logged_in,
        ]);
    }

    /* ── GET profile ───────────────────────────────────────── */

    public static function get_profile(\WP_REST_Request $request) {
        $user = wp_get_current_user();

        $google_picture = get_user_meta($user->ID, '_bl_google_picture', true);
        $custom_avatar  = get_user_meta($user->ID, '_bl_custom_avatar', true);
        $phone          = get_user_meta($user->ID, '_bl_phone', true);

        // Priority: custom upload → google picture → gravatar
        $avatar = $custom_avatar ?: ($google_picture ?: get_avatar_url($user->ID, ['size' => 200]));

        return rest_ensure_response([
            'id'             => $user->ID,
            'username'       => $user->user_login,
            'displayName'    => $user->display_name,
            'firstName'      => $user->first_name,
            'lastName'       => $user->last_name,
            'email'          => $user->user_email,
            'phone'          => $phone ?: '',
            'avatar'         => $avatar,
            'bio'            => $user->description,
            'dateRegistered' => $user->user_registered,
        ]);
    }

    /* ── POST update profile ───────────────────────────────── */

    public static function update_profile(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if ($request->has_param('firstName')) {
            update_user_meta($user_id, 'first_name', $request->get_param('firstName'));
        }
        if ($request->has_param('lastName')) {
            update_user_meta($user_id, 'last_name', $request->get_param('lastName'));
        }
        if ($request->has_param('bio')) {
            update_user_meta($user_id, 'description', $request->get_param('bio'));
        }
        if ($request->has_param('phone')) {
            $phone = preg_replace('/[^0-9+\-\s()]/', '', $request->get_param('phone'));
            update_user_meta($user_id, '_bl_phone', $phone);
        }
        if ($request->has_param('displayName')) {
            $dn = $request->get_param('displayName');
            if (!empty($dn)) {
                wp_update_user(['ID' => $user_id, 'display_name' => $dn]);
            }
        }

        return self::get_profile($request);
    }

    /* ── Avatar upload ─────────────────────────────────────── */

    public static function upload_avatar(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $files   = $request->get_file_params();

        if (empty($files['avatar'])) {
            return new \WP_Error('no_file', 'No file uploaded.', ['status' => 400]);
        }

        $file = $files['avatar'];

        // Validate type
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            return new \WP_Error('invalid_type', 'Only JPEG, PNG, WebP, and GIF images are allowed.', ['status' => 400]);
        }

        // Max 2 MB
        if ($file['size'] > 2 * 1024 * 1024) {
            return new \WP_Error('too_large', 'Image must be under 2 MB.', ['status' => 400]);
        }

        // Delete old custom avatar first
        self::remove_custom_avatar($user_id);

        // Upload via WP media
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new \WP_Error('upload_failed', $upload['error'], ['status' => 500]);
        }

        $attachment_id = wp_insert_attachment([
            'post_title'     => 'bl-avatar-' . $user_id,
            'post_mime_type' => $upload['type'],
            'post_status'    => 'private',
        ], $upload['file']);

        if (is_wp_error($attachment_id)) {
            return new \WP_Error('attachment_failed', 'Failed to save avatar.', ['status' => 500]);
        }

        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));

        update_user_meta($user_id, '_bl_custom_avatar', $upload['url']);
        update_user_meta($user_id, '_bl_custom_avatar_id', $attachment_id);

        return self::get_profile($request);
    }

    /* ── Avatar delete ─────────────────────────────────────── */

    public static function delete_avatar(\WP_REST_Request $request) {
        self::remove_custom_avatar(get_current_user_id());
        return self::get_profile($request);
    }

    private static function remove_custom_avatar(int $user_id): void {
        $old_id = get_user_meta($user_id, '_bl_custom_avatar_id', true);
        if ($old_id) {
            wp_delete_attachment((int) $old_id, true);
        }
        delete_user_meta($user_id, '_bl_custom_avatar');
        delete_user_meta($user_id, '_bl_custom_avatar_id');
    }

    /* ── Email change — Step 1: send OTP to current email ── */

    public static function send_old_email_otp(\WP_REST_Request $request) {
        $user  = wp_get_current_user();
        $result = OTPManager::send_otp($user->user_email, 'verification');
        return rest_ensure_response($result);
    }

    /* ── Email change — Step 2: verify current-email OTP ─── */

    public static function verify_old_email_otp(\WP_REST_Request $request) {
        $user = wp_get_current_user();
        $otp  = $request->get_param('otp');

        $result = OTPManager::verify_otp($user->user_email, $otp, true);

        if ($result['success']) {
            // Store a short-lived token proving old-email was verified
            set_transient('bl_email_change_' . $user->ID, true, 15 * MINUTE_IN_SECONDS);
        }

        return rest_ensure_response($result);
    }

    /* ── Email change — Step 3a: send OTP to new email ───── */

    public static function send_new_email_otp(\WP_REST_Request $request) {
        $user     = wp_get_current_user();
        $newEmail = $request->get_param('newEmail');

        if (!is_email($newEmail)) {
            return new \WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        if (strtolower($newEmail) === strtolower($user->user_email)) {
            return new \WP_Error('same_email', 'New email is the same as your current email.', ['status' => 400]);
        }

        // Check old-email was verified
        if (!get_transient('bl_email_change_' . $user->ID)) {
            return new \WP_Error('old_not_verified', 'Please verify your current email first.', ['status' => 403]);
        }

        // Check the new email isn't already in use
        if (email_exists($newEmail)) {
            return new \WP_Error('email_taken', 'That email address is already in use.', ['status' => 409]);
        }

        $result = OTPManager::send_otp($newEmail, 'verification');
        return rest_ensure_response($result);
    }

    /* ── Email change — Step 3b: confirm new email OTP ───── */

    public static function confirm_email_change(\WP_REST_Request $request) {
        $user     = wp_get_current_user();
        $newEmail = $request->get_param('newEmail');
        $otp      = $request->get_param('otp');

        if (!get_transient('bl_email_change_' . $user->ID)) {
            return new \WP_Error('old_not_verified', 'Please verify your current email first.', ['status' => 403]);
        }

        if (!is_email($newEmail)) {
            return new \WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }

        if (email_exists($newEmail)) {
            return new \WP_Error('email_taken', 'That email address is already in use.', ['status' => 409]);
        }

        $result = OTPManager::verify_otp($newEmail, $otp, true);
        if (!$result['success']) {
            return rest_ensure_response($result);
        }

        // Apply the change
        wp_update_user([
            'ID'         => $user->ID,
            'user_email' => $newEmail,
        ]);

        // Clean up
        delete_transient('bl_email_change_' . $user->ID);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Email address updated successfully.',
        ]);
    }

    /* ── Stats ─────────────────────────────────────────────── */

    public static function get_stats(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        global $wpdb;

        $participants_table = $wpdb->prefix . 'bl_tournament_participants';
        $tournaments_entered = 0;
        $tournaments_won     = 0;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$participants_table}'") === $participants_table) {
            $tournaments_entered = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE user_id = %d", $user_id
            ));
            $tournaments_won = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$participants_table} WHERE user_id = %d AND rank = 1", $user_id
            ));
        }

        $total_winnings = '0';
        $wallet_table   = $wpdb->prefix . 'bl_wallets';
        $tx_table       = $wpdb->prefix . 'bl_wallet_transactions';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wallet_table}'") === $wallet_table) {
            $wallet = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wallet_table} WHERE user_id = %d", $user_id
            ));
            if ($wallet) {
                $total_winnings = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(amount), 0) FROM {$tx_table} WHERE wallet_id = %d AND type = 'credit' AND description LIKE %s",
                    $wallet->id, '%prize%'
                )) ?: '0';
            }
        }

        return rest_ensure_response([
            'tournaments_entered' => $tournaments_entered,
            'tournaments_won'     => $tournaments_won,
            'total_winnings'      => $total_winnings,
            'current_rank'        => null,
        ]);
    }

    /* ── Helper: get phone for a user (used by other controllers) */

    public static function get_phone(int $user_id): string {
        return (string) get_user_meta($user_id, '_bl_phone', true);
    }
}
