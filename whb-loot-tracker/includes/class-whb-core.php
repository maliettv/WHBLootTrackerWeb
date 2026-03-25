<?php
if (!defined('ABSPATH')) exit;

class WHB_Core {

    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once WHB_DIR . 'includes/class-whb-ajax.php';
        require_once WHB_DIR . 'includes/class-whb-shortcodes.php';
        
        if (is_admin()) {
            require_once WHB_DIR . 'includes/class-whb-admin.php';
        }
    }

    private function init_hooks() {
        // Instantiate the classes
        if (is_admin() && class_exists('WHB_Admin')) {
            new WHB_Admin();
        }
        if (class_exists('WHB_Shortcodes')) {
            new WHB_Shortcodes();
        }
        if (class_exists('WHB_Ajax')) {
            new WHB_Ajax();
        }

        // Global Frontend Scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_script('wowhead-tooltips', 'https://wow.zamimg.com/widgets/power.js', array(), null, true);
        wp_add_inline_script('wowhead-tooltips', 'var whTooltips = {colorLinks: true, iconizeLinks: true, renameLinks: true};', 'before');
    }
}