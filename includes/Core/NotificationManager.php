<?php
namespace BattleLedger\Core;

/**
 * NotificationManager — helper to create in-app notifications.
 *
 * ┌────────────────────────────────────────────────────────────────────────┐
 * │  NOTIFICATION TYPES COVERED (admin-level, user_id = 0)               │
 * │                                                                        │
 * │  TOURNAMENT EVENTS                                                     │
 * │  ─────────────────                                                     │
 * │  • tournament_created     — New tournament created                     │
 * │  • tournament_activated   — Tournament goes live / active              │
 * │  • tournament_cancelled   — Tournament cancelled by admin              │
 * │  • tournament_completed   — Winners assigned, tournament finished      │
 * │  • tournament_starting    — Tournament starts within threshold         │
 * │                                                                        │
 * │  PARTICIPANT / REGISTRATION EVENTS                                     │
 * │  ─────────────────────────────────                                     │
 * │  • participant_registered — New participant registered                  │
 * │  • participant_removed    — Participant removed from tournament         │
 * │  • tournament_full        — Tournament reached max participants         │
 * │                                                                        │
 * │  MATCH EVENTS                                                          │
 * │  ────────────                                                          │
 * │  • match_completed        — Match results recorded                     │
 * │  • match_scheduled        — New match scheduled                        │
 * │                                                                        │
 * │  WALLET / PAYMENT EVENTS                                               │
 * │  ───────────────────────                                               │
 * │  • wallet_deposit         — User deposited funds                       │
 * │  • withdrawal_requested   — User requested withdrawal                  │
 * │  • withdrawal_approved    — Withdrawal approved                        │
 * │  • withdrawal_rejected    — Withdrawal rejected                        │
 * │  • payment_pending        — Pending payments need attention             │
 * │                                                                        │
 * │  SYSTEM EVENTS                                                         │
 * │  ─────────────                                                         │
 * │  • system_info            — General system notice                      │
 * │  • system_warning         — Something needs admin attention             │
 * │  • system_error           — Critical error                             │
 * └────────────────────────────────────────────────────────────────────────┘
 *
 * Usage:
 *   NotificationManager::create('tournament', 'Tournament Created', 'PUBG Pro League was created.', [
 *       'icon' => 'trophy',
 *       'link' => '#tournaments',
 *       'metadata' => ['tournament_id' => 42],
 *   ]);
 *
 *   NotificationManager::tournament_created($tournament);
 */
class NotificationManager {

    /* ──────────────────────────────────────────
       Core insert
       ────────────────────────────────────────── */

    /**
     * Insert a notification row.
     *
     * @param string $type     Notification type (tournament|participant|alert|success|system)
     * @param string $title    Short title
     * @param string $message  Descriptive message
     * @param array  $extra    Optional: icon, link, user_id, metadata
     * @return int|false       Inserted row ID or false on failure
     */
    public static function create(string $type, string $title, string $message, array $extra = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'bl_notifications';

        $data = [
            'user_id'    => (int) ($extra['user_id'] ?? 0),  // 0 = admin-wide
            'type'       => sanitize_text_field($type),
            'title'      => sanitize_text_field($title),
            'message'    => sanitize_textarea_field($message),
            'icon'       => sanitize_text_field($extra['icon'] ?? ''),
            'link'       => esc_url_raw($extra['link'] ?? ''),
            'is_read'    => 0,
            'metadata'   => isset($extra['metadata']) ? wp_json_encode($extra['metadata']) : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $inserted = $wpdb->insert($table, $data);

        // If this is a user-targeted notification, also send a push
        $user_id = (int) ($extra['user_id'] ?? 0);
        if ($inserted && $user_id > 0) {
            PushNotificationManager::send_to_user($user_id, $title, $message, [
                'icon' => $extra['icon'] ?? '',
                'url'  => $extra['link'] ?? '',
            ]);
        }

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /* ══════════════════════════════════════════
       TOURNAMENT helpers
       ══════════════════════════════════════════ */

    /** Tournament created */
    public static function tournament_created(array $tournament): void {
        self::create('tournament', 'Tournament Created', sprintf(
            '"%s" has been created and is in %s status.',
            $tournament['name'] ?? 'Unnamed',
            $tournament['status'] ?? 'draft'
        ), [
            'icon' => 'trophy',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Tournament activated (went live) */
    public static function tournament_activated(array $tournament): void {
        self::create('tournament', 'Tournament Activated', sprintf(
            '"%s" is now live and accepting registrations.',
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'trophy',
            'link' => '#live',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Tournament cancelled */
    public static function tournament_cancelled(array $tournament): void {
        self::create('alert', 'Tournament Cancelled', sprintf(
            '"%s" has been cancelled.',
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'alert',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Tournament completed — winners assigned */
    public static function tournament_completed(array $tournament): void {
        self::create('success', 'Tournament Completed', sprintf(
            '"%s" has been completed. Winners have been assigned.',
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'success',
            'link' => '#finished',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Tournament starting soon (can be fired by cron or manually) */
    public static function tournament_starting(array $tournament, string $time_label = 'soon'): void {
        self::create('tournament', 'Tournament Starting Soon', sprintf(
            '"%s" starts %s.',
            $tournament['name'] ?? 'Unnamed',
            $time_label
        ), [
            'icon' => 'trophy',
            'link' => '#live',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /* ══════════════════════════════════════════
       PARTICIPANT / REGISTRATION helpers
       ══════════════════════════════════════════ */

    /** New participant registered */
    public static function participant_registered(string $display_name, array $tournament): void {
        self::create('participant', 'New Registration', sprintf(
            '%s registered for "%s".',
            $display_name,
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'users',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Participant removed */
    public static function participant_removed(string $display_name, array $tournament): void {
        self::create('participant', 'Participant Removed', sprintf(
            '%s was removed from "%s".',
            $display_name,
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'users',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Tournament full — max participants reached */
    public static function tournament_full(array $tournament): void {
        self::create('success', 'Tournament Full', sprintf(
            '"%s" has reached its maximum number of participants.',
            $tournament['name'] ?? 'Unnamed'
        ), [
            'icon' => 'success',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /* ══════════════════════════════════════════
       MATCH helpers
       ══════════════════════════════════════════ */

    /** Match completed */
    public static function match_completed(array $tournament, int $round, int $match_number): void {
        self::create('success', 'Match Completed', sprintf(
            '%s — Round %d, Match %d results have been recorded.',
            $tournament['name'] ?? 'Tournament',
            $round,
            $match_number
        ), [
            'icon' => 'success',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null, 'round' => $round, 'match' => $match_number],
        ]);
    }

    /** Match scheduled */
    public static function match_scheduled(array $tournament, int $round, int $match_number): void {
        self::create('tournament', 'Match Scheduled', sprintf(
            '%s — Round %d, Match %d has been scheduled.',
            $tournament['name'] ?? 'Tournament',
            $round,
            $match_number
        ), [
            'icon' => 'trophy',
            'link' => '#tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null, 'round' => $round, 'match' => $match_number],
        ]);
    }

    /* ══════════════════════════════════════════
       WALLET / PAYMENT helpers
       ══════════════════════════════════════════ */

    /** Wallet deposit */
    public static function wallet_deposit(string $display_name, float $amount, string $currency = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        self::create('success', 'Wallet Deposit', sprintf(
            '%s deposited %s %s into their wallet.',
            $display_name,
            number_format($amount, 2),
            $currency
        ), [
            'icon' => 'success',
            'link' => '#wallets',
        ]);
    }

    /** Withdrawal requested */
    public static function withdrawal_requested(string $display_name, float $amount, string $currency = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        self::create('alert', 'Withdrawal Requested', sprintf(
            '%s requested a withdrawal of %s %s.',
            $display_name,
            number_format($amount, 2),
            $currency
        ), [
            'icon' => 'alert',
            'link' => '#wallets',
        ]);
    }

    /** Withdrawal approved — notify the user */
    public static function withdrawal_approved(int $user_id, float $amount, string $currency = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        self::create('success', 'Withdrawal Approved', sprintf(
            'Your withdrawal of %s %s has been approved.',
            number_format($amount, 2),
            $currency
        ), [
            'user_id'  => $user_id,
            'icon'     => 'success',
            'link'     => '/dashboard/wallet',
            'metadata' => ['amount' => $amount, 'currency' => $currency],
        ]);
    }

    /** Withdrawal rejected — notify the user */
    public static function withdrawal_rejected(int $user_id, float $amount, string $currency = '', string $reason = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        $msg = sprintf(
            'Your withdrawal of %s %s was rejected.',
            number_format($amount, 2),
            $currency
        );
        if ($reason) {
            $msg .= ' Reason: ' . $reason;
        }
        self::create('alert', 'Withdrawal Rejected', $msg, [
            'user_id'  => $user_id,
            'icon'     => 'alert',
            'link'     => '/dashboard/wallet',
            'metadata' => ['amount' => $amount, 'currency' => $currency],
        ]);
    }

    /** Payment pending (generic alert) */
    public static function payment_pending(int $count): void {
        self::create('alert', 'Payment Pending', sprintf(
            '%d participant(s) have pending prize money claims.',
            $count
        ), [
            'icon' => 'alert',
            'link' => '#wallets',
        ]);
    }

    /* ══════════════════════════════════════════
       SYSTEM helpers
       ══════════════════════════════════════════ */

    /** Generic system info */
    public static function system_info(string $title, string $message): void {
        self::create('system', $title, $message, ['icon' => 'info']);
    }

    /** System warning */
    public static function system_warning(string $title, string $message): void {
        self::create('alert', $title, $message, ['icon' => 'alert']);
    }

    /** System error */
    public static function system_error(string $title, string $message): void {
        self::create('alert', $title, $message, ['icon' => 'alert']);
    }

    /* ══════════════════════════════════════════
       USER-FACING helpers (user_id = specific user)
       ══════════════════════════════════════════ */

    /** Notify user: deposit confirmed */
    public static function user_deposit_confirmed(int $user_id, float $amount, string $currency = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        self::create('success', 'Deposit Confirmed', sprintf(
            'Your deposit of %s %s has been credited to your wallet.',
            number_format($amount, 2),
            $currency
        ), [
            'user_id'  => $user_id,
            'icon'     => 'success',
            'link'     => '/dashboard/wallet',
            'metadata' => ['amount' => $amount, 'currency' => $currency],
        ]);
    }

    /** Notify user: tournament joined */
    public static function user_tournament_joined(int $user_id, array $tournament): void {
        self::create('success', 'Tournament Joined', sprintf(
            'You have successfully joined "%s".',
            $tournament['name'] ?? 'a tournament'
        ), [
            'user_id'  => $user_id,
            'icon'     => 'trophy',
            'link'     => '/dashboard/tournaments',
            'metadata' => ['tournament_id' => $tournament['id'] ?? null],
        ]);
    }

    /** Notify user: tournament completed (results available) */
    public static function user_tournament_completed(int $user_id, array $tournament, string $placement = ''): void {
        $msg = sprintf('Tournament "%s" has ended.', $tournament['name'] ?? 'A tournament');
        if ($placement) {
            $msg .= ' You placed: ' . $placement . '.';
        }
        self::create('tournament', 'Tournament Completed', $msg, [
            'user_id'  => $user_id,
            'icon'     => 'trophy',
            'link'     => '/dashboard/tournaments',
            'metadata' => [
                'tournament_id' => $tournament['id'] ?? null,
                'placement'     => $placement,
            ],
        ]);
    }

    /** Notify user: withdrawal submitted confirmation */
    public static function user_withdrawal_submitted(int $user_id, float $amount, string $currency = ''): void {
        if ($currency === '') $currency = \BattleLedger\Wallet\WalletManager::get_currency();
        self::create('system', 'Withdrawal Submitted', sprintf(
            'Your withdrawal request of %s %s has been submitted and is awaiting approval.',
            number_format($amount, 2),
            $currency
        ), [
            'user_id'  => $user_id,
            'icon'     => 'info',
            'link'     => '/dashboard/wallet',
            'metadata' => ['amount' => $amount, 'currency' => $currency],
        ]);
    }
}
