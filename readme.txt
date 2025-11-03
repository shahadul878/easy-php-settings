=== Easy PHP Settings ===
Contributors: shahadul878
Tags: php settings, ini, performance, debug, wp-config
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.4
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

This plugin is perfect for developers and site administrators who want a quick and safe way to view and adjust their site's technical settings.

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

When you save settings, the plugin automatically creates these files in the root directory of your WordPress installation (the same directory where `wp-config.php` is located).

= What do the switches on the Debugging tab do? =

These switches directly control the debugging constants in your `wp-config.php` file. Toggling them on or off will define or update the corresponding constant (`WP_DEBUG`, `WP_DEBUG_LOG`, etc.), allowing you to easily enable or disable WordPress debugging modes.

= What is WP_MEMORY_LIMIT and why is it important? =

`WP_MEMORY_LIMIT` is a WordPress-specific constant that controls the amount of memory allocated to WordPress for its operations. It's different from PHP's `memory_limit` as it specifically affects WordPress processes. This setting is crucial for sites with many plugins, themes, or heavy content management. The plugin allows you to easily configure this setting to prevent "Allowed memory size exhausted" errors and improve your site's performance.

== Screenshots ==

1. The General Settings tab where users can input their desired PHP values.
2. The Configuration Generator, which provides code snippets for manual server configuration.
3. The Status tab, showing live PHP values and server environment details.
4. The Debugging tab, with toggle switches for WordPress debugging constants.

== Changelog ==

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

= 1.0.0 =
The first version of the plugin. No upgrade notice yet. 