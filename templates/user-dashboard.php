<?php
/**
 * Template: User Dashboard
 */

use BattleLedger\Database\QueryBuilder;

$user_id = get_current_user_id();

// Get user's tournaments
$query = new QueryBuilder('bl_tournament_participants');
$my_tournaments = $query->where('user_id', $user_id)
                       ->orderBy('registered_at', 'DESC')
                       ->get();
?>

<div class="bl-user-dashboard" id="bl-user-dashboard">
    <h2 class="bl-dashboard-title"><?php esc_html_e('My Tournament Dashboard', 'battle-ledger'); ?></h2>
    
    <div class="bl-dashboard-stats">
        <div class="bl-stat-card">
            <div class="bl-stat-value"><?php echo count($my_tournaments); ?></div>
            <div class="bl-stat-label"><?php esc_html_e('Tournaments Entered', 'battle-ledger'); ?></div>
        </div>
    </div>
    
    <div class="bl-my-tournaments">
        <h3><?php esc_html_e('My Tournaments', 'battle-ledger'); ?></h3>
        
        <?php if (empty($my_tournaments)): ?>
            <p class="bl-no-data"><?php esc_html_e('You have not entered any tournaments yet.', 'battle-ledger'); ?></p>
        <?php else: ?>
            <div class="bl-tournaments-list">
                <?php foreach ($my_tournaments as $participation): 
                    $tournament_query = new QueryBuilder('bl_tournaments');
                    $tournament = $tournament_query->where('id', $participation->tournament_id)->first();
                    
                    if (!$tournament) continue;
                ?>
                    <div class="bl-tournament-item">
                        <h4><?php echo esc_html($tournament->name); ?></h4>
                        <div class="bl-tournament-info">
                            <span class="bl-status"><?php echo esc_html(ucfirst($participation->status)); ?></span>
                            <?php if ($participation->rank): ?>
                                <span class="bl-rank">Rank: <?php echo esc_html($participation->rank); ?></span>
                            <?php endif; ?>
                            <span class="bl-score">Score: <?php echo esc_html(number_format($participation->score, 2)); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
