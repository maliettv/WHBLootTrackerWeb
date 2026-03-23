<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;

/**
 * TBC Raid Database (Helper Function)
 * We define this here as well in case the admin panel isn't loaded on the frontend.
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
 * Shortcode 1: [loot_viewer]
 * Displays the live search table for guild loot.
 */
add_shortcode('loot_viewer', function() {
    global $wpdb;
    
    // Get distinct zones for the dropdown filter
    $zones = $wpdb->get_col("SELECT DISTINCT zone FROM {$wpdb->prefix}whb_loot ORDER BY zone ASC");
    
    // Fetch Theme Options
    $theme = get_option('whb_theme', 'dark');
    $custom_bg = get_option('whb_custom_bg', '#1a1a1a');
    $custom_text = get_option('whb_custom_text', '#ffffff');

    // Assign colors based on theme selection
    if ($theme === 'light') {
        $bg = '#ffffff'; 
        $text = '#1a202c'; 
        $sec_bg = '#f7fafc'; 
        $border = '#e2e8f0';
    } elseif ($theme === 'dark') {
        $bg = '#1a1a1a'; 
        $text = '#f3f4f6'; 
        $sec_bg = '#222222'; 
        $border = '#333333';
    } else {
        $bg = $custom_bg; 
        $text = $custom_text;
        $sec_bg = 'rgba(128, 128, 128, 0.1)'; 
        $border = 'rgba(128, 128, 128, 0.2)';
    }

    ob_start(); 
    ?>
    <div id="whb-wrap" style="background:<?php echo esc_attr($bg); ?>; color:<?php echo esc_attr($text); ?>; padding:20px; border-radius:8px; border:1px solid <?php echo esc_attr($border); ?>;">
        
        <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
            <input type="text" id="whb-search" placeholder="Search Player Name..." style="flex:2; padding:10px; background:<?php echo esc_attr($sec_bg); ?>; color:<?php echo esc_attr($text); ?>; border:1px solid <?php echo esc_attr($border); ?>; border-radius:4px;">
            
            <select id="whb-zone" style="flex:1; padding:10px; background:<?php echo esc_attr($sec_bg); ?>; color:<?php echo esc_attr($text); ?>; border:1px solid <?php echo esc_attr($border); ?>; border-radius:4px;">
                <option value="">All Zones</option>
                <?php foreach($zones as $z) echo "<option value='".esc_attr($z)."'>".esc_html($z)."</option>"; ?>
            </select>
        </div>

        <table style="width:100%; border-collapse:collapse; text-align:left;">
            <thead style="background:<?php echo esc_attr($sec_bg); ?>; border-bottom:2px solid <?php echo esc_attr($border); ?>;">
                <tr>
                    <th style="padding:12px;">Date</th>
                    <th style="padding:12px;">Player</th>
                    <th style="padding:12px;">Item</th>
                    <th style="padding:12px;">Zone</th>
                    <th style="padding:12px;">Group</th>
                </tr>
            </thead>
            <tbody id="whb-results">
                <tr><td colspan="5" style="text-align:center; padding: 20px;">Loading loot data...</td></tr>
            </tbody>
        </table>

        <div id="whb-pagination" style="margin-top: 15px;"></div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => { 
            const ev = new Event('input'); 
            const search = document.getElementById('whb-search'); 
            if(search) search.dispatchEvent(ev); 
        });
    </script>
    <?php 
    return ob_get_clean();
});

/**
 * Shortcode 2: [raid_progression]
 * Displays the dynamic progress bars for guild kills.
 */
add_shortcode('raid_progression', function() {
    $raids = whb_get_tbc_raids();
    $progression = get_option('whb_progression_data', []);
    $visible_raids = get_option('whb_visible_raids', []);
    
    // Fail gracefully if no raids are selected in the admin panel
    if (empty($visible_raids)) {
        return "<p>No raids currently visible. Configure this in the WHB Tracker admin panel.</p>";
    }

    // Fetch Theme Options
    $theme = get_option('whb_theme', 'dark');
    $bg = ($theme === 'light') ? '#f7fafc' : (($theme === 'dark') ? '#222222' : 'rgba(128, 128, 128, 0.1)');
    $text = ($theme === 'light') ? '#1a202c' : (($theme === 'dark') ? '#f3f4f6' : get_option('whb_custom_text', '#ffffff'));
    
    // Default progress bar color (Epic Purple)
    $accent = '#a335ee';

    ob_start(); 
    ?>
    <div style="display:flex; flex-direction:column; gap:15px; font-family:inherit; color:<?php echo esc_attr($text); ?>;">
        <?php 
        foreach ($visible_raids as $raid_key): 
            if (!isset($raids[$raid_key])) continue;
            
            $raid = $raids[$raid_key];
            $total = count($raid['bosses']);
            $killed = isset($progression[$raid_key]) ? count($progression[$raid_key]) : 0;
            $percent = ($total > 0) ? round(($killed / $total) * 100) : 0;
        ?>
            <div style="background:<?php echo esc_attr($bg); ?>; padding:15px; border-radius:8px; display:flex; flex-wrap:wrap; align-items:center; gap:15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div style="flex: 1 1 200px;">
                    <h3 style="margin:0 0 5px 0; font-size:18px;"><?php echo esc_html($raid['name']); ?></h3>
                    <span style="font-size:14px; opacity:0.8;"><?php echo "$killed / $total Bosses Defeated"; ?></span>
                </div>
                
                <div style="flex: 2 1 300px; background:rgba(0,0,0,0.2); border-radius:10px; height:20px; overflow:hidden; border: 1px solid rgba(255,255,255,0.1);">
                    <div style="width:<?php echo $percent; ?>%; background:<?php echo $accent; ?>; height:100%; transition:width 1s ease-in-out;"></div>
                </div>
                
                <div style="font-weight: bold; font-size: 16px; min-width: 45px; text-align: right;">
                    <?php echo $percent; ?>%
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php 
    return ob_get_clean();
});