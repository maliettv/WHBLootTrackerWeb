=== WHB Loot Tracker & Progression ===
Contributors: Justin Bowman 
Tags: wow, tbc, loot, wowhead, progression, roster
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later

Modular Guild Management for the Waffle House Brawlers (TBC Anniversary).

== Description ==

The WHB Loot Tracker & Progression plugin is a custom-built, lightweight guild management tool designed specifically for TBC Anniversary. It allows your guild to easily import loot history, track raid progression, and manage your player roster all from the WordPress dashboard.

Features include:
* **AJAX Loot Viewer:** A fast, searchable, and filterable loot history table with Wowhead tooltip integration.
* **Raid Progression:** Dynamic progress bars tracking your guild's kills from Karazhan to Sunwell Plateau.
* **Player Roster:** Assign roles (Tank, Healer, DPS) and specific TBC classes to players, automatically coloring their names and adding role icons in the frontend loot viewer.
* **Customizable Themes:** Choose between Light, Dark, or Custom Hex color schemes to perfectly match your guild's website aesthetic.

== Installation ==

1. Upload the entire `whb-loot-tracker` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the new 'WHB Tracker' menu in your WordPress admin sidebar.
4. Import your WHBLootTracker Classic CSV file via the Loot tab.
5. Use the shortcodes `[loot_viewer]` and `[raid_progression]` on any page or post to display the tools.

== Frequently Asked Questions ==

= How do I update my raid progression? =
Go to the WHB Tracker menu, click the "Raid Progression" tab, check the boxes next to the bosses your guild has defeated, and click save. The progress bars on your website will automatically recalculate.

= Why aren't my Wowhead tooltips showing? =
Make sure you have imported valid item IDs from your CSV. The plugin automatically enqueues the Wowhead power.js script and forces it to re-initialize whenever the AJAX search or pagination is used.

== Changelog ==

= 1.0.1 =
* **Major Rewrite:** Transitioned the entire codebase to a fully modular file architecture (`admin-panel.php`, `ajax-handlers.php`, `shortcodes.php`, `db-setup.php`) for improved performance and easier maintenance.
* **New Feature:** Added the Player Roster tab to assign standard TBC class colors and role icons to members.
* **New Feature:** Added Raid Progression tracking and the `[raid_progression]` shortcode.
* **Enhancement:** Implemented AJAX pagination for the loot viewer to handle large, multi-group loot histories efficiently.
* **Enhancement:** Added Light, Dark, and Custom Hex theme options for the frontend UI.