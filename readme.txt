=== Easy PHP Settings ===
Plugin Name: Easy PHP Settings
Contributors: shahadul878,codereyes
Tags: php settings, ini, performance, debug, wp-config
Requires at least: 5.0
Tested up to: 7.1
Stable tag: 1.1.9
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An easy way to manage common PHP INI settings and WordPress debugging constants from the WordPress admin panel.

== Description ==

Easy PHP Settings provides a user-friendly interface to view and manage crucial PHP and WordPress configurations without needing to manually edit server files. It's designed for both single-site and multisite installations, giving administrators the power to optimize their environment directly from the dashboard.

**Key Features:**

*   **Manage PHP Settings:** Easily modify the 5 core PHP settings (`memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`, `max_input_vars`) through dedicated fields.
*   **Custom php.ini Configuration:** Add any additional PHP directives (session settings, timezone, logging, file uploads, etc.) directly in the flexible custom configuration textarea.
*   **Quick Presets:** Choose from pre-configured optimization profiles (Default, Performance, WooCommerce, Development, Large Media) that populate both core fields and custom php.ini directives automatically.
*   **WordPress Memory Management:** Configure WordPress-specific memory limits including `WP_MEMORY_LIMIT` and `WP_MAX_MEMORY_LIMIT` to optimize your site's performance.
*   **Automatic Configuration:** When you save your settings, the plugin automatically generates `.user.ini` and `php.ini` files in your WordPress root directory.
*   **Configuration Generator:** For locked-down environments, the plugin provides a generator to create configuration snippets that you can manually add to your server files.
*   **PHP Extensions Viewer:** View all loaded PHP extensions categorized by type, with indicators for critical missing extensions and recommendations.
*   **Settings Validation:** Automatically detects potentially problematic configuration values and warns you before saving.
*   **Settings History:** Track all changes made to your settings with the ability to restore previous configurations. Export history as CSV.
*   **Import/Export:** Backup your settings as JSON files and migrate configurations between sites effortlessly.
*   **One-Click Reset:** Reset to recommended values or server defaults with automatic backup creation.
*   **Helpful Tooltips:** Hover over help icons next to each setting to understand what it does and why it matters.
*   **Live Status Checker:** A dedicated "Status" tab shows your current server environment, including PHP version, server software, and a comparison of current vs. recommended PHP values.
*   **WordPress Debugging:** A "Debugging" tab with on/off switches lets you easily toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, and `SCRIPT_DEBUG` constants in your `wp-config.php` file.
*   **Multisite Compatible:** On multisite networks, settings are managed at the network level by Super Admins.
*   **WP-CLI Command:** Manage settings from the terminal with `wp easy-php list`, `get`, `set`, `debug on|off`, and `remove-ini`. Changes go through the same validation, backups, and history as the admin screen.

This plugin is perfect for developers and site administrators who want a quick and safe way to view and adjust their site's technical settings.

**Privacy & data sharing:**

Easy PHP Settings includes an **optional**, **default-off** integration with Plugin Tracker (CodeEyes) at https://plugin.codereyes.com/. **No data is sent until an administrator explicitly opts in** from the About tab or the optional admin notice.

If you opt in, the following may be transmitted: site URL; WordPress, PHP, and plugin versions; server software; administrator email and display name; and lists of installed plugins and themes (name, version, active status). Data is sent once when you opt in, then at most once per week (scheduled task), at most once per hour during admin use, after plugin updates, and once on deactivation if you had opted in. Purpose: improve plugin support and related services/offers.

You can revoke consent at any time under **Tools → Easy PHP Settings → About → Privacy & Data Sharing**. The plugin works fully without enabling data sharing. See the **Privacy Policy** section below for full details.

== Pro Features ==

Upgrade to Easy PHP Settings Pro for advanced controls, automation, and tooling designed for performance, safety, and team productivity.

=== Advanced PHP & Server Controls ===

* Manage all PHP INI directives (memory, upload, post size, execution time, input vars, OPcache, sessions, error_reporting).
* Advanced Config Generator (Apache .htaccess, NGINX snippets, cPanel/LiteSpeed compatibility).
* Per-site overrides in Multisite (instead of only Network Admin).
* PHP Extension Checker → Detects missing extensions (imagick, intl, bcmath, etc.) and gives install guidance.
* Real-time Server Health Monitor → CPU, RAM, disk usage, PHP-FPM pool stats.

=== Optimization & Performance ===

* One-click Optimization Profiles (ready presets):
    * WooCommerce Stores
    * Elementor / Page Builders
    * LMS (LearnDash, TutorLMS)
    * High Traffic Blogs
    * Multisite Networks
* Smart Recommendations → Suggest best values based on your hosting/server.
* OPcache Manager → Enable/disable and tune OPcache.

=== Safety & Reliability ===

* Backup & Restore Configurations (before/after editing .user.ini & php.ini).
* Safe Mode → If wrong values break the site, plugin auto-rolls back to last working config.
* Error Log Viewer → View PHP error logs and debug logs directly from dashboard.
* Email Alerts & Notifications → Sends warnings if PHP limits are too low, or site hits memory/time limits.

=== Productivity & Agency Tools ===

* Import / Export Settings → Save your preferred config and apply on other sites.
* Multi-Site Templates → Apply one config across the network.
* White-label Option → Rebrand plugin for agencies (hide “Easy PHP Settings” branding).
* Role-based Access → Allow only specific roles (like Admins, Developers) to change PHP settings.

=== Premium Experience ===

* Priority Support (faster replies, email/ticket).
* Regular Pro Updates with new hosting compatibility.
* Advanced Documentation & Tutorials (step-by-step setup guides).

=== Summary (Pro Highlights) ===

* Advanced Settings (all directives, OPcache, sessions)
* Profiles (WooCommerce, LMS, high traffic, etc.)
* Monitoring (server health, error logs)
* Backup/Restore + Safe Mode
* Import/Export & Agency Tools
* Alerts & Notifications
* Premium Support

== Installation ==

1.  Upload the `easy-php-settings` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Tools > PHP Settings** to configure the plugin. For multisite installations, go to **Network Admin > Settings > PHP Settings**.

== Frequently Asked Questions ==

= Why can't I change some settings directly? =

Some PHP settings are locked at the server level for security or performance reasons. Your hosting provider determines which settings can be modified at runtime. For these settings, our plugin provides a "Configuration Generator" to give you the code snippets you need to place in your server's configuration files (like `.user.ini` or `.htaccess`).

= Where are the .user.ini and php.ini files saved? =

When you save settings, the plugin automatically creates these files in the root directory of your WordPress installation (the same directory where `wp-config.php` is located). It writes only between `; BEGIN Easy PHP Settings` and `; END Easy PHP Settings` lines, so content added by other plugins (such as a firewall) is kept.

= Can I manage settings with WP-CLI? =

Yes. Examples:

`wp easy-php list` shows saved and recommended values.
`wp easy-php set memory_limit 512M` saves a PHP setting and writes the INI files.
`wp easy-php set wp_memory_limit 256M` updates `WP_MEMORY_LIMIT` in `wp-config.php` (backed up first).
`wp easy-php debug on` turns on `WP_DEBUG` and `WP_DEBUG_LOG` without showing errors to visitors. Add `--display` or `--script-debug` to turn those on too, or use `wp easy-php debug off`.
`wp easy-php remove-ini` removes this plugin's lines from `.user.ini` and `php.ini`.

Run `wp help easy-php` for full details. Note that PHP on the command line uses its own `php.ini` and does not read `.user.ini`, so check the Status tab for the values your website actually uses.

= What do the switches on the Debugging tab do? =

These switches directly control the debugging constants in your `wp-config.php` file. Toggling them on or off will define or update the corresponding constant (`WP_DEBUG`, `WP_DEBUG_LOG`, etc.), allowing you to easily enable or disable WordPress debugging modes.

= What is WP_MEMORY_LIMIT and why is it important? =

`WP_MEMORY_LIMIT` is a WordPress-specific constant that controls the amount of memory allocated to WordPress for its operations. It's different from PHP's `memory_limit` as it specifically affects WordPress processes. This setting is crucial for sites with many plugins, themes, or heavy content management. The plugin allows you to easily configure this setting to prevent "Allowed memory size exhausted" errors and improve your site's performance.

== Screenshots ==

1. General Settings — Live server summary, one-click presets, and PHP limits that show the value in use and whether your saved value has taken effect.
2. Tools — WordPress debugging switches with plain-language explanations, plus the log viewer, export/import, and reset options.
3. PHP Settings — Full table of all PHP directives with search and copy functionality.
4. Extensions — View all loaded PHP extensions by category with missing extension alerts.
5. Status — Live comparison of current vs. recommended PHP and WordPress memory values.
6. About — Author profile with skills, services and a Hire me button, plus plugin details and privacy & data sharing controls.

== Privacy Policy ==

This plugin can optionally send site information to a third-party service when an administrator explicitly opts in. **Data sharing is disabled by default.**

**Third-party service:** Plugin Tracker (CodeEyes) — https://plugin.codereyes.com/

**Privacy Policy:** https://codereyes.com/privacy-policy/

**Terms of Service:** https://github.com/shahadul878/easy-php-settings/blob/master/legal/plugin-tracker-terms.md

**Data that may be sent after opt-in:**
* Site URL
* WordPress, PHP, and Easy PHP Settings plugin versions
* Server software
* Administrator email address and display name
* Installed plugins and themes (name, version, active status)

**When data is sent:** Once when you opt in; then at most once per week via a scheduled task; at most once per hour during admin dashboard use; after plugin updates; and once on plugin deactivation if you had opted in.

**Purpose:** Improve plugin support, service updates, and optional offers from the developer.

**How to enable or revoke:** Go to **Tools → Easy PHP Settings → About → Privacy & Data Sharing**. Use **Allow data sharing** to opt in or **Revoke consent** to stop all future transmission and clear scheduled sync tasks.

**No data before opt-in:** Activating or using Easy PHP Settings does not send any information to plugin.codereyes.com until you explicitly allow it.

== External services ==

Easy PHP Settings can optionally connect to **Plugin Tracker**, a service operated by CodeEyes at https://plugin.codereyes.com/. This is used only after an administrator opts in from Tools → Easy PHP Settings → About. The plugin works fully without this connection.

**What the service is used for:** Plugin support, compatibility insight, and optional service offers related to Easy PHP Settings.

**What data is sent and when:** After opt-in, the plugin may send the site URL; WordPress, PHP, and plugin versions; server software; the administrator email address and display name; and installed plugins and themes (name, version, active status). Data may be sent once on opt-in, at most once per week on a schedule, at most once per hour during wp-admin use, after plugin updates, and once on deactivation if you had opted in.

**Terms of Service:** https://github.com/shahadul878/easy-php-settings/blob/master/legal/plugin-tracker-terms.md

**Privacy Policy:** https://codereyes.com/privacy-policy/

== Changelog ==

= 1.1.9 =
Released: November 6, 2026

* Fixed: Saving settings no longer overwrites other content in .user.ini and php.ini (for example the Wordfence firewall's auto_prepend_file line). The plugin now writes only between "; BEGIN Easy PHP Settings" and "; END Easy PHP Settings" markers, and converts files written by older versions on the next save.
* Changed: "Remove .ini Directives" (previously "Delete .ini Files") removes only this plugin's lines and deletes a file only if nothing else is left in it.
* Changed: Redesigned admin screen. A new header shows PHP version, server API, whether .user.ini is read, and whether the site root is writable.
* Changed: Each PHP and WordPress memory setting now shows the value in use, how it compares to the recommendation, and whether your saved value has taken effect (with manual snippets when it has not).
* Changed: Presets are clickable cards that fill the form without leaving the page; changed fields are highlighted and a save bar counts unsaved changes and warns before you leave the page.
* Changed: Debugging switches now explain what each constant does.
* Changed: The About tab is laid out as a CV (profile, skills, services, selected work) with "Hire me" buttons that open an email.
* Changed: Admin notices, including the data-sharing notice, appear above the plugin header.
* Changed: The review request no longer pops up on every admin page after 7 days. It appears on the General tab only when the plugin's live check confirms your saved PHP settings are in use, and "Not now" waits 30 days.
* Changed: The optional data-sharing notice is now compact: a one-line summary with Allow and No thanks buttons. What is sent, when, how to turn it off, and the Privacy Policy and Terms links are under "Learn more".
* Fixed: Choosing a preset now updates the custom php.ini editor; before, the editor kept its old text and saved over the preset.
* Fixed: The Status tab's "Changeable" column said "No" for upload_max_filesize, post_max_size and max_input_vars even on servers where the plugin sets them through .user.ini. It is now "How to change it": this plugin (.user.ini), .htaccess (Apache module), or host only.
* Fixed: A JavaScript error on tabs without the custom php.ini editor.
* Fixed: "Use recommended" is offered only when the current value is below the recommendation, so it never suggests lowering a limit.
* Removed: The "Test Settings" button, which only showed a timed message; the per-setting "In use" check replaces it.
* Added: Two Site Health checks: "PHP limits" (flags values below the recommendation) and "Saved PHP settings in use" (flags saved values the server ignores).
* Added: The General tab re-checks the values PHP really uses when it loads, with a "Check again" button. Admin pages can show values the plugin set only for that page; the check reads them without that.
* Added: An alert on the General tab when the server keeps ignoring saved settings, with a ready-to-send message for your host.
* Added: Under the Media uploader, a note saying which PHP setting sets the upload limit, with a link to change it.
* Changed: The debug log viewer groups repeated errors with a count, shows the newest first, filters by type (fatal, warning, notice, deprecated, database), and keeps the raw log one click away. It now reads the last 256 KB instead of 64 KB.
* Fixed: Deleting the plugin now removes its lines from .user.ini and php.ini (other plugins' lines are kept).
* Fixed: memory_limit = -1 and max_execution_time = 0 (no limit) are no longer shown as "Below recommended".
* Added: `wp easy-php` WP-CLI command with `list`, `get`, `set`, `debug on|off`, and `remove-ini` subcommands. Run `wp help easy-php` for details.
* Changed: Settings history records "wp-cli" as the user for changes made from the command line.
* Fixed: If writing debugging constants to wp-config.php fails, the stored debugging settings are no longer changed to values that were never applied.
* Dev: Added PHPUnit unit tests for the INI merge logic and the WP-CLI command (`composer install && composer test`).

= 1.1.8 =
Released: September 2, 2026

* Fixed: Admin CSS is scoped to the plugin screen and no longer restyles core Dashboard buttons or tables.
* Fixed: Plugin Check errors — translators comments, escaped exception messages, filesystem APIs, and Tested up to 7.1.
* Fixed: Dashboard widget and admin notices load only their own stylesheets, not the full plugin admin CSS.
* Fixed: About tab author image is a local asset (no GitHub avatar request).
* Fixed: Review link points to the full reviews page instead of a 5-star filtered view.
* Fixed: Plugin URI now points to the public GitHub repository.
* Fixed: wp-config.php backups are stored as non-executable ZIP archives in the uploads directory.
* Added: Plugin Tracker Terms of Service and Privacy Policy links in the readme and opt-in UI.

= 1.1.7 =
Released: August 30, 2026

* Fixed: WordPress.org Guideline 7 compliance — all communication with plugin.codereyes.com is gated behind explicit, default-off administrator opt-in.
* Added: Opt-in admin notice with full disclosure and Allow / No thanks actions; About tab privacy panel with enable and revoke controls.
* Added: Suggested privacy policy content via wp_add_privacy_policy_content().
* Changed: Plugin activation no longer sends install reports or schedules tracker cron until consent is granted.
* Changed: Dashboard offers widget, offer notices, and external offer images load only after opt-in.
* Updated: Readme privacy documentation to accurately describe optional data sharing.

= 1.1.6 =
Released: June 20, 2026

* Improved: Complete admin UI redesign with hero header, icon tabs, card-based layout, and responsive design system across all tabs.
* Improved: Redesigned save button with sticky footer bar, gradient styling, and loading spinner overlay during form submission.
* Improved: PHP Settings table with search toolbar, status badges, select-all, and copy-selected actions.
* Improved: Advanced Tools section combining Configuration Generator, Test Settings, and Danger Zone into a unified grid layout.
* Added: Dashboard widget offers displayed as post-style cards with images from the plugin tracker API (requires opt-in as of 1.1.7).
* Added: 7-day countdown admin notice for new tracker offers with dismiss and snooze actions (requires opt-in as of 1.1.7).
* Improved: Tracker offer cache refresh and hourly admin sync for up-to-date dashboard data (requires opt-in as of 1.1.7).

= 1.1.5 =
Released: May 27, 2026

* Added: Admin notice asking the user to leave a 5-star review on WordPress.org after the plugin has been installed for 7 days. Includes "Rate now", "Maybe later" (7-day snooze), and "Already did / Don't show again" actions.
* Note: Tracker behavior in 1.1.5 was superseded by explicit opt-in requirements in 1.1.7.

= 1.1.4 =
Released: March 14, 2026

* Fixed: Removed unbalanced-parentheses validation that caused false "Unbalanced parentheses detected in config file" errors on live servers when wp-config.php contains parentheses inside strings or comments.

= 1.1.3 =
Released: March 14, 2026

* Fixed: wp-config.php validation now detects required DB_* constants with flexible spacing/quoting, preventing false “DB_NAME not found” errors on some hosts.

= 1.1.2 =
Released: March 8, 2026

* Fixed: Debugging Constants switches not applying changes to wp-config.php due to outdated settings registration; now correctly updates WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, and SCRIPT_DEBUG.
* Changed: Removed hard 1G upper limit from size validators so larger values like 2G+ are accepted, relying on PHP/server limits and relationship checks instead.

= 1.1.1 =
Released: March 8, 2026

* Added: Plugin usage tracker integration — optional anonymous install/activation reporting to improve plugin services (site URL, WordPress version, plugin version; no personally identifiable data in clear text)
* Added: Admin notice explaining data collection with link to privacy details
* Improved: Tracker integration runs on activation and sends version/site info for service updates

= 1.1.0 =
Released: March 5, 2026

* Refactored: Complete modular architecture — main plugin file reduced from 2,000+ lines to ~300 lines
* Refactored: All features moved into self-contained modules (General Settings, Tools, Status, Extensions, PHP Settings, About)
* Refactored: Each module owns its own settings registration, rendering, and action handling
* Fixed: Double settings registration that could cause unexpected behavior
* Fixed: Broken sanitize callback reference in General Settings module
* Fixed: Form/handler mismatch — export, import, and reset handlers now live in the Tools module alongside their forms
* Improved: Cleaner separation of concerns with public getters on the main class
* Improved: Tools tab now consolidates debugging settings, log viewer, export/import, and reset in one place
* Removed: Legacy duplicate file (includes/class-easy-php-settings.php) that referenced non-existent classes
* Removed: Empty placeholder directories (includes/admin, data, handlers, info, settings, utils)
* Removed: Unused duplicate assets and view directories
* Organized: Plugin submission assets moved to .wordpress-org/ directory
* Updated: GitHub Actions workflows aligned with new file structure
* Updated: .distignore cleaned up for accurate distribution builds

= 1.0.5 =
* Enhanced: Improved wp-config.php editing security with proper parser and backup/restore functionality
* Enhanced: Added comprehensive input validation for all PHP settings
* Enhanced: Implemented caching layer for PHP info, extensions, and history using WordPress transients
* Enhanced: Added pagination to history display for better performance
* Enhanced: Improved error handling with structured logging and recovery mechanisms
* Enhanced: Added real-time validation feedback in admin interface
* Enhanced: Improved JavaScript with debouncing, loading states, and keyboard shortcuts
* Enhanced: Added ARIA labels and improved accessibility throughout the plugin
* Enhanced: Added file operation security with path validation and atomic writes
* Fixed: Improved error messages and user feedback
* Security: Enhanced file upload validation for import functionality
* Security: Added backup before wp-config.php modifications with automatic rollback on failure

= 1.0.4 =
* Added: Quick Settings Presets - Choose from 5 optimization profiles (Default, Performance, WooCommerce, Development, Large Media). Each preset includes optimized values for core settings and custom php.ini directives.
* Added: PHP Extensions Viewer tab - View all loaded PHP extensions categorized by type with missing extension alerts.
* Added: Settings History tracking - Track all changes with ability to restore previous configurations and export as CSV.
* Added: Import/Export functionality - Backup and migrate settings between sites as JSON files.
* Added: One-Click Reset - Reset to recommended values or server defaults with automatic backups.
* Added: Settings Validation - Automatic detection of problematic configuration values with warnings.
* Added: Helpful Tooltips - Help icons next to each setting explaining what it does.
* Added: New Tools tab for Import/Export and Reset operations.
* Enhanced: Custom php.ini Configuration textarea now includes helpful examples and placeholder text for common directives (session, timezone, logging, file uploads, etc.).
* Enhanced: Improved UI with better organization and visual indicators.
* Enhanced: Client-side validation with warnings before saving.
* Enhanced: Better status indicators with color-coded warnings.
* Enhanced: Search functionality for extensions.
* Improved: Code organization and documentation.
* Improved: Consolidated two save buttons into one "Save All Settings" button for better UX.

= 1.0.3 =
* Added: WordPress memory limit management (`WP_MEMORY_LIMIT` and `WP_MAX_MEMORY_LIMIT`) configuration.
* Enhanced: Better error handling and user feedback.
* Improved: Code documentation and inline comments.
* Updated: Documentation to include WordPress memory management features.

= 1.0.2 =
* Fixed: PHPCS coding standards compliance - resolved all errors and warnings.
* Improved: Extracted inline JavaScript to external file for better maintainability.
* Enhanced: Added proper documentation and function comments.
* Updated: File naming conventions to follow WordPress standards.
* Improved: Code organization and structure.

= 1.0.1 =
* Updated: Version bump and minor improvements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.9 =
Safer .user.ini and php.ini writes that keep other plugins' lines (e.g. Wordfence), a redesigned settings screen that shows whether your values are really in use, Site Health checks and a WP-CLI command.

= 1.1.8 =
WordPress.org review fixes: scoped admin CSS, local About avatar, unfiltered review URL, Plugin Tracker terms/privacy links, and ZIP (not plaintext PHP) wp-config backups.

= 1.1.7 =
Restores WordPress.org compliance: optional data sharing with plugin.codereyes.com is disabled by default and requires explicit administrator opt-in. No outbound tracker requests until you allow it from the About tab.

= 1.1.6 =
Major admin UI refresh with redesigned settings pages, improved save experience, Advanced Tools section, and enhanced plugin tracker dashboard offers.

= 1.1.5 =
Adds a friendly admin notice asking long-time users to leave a 5-star review on WordPress.org. Fixes plugin usage tracker to send data on activation with full site details.

= 1.1.4 =
Fixes wp-config.php update failing on some live hosts with "Unbalanced parentheses" by removing the unreliable parentheses check.

= 1.1.3 =
Improves wp-config.php validation to avoid false missing DB constants errors on hosts that format define() statements differently.

= 1.1.2 =
Fixes Debugging Constants switches so they correctly update wp-config.php and removes the old 1G cap on size values, allowing higher limits where supported.

= 1.1.1 =
Adds optional plugin usage tracker for install/activation reporting. An admin notice explains what data is sent.

= 1.0.0 =
The first version of the plugin. No upgrade notice yet. 