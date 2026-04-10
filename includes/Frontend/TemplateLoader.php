<?php
namespace BattleLedger\Frontend;

use BattleLedger\Core\PageInstaller;

/**
 * Template loader for frontend
 */
class TemplateLoader {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('template_include', [$this, 'template_loader']);
    }
    
    /**
     * Load custom templates
     */
    public function template_loader($template) {
        if (is_singular('page')) {
            $current_page_id = get_queried_object_id();

            if ($current_page_id
                && PageInstaller::is_landing_plugin_shell_enabled()
                && PageInstaller::is_battleledger_page((int) $current_page_id)
            ) {
                $landing_shell_template = BATTLE_LEDGER_PLUGIN_DIR . 'templates/landing-shell.php';

                if (file_exists($landing_shell_template)) {
                    return $landing_shell_template;
                }
            }
        }

        // Check for custom post types or pages
        if (is_singular('bl_tournament')) {
            $custom_template = $this->locate_template('single-tournament.php');
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        if (is_post_type_archive('bl_tournament')) {
            $custom_template = $this->locate_template('archive-tournament.php');
            if ($custom_template) {
                return $custom_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Locate template
     */
    private function locate_template($template_name) {
        // Check theme directory first
        $theme_template = locate_template([
            'battle-ledger/' . $template_name,
            $template_name,
        ]);
        
        if ($theme_template) {
            return $theme_template;
        }
        
        // Check plugin templates directory
        $plugin_template = BATTLE_LEDGER_PLUGIN_DIR . 'templates/' . $template_name;
        
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Get template part
     */
    public static function get_template_part($slug, $name = null, $args = []) {
        $templates = [];
        
        if ($name) {
            $templates[] = "{$slug}-{$name}.php";
        }
        
        $templates[] = "{$slug}.php";
        
        $located = '';
        
        foreach ($templates as $template_name) {
            if (file_exists(BATTLE_LEDGER_PLUGIN_DIR . 'templates/' . $template_name)) {
                $located = BATTLE_LEDGER_PLUGIN_DIR . 'templates/' . $template_name;
                break;
            }
        }
        
        if ($located) {
            extract($args);
            include $located;
        }
    }
}
