<?php
/**
 * Plugin Name: WHB Loot Tracker & Progression
 * Description: Professional Guild Management for Waffle House Brawlers. Includes Loot, Roster, and Progression.
 * Version: 1.0.2
 * Author: Justin Bowman (maliettv)
 * License: GPL2
 */

if (!defined('ABSPATH')) exit;

/**
 * Define Plugin Constants
 */
define('WHB_VERSION', '1.0.2');
define('WHB_PATH', plugin_dir_path(__FILE__));
define('WHB_URL', plugin_dir_url(__FILE__));

/**
 * Initialize the Core Modular Loader
 */
require_once WHB_PATH . 'includes/class-whb-core.php';

// Launch the plugin
function run_whb_tracker() {
    $plugin = new WHB_Core();
    $plugin->init();
}
run_whb_tracker();

/**
 * Activation Hook
 * We keep this here to ensure it fires correctly during the plugin handshake.
 */
register_activation_hook(__FILE__, function() {
    require_once WHB_PATH . 'includes/class-whb-db.php';
    WHB_DB::create_tables();
});