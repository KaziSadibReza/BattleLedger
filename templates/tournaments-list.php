<?php
/**
 * Template: Tournaments List
 * 
 * @var array $atts Shortcode attributes
 */

use BattleLedger\Database\QueryBuilder;

$status = $atts['status'] ?? 'active';
$limit = intval($atts['limit'] ?? 10);
$game_type = $atts['game_type'] ?? '';

$query = new QueryBuilder('bl_tournaments');

if ($status) {
    $query->where('status', $status);
}

if ($game_type) {
    $query->where('game_type', $game_type);
}

$tournaments = $query->orderBy('start_date', 'DESC')
                    ->limit($limit)
                    ->get();
?>

<div class="bl-tournaments-list" id="bl-tournaments-list">
    <?php if (empty($tournaments)): ?>
        <p class="bl-no-tournaments"><?php esc_html_e('No tournaments found.', 'battle-ledger'); ?></p>
    <?php else: ?>
        <div class="bl-tournaments-grid">
            <?php foreach ($tournaments as $tournament): ?>
                <div class="bl-tournament-card" id="tournament-<?php echo esc_attr($tournament->id); ?>">
                    <h3 class="bl-tournament-title"><?php echo esc_html($tournament->name); ?></h3>
                    
                    <?php if ($tournament->description): ?>
                        <div class="bl-tournament-description">
                            <?php echo wp_kses_post($tournament->description); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="bl-tournament-meta">
                        <span class="bl-status bl-status-<?php echo esc_attr($tournament->status); ?>">
                            <?php echo esc_html(ucfirst($tournament->status)); ?>
                        </span>
                        
                        <?php if ($tournament->start_date): ?>
                            <span class="bl-date">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tournament->start_date))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <a href="<?php echo esc_url(add_query_arg('tournament_id', $tournament->id, get_permalink())); ?>" 
                       class="bl-view-button">
                        <?php esc_html_e('View Details', 'battle-ledger'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
