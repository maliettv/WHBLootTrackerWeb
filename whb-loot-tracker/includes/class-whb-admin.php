<?php
if (!defined('ABSPATH')) exit;

class WHB_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
    }

    public function add_menu_pages() {
        add_menu_page('WHB Tracker', 'WHB Tracker', 'manage_options', 'whb-tracker', array($this, 'render_admin_page'), 'dashicons-shield');
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

    private function get_group_names() {
        $defaults = ['10m_1' => '10M Group 1', '10m_2' => '10M Group 2', '10m_3' => '10M Group 3', '10m_4' => '10M Group 4', '10m_5' => '10M Group 5', '25m_main' => '25M Main Raid', '25m_alt' => '25M Alt Raid'];
        return wp_parse_args(get_option('whb_group_names', []), $defaults);
    }

    private function get_managed_zones() {
        global $wpdb;
        $defaults = ['Karazhan', "Gruul's Lair", "Magtheridon's Lair", 'Serpentshrine Cavern', 'Tempest Keep', 'Battle for Mount Hyjal', 'Black Temple', "Zul'Aman", 'Sunwell Plateau'];
        $db_zones = $wpdb->get_col("SELECT DISTINCT zone FROM {$wpdb->prefix}whb_loot WHERE zone != ''");
        if (!is_array($db_zones)) $db_zones = [];
        
        $stripped_db_zones = array_map('stripslashes', $db_zones);
        $all_raw = array_unique(array_merge($defaults, $stripped_db_zones));
        sort($all_raw);
        
        $settings = get_option('whb_zone_settings', []);
        $managed = [];
        
        foreach ($all_raw as $raw) {
            $managed[$raw] = [
                'display' => isset($settings[$raw]['display']) && !empty($settings[$raw]['display']) ? stripslashes($settings[$raw]['display']) : $raw,
                'visible' => isset($settings[$raw]['visible']) ? $settings[$raw]['visible'] : 1
            ];
        }
        return $managed;
    }

    private function format_status_message($template, $applicant_name, $answers) {
        $schema = json_decode(get_option('whb_form_schema', '[]'), true);
        $parsed = str_ireplace('{Applicant}', $applicant_name, $template);
        if (is_array($answers)) {
            foreach ($answers as $q => $a) {
                if ($q === '_status' || $q === '_action_token') continue;
                $val = is_array($a) ? implode(', ', $a) : $a;
                $parsed = str_ireplace('{' . $q . '}', $val, $parsed);
            }
            if (is_array($schema)) {
                foreach ($schema as $f) {
                    $label = $f['label'];
                    if (isset($answers[$label])) {
                        $val = $answers[$label];
                        $val_str = is_array($val) ? implode(', ', $val) : $val;
                        $parsed = str_ireplace('{' . $f['id'] . '}', $val_str, $parsed);
                    }
                }
            }
        }
        return $parsed;
    }

    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'loot';
        $this->handle_post_requests();
        ?>
        <div class="wrap">
            <h1>WHB Guild Management <span style="font-size:12px; opacity:0.6;">v1.0.14</span></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=whb-tracker&tab=loot" class="nav-tab <?php echo $active_tab == 'loot' ? 'nav-tab-active' : ''; ?>">Loot & Themes</a>
                <a href="?page=whb-tracker&tab=progression" class="nav-tab <?php echo $active_tab == 'progression' ? 'nav-tab-active' : ''; ?>">Raid Progression</a>
                <a href="?page=whb-tracker&tab=roster" class="nav-tab <?php echo $active_tab == 'roster' ? 'nav-tab-active' : ''; ?>">Player Roster</a>
                <a href="?page=whb-tracker&tab=groups" class="nav-tab <?php echo $active_tab == 'groups' ? 'nav-tab-active' : ''; ?>">Raid Groups</a>
                <a href="?page=whb-tracker&tab=recruitment" class="nav-tab <?php echo $active_tab == 'recruitment' ? 'nav-tab-active' : ''; ?>">Recruitment</a>
                <a href="?page=whb-tracker&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">Loot Management</a>
                <a href="?page=whb-tracker&tab=builder" class="nav-tab <?php echo $active_tab == 'builder' ? 'nav-tab-active' : ''; ?>">Form Builder</a>
                <a href="?page=whb-tracker&tab=apps" class="nav-tab <?php echo $active_tab == 'apps' ? 'nav-tab-active' : ''; ?>">Applications</a>
                <a href="?page=whb-tracker&tab=credits" class="nav-tab <?php echo $active_tab == 'credits' ? 'nav-tab-active' : ''; ?>">Credits</a>
            </h2>
            <div style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:15px; max-width:1100px;">
                <?php
                switch ($active_tab) {
                    case 'loot': $this->tab_loot_themes(); break;
                    case 'progression': $this->tab_progression(); break;
                    case 'roster': $this->tab_roster(); break;
                    case 'groups': $this->tab_groups(); break;
                    case 'recruitment': $this->tab_recruitment(); break;
                    case 'tools': $this->tab_loot_management(); break;
                    case 'builder': $this->tab_builder(); break;
                    case 'apps': $this->tab_applications(); break;
                    case 'credits': $this->tab_credits(); break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function handle_post_requests() {
        global $wpdb;
        $loot_table = $wpdb->prefix . 'whb_loot';
        $apps_table = $wpdb->prefix . 'whb_applications';
        $roster_table = $wpdb->prefix . 'whb_roster';

        if (isset($_POST['whb_save_zone_settings'])) {
            $zone_settings = [];
            $originals = $_POST['zone_original'] ?? [];
            $displays = $_POST['zone_display'] ?? [];
            $visibles = $_POST['zone_visible'] ?? [];
            foreach ($originals as $i => $orig) {
                $clean_orig = stripslashes($orig); 
                $zone_settings[$clean_orig] = [
                    'display' => sanitize_text_field(stripslashes($displays[$i])),
                    'visible' => isset($visibles[$i]) ? 1 : 0
                ];
            }
            update_option('whb_zone_settings', $zone_settings);
            echo "<div class='updated'><p>Zone Display Names and Visibility saved successfully.</p></div>";
        }

        if (isset($_POST['whb_save_group_names'])) {
            $custom_names = []; foreach ($_POST['group_names'] as $key => $val) { $custom_names[$key] = sanitize_text_field(stripslashes($val)); }
            update_option('whb_group_names', $custom_names);
            echo "<div class='updated'><p>Raid Group Display Names saved successfully.</p></div>";
        }
        
        if (isset($_POST['whb_manual_add_player']) && check_admin_referer('whb_manual_add_player_action')) {
            $name = sanitize_text_field(stripslashes($_POST['new_player_name'])); 
            $class = sanitize_text_field($_POST['new_player_class']); 
            $role = sanitize_text_field($_POST['new_player_role']);
            if (!empty($name) && !empty($class)) {
                if (!$wpdb->get_var($wpdb->prepare("SELECT player FROM $roster_table WHERE player = %s", $name))) {
                    $wpdb->insert($roster_table, ['player' => $name, 'player_class' => $class, 'role' => $role]);
                    echo "<div class='updated'><p>Successfully added <strong>".esc_html($name)."</strong> to the roster.</p></div>";
                } else { echo "<div class='error'><p>Player <strong>".esc_html($name)."</strong> already exists.</p></div>"; }
            }
        }
        
        if (isset($_POST['whb_manual_add_loot']) && check_admin_referer('whb_manual_add_loot_action')) {
            $date = sanitize_text_field($_POST['manual_loot_date']); 
            $player = sanitize_text_field(stripslashes($_POST['manual_loot_player'])); 
            $item_name = sanitize_text_field(stripslashes($_POST['manual_loot_item_name']));
            if (!empty($date) && !empty($player) && !empty($item_name)) {
                $wpdb->insert($loot_table, ['loot_date'=>$date, 'player'=>$player, 'item_id'=>intval($_POST['manual_loot_item_id']), 'item_name'=>$item_name, 'zone'=>sanitize_text_field(stripslashes($_POST['manual_loot_zone'])), 'raid_group'=>sanitize_text_field(stripslashes($_POST['manual_loot_group']))]);
                echo "<div class='updated'><p>Manually added <strong>".esc_html($item_name)."</strong> for ".esc_html($player).".</p></div>";
            }
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete_player' && isset($_GET['player']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_player_' . sanitize_text_field(stripslashes($_GET['player'])))) {
                $wpdb->delete($roster_table, ['player' => sanitize_text_field(stripslashes($_GET['player']))]); 
                $wpdb->delete($wpdb->prefix . 'whb_raid_groups', ['player_name' => sanitize_text_field(stripslashes($_GET['player']))]);
                echo "<div class='updated'><p>Player removed.</p></div>";
            }
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete_app' && isset($_GET['app_id']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_app_' . intval($_GET['app_id']))) {
                $wpdb->delete($apps_table, ['id' => intval($_GET['app_id'])]);
                echo "<div class='updated'><p>Application deleted.</p></div>";
            }
        }

        // Handle Admin Application Status Changes
        if (isset($_GET['action']) && in_array($_GET['action'], ['approve_app', 'deny_app', 'pending_app']) && isset($_GET['app_id']) && isset($_GET['_wpnonce'])) {
            $app_id = intval($_GET['app_id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'status_app_' . $app_id)) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $apps_table WHERE id = %d", $app_id));
                if ($row) {
                    $data = json_decode($row->app_data, true);
                    $new_status = 'pending';
                    if ($_GET['action'] === 'approve_app') $new_status = 'approved';
                    if ($_GET['action'] === 'deny_app') $new_status = 'denied';
                    
                    // Only trigger webhook if the status changes TO approved/denied
                    $trigger_webhook = ((!isset($data['_status']) || $data['_status'] !== $new_status) && in_array($new_status, ['approved', 'denied']));
                    
                    $data['_status'] = $new_status;
                    $wpdb->update($apps_table, ['app_data' => wp_json_encode($data)], ['id' => $app_id]);
                    
                    if ($trigger_webhook) {
                        $status_url = get_option('whb_discord_webhook_status');
                        if (!empty($status_url)) {
                            $template = ($new_status === 'approved') ? get_option('whb_msg_approved') : get_option('whb_msg_denied');
                            if (!empty($template)) {
                                $msg = $this->format_status_message($template, $row->applicant, $data);
                                $color = ($new_status === 'approved') ? hexdec("46b450") : hexdec("d63638");
                                $embed = [ "description" => $msg, "color" => $color ];
                                wp_remote_post($status_url, ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['embeds' => [$embed]])]);
                            }
                        }
                    }
                    echo "<div class='updated'><p>Application successfully marked as <strong>" . ucfirst($new_status) . "</strong>.</p></div>";
                }
            }
        }
        
        if (isset($_POST['upload_rc_json']) && !empty($_POST['rc_json'])) {
            $data = json_decode(stripslashes($_POST['rc_json']), true); $raid_group = sanitize_text_field(stripslashes($_POST['rc_raid_group'])); $c = 0; $skipped = 0;
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (empty($item['player']) || empty($item['itemID'])) continue;
                    $player_clean = explode('-', sanitize_text_field(stripslashes($item['player'])))[0]; 
                    $date_clean = str_replace('/', '-', sanitize_text_field($item['date'])); 
                    $zone_clean = explode('-', sanitize_text_field(stripslashes($item['instance'])))[0]; 
                    $item_id = intval($item['itemID']); 
                    $item_name = sanitize_text_field(stripslashes($item['itemName']));
                    
                    if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM $loot_table WHERE loot_date = %s AND player = %s AND item_id = %d AND zone = %s AND raid_group = %s", $date_clean, $player_clean, $item_id, $zone_clean, $raid_group))) {
                        $wpdb->insert($loot_table, ['loot_date'=>$date_clean, 'player'=>$player_clean, 'item_id'=>$item_id, 'item_name'=>$item_name, 'zone'=>$zone_clean, 'raid_group'=>$raid_group]); $c++;
                    } else { $skipped++; }
                }
                echo "<div class='updated'><p>Imported $c new loot records from RCLootCouncil. Skipped $skipped duplicates.</p></div>";
            }
        }
        
        if (isset($_POST['whb_save_form'])) {
            update_option('whb_form_schema', stripslashes($_POST['whb_form_schema'])); 
            update_option('whb_discord_webhook', sanitize_text_field($_POST['whb_discord_webhook'])); 
            update_option('whb_app_status', sanitize_text_field($_POST['whb_app_status']));
            
            update_option('whb_discord_webhook_status', sanitize_text_field($_POST['whb_discord_webhook_status'])); 
            update_option('whb_msg_applied', sanitize_textarea_field(stripslashes($_POST['whb_msg_applied']))); 
            update_option('whb_msg_approved', sanitize_textarea_field(stripslashes($_POST['whb_msg_approved']))); 
            update_option('whb_msg_denied', sanitize_textarea_field(stripslashes($_POST['whb_msg_denied']))); 

            echo "<div class='updated'><p>Application Form settings and Discord templates saved.</p></div>";
        }
        
        if (isset($_POST['whb_save_settings'])) {
            update_option('whb_theme', sanitize_text_field($_POST['whb_theme'])); update_option('whb_custom_bg', sanitize_hex_color($_POST['whb_custom_bg'])); update_option('whb_custom_text', sanitize_hex_color($_POST['whb_custom_text']));
            echo "<div class='updated'><p>Settings Saved.</p></div>";
        }
        
        if (isset($_POST['upload_loot']) && !empty($_FILES['csv']['tmp_name'])) {
            $f = fopen($_FILES['csv']['tmp_name'], 'r'); fgetcsv($f); $c = 0; $skipped = 0; $auto_fixed = 0;
            while (($l = fgetcsv($f)) !== FALSE) {
                if (empty($l[0]) || empty($l[1]) || empty($l[2])) continue; 
                preg_match('/item:(\d+)/', $l[2], $id); preg_match('/\[(.*?)\]/', $l[2], $nm);
                $item_id = isset($id[1]) ? intval($id[1]) : 0; 
                $item_name = isset($nm[1]) ? sanitize_text_field(stripslashes($nm[1])) : 'Unknown';
                
                if ($item_id === 0 && $item_name !== 'Unknown') {
                    $known_id = $wpdb->get_var($wpdb->prepare("SELECT item_id FROM $loot_table WHERE item_name = %s AND item_id > 0 LIMIT 1", $item_name));
                    if ($known_id) { $item_id = intval($known_id); $auto_fixed++; }
                }
                if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM $loot_table WHERE loot_date = %s AND player = %s AND item_id = %d AND zone = %s AND raid_group = %s", sanitize_text_field($l[0]), sanitize_text_field(stripslashes($l[1])), $item_id, sanitize_text_field(stripslashes($l[3])), sanitize_text_field(stripslashes($l[4]))))) {
                    $wpdb->insert($loot_table, ['loot_date'=>sanitize_text_field($l[0]), 'player'=>sanitize_text_field(stripslashes($l[1])), 'item_id'=>$item_id, 'item_name'=>$item_name, 'zone'=>sanitize_text_field(stripslashes($l[3])), 'raid_group'=>sanitize_text_field(stripslashes($l[4]))]); $c++;
                } else { $skipped++; }
            }
            $msg = "Imported $c new loot records. Skipped $skipped duplicate records."; if ($auto_fixed > 0) $msg .= " Auto-corrected missing WoWhead IDs for $auto_fixed items.";
            echo "<div class='updated'><p>$msg</p></div>";
        }
        
        if (isset($_POST['whb_purge_all_loot']) && check_admin_referer('whb_purge_action')) {
            $wpdb->query("TRUNCATE TABLE $loot_table"); echo "<div class='error'><p>All loot history deleted.</p></div>";
        }
        if (isset($_POST['whb_delete_item']) && check_admin_referer('whb_delete_item_action')) {
            $wpdb->delete($loot_table, ['id' => intval($_POST['delete_id'])]); echo "<div class='updated'><p>Item deleted successfully.</p></div>";
        }
        if (isset($_POST['whb_save_progression'])) {
            update_option('whb_progression_data', $_POST['prog'] ?? []); update_option('whb_visible_raids', array_keys($_POST['visible_raids'] ?? [])); echo "<div class='updated'><p>Progression Updated.</p></div>";
        }
        if (isset($_POST['sync_roster_json']) && !empty($_POST['roster_json'])) {
            $data = json_decode(stripslashes($_POST['roster_json']), true);
            if (is_array($data)) {
                $added = 0; $updated = 0;
                foreach ($data as $p) {
                    if (empty($p['name'])) continue;
                    $p_name = sanitize_text_field(stripslashes($p['name'])); $p_class = strtolower(sanitize_text_field($p['class']));
                    if (!$wpdb->get_var($wpdb->prepare("SELECT player FROM $roster_table WHERE player = %s", $p_name))) {
                        $wpdb->insert($roster_table, ['player' => $p_name, 'player_class' => $p_class, 'role' => '']); $added++;
                    } else {
                        $wpdb->update($roster_table, ['player_class' => $p_class], ['player' => $p_name]); $updated++;
                    }
                }
                echo "<div class='updated'><p>Roster Synced. Added $added new players, updated $updated existing players.</p></div>";
            }
        }
        if (isset($_POST['whb_save_roster'])) {
            foreach ($_POST['roster'] as $player => $role) {
                $clean_player = sanitize_text_field(stripslashes($player));
                $wpdb->update($roster_table, ['role' => sanitize_text_field($role), 'player_class' => sanitize_text_field($_POST['roster_class'][$player])], ['player' => $clean_player]);
            }
            echo "<div class='updated'><p>Roster Roles/Classes Updated.</p></div>";
        }
        if (isset($_POST['save_groups'])) {
            update_option('whb_active_groups', $_POST['active_groups'] ?? []);
            foreach ($_POST['assign'] as $g_key => $slots) { foreach ($slots as $slot_id => $p_name) $wpdb->replace($wpdb->prefix.'whb_raid_groups', ['group_key' => $g_key, 'slot_id' => $slot_id, 'player_name' => sanitize_text_field(stripslashes($p_name))]); }
            echo "<div class='updated'><p>Raid Groups Saved.</p></div>";
        }
        if (isset($_POST['save_recruitment'])) {
            $wpdb->update($wpdb->prefix.'whb_recruitment', ['is_open' => 0], ['is_open' => 1]);
            if (isset($_POST['rec_open'])) { foreach ($_POST['rec_open'] as $id => $val) $wpdb->update($wpdb->prefix.'whb_recruitment', ['is_open' => 1], ['id' => intval($id)]); }
            echo "<div class='updated'><p>Recruitment Status Updated.</p></div>";
        }
    }

    private function tab_loot_themes() {
        $active_groups = (array) get_option('whb_active_groups', []); 
        $all_groups = $this->get_group_names();
        $managed_zones = $this->get_managed_zones();
        ?>
        
        <h2>Import RCLootCouncil JSON</h2>
        <form method="post" style="background:#fafafa; border:1px solid #ccc; padding:15px; border-radius:4px; margin-bottom:30px;">
            <textarea name="rc_json" style="width:100%; height:120px; font-family:monospace; margin-bottom:15px;"></textarea>
            <div style="display:flex; gap:15px; align-items:center;">
                <select name="rc_raid_group" required style="padding:5px;"><option value="">-- Assign to Raid Group --</option>
                    <?php foreach ($active_groups as $g_key): if(isset($all_groups[$g_key])): ?><option value="<?php echo esc_attr($g_key); ?>"><?php echo esc_html(stripslashes($all_groups[$g_key])); ?></option><?php endif; endforeach; ?>
                </select>
                <input type="submit" name="upload_rc_json" class="button button-primary" value="Process JSON Import">
            </div>
        </form>

        <hr style="margin:25px 0;">
        
        <h2>Customize Raid Group Display Names</h2>
        <form method="post" style="background:#fafafa; border:1px solid #ccc; padding:15px; border-radius:4px; margin-bottom:30px;">
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px;">
                <?php foreach ($all_groups as $key => $val): ?>
                    <div><label style="display:block; font-weight:bold; font-size:12px; margin-bottom:4px;"><?php echo esc_html($key); ?></label><input type="text" name="group_names[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(stripslashes($val)); ?>" style="width:100%;"></div>
                <?php endforeach; ?>
            </div>
            <input type="submit" name="whb_save_group_names" class="button button-primary" value="Save Group Names" style="margin-top:15px;">
        </form>

        <hr style="margin:25px 0;">

        <h2>Customize Zone Display Names & Visibility</h2>
        <form method="post" style="background:#fafafa; border:1px solid #ccc; padding:15px; border-radius:4px; margin-bottom:30px;">
            <table class="form-table" style="max-width: 600px; margin-top:0;">
                <thead>
                    <tr>
                        <th style="padding-bottom:10px; padding-top:0;">Database Name</th>
                        <th style="padding-bottom:10px; padding-top:0;">Display Name</th>
                        <th style="padding-bottom:10px; padding-top:0; text-align:center;">Visible?</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 0; foreach ($managed_zones as $raw => $data): ?>
                    <tr>
                        <td style="padding: 5px 10px 5px 0;"><strong><?php echo esc_html(stripslashes($raw)); ?></strong><input type="hidden" name="zone_original[<?php echo $i; ?>]" value="<?php echo esc_attr(stripslashes($raw)); ?>"></td>
                        <td style="padding: 5px 10px;"><input type="text" name="zone_display[<?php echo $i; ?>]" value="<?php echo esc_attr(stripslashes($data['display'])); ?>" style="width:100%;"></td>
                        <td style="padding: 5px 0; text-align:center;"><input type="checkbox" name="zone_visible[<?php echo $i; ?>]" value="1" <?php checked($data['visible'], 1); ?>></td>
                    </tr>
                    <?php $i++; endforeach; ?>
                </tbody>
            </table>
            <input type="submit" name="whb_save_zone_settings" class="button button-primary" value="Save Zone Settings" style="margin-top:15px;">
        </form>

        <hr style="margin:25px 0;">

        <h2>Legacy CSV Import</h2>
        <form method="post" enctype="multipart/form-data"><input type="file" name="csv" accept=".csv" required><input type="submit" name="upload_loot" class="button button-secondary" value="Process CSV"></form>
        
        <hr style="margin:25px 0;">
        
        <h2>Display Settings</h2>
        <form method="post">
            <table class="form-table">
                <tr><th>Theme</th><td><select name="whb_theme"><option value="dark" <?php selected(get_option('whb_theme'), 'dark'); ?>>Dark Mode</option><option value="light" <?php selected(get_option('whb_theme'), 'light'); ?>>Light Mode</option><option value="custom" <?php selected(get_option('whb_theme'), 'custom'); ?>>Custom Hex</option></select></td></tr>
                <tr><th>Custom Background</th><td><input type="color" name="whb_custom_bg" value="<?php echo esc_attr(get_option('whb_custom_bg','#120f26')); ?>"></td></tr>
                <tr><th>Custom Text Color</th><td><input type="color" name="whb_custom_text" value="<?php echo esc_attr(get_option('whb_custom_text','#e2d3a3')); ?>"></td></tr>
            </table>
            <input type="submit" name="whb_save_settings" class="button button-primary" value="Save Theme Settings">
        </form>
        <?php
    }

    private function tab_progression() {
        $raids = $this->get_raids(); $prog = (array) get_option('whb_progression_data', []); $vis = (array) get_option('whb_visible_raids', []);
        ?>
        <h2>Raid Progression</h2>
        <form method="post">
            <?php foreach ($raids as $k => $r): ?>
                <div style="border:1px solid #ddd; padding:10px; margin-bottom:10px; background:#fcfcfc;">
                    <strong><label><input type="checkbox" name="visible_raids[<?php echo $k; ?>]" <?php checked(in_array($k,$vis)); ?>> Show <?php echo esc_html($r['name']); ?></label></strong>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:5px; margin-top:10px;">
                        <?php foreach($r['bosses'] as $i => $b): ?><label style="font-size:12px;"><input type="checkbox" name="prog[<?php echo $k; ?>][]" value="<?php echo $i; ?>" <?php checked(isset($prog[$k]) && in_array($i,$prog[$k])); ?>> <?php echo esc_html($b); ?></label><?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <input type="submit" name="whb_save_progression" class="button button-primary" value="Save Progression">
        </form>
        <?php
    }

    private function tab_roster() {
        global $wpdb; $players = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}whb_roster ORDER BY player ASC"); if (!is_array($players)) $players = []; 
        ?>
        <h2>Manually Add Player</h2>
        <form method="post" style="background:#fafafa; border:1px solid #ccc; padding:15px; border-radius:4px; margin-bottom:30px; display:flex; flex-wrap:wrap; gap:15px; align-items:flex-end;">
            <?php wp_nonce_field('whb_manual_add_player_action'); ?>
            <div><label style="display:block; font-weight:bold; margin-bottom:5px;">Player Name</label><input type="text" name="new_player_name" required style="width:150px;"></div>
            <div><label style="display:block; font-weight:bold; margin-bottom:5px;">Class</label><select name="new_player_class" required><option value="">-- Select --</option><?php foreach(['druid','hunter','mage','paladin','priest','rogue','shaman','warlock','warrior'] as $cl): ?><option value="<?php echo esc_attr($cl); ?>"><?php echo ucfirst($cl); ?></option><?php endforeach; ?></select></div>
            <div><label style="display:block; font-weight:bold; margin-bottom:5px;">Role (Optional)</label><select name="new_player_role"><option value="">Unassigned</option><option value="tank">🛡️ Tank</option><option value="healer">💚 Healer</option><option value="melee">⚔️ Melee</option><option value="ranged">🔮 Ranged</option></select></div>
            <div><input type="submit" name="whb_manual_add_player" class="button button-primary" value="Add Player"></div>
        </form>
        <hr style="margin:25px 0;">
        <h2>JSON Roster Importer</h2>
        <form method="post" style="margin-bottom:30px;"><textarea name="roster_json" style="width:100%; height:80px; font-family:monospace;"></textarea><input type="submit" name="sync_roster_json" class="button button-secondary" value="Sync Roster from Addon String" style="margin-top:10px;"></form>
        <hr style="margin:25px 0;">
        <h2>Manual Roster Overrides</h2>
        <form method="post">
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Name</th><th>Role</th><th>Class</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($players)): ?><tr><td colspan="4">No players found.</td></tr><?php else: foreach ($players as $p): $delete_url = wp_nonce_url(admin_url('admin.php?page=whb-tracker&tab=roster&action=delete_player&player=' . urlencode(stripslashes($p->player))), 'delete_player_' . stripslashes($p->player)); ?>
                    <tr>
                        <td><strong><?php echo esc_html(stripslashes($p->player)); ?></strong></td>
                        <td><select name="roster[<?php echo esc_html(stripslashes($p->player)); ?>]"><option value="">Unassigned</option><option value="tank" <?php selected($p->role,'tank'); ?>>🛡️ Tank</option><option value="healer" <?php selected($p->role,'healer'); ?>>💚 Healer</option><option value="melee" <?php selected($p->role,'melee'); ?>>⚔️ Melee</option><option value="ranged" <?php selected($p->role,'ranged'); ?>>🔮 Ranged</option></select></td>
                        <td><select name="roster_class[<?php echo esc_html(stripslashes($p->player)); ?>]"><?php foreach(['druid','hunter','mage','paladin','priest','rogue','shaman','warlock','warrior'] as $cl): ?><option value="<?php echo esc_attr($cl); ?>" <?php selected($p->player_class,$cl); ?>><?php echo ucfirst($cl); ?></option><?php endforeach; ?></select></td>
                        <td><a href="<?php echo esc_url($delete_url); ?>" class="button button-small" style="color:#d63638; border-color:#d63638;" onclick="return confirm('Remove <?php echo esc_js(stripslashes($p->player)); ?> completely?');">Delete</a></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            <p><input type="submit" name="whb_save_roster" class="button button-primary" value="Update Manual Changes"></p>
        </form>
        <?php
    }

    private function tab_groups() {
        global $wpdb; $players = $wpdb->get_col("SELECT player FROM {$wpdb->prefix}whb_roster ORDER BY player ASC"); if (!is_array($players)) $players = []; 
        $active_groups = (array) get_option('whb_active_groups', []); $all_groups = $this->get_group_names();

        echo '<form method="post">';
        foreach ($all_groups as $key => $label) {
            $is_10m = (strpos($key, '10m') !== false); $num_tanks = $is_10m ? 2 : 3; $num_heals = $is_10m ? 2 : 6; $num_dps = $is_10m ? 6 : 16; $is_active = in_array($key, $active_groups);
            echo "<div style='border:1px solid #ccc; padding:15px; margin-bottom:15px; background:".($is_active ? '#fff' : '#eee')."'><h3><label><input type='checkbox' name='active_groups[]' value='$key' ".checked($is_active, true, false)."> Enable ".esc_html(stripslashes($label))."</label></h3>";
            if ($is_active) {
                echo "<div style='display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;'>";
                $this->render_group_slots($key, 'Tank', $num_tanks, $players); $this->render_group_slots($key, 'Healer', $num_heals, $players); $this->render_group_slots($key, 'DPS', $num_dps, $players);
                echo "</div>";
            }
            echo "</div>";
        }
        echo '<input type="submit" name="save_groups" class="button button-primary" value="Save Group Assignments"></form>';
    }

    private function render_group_slots($g_key, $role_label, $count, $players) {
        global $wpdb; echo "<div><strong>$role_label</strong><br>";
        for ($i = 1; $i <= $count; $i++) {
            $slot_id = "{$role_label}_$i"; $current = $wpdb->get_var($wpdb->prepare("SELECT player_name FROM {$wpdb->prefix}whb_raid_groups WHERE group_key = %s AND slot_id = %s", $g_key, $slot_id));
            echo "<select name='assign[$g_key][$slot_id]' style='width:100%; margin-bottom:5px;'><option value=''>-- Empty --</option>";
            foreach ($players as $p) {
                $p_clean = stripslashes($p);
                echo "<option value='".esc_attr($p_clean)."' ".selected(stripslashes($current), $p_clean, false).">".esc_html($p_clean)."</option>";
            }
            echo "</select>";
        }
        echo "</div>";
    }

    private function tab_recruitment() {
        global $wpdb; $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}whb_recruitment ORDER BY class_key ASC"); if (!is_array($data)) $data = []; 
        $classes = []; foreach ($data as $r) { $classes[$r->class_key][] = $r; }

        echo "<h2>Recruitment Toggles</h2><form method='post'><div style='display:grid; grid-template-columns: repeat(3, 1fr); gap:15px;'>";
        foreach ($classes as $cl => $specs) {
            echo "<div style='padding:10px; border:1px solid #ddd; background:#fff;'><h4><img src='".WHB_URL."includes/images/class-icons/$cl.png' style='width:18px; vertical-align:middle;'> ".ucfirst($cl)."</h4>";
            foreach ($specs as $s) echo "<label style='display:block; font-size:12px;'><input type='checkbox' name='rec_open[{$s->id}]' value='1' ".checked($s->is_open, 1, false)."> ".esc_html(stripslashes($s->spec_key))."</label>";
            echo "</div>";
        }
        echo "</div><input type='submit' name='save_recruitment' class='button button-primary' value='Update Live Recruitment' style='margin-top:20px;'></form>";
    }

    private function tab_loot_management() {
        global $wpdb; $loot_table = $wpdb->prefix . 'whb_loot';
        $search = isset($_GET['loot_search']) ? sanitize_text_field($_GET['loot_search']) : '';
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1; $per_page = 25; $offset = ($current_page - 1) * $per_page;
        
        $active_groups = (array) get_option('whb_active_groups', []); 
        $all_groups = $this->get_group_names(); 
        $managed_zones = $this->get_managed_zones();
        ?>
        <h2>Loot Management</h2>
        <form method="post" style="background:#fafafa; border:1px solid #ccc; padding:15px; border-radius:4px; margin-bottom:30px; display:flex; flex-wrap:wrap; gap:15px; align-items:flex-end;">
            <?php wp_nonce_field('whb_manual_add_loot_action'); ?>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">Date</label><input type="date" name="manual_loot_date" value="<?php echo current_time('Y-m-d'); ?>" required style="width:130px;"></div>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">Player</label><input type="text" name="manual_loot_player" required style="width:120px;"></div>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">Item Name</label><input type="text" name="manual_loot_item_name" required style="width:150px;"></div>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">WoWhead ID</label><input type="number" name="manual_loot_item_id" value="0" style="width:80px;"></div>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">Zone</label>
                <select name="manual_loot_zone" style="width:140px;"><option value="">-- Select --</option>
                    <?php foreach ($managed_zones as $raw => $data): if($data['visible']): ?>
                        <option value="<?php echo esc_attr(stripslashes($raw)); ?>"><?php echo esc_html(stripslashes($data['display'])); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div><label style="font-weight:bold; display:block; margin-bottom:5px;">Group</label>
                <select name="manual_loot_group" style="width:130px;"><option value="">-- None --</option>
                    <?php foreach ($active_groups as $g_key): if(isset($all_groups[$g_key])): ?>
                        <option value="<?php echo esc_attr(stripslashes($g_key)); ?>"><?php echo esc_html(stripslashes($all_groups[$g_key])); ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <div><input type="submit" name="whb_manual_add_loot" class="button button-primary" value="Add Item"></div>
        </form>
        <div style="background:#fff2f2; border:1px solid #d63638; padding:15px; border-radius:5px; margin-bottom:30px;">
            <h3 style="color:#d63638; margin-top:0;">Purge All Loot History</h3><form method="post" onsubmit="return confirm('Delete ALL?');"><?php wp_nonce_field('whb_purge_action'); ?><input type="submit" name="whb_purge_all_loot" class="button button-link-delete" value="Purge All Records" style="color:#d63638;"></form>
        </div>
        <hr>
        <form method="get" style="margin-bottom:20px; display:flex; gap:10px;"><input type="hidden" name="page" value="whb-tracker"><input type="hidden" name="tab" value="tools"><input type="text" name="loot_search" value="<?php echo esc_attr($search); ?>" placeholder="Search Player or Item..."><input type="submit" class="button" value="Search"><?php if($search): ?><a href="?page=whb-tracker&tab=tools" class="button">Clear Search</a><?php endif; ?></form>
        <?php 
        if ($search) {
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $loot_table WHERE player LIKE %s OR item_name LIKE %s", "%$search%", "%$search%"));
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $loot_table WHERE player LIKE %s OR item_name LIKE %s ORDER BY loot_date DESC, id DESC LIMIT %d OFFSET %d", "%$search%", "%$search%", $per_page, $offset));
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $loot_table");
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $loot_table ORDER BY loot_date DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset));
        }
        $total_pages = ceil($total_items / $per_page);
        $pagination_args = ['base' => add_query_arg('paged', '%#%'), 'format' => '', 'prev_text' => '&laquo;', 'next_text' => '&raquo;', 'total' => $total_pages, 'current' => $current_page];
        if ($search) $pagination_args['base'] = add_query_arg(['paged' => '%#%', 'loot_search' => urlencode($search)]);
        ?>
        <div class="tablenav top" style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;"><div class="tablenav-pages"><span class="displaying-num" style="margin-right:15px;"><?php echo $total_items; ?> items</span><?php echo paginate_links($pagination_args); ?></div></div>
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th style="width:110px;">Date</th><th style="width:130px;">Player</th><th>Item</th><th style="width:80px;">WoWhead ID</th><th style="width:130px;">Zone</th><th style="width:120px;">Group</th><th style="width:130px;">Action</th></tr></thead>
            <tbody>
                <?php if ($results): foreach ($results as $row): 
                    $row_zone = stripslashes($row->zone);
                ?>
                    <tr data-id="<?php echo $row->id; ?>">
                        <td style="vertical-align:middle;"><?php echo esc_html($row->loot_date); ?></td>
                        <td style="vertical-align:middle;"><input type="text" class="edit-player" value="<?php echo esc_attr(stripslashes($row->player)); ?>" style="width:100%; max-width:140px;"></td>
                        <td style="vertical-align:middle;"><?php echo esc_html(stripslashes($row->item_name)); ?></td>
                        <td style="vertical-align:middle;"><input type="number" class="edit-item-id" value="<?php echo esc_attr($row->item_id); ?>" style="width:100%; max-width:80px;"></td>
                        <td style="vertical-align:middle;">
                            <select class="edit-zone" style="width:100%;">
                                <option value="">--</option>
                                <?php foreach ($managed_zones as $raw => $data): 
                                    if (!$data['visible'] && $row_zone !== $raw) continue;
                                ?>
                                    <option value="<?php echo esc_attr(stripslashes($raw)); ?>" <?php selected($row_zone, $raw); ?>><?php echo esc_html(stripslashes($data['display'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td style="vertical-align:middle;"><select class="edit-group" style="width:100%;"><option value="">--</option><?php foreach ($active_groups as $g_key): if(isset($all_groups[$g_key])): ?><option value="<?php echo esc_attr(stripslashes($g_key)); ?>" <?php selected(stripslashes($row->raid_group), stripslashes($g_key)); ?>><?php echo esc_html(stripslashes($all_groups[$g_key])); ?></option><?php endif; endforeach; ?></select></td>
                        <td style="vertical-align:middle;">
                            <button type="button" class="button button-small whb-ajax-update-btn">Save</button><span class="whb-status-icon" style="color:#46b450; display:none; font-weight:bold;">✔</span>
                            <form method="post" style="display:inline-block; margin-left:5px;" onsubmit="return confirm('Delete this entry?');"><?php wp_nonce_field('whb_delete_item_action'); ?><input type="hidden" name="delete_id" value="<?php echo $row->id; ?>"><input type="submit" name="whb_delete_item" class="button button-link-delete" value="Del" style="color:#d63638; padding:0; background:none; border:none; font-weight:normal; text-decoration:underline;"></form>
                        </td>
                    </tr>
                <?php endforeach; else: ?><tr><td colspan="7">No loot records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <div class="tablenav bottom" style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;"><div class="tablenav-pages"><span class="displaying-num" style="margin-right:15px;"><?php echo $total_items; ?> items</span><?php echo paginate_links($pagination_args); ?></div></div>
        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('whb_update_loot_action'); ?>';
            $('.whb-ajax-update-btn').on('click', function(e) {
                e.preventDefault(); var $btn = $(this); var $row = $btn.closest('tr'); var $icon = $row.find('.whb-status-icon');
                $btn.prop('disabled', true).text('...');
                $.post(ajaxurl, { action: 'whb_update_loot_entry', security: nonce, edit_id: $row.data('id'), new_player: $row.find('.edit-player').val(), new_item_id: $row.find('.edit-item-id').val(), new_zone: $row.find('.edit-zone').val(), new_group: $row.find('.edit-group').val() }, function(res) {
                    $btn.prop('disabled', false).text('Save'); if(res.success) { $icon.fadeIn('fast').delay(1500).fadeOut('slow'); } else { alert('Error: ' + res.data); }
                }).fail(function() { $btn.prop('disabled', false).text('Save'); alert('Connection error.'); });
            });
        });
        </script>
        <?php
    }

    private function tab_builder() {
        $webhook = get_option('whb_discord_webhook', ''); 
        $app_status = get_option('whb_app_status', 'open');
        
        $webhook_status = get_option('whb_discord_webhook_status', '');
        $msg_applied = get_option('whb_msg_applied', "📣 **{Applicant}** just submitted an application!");
        $msg_approved = get_option('whb_msg_approved', "✅ **{Applicant}**'s application has been approved! Welcome!");
        $msg_denied = get_option('whb_msg_denied', "❌ **{Applicant}**'s application has been denied.");
        ?>
        
        <h2>Application Form Builder</h2>
        <p class="description">Use the shortcode <strong>[whb-apply]</strong> on any page to display this form.</p>
        
        <form method="post" id="whb-builder-form">
            <div style="background:#f1f1f1; padding:15px; border-left:4px solid #a335ee; margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap;">
                <div style="flex:1; min-width:250px;">
                    <strong>Application Form Status:</strong><br>
                    <select name="whb_app_status" style="width:100%; margin-top:5px; padding:5px;">
                        <option value="open" <?php selected($app_status, 'open'); ?>>🟢 Open (Accepting Applications)</option>
                        <option value="closed" <?php selected($app_status, 'closed'); ?>>🔴 Closed (Hide Form)</option>
                    </select>
                </div>
                <div style="flex:2; min-width:300px;">
                    <strong>Private Officer Webhook URL:</strong><br>
                    <p style="font-size:12px; color:#666; margin:0 0 5px 0;">This channel receives the FULL application dump + Approve/Deny action buttons.</p>
                    <input type="url" name="whb_discord_webhook" value="<?php echo esc_url($webhook); ?>" style="width:100%; padding:5px;" placeholder="https://discord.com/api/webhooks/...">
                </div>
            </div>

            <div style="background:#eef6ff; padding:15px; border-left:4px solid #0070DE; margin-bottom:20px;">
                <h3 style="margin-top:0;">Public Announcement Webhook (Status Updates)</h3>
                <p style="font-size:13px;">This channel automatically receives a short, formatted message when an application is submitted, approved, or denied. <br>
                <strong>Tip:</strong> Use <code>{Applicant}</code> to inject the player's name, or copy the exact <code>{whb_f_id}</code> from the form builder below to inject specific answers!</p>
                
                <div style="margin-bottom:15px;">
                    <strong>Announcement Webhook URL:</strong><br>
                    <input type="url" name="whb_discord_webhook_status" value="<?php echo esc_url($webhook_status); ?>" style="width:100%; max-width:600px; padding:5px;">
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:15px;">
                    <div>
                        <strong style="color:#0070DE;">Applied Template:</strong>
                        <textarea name="whb_msg_applied" rows="3" style="width:100%; padding:5px;"><?php echo esc_textarea($msg_applied); ?></textarea>
                    </div>
                    <div>
                        <strong style="color:#46b450;">Approved Template:</strong>
                        <textarea name="whb_msg_approved" rows="3" style="width:100%; padding:5px;"><?php echo esc_textarea($msg_approved); ?></textarea>
                    </div>
                    <div>
                        <strong style="color:#d63638;">Denied Template:</strong>
                        <textarea name="whb_msg_denied" rows="3" style="width:100%; padding:5px;"><?php echo esc_textarea($msg_denied); ?></textarea>
                    </div>
                </div>
            </div>

            <div style="margin-bottom:20px;"><select id="add-field-type" style="padding:5px;"><option value="">+ Add a New Question</option><option value="text">Single Line Text</option><option value="textarea">Paragraph Text</option><option value="select">Dropdown (Select One)</option><option value="radio">Radio Buttons (Select One)</option><option value="checkbox">Checkboxes (Select Multiple)</option></select></div>
            <div id="whb-fields-container" style="background:#fff; border:1px solid #ddd; padding:20px; border-radius:4px;"></div>
            <textarea name="whb_form_schema" id="whb_form_schema_output" style="display:none;"></textarea>
            <p style="margin-top:20px;"><input type="submit" name="whb_save_form" class="button button-primary button-large" value="Save Application Form Settings"></p>
        </form>
        <script>
        jQuery(document).ready(function($) {
            let fields = <?php echo get_option('whb_form_schema') ?: '[]'; ?>; if (!Array.isArray(fields)) fields = [];
            function render() {
                $('#whb-fields-container').empty(); if (fields.length === 0) $('#whb-fields-container').append('<p style="color:#777; font-style:italic;">No questions added yet.</p>');
                fields.forEach((f, i) => {
                    if (typeof f.required === 'undefined') f.required = true; 
                    let typeLabel = f.type === 'text' ? 'Single Line Text' : (f.type === 'textarea' ? 'Paragraph' : (f.type === 'radio' ? 'Radio Buttons' : (f.type === 'select' ? 'Dropdown' : 'Checkboxes')));
                    let html = `<div style="border:1px solid #ccc; padding:15px; margin-bottom:15px; background:#fafafa; border-radius:4px; display:flex; gap:15px; align-items:flex-start;"><div style="flex:1;">`;
                    html += `<div style="font-size:11px; text-transform:uppercase; color:#a335ee; font-weight:bold; margin-bottom:5px;">Type: ${typeLabel} <span style="color:#666; margin-left:10px;">Variable: <code style="background:#e0e0e0; padding:2px 4px; border-radius:3px; color:#333;">{${f.id}}</code></span></div>`;
                    html += `<label style="display:block; font-weight:bold; margin-bottom:10px;">Question Label:<br><input type="text" class="f-label" data-idx="${i}" value="${f.label}" style="width:100%; max-width:500px;"></label>`;
                    html += `<label style="display:block; margin-bottom:10px; font-size:13px;">Description (Optional):<br><input type="text" class="f-desc" data-idx="${i}" value="${f.description || ''}" style="width:100%; max-width:500px;"></label>`;
                    if (f.type === 'radio' || f.type === 'checkbox' || f.type === 'select') {
                        html += `<label style="display:block; font-weight:bold;">Options (comma separated):<br><input type="text" class="f-options" data-idx="${i}" value="${f.options || ''}" style="width:100%; max-width:500px;"></label>`;
                    }
                    html += `<label style="display:inline-flex; align-items:center; cursor:pointer; margin-top:10px;"><input type="checkbox" class="f-req" data-idx="${i}" ${f.required ? 'checked' : ''}><span style="margin-left:5px; font-size:12px; color:#555;">Required Field</span></label>`;
                    html += `</div><div style="display:flex; flex-direction:column; gap:5px; padding-top:20px;"><button type="button" class="button btn-up" data-idx="${i}">▲</button><button type="button" class="button btn-down" data-idx="${i}">▼</button><button type="button" class="button button-link-delete btn-del" data-idx="${i}" style="color:#d63638;">Delete</button></div></div>`;
                    $('#whb-fields-container').append(html);
                });
                $('#whb_form_schema_output').val(JSON.stringify(fields));
            }
            $('#add-field-type').change(function() { let t = $(this).val(); if(!t) return; fields.push({ id: 'whb_f_' + Date.now(), type: t, label: 'New Question', description: '', options: '', required: true }); $(this).val(''); render(); });
            $(document).on('input', '.f-label', function() { fields[$(this).data('idx')].label = $(this).val(); $('#whb_form_schema_output').val(JSON.stringify(fields)); });
            $(document).on('input', '.f-desc', function() { fields[$(this).data('idx')].description = $(this).val(); $('#whb_form_schema_output').val(JSON.stringify(fields)); });
            $(document).on('input', '.f-options', function() { fields[$(this).data('idx')].options = $(this).val(); $('#whb_form_schema_output').val(JSON.stringify(fields)); });
            $(document).on('change', '.f-req', function() { fields[$(this).data('idx')].required = $(this).is(':checked'); $('#whb_form_schema_output').val(JSON.stringify(fields)); });
            $(document).on('click', '.btn-up', function() { let i = $(this).data('idx'); if(i===0) return; [fields[i-1], fields[i]] = [fields[i], fields[i-1]]; render(); });
            $(document).on('click', '.btn-down', function() { let i = $(this).data('idx'); if(i===fields.length-1) return; [fields[i], fields[i+1]] = [fields[i+1], fields[i]]; render(); });
            $(document).on('click', '.btn-del', function() { if(confirm('Remove this question?')) { fields.splice($(this).data('idx'), 1); render(); } });
            render();
        });
        </script>
        <?php
    }

    private function tab_applications() {
        global $wpdb; $apps_table = $wpdb->prefix . 'whb_applications';
        $wpdb->query("CREATE TABLE IF NOT EXISTS $apps_table ( id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, applicant VARCHAR(255), app_data LONGTEXT )");
        $results = $wpdb->get_results("SELECT * FROM $apps_table ORDER BY created_at DESC"); if (!is_array($results)) $results = [];
        ?>
        <h2>Submitted Applications</h2>
        <table class="wp-list-table widefat fixed striped" style="margin-top:15px;">
            <thead><tr><th style="width:150px;">Date Received</th><th style="width:200px;">Applicant</th><th>Application Details</th><th style="width:140px;">Status & Actions</th></tr></thead>
            <tbody>
                <?php if (empty($results)): ?><tr><td colspan="4">No applications have been submitted yet.</td></tr><?php else: foreach ($results as $row): 
                    $data = json_decode($row->app_data, true); 
                    $status = isset($data['_status']) ? $data['_status'] : 'pending';
                    $delete_url = wp_nonce_url(admin_url('admin.php?page=whb-tracker&tab=apps&action=delete_app&app_id=' . $row->id), 'delete_app_' . $row->id); 
                    $approve_url = wp_nonce_url(admin_url('admin.php?page=whb-tracker&tab=apps&action=approve_app&app_id=' . $row->id), 'status_app_' . $row->id); 
                    $deny_url = wp_nonce_url(admin_url('admin.php?page=whb-tracker&tab=apps&action=deny_app&app_id=' . $row->id), 'status_app_' . $row->id); 
                    $pending_url = wp_nonce_url(admin_url('admin.php?page=whb-tracker&tab=apps&action=pending_app&app_id=' . $row->id), 'status_app_' . $row->id); 
                    
                    $status_badge = "<span style='display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:bold; background:#e0e0e0; color:#333;'>Pending</span>";
                    if ($status === 'approved') $status_badge = "<span style='display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:bold; background:#46b450; color:#fff;'>Approved</span>";
                    if ($status === 'denied') $status_badge = "<span style='display:inline-block; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:bold; background:#d63638; color:#fff;'>Denied</span>";
                ?>
                    <tr>
                        <td style="vertical-align:top; padding-top:12px;"><strong><?php echo date('M j, Y g:i A', strtotime($row->created_at)); ?></strong></td>
                        <td style="vertical-align:top; padding-top:12px; color:#a335ee; font-weight:bold; font-size:14px;">
                            <?php echo esc_html(stripslashes($row->applicant)); ?><br>
                            <div style="margin-top:5px;"><?php echo $status_badge; ?></div>
                        </td>
                        <td style="padding:12px;">
                            <div style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px;">
                                <?php if(is_array($data)): foreach($data as $q => $a): 
                                    if ($q === '_status' || $q === '_action_token') continue;
                                    $val = is_array($a) ? implode(', ', $a) : $a; 
                                ?>
                                    <div style="margin-bottom:10px;"><strong style="color:#444; display:block; font-size:12px; border-bottom:1px solid #eee; padding-bottom:3px; margin-bottom:5px;"><?php echo esc_html(stripslashes($q)); ?></strong><span style="font-size:14px; white-space:pre-wrap;"><?php echo esc_html(stripslashes($val)); ?></span></div>
                                <?php endforeach; endif; ?>
                            </div>
                        </td>
                        <td style="vertical-align:top; padding-top:12px;">
                            <a href="<?php echo esc_url($approve_url); ?>" class="button button-small" style="color:#46b450; border-color:#46b450; display:block; text-align:center; margin-bottom:5px;" onclick="return confirm('Approve this application and notify Discord?');">Approve</a>
                            <a href="<?php echo esc_url($deny_url); ?>" class="button button-small" style="color:#d63638; border-color:#d63638; display:block; text-align:center; margin-bottom:5px;" onclick="return confirm('Deny this application and notify Discord?');">Deny</a>
                            <a href="<?php echo esc_url($pending_url); ?>" class="button button-small" style="color:#666; border-color:#999; display:block; text-align:center; margin-bottom:15px;" onclick="return confirm('Set status back to Pending?');">Set Pending</a>
                            <hr style="margin:10px 0;">
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-small" style="display:block; text-align:center;" onclick="return confirm('Delete this application permanently?');">Delete App</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function tab_credits() {
        ?>
        <div style="background: #1e1e1e; color: #f3f4f6; padding: 40px; border-radius: 8px; border: 1px solid #333; box-shadow: 0 10px 30px rgba(0,0,0,0.5); margin-top: 20px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #a335ee; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; text-shadow: 0 2px 4px rgba(0,0,0,0.8);">Designed with Love for Waffle House Brawlers</h1>
                <div style="display: inline-flex; gap: 20px; font-size: 16px; background: rgba(0,0,0,0.3); padding: 10px 20px; border-radius: 6px; border: 1px solid #444;">
                    <span>Guild: <strong style="color: #fff;">Waffle House Brawlers</strong></span>
                    <span style="color: #555;">|</span>
                    <span>Server: <strong style="color: #fff;">Nightslayer</strong></span>
                    <span style="color: #555;">|</span>
                    <span>Faction: <strong style="color: #ff3333; text-shadow: 0 0 5px rgba(255,51,51,0.5);">Horde</strong></span>
                </div>
            </div>
            <hr style="border: 0; height: 1px; background: linear-gradient(90deg, transparent, #555, transparent); margin: 30px 0;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start;">
                <div style="background: rgba(255,255,255,0.03); padding: 25px; border-radius: 6px; border-left: 4px solid #a335ee;">
                    <h3 style="color: #a335ee; margin-top: 0; font-size: 20px; border-bottom: 1px solid #444; padding-bottom: 10px;">The Waffle House Team</h3>
                    <ul style="list-style: none; padding: 0; margin: 0; font-size: 16px; line-height: 2;">
                        <li><strong style="color: #aaa; display: inline-block; width: 120px;">Developer:</strong> <span style="color: #fff; font-weight: bold;">Maliettv</span></li>
                        <li><strong style="color: #aaa; display: inline-block; width: 120px;">Features:</strong> <span style="color: #fff; font-weight: bold;">Treechopper & Bowphades</span></li>
                        <li><strong style="color: #aaa; display: inline-block; width: 120px;">Financing:</strong> <span style="color: #fff; font-weight: bold;">Fartjars</span></li>
                        <li><strong style="color: #aaa; display: inline-block; width: 120px;">Guild Rug:</strong> <span style="color: #fff; font-weight: bold;">Bigpingus</span></li>
                        <li><strong style="color: #aaa; display: inline-block; width: 120px;">Taskmaster:</strong> <span style="color: #fff; font-weight: bold;">Realpower</span></li>
                    </ul>
                </div>
                <div>
                    <h3 style="color: #a335ee; margin-top: 0; font-size: 20px;">Open Source & Integrations</h3>
                    <p style="font-size: 15px; color: #ccc; line-height: 1.6;">The source for this plugin is available on GitHub and other guilds are completely welcome to use it to power their own web rosters.</p>
                    <p><a href="https://github.com/maliettv/WHBLootTrackerWeb" target="_blank" style="display: inline-block; background: #24292e; color: #fff; padding: 10px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; border: 1px solid #444; transition: border-color 0.2s;"><span style="margin-right: 8px;">📦</span> View Project on GitHub</a></p>
                    <div style="margin-top: 25px; background: rgba(0,0,0,0.4); padding: 15px; border-radius: 6px; border: 1px solid #333; font-size: 14px; color: #ddd; line-height: 1.5;">
                        <strong style="color: #f58cba; display: block; margin-bottom: 5px;">Curseforge Integration Required:</strong>
                        You'll need to download both <strong>WHB Loot Tracker</strong> and <strong>WHB Guild Sync</strong> off Curseforge to easily sync data and use all the web features.
                    </div>
                </div>
            </div>
            <div style="margin-top: 40px; text-align: center; padding-top: 30px; border-top: 1px dashed #444;">
                <p style="color: #aaa; font-size: 15px; margin-bottom: 15px;">If you enjoy using this plugin and want to support continued development, consider buying Justin a coffee!</p>
                <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=justin@coastalpc.ca&item_name=WHB+Loot+Tracker+Plugin+Support&currency_code=USD" target="_blank" style="display: inline-block; background: #0070ba; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; transition: background 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.4);">☕ Donate via PayPal</a>
            </div>
        </div>
        <?php
    }
}