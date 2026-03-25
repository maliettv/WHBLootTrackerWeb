<?php
if (!defined('ABSPATH')) exit;

class WHB_Ajax {

    public function __construct() {
        add_action('wp_ajax_whb_update_loot_entry', array($this, 'ajax_update_loot_entry'));
        add_action('wp_ajax_whb_get_loot_frontend', array($this, 'ajax_get_loot_frontend'));
        add_action('wp_ajax_nopriv_whb_get_loot_frontend', array($this, 'ajax_get_loot_frontend'));
        
        // NEW: Public hooks for Discord 1-click Approval/Denial
        add_action('wp_ajax_whb_app_action', array($this, 'ajax_app_action'));
        add_action('wp_ajax_nopriv_whb_app_action', array($this, 'ajax_app_action'));
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

    // Helper to format the webhook templates with real data
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

    /**
     * NEW: Handle Discord 1-Click Approve/Deny Requests
     */
    public function ajax_app_action() {
        global $wpdb;
        $app_id = isset($_GET['app_id']) ? intval($_GET['app_id']) : 0;
        $token  = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        $set_status = isset($_GET['set_status']) ? sanitize_text_field($_GET['set_status']) : '';

        if (!$app_id || !$token || !in_array($set_status, ['approved', 'denied'])) {
            wp_die('Invalid request formatting.');
        }

        $apps_table = $wpdb->prefix . 'whb_applications';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $apps_table WHERE id = %d", $app_id));

        if (!$row) wp_die('Application not found.');

        $data = json_decode($row->app_data, true);
        
        // Verify the secure token generated at submission
        if (!isset($data['_action_token']) || $data['_action_token'] !== $token) {
            wp_die('Invalid or expired security token. You cannot authorize this action.');
        }

        if (isset($data['_status']) && $data['_status'] === $set_status) {
            wp_die('This application has already been marked as ' . esc_html(strtoupper($set_status)) . '.');
        }

        // Update Database
        $data['_status'] = $set_status;
        $wpdb->update($apps_table, ['app_data' => wp_json_encode($data)], ['id' => $app_id]);

        // Trigger Announcement Webhook
        $status_url = get_option('whb_discord_webhook_status');
        if (!empty($status_url)) {
            $template = ($set_status === 'approved') ? get_option('whb_msg_approved') : get_option('whb_msg_denied');
            if (!empty($template)) {
                $msg = $this->format_status_message($template, $row->applicant, $data);
                $color = ($set_status === 'approved') ? hexdec("46b450") : hexdec("d63638");
                $embed = [ "description" => $msg, "color" => $color ];
                wp_remote_post($status_url, ['headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['embeds' => [$embed]])]);
            }
        }

        // Render Success Screen to the Officer
        $color = ($set_status === 'approved') ? '#46b450' : '#d63638';
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px; background:#120f26; color:#fff; height:100vh; box-sizing:border-box;'>
                <div style='max-width:500px; margin:0 auto; background:#1a1a1a; padding:40px; border-radius:8px; border:2px solid $color; box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
                    <h2 style='color:$color; margin-top:0; font-size:24px; text-transform:uppercase;'>Action Confirmed!</h2>
                    <p style='font-size:18px; margin:20px 0;'><strong>".esc_html(stripslashes($row->applicant))."</strong> has been successfully <strong>{$set_status}</strong>.</p>
                    <p style='color:#aaa; font-size:14px; margin-top:30px; padding-top:20px; border-top:1px solid #333;'>The public Discord announcement has been triggered. You can close this window.</p>
                </div>
              </div>";
        exit;
    }

    public function ajax_update_loot_entry() {
        check_ajax_referer('whb_update_loot_action', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->prefix . 'whb_loot', 
            [
                'item_id' => intval($_POST['new_item_id']), 
                'player' => sanitize_text_field(stripslashes($_POST['new_player'])), 
                'zone' => sanitize_text_field(stripslashes($_POST['new_zone'])), 
                'raid_group' => sanitize_text_field(stripslashes($_POST['new_group']))
            ], 
            ['id' => intval($_POST['edit_id'])]
        );
        
        if ($updated !== false) wp_send_json_success('Updated successfully'); 
        else wp_send_json_error('Database error');
    }

    public function ajax_get_loot_frontend() {
        global $wpdb; 
        $loot_table = $wpdb->prefix . 'whb_loot';
        $roster_table = $wpdb->prefix . 'whb_roster';
        
        $player = isset($_GET['player']) ? sanitize_text_field($_GET['player']) : '';
        $zone   = isset($_GET['zone']) ? sanitize_text_field(stripslashes($_GET['zone'])) : '';
        $group  = isset($_GET['group']) ? sanitize_text_field(stripslashes($_GET['group'])) : '';
        $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20; 
        $offset = ($paged - 1) * $per_page;

        $where = ["1=1"]; 
        $args = [];

        if (!empty($player)) { 
            $where[] = "(l.player LIKE %s OR l.item_name LIKE %s)"; 
            $args[] = "%" . $wpdb->esc_like($player) . "%"; 
            $args[] = "%" . $wpdb->esc_like($player) . "%"; 
        }
        if (!empty($zone)) { 
            $where[] = "l.zone = %s"; 
            $args[] = $zone; 
        }
        if (!empty($group)) { 
            $where[] = "l.raid_group = %s"; 
            $args[] = $group; 
        }

        $where_sql = implode(' AND ', $where);
        
        if (!empty($args)) {
            $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(l.id) FROM $loot_table l WHERE $where_sql", $args));
            $query = $wpdb->prepare("SELECT l.*, r.player_class FROM $loot_table l LEFT JOIN $roster_table r ON l.player = r.player WHERE $where_sql ORDER BY l.loot_date DESC, l.id DESC LIMIT %d OFFSET %d", array_merge($args, [$per_page, $offset]));
        } else {
            $total_items = $wpdb->get_var("SELECT COUNT(l.id) FROM $loot_table l");
            $query = $wpdb->prepare("SELECT l.*, r.player_class FROM $loot_table l LEFT JOIN $roster_table r ON l.player = r.player ORDER BY l.loot_date DESC, l.id DESC LIMIT %d OFFSET %d", $per_page, $offset);
        }

        $results = $wpdb->get_results($query); 
        $all_groups = $this->get_group_names(); 
        $zone_settings = get_option('whb_zone_settings', []);
        $s = $this->get_styles(); 
        $rows = '';

        $colors = ['druid'=>'#FF7D0A','hunter'=>'#ABD473','mage'=>'#69CCF0','paladin'=>'#F58CBA','priest'=>'#FFFFFF','rogue'=>'#FFF569','shaman'=>'#0070DE','warlock'=>'#9482C9','warrior'=>'#C69B6D'];

        if ($results) {
            foreach ($results as $row) {
                $item_name_clean = stripslashes($row->item_name);
                $player_clean = stripslashes($row->player);
                $raw_z = stripslashes($row->zone);
                
                $item_display = ($row->item_id > 0) ? "<a href='https://www.wowhead.com/tbc/item={$row->item_id}' target='_blank' data-wowhead='item={$row->item_id}&domain=tbc' style='color:#a335ee; text-decoration:none; font-weight:bold;'>".esc_html($item_name_clean)."</a>" : "<span style='color:#a335ee; font-weight:bold;'>".esc_html($item_name_clean)."</span>";
                $group_display = isset($all_groups[$row->raid_group]) ? stripslashes($all_groups[$row->raid_group]) : stripslashes($row->raid_group); 
                if (empty($group_display)) $group_display = '-';
                
                $zone_display = isset($zone_settings[$raw_z]['display']) && !empty($zone_settings[$raw_z]['display']) ? stripslashes($zone_settings[$raw_z]['display']) : $raw_z;

                $p_class = strtolower($row->player_class ?? '');
                $c = isset($colors[$p_class]) ? $colors[$p_class] : $s['text'];
                $icon = !empty($p_class) ? "<img src='".WHB_URL."includes/images/class-icons/{$p_class}.png' style='width:16px; height:16px; border-radius:2px; vertical-align:middle; margin-right:6px;'>" : "";
                $player_html = "<div style='display:flex; align-items:center;'>{$icon}<strong style='color:{$c}; text-shadow:1px 1px 1px rgba(0,0,0,0.5);'>".esc_html($player_clean)."</strong></div>";

                $rows .= "<tr>
                    <td style='padding:12px; border-bottom:1px solid {$s['border']};'>".esc_html($row->loot_date)."</td>
                    <td style='padding:12px; border-bottom:1px solid {$s['border']};'>{$player_html}</td>
                    <td style='padding:12px; border-bottom:1px solid {$s['border']};'>{$item_display}</td>
                    <td style='padding:12px; border-bottom:1px solid {$s['border']};'>".esc_html($zone_display)."</td>
                    <td style='padding:12px; border-bottom:1px solid {$s['border']};'>".esc_html($group_display)."</td>
                </tr>";
            }
        } else { 
            $rows = "<tr><td colspan='5' style='text-align:center; padding:30px;'>No loot found.</td></tr>"; 
        }

        $total_pages = ceil($total_items / $per_page); 
        $pagination = '';
        if ($total_pages > 1) {
            $pagination .= "<div style='display:flex; gap:5px; flex-wrap:wrap; justify-content:center;'>";
            if ($paged > 1) $pagination .= "<button data-page='".($paged-1)."' style='padding:8px 12px; cursor:pointer; background:{$s['sec']}; color:{$s['text']}; border:1px solid {$s['border']}; border-radius:4px;'>Prev</button>";
            for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == $paged) { 
                    $pagination .= "<button disabled style='padding:8px 12px; background:#a335ee; color:#fff; border:none; border-radius:4px; font-weight:bold;'>$i</button>"; 
                } 
                elseif (abs($i - $paged) < 3 || $i == 1 || $i == $total_pages) { 
                    $pagination .= "<button data-page='$i' style='padding:8px 12px; cursor:pointer; background:{$s['sec']}; color:{$s['text']}; border:1px solid {$s['border']}; border-radius:4px;'>$i</button>"; 
                } 
                elseif (abs($i - $paged) == 3) { 
                    $pagination .= "<span style='padding:8px 12px;'>...</span>"; 
                }
            }
            if ($paged < $total_pages) $pagination .= "<button data-page='".($paged+1)."' style='padding:8px 12px; cursor:pointer; background:{$s['sec']}; color:{$s['text']}; border:1px solid {$s['border']}; border-radius:4px;'>Next</button>";
            $pagination .= "</div>";
        }
        wp_send_json(['rows' => $rows, 'pagination' => $pagination]);
    }
}