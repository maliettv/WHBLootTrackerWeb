<?php
/**
 * Plugin Name: WHB Loot Tracker & Progression
 * Description: Modular Guild Management for the Waffle House Brawlers (TBC Anniversary).
 * Version: 1.0.1
 * Author: Justin Bowman (maliettv)
 * License: GPL2
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants for easy pathing
define('WHB_PATH', plugin_dir_path(__FILE__));

// 1. Database Setup (Needed for table creation and activation hooks)
require_once WHB_PATH . 'includes/db-setup.php';

// 2. Load Admin Logic only when a user is in the WordPress Dashboard
if (is_admin()) {
    require_once WHB_PATH . 'includes/admin-panel.php';
}

// 3. Load Shortcodes & AJAX (Needed for frontend rendering and background requests)
require_once WHB_PATH . 'includes/shortcodes.php';
require_once WHB_PATH . 'includes/ajax-handlers.php';

// 4. Enqueue Global Scripts
add_action('wp_enqueue_scripts', function() {
    // Load Wowhead tooltips globally so they work on any page with shortcodes
    wp_enqueue_script('wowhead-tooltips', 'https://nether.wowhead.com/widgets/power.js', array(), null, true);
});