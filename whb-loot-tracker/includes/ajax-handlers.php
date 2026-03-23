<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * Register AJAX actions for both logged-in and guest users
 */
add_action('wp_ajax_whb_get_loot', 'whb_execute_loot_query');
add_action('wp_ajax_nopriv_whb_get_loot', 'whb_execute_loot_query');

function whb_execute_loot_query() {
    global $wpdb;
    $loot_table = $wpdb->prefix . 'whb_loot';
    $roster_table = $wpdb->prefix . 'whb_roster';
    
    // Sanitize incoming search parameters
    $p = sanitize_text_field($_GET['player'] ?? '');
    $z = sanitize_text_field($_GET['zone'] ?? '');
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 25;

    // Build the WHERE clause dynamically based on user input
    $where = "WHERE 1=1"; 
    $params = [];
    if ($p) { 
        $where .= " AND l.player LIKE %s"; 
        $params[] = "%$p%"; 
    }
    if ($z) { 
        $where .= " AND l.zone = %s"; 
        $params[] = $z; 
    }

    // Calculate total pages for pagination
    $count_sql = "SELECT COUNT(*) FROM $loot_table l $where";
    $total_items = $params ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql);
    $total_pages = ceil($total_items / $per_page);
    
    // Fetch the loot data, joining the roster table to grab roles and classes
    $sql = "SELECT l.*, r.role, r.player_class 
            FROM $loot_table l 
            LEFT JOIN $roster_table r ON l.player = r.player 
            $where 
            ORDER BY l.loot_date DESC 
            LIMIT %d OFFSET %d";
            
    $query_params = array_merge($params, [$per_page, ($page - 1) * $per_page]);
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$query_params));

    // Fetch theme settings for styling
    $theme = get_option('whb_theme', 'dark');
    $border = ($theme === 'light') ? '#e2e8f0' : (($theme === 'dark') ? '#333333' : 'rgba(128,128,128,0.2)');
    $btn_bg = ($theme === 'light') ? '#e2e8f0' : '#333'; 
    $btn_text = ($theme === 'light') ? '#1a202c' : '#fff';

    // Role icons and official TBC Class Colors
    $role_icons = ['tank' => '🛡️', 'healer' => '💚', 'melee' => '⚔️', 'ranged' => '🔮'];
    $class_colors = [
        'druid' => '#FF7D0A', 'hunter' => '#ABD473', 'mage' => '#69CCF0',
        'paladin' => '#F58CBA', 'priest' => '#FFFFFF', 'rogue' => '#FFF569',
        'shaman' => '#0070DE', 'warlock' => '#9482C9', 'warrior' => '#C69B6D'
    ];

    // Generate the HTML for the table rows
    $html = "";
    if (!$rows) {
        $html = "<tr><td colspan='5' style='padding:15px; text-align:center;'>No data found.</td></tr>"; 
    } else {
        foreach ($rows as $r) {
            // Apply role icon
            $icon = isset($role_icons[$r->role]) ? "<span title='".ucfirst($r->role)."' style='font-size:14px; margin-right:5px;'>".$role_icons[$r->role]."</span>" : "";
            
            // Apply class color with a subtle drop shadow to ensure it's readable on light or dark themes
            $name_color = isset($class_colors[$r->player_class]) ? $class_colors[$r->player_class] : 'inherit';
            $player_display = "<span style='color: {$name_color}; font-weight: bold; text-shadow: 1px 1px 1px rgba(0,0,0,0.8);'>".esc_html($r->player)."</span>";

            $html .= sprintf(
                "<tr>
                    <td style='padding:10px; border-bottom:1px solid %s;'>%s</td>
                    <td style='padding:10px; border-bottom:1px solid %s;'>%s%s</td>
                    <td style='padding:10px; border-bottom:1px solid %s;'><a href='https://www.wowhead.com/tbc/item=%d' class='q4'>[%s]</a></td>
                    <td style='padding:10px; border-bottom:1px solid %s;'>%s</td>
                    <td style='padding:10px; border-bottom:1px solid %s;'>%s</td>
                </tr>", 
                $border, date('M j, Y', strtotime($r->loot_date)), 
                $border, $icon, $player_display, 
                $border, intval($r->item_id), esc_html($r->item_name), 
                $border, esc_html($r->zone), 
                $border, esc_html($r->raid_group)
            );
        }
    }

    // Generate HTML for the Pagination buttons
    $pag_html = "";
    if ($total_pages > 1) {
        $pag_html .= "<div style='display:flex; gap:5px; justify-content:center; padding-top:15px;'>";
        
        if ($page > 1) {
            $pag_html .= "<button data-page='".($page - 1)."' style='padding:5px 10px; cursor:pointer; background:$btn_bg; color:$btn_text; border:none; border-radius:3px;'>&laquo; Prev</button>";
        }
        
        $pag_html .= "<span style='padding:5px 10px;'>Page $page of $total_pages</span>";
        
        if ($page < $total_pages) {
            $pag_html .= "<button data-page='".($page + 1)."' style='padding:5px 10px; cursor:pointer; background:$btn_bg; color:$btn_text; border:none; border-radius:3px;'>Next &raquo;</button>";
        }
        
        $pag_html .= "</div>";
    }

    // Send the package back to the browser
    wp_send_json(['rows' => $html, 'pagination' => $pag_html]);
}