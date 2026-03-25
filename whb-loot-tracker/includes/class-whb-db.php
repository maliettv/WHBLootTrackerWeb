<?php
if (!defined('ABSPATH')) exit;

class WHB_DB {

    public static function activate() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $table_loot = $wpdb->prefix . 'whb_loot';
        dbDelta("CREATE TABLE $table_loot ( id mediumint(9) NOT NULL AUTO_INCREMENT, loot_date varchar(50) NOT NULL, player varchar(100) NOT NULL, item_id int(11) NOT NULL DEFAULT 0, item_name varchar(255) NOT NULL, zone varchar(100) NOT NULL, raid_group varchar(100) NOT NULL, PRIMARY KEY  (id) ) $charset_collate;");

        $table_roster = $wpdb->prefix . 'whb_roster';
        dbDelta("CREATE TABLE $table_roster ( player varchar(100) NOT NULL, player_class varchar(50) NOT NULL, role varchar(50) NOT NULL DEFAULT '', PRIMARY KEY  (player) ) $charset_collate;");

        $table_groups = $wpdb->prefix . 'whb_raid_groups';
        dbDelta("CREATE TABLE $table_groups ( id mediumint(9) NOT NULL AUTO_INCREMENT, group_key varchar(50) NOT NULL, slot_id varchar(50) NOT NULL, player_name varchar(100) NOT NULL, PRIMARY KEY  (id), UNIQUE KEY group_slot (group_key, slot_id) ) $charset_collate;");

        $table_recruitment = $wpdb->prefix . 'whb_recruitment';
        dbDelta("CREATE TABLE $table_recruitment ( id mediumint(9) NOT NULL AUTO_INCREMENT, class_key varchar(50) NOT NULL, spec_key varchar(100) NOT NULL, is_open tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY  (id) ) $charset_collate;");

        $table_apps = $wpdb->prefix . 'whb_applications';
        dbDelta("CREATE TABLE $table_apps ( id mediumint(9) NOT NULL AUTO_INCREMENT, created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, applicant varchar(255) NOT NULL, app_data longtext NOT NULL, PRIMARY KEY  (id) ) $charset_collate;");

        // Seed default recruitment classes
        if ($wpdb->get_var("SELECT COUNT(*) FROM $table_recruitment") == 0) {
            $classes = ['druid'=>['Balance','Feral','Restoration'], 'hunter'=>['Beast Mastery','Marksmanship','Survival'], 'mage'=>['Arcane','Fire','Frost'], 'paladin'=>['Holy','Protection','Retribution'], 'priest'=>['Discipline','Holy','Shadow'], 'rogue'=>['Assassination','Combat','Subtlety'], 'shaman'=>['Elemental','Enhancement','Restoration'], 'warlock'=>['Affliction','Demonology','Destruction'], 'warrior'=>['Arms','Fury','Protection']];
            foreach ($classes as $class => $specs) {
                foreach ($specs as $spec) { $wpdb->insert($table_recruitment, ['class_key' => $class, 'spec_key' => $spec, 'is_open' => 1]); }
            }
        }
    }
}