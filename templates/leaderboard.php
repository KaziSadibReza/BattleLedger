<?php
/**
 * Template: Leaderboard
 * 
 * @var array $atts Shortcode attributes
 */

use BattleLedger\Database\QueryBuilder;

$tournament_id = intval($atts['tournament_id'] ?? 0);
$limit = intval($atts['limit'] ?? 20);

$query = new QueryBuilder('bl_tournament_participants');

if ($tournament_id) {
    $query->where('tournament_id', $tournament_id);
}

$participants = $query->orderBy('rank', 'ASC')
                     ->orderBy('score', 'DESC')
                     ->limit($limit)
                     ->get();
?>

<div class="bl-leaderboard" id="bl-leaderboard">
    <?php if (empty($participants)): ?>
        <p class="bl-no-data"><?php esc_html_e('No leaderboard data available.', 'battle-ledger'); ?></p>
    <?php else: ?>
        <table class="bl-leaderboard-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rank', 'battle-ledger'); ?></th>
                    <th><?php esc_html_e('Player', 'battle-ledger'); ?></th>
                    <th><?php esc_html_e('Team', 'battle-ledger'); ?></th>
                    <th><?php esc_html_e('Score', 'battle-ledger'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $index => $participant): 
                    $user = get_userdata($participant->user_id);
                    $rank = $participant->rank ?: ($index + 1);
                ?>
                    <tr class="bl-leaderboard-row">
                        <td class="bl-rank">
                            <span class="bl-rank-number rank-<?php echo esc_attr($rank); ?>">
                                <?php echo esc_html($rank); ?>
                            </span>
                        </td>
                        <td class="bl-player">
                            <?php echo esc_html($user ? $user->display_name : __('Unknown', 'battle-ledger')); ?>
                        </td>
                        <td class="bl-team">
                            <?php echo esc_html($participant->team_name ?: '-'); ?>
                        </td>
                        <td class="bl-score">
                            <?php echo esc_html(number_format($participant->score, 2)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
