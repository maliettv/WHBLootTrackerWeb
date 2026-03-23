<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * TBC Raid Database (Helper Function)
 * Used by both the Admin Panel and the Frontend Shortcode.
 */
if (!function_exists('whb_get_tbc_raids')) {
    function whb_get_tbc_raids() {
        return [
            'karazhan' => ['name' => 'Karazhan', 'bosses' => ['Attumen the Huntsman', 'Moroes', 'Maiden of Virtue', 'Opera Event', 'Nightbane', 'The Curator', 'Terestian Illhoof', 'Shade of Aran', 'Netherspite', 'Chess Event', 'Prince Malchezaar']],
            'gruul' => ['name' => "Gruul's Lair", 'bosses' => ['High King Maulgar', 'Gruul the Dragonkiller']],
            'magtheridon' => ['name' => "Magtheridon's Lair", 'bosses' => ['Magtheridon']],
            'ssc' => ['name' => 'Serpentshrine Cavern', 'bosses' => ['Hydross the Unstable', 'The Lurker Below', 'Leotheras the Blind', 'Fathom-Lord Karathress', 'Morogrim Tidewalker', 'Lady Vashj']],
            'tk' => ['name' => 'Tempest Keep', 'bosses' => ["Al'ar", 'Void Reaver', 'High Astromancer Solarian', 'Kael\'thas Sunstrider']],
            'hyjal' => ['name' => 'Battle for Mount Hyjal', 'bosses' => ['Rage Winterchill', 'Anetheron', 'Kaz\'rogal', 'Azgalor', 'Archimonde']],
            'bt' => ['name' => 'Black Temple', 'bosses' => ['High Warlord Naj\'entus', 'Supremus', 'Shade of Akama', 'Teron Gorefiend', 'Reliquary of Souls', 'Gurtogg Bloodboil', 'Mother Shahraz', 'The Illidari Council', 'Illidan Stormrage']],
            'za' => ['name' => 'Zul\'Aman', 'bosses' => ['Nalorakk', 'Akil\'zon', 'Jan\'alai', 'Halazzi', 'Hex Lord Malacrass', 'Daakara']],
            'sunwell' => ['name' => 'Sunwell Plateau', 'bosses' => ['Kalecgos', 'Brutallus', 'Felmyst', 'The Eredar Twins', 'M\'uru', 'Kil\'jaeden']]
        ];
    }
}

/**
 * Register the Admin Menu
 */
add_action('admin_menu', function() {
    add_menu_page('WHB Tracker', 'WHB Tracker', 'manage_options', 'whb-tracker', 'whb_admin_page', 'dashicons-shield');
});

/**
 * Render the Admin Page UI and Handle Form Submissions
 */
function whb_admin_page() {
    global $wpdb;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'loot';
    $raids = whb_get_tbc_raids();

    // --------------------------------------------------------
    // Handle Saves & Data Processing
    // --------------------------------------------------------
    
    // 1. Save Theme Settings
    if (isset($_POST['whb_save_settings'])) {
        update_option('whb_theme', sanitize_text_field($_POST['whb_theme']));
        update_option('whb_custom_bg', sanitize_hex_color($_POST['whb_custom_bg']));
        update_option('whb_custom_text', sanitize_hex_color($_POST['whb_custom_text']));
        echo "<div class='updated'><p>Display Settings Saved!</p></div>";
    }

    // 2. Save Raid Progression
    if (isset($_POST['whb_save_progression'])) {
        $prog_data = isset($_POST['prog']) ? array_map(function($val) {
            return is_array($val) ? array_map('sanitize_text_field', $val) : sanitize_text_field($val);
        }, $_POST['prog']) : [];
        
        $visible = isset($_POST['visible_raids']) ? array_map('sanitize_text_field', array_keys($_POST['visible_raids'])) : [];
        
        update_option('whb_progression_data', $prog_data);
        update_option('whb_visible_raids', $visible);
        echo "<div class='updated'><p>Progression Updated!</p></div>";
    }

    // 3. Save Roster (Roles & Classes)
    if (isset($_POST['whb_save_roster'])) {
        foreach ($_POST['roster'] as $player => $role) {
            $player = sanitize_text_field($player);
            $role = sanitize_text_field($role);
            $p_class = sanitize_text_field($_POST['roster_class'][$player] ?? '');
            
            if ($role || $p_class) {
                $wpdb->replace($wpdb->prefix.'whb_roster', [
                    'player' => $player, 
                    'role' => $role,
                    'player_class' => $p_class
                ]);
            } else {
                $wpdb->delete($wpdb->prefix.'whb_roster', ['player' => $player]);
            }
        }
        echo "<div class='updated'><p>Roster Roles & Classes Updated!</p></div>";
    }

    // 4. Process CSV Upload
    if (isset($_POST['upload']) && !empty($_FILES['csv']['tmp_name'])) {
        $f = fopen($_FILES['csv']['tmp_name'], 'r'); 
        fgetcsv($f); // Skip header
        $c = 0;
        while (($l = fgetcsv($f)) !== FALSE) {
            preg_match('/item:(\d+)/', $l[2], $id); 
            preg_match('/\[(.*?)\]/', $l[2], $nm);
            $wpdb->insert($wpdb->prefix.'whb_loot', [
                'loot_date'  => sanitize_text_field($l[0]), 
                'player'     => sanitize_text_field($l[1]), 
                'item_id'    => $id[1] ?? 0, 
                'item_name'  => sanitize_text_field($nm[1] ?? 'Unknown'), 
                'zone'       => sanitize_text_field($l[3]), 
                'raid_group' => sanitize_text_field($l[4])
            ]);
            $c++;
        }
        fclose($f);
        echo "<div class='updated'><p>Imported $c records successfully.</p></div>";
    }

    // --------------------------------------------------------
    // Build the UI
    // --------------------------------------------------------
    ?>
    <div class="wrap">
        <h1>WHB Guild Tracker</h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=whb-tracker&tab=loot" class="nav-tab <?php echo $active_tab == 'loot' ? 'nav-tab-active' : ''; ?>">Loot & Themes</a>
            <a href="?page=whb-tracker&tab=progression" class="nav-tab <?php echo $active_tab == 'progression' ? 'nav-tab-active' : ''; ?>">Raid Progression</a>
            <a href="?page=whb-tracker&tab=roster" class="nav-tab <?php echo $active_tab == 'roster' ? 'nav-tab-active' : ''; ?>">Player Roster</a>
        </h2>

        <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:15px; max-width:800px;">
            
            <?php if ($active_tab == 'loot'): ?>
                <h2>1. Import Loot Data</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="csv" accept=".csv" required><br><br>
                    <input type="submit" name="upload" class="button-primary" value="Upload & Process CSV">
                </form>
                
                <hr style="margin: 20px 0;">
                
                <h2>2. Display Settings (Shortcode: <code>[loot_viewer]</code>)</h2>
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th>Theme</th>
                            <td>
                                <select name="whb_theme">
                                    <option value="dark" <?php selected(get_option('whb_theme'), 'dark'); ?>>Dark Mode</option>
                                    <option value="light" <?php selected(get_option('whb_theme'), 'light'); ?>>Light Mode</option>
                                    <option value="custom" <?php selected(get_option('whb_theme'), 'custom'); ?>>Custom Hex</option>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Custom Background</th><td><input type="color" name="whb_custom_bg" value="<?php echo esc_attr(get_option('whb_custom_bg', '#120f26')); ?>"></td></tr>
                        <tr><th>Custom Text</th><td><input type="color" name="whb_custom_text" value="<?php echo esc_attr(get_option('whb_custom_text', '#e2d3a3')); ?>"></td></tr>
                    </table>
                    <p><input type="submit" name="whb_save_settings" class="button-primary" value="Save Settings"></p>
                </form>

            <?php elseif ($active_tab == 'progression'): ?>
                <h2>Raid Progression Tracker (Shortcode: <code>[raid_progression]</code>)</h2>
                <form method="post">
                    <?php 
                    $saved_prog = get_option('whb_progression_data', []);
                    $saved_vis = get_option('whb_visible_raids', []);
                    
                    foreach ($raids as $key => $raid): 
                        $is_visible = in_array($key, $saved_vis);
                    ?>
                        <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fafafa;">
                            <h3 style="margin-top:0;">
                                <label>
                                    <input type="checkbox" name="visible_raids[<?php echo esc_attr($key); ?>]" value="1" <?php checked($is_visible, true); ?>> 
                                    <strong>Show <?php echo esc_html($raid['name']); ?> on Website</strong>
                                </label>
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                                <?php foreach ($raid['bosses'] as $index => $boss): 
                                    $is_dead = isset($saved_prog[$key]) && in_array($index, $saved_prog[$key]);
                                ?>
                                    <label>
                                        <input type="checkbox" name="prog[<?php echo esc_attr($key); ?>][]" value="<?php echo esc_attr($index); ?>" <?php checked($is_dead, true); ?>> 
                                        <?php echo esc_html($boss); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p><input type="submit" name="whb_save_progression" class="button-primary" value="Save Progression"></p>
                </form>

            <?php elseif ($active_tab == 'roster'): ?>
                <h2>Player Roles & Classes</h2>
                <p>Assign roles and classes to players. This colors their names and adds role icons in the <code>[loot_viewer]</code> table.</p>
                <form method="post">
                    <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                        <thead>
                            <tr>
                                <th>Player Name</th>
                                <th>Role</th>
                                <th>Class</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $players = $wpdb->get_col("SELECT DISTINCT player FROM {$wpdb->prefix}whb_loot ORDER BY player ASC");
                            if (!$players) echo "<tr><td colspan='3'>No players found in loot history yet. Import a CSV first.</td></tr>";
                            
                            foreach ($players as $player) {
                                if (in_array($player, ['Pending Trade', 'Disenchanted', 'Bank'])) continue;
                                
                                $roster_data = $wpdb->get_row($wpdb->prepare("SELECT role, player_class FROM {$wpdb->prefix}whb_roster WHERE player = %s", $player));
                                $current_role = $roster_data ? $roster_data->role : '';
                                $current_class = $roster_data ? $roster_data->player_class : '';
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($player); ?></strong></td>
                                    <td>
                                        <select name="roster[<?php echo esc_attr($player); ?>]">
                                            <option value="">Unassigned</option>
                                            <option value="tank" <?php selected($current_role, 'tank'); ?>>🛡️ Tank</option>
                                            <option value="healer" <?php selected($current_role, 'healer'); ?>>💚 Healer</option>
                                            <option value="melee" <?php selected($current_role, 'melee'); ?>>⚔️ Melee DPS</option>
                                            <option value="ranged" <?php selected($current_role, 'ranged'); ?>>🔮 Ranged DPS</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="roster_class[<?php echo esc_attr($player); ?>]">
                                            <option value="">Unknown</option>
                                            <option value="druid" <?php selected($current_class, 'druid'); ?>>Druid</option>
                                            <option value="hunter" <?php selected($current_class, 'hunter'); ?>>Hunter</option>
                                            <option value="mage" <?php selected($current_class, 'mage'); ?>>Mage</option>
                                            <option value="paladin" <?php selected($current_class, 'paladin'); ?>>Paladin</option>
                                            <option value="priest" <?php selected($current_class, 'priest'); ?>>Priest</option>
                                            <option value="rogue" <?php selected($current_class, 'rogue'); ?>>Rogue</option>
                                            <option value="shaman" <?php selected($current_class, 'shaman'); ?>>Shaman</option>
                                            <option value="warlock" <?php selected($current_class, 'warlock'); ?>>Warlock</option>
                                            <option value="warrior" <?php selected($current_class, 'warrior'); ?>>Warrior</option>
                                        </select>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <p><input type="submit" name="whb_save_roster" class="button-primary" value="Save Roster Configurations"></p>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}