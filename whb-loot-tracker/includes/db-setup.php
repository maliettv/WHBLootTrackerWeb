<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Database Setup on Plugin Activation
 * Creates the necessary tables for Loot and the Player Roster.
 */
function whb_plugin_activation_setup() {
    global $wpdb;
    $collate = $wpdb->get_charset_collate();
    
    // We need this WordPress core file to use the dbDelta() function
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // 1. Create the Loot History Table
    $loot_table = $wpdb->prefix . 'whb_loot';
    $sql_loot = "CREATE TABLE IF NOT EXISTS $loot_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        loot_date datetime, 
        player varchar(100), 
        item_id int,
        item_name varchar(255), 
        officer varchar(100), 
        zone varchar(100),
        raid_group varchar(100), 
        PRIMARY KEY (id)
    ) $collate;";
    dbDelta($sql_loot);

    // 2. Create the Roster & Roles Table (Includes the new player_class column)
    $roster_table = $wpdb->prefix . 'whb_roster';
    $sql_roster = "CREATE TABLE IF NOT EXISTS $roster_table (
        player varchar(100) NOT NULL,
        role varchar(50) NOT NULL,
        player_class varchar(50) DEFAULT '',
        PRIMARY KEY (player)
    ) $collate;";
    dbDelta($sql_roster);
}

// Hook into activation. We use WHB_PATH to ensure it targets the main plugin file.
register_activation_hook(WHB_PATH . 'whb-loot-tracker.php', 'whb_plugin_activation_setup');