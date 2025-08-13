=== Easy PHP Settings ===
Contributors: shahadul878
Tags: php settings, ini, performance, debug, wp-config
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.3
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An easy way to manage common PHP INI settings and WordPress debugging constants from the WordPress admin panel.

== Description ==

Easy PHP Settings provides a user-friendly interface to view and manage crucial PHP and WordPress configurations without needing to manually edit server files. It's designed for both single-site and multisite installations, giving administrators the power to optimize their environment directly from the dashboard.

**Key Features:**

*   **Manage PHP Settings:** Easily modify common PHP settings such as `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`, and `max_input_vars`.
*   **WordPress Memory Management:** Configure WordPress-specific memory limits including `WP_MEMORY_LIMIT` and `WP_MAX_MEMORY_LIMIT` to optimize your site's performance.
*   **Automatic Configuration:** When you save your settings, the plugin automatically generates `.user.ini` and `php.ini` files in your WordPress root directory.
*   **Configuration Generator:** For locked-down environments, the plugin provides a generator to create configuration snippets that you can manually add to your server files.
*   **Live Status Checker:** A dedicated "Status" tab shows your current server environment, including PHP version, server software, and a comparison of current vs. recommended PHP values.
*   **WordPress Debugging:** A "Debugging" tab with on/off switches lets you easily toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, and `SCRIPT_DEBUG` constants in your `wp-config.php` file.
*   **Multisite Compatible:** On multisite networks, settings are managed at the network level by Super Admins.

This plugin is perfect for developers and site administrators who want a quick and safe way to view and adjust their site's technical settings.

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