<?php
/**
 * Template: Live Tournaments (Frontend)
 * 
 * Shows all active/live tournaments with game info, participants, and join button.
 * 
 * Shortcode: [battleledger_live_tournaments]
 * 
 * @var array $atts Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

use BattleLedger\Database\QueryBuilder;

$limit     = intval($atts['limit'] ?? 20);
$game_type = sanitize_text_field($atts['game_type'] ?? '');
$columns   = intval($atts['columns'] ?? 3);

// Fetch active tournaments
global $wpdb;
$table        = $wpdb->prefix . 'bl_tournaments';
$participants = $wpdb->prefix . 'bl_tournament_participants';
$rules_table  = $wpdb->prefix . 'bl_game_rules';

$where = ["t.status = 'active'"];
$args  = [];

if ($game_type) {
    $where[] = 't.game_type = %s';
    $args[]  = $game_type;
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
        ORDER BY t.start_date ASC
        LIMIT %d";
$args[] = $limit;

if (count($args) > 1) {
    $tournaments = $wpdb->get_results($wpdb->prepare($sql, ...$args));
} else {
    $tournaments = $wpdb->get_results($wpdb->prepare($sql, $limit));
}

// Fetch game rules for icons/names
$game_rules = $wpdb->get_results("SELECT slug, game_name, game_icon, game_image FROM $rules_table WHERE is_active = 1");
$games_map  = [];
foreach ($game_rules as $rule) {
    $games_map[$rule->slug] = $rule;
}

// Check current user's joined tournaments
$user_joined = [];
if (is_user_logged_in()) {
    $user_id = get_current_user_id();
    $joined  = $wpdb->get_col($wpdb->prepare(
        "SELECT tournament_id FROM $participants WHERE user_id = %d",
        $user_id
    ));
    $user_joined = array_flip($joined);
}

// Login page URL
$login_url = '';
if (!is_user_logged_in()) {
    $login_url = \BattleLedger\Core\PageInstaller::get_page_url('login');
}
?>

<div class="bl-live-tournaments-frontend" style="--bl-live-cols: <?php echo esc_attr($columns); ?>">

    <?php if (empty($tournaments)): ?>
        <div class="bl-live-ft-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                <path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
            </svg>
            <h3><?php esc_html_e('No Live Tournaments', 'battle-ledger'); ?></h3>
            <p><?php esc_html_e('There are no active tournaments right now. Check back soon!', 'battle-ledger'); ?></p>
        </div>

    <?php else: ?>
        <div class="bl-live-ft-grid">
            <?php foreach ($tournaments as $t):
                $settings    = json_decode($t->settings ?? '{}', true) ?: [];
                $game_slug   = $t->game_type;
                $game        = $games_map[$game_slug] ?? null;
                $game_name   = $game ? $game->game_name : ucfirst($game_slug ?: 'Unknown');
                $game_icon   = $game ? $game->game_icon : '';
                $fill_pct    = $t->max_participants > 0 ? min(100, round(($t->participant_count / $t->max_participants) * 100)) : 0;
                $is_full     = $t->max_participants > 0 && $t->participant_count >= $t->max_participants;
                $has_joined  = isset($user_joined[$t->id]);

                // Time info
                $start_ts    = $t->start_date ? strtotime($t->start_date) : null;
                $end_ts      = $t->end_date ? strtotime($t->end_date) : null;
                $now         = time();
                $is_started  = $start_ts && $start_ts <= $now;
                $is_ended    = $end_ts && $end_ts <= $now;
            ?>
                <div class="bl-live-ft-card<?php echo $is_ended ? ' bl-live-ft-ended' : ''; ?>">
                    <!-- Header with game info -->
                    <div class="bl-live-ft-card-header">
                        <?php if ($game_icon): ?>
                            <img src="<?php echo esc_url($game_icon); ?>" alt="" class="bl-live-ft-game-icon" />
                        <?php else: ?>
                            <div class="bl-live-ft-game-icon bl-live-ft-game-placeholder">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 11h4M8 9v4M15 12h.01M18 10h.01"/><rect x="2" y="6" width="20" height="12" rx="2"/></svg>
                            </div>
                        <?php endif; ?>
                        <div class="bl-live-ft-card-title">
                            <h3><?php echo esc_html($t->name); ?></h3>
                            <span class="bl-live-ft-game-name"><?php echo esc_html($game_name); ?></span>
                        </div>
                        <span class="bl-live-ft-status-badge">
                            <span class="bl-live-ft-dot"></span>
                            <?php echo $is_ended ? esc_html__('Ended', 'battle-ledger') : esc_html__('Live', 'battle-ledger'); ?>
                        </span>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($t->description)): ?>
                        <p class="bl-live-ft-desc"><?php echo esc_html(wp_trim_words(strip_tags($t->description), 20)); ?></p>
                    <?php endif; ?>

                    <!-- Stats -->
                    <div class="bl-live-ft-stats">
                        <div class="bl-live-ft-stat">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>
                                <?php echo intval($t->participant_count); ?>
                                <?php if ($t->max_participants > 0): ?>
                                    / <?php echo intval($t->max_participants); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($t->entry_fee > 0): ?>
                            <div class="bl-live-ft-stat">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                <span><?php echo esc_html(number_format($t->entry_fee, 2)); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($t->prize_pool > 0): ?>
                            <div class="bl-live-ft-stat bl-live-ft-stat-prize">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                                <span>$<?php echo esc_html(number_format($t->prize_pool, 2)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Date info -->
                    <div class="bl-live-ft-dates">
                        <?php if ($start_ts): ?>
                            <div class="bl-live-ft-date">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span><?php echo esc_html(date_i18n('M j, Y g:i A', $start_ts)); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($end_ts): ?>
                            <div class="bl-live-ft-date">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span><?php echo esc_html(date_i18n('M j, Y g:i A', $end_ts)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Capacity bar -->
                    <?php if ($t->max_participants > 0): ?>
                        <div class="bl-live-ft-capacity">
                            <div class="bl-live-ft-capacity-track">
                                <div class="bl-live-ft-capacity-bar<?php echo $is_full ? ' full' : ''; ?>" style="width: <?php echo $fill_pct; ?>%"></div>
                            </div>
                            <span class="bl-live-ft-capacity-text"><?php echo $fill_pct; ?>% <?php esc_html_e('filled', 'battle-ledger'); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Action button -->
                    <div class="bl-live-ft-action">
                        <?php if ($has_joined): ?>
                            <span class="bl-live-ft-joined-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <?php esc_html_e('Joined', 'battle-ledger'); ?>
                            </span>
                        <?php elseif ($is_full): ?>
                            <span class="bl-live-ft-full-badge"><?php esc_html_e('Tournament Full', 'battle-ledger'); ?></span>
                        <?php elseif ($is_ended): ?>
                            <span class="bl-live-ft-ended-badge"><?php esc_html_e('Tournament Ended', 'battle-ledger'); ?></span>
                        <?php elseif (!is_user_logged_in()): ?>
                            <a href="<?php echo esc_url($login_url); ?>" class="bl-live-ft-btn bl-live-ft-btn-login">
                                <?php esc_html_e('Log in to Join', 'battle-ledger'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* ── Live Tournaments Frontend Styles ────────────────────── */
.bl-live-tournaments-frontend {
    --bl-live-primary: #6366f1;
    --bl-live-primary-light: rgba(99, 102, 241, 0.1);
    --bl-live-success: #10b981;
    --bl-live-warning: #f59e0b;
    --bl-live-danger: #ef4444;
    --bl-live-text: #1e293b;
    --bl-live-text-muted: #64748b;
    --bl-live-border: #e2e8f0;
    --bl-live-bg: #f8fafc;
    --bl-live-surface: #ffffff;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px 0;
}

.bl-live-ft-empty {
    text-align: center;
    padding: 60px 20px;
    color: var(--bl-live-text-muted);
}

.bl-live-ft-empty svg { margin-bottom: 16px; opacity: 0.5; }
.bl-live-ft-empty h3 { margin: 0 0 8px; font-size: 18px; color: var(--bl-live-text); }
.bl-live-ft-empty p { margin: 0; font-size: 14px; }

.bl-live-ft-grid {
    display: grid;
    grid-template-columns: repeat(var(--bl-live-cols, 3), 1fr);
    gap: 20px;
}

@media (max-width: 900px) { .bl-live-ft-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .bl-live-ft-grid { grid-template-columns: 1fr; } }

.bl-live-ft-card {
    background: var(--bl-live-surface);
    border: 1px solid var(--bl-live-border);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    transition: box-shadow 0.2s, transform 0.2s;
}

.bl-live-ft-card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.bl-live-ft-card.bl-live-ft-ended { opacity: 0.7; }

/* Header */
.bl-live-ft-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bl-live-ft-game-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    object-fit: cover;
    flex-shrink: 0;
}

.bl-live-ft-game-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bl-live-bg);
    color: var(--bl-live-text-muted);
}

.bl-live-ft-card-title {
    flex: 1;
    min-width: 0;
}

.bl-live-ft-card-title h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: var(--bl-live-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bl-live-ft-game-name {
    font-size: 12px;
    color: var(--bl-live-text-muted);
}

.bl-live-ft-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--bl-live-success);
    background: rgba(16, 185, 129, 0.1);
    padding: 4px 10px;
    border-radius: 20px;
    flex-shrink: 0;
}

.bl-live-ft-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--bl-live-success);
    animation: bl-live-pulse 2s infinite;
}

.bl-live-ft-ended .bl-live-ft-status-badge {
    color: var(--bl-live-text-muted);
    background: rgba(100, 116, 139, 0.1);
}
.bl-live-ft-ended .bl-live-ft-dot {
    background: var(--bl-live-text-muted);
    animation: none;
}

@keyframes bl-live-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* Description */
.bl-live-ft-desc {
    margin: 0;
    font-size: 13px;
    color: var(--bl-live-text-muted);
    line-height: 1.5;
}

/* Stats */
.bl-live-ft-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.bl-live-ft-stat {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: var(--bl-live-text-muted);
}

.bl-live-ft-stat svg { color: var(--bl-live-text-muted); opacity: 0.7; }

.bl-live-ft-stat-prize {
    color: var(--bl-live-warning);
    font-weight: 600;
}
.bl-live-ft-stat-prize svg { color: var(--bl-live-warning); opacity: 1; }

/* Dates */
.bl-live-ft-dates {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bl-live-ft-date {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--bl-live-text-muted);
}

.bl-live-ft-date svg { opacity: 0.5; }

/* Capacity bar */
.bl-live-ft-capacity {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bl-live-ft-capacity-track {
    flex: 1;
    height: 6px;
    background: var(--bl-live-bg);
    border-radius: 3px;
    overflow: hidden;
}

.bl-live-ft-capacity-bar {
    height: 100%;
    border-radius: 3px;
    background: var(--bl-live-primary);
    transition: width 0.3s;
}

.bl-live-ft-capacity-bar.full { background: var(--bl-live-danger); }

.bl-live-ft-capacity-text {
    font-size: 11px;
    color: var(--bl-live-text-muted);
    white-space: nowrap;
}

/* Action buttons */
.bl-live-ft-action {
    margin-top: auto;
    padding-top: 6px;
}

.bl-live-ft-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px 16px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: opacity 0.15s, transform 0.1s;
}

.bl-live-ft-btn:hover { opacity: 0.9; transform: translateY(-1px); }

.bl-live-ft-btn-login {
    background: var(--bl-live-primary);
    color: #fff;
}

.bl-live-ft-joined-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 100%;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--bl-live-success);
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.bl-live-ft-full-badge,
.bl-live-ft-ended-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: var(--bl-live-text-muted);
    background: var(--bl-live-bg);
}
</style>
