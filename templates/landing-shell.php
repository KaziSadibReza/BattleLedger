<?php
/**
 * BattleLedger Landing Shell Template
 *
 * Minimal, plugin-controlled template used when "Plugin-only shell" is enabled.
 * This bypasses theme layout files and renders only the landing shortcode container.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_page_id = (int) get_queried_object_id();
$is_landing_page = \BattleLedger\Core\PageInstaller::is_landing_page($current_page_id);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class('battleledger-landing-shell'); ?>>
<?php wp_body_open(); ?>
<style>
    html, body { margin: 0; padding: 0; }
    .battleledger-landing-shell-wrap { min-height: 100vh; }
    .battleledger-landing-shell-main {
        min-height: 60vh;
        padding-top: 70px;
    }
    .battleledger-landing-shell-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 1.25rem 1rem 2rem;
    }
    @media (min-width: 768px) {
        .battleledger-landing-shell-content {
            padding: 1.5rem;
        }
    }
</style>
<div class="battleledger-landing-shell-wrap">
    <?php if ($is_landing_page): ?>
        <?php echo do_shortcode('[battleledger_landing]'); ?>
    <?php else: ?>
        <div id="battleledger-landing-shell-header"></div>
        <main class="battleledger-landing-shell-main">
            <div class="battleledger-landing-shell-content">
                <?php
                if (have_posts()) {
                    while (have_posts()) {
                        the_post();
                        the_content();
                    }
                }
                ?>
            </div>
        </main>
        <div id="battleledger-landing-shell-footer"></div>
    <?php endif; ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
