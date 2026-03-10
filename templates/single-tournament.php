<?php
/**
 * Template: Single Tournament
 * 
 * @var array $atts Shortcode attributes
 */

use BattleLedger\Database\QueryBuilder;

$tournament_id = intval($atts['id'] ?? $_GET['tournament_id'] ?? 0);

if (!$tournament_id) {
    echo '<p>' . esc_html__('Tournament not found.', 'battle-ledger') . '</p>';
    return;
}

$query = new QueryBuilder('bl_tournaments');
$tournament = $query->where('id', $tournament_id)->first();

if (!$tournament) {
    echo '<p>' . esc_html__('Tournament not found.', 'battle-ledger') . '</p>';
    return;
}

// Get participants count
$participants_query = new QueryBuilder('bl_tournament_participants');
$participants_count = $participants_query->where('tournament_id', $tournament_id)->count();
?>

<div class="bl-single-tournament" id="tournament-<?php echo esc_attr($tournament->id); ?>">
    <header class="bl-tournament-header">
        <h1 class="bl-tournament-title"><?php echo esc_html($tournament->name); ?></h1>
        
        <div class="bl-tournament-meta">
            <span class="bl-status bl-status-<?php echo esc_attr($tournament->status); ?>">
                <?php echo esc_html(ucfirst($tournament->status)); ?>
            </span>
            <span class="bl-game-type"><?php echo esc_html($tournament->game_type); ?></span>
        </div>
    </header>
    
    <div class="bl-tournament-content">
        <?php if ($tournament->description): ?>
            <div class="bl-tournament-description">
                <?php echo wp_kses_post($tournament->description); ?>
            </div>
        <?php endif; ?>
        
        <div class="bl-tournament-details">
            <div class="bl-detail-item">
                <span class="bl-detail-label"><?php esc_html_e('Start Date:', 'battle-ledger'); ?></span>
                <span class="bl-detail-value">
                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->start_date))); ?>
                </span>
            </div>
            
            <?php if ($tournament->end_date): ?>
                <div class="bl-detail-item">
                    <span class="bl-detail-label"><?php esc_html_e('End Date:', 'battle-ledger'); ?></span>
                    <span class="bl-detail-value">
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->end_date))); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="bl-detail-item">
                <span class="bl-detail-label"><?php esc_html_e('Participants:', 'battle-ledger'); ?></span>
                <span class="bl-detail-value">
                    <?php echo esc_html($participants_count); ?>
                    <?php if ($tournament->max_participants): ?>
                        / <?php echo esc_html($tournament->max_participants); ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <?php if ($tournament->entry_fee > 0): ?>
                <div class="bl-detail-item">
                    <span class="bl-detail-label"><?php esc_html_e('Entry Fee:', 'battle-ledger'); ?></span>
                    <span class="bl-detail-value">
                        <?php echo wc_price($tournament->entry_fee); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if ($tournament->prize_pool > 0): ?>
                <div class="bl-detail-item">
                    <span class="bl-detail-label"><?php esc_html_e('Prize Pool:', 'battle-ledger'); ?></span>
                    <span class="bl-detail-value">
                        <?php echo wc_price($tournament->prize_pool); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (is_user_logged_in() && $tournament->status === 'active'): ?>
            <div class="bl-tournament-actions">
                <button class="bl-register-button" data-tournament-id="<?php echo esc_attr($tournament->id); ?>">
                    <?php esc_html_e('Register for Tournament', 'battle-ledger'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
