<?php
if (!defined('ABSPATH')) exit;

class WHB_Shortcodes {

    public function __construct() {
        add_shortcode('loot_viewer', array($this, 'render_loot_viewer'));
        add_shortcode('raid_progression', array($this, 'render_progression'));
        add_shortcode('whb_recruitment', array($this, 'render_recruitment'));
        add_shortcode('whb_10m_roster', array($this, 'render_10m_roster'));
        add_shortcode('whb_25m_roster', array($this, 'render_25m_roster'));
        add_shortcode('whb-apply', array($this, 'render_apply_form'));
    }

    private function get_raids_data() {
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

    private function get_styles() {
        $theme = get_option('whb_theme', 'dark');
        if ($theme === 'light') return ['bg' => '#ffffff', 'text' => '#1a202c', 'sec' => '#f7fafc', 'border' => '#e2e8f0', 'shadow' => 'rgba(0,0,0,0.1)'];
        if ($theme === 'dark') return ['bg' => '#1a1a1a', 'text' => '#f3f4f6', 'sec' => '#222222', 'border' => '#333333', 'shadow' => 'rgba(0,0,0,0.3)'];
        return ['bg' => get_option('whb_custom_bg', '#1a1a1a'), 'text' => get_option('whb_custom_text', '#ffffff'), 'sec' => 'rgba(128, 128, 128, 0.1)', 'border' => 'rgba(128, 128, 128, 0.2)', 'shadow' => 'rgba(0,0,0,0.3)'];
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

    public function render_apply_form() {
        global $wpdb; 
        $s = $this->get_styles();
        $status = get_option('whb_app_status', 'open');
        $schema = json_decode(get_option('whb_form_schema', '[]'), true);
        
        if ($status === 'closed' || empty($schema)) {
            ob_start();
            ?>
            <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:30px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>; max-width:800px; margin:0 auto; text-align:center;">
                <h2 style="margin-top:0; color:#a335ee; text-transform:uppercase; letter-spacing:1px;">Applications Closed</h2>
                <p style="font-size:16px; margin-top:15px;">The Waffle House Brawlers are not currently accepting new applications. Please check back later or reach out in Discord!</p>
            </div>
            <?php
            return ob_get_clean();
        }

        $apps_table = $wpdb->prefix . 'whb_applications';
        $message = '';

        if (isset($_POST['whb_submit_app']) && wp_verify_nonce($_POST['whb_app_nonce'], 'submit_whb_app')) {
            $wpdb->query("CREATE TABLE IF NOT EXISTS $apps_table ( id INT AUTO_INCREMENT PRIMARY KEY, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, applicant VARCHAR(255), app_data LONGTEXT )");

            $answers = [];
            $applicant_name = 'Unknown';

            foreach ($schema as $f) {
                $field_id = $f['id'];
                $label = sanitize_text_field(stripslashes($f['label']));
                if (isset($_POST[$field_id])) {
                    if (is_array($_POST[$field_id])) {
                        $answers[$label] = array_map('sanitize_text_field', $_POST[$field_id]);
                    } else {
                        $answers[$label] = sanitize_textarea_field(stripslashes($_POST[$field_id]));
                    }
                    if ($applicant_name === 'Unknown' && $f['type'] === 'text') {
                        $applicant_name = sanitize_text_field(stripslashes($_POST[$field_id]));
                    }
                }
            }

            $wpdb->insert($apps_table, ['applicant' => $applicant_name, 'app_data' => '']);
            $app_id = $wpdb->insert_id;

            $action_token = bin2hex(random_bytes(16));
            $answers['_status'] = 'pending';
            $answers['_action_token'] = $action_token;

            $wpdb->update($apps_table, ['app_data' => wp_json_encode($answers)], ['id' => $app_id]);

            $approve_url = admin_url('admin-ajax.php?action=whb_app_action&app_id=' . $app_id . '&token=' . $action_token . '&set_status=approved');
            $deny_url    = admin_url('admin-ajax.php?action=whb_app_action&app_id=' . $app_id . '&token=' . $action_token . '&set_status=denied');

            $webhook_url = get_option('whb_discord_webhook');
            if (!empty($webhook_url)) {
                $fields = [];
                foreach ($answers as $q => $a) {
                    if ($q === '_status' || $q === '_action_token') continue;
                    $val = is_array($a) ? implode(', ', $a) : $a;
                    $fields[] = ["name" => $q, "value" => !empty($val) ? $val : "*(Left blank)*", "inline" => false];
                }
                $embed = [
                    "title" => "📝 New Guild Application: " . $applicant_name, 
                    // FIX: Changed esc_url to esc_url_raw here to prevent HTML encoding of ampersands
                    "description" => "**Officer Actions (No Login Required):**\n🟢 [**APPROVE THIS APPLICATION**](" . esc_url_raw($approve_url) . ")\n🔴 [**DENY THIS APPLICATION**](" . esc_url_raw($deny_url) . ")\n---",
                    "color" => hexdec("a335ee"), 
                    "fields" => $fields, 
                    "footer" => ["text" => "Waffle House Brawlers Recruitment"]
                ];
                wp_remote_post($webhook_url, ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['embeds' => [$embed]])]);
            }

            $status_url = get_option('whb_discord_webhook_status');
            $applied_template = get_option('whb_msg_applied');
            if (!empty($status_url) && !empty($applied_template)) {
                $msg = $this->format_status_message($applied_template, $applicant_name, $answers);
                $embed = [ "description" => $msg, "color" => hexdec("a335ee") ];
                wp_remote_post($status_url, ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['embeds' => [$embed]])]);
            }

            $message = "<div style='background:#46b450; color:#fff; padding:15px; border-radius:6px; margin-bottom:20px; text-align:center; font-weight:bold;'>Thank you! Your application has been submitted to the officers.</div>";
        }

        ob_start(); 
        echo $message;
        ?>
        <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:25px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>; max-width:800px; margin:0 auto;">
            <h2 style="margin-top:0; border-bottom:1px solid <?php echo $s['border']; ?>; padding-bottom:15px; text-align:center; color:#a335ee; text-transform:uppercase; letter-spacing:1px;">Guild Application</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('submit_whb_app', 'whb_app_nonce'); ?>
                <?php foreach ($schema as $f): 
                    $id = esc_attr($f['id']); 
                    $label = esc_html(stripslashes($f['label'])); 
                    $desc = isset($f['description']) ? esc_html(stripslashes($f['description'])) : '';
                    $is_required = !isset($f['required']) || filter_var($f['required'], FILTER_VALIDATE_BOOLEAN);
                    $req_attr = $is_required ? 'required' : ''; 
                    $asterisk = $is_required ? '<span style="color:#ff4d4d; margin-left:3px;" title="Required">*</span>' : '';
                ?>
                    <div style="margin-bottom: 20px;">
                        <label style="display:block; font-weight:bold; margin-bottom:<?php echo empty($desc) ? '8px' : '2px'; ?>;">
                            <?php echo $label . $asterisk; ?>
                        </label>
                        <?php if (!empty($desc)): ?>
                            <div style="font-size:12px; opacity:0.8; margin-bottom:8px; line-height:1.4;"><?php echo $desc; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($f['type'] === 'text'): ?>
                            <input type="text" name="<?php echo $id; ?>" <?php echo $req_attr; ?> style="width:100%; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                        <?php elseif ($f['type'] === 'textarea'): ?>
                            <textarea name="<?php echo $id; ?>" <?php echo $req_attr; ?> rows="4" style="width:100%; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;"></textarea>
                        <?php elseif ($f['type'] === 'select'): $opts = array_map('trim', explode(',', $f['options'])); ?>
                            <select name="<?php echo $id; ?>" <?php echo $req_attr; ?> style="width:100%; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                                <option value="">-- Please Select --</option>
                                <?php foreach ($opts as $opt): if (empty($opt)) continue; ?>
                                    <option value="<?php echo esc_attr(stripslashes($opt)); ?>"><?php echo esc_html(stripslashes($opt)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'radio'): $opts = array_map('trim', explode(',', $f['options'])); foreach ($opts as $opt): if (empty($opt)) continue; ?>
                            <label style="display:block; margin-bottom:5px; cursor:pointer;"><input type="radio" name="<?php echo $id; ?>" value="<?php echo esc_attr(stripslashes($opt)); ?>" <?php echo $req_attr; ?>> <?php echo esc_html(stripslashes($opt)); ?></label>
                        <?php endforeach; endif; ?>
                        <?php if ($f['type'] === 'checkbox'): $opts = array_map('trim', explode(',', $f['options'])); foreach ($opts as $opt): if (empty($opt)) continue; ?>
                            <label style="display:block; margin-bottom:5px; cursor:pointer;"><input type="checkbox" name="<?php echo $id; ?>[]" value="<?php echo esc_attr(stripslashes($opt)); ?>"> <?php echo esc_html(stripslashes($opt)); ?></label>
                        <?php endforeach; endif; ?>
                    </div>
                <?php endforeach; ?>
                <div style="margin-top: 30px; text-align:center;"><button type="submit" name="whb_submit_app" style="background:#a335ee; color:#fff; border:none; padding:12px 30px; font-size:16px; font-weight:bold; border-radius:4px; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.3); transition:background 0.2s;">Submit Application</button></div>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    public function render_loot_viewer() {
        $s = $this->get_styles();
        $all_groups = $this->get_group_names(); 
        $active_groups = get_option('whb_active_groups', []);
        $managed_zones = $this->get_managed_zones();

        ob_start(); ?>
        <style>
            .whb-loot-filters { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
            .whb-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 4px; }
            .whb-table-wrapper table { min-width: 600px; } 
            @media (max-width: 600px) { .whb-loot-filters input, .whb-loot-filters select { flex: 1 1 100% !important; } }
        </style>
        <div id="whb-loot-app" style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:20px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>;">
            <div class="whb-loot-filters">
                <input type="text" id="whb-search" placeholder="Search Player or Item..." style="flex:2; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                <select id="whb-zone" style="flex:1; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                    <option value="">All Zones</option>
                    <?php 
                    foreach ($managed_zones as $raw => $data) {
                        if ($data['visible']) {
                            echo "<option value='".esc_attr($raw)."'>".esc_html(stripslashes($data['display']))."</option>";
                        }
                    }
                    ?>
                </select>
                <select id="whb-group" style="flex:1; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                    <option value="">All Groups</option>
                    <?php 
                    if (is_array($active_groups)) { 
                        foreach($active_groups as $g_key) { 
                            if(isset($all_groups[$g_key])) { 
                                echo "<option value='".esc_attr($g_key)."'>".esc_html(stripslashes($all_groups[$g_key]))."</option>"; 
                            } 
                        } 
                    } 
                    ?>
                </select>
            </div>
            <div class="whb-table-wrapper">
                <table style="color:#000000; width:100%; border-collapse:collapse; text-align:left;">
                    <thead style="background:<?php echo $s['sec']; ?>; border-bottom:2px solid <?php echo $s['border']; ?>;">
                        <tr><th style="padding:12px; white-space:nowrap;">Date</th><th style="padding:12px; white-space:nowrap;">Player</th><th style="padding:12px; white-space:nowrap;">Item</th><th style="padding:12px; white-space:nowrap;">Zone</th><th style="padding:12px; white-space:nowrap;">Group</th></tr>
                    </thead>
                    <tbody id="whb-results">
                        <tr><td colspan="5" style="text-align:center; padding:30px;">Loading loot database...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="whb-pagination" style="margin-top:15px; display:flex; justify-content:center;"></div>
        </div>

        <script>
        (function($) {
            function loadLoot(page = 1) {
                $.ajax({ url: '<?php echo admin_url('admin-ajax.php'); ?>', type: 'GET', data: { action: 'whb_get_loot_frontend', player: $('#whb-search').val(), zone: $('#whb-zone').val(), group: $('#whb-group').val(), paged: page }, success: function(res) { $('#whb-results').html(res.rows); $('#whb-pagination').html(res.pagination); } });
            }
            $(document).on('input', '#whb-search', function() { loadLoot(1); }); $(document).on('change', '#whb-zone', function() { loadLoot(1); }); $(document).on('change', '#whb-group', function() { loadLoot(1); }); $(document).on('click', '#whb-pagination button', function() { loadLoot($(this).data('page')); }); $(document).ready(function() { loadLoot(1); });
        })(jQuery);
        </script>
        <?php return ob_get_clean();
    }

    public function render_progression() {
        $raids = $this->get_raids_data(); 
        $prog = (array) get_option('whb_progression_data', []); 
        $visible = (array) get_option('whb_visible_raids', []); 
        $s = $this->get_styles();
        
        if (empty($visible)) return "";
        
        ob_start(); ?>
        <style>
            .whb-prog-wrapper { position: relative; cursor: help; background: rgba(0,0,0,0.1); padding: 12px; border-radius: 6px; border: 1px solid <?php echo $s['border']; ?>; margin-bottom: 12px; transition: background 0.2s; }
            .whb-prog-wrapper:hover { background: rgba(0,0,0,0.2); }
            .whb-boss-tooltip { visibility: hidden; opacity: 0; position: absolute; bottom: 115%; left: 50%; transform: translateX(-50%); background: #111; color: #fff; padding: 15px; border-radius: 6px; border: 1px solid #a335ee; z-index: 1000; min-width: 220px; box-shadow: 0 10px 25px rgba(0,0,0,0.6); transition: opacity 0.3s, transform 0.3s; }
            .whb-prog-wrapper:hover .whb-boss-tooltip { visibility: visible; opacity: 1; transform: translateX(-50%) translateY(-5px); }
            @media (max-width: 600px) { .whb-boss-tooltip { min-width: 200px; padding: 10px; font-size: 11px; } }
        </style>
        <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:15px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>;">
            <h3 style="margin-top:0; border-bottom:1px solid <?php echo $s['border']; ?>; padding-bottom:10px; text-align:center; text-transform:uppercase; letter-spacing:1px; font-size:16px;">Raid Progression</h3>
            <div class="whb-progression-list" style="margin-top: 15px;">
                <?php foreach ($visible as $rk): 
                    if(!isset($raids[$rk])) continue;
                    $r = $raids[$rk]; 
                    $killed = isset($prog[$rk]) ? (array) $prog[$rk] : []; 
                    $percent = round((count($killed) / count($r['bosses'])) * 100);
                ?>
                <div class="whb-prog-wrapper">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px;">
                        <strong style="color:<?php echo $s['text']; ?>;"><?php echo esc_html(stripslashes($r['name'])); ?></strong>
                        <span style="font-weight:bold; opacity:0.8;"><?php echo $percent; ?>%</span>
                    </div>
                    <div style="height:10px; background:rgba(0,0,0,0.3); border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,0.05);">
                        <div style="width:<?php echo $percent; ?>%; height:100%; background:linear-gradient(90deg, #6b21a8, #a335ee); transition: width 0.8s ease-in-out;"></div>
                    </div>
                    <div class="whb-boss-tooltip">
                        <strong style="display:block; border-bottom:1px solid #444; margin-bottom:10px; padding-bottom:5px; font-size:14px; color:#a335ee;"><?php echo esc_html(stripslashes($r['name'])); ?> Status</strong>
                        <ul style="list-style:none; margin:0; padding:0; line-height:1.6;">
                            <?php foreach ($r['bosses'] as $idx => $bn): 
                                $is_dead = in_array($idx, $killed); $status_color = $is_dead ? '#4fff4f' : '#ff4f4f';
                            ?>
                            <li style="display:flex; justify-content:space-between; color:<?php echo $status_color; ?>;">
                                <span><?php echo $is_dead ? '✅' : '❌'; ?> <?php echo esc_html(stripslashes($bn)); ?></span>
                                <span style="font-size:10px; opacity:0.6; margin-left: 10px;"><?php echo $is_dead ? 'KILLED' : 'ALIVE'; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function render_recruitment() {
        global $wpdb; 
        $s = $this->get_styles(); 
        $table = $wpdb->prefix . 'whb_recruitment';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return "<p>Recruitment database missing.</p>";
        
        $data = $wpdb->get_results("SELECT * FROM $table ORDER BY class_key ASC");
        $classes = []; 
        if (is_array($data) || is_object($data)) { 
            foreach ($data as $r) { $classes[$r->class_key][] = $r; } 
        }
        
        ob_start(); ?>
        <style>.whb-recruitment-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 10px; } @media (max-width: 480px) { .whb-recruitment-grid { grid-template-columns: 1fr; } }</style>
        <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:15px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>;">
            <h3 style="margin-top:0; border-bottom:1px solid <?php echo $s['border']; ?>; padding-bottom:10px; margin-bottom:15px; text-align:center; text-transform:uppercase; letter-spacing:1px; font-size:16px;">Recruitment</h3>
            <div class="whb-recruitment-grid">
                <?php foreach ($classes as $cl => $specs): 
                    $any_open = false; 
                    foreach ($specs as $spec) { if ($spec->is_open) $any_open = true; }
                ?>
                <div style="display:flex; align-items:flex-start; gap:8px;">
                    <img src="<?php echo WHB_URL; ?>includes/images/class-icons/<?php echo $cl; ?>.png" style="width:18px; height:18px; margin-top:2px;">
                    <div>
                        <strong style="display:block; font-size:13px; line-height:1.2;"><?php echo ucfirst($cl); ?></strong>
                        <?php if (!$any_open): ?>
                            <div style="color:#ff4d4d; font-weight:bold; font-size:10px; margin-top:2px;">CLOSED</div>
                        <?php else: ?>
                            <div style="font-size:11px; opacity:0.8; margin-top:2px; line-height:1.3;">
                                <?php $out = []; foreach ($specs as $s_spec) { $out[] = $s_spec->is_open ? stripslashes($s_spec->spec_key) : "<span style='text-decoration:line-through; opacity:0.5;'>".stripslashes($s_spec->spec_key)."</span>"; } echo implode(', ', $out); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:20px; padding-top:15px; border-top:1px solid <?php echo $s['border']; ?>; text-align:center;">
                <a href="https://discord.gg/whbguild" target="_blank" style="display:inline-block; background:#5865F2; color:#fff; padding:10px 15px; text-decoration:none; border-radius:4px; font-weight:bold; font-size:13px; transition:background 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">Join the Waffle House</a>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public function render_10m_roster() { return $this->render_roster_tabs('10m'); }
    public function render_25m_roster() { return $this->render_roster_tabs('25m'); }

    private function render_roster_tabs($type) {
        $active = (array) get_option('whb_active_groups', []); 
        $s = $this->get_styles(); 
        $display_groups = []; 
        $title = ''; 
        $all_groups = $this->get_group_names(); 
        
        if ($type === '10m') {
            $title = '10-Man Raid Teams';
            for($i=1;$i<=5;$i++) { 
                $key = "10m_$i"; 
                if(in_array($key, $active)) $display_groups[$key] = stripslashes($all_groups[$key]); 
            }
        } else {
            $title = '25-Man Raid Teams';
            if(in_array('25m_main', $active)) $display_groups['25m_main'] = stripslashes($all_groups['25m_main']);
            if(in_array('25m_alt', $active)) $display_groups['25m_alt'] = stripslashes($all_groups['25m_alt']);
        }
        
        if (empty($display_groups)) return "";
        
        ob_start(); ?>
        <style>
            .whb-grid-10m { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .whb-grid-25m { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; }
            .whb-tab-btn-wrap { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; justify-content: center; }
            @media (max-width: 900px) { .whb-grid-25m { grid-template-columns: repeat(3, 1fr); } }
            @media (max-width: 600px) { .whb-grid-25m { grid-template-columns: repeat(2, 1fr); gap: 10px; } .whb-grid-10m { gap: 10px; } .whb-tab-btn { flex: 1 1 calc(50% - 10px); text-align: center; } }
            @media (max-width: 400px) { .whb-grid-10m, .whb-grid-25m { grid-template-columns: 1fr; } .whb-tab-btn { flex: 1 1 100%; } }
        </style>
        <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:15px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>; box-shadow:0 2px 4px <?php echo $s['shadow']; ?>;">
            <h3 style="margin-top:0; border-bottom:1px solid <?php echo $s['border']; ?>; padding-bottom:10px; text-align:center; text-transform:uppercase; letter-spacing:1px; font-size:16px;"><?php echo $title; ?></h3>
            <div class="whb-roster-wrapper" style="margin-top:15px;">
                <div class="whb-tab-btn-wrap">
                    <?php $first=true; foreach($display_groups as $k => $l): ?>
                        <button onclick="whbSwitchTab('<?php echo $k; ?>', this)" class="whb-tab-btn" style="padding:8px 15px; border:none; background:<?php echo $first ? '#a335ee' : $s['sec']; ?>; color:#fff; cursor:pointer; border-radius:4px; font-weight:bold; transition: background 0.2s;"><?php echo esc_html($l); ?></button>
                    <?php $first=false; endforeach; ?>
                </div>
                <?php 
                $grid_class = ($type === '10m') ? 'whb-grid-10m' : 'whb-grid-25m'; 
                $first=true; foreach($display_groups as $k => $l): 
                ?>
                    <div id="whb-pane-<?php echo $k; ?>" class="whb-roster-pane <?php echo $grid_class; ?>" style="display:<?php echo $first ? 'grid' : 'none'; ?>; background:<?php echo $s['sec']; ?>; padding:15px; border-radius:6px; border:1px solid <?php echo $s['border']; ?>;">
                        <?php $this->render_group_composition($k); ?>
                    </div>
                <?php $first=false; endforeach; ?>
            </div>
        </div>
        <script>
            function whbSwitchTab(id, btn) { 
                const wrapper = btn.closest('.whb-roster-wrapper'); 
                wrapper.querySelectorAll('.whb-roster-pane').forEach(p => p.style.display = 'none'); 
                wrapper.querySelectorAll('.whb-tab-btn').forEach(b => b.style.background = '<?php echo $s['sec']; ?>'); 
                wrapper.querySelector('#whb-pane-'+id).style.display = 'grid'; 
                btn.style.background = '#a335ee'; 
            }
        </script>
        <?php return ob_get_clean();
    }

    private function render_group_composition($g_key) {
        global $wpdb;
        $table = $wpdb->prefix . 'whb_raid_groups';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) return;

        $slots = $wpdb->get_results($wpdb->prepare("SELECT g.slot_id, g.player_name, r.player_class FROM {$wpdb->prefix}whb_raid_groups g LEFT JOIN {$wpdb->prefix}whb_roster r ON g.player_name = r.player WHERE g.group_key = %s ORDER BY g.id ASC", $g_key));
        $colors = ['druid'=>'#FF7D0A','hunter'=>'#ABD473','mage'=>'#69CCF0','paladin'=>'#F58CBA','priest'=>'#FFFFFF','rogue'=>'#FFF569','shaman'=>'#0070DE','warlock'=>'#9482C9','warrior'=>'#C69B6D'];

        if (is_array($slots)) {
            foreach ($slots as $slot_data) {
                if (empty($slot_data->player_name)) continue;
                $c = $colors[$slot_data->player_class] ?? '#fff';
                $p_name = esc_html(stripslashes($slot_data->player_name));
                
                echo "<div>
                    <span style='font-size:9px; text-transform:uppercase; opacity:0.5; font-weight:bold;'>".str_replace('_',' ',$slot_data->slot_id)."</span>
                    <div style='display:flex; align-items:center; gap:5px; margin-top:3px;'>
                        <img src='".WHB_URL."includes/images/class-icons/{$slot_data->player_class}.png' style='width:16px; height:16px; border-radius:2px;'>
                        <strong style='color:$c; text-shadow:1px 1px 1px rgba(0,0,0,0.5); font-size:13px;'>{$p_name}</strong>
                    </div>
                </div>";
            }
        }
    }
}