<?php
namespace BattleLedger\Frontend;

/**
 * Shortcodes management
 */
class Shortcodes {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('battle_ledger_tournaments', [$this, 'tournaments_list']);
        add_shortcode('battle_ledger_tournament', [$this, 'single_tournament']);
        add_shortcode('battle_ledger_leaderboard', [$this, 'leaderboard']);
        add_shortcode('battle_ledger_user_dashboard', [$this, 'user_dashboard']);
        add_shortcode('battleledger_live_tournaments', [$this, 'live_tournaments']);
    }
    
    /**
     * Tournaments list shortcode
     */
    public function tournaments_list($atts) {
        $atts = shortcode_atts([
            'status' => 'active',
            'limit' => 10,
            'game_type' => '',
        ], $atts);
        
        ob_start();
        include BATTLE_LEDGER_PLUGIN_DIR . 'templates/tournaments-list.php';
        return ob_get_clean();
    }
    
    /**
     * Single tournament shortcode
     */
    public function single_tournament($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);
        
        ob_start();
        include BATTLE_LEDGER_PLUGIN_DIR . 'templates/single-tournament.php';
        return ob_get_clean();
    }
    
    /**
     * Leaderboard shortcode
     */
    public function leaderboard($atts) {
        $atts = shortcode_atts([
            'tournament_id' => 0,
            'limit' => 20,
        ], $atts);
        
        ob_start();
        include BATTLE_LEDGER_PLUGIN_DIR . 'templates/leaderboard.php';
        return ob_get_clean();
    }
    
    /**
     * User dashboard shortcode
     */
    public function user_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please login to view your dashboard.', 'battle-ledger') . '</p>';
        }
        
        ob_start();
        include BATTLE_LEDGER_PLUGIN_DIR . 'templates/user-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Live tournaments shortcode (React SPA)
     * Usage: [battleledger_live_tournaments]
     */
    public function live_tournaments($atts) {
        // Enqueue the live-tournaments React bundle
        $is_dev = wp_script_is('battleledger-vite-client-live-tournaments', 'registered');
        if ($is_dev) {
            wp_enqueue_script('battleledger-vite-client-live-tournaments');
        }
        wp_enqueue_script('battleledger-live-tournaments');
        wp_enqueue_style('battleledger-live-tournaments');

        // Build props for the React app
        $login_url = '';
        if (!is_user_logged_in()) {
            $login_url = \BattleLedger\Core\PageInstaller::get_page_url('login');
        }

        $current_user = wp_get_current_user();

        $props = [
            'apiUrl'    => esc_url_raw(rest_url()),
            'nonce'     => wp_create_nonce('wp_rest'),
            'isLoggedIn'=> is_user_logged_in(),
            'loginUrl'  => $login_url ?: wp_login_url(get_permalink()),
            'userId'    => $current_user->ID ?? 0,
        ];

        // Generate CSS variables from admin appearance settings
        $css_vars = self::generate_live_tournaments_css_variables();

        return sprintf(
            '<style>%s</style><div class="battleledger-live-tournaments-container" data-props="%s"></div>',
            $css_vars,
            esc_attr(wp_json_encode($props))
        );
    }
    
    /**
     * Generate CSS custom properties for live tournaments from admin appearance settings
     */
    private static function generate_live_tournaments_css_variables(): string {
        $defaults = [
            'primaryColor'       => '#6366f1',
            'primaryHoverColor'  => '#4f46e5',
            'successColor'       => '#10b981',
            'warningColor'       => '#f59e0b',
            'dangerColor'        => '#ef4444',
            'backgroundColor'    => '#f8fafc',
            'surfaceColor'       => '#ffffff',
            'borderColor'        => '#e2e8f0',
            'textColor'          => '#0f172a',
            'textSecondaryColor' => '#475569',
            'textMutedColor'     => '#94a3b8',
        ];
        $settings = get_option('battle_ledger_frontend_appearance', []);
        $s = wp_parse_args($settings, $defaults);

        $css = '.bl-lt-root {';

        // Primary
        $css .= '--bl-lt-primary: '       . esc_attr($s['primaryColor']) . ';';
        $css .= '--bl-lt-primary-hover: '  . esc_attr($s['primaryHoverColor']) . ';';
        $css .= '--bl-lt-primary-light: '  . self::hex_to_rgba($s['primaryColor'], 0.08) . ';';

        // Success
        $css .= '--bl-lt-success: '        . esc_attr($s['successColor']) . ';';
        $css .= '--bl-lt-success-light: '  . self::hex_to_rgba($s['successColor'], 0.08) . ';';
        $css .= '--bl-lt-success-glow: '   . self::hex_to_rgba($s['successColor'], 0.5) . ';';
        $css .= '--bl-lt-success-border: ' . self::hex_to_rgba($s['successColor'], 0.2) . ';';

        // Warning
        $css .= '--bl-lt-warning: '        . esc_attr($s['warningColor']) . ';';
        $css .= '--bl-lt-warning-light: '  . self::hex_to_rgba($s['warningColor'], 0.08) . ';';

        // Danger
        $css .= '--bl-lt-danger: '         . esc_attr($s['dangerColor']) . ';';
        $css .= '--bl-lt-danger-light: '   . self::hex_to_rgba($s['dangerColor'], 0.08) . ';';

        // Text
        $css .= '--bl-lt-text: '           . esc_attr($s['textColor']) . ';';
        $css .= '--bl-lt-text-secondary: ' . esc_attr($s['textSecondaryColor']) . ';';
        $css .= '--bl-lt-text-muted: '     . esc_attr($s['textMutedColor']) . ';';

        // Surfaces
        $css .= '--bl-lt-border: '  . esc_attr($s['borderColor']) . ';';
        $css .= '--bl-lt-bg: '      . esc_attr($s['backgroundColor']) . ';';
        $css .= '--bl-lt-surface: ' . esc_attr($s['surfaceColor']) . ';';

        $css .= '}';

        return $css;
    }
    
    /**
     * Convert hex color to rgba string
     */
    private static function hex_to_rgba(string $hex, float $alpha = 1.0): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
