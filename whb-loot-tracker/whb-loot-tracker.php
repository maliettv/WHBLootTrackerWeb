<?php
/**
 * Plugin Name: WHB Loot Tracker
 * Description: Comprehensive guild management, loot tracking, dynamic roster builder, and application system designed exclusively for the Waffle House Brawlers.
 * Version: 1.0.13
 * Author: Maliettv
 * Plugin URI: https://github.com/maliettv/WHBLootTrackerWeb
 */

if (!defined('ABSPATH')) exit;

// Define Plugin Constants
define('WHB_VERSION', '1.0.14');
define('WHB_DIR', plugin_dir_path(__FILE__));
define('WHB_URL', plugin_dir_url(__FILE__));

// Require the Database class for the activation hook
require_once WHB_DIR . 'includes/class-whb-db.php';
register_activation_hook(__FILE__, array('WHB_DB', 'activate'));

// Require the core class and boot up
require_once WHB_DIR . 'includes/class-whb-core.php';
add_action('plugins_loaded', function() {
    new WHB_Core();
});