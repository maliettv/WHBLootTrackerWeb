<?php
if (!defined('ABSPATH')) exit;

class WHB_Admin {

    public function add_menu_pages() {
        add_menu_page(
            'WHB Tracker', 'WHB Tracker', 'manage_options', 
            'whb-tracker', array($this, 'render_admin_page'), 'dashicons-shield'
        );
    }

    private function get_raids() {
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

    public function render_admin_page() {
        global $wpdb;
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'loot';
        $this->handle_post_requests();

        ?>
        <div class="wrap">
            <h1>WHB Guild Management <span style="font-size:12px; opacity:0.6;">v1.0.2</span></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=whb-tracker&tab=loot" class="nav-tab <?php echo $active_tab == 'loot' ? 'nav-tab-active' : ''; ?>">Loot & Themes</a>
                <a href="?page=whb-tracker&tab=progression" class="nav-tab <?php echo $active_tab == 'progression' ? 'nav-tab-active' : ''; ?>">Raid Progression</a>
                <a href="?page=whb-tracker&tab=roster" class="nav-tab <?php echo $active_tab == 'roster' ? 'nav-tab-active' : ''; ?>">Player Roster</a>
                <a href="?page=whb-tracker&tab=groups" class="nav-tab <?php echo $active_tab == 'groups' ? 'nav-tab-active' : ''; ?>">Raid Groups</a>
                <a href="?page=whb-tracker&tab=recruitment" class="nav-tab <?php echo $active_tab == 'recruitment' ? 'nav-tab-active' : ''; ?>">Recruitment</a>
                <a href="?page=whb-tracker&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Database Tools</a>
            </h2>

            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:15px; max-width:1100px;">
                <?php
                switch ($active_tab) {
                    case 'loot':        $this->tab_loot_themes(); break;
                    case 'progression': $this->tab_progression(); break;
                    case 'roster':      $this->tab_roster(); break;
                    case 'groups':      $this->tab_groups(); break;
                    case 'recruitment': $this->tab_recruitment(); break;
                    case 'tools':       $this->tab_database_tools(); break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function handle_post_requests() {
        global $wpdb;
        $loot_table = $wpdb->prefix . 'whb_loot';

        if (isset($_POST['whb_save_settings'])) {
            update_option('whb_theme', sanitize_text_field($_POST['whb_theme']));
            update_option('whb_custom_bg', sanitize_hex_color($_POST['whb_custom_bg']));
            update_option('whb_custom_text', sanitize_hex_color($_POST['whb_custom_text']));
            echo "<div class='updated'><p>Settings Saved.</p></div>";
        }

        if (isset($_POST['upload_loot']) && !empty($_FILES['csv']['tmp_name'])) {
            $f = fopen($_FILES['csv']['tmp_name'], 'r'); fgetcsv($f); $c = 0;
            while (($l = fgetcsv($f)) !== FALSE) {
                preg_match('/item:(\d+)/', $l[2], $id); preg_match('/\[(.*?)\]/', $l[2], $nm);
                $wpdb->insert($loot_table, [
                    'loot_date'=>$l[0], 'player'=>$l[1], 'item_id'=>$id[1]??0, 
                    'item_name'=>$nm[1]??'Unknown', 'zone'=>$l[3], 'raid_group'=>$l[4]
                ]);
                $c++;
            }
            echo "<div class='updated'><p>Imported $c loot records.</p></div>";
        }

        if (isset($_POST['whb_purge_all_loot']) && check_admin_referer('whb_purge_action')) {
            $wpdb->query("TRUNCATE TABLE $loot_table");
            echo "<div class='error'><p>All loot history has been permanently deleted.</p></div>";
        }

        if (isset($_POST['whb_delete_item']) && check_admin_referer('whb_delete_item_action')) {
            $item_id_to_delete = intval($_POST['delete_id']);
            $wpdb->delete($loot_table, ['id' => $item_id_to_delete]);
            echo "<div class='updated'><p>Item deleted successfully.</p></div>";
        }

        if (isset($_POST['whb_save_progression'])) {
            update_option('whb_progression_data', $_POST['prog'] ?? []);
            update_option('whb_visible_raids', array_keys($_POST['visible_raids'] ?? []));
            echo "<div class='updated'><p>Progression Updated.</p></div>";
        }

        if (isset($_POST['sync_roster_json']) && !empty($_POST['roster_json'])) {
            $data = json_decode(stripslashes($_POST['roster_json']), true);
            if (is_array($data)) {
                foreach ($data as $p) {
                    $wpdb->replace($wpdb->prefix.'whb_roster', [
                        'player' => sanitize_text_field($p['name']),
                        'player_class' => strtolower(sanitize_text_field($p['class'])),
                        'role' => ''
                    ]);
                }
                echo "<div class='updated'><p>Roster Synced from Addon.</p></div>";
            }
        }

        if (isset($_POST['whb_save_roster'])) {
            foreach ($_POST['roster'] as $player => $role) {
                $wpdb->update($wpdb->prefix.'whb_roster', [
                    'role' => sanitize_text_field($role),
                    'player_class' => sanitize_text_field($_POST['roster_class'][$player])
                ], ['player' => sanitize_text_field($player)]);
            }
            echo "<div class='updated'><p>Roster Roles/Classes Updated.</p></div>";
        }

        if (isset($_POST['save_groups'])) {
            update_option('whb_active_groups', $_POST['active_groups'] ?? []);
            foreach ($_POST['assign'] as $g_key => $slots) {
                foreach ($slots as $slot_id => $p_name) {
                    $wpdb->replace($wpdb->prefix.'whb_raid_groups', [
                        'group_key' => $g_key, 'slot_id' => $slot_id, 'player_name' => sanitize_text_field($p_name)
                    ]);
                }
            }
            echo "<div class='updated'><p>Raid Groups Saved.</p></div>";
        }

        if (isset($_POST['save_recruitment'])) {
            $wpdb->update($wpdb->prefix.'whb_recruitment', ['is_open' => 0], ['is_open' => 1]);
            if (isset($_POST['rec_open'])) {
                foreach ($_POST['rec_open'] as $id => $val) {
                    $wpdb->update($wpdb->prefix.'whb_recruitment', ['is_open' => 1], ['id' => intval($id)]);
                }
            }
            echo "<div class='updated'><p>Recruitment Status Updated.</p></div>";
        }
    }

    private function tab_loot_themes() {
        ?>
        <h2>Import Loot History</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv" accept=".csv" required>
            <input type="submit" name="upload_loot" class="button button-primary" value="Process CSV">
        </form>
        <hr style="margin:25px 0;">
        <h2>Display Settings</h2>
        <form method="post">
            <table class="form-table">
                <tr><th>Theme</th><td>
                    <select name="whb_theme">
                        <option value="dark" <?php selected(get_option('whb_theme'), 'dark'); ?>>Dark Mode</option>
                        <option value="light" <?php selected(get_option('whb_theme'), 'light'); ?>>Light Mode</option>
                        <option value="custom" <?php selected(get_option('whb_theme'), 'custom'); ?>>Custom Hex</option>
                    </select>
                </td></tr>
                <tr><th>Custom Background</th><td><input type="color" name="whb_custom_bg" value="<?php echo esc_attr(get_option('whb_custom_bg','#120f26')); ?>"></td></tr>
                <tr><th>Custom Text Color</th><td><input type="color" name="whb_custom_text" value="<?php echo esc_attr(get_option('whb_custom_text','#e2d3a3')); ?>"></td></tr>
            </table>
            <input type="submit" name="whb_save_settings" class="button button-primary" value="Save Theme Settings">
        </form>
        <?php
    }

    private function tab_progression() {
        $raids = $this->get_raids();
        $prog = get_option('whb_progression_data', []);
        $vis = get_option('whb_visible_raids', []);
        ?>
        <h2>Raid Progression (Hover states enabled on frontend)</h2>
        <form method="post">
            <?php foreach ($raids as $k => $r): ?>
                <div style="border:1px solid #ddd; padding:10px; margin-bottom:10px; background:#fcfcfc;">
                    <strong><label><input type="checkbox" name="visible_raids[<?php echo $k; ?>]" <?php checked(in_array($k,$vis)); ?>> Show <?php echo $r['name']; ?></label></strong>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:5px; margin-top:10px;">
                        <?php foreach($r['bosses'] as $i => $b): ?>
                            <label style="font-size:12px;"><input type="checkbox" name="prog[<?php echo $k; ?>][]" value="<?php echo $i; ?>" <?php checked(isset($prog[$k]) && in_array($i,$prog[$k])); ?>> <?php echo $b; ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <input type="submit" name="whb_save_progression" class="button button-primary" value="Save Progression">
        </form>
        <?php
    }

    private function tab_roster() {
        global $wpdb;
        $players = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}whb_roster ORDER BY player ASC");
        if (!is_array($players)) $players = []; // Failsafe if table doesn't exist yet
        ?>
        <h2>JSON Roster Importer</h2>
        <form method="post" style="margin-bottom:30px;">
            <textarea name="roster_json" style="width:100%; height:80px; font-family:monospace;" placeholder='[{"name":"Player","class":"warrior"}]'></textarea>
            <input type="submit" name="sync_roster_json" class="button button-secondary" value="Sync Roster from Addon String">
        </form>
        <hr>
        <h2>Manual Roster Overrides</h2>
        <form method="post">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Name</th><th>Role</th><th>Class</th></tr></thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr><td colspan="3">No players found. Sync roster to begin.</td></tr>
                    <?php else: foreach ($players as $p): ?>
                    <tr>
                        <td><strong><?php echo $p->player; ?></strong></td>
                        <td>
                            <select name="roster[<?php echo $p->player; ?>]">
                                <option value="">Unassigned</option>
                                <option value="tank" <?php selected($p->role,'tank'); ?>>🛡️ Tank</option>
                                <option value="healer" <?php selected($p->role,'healer'); ?>>💚 Healer</option>
                                <option value="melee" <?php selected($p->role,'melee'); ?>>⚔️ Melee</option>
                                <option value="ranged" <?php selected($p->role,'ranged'); ?>>🔮 Ranged</option>
                            </select>
                        </td>
                        <td>
                            <select name="roster_class[<?php echo $p->player; ?>]">
                                <?php foreach(['druid','hunter','mage','paladin','priest','rogue','shaman','warlock','warrior'] as $cl): ?>
                                    <option value="<?php echo $cl; ?>" <?php selected($p->player_class,$cl); ?>><?php echo ucfirst($cl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <input type="submit" name="whb_save_roster" class="button button-primary" value="Update Manual Changes">
        </form>
        <?php
    }

    private function tab_groups() {
        global $wpdb;
        $players = $wpdb->get_col("SELECT player FROM {$wpdb->prefix}whb_roster ORDER BY player ASC");
        if (!is_array($players)) $players = []; // Failsafe
        
        $active_groups = get_option('whb_active_groups', []);
        $groups = [
            '10m_1'=>'10M Group 1','10m_2'=>'10M Group 2','10m_3'=>'10M Group 3','10m_4'=>'10M Group 4','10m_5'=>'10M Group 5',
            '25m_main'=>'25M Main Raid','25m_alt'=>'25M Alt Raid'
        ];

        echo '<form method="post">';
        foreach ($groups as $key => $label) {
            $is_10m = (strpos($key, '10m') !== false);
            $num_tanks = 2; $num_heals = 2; $num_dps = $is_10m ? 6 : 21;
            $is_active = in_array($key, $active_groups);

            echo "<div style='border:1px solid #ccc; padding:15px; margin-bottom:15px; background:".($is_active ? '#fff' : '#eee')."'>";
            echo "<h3><label><input type='checkbox' name='active_groups[]' value='$key' ".checked($is_active, true, false)."> Enable $label</label></h3>";
            
            if ($is_active) {
                echo "<div style='display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;'>";
                $this->render_group_slots($key, 'Tank', $num_tanks, $players);
                $this->render_group_slots($key, 'Healer', $num_heals, $players);
                $this->render_group_slots($key, 'DPS', $num_dps, $players);
                echo "</div>";
            }
            echo "</div>";
        }
        echo '<input type="submit" name="save_groups" class="button button-primary" value="Save Group Assignments"></form>';
    }

    private function render_group_slots($g_key, $role_label, $count, $players) {
        global $wpdb;
        echo "<div><strong>$role_label</strong><br>";
        for ($i = 1; $i <= $count; $i++) {
            $slot_id = "{$role_label}_$i";
            
            // Failsafe for missing groups table
            $current = '';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}whb_raid_groups'") == $wpdb->prefix.'whb_raid_groups') {
                $current = $wpdb->get_var($wpdb->prepare("SELECT player_name FROM {$wpdb->prefix}whb_raid_groups WHERE group_key = %s AND slot_id = %s", $g_key, $slot_id));
            }
            
            echo "<select name='assign[$g_key][$slot_id]' style='width:100%; margin-bottom:5px;'>";
            echo "<option value=''>-- Empty --</option>";
            foreach ($players as $p) echo "<option value='".esc_attr($p)."' ".selected($current, $p, false).">$p</option>";
            echo "</select>";
        }
        echo "</div>";
    }

    private function tab_recruitment() {
        global $wpdb;
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}whb_recruitment ORDER BY class_key ASC");
        if (!is_array($data)) $data = []; // Failsafe
        
        $classes = []; foreach ($data as $r) { $classes[$r->class_key][] = $r; }

        echo "<h2>Recruitment Toggles</h2><form method='post'><div style='display:grid; grid-template-columns: repeat(3, 1fr); gap:15px;'>";
        
        if (empty($classes)) {
            echo "<p style='grid-column: 1 / -1; color: red;'>Database tables missing. Please deactivate and reactivate the plugin to generate recruitment data.</p>";
        } else {
            foreach ($classes as $cl => $specs) {
                echo "<div style='padding:10px; border:1px solid #ddd; background:#fff;'>";
                echo "<h4><img src='".WHB_URL."includes/images/class-icons/$cl.png' style='width:18px; vertical-align:middle;'> ".ucfirst($cl)."</h4>";
                foreach ($specs as $s) {
                    echo "<label style='display:block; font-size:12px;'><input type='checkbox' name='rec_open[{$s->id}]' value='1' ".checked($s->is_open, 1, false)."> {$s->spec_key}</label>";
                }
                echo "</div>";
            }
        }
        echo "</div><input type='submit' name='save_recruitment' class='button button-primary' value='Update Live Recruitment' style='margin-top:20px;'></form>";
    }

    private function tab_database_tools() {
        global $wpdb;
        $loot_table = $wpdb->prefix . 'whb_loot';
        $search = isset($_POST['loot_search']) ? sanitize_text_field($_POST['loot_search']) : '';
        ?>
        <h2>Database Maintenance</h2>
        
        <div style="background:#fff2f2; border:1px solid #d63638; padding:15px; border-radius:5px; margin-bottom:30px;">
            <h3 style="color:#d63638; margin-top:0;">Purge All Loot History</h3>
            <p><strong>Warning:</strong> This will permanently delete every single loot record in the database. This cannot be undone.</p>
            <form method="post" onsubmit="return confirm('Are you absolutely sure you want to delete ALL loot history?');">
                <?php wp_nonce_field('whb_purge_action'); ?>
                <input type="submit" name="whb_purge_all_loot" class="button button-link-delete" value="Purge All Loot Records" style="color:#d63638;">
            </form>
        </div>

        <hr>

        <h3>Delete Individual Loot Entries</h3>
        <p>Search for a player name or item name to find and remove specific mistakes.</p>
        
        <form method="post" style="margin-bottom:20px; display:flex; gap:10px;">
            <input type="text" name="loot_search" value="<?php echo esc_attr($search); ?>" placeholder="Player or Item Name..." style="width:300px;">
            <input type="submit" class="button" value="Search Database">
        </form>

        <?php if ($search): 
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $loot_table WHERE player LIKE %s OR item_name LIKE %s ORDER BY loot_date DESC LIMIT 50",
                "%$search%", "%$search%"
            ));
            if (!is_array($results)) $results = []; // Failsafe
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Player</th>
                        <th>Item</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($row->loot_date)); ?></td>
                            <td><strong><?php echo esc_html($row->player); ?></strong></td>
                            <td><?php echo esc_html($row->item_name); ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this specific entry?');">
                                    <?php wp_nonce_field('whb_delete_item_action'); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $row->id; ?>">
                                    <input type="submit" name="whb_delete_item" class="button button-small" value="Delete" style="color:#d63638;">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4">No items found matching that search.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }
}