<?php
/**
 * Uninstall Easy PHP Settings
 *
 * Fired when the plugin is uninstalled (deleted). Removes all options,
 * transients, and scheduled events created by the plugin.
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$is_multisite = is_multisite();

/**
 * Delete an option (site or network depending on context).
 *
 * @param string $option Option name.
 */
$delete_option_fn = function ( $option ) use ( $is_multisite ) {
	if ( $is_multisite ) {
		delete_site_option( $option );
	} else {
		delete_option( $option );
	}
};

// Plugin options.
$option_keys = array(
	'easy_php_settings_settings',
	'easy_php_settings_wp_memory_settings',
	'easy_php_settings_debugging_settings',
	'easy_php_settings_import_backup',
	'easy_php_settings_reset_backup',
	'easy_php_settings_history',
	'easy_php_settings_error_log',
);

foreach ( $option_keys as $key ) {
	$delete_option_fn( $key );
}

// Config backups (option names like easy_php_settings_config_backup_1234567890).
if ( $is_multisite ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE %s",
			 $wpdb->esc_like( 'easy_php_settings_config_backup_' ) . '%'
		)
	);
} else {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
			$wpdb->esc_like( 'easy_php_settings_config_backup_' ) . '%'
		)
	);
}

// Plugin transients (easy_php_settings_cache_*, easy_php_settings_log_cleared).
// Transients are stored as _transient_* and _transient_timeout_* in options.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_easy_php_settings_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_easy_php_settings_' ) . '%'
	)
);

// Plugin Tracker integration: cron and options.
$main_plugin_file = dirname( __FILE__ ) . '/class-easy-php-settings.php';
$basename        = plugin_basename( $main_plugin_file );
$cron_option     = 'plugin_tracker_cron_' . md5( $basename );
$hook_name       = 'plugin_tracker_ping_' . preg_replace( '/[^a-z0-9_-]/i', '_', $basename );

wp_clear_scheduled_hook( $hook_name );
delete_option( $cron_option );
delete_option( 'plugin_tracker_plugin_file' );

// Plugin Tracker transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_plugin_tracker_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_plugin_tracker_' ) . '%'
	)
);
