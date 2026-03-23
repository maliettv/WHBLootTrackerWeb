<?php
if (!defined('ABSPATH')) exit;

class WHB_Core {

    /**
     * Initialize all Modular Components
     */
    public function init() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load all required PHP Classes
     */
    private function load_dependencies() {
        require_once WHB_PATH . 'includes/class-whb-db.php';
        require_once WHB_PATH . 'includes/class-whb-admin.php';
        require_once WHB_PATH . 'includes/class-whb-ajax.php';
        require_once WHB_PATH . 'includes/class-whb-shortcodes.php';
    }

    /**
     * Register Dashboard-only Logic
     */
    private function define_admin_hooks() {
        if (is_admin()) {
            $admin = new WHB_Admin();
            add_action('admin_menu', array($admin, 'add_menu_pages'));
        }
    }

    /**
     * Register Frontend Logic (Shortcodes & Assets)
     */
    private function define_public_hooks() {
        $shortcodes = new WHB_Shortcodes();
        
        // Register Loot Viewer
        add_shortcode('loot_viewer', array($shortcodes, 'render_loot_viewer'));
        
        // Register Raid Progression
        add_shortcode('raid_progression', array($shortcodes, 'render_progression'));
        
        // Register Roster Shortcodes
        add_shortcode('whb_10m_roster', array($shortcodes, 'render_10m_roster'));
        add_shortcode('whb_25m_roster', array($shortcodes, 'render_25m_roster'));
        
        // Register Recruitment Shortcode
        add_shortcode('whb_recruitment', array($shortcodes, 'render_recruitment'));

        // Enqueue Scripts & Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Global Asset Loading (Wowhead & Custom CSS)
     */
    public function enqueue_assets() {
        wp_enqueue_script('wowhead-tooltips', 'https://nether.wowhead.com/widgets/power.js', array(), null, true);
        
        // Add a small piece of CSS for the 100x100 icons and progression tooltips
        $custom_css = "
            .whb-class-icon { width: 18px; height: 18px; vertical-align: middle; margin-right: 5px; border-radius: 2px; }
            .whb-prog-bar { position: relative; cursor: help; }
            .whb-tooltip { 
                visibility: hidden; position: absolute; bottom: 125%; left: 50%; transform: translateX(-50%);
                background: #111; color: #fff; padding: 10px; border-radius: 5px; border: 1px solid #a335ee;
                z-index: 100; min-width: 180px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); opacity: 0; transition: opacity 0.3s;
            }
            .whb-prog-bar:hover .whb-tooltip { visibility: visible; opacity: 1; }
            .whb-recruitment-closed { color: #ff4d4d; font-weight: bold; text-transform: uppercase; }
            .whb-spec-strike { text-decoration: line-through; opacity: 0.5; }
        ";
        wp_add_inline_style('wowhead-tooltips', $custom_css);
    }
}