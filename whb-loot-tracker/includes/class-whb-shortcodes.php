<?php
if (!defined('ABSPATH')) exit;

class WHB_Shortcodes {

    /**
     * Helper: Centralized TBC Raid Data
     */
    private function get_raids_data() {
        return [
            'karazhan' => ['name' => 'Karazhan', 'bosses' => ['Attumen the Huntsman', 'Moroes', 'Maiden of Virtue', 'Opera Event', 'Nightbane', 'The Curator', 'Terestian Illhoof', 'Shade of Aran', 'Netherspite', 'Chess Event', 'Prince Malchezaar']],
            'gruul' => ['name' => "Gruul's Lair", 'bosses' => ['High King Maulgar', 'Gruul the Dragonkiller']],
            'magtheridon' => ['name' => "Magtheridon's Lair", 'bosses' => ['Magtheridon']],
            'ssc' => ['name' => 'Serpentshrine Cavern', 'bosses' => ['Hydross the Unstable', 'The Lower Below', 'Leotheras the Blind', 'Fathom-Lord Karathress', 'Morogrim Tidewalker', 'Lady Vashj']],
            'tk' => ['name' => 'Tempest Keep', 'bosses' => ["Al'ar", 'Void Reaver', 'High Astromancer Solarian', 'Kael\'thas Sunstrider']],
            'hyjal' => ['name' => 'Battle for Mount Hyjal', 'bosses' => ['Rage Winterchill', 'Anetheron', 'Kaz\'rogal', 'Azgalor', 'Archimonde']],
            'bt' => ['name' => 'Black Temple', 'bosses' => ['High Warlord Naj\'entus', 'Supremus', 'Shade of Akama', 'Teron Gorefiend', 'Reliquary of Souls', 'Gurtogg Bloodboil', 'Mother Shahraz', 'The Illidari Council', 'Illidan Stormrage']],
            'za' => ['name' => 'Zul\'Aman', 'bosses' => ['Nalorakk', 'Akil\'zon', 'Jan\'alai', 'Halazzi', 'Hex Lord Malacrass', 'Daakara']],
            'sunwell' => ['name' => 'Sunwell Plateau', 'bosses' => ['Kalecgos', 'Brutallus', 'Felmyst', 'The Eredar Twins', 'M\'uru', 'Kil\'jaeden']]
        ];
    }

    /**
     * Helper: Get Theme Styling
     */
    private function get_styles() {
        $theme = get_option('whb_theme', 'dark');
        if ($theme === 'light') {
            return ['bg' => '#ffffff', 'text' => '#1a202c', 'sec' => '#f7fafc', 'border' => '#e2e8f0', 'shadow' => 'rgba(0,0,0,0.1)'];
        } elseif ($theme === 'dark') {
            return ['bg' => '#1a1a1a', 'text' => '#f3f4f6', 'sec' => '#222222', 'border' => '#333333', 'shadow' => 'rgba(0,0,0,0.3)'];
        }
        return [
            'bg' => get_option('whb_custom_bg', '#1a1a1a'),
            'text' => get_option('whb_custom_text', '#ffffff'),
            'sec' => 'rgba(128, 128, 128, 0.1)',
            'border' => 'rgba(128, 128, 128, 0.2)',
            'shadow' => 'rgba(0,0,0,0.3)'
        ];
    }

    /**
     * [loot_viewer] - AJAX Powered Loot Table
     */
    public function render_loot_viewer() {
        global $wpdb; $s = $this->get_styles();
        $zones = $wpdb->get_col("SELECT DISTINCT zone FROM {$wpdb->prefix}whb_loot ORDER BY zone ASC");
        
        ob_start(); ?>
        <div id="whb-loot-app" style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:20px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>;">
            <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
                <input type="text" id="whb-search" placeholder="Search Player..." style="flex:2; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                <select id="whb-zone" style="flex:1; padding:10px; background:<?php echo $s['sec']; ?>; color:<?php echo $s['text']; ?>; border:1px solid <?php echo $s['border']; ?>; border-radius:4px;">
                    <option value="">All Zones</option>
                    <?php foreach($zones as $z) echo "<option value='".esc_attr($z)."'>".esc_html($z)."</option>"; ?>
                </select>
            </div>
            <table style="width:100%; border-collapse:collapse; text-align:left;">
                <thead style="background:<?php echo $s['sec']; ?>; border-bottom:2px solid <?php echo $s['border']; ?>;">
                    <tr><th style="padding:12px;">Date</th><th style="padding:12px;">Player</th><th style="padding:12px;">Item</th><th style="padding:12px;">Zone</th><th style="padding:12px;">Group</th></tr>
                </thead>
                <tbody id="whb-results">
                    <tr><td colspan="5" style="text-align:center; padding:30px;">Loading loot database...</td></tr>
                </tbody>
            </table>
            <div id="whb-pagination" style="margin-top:15px;"></div>
        </div>

        <script>
        (function($) {
            function loadLoot(page = 1) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'GET',
                    data: {
                        action: 'whb_get_loot',
                        player: $('#whb-search').val(),
                        zone: $('#whb-zone').val(),
                        paged: page
                    },
                    success: function(res) {
                        $('#whb-results').html(res.rows);
                        $('#whb-pagination').html(res.pagination);
                    }
                });
            }
            $(document).on('input', '#whb-search', function() { loadLoot(1); });
            $(document).on('change', '#whb-zone', function() { loadLoot(1); });
            $(document).on('click', '#whb-pagination button', function() { loadLoot($(this).data('page')); });
            $(document).ready(function() { loadLoot(1); });
        })(jQuery);
        </script>
        <?php return ob_get_clean();
    }

    /**
     * [raid_progression] - With Hover Boss States
     */
    public function render_progression() {
        $raids = $this->get_raids_data();
        $prog = get_option('whb_progression_data', []);
        $visible = get_option('whb_visible_raids', []);
        $s = $this->get_styles();

        if (empty($visible)) return "";

        ob_start(); ?>
        <style>
            .whb-prog-wrapper { position: relative; cursor: help; background: <?php echo $s['sec']; ?>; padding: 15px; border-radius: 6px; border: 1px solid <?php echo $s['border']; ?>; margin-bottom: 12px; }
            .whb-boss-tooltip { 
                visibility: hidden; opacity: 0; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%);
                background: #111; color: #fff; padding: 15px; border-radius: 6px; border: 1px solid #a335ee;
                z-index: 1000; min-width: 200px; box-shadow: 0 10px 20px rgba(0,0,0,0.5); transition: opacity 0.3s;
            }
            .whb-prog-wrapper:hover .whb-boss-tooltip { visibility: visible; opacity: 1; }
        </style>

        <div class="whb-progression-list">
            <?php foreach ($visible as $rk): 
                if(!isset($raids[$rk])) continue;
                $r = $raids[$rk];
                $killed = $prog[$rk] ?? [];
                $percent = round((count($killed) / count($r['bosses'])) * 100);
            ?>
            <div class="whb-prog-wrapper">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <strong><?php echo $r['name']; ?></strong>
                    <span><?php echo $percent; ?>%</span>
                </div>
                <div style="height:12px; background:rgba(0,0,0,0.2); border-radius:10px; overflow:hidden;">
                    <div style="width:<?php echo $percent; ?>%; height:100%; background:#a335ee;"></div>
                </div>
                
                <div class="whb-boss-tooltip">
                    <strong style="display:block; border-bottom:1px solid #444; margin-bottom:8px;"><?php echo $r['name']; ?> Status</strong>
                    <ul style="list-style:none; margin:0; padding:0; font-size:12px;">
                        <?php foreach ($r['bosses'] as $idx => $bn): 
                            $is_dead = in_array($idx, $killed);
                        ?>
                        <li style="color:<?php echo $is_dead ? '#4fff4f' : '#ff4f4f'; ?>;">
                            <?php echo $is_dead ? '✅' : '❌'; ?> <?php echo $bn; ?> - <?php echo $is_dead ? 'Killed' : 'Alive'; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * [whb_recruitment]
     */
    public function render_recruitment() {
        global $wpdb; $s = $this->get_styles();
        $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}whb_recruitment ORDER BY class_key ASC");
        $classes = []; foreach ($data as $r) { $classes[$r->class_key][] = $r; }

        ob_start(); ?>
        <div style="background:<?php echo $s['bg']; ?>; color:<?php echo $s['text']; ?>; padding:15px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>;">
            <h3 style="margin-top:0; border-bottom:1px solid <?php echo $s['border']; ?>; padding-bottom:10px;">Recruitment</h3>
            <?php foreach ($classes as $cl => $specs): 
                $any_open = false; foreach ($specs as $spec) { if ($spec->is_open) $any_open = true; }
            ?>
            <div style="margin-bottom:12px; display:flex; gap:10px;">
                <img src="<?php echo WHB_URL; ?>includes/images/class-icons/<?php echo $cl; ?>.png" style="width:18px; height:18px; margin-top:3px;">
                <div>
                    <strong><?php echo ucfirst($cl); ?></strong>
                    <?php if (!$any_open): ?>
                        <div style="color:#ff4d4d; font-weight:bold; font-size:11px;">CLOSED</div>
                    <?php else: ?>
                        <div style="font-size:12px; opacity:0.8;">
                            <?php 
                            $out = [];
                            foreach ($specs as $s) { $out[] = $s->is_open ? $s->spec_key : "<span style='text-decoration:line-through; opacity:0.5;'>{$s->spec_key}</span>"; }
                            echo implode(', ', $out);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    /**
     * [whb_10m_roster] / [whb_25m_roster]
     */
    public function render_10m_roster() { return $this->render_roster_tabs('10m'); }
    public function render_25m_roster() { return $this->render_roster_tabs('25m'); }

    private function render_roster_tabs($type) {
        $active = get_option('whb_active_groups', []); $s = $this->get_styles();
        $display_groups = [];
        if ($type === '10m') {
            for($i=1;$i<=5;$i++) if(in_array("10m_$i", $active)) $display_groups["10m_$i"] = "Group $i";
        } else {
            if(in_array('25m_main', $active)) $display_groups['25m_main'] = "Main Raid";
            if(in_array('25m_alt', $active)) $display_groups['25m_alt'] = "Alt Raid";
        }

        if (empty($display_groups)) return "";

        ob_start(); ?>
        <div class="whb-roster-wrapper">
            <div style="display:flex; gap:8px; margin-bottom:15px;">
                <?php $first=true; foreach($display_groups as $k => $l): ?>
                    <button onclick="whbSwitchTab('<?php echo $k; ?>', this)" 
                            class="whb-tab-btn" 
                            style="padding:8px 15px; border:none; background:<?php echo $first ? '#a335ee' : $s['sec']; ?>; color:#fff; cursor:pointer; border-radius:4px;">
                        <?php echo $l; ?>
                    </button>
                <?php $first=false; endforeach; ?>
            </div>
            <?php $first=true; foreach($display_groups as $k => $l): ?>
                <div id="whb-pane-<?php echo $k; ?>" class="whb-roster-pane" style="display:<?php echo $first ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:15px; background:<?php echo $s['bg']; ?>; padding:15px; border-radius:8px; border:1px solid <?php echo $s['border']; ?>;">
                    <?php $this->render_group_composition($k); ?>
                </div>
            <?php $first=false; endforeach; ?>
        </div>
        <script>
            function whbSwitchTab(id, btn) {
                document.querySelectorAll('.whb-roster-pane').forEach(p => p.style.display = 'none');
                document.querySelectorAll('.whb-tab-btn').forEach(b => b.style.background = '<?php echo $s['sec']; ?>');
                document.getElementById('whb-pane-'+id).style.display = 'grid';
                btn.style.background = '#a335ee';
            }
        </script>
        <?php return ob_get_clean();
    }

    private function render_group_composition($g_key) {
        global $wpdb;
        $slots = $wpdb->get_results($wpdb->prepare("
            SELECT g.slot_id, g.player_name, r.player_class 
            FROM {$wpdb->prefix}whb_raid_groups g 
            LEFT JOIN {$wpdb->prefix}whb_roster r ON g.player_name = r.player 
            WHERE g.group_key = %s ORDER BY g.id ASC", $g_key));
        
        $colors = ['druid'=>'#FF7D0A','hunter'=>'#ABD473','mage'=>'#69CCF0','paladin'=>'#F58CBA','priest'=>'#FFFFFF','rogue'=>'#FFF569','shaman'=>'#0070DE','warlock'=>'#9482C9','warrior'=>'#C69B6D'];

        foreach ($slots as $s) {
            if (empty($s->player_name)) continue;
            $c = $colors[$s->player_class] ?? '#fff';
            echo "<div>
                <span style='font-size:9px; text-transform:uppercase; opacity:0.5;'>".str_replace('_',' ',$s->slot_id)."</span>
                <div style='display:flex; align-items:center; gap:5px;'>
                    <img src='".WHB_URL."includes/images/class-icons/{$s->player_class}.png' style='width:16px; height:16px;'>
                    <strong style='color:$c;'>".esc_html($s->player_name)."</strong>
                </div>
            </div>";
        }
    }
}