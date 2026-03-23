<?php
if (!defined('ABSPATH')) exit;

class WHB_Ajax {

    public function __construct() {
        // Register the AJAX actions for both logged-in and guest users
        add_action('wp_ajax_whb_get_loot', array($this, 'handle_loot_request'));
        add_action('wp_ajax_nopriv_whb_get_loot', array($this, 'handle_loot_request'));
    }

    /**
     * Process the Loot Viewer AJAX search
     */
    public function handle_loot_request() {
        global $wpdb;
        $loot_table = $wpdb->prefix . 'whb_loot';
        $roster_table = $wpdb->prefix . 'whb_roster';

        // 1. Sanitize Inputs
        $player_search = sanitize_text_field($_GET['player'] ?? '');
        $zone_filter   = sanitize_text_field($_GET['zone'] ?? '');
        $current_page  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page      = 25;

        // 2. Build the Query
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($player_search)) {
            $where .= " AND l.player LIKE %s";
            $params[] = '%' . $wpdb->esc_like($player_search) . '%';
        }

        if (!empty($zone_filter)) {
            $where .= " AND l.zone = %s";
            $params[] = $zone_filter;
        }

        // 3. Pagination Math
        $total_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $loot_table l $where", 
            ...$params
        ));
        $total_pages = ceil($total_items / $per_page);
        $offset = ($current_page - 1) * $per_page;

        // 4. Fetch Data (Joined with Roster for Class Colors & Role Icons)
        $sql = "SELECT l.*, r.role, r.player_class 
                FROM $loot_table l 
                LEFT JOIN $roster_table r ON l.player = r.player 
                $where 
                ORDER BY l.loot_date DESC 
                LIMIT %d OFFSET %d";
        
        $query_params = array_merge($params, [$per_page, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));

        // 5. Apply Theme Styling
        $theme = get_option('whb_theme', 'dark');
        $border = ($theme === 'light') ? '#e2e8f0' : (($theme === 'dark') ? '#333' : 'rgba(128,128,128,0.2)');
        $btn_bg = ($theme === 'light') ? '#e2e8f0' : '#333';
        $btn_text = ($theme === 'light') ? '#1a202c' : '#fff';

        // TBC Metadata
        $role_icons = ['tank' => '🛡️', 'healer' => '💚', 'melee' => '⚔️', 'ranged' => '🔮'];
        $class_colors = [
            'druid' => '#FF7D0A', 'hunter' => '#ABD473', 'mage' => '#69CCF0',
            'paladin' => '#F58CBA', 'priest' => '#FFFFFF', 'rogue' => '#FFF569',
            'shaman' => '#0070DE', 'warlock' => '#9482C9', 'warrior' => '#C69B6D'
        ];

        // 6. Generate Row HTML
        $html = "";
        if (empty($rows)) {
            $html = "<tr><td colspan='5' style='padding:20px; text-align:center;'>No loot found matching those criteria.</td></tr>";
        } else {
            foreach ($rows as $row) {
                $icon = isset($role_icons[$row->role]) ? "<span title='".ucfirst($row->role)."' style='margin-right:5px;'>".$role_icons[$row->role]."</span>" : "";
                $color = isset($class_colors[$row->player_class]) ? $class_colors[$row->player_class] : 'inherit';
                
                $player_html = sprintf(
                    "<span style='color:%s; font-weight:bold; text-shadow:1px 1px 1px rgba(0,0,0,0.5);'>%s</span>",
                    $color, esc_html($row->player)
                );

                $html .= "<tr>
                    <td style='padding:10px; border-bottom:1px solid $border;'>".date('M j, Y', strtotime($row->loot_date))."</td>
                    <td style='padding:10px; border-bottom:1px solid $border;'>{$icon}{$player_html}</td>
                    <td style='padding:10px; border-bottom:1px solid $border;'><a href='https://www.wowhead.com/tbc/item={$row->item_id}' class='q4'>[{$row->item_name}]</a></td>
                    <td style='padding:10px; border-bottom:1px solid $border;'>".esc_html($row->zone)."</td>
                    <td style='padding:10px; border-bottom:1px solid $border;'>".esc_html($row->raid_group)."</td>
                </tr>";
            }
        }

        // 7. Generate Pagination HTML
        $pag_html = "";
        if ($total_pages > 1) {
            $pag_html .= "<div style='display:flex; gap:8px; justify-content:center; padding:15px 0;'>";
            if ($current_page > 1) {
                $pag_html .= "<button data-page='".($current_page - 1)."' style='padding:5px 12px; cursor:pointer; background:$btn_bg; color:$btn_text; border:none; border-radius:4px;'>&laquo; Prev</button>";
            }
            $pag_html .= "<span style='padding:5px 10px; opacity:0.7;'>Page $current_page of $total_pages</span>";
            if ($current_page < $total_pages) {
                $pag_html .= "<button data-page='".($current_page + 1)."' style='padding:5px 12px; cursor:pointer; background:$btn_bg; color:$btn_text; border:none; border-radius:4px;'>Next &raquo;</button>";
            }
            $pag_html .= "</div>";
        }

        wp_send_json([
            'rows' => $html,
            'pagination' => $pag_html
        ]);
    }
}

// Instantiate the class to register hooks
new WHB_Ajax();