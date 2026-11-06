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

/**
 * Remove plugin data from the current site (and network options when applicable).
 *
 * @return void
 */
function easy_php_settings_uninstall() {
	global $wpdb;

	$easy_php_settings_is_multisite = is_multisite();

	$easy_php_settings_option_keys = array(
		'easy_php_settings_settings',
		'easy_php_settings_wp_memory_settings',
		'easy_php_settings_debugging_settings',
		'easy_php_settings_import_backup',
		'easy_php_settings_reset_backup',
		'easy_php_settings_history',
		'easy_php_settings_error_log',
		'easy_php_settings_last_saved',
	);

	foreach ( $easy_php_settings_option_keys as $easy_php_settings_key ) {
		if ( $easy_php_settings_is_multisite ) {
			delete_site_option( $easy_php_settings_key );
		} else {
			delete_option( $easy_php_settings_key );
		}
	}

	if ( $easy_php_settings_is_multisite ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of leftover backup options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->sitemeta WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'easy_php_settings_config_backup_' ) . '%'
			)
		);
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of leftover backup options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
				$wpdb->esc_like( 'easy_php_settings_config_backup_' ) . '%'
			)
		);
	}

	$easy_php_settings_uploads = wp_upload_dir( null, false );
	if ( empty( $easy_php_settings_uploads['error'] ) && ! empty( $easy_php_settings_uploads['basedir'] ) ) {
		$easy_php_settings_backup_dir = trailingslashit( $easy_php_settings_uploads['basedir'] ) . 'easy-php-settings-backups';
		if ( is_dir( $easy_php_settings_backup_dir ) ) {
			$easy_php_settings_entries = glob( trailingslashit( $easy_php_settings_backup_dir ) . '*' );
			if ( is_array( $easy_php_settings_entries ) ) {
				foreach ( $easy_php_settings_entries as $easy_php_settings_entry ) {
					if ( is_file( $easy_php_settings_entry ) ) {
						wp_delete_file( $easy_php_settings_entry );
					}
				}
			}
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			global $wp_filesystem;
			if ( $wp_filesystem ) {
				$wp_filesystem->rmdir( $easy_php_settings_backup_dir );
			}
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of plugin transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_easy_php_settings_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_easy_php_settings_' ) . '%'
		)
	);

	$easy_php_settings_tracker_file = dirname( __FILE__ ) . '/includes/plugin-tracker-integration.php';
	$easy_php_settings_main_file    = dirname( __FILE__ ) . '/class-easy-php-settings.php';

	if ( file_exists( $easy_php_settings_tracker_file ) ) {
		require_once $easy_php_settings_tracker_file;

		$easy_php_settings_had_consent = '1' === get_option( 'plugin_tracker_consent', '0' );
		$easy_php_settings_plugin_file = get_option( 'plugin_tracker_plugin_file', $easy_php_settings_main_file );

		if ( $easy_php_settings_had_consent && $easy_php_settings_plugin_file && function_exists( 'tracker_integration_report_deactivate' ) ) {
			tracker_integration_report_deactivate( $easy_php_settings_plugin_file );
		}

		if ( function_exists( 'tracker_integration_cleanup' ) ) {
			tracker_integration_cleanup( $easy_php_settings_main_file );
		}
	} else {
		$easy_php_settings_basename    = plugin_basename( $easy_php_settings_main_file );
		$easy_php_settings_cron_option = 'plugin_tracker_cron_' . md5( $easy_php_settings_basename );
		$easy_php_settings_hook_name   = 'plugin_tracker_ping_' . preg_replace( '/[^a-z0-9_-]/i', '_', $easy_php_settings_basename );

		wp_clear_scheduled_hook( $easy_php_settings_hook_name );
		delete_option( $easy_php_settings_cron_option );
		delete_option( 'plugin_tracker_plugin_file' );
		delete_option( 'plugin_tracker_consent' );
		delete_option( 'plugin_tracker_consent_time' );
		delete_option( 'plugin_tracker_known_offer_ids' );
		delete_option( 'plugin_tracker_offer_notices' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- uninstall cleanup of per-user notice flags.
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'plugin_tracker_optin_dismissed' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of tracker transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_plugin_tracker_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_plugin_tracker_' ) . '%'
			)
		);
	}
}

/**
 * Remove this plugin's block from .user.ini and php.ini.
 *
 * Uses the same marker-aware removal as the "Remove directives" button, so
 * lines added by other plugins stay. Failure (e.g. a read-only site root or
 * a filesystem that needs FTP credentials) must not stop the uninstall.
 *
 * @return void
 */
function easy_php_settings_uninstall_ini_files() {
	$easy_php_settings_dir = plugin_dir_path( __FILE__ ) . 'includes/';
	require_once $easy_php_settings_dir . 'class-easy-error-handler.php';
	require_once $easy_php_settings_dir . 'class-easyinifile.php';

	EasyIniFile::remove_files();
}

easy_php_settings_uninstall_ini_files();
easy_php_settings_uninstall();
