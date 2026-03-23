<?php
if (!defined('ABSPATH')) exit;

class WHB_DB {

    /**
     * Create or Update Custom SQL Tables
     * Called via register_activation_hook in the main plugin file.
     */
    public static function create_tables() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Loot History Table
        $table_loot = $wpdb->prefix . 'whb_loot';
        $sql_loot = "CREATE TABLE IF NOT EXISTS $table_loot (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            loot_date datetime,
            player varchar(100),
            item_id int,
            item_name varchar(255),
            officer varchar(100),
            zone varchar(100),
            raid_group varchar(100),
            PRIMARY KEY (id),
            KEY player_idx (player),
            KEY zone_idx (zone)
        ) $collate;";
        dbDelta($sql_loot);

        // 2. Player Roster Table (Core player data)
        $table_roster = $wpdb->prefix . 'whb_roster';
        $sql_roster = "CREATE TABLE IF NOT EXISTS $table_roster (
            player varchar(100) NOT NULL,
            role varchar(50) NOT NULL,
            player_class varchar(50) DEFAULT '',
            PRIMARY KEY (player)
        ) $collate;";
        dbDelta($sql_roster);

        // 3. Raid Group Assignments Table (10M and 25M Compositions)
        $table_groups = $wpdb->prefix . 'whb_raid_groups';
        $sql_groups = "CREATE TABLE IF NOT EXISTS $table_groups (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            group_key varchar(50) NOT NULL,
            player_name varchar(100) DEFAULT '',
            slot_id varchar(50) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY group_slot (group_key, slot_id)
        ) $collate;";
        dbDelta($sql_groups);

        // 4. Recruitment Status Table
        $table_recruitment = $wpdb->prefix . 'whb_recruitment';
        $sql_recruitment = "CREATE TABLE IF NOT EXISTS $table_recruitment (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            class_key varchar(50) NOT NULL,
            spec_key varchar(50) NOT NULL,
            is_open tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY class_spec (class_key, spec_key)
        ) $collate;";
        dbDelta($sql_recruitment);
        
        // Ensure default recruitment data exists if table is new
        self::seed_recruitment_data();
    }

    /**
     * Pre-populate the recruitment table with all TBC classes/specs
     */
    private static function seed_recruitment_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'whb_recruitment';
        
        $specs = [
            'druid'   => ['Balance', 'Feral', 'Restoration'],
            'hunter'  => ['Beast Mastery', 'Marksmanship', 'Survival'],
            'mage'    => ['Arcane', 'Fire', 'Frost'],
            'paladin' => ['Holy', 'Protection', 'Retribution'],
            'priest'  => ['Discipline', 'Holy', 'Shadow'],
            'rogue'   => ['Assassination', 'Combat', 'Subtlety'],
            'shaman'  => ['Elemental', 'Enhancement', 'Restoration'],
            'warlock' => ['Affliction', 'Demonology', 'Destruction'],
            'warrior' => ['Arms', 'Fury', 'Protection']
        ];

        foreach ($specs as $class => $class_specs) {
            foreach ($class_specs as $spec) {
                // Using INSERT IGNORE via query to prevent duplicates if already seeded
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO $table (class_key, spec_key, is_open) VALUES (%s, %s, %d)",
                    $class, $spec, 1
                ));
            }
        }
    }
}