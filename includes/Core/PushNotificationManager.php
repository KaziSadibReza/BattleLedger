<?php
namespace BattleLedger\Core;

/**
 * PushNotificationManager — Web Push without external dependencies.
 *
 * Uses VAPID (Voluntary Application Server Identification) with PHP sodium.
 * Stores subscriptions in wp_usermeta (key: bl_push_subscriptions).
 * VAPID keys stored in wp_options (bl_vapid_public_key, bl_vapid_private_key).
 */
class PushNotificationManager {

    const META_KEY      = 'bl_push_subscriptions';
    const OPTION_PUBLIC = 'bl_vapid_public_key';
    const OPTION_PRIVATE = 'bl_vapid_private_key';

    /* ──────────────────────────────────────────
       VAPID key management
       ────────────────────────────────────────── */

    /**
     * Get or generate VAPID key pair.
     * Returns ['public' => base64url, 'private' => base64url]
     */
    public static function get_vapid_keys(): array {
        $public  = get_option(self::OPTION_PUBLIC, '');
        $private = get_option(self::OPTION_PRIVATE, '');

        if (self::is_valid_vapid_key_pair($public, $private)) {
            return ['public' => $public, 'private' => $private];
        }

        if (!empty($public) || !empty($private)) {
            // Remove malformed values so we can re-attempt generation cleanly.
            delete_option(self::OPTION_PUBLIC);
            delete_option(self::OPTION_PRIVATE);
        }

        try {
            return self::generate_vapid_keys();
        } catch (\Throwable $e) {
            self::log_error('VAPID key generation failed: ' . $e->getMessage());
            return ['public' => '', 'private' => ''];
        }
    }

    /**
     * Generate a new ECDSA P-256 key pair for VAPID, stored as base64url.
     */
    private static function generate_vapid_keys(): array {
        if (!function_exists('openssl_pkey_new') || !defined('OPENSSL_KEYTYPE_EC')) {
            throw new \RuntimeException('OpenSSL EC key generation is unavailable.');
        }

        // Generate ephemeral EC key pair using OpenSSL
        $args = [
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];

        $key = openssl_pkey_new($args);
        if (!$key) {
            $candidate_paths = [];
            $env_conf = getenv('OPENSSL_CONF');
            if (is_string($env_conf) && $env_conf !== '') {
                $candidate_paths[] = $env_conf;
            }

            if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
                $php_bin_dir = dirname(PHP_BINARY);
                $candidate_paths[] = $php_bin_dir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
                $candidate_paths[] = dirname($php_bin_dir) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
            }

            foreach ($candidate_paths as $path) {
                if (!is_string($path) || $path === '' || !file_exists($path)) {
                    continue;
                }

                $key = openssl_pkey_new($args + ['config' => $path]);
                if ($key) {
                    break;
                }
            }
        }

        if (!$key) {
            $openssl_errors = self::consume_openssl_errors();
            throw new \RuntimeException('Unable to generate VAPID key pair. ' . $openssl_errors);
        }

        $details = openssl_pkey_get_details($key);
        if (!is_array($details)) {
            $openssl_errors = self::consume_openssl_errors();
            throw new \RuntimeException('OpenSSL did not return key details. ' . $openssl_errors);
        }

        if (
            !isset($details['ec']) ||
            !is_array($details['ec']) ||
            !isset($details['ec']['x'], $details['ec']['y'], $details['ec']['d'])
        ) {
            throw new \RuntimeException('OpenSSL returned an unexpected EC key detail structure.');
        }

        // The public key is the uncompressed point (65 bytes: 0x04 || x || y)
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $public_raw = "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);

        // The private key scalar 'd' (32 bytes)
        $private_raw = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);

        $public_b64  = self::base64url_encode($public_raw);
        $private_b64 = self::base64url_encode($private_raw);

        if (!self::is_valid_vapid_key_pair($public_b64, $private_b64)) {
            throw new \RuntimeException('Generated VAPID key pair failed validation.');
        }

        update_option(self::OPTION_PUBLIC, $public_b64, false);
        update_option(self::OPTION_PRIVATE, $private_b64, false);

        return ['public' => $public_b64, 'private' => $private_b64];
    }

    /**
     * Get the public key for the browser (application server key).
     */
    public static function get_public_key(): string {
        $keys = self::get_vapid_keys();
        return is_string($keys['public'] ?? null) ? $keys['public'] : '';
    }

    /* ──────────────────────────────────────────
       Subscription management (wp_usermeta)
       ────────────────────────────────────────── */

    /**
     * Save a push subscription for a user.
     * Each user can have multiple subscriptions (different browsers/devices).
     *
     * @param int   $user_id
     * @param array $subscription ['endpoint' => string, 'keys' => ['p256dh' => string, 'auth' => string]]
     */
    public static function save_subscription(int $user_id, array $subscription): bool {
        $normalized = self::normalize_subscription($subscription);
        if ($normalized === null) {
            return false;
        }

        $subs = self::get_subscriptions($user_id);
        $clean_subs = [];
        $replaced = false;

        foreach ($subs as $existing) {
            if (!is_array($existing)) {
                continue;
            }

            $existing_normalized = self::normalize_subscription($existing);
            if ($existing_normalized === null) {
                continue;
            }

            if (($existing_normalized['endpoint'] ?? '') === $normalized['endpoint']) {
                $clean_subs[] = $normalized;
                $replaced = true;
                continue;
            }

            $clean_subs[] = $existing_normalized;
        }

        if (!$replaced) {
            $clean_subs[] = $normalized;
        }

        update_user_meta($user_id, self::META_KEY, array_values($clean_subs));
        return true;
    }

    /**
     * Remove a subscription by endpoint.
     */
    public static function remove_subscription(int $user_id, string $endpoint): void {
        $subs = self::get_subscriptions($user_id);
        $subs = array_filter($subs, fn($s) => ($s['endpoint'] ?? '') !== $endpoint);
        update_user_meta($user_id, self::META_KEY, array_values($subs));
    }

    /**
     * Get all subscriptions for a user.
     */
    public static function get_subscriptions(int $user_id): array {
        $subs = get_user_meta($user_id, self::META_KEY, true);
        return is_array($subs) ? $subs : [];
    }

    /* ──────────────────────────────────────────
       Send push notification
       ────────────────────────────────────────── */

    /**
     * Send a push notification to a specific user (all their devices).
     *
     * @param int    $user_id
     * @param string $title
     * @param string $body
     * @param array  $data  Extra data for the client (icon, url, etc.)
     */
    public static function send_to_user(int $user_id, string $title, string $body, array $data = []): void {
        $subs = self::get_subscriptions($user_id);
        if (empty($subs)) {
            return;
        }

        $valid_subs = [];
        foreach ($subs as $sub) {
            if (!is_array($sub)) {
                continue;
            }

            $normalized = self::normalize_subscription($sub);
            if ($normalized !== null) {
                $valid_subs[] = $normalized;
            }
        }

        if (empty($valid_subs)) {
            update_user_meta($user_id, self::META_KEY, []);
            return;
        }

        if (count($valid_subs) !== count($subs)) {
            update_user_meta($user_id, self::META_KEY, array_values($valid_subs));
        }

        $payload = wp_json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => $data['icon'] ?? '',
            'url'   => $data['url'] ?? '',
            'data'  => $data,
        ]);

        if (!is_string($payload) || $payload === '') {
            return;
        }

        $keys = self::get_vapid_keys();
        if (!self::is_valid_vapid_key_pair($keys['public'] ?? '', $keys['private'] ?? '')) {
            self::log_error('Push send aborted: VAPID keys unavailable or invalid.');
            return;
        }

        foreach ($valid_subs as $sub) {
            try {
                $result = self::send_push($sub, $payload, $keys);
            } catch (\Throwable $e) {
                self::log_error('Push send failed for user ' . $user_id . ': ' . $e->getMessage());
                continue;
            }

            // Remove expired/invalid subscriptions so future sends can recover.
            if (in_array($result, [400, 401, 403, 404, 410], true)) {
                self::remove_subscription($user_id, $sub['endpoint']);
            }
        }
    }

    /**
     * Low-level push send using wp_remote_post.
     * Returns HTTP status code.
     */
    private static function send_push(array $subscription, string $payload, array $vapid_keys): int {
        $endpoint = $subscription['endpoint'];

        // Build VAPID Authorization header
        $parsed  = wp_parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];
        $vapid_headers = self::create_vapid_headers($audience, $vapid_keys);

        if (!$vapid_headers) {
            return 0;
        }

        // Encrypt the payload using the subscription keys
        $encrypted = self::encrypt_payload(
            $payload,
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );

        if (!$encrypted) {
            return 0;
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'vapid t=' . $vapid_headers['token'] . ', k=' . $vapid_keys['public'],
                'Content-Type'  => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL'           => '86400',
                'Urgency'       => 'normal',
            ],
            'body' => $encrypted,
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        return (int) wp_remote_retrieve_response_code($response);
    }

    /* ──────────────────────────────────────────
       VAPID JWT token
       ────────────────────────────────────────── */

    private static function create_vapid_headers(string $audience, array $keys): ?array {
        $header = self::base64url_encode(wp_json_encode([
            'typ' => 'JWT',
            'alg' => 'ES256',
        ]));

        $payload = self::base64url_encode(wp_json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => 'mailto:' . get_option('admin_email', 'admin@example.com'),
        ]));

        $signing_input = $header . '.' . $payload;

        // Sign with ECDSA P-256 (ES256)
        $private_raw = self::base64url_decode($keys['private']);
        $public_raw  = self::base64url_decode($keys['public']);

        // Build PEM from raw keys
        $pem = self::raw_to_pem($private_raw, $public_raw);
        if (!$pem) {
            return null;
        }

        $pkey = openssl_pkey_get_private($pem);
        if (!$pkey) {
            return null;
        }

        $signature = '';
        if (!openssl_sign($signing_input, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        // Convert DER signature to raw r||s (64 bytes)
        $raw_sig = self::der_to_raw($signature);
        if (!$raw_sig) {
            return null;
        }

        $token = $signing_input . '.' . self::base64url_encode($raw_sig);
        return ['token' => $token];
    }

    /* ──────────────────────────────────────────
       Payload encryption (aes128gcm / RFC 8291)
       ────────────────────────────────────────── */

    private static function encrypt_payload(string $payload, string $client_public_b64, string $auth_b64): ?string {
        $client_public = self::base64url_decode($client_public_b64);
        $auth_secret   = self::base64url_decode($auth_b64);

        if (strlen($client_public) !== 65 || strlen($auth_secret) !== 16) {
            return null;
        }

        if (!function_exists('openssl_pkey_new') || !defined('OPENSSL_KEYTYPE_EC')) {
            self::log_error('Payload encryption failed: OpenSSL EC key generation is unavailable.');
            return null;
        }

        // Generate ephemeral EC key pair
        $local_key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (!$local_key) {
            $candidate_paths = [];
            $env_conf = getenv('OPENSSL_CONF');
            if (is_string($env_conf) && $env_conf !== '') {
                $candidate_paths[] = $env_conf;
            }

            if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
                $php_bin_dir = dirname(PHP_BINARY);
                $candidate_paths[] = $php_bin_dir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
                $candidate_paths[] = dirname($php_bin_dir) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
            }

            foreach ($candidate_paths as $path) {
                if (!is_string($path) || $path === '' || !file_exists($path)) {
                    continue;
                }

                $local_key = openssl_pkey_new([
                    'curve_name'       => 'prime256v1',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                    'config'           => $path,
                ]);

                if ($local_key) {
                    break;
                }
            }
        }

        if (!$local_key) {
            self::log_error('Payload encryption failed: unable to generate ephemeral EC key pair. ' . self::consume_openssl_errors());
            return null;
        }

        $local_details = openssl_pkey_get_details($local_key);
        if (!is_array($local_details)) {
            self::log_error('Payload encryption failed: OpenSSL did not return EC key details. ' . self::consume_openssl_errors());
            return null;
        }

        if (
            !isset($local_details['ec']) ||
            !is_array($local_details['ec']) ||
            !isset($local_details['ec']['x'], $local_details['ec']['y'])
        ) {
            self::log_error('Payload encryption failed: invalid OpenSSL EC key details structure.');
            return null;
        }

        $local_x = str_pad($local_details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $local_y = str_pad($local_details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        $local_public = "\x04" . $local_x . $local_y;

        // ECDH shared secret
        $shared_secret = self::ecdh($local_key, $client_public);
        if (!$shared_secret) {
            return null;
        }

        // Key derivation (RFC 8291 §3.4)
        // IKM = ECDH(local_private, client_public)
        // PRK = HKDF-Extract(auth_secret, IKM)
        $ikm_info = "WebPush: info\x00" . $client_public . $local_public;
        $prk = self::hkdf($auth_secret, $shared_secret, $ikm_info, 32);

        // Salt (random 16 bytes)
        $salt = random_bytes(16);

        // Content encryption key
        $cek_info = "Content-Encoding: aes128gcm\x00";
        $cek = self::hkdf($salt, $prk, $cek_info, 16);

        // Nonce
        $nonce_info = "Content-Encoding: nonce\x00";
        $nonce = self::hkdf($salt, $prk, $nonce_info, 12);

        // Pad payload (add delimiter byte 0x02 and optional padding)
        $padded = $payload . "\x02";

        // Encrypt with AES-128-GCM
        $tag = '';
        $encrypted = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($encrypted === false) {
            return null;
        }

        // Build aes128gcm content coding header
        // salt (16) + rs (4) + idlen (1) + keyid (65)
        $rs = pack('N', 4096);
        $header = $salt . $rs . chr(65) . $local_public;

        return $header . $encrypted . $tag;
    }

    /**
     * Compute ECDH shared secret.
     */
    private static function ecdh($local_private_key, string $remote_public_raw): ?string {
        // Extract x,y from uncompressed point
        $x = substr($remote_public_raw, 1, 32);
        $y = substr($remote_public_raw, 33, 32);

        // Build PEM for the peer's public key
        $peer_pem = self::public_raw_to_pem($x, $y);
        if (!$peer_pem) {
            return null;
        }

        $peer_key = openssl_pkey_get_public($peer_pem);
        if (!$peer_key) {
            return null;
        }

        $shared = openssl_pkey_derive($peer_key, $local_private_key, 32);
        if ($shared === false) {
            return null;
        }

        return $shared;
    }

    /* ──────────────────────────────────────────
       HKDF (RFC 5869)
       ────────────────────────────────────────── */

    private static function hkdf(string $salt, string $ikm, string $info, int $length): string {
        // Extract
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        // Expand
        $t = '';
        $output = '';
        $counter = 1;
        while (strlen($output) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
            $output .= $t;
            $counter++;
        }
        return substr($output, 0, $length);
    }

    /* ──────────────────────────────────────────
       Key format helpers
       ────────────────────────────────────────── */

    /**
     * Build PEM private key from raw d + uncompressed public key.
     */
    private static function raw_to_pem(string $private_d, string $public_raw): ?string {
        // SEC 1 / RFC 5915 EC private key DER
        $oid = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1

        $d_octet = "\x04\x20" . $private_d;

        $pub_bitstring = "\x03" . chr(strlen($public_raw) + 1) . "\x00" . $public_raw;
        $pub_explicit  = "\xa1" . self::asn1_length($pub_bitstring) . $pub_bitstring;

        $oid_explicit  = "\xa0" . self::asn1_length($oid) . $oid;

        $inner = "\x02\x01\x01" . $d_octet . $oid_explicit . $pub_explicit;
        $der   = "\x30" . self::asn1_length($inner) . $inner;

        return "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Build PEM public key from raw x, y coordinates.
     */
    private static function public_raw_to_pem(string $x, string $y): ?string {
        $point = "\x04" . $x . $y;

        $algo_oid   = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"; // EC
        $curve_oid  = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1
        $algo_seq   = "\x30" . self::asn1_length($algo_oid . $curve_oid) . $algo_oid . $curve_oid;
        $bitstring  = "\x03" . chr(strlen($point) + 1) . "\x00" . $point;

        $inner = $algo_seq . $bitstring;
        $der   = "\x30" . self::asn1_length($inner) . $inner;

        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Convert DER-encoded ECDSA signature to raw r||s (64 bytes).
     */
    private static function der_to_raw(string $der): ?string {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) return null;
        $offset++; // skip total length

        // r
        if (ord($der[$offset++]) !== 0x02) return null;
        $r_len = ord($der[$offset++]);
        $r = substr($der, $offset, $r_len);
        $offset += $r_len;

        // s
        if (ord($der[$offset++]) !== 0x02) return null;
        $s_len = ord($der[$offset++]);
        $s = substr($der, $offset, $s_len);

        // Trim leading zero bytes and pad to 32
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    private static function asn1_length(string $data): string {
        $len = strlen($data);
        if ($len < 128) {
            return chr($len);
        }
        $bytes = '';
        $temp = $len;
        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /* ──────────────────────────────────────────
       Base64url helpers
       ────────────────────────────────────────── */

    public static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64url_decode(string $data): string {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : '';
    }

    private static function is_valid_vapid_key_pair($public, $private): bool {
        if (!is_string($public) || !is_string($private) || $public === '' || $private === '') {
            return false;
        }

        $public_raw = self::base64url_decode($public);
        $private_raw = self::base64url_decode($private);

        return strlen($public_raw) === 65
            && $public_raw[0] === "\x04"
            && strlen($private_raw) === 32;
    }

    private static function consume_openssl_errors(): string {
        if (!function_exists('openssl_error_string')) {
            return '';
        }

        $errors = [];
        while (($err = openssl_error_string()) !== false) {
            $errors[] = $err;
        }

        return empty($errors) ? '' : implode(' | ', $errors);
    }

    private static function log_error(string $message): void {
        if (function_exists('error_log')) {
            error_log('[BattleLedger Push] ' . $message);
        }
    }

    private static function normalize_subscription(array $subscription): ?array {
        $endpoint = esc_url_raw((string) ($subscription['endpoint'] ?? ''));
        $keys = $subscription['keys'] ?? [];
        $p256dh = sanitize_text_field((string) ($keys['p256dh'] ?? ''));
        $auth = sanitize_text_field((string) ($keys['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return null;
        }

        $p256dh_raw = self::base64url_decode($p256dh);
        $auth_raw = self::base64url_decode($auth);

        if (
            strlen($p256dh_raw) !== 65 ||
            $p256dh_raw[0] !== "\x04" ||
            strlen($auth_raw) !== 16
        ) {
            return null;
        }

        return [
            'endpoint' => $endpoint,
            'keys'     => [
                'p256dh' => $p256dh,
                'auth'   => $auth,
            ],
            'created'  => gmdate('Y-m-d H:i:s'),
        ];
    }
}
