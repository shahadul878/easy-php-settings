=== Easy PHP Settings ===
Contributors: shahadul878
Tags: php settings, ini, performance, debug, wp-config
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An easy way to manage common PHP INI settings and WordPress debugging constants from the WordPress admin panel.

== Description ==

Easy PHP Settings provides a user-friendly interface to view and manage crucial PHP and WordPress configurations without needing to manually edit server files. It's designed for both single-site and multisite installations, giving administrators the power to optimize their environment directly from the dashboard.

**Key Features:**

*   **Manage PHP Settings:** Easily modify common PHP settings such as `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`, and `max_input_vars`.
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

== Screenshots ==

1. The General Settings tab where users can input their desired PHP values.
2. The Configuration Generator, which provides code snippets for manual server configuration.
3. The Status tab, showing live PHP values and server environment details.
4. The Debugging tab, with toggle switches for WordPress debugging constants.

== Changelog ==

= 1.0.1 =
* Updated: Version bump and minor improvements.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
The first version of the plugin. No upgrade notice yet. 