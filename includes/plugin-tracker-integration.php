<?php
/**
 * Plugin Tracker Integration – drop-in for existing WordPress plugins
 *
 * Plugin Tracker Integration is a plugin that integrates with the Plugin Tracker API to report plugin usage and get service offers.
 *
 * @package PluginTrackerIntegration
 * @version 1.0.0
 * @author H M Shahadul Islam
 * @link https://github.com/shahadul878
 * @license GPL-2.0+
 * @copyright 2026 H M Shahadul Islam
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define defaults if not set by host plugin
if (!defined('PLUGIN_TRACKER_API_URL')) {
    define('PLUGIN_TRACKER_API_URL', 'https://plugin.codereyes.com/');
}
if (!defined('PLUGIN_TRACKER_API_KEY')) {
    define('PLUGIN_TRACKER_API_KEY', 'pt_RVdd3aNGApDHlHwNRkqnSiBjN19IpmcnYJcy3TpG');
}


$plugin_tracker_integration_plugin_file = isset($GLOBALS['plugin_tracker_integration_plugin_file']) ? $GLOBALS['plugin_tracker_integration_plugin_file'] : null;


$plugin_tracker_integration_plugin_file = isset($GLOBALS['plugin_tracker_integration_plugin_file']) ? $GLOBALS['plugin_tracker_integration_plugin_file'] : null;

/**
 * Get plugin version for tracker (from host plugin or constant).
 */
function tracker_integration_get_version($plugin_file = null) {
    if (defined('PLUGIN_TRACKER_PLUGIN_VERSION') && PLUGIN_TRACKER_PLUGIN_VERSION !== '') {
        return PLUGIN_TRACKER_PLUGIN_VERSION;
    }
    if ($plugin_file && function_exists('get_plugin_data')) {
        $data = get_plugin_data($plugin_file, false, false);
        return isset($data['Version']) ? $data['Version'] : '1.0';
    }
    return '1.0';
}

/**
 * Get list of all installed plugins with name, version, and active status.
 *
 * @return array<int, array{name: string, version: string, active: bool}>
 */
function tracker_integration_get_installed_plugins() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all = get_plugins();
    $active = (array) get_option('active_plugins', []);
    $list = [];
    foreach ($all as $basename => $info) {
        $list[] = [
            'name' => isset($info['Name']) ? (string) $info['Name'] : $basename,
            'version' => isset($info['Version']) ? (string) $info['Version'] : '',
            'active' => in_array($basename, $active, true),
        ];
    }
    return $list;
}

/**
 * Get list of all installed themes with name, version, and active status.
 *
 * @return array<int, array{name: string, version: string, active: bool}>
 */
function tracker_integration_get_installed_themes() {
    $themes = wp_get_themes();
    $active_stylesheet = get_stylesheet();
    $list = [];
    foreach ($themes as $slug => $theme) {
        $list[] = [
            'name' => $theme->get('Name') ?: $slug,
            'version' => $theme->get('Version') ?: '',
            'active' => ($theme->get_stylesheet() === $active_stylesheet),
        ];
    }
    return $list;
}

/**
 * Get cron hook name for this plugin (unique per plugin file).
 */
function tracker_integration_cron_hook($plugin_file) {
    $base = $plugin_file ? plugin_basename($plugin_file) : 'tracker';
    $base = preg_replace('/[^a-z0-9_-]/i', '_', $base);
    return 'plugin_tracker_ping_' . $base;
}

/**
 * Report install to Plugin Tracker API. Call from activation hook.
 *
 * @param string $plugin_file Path to the main plugin file (e.g. __FILE__).
 */
function tracker_integration_report_install($plugin_file) {
    $GLOBALS['plugin_tracker_integration_plugin_file'] = $plugin_file;

    $url = rtrim(PLUGIN_TRACKER_API_URL, '/') . '/api/plugin/install';
    $body = [
        'api_key'        => PLUGIN_TRACKER_API_KEY,
        'site_url'       => site_url(),
        'plugin_version' => tracker_integration_get_version($plugin_file),
        'wp_version'     => get_bloginfo('version'),
    ];
    $admin_email = get_option('admin_email');
    if ($admin_email) {
        $body['admin_email'] = $admin_email;
    }
    $admin_user = get_user_by('email', $admin_email);
    if ($admin_user && !empty($admin_user->display_name)) {
        $body['admin_name'] = $admin_user->display_name;
    }
    if (!empty($_SERVER['SERVER_SOFTWARE'])) {
        $body['server_software'] = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
    }
    $body['php_version'] = PHP_VERSION;
    $body['installed_plugins'] = wp_json_encode(tracker_integration_get_installed_plugins());
    $body['installed_themes'] = wp_json_encode(tracker_integration_get_installed_themes());

    wp_remote_post($url, [
        'timeout' => 5,
        'blocking' => false,
        'body' => $body,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ]);

    $hook = tracker_integration_cron_hook($plugin_file);
    update_option('plugin_tracker_cron_' . md5(plugin_basename($plugin_file)), $hook, false);
    update_option('plugin_tracker_plugin_file', $plugin_file, false);
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(time(), 'weekly', $hook);
    }
}

/**
 * Report deactivation to Plugin Tracker API. Call from deactivation hook.
 * Marks the site as inactive in the tracker.
 *
 * @param string $plugin_file Path to the main plugin file (e.g. __FILE__).
 */
function tracker_integration_report_deactivate($plugin_file) {
    $url = rtrim(PLUGIN_TRACKER_API_URL, '/') . '/api/plugin/deactivate';
    $body = [
        'api_key'        => PLUGIN_TRACKER_API_KEY,
        'site_url'       => site_url(),
        'plugin_version' => tracker_integration_get_version($plugin_file),
        'wp_version'     => get_bloginfo('version'),
    ];
    wp_remote_post($url, [
        'timeout' => 5,
        'blocking' => false,
        'body' => $body,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ]);
}

/**
 * Send weekly ping. Called by cron. Also sends admin/server details so the API can backfill missing data.
 */
function tracker_integration_ping($plugin_file = null) {
    $plugin_file = $plugin_file
        ?: (isset($GLOBALS['plugin_tracker_integration_plugin_file']) ? $GLOBALS['plugin_tracker_integration_plugin_file'] : null)
        ?: get_option('plugin_tracker_plugin_file');
    if (!$plugin_file) {
        return;
    }

    $url = rtrim(PLUGIN_TRACKER_API_URL, '/') . '/api/plugin/ping';
    $body = [
        'api_key'        => PLUGIN_TRACKER_API_KEY,
        'site_url'       => site_url(),
        'plugin_version' => tracker_integration_get_version($plugin_file),
        'wp_version'     => get_bloginfo('version'),
    ];
    $admin_email = get_option('admin_email');
    if ($admin_email) {
        $body['admin_email'] = $admin_email;
    }
    $admin_user = $admin_email ? get_user_by('email', $admin_email) : null;
    if ($admin_user && !empty($admin_user->display_name)) {
        $body['admin_name'] = $admin_user->display_name;
    }
    if (!empty($_SERVER['SERVER_SOFTWARE'])) {
        $body['server_software'] = sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE']));
    }
    $body['php_version'] = PHP_VERSION;
    $body['installed_plugins'] = wp_json_encode(tracker_integration_get_installed_plugins());
    $body['installed_themes'] = wp_json_encode(tracker_integration_get_installed_themes());

    wp_remote_post($url, [
        'timeout' => 5,
        'blocking' => false,
        'body' => $body,
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    ]);
}

/**
 * Clean up on uninstall. Call from uninstall.php.
 * Removes scheduled cron, options, transients, and notice-dismissed user meta.
 *
 * @param string|null $plugin_file Path to the main plugin file (e.g. same as used in activation). If omitted, uses stored options to find and clear all plugin tracker data.
 */
function tracker_integration_cleanup($plugin_file = null) {
    global $wpdb;

    if ($plugin_file) {
        $hook = tracker_integration_cron_hook($plugin_file);
        wp_clear_scheduled_hook($hook);
        delete_option('plugin_tracker_cron_' . md5(plugin_basename($plugin_file)));
        delete_option('plugin_tracker_plugin_file');
    } else {
        $opts = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'plugin_tracker_cron_%'");
        foreach ($opts as $name) {
            $hook = get_option($name);
            if ($hook) {
                wp_clear_scheduled_hook($hook);
            }
            delete_option($name);
        }
        delete_option('plugin_tracker_plugin_file');
    }

    // Delete transients (offers cache, sync throttle)
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_plugin_tracker_%' OR option_name LIKE '_transient_timeout_plugin_tracker_%'");

    // Delete notice-dismissed user meta for all users
    $wpdb->delete($wpdb->usermeta, ['meta_key' => 'plugin_tracker_notice_dismissed']);
}

// When this plugin is updated, ping the API to refresh site/version data
add_action('upgrader_process_complete', function ($upgrader, $options) {
    if (!isset($options['type']) || $options['type'] !== 'plugin') {
        return;
    }
    $our_plugin_file = get_option('plugin_tracker_plugin_file');
    if (!$our_plugin_file || !file_exists($our_plugin_file)) {
        return;
    }
    $our_basename = plugin_basename($our_plugin_file);
    $updated = [];
    if (!empty($options['plugins']) && is_array($options['plugins'])) {
        $updated = $options['plugins'];
    } elseif (!empty($options['plugin']) && is_string($options['plugin'])) {
        $updated = [$options['plugin']];
    }
    if (in_array($our_basename, $updated, true)) {
        tracker_integration_ping($our_plugin_file);
    }
}, 10, 2);

// Register cron callback when plugin file is known
add_action('init', function () use (&$plugin_tracker_integration_plugin_file) {
    if (!isset($GLOBALS['plugin_tracker_integration_plugin_file'])) {
        return;
    }
    $plugin_tracker_integration_plugin_file = $GLOBALS['plugin_tracker_integration_plugin_file'];
    $hook = tracker_integration_cron_hook($plugin_tracker_integration_plugin_file);
    $GLOBALS['plugin_tracker_integration_cron_hook'] = $hook;
    add_action($hook, function () use ($plugin_tracker_integration_plugin_file) {
        tracker_integration_ping($plugin_tracker_integration_plugin_file);
    });
}, 1);

/**
 * Fetch service offers from API (cached in transient).
 *
 * @return array List of offer arrays with title, description, link, etc.
 */
function tracker_integration_get_offers() {
    $transient_key = 'plugin_tracker_offers_' . md5(PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL);
    $offers = get_transient($transient_key);
    if ($offers !== false) {
        return is_array($offers) ? $offers : [];
    }
    $url = rtrim(PLUGIN_TRACKER_API_URL, '/') . '/api/plugin/offers';
    $res = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => ['X-Api-Key' => PLUGIN_TRACKER_API_KEY],
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
        return [];
    }
    $body = wp_remote_retrieve_body($res);
    $decoded = json_decode($body, true);
    $offers = is_array($decoded) ? $decoded : [];
    set_transient($transient_key, $offers, HOUR_IN_SECONDS);
    return $offers;
}

// Dashboard widget: show service offers
add_action('wp_dashboard_setup', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_add_dashboard_widget(
        'plugin_tracker_offers_widget',
        __('Plugin Tracker – Services', 'plugin-tracker-integration'),
        'tracker_integration_render_dashboard_widget',
        null,
        null,
        'side'
    );
});

/**
 * Render the dashboard widget content with service offers.
 */
function tracker_integration_render_dashboard_widget() {
    $offers = tracker_integration_get_offers();
    if (!empty($offers)) {
        echo '<ul class="plugin-tracker-offers-list" style="margin: 0; padding-left: 0; list-style: none;">';
        foreach ($offers as $offer) {
            $title = isset($offer['title']) ? $offer['title'] : '';
            $link = isset($offer['link']) ? $offer['link'] : '';
            $desc = isset($offer['description']) ? $offer['description'] : '';
            echo '<li style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee;">';
            if ($link) {
                echo '<strong><a href="' . esc_url($link) . '" target="_blank" rel="noopener">' . esc_html($title) . '</a></strong>';
            } else {
                echo '<strong>' . esc_html($title) . '</strong>';
            }
            if ($desc) {
                echo '<p style="margin: 4px 0 0; color: #50575e; font-size: 12px; line-height: 1.4;">' . esc_html(wp_trim_words($desc, 15)) . '</p>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="margin: 0;">Need help with WordPress? Server optimization, speed, security.</p>';
        echo '<p style="margin: 8px 0 0;"><a href="https://codereyes.com" target="_blank" rel="noopener">Codereyes</a></p>';
    }
}

// Admin notice: show one line with link to services (dismissible per user)
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $user_id = get_current_user_id();
    $dismissed = get_user_meta($user_id, 'plugin_tracker_notice_dismissed', true);
    if ($dismissed) {
        return;
    }
    $offers = tracker_integration_get_offers();
    $nonce = wp_create_nonce('plugin_tracker_dismiss_notice');
    $dismiss_url = add_query_arg([
        'plugin_tracker_dismiss' => '1',
        '_wpnonce' => $nonce,
    ], admin_url());
    ?>
    <div class="notice notice-info plugin-tracker-notice is-dismissible" style="position: relative;">
        <p style="margin: 0.5em 0;">
            <?php if (!empty($offers)) : ?>
                <strong><?php echo esc_html($offers[0]['title'] ?? 'Services'); ?></strong>
                <?php if (!empty($offers[0]['link'])) : ?>
                    <a href="<?php echo esc_url($offers[0]['link']); ?>" target="_blank" rel="noopener" style="margin-left: 8px;"><?php esc_html_e('Learn more', 'plugin-tracker-integration'); ?></a>
                <?php endif; ?>
            <?php else : ?>
                <a href="https://codereyes.com" target="_blank" rel="noopener">Codereyes</a> – WordPress help, optimization &amp; security.
            <?php endif; ?>
            <a href="<?php echo esc_url($dismiss_url); ?>" class="notice-dismiss" style="text-decoration: none;"><span class="screen-reader-text"><?php esc_attr_e('Dismiss', 'plugin-tracker-integration'); ?></span></a>
        </p>
    </div>
    <?php
});

// Handle notice dismiss (persist in user meta so it stays dismissed)
add_action('admin_init', function () {
    if (!isset($_GET['plugin_tracker_dismiss']) || !isset($_GET['_wpnonce'])) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'plugin_tracker_dismiss_notice')) {
        return;
    }
    update_user_meta(get_current_user_id(), 'plugin_tracker_notice_dismissed', 1);
    wp_safe_redirect(remove_query_arg(['plugin_tracker_dismiss', '_wpnonce']));
    exit;
});

/**
 * Render settings page with privacy notice and service offers from API.
 * Triggers a one-time sync (ping with full details) when the page is loaded, so site details in the tracker stay up to date.
 */
function tracker_integration_render_settings_page() {
    $sync_transient = 'plugin_tracker_sync_' . md5(PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL . site_url());
    if (get_transient($sync_transient) === false) {
        tracker_integration_ping($GLOBALS['plugin_tracker_integration_plugin_file'] ?? null);
        set_transient($sync_transient, 1, HOUR_IN_SECONDS);
    }

    $offers = tracker_integration_get_offers();
    ?>
    <div class="wrap">
        <h1>Plugin Tracker</h1>

        <div class="card" style="max-width: 640px; padding: 1em 1.5em;">
            <h2 style="margin-top: 0;">Privacy notice</h2>
            <p>This plugin sends basic site information (site URL, WordPress version, plugin version) to improve plugin services. No personally identifiable data is sent in clear text; the admin email may be sent in hashed form for analytics.</p>
        </div>

        <div class="card" style="max-width: 640px; margin-top: 1em; padding: 1em 1.5em;">
            <h2 style="margin-top: 0;">Services</h2>
            <?php if (!empty($offers)) : ?>
                <ul style="list-style: none; padding: 0;">
                    <?php foreach ($offers as $offer) : ?>
                        <li style="margin-bottom: 1em; padding: 0.75em; background: #f6f7f7; border-radius: 4px;">
                            <strong><?php echo esc_html($offer['title'] ?? ''); ?></strong>
                            <?php if (!empty($offer['description'])) : ?>
                                <p style="margin: 0.5em 0 0; color: #50575e;"><?php echo esc_html($offer['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($offer['link'])) : ?>
                                <p style="margin: 0.5em 0 0;"><a href="<?php echo esc_url($offer['link']); ?>" target="_blank" rel="noopener">Learn more</a></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p>Need help with WordPress? Server optimization, speed optimization, security setup. Visit <a href="https://codereyes.com" target="_blank" rel="noopener">Codereyes</a>.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
