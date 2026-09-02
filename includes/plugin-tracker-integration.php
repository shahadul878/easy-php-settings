<?php
/**
 * Plugin Tracker Integration – drop-in for existing WordPress plugins.
 *
 * Reports basic site and plugin usage to the Plugin Tracker API when the
 * administrator has explicitly opted in. No data is sent by default.
 *
 * @package PluginTrackerIntegration
 * @version 1.1.8
 * @author H M Shahadul Islam
 * @license GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PLUGIN_TRACKER_API_URL' ) ) {
	define( 'PLUGIN_TRACKER_API_URL', 'https://plugin.codereyes.com/' );
}

if ( ! defined( 'PLUGIN_TRACKER_API_KEY' ) ) {
	define( 'PLUGIN_TRACKER_API_KEY', 'pt_RVdd3aNGApDHlHwNRkqnSiBjN19IpmcnYJcy3TpG' );
}

if ( ! defined( 'PLUGIN_TRACKER_PRIVACY_URL' ) ) {
	define( 'PLUGIN_TRACKER_PRIVACY_URL', 'https://codereyes.com/privacy-policy/' );
}

if ( ! defined( 'PLUGIN_TRACKER_TERMS_URL' ) ) {
	define( 'PLUGIN_TRACKER_TERMS_URL', 'https://github.com/shahadul878/easy-php-settings/blob/master/legal/plugin-tracker-terms.md' );
}

const PLUGIN_TRACKER_PLUGIN_FILE_OPT = 'plugin_tracker_plugin_file';
const PLUGIN_TRACKER_CONSENT_OPT     = 'plugin_tracker_consent';
const PLUGIN_TRACKER_CONSENT_TIME_OPT = 'plugin_tracker_consent_time';
const PLUGIN_TRACKER_KNOWN_OFFERS_OPT  = 'plugin_tracker_known_offer_ids';
const PLUGIN_TRACKER_OFFER_NOTICES_OPT = 'plugin_tracker_offer_notices';
const PLUGIN_TRACKER_OFFER_NOTICE_TTL  = 7 * DAY_IN_SECONDS;
const PLUGIN_TRACKER_OFFER_SNOOZE_TTL  = DAY_IN_SECONDS;

/**
 * Ensure wp-admin plugin helpers are loaded (needed during cron / early hooks).
 */
function tracker_integration_load_plugin_api() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
}

/**
 * Get plugin version for tracker.
 *
 * @param string|null $plugin_file Plugin file path.
 * @return string
 */
function tracker_integration_get_version( $plugin_file = null ) {
	if ( defined( 'PLUGIN_TRACKER_PLUGIN_VERSION' ) && PLUGIN_TRACKER_PLUGIN_VERSION !== '' ) {
		return PLUGIN_TRACKER_PLUGIN_VERSION;
	}
	if ( $plugin_file ) {
		tracker_integration_load_plugin_api();
		if ( function_exists( 'get_plugin_data' ) ) {
			$data = get_plugin_data( $plugin_file, false, false );
			return isset( $data['Version'] ) ? $data['Version'] : '1.0';
		}
	}
	return '1.0';
}

/**
 * Get list of all installed plugins with name, version, and active status.
 *
 * @return array<int, array{name: string, version: string, active: bool}>
 */
function tracker_integration_get_installed_plugins() {
	tracker_integration_load_plugin_api();
	$all    = get_plugins();
	$active = (array) get_option( 'active_plugins', array() );
	$list   = array();
	foreach ( $all as $basename => $info ) {
		$list[] = array(
			'name'    => isset( $info['Name'] ) ? (string) $info['Name'] : $basename,
			'version' => isset( $info['Version'] ) ? (string) $info['Version'] : '',
			'active'  => in_array( $basename, $active, true ),
		);
	}
	return $list;
}

/**
 * Get list of all installed themes with name, version, and active status.
 *
 * @return array<int, array{name: string, version: string, active: bool}>
 */
function tracker_integration_get_installed_themes() {
	$themes            = wp_get_themes();
	$active_stylesheet = get_stylesheet();
	$list              = array();
	foreach ( $themes as $slug => $theme ) {
		$list[] = array(
			'name'    => $theme->get( 'Name' ) ?: $slug,
			'version' => $theme->get( 'Version' ) ?: '',
			'active'  => ( $theme->get_stylesheet() === $active_stylesheet ),
		);
	}
	return $list;
}

/**
 * Cron hook name for this plugin (unique per plugin file).
 *
 * @param string|null $plugin_file Plugin file path.
 * @return string
 */
function tracker_integration_cron_hook( $plugin_file ) {
	$base = $plugin_file ? plugin_basename( $plugin_file ) : 'tracker';
	$base = preg_replace( '/[^a-z0-9_-]/i', '_', $base );
	return 'plugin_tracker_ping_' . $base;
}

/**
 * Build payload for tracker network calls.
 *
 * @param string $plugin_file Plugin file path.
 * @return array
 */
function tracker_integration_build_payload( $plugin_file ) {
	$payload = array(
		'api_key'        => PLUGIN_TRACKER_API_KEY,
		'site_url'       => site_url(),
		'plugin_version' => tracker_integration_get_version( $plugin_file ),
		'wp_version'     => get_bloginfo( 'version' ),
		'php_version'    => PHP_VERSION,
	);

	$admin_email = get_option( 'admin_email' );
	if ( $admin_email ) {
		$payload['admin_email'] = $admin_email;
	}
	$admin_user = $admin_email ? get_user_by( 'email', $admin_email ) : null;
	if ( $admin_user && ! empty( $admin_user->display_name ) ) {
		$payload['admin_name'] = $admin_user->display_name;
	}
	if ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
		$payload['server_software'] = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
	}
	$payload['installed_plugins'] = wp_json_encode( tracker_integration_get_installed_plugins() );
	$payload['installed_themes']  = wp_json_encode( tracker_integration_get_installed_themes() );

	return $payload;
}

/**
 * Whether the site administrator has opted in to third-party data sharing.
 *
 * @return bool
 */
function tracker_integration_has_consent() {
	return '1' === get_option( PLUGIN_TRACKER_CONSENT_OPT, '0' );
}

/**
 * Register plugin on activation without sending any external data.
 *
 * @param string $plugin_file Path to the main plugin file.
 */
function tracker_integration_on_activate( $plugin_file ) {
	$GLOBALS['plugin_tracker_integration_plugin_file'] = $plugin_file;
	update_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT, $plugin_file, false );
}

/**
 * Opt in to data sharing: persist consent, send install report, schedule cron.
 *
 * @param string $plugin_file Path to the main plugin file.
 */
function tracker_integration_grant_consent( $plugin_file ) {
	update_option( PLUGIN_TRACKER_CONSENT_OPT, '1', false );
	update_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, time(), false );

	tracker_integration_delete_offers_cache();
	tracker_integration_send_install( $plugin_file );
	tracker_integration_schedule_cron( $plugin_file );
}

/**
 * Revoke consent and stop all scheduled tracking.
 *
 * @param string $plugin_file     Path to the main plugin file.
 * @param bool   $send_deactivate Send a best-effort deactivate ping when consent was active.
 */
function tracker_integration_revoke_consent( $plugin_file, $send_deactivate = true ) {
	$had_consent = tracker_integration_has_consent();

	if ( $send_deactivate && $had_consent && $plugin_file ) {
		tracker_integration_post( 'deactivate', tracker_integration_build_payload( $plugin_file ), false, false );
	}

	update_option( PLUGIN_TRACKER_CONSENT_OPT, '0', false );
	delete_option( PLUGIN_TRACKER_CONSENT_TIME_OPT );
	tracker_integration_unschedule_cron( $plugin_file );
	tracker_integration_delete_offers_cache();
	delete_option( PLUGIN_TRACKER_KNOWN_OFFERS_OPT );
	delete_option( PLUGIN_TRACKER_OFFER_NOTICES_OPT );
}

/**
 * Handle plugin deactivation: notify tracker only if opted in, always clear cron.
 *
 * @param string $plugin_file Path to the main plugin file.
 */
function tracker_integration_on_deactivate( $plugin_file ) {
	if ( tracker_integration_has_consent() ) {
		tracker_integration_report_deactivate( $plugin_file );
	}
	tracker_integration_unschedule_cron( $plugin_file );
}

/**
 * URL to the About tab consent panel.
 *
 * @return string
 */
function tracker_integration_get_consent_settings_url() {
	return add_query_arg(
		array(
			'page' => 'easy-php-settings',
			'tab'  => 'about',
		),
		admin_url( 'tools.php' )
	);
}

/**
 * Shared disclosure copy for opt-in UI.
 *
 * @return array<string, string>
 */
function tracker_integration_get_disclosure_fields() {
	return array(
		__( 'Site URL', 'easy-php-settings' ),
		__( 'WordPress, PHP, and plugin versions', 'easy-php-settings' ),
		__( 'Server software', 'easy-php-settings' ),
		__( 'Administrator email address and display name', 'easy-php-settings' ),
		__( 'Installed plugins and themes (name, version, active status)', 'easy-php-settings' ),
	);
}

/**
 * @deprecated 1.1.7 Use tracker_integration_on_activate() instead.
 * @param string $plugin_file Path to the main plugin file.
 */
function tracker_integration_report_install( $plugin_file ) {
	tracker_integration_on_activate( $plugin_file );
}

/**
 * POST a payload to the tracker.
 *
 * @param string $endpoint         Path under PLUGIN_TRACKER_API_URL (e.g. 'install').
 * @param array  $payload          Body.
 * @param bool   $blocking         Whether to wait for the HTTP response.
 * @param bool   $require_consent  When true, abort if the site has not opted in.
 */
function tracker_integration_post( $endpoint, $payload, $blocking = false, $require_consent = true ) {
	if ( $require_consent && ! tracker_integration_has_consent() ) {
		return;
	}
	if ( '' === PLUGIN_TRACKER_API_KEY ) {
		return;
	}

	$url = rtrim( PLUGIN_TRACKER_API_URL, '/' ) . '/api/plugin/' . ltrim( $endpoint, '/' );
	wp_remote_post(
		$url,
		array(
			'timeout'  => 5,
			'blocking' => $blocking,
			'body'     => $payload,
			'headers'  => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
		)
	);
}

/**
 * Send weekly ping. Called by cron and admin sync.
 *
 * @param string|null $plugin_file Plugin file path.
 */
function tracker_integration_ping( $plugin_file = null ) {
	$plugin_file = $plugin_file
		?: ( $GLOBALS['plugin_tracker_integration_plugin_file'] ?? null )
		?: get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );

	if ( ! $plugin_file ) {
		return;
	}

	tracker_integration_post( 'ping', tracker_integration_build_payload( $plugin_file ), false );
}

/**
 * Schedule the weekly ping cron, if not already scheduled.
 *
 * @param string $plugin_file Plugin file path.
 */
function tracker_integration_schedule_cron( $plugin_file ) {
	$hook = tracker_integration_cron_hook( $plugin_file );
	update_option( 'plugin_tracker_cron_' . md5( plugin_basename( $plugin_file ) ), $hook, false );
	if ( ! wp_next_scheduled( $hook ) ) {
		wp_schedule_event( time(), 'weekly', $hook );
	}
}

/**
 * Unschedule the weekly ping cron.
 *
 * @param string|null $plugin_file Plugin file path.
 */
function tracker_integration_unschedule_cron( $plugin_file = null ) {
	$plugin_file = $plugin_file ?: get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
	if ( ! $plugin_file ) {
		return;
	}
	$hook = tracker_integration_cron_hook( $plugin_file );
	wp_clear_scheduled_hook( $hook );
}

/**
 * Send the install report (blocking so activation completes the request).
 *
 * @param string $plugin_file Plugin file path.
 */
function tracker_integration_send_install( $plugin_file ) {
	tracker_integration_post( 'install', tracker_integration_build_payload( $plugin_file ), true );
}

/**
 * Notify the tracker that the site is no longer participating. Best-effort.
 *
 * @param string $plugin_file Plugin file path.
 */
function tracker_integration_report_deactivate( $plugin_file ) {
	if ( ! tracker_integration_has_consent() ) {
		return;
	}
	tracker_integration_post( 'deactivate', tracker_integration_build_payload( $plugin_file ), false, false );
}

/**
 * Clean up cron, options, transients, and notice-dismissed user meta.
 * Called from uninstall.php.
 *
 * @param string|null $plugin_file Plugin file path (optional).
 */
function tracker_integration_cleanup( $plugin_file = null ) {
	global $wpdb;

	if ( $plugin_file ) {
		tracker_integration_unschedule_cron( $plugin_file );
		delete_option( 'plugin_tracker_cron_' . md5( plugin_basename( $plugin_file ) ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- locate leftover tracker cron options during uninstall.
		$opts = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
				$wpdb->esc_like( 'plugin_tracker_cron_' ) . '%'
			)
		);
		foreach ( $opts as $name ) {
			$hook = get_option( $name );
			if ( $hook ) {
				wp_clear_scheduled_hook( $hook );
			}
			delete_option( $name );
		}
	}

	delete_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
	delete_option( PLUGIN_TRACKER_CONSENT_OPT );
	delete_option( PLUGIN_TRACKER_CONSENT_TIME_OPT );
	delete_option( PLUGIN_TRACKER_KNOWN_OFFERS_OPT );
	delete_option( PLUGIN_TRACKER_OFFER_NOTICES_OPT );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall/cleanup of tracker transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_plugin_tracker_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_plugin_tracker_' ) . '%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- targeted usermeta cleanup on uninstall.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'plugin_tracker_optin_dismissed' ) );
}

// Re-ping after our own plugin is updated so the tracker refreshes version info.
add_action(
	'upgrader_process_complete',
	function ( $upgrader, $options ) {
		if ( ! tracker_integration_has_consent() ) {
			return;
		}
		if ( ! isset( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		$our_plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
		if ( ! $our_plugin_file || ! file_exists( $our_plugin_file ) ) {
			return;
		}
		$our_basename = plugin_basename( $our_plugin_file );
		$updated      = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			$updated = array( $options['plugin'] );
		}
		if ( in_array( $our_basename, $updated, true ) ) {
			tracker_integration_ping( $our_plugin_file );
		}
	},
	10,
	2
);

// Bind cron callback.
add_action(
	'init',
	function () {
		$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
		if ( ! $plugin_file ) {
			return;
		}
		$GLOBALS['plugin_tracker_integration_plugin_file'] = $plugin_file;
		$hook = tracker_integration_cron_hook( $plugin_file );
		add_action(
			$hook,
			function () use ( $plugin_file ) {
				if ( ! tracker_integration_has_consent() ) {
					return;
				}
				tracker_integration_ping( $plugin_file );
			}
		);
	},
	1
);

// Throttled admin sync: ping once per hour when an admin loads the dashboard.
add_action(
	'admin_init',
	function () {
		if ( ! tracker_integration_has_consent() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
		if ( ! $plugin_file ) {
			return;
		}
		$sync_key = 'plugin_tracker_sync_' . md5( PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL . site_url() );
		if ( false !== get_transient( $sync_key ) ) {
			return;
		}
		tracker_integration_ping( $plugin_file );
		set_transient( $sync_key, 1, HOUR_IN_SECONDS );
	}
);

/**
 * Delete cached offers so the next fetch pulls fresh API data.
 */
function tracker_integration_delete_offers_cache() {
	$keys = array(
		'plugin_tracker_offers_' . md5( PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL ),
		'plugin_tracker_offers_v2_' . md5( PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL ),
	);
	foreach ( $keys as $key ) {
		delete_transient( $key );
	}
}

/**
 * Sort offers for stable display order.
 *
 * @param array $offers Raw offers from the API.
 * @return array
 */
function tracker_integration_sort_offers( $offers ) {
	if ( ! is_array( $offers ) || empty( $offers ) ) {
		return array();
	}

	usort(
		$offers,
		function ( $a, $b ) {
			$order_a = isset( $a['sort_order'] ) ? (int) $a['sort_order'] : 0;
			$order_b = isset( $b['sort_order'] ) ? (int) $b['sort_order'] : 0;
			if ( $order_a !== $order_b ) {
				return $order_a <=> $order_b;
			}
			$id_a = isset( $a['id'] ) ? (int) $a['id'] : 0;
			$id_b = isset( $b['id'] ) ? (int) $b['id'] : 0;
			return $id_a <=> $id_b;
		}
	);

	return $offers;
}

/**
 * Fetch service offers from API (cached).
 *
 * @param bool $force_refresh Skip cache and pull fresh data.
 * @return array
 */
function tracker_integration_get_offers( $force_refresh = false ) {
	if ( ! tracker_integration_has_consent() ) {
		return array();
	}
	if ( '' === PLUGIN_TRACKER_API_KEY ) {
		return array();
	}

	$transient_key = 'plugin_tracker_offers_v2_' . md5( PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL );
	if ( ! $force_refresh ) {
		$offers = get_transient( $transient_key );
		if ( false !== $offers ) {
			return tracker_integration_sort_offers( is_array( $offers ) ? $offers : array() );
		}
	}

	$url = rtrim( PLUGIN_TRACKER_API_URL, '/' ) . '/api/plugin/offers';
	$res = wp_remote_get(
		$url,
		array(
			'timeout' => 10,
			'headers' => array( 'X-Api-Key' => PLUGIN_TRACKER_API_KEY ),
		)
	);
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		set_transient( $transient_key, array(), 15 * MINUTE_IN_SECONDS );
		return array();
	}
	$body    = wp_remote_retrieve_body( $res );
	$decoded = json_decode( $body, true );
	$offers  = tracker_integration_sort_offers( is_array( $decoded ) ? $decoded : array() );
	set_transient( $transient_key, $offers, 15 * MINUTE_IN_SECONDS );
	return $offers;
}

/**
 * Allowed link hosts for offer rendering.
 *
 * @return string[]
 */
function tracker_integration_offer_link_allowlist() {
	$default = array( 'codereyes.com', 'plugin.codereyes.com', 'wa.me', 'api.whatsapp.com' );
	$list    = apply_filters( 'plugin_tracker_offer_link_allowlist', $default );
	return array_filter( array_map( 'strtolower', (array) $list ) );
}

/**
 * Validate an offer link against the host allowlist.
 *
 * @param string $url URL to check.
 * @return string Empty string if not allowed, otherwise the URL.
 */
function tracker_integration_safe_offer_link( $url ) {
	if ( empty( $url ) ) {
		return '';
	}
	$host = wp_parse_url( (string) $url, PHP_URL_HOST );
	if ( ! $host ) {
		return '';
	}
	$host = strtolower( $host );
	foreach ( tracker_integration_offer_link_allowlist() as $allowed ) {
		if ( $host === $allowed || ( '.' . $allowed ) === substr( $host, -1 - strlen( $allowed ) ) ) {
			return esc_url_raw( $url );
		}
	}
	return '';
}

/**
 * Allowed image hosts for offer thumbnails.
 *
 * @return string[]
 */
function tracker_integration_offer_image_allowlist() {
	$default = array( 'codereyes.com', 'plugin.codereyes.com' );
	$list    = apply_filters( 'plugin_tracker_offer_image_allowlist', $default );
	return array_filter( array_map( 'strtolower', (array) $list ) );
}

/**
 * Resolve and validate an offer image URL from the API payload.
 *
 * @param string|null $image Image path or URL from the API.
 * @return string
 */
function tracker_integration_resolve_offer_image( $image ) {
	if ( empty( $image ) ) {
		return '';
	}

	$image = trim( (string) $image );

	if ( preg_match( '#^https?://#i', $image ) ) {
		$host = wp_parse_url( $image, PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}
		$host = strtolower( (string) $host );
		foreach ( tracker_integration_offer_image_allowlist() as $allowed ) {
			if ( $host === $allowed || ( '.' . $allowed ) === substr( $host, -1 - strlen( $allowed ) ) ) {
				return esc_url_raw( $image );
			}
		}
		return '';
	}

	$path = ltrim( $image, '/' );
	if ( preg_match( '#\.\.(\\\\|/)#', $path ) ) {
		return '';
	}

	return esc_url_raw( rtrim( PLUGIN_TRACKER_API_URL, '/' ) . '/storage/' . $path );
}

/**
 * Pick a placeholder style for offers without a remote image.
 *
 * @param string $title Offer title.
 * @return array{slug: string, icon: string}
 */
function tracker_integration_get_offer_placeholder( $title ) {
	$title = strtolower( $title );

	if ( preg_match( '/speed|optim|performance|fast|cache/', $title ) ) {
		return array(
			'slug' => 'speed',
			'icon' => 'dashicons-performance',
		);
	}
	if ( preg_match( '/security|secure|harden|firewall|malware/', $title ) ) {
		return array(
			'slug' => 'security',
			'icon' => 'dashicons-shield',
		);
	}
	if ( preg_match( '/website|design|wordpress|launch|business/', $title ) ) {
		return array(
			'slug' => 'website',
			'icon' => 'dashicons-admin-site-alt3',
		);
	}

	return array(
		'slug' => 'default',
		'icon' => 'dashicons-megaphone',
	);
}

/**
 * Render offer cards in a post-style layout.
 *
 * @param array $offers Offer list from the API.
 */
function tracker_integration_render_offer_cards( $offers ) {
	echo '<div class="eps-tracker-offers">';

	foreach ( $offers as $offer ) {
		$title = isset( $offer['title'] ) ? (string) $offer['title'] : '';
		$link  = tracker_integration_safe_offer_link( isset( $offer['link'] ) ? $offer['link'] : '' );
		$desc  = isset( $offer['description'] ) ? (string) $offer['description'] : '';
		$desc  = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
		$image = tracker_integration_resolve_offer_image( isset( $offer['image'] ) ? $offer['image'] : '' );
		$placeholder = tracker_integration_get_offer_placeholder( $title );

		echo '<article class="eps-tracker-offer-card">';

		if ( $image ) {
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '" class="eps-tracker-offer-media" target="_blank" rel="noopener">';
			} else {
				echo '<div class="eps-tracker-offer-media">';
			}
			echo '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" />';
			echo $link ? '</a>' : '</div>';
		} else {
			$media_classes = 'eps-tracker-offer-media eps-tracker-offer-media--placeholder eps-tracker-offer-media--' . esc_attr( $placeholder['slug'] );
			if ( $link ) {
				echo '<a href="' . esc_url( $link ) . '" class="' . esc_attr( $media_classes ) . '" target="_blank" rel="noopener">';
			} else {
				echo '<div class="' . esc_attr( $media_classes ) . '">';
			}
			echo '<span class="dashicons ' . esc_attr( $placeholder['icon'] ) . '" aria-hidden="true"></span>';
			echo $link ? '</a>' : '</div>';
		}

		echo '<div class="eps-tracker-offer-body">';
		echo '<h4 class="eps-tracker-offer-title">';
		if ( $link ) {
			echo '<a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>';
		} else {
			echo esc_html( $title );
		}
		echo '</h4>';

		if ( $desc ) {
			echo '<p class="eps-tracker-offer-excerpt">' . esc_html( wp_trim_words( $desc, 22 ) ) . '</p>';
		}

		if ( $link ) {
			echo '<a href="' . esc_url( $link ) . '" class="eps-tracker-offer-link" target="_blank" rel="noopener">';
			esc_html_e( 'Learn more', 'easy-php-settings' );
			echo '<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>';
			echo '</a>';
		}

		echo '</div>';
		echo '</article>';
	}

	echo '</div>';
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' !== $hook || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_enqueue_style(
			'easy-php-settings-tracker-widget',
			plugins_url( 'css/tracker-widget.css', dirname( __DIR__ ) . '/class-easy-php-settings.php' ),
			array( 'dashicons' ),
			defined( 'PLUGIN_TRACKER_PLUGIN_VERSION' ) ? PLUGIN_TRACKER_PLUGIN_VERSION : '1.1.8'
		);
	}
);

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'plugin_tracker_offers_widget',
			__( 'Easy PHP Settings – Services by codereyes', 'easy-php-settings' ),
			'tracker_integration_render_dashboard_widget',
			null,
			null,
			'normal'
		);
	}
);

/**
 * Render the dashboard widget content.
 */
function tracker_integration_render_dashboard_widget() {
	if ( ! tracker_integration_has_consent() ) {
		echo '<p style="margin:0;">' . esc_html__( 'Optional: share anonymous site usage data to receive service offers and support updates from CodeEyes.', 'easy-php-settings' ) . '</p>';
		echo '<p style="margin:8px 0 0;"><a href="' . esc_url( tracker_integration_get_consent_settings_url() ) . '">' . esc_html__( 'Manage data sharing preferences', 'easy-php-settings' ) . '</a></p>';
		return;
	}

	$offers = tracker_integration_get_offers();
	if ( empty( $offers ) ) {
		echo '<p style="margin:0;">' . esc_html__( 'Need help with WordPress? Performance, security, optimization.', 'easy-php-settings' ) . '</p>';
		echo '<p style="margin:8px 0 0;"><a href="https://codereyes.com" target="_blank" rel="noopener">codereyes.com</a></p>';
		return;
	}

	tracker_integration_render_offer_cards( $offers );
}

/* ──────────────────────────────────────────────
   New offer admin notice (7-day countdown)
   ────────────────────────────────────────────── */

/**
 * @return array<string, array{first_seen: int, dismissed: bool, snooze_until: int}>
 */
function tracker_integration_get_offer_notice_state() {
	$state = get_option( PLUGIN_TRACKER_OFFER_NOTICES_OPT, array() );
	return is_array( $state ) ? $state : array();
}

/**
 * @param array<string, array{first_seen: int, dismissed: bool, snooze_until: int}> $state Notice state.
 */
function tracker_integration_save_offer_notice_state( $state ) {
	update_option( PLUGIN_TRACKER_OFFER_NOTICES_OPT, is_array( $state ) ? $state : array(), false );
}

/**
 * Register newly published offers and expire old notice entries.
 *
 * @param array $offers Offers from the API.
 */
function tracker_integration_sync_offer_notices( $offers ) {
	if ( ! is_array( $offers ) ) {
		return;
	}

	$current_ids = array();
	foreach ( $offers as $offer ) {
		if ( isset( $offer['id'] ) ) {
			$current_ids[] = (string) $offer['id'];
		}
	}
	$current_ids = array_values( array_unique( $current_ids ) );

	$known   = get_option( PLUGIN_TRACKER_KNOWN_OFFERS_OPT, null );
	$notices = tracker_integration_get_offer_notice_state();
	$now     = time();

	if ( null === $known ) {
		update_option( PLUGIN_TRACKER_KNOWN_OFFERS_OPT, $current_ids, false );
		return;
	}

	$known = array_map( 'strval', (array) $known );

	foreach ( $current_ids as $offer_id ) {
		if ( in_array( $offer_id, $known, true ) ) {
			continue;
		}
		$notices[ $offer_id ] = array(
			'first_seen'   => $now,
			'dismissed'    => false,
			'snooze_until' => 0,
		);
	}

	foreach ( $notices as $offer_id => $meta ) {
		if ( ! in_array( (string) $offer_id, $current_ids, true ) ) {
			unset( $notices[ $offer_id ] );
			continue;
		}
		$first_seen = isset( $meta['first_seen'] ) ? (int) $meta['first_seen'] : 0;
		if ( $first_seen && ( $now - $first_seen ) >= PLUGIN_TRACKER_OFFER_NOTICE_TTL ) {
			unset( $notices[ $offer_id ] );
		}
	}

	update_option( PLUGIN_TRACKER_KNOWN_OFFERS_OPT, $current_ids, false );
	tracker_integration_save_offer_notice_state( $notices );
}

/**
 * Get the offer that should display the admin notice, if any.
 *
 * @return array{offer: array, meta: array, expires_at: int}|null
 */
function tracker_integration_get_active_offer_notice() {
	if ( ! tracker_integration_has_consent() ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return null;
	}

	$offers = tracker_integration_get_offers();
	if ( empty( $offers ) ) {
		return null;
	}

	tracker_integration_sync_offer_notices( $offers );
	$notices = tracker_integration_get_offer_notice_state();
	$now     = time();
	$by_id   = array();

	foreach ( $offers as $offer ) {
		if ( isset( $offer['id'] ) ) {
			$by_id[ (string) $offer['id'] ] = $offer;
		}
	}

	$candidates = array();
	foreach ( $notices as $offer_id => $meta ) {
		if ( empty( $by_id[ (string) $offer_id ] ) ) {
			continue;
		}
		if ( ! empty( $meta['dismissed'] ) ) {
			continue;
		}
		if ( ! empty( $meta['snooze_until'] ) && $now < (int) $meta['snooze_until'] ) {
			continue;
		}

		$first_seen = isset( $meta['first_seen'] ) ? (int) $meta['first_seen'] : 0;
		if ( ! $first_seen ) {
			continue;
		}

		$expires_at = $first_seen + PLUGIN_TRACKER_OFFER_NOTICE_TTL;
		if ( $now >= $expires_at ) {
			continue;
		}

		$candidates[] = array(
			'offer'      => $by_id[ (string) $offer_id ],
			'meta'       => $meta,
			'expires_at' => $expires_at,
		);
	}

	if ( empty( $candidates ) ) {
		return null;
	}

	usort(
		$candidates,
		function ( $a, $b ) {
			return (int) $b['meta']['first_seen'] <=> (int) $a['meta']['first_seen'];
		}
	);

	return $candidates[0];
}

/**
 * Whether the new-offer notice should render.
 *
 * @return bool
 */
function tracker_integration_should_show_offer_notice() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return false;
	}
	return null !== tracker_integration_get_active_offer_notice();
}

/**
 * Enqueue assets for the new-offer admin notice.
 */
function tracker_integration_enqueue_offer_notice_assets() {
	if ( ! tracker_integration_should_show_offer_notice() ) {
		return;
	}

	$active = tracker_integration_get_active_offer_notice();
	if ( ! $active ) {
		return;
	}

	$plugin_file = dirname( __DIR__ ) . '/class-easy-php-settings.php';
	$version     = defined( 'PLUGIN_TRACKER_PLUGIN_VERSION' ) ? PLUGIN_TRACKER_PLUGIN_VERSION : '1.1.8';
	$offer       = $active['offer'];
	$offer_id    = isset( $offer['id'] ) ? (string) $offer['id'] : '';
	$offer_link  = tracker_integration_safe_offer_link( isset( $offer['link'] ) ? $offer['link'] : '' );

	wp_enqueue_style(
		'easy-php-settings-notices',
		plugins_url( 'css/admin-notices.css', $plugin_file ),
		array( 'dashicons' ),
		$version
	);

	wp_enqueue_script(
		'easy-php-settings-offer-notice',
		plugins_url( 'js/offer-notice.js', $plugin_file ),
		array( 'jquery' ),
		$version,
		true
	);

	wp_localize_script(
		'easy-php-settings-offer-notice',
		'easyPhpSettingsOfferNotice',
		array(
			'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
			'nonce'              => wp_create_nonce( 'plugin_tracker_offer_notice' ),
			'offerId'            => $offer_id,
			'offerUrl'           => $offer_link,
			'expiredLabel'       => __( 'Expired', 'easy-php-settings' ),
			/* translators: 1: number of days remaining, 2: number of hours remaining */
			'daysHoursLabel'     => __( '%1$d days %2$d hours left', 'easy-php-settings' ),
			/* translators: 1: number of hours remaining, 2: number of minutes remaining */
			'hoursMinutesLabel'  => __( '%1$d hours %2$d minutes left', 'easy-php-settings' ),
		)
	);
}

/**
 * Render the new-offer admin notice.
 */
function tracker_integration_render_offer_notice() {
	$active = tracker_integration_get_active_offer_notice();
	if ( ! $active ) {
		return;
	}

	if ( ! wp_script_is( 'easy-php-settings-offer-notice', 'enqueued' ) ) {
		tracker_integration_enqueue_offer_notice_assets();
	}

	$offer      = $active['offer'];
	$expires_at = (int) $active['expires_at'];
	$offer_id   = isset( $offer['id'] ) ? (string) $offer['id'] : '';
	$title      = isset( $offer['title'] ) ? (string) $offer['title'] : '';
	$desc       = isset( $offer['description'] ) ? (string) $offer['description'] : '';
	$desc       = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $desc ) ) );
	$remaining  = max( 0, $expires_at - time() );
	$days_left  = (int) floor( $remaining / DAY_IN_SECONDS );
	$hours_left = (int) floor( ( $remaining % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
	$expires_on = date_i18n( get_option( 'date_format' ), $expires_at );

	if ( $days_left > 0 ) {
		$timer_text = sprintf(
			/* translators: 1: days remaining, 2: hours remaining, 3: expiry date */
			__( '%1$d days %2$d hours left (until %3$s)', 'easy-php-settings' ),
			$days_left,
			$hours_left,
			$expires_on
		);
	} else {
		$minutes_left = (int) floor( ( $remaining % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$timer_text   = sprintf(
			/* translators: 1: hours remaining, 2: minutes remaining, 3: expiry date */
			__( '%1$d hours %2$d minutes left (until %3$s)', 'easy-php-settings' ),
			$hours_left,
			$minutes_left,
			$expires_on
		);
	}
	?>
	<div
		id="easy-php-settings-offer-notice"
		class="notice notice-info easy-php-review-notice easy-php-offer-notice"
		data-expires-at="<?php echo esc_attr( (string) $expires_at ); ?>"
		data-offer-id="<?php echo esc_attr( $offer_id ); ?>"
	>
		<div class="easy-php-review-notice-inner">
			<div class="easy-php-review-notice-icon easy-php-offer-notice-icon" aria-hidden="true">
				<span class="dashicons dashicons-megaphone"></span>
			</div>

			<div class="easy-php-review-notice-body">
				<p class="easy-php-review-notice-title">
					<strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: offer title */
								__( 'New offer: %s', 'easy-php-settings' ),
								$title
							)
						);
						?>
					</strong>
				</p>
				<?php if ( $desc ) : ?>
					<p class="easy-php-review-notice-message">
						<?php echo esc_html( wp_trim_words( $desc, 28 ) ); ?>
					</p>
				<?php endif; ?>
				<p class="easy-php-offer-notice-timer">
					<span class="dashicons dashicons-clock" aria-hidden="true"></span>
					<span data-offer-countdown><?php echo esc_html( $timer_text ); ?></span>
				</p>
				<p class="easy-php-review-notice-actions">
					<button type="button" class="button button-primary easy-php-review-btn" data-offer-action="view">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'View offer', 'easy-php-settings' ); ?>
					</button>
					<button type="button" class="button easy-php-review-btn" data-offer-action="later">
						<?php esc_html_e( 'Maybe later', 'easy-php-settings' ); ?>
					</button>
					<button type="button" class="button-link easy-php-review-btn-link" data-offer-action="dismiss">
						<?php esc_html_e( 'Don\'t show again', 'easy-php-settings' ); ?>
					</button>
				</p>
			</div>

			<button type="button" class="notice-dismiss easy-php-review-notice-close" data-offer-action="later">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'easy-php-settings' ); ?></span>
			</button>
		</div>
	</div>
	<?php
}

add_action( 'admin_enqueue_scripts', 'tracker_integration_enqueue_offer_notice_assets' );
add_action( 'admin_notices', 'tracker_integration_render_offer_notice' );
add_action( 'network_admin_notices', 'tracker_integration_render_offer_notice' );

add_action(
	'wp_ajax_plugin_tracker_offer_notice_action',
	function () {
		check_ajax_referer( 'plugin_tracker_offer_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'easy-php-settings' ) ), 403 );
		}

		$offer_id = isset( $_POST['offer_id'] ) ? sanitize_key( wp_unslash( $_POST['offer_id'] ) ) : '';
		$action   = isset( $_POST['offer_action'] ) ? sanitize_key( wp_unslash( $_POST['offer_action'] ) ) : '';

		if ( '' === $offer_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing offer.', 'easy-php-settings' ) ), 400 );
		}

		$notices = tracker_integration_get_offer_notice_state();
		if ( ! isset( $notices[ $offer_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Offer not found.', 'easy-php-settings' ) ), 404 );
		}

		switch ( $action ) {
			case 'view':
				unset( $notices[ $offer_id ] );
				break;
			case 'later':
				$notices[ $offer_id ]['snooze_until'] = time() + PLUGIN_TRACKER_OFFER_SNOOZE_TTL;
				break;
			case 'dismiss':
				$notices[ $offer_id ]['dismissed'] = true;
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'easy-php-settings' ) ), 400 );
		}

		tracker_integration_save_offer_notice_state( $notices );
		wp_send_json_success();
	}
);

/* ──────────────────────────────────────────────
   Opt-in consent notice and About tab controls
   ────────────────────────────────────────────── */

/**
 * Whether to show the first-run opt-in admin notice.
 *
 * @return bool
 */
function tracker_integration_should_show_optin_notice() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return false;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	if ( tracker_integration_has_consent() ) {
		return false;
	}
	if ( ! get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT ) ) {
		return false;
	}
	if ( get_user_meta( get_current_user_id(), 'plugin_tracker_optin_dismissed', true ) ) {
		return false;
	}
	return true;
}

/**
 * Handle grant/revoke consent form submissions.
 */
function tracker_integration_handle_consent_actions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
	if ( ! $plugin_file ) {
		return;
	}

	if ( isset( $_POST['plugin_tracker_consent_action'], $_POST['plugin_tracker_consent_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['plugin_tracker_consent_nonce'] ) ), 'plugin_tracker_consent' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['plugin_tracker_consent_action'] ) );
		if ( 'grant' === $action ) {
			tracker_integration_grant_consent( $plugin_file );
			delete_user_meta( get_current_user_id(), 'plugin_tracker_optin_dismissed' );
			add_settings_error( 'plugin_tracker_consent', 'granted', __( 'Data sharing enabled. You can revoke this at any time from the About tab.', 'easy-php-settings' ), 'updated' );
		} elseif ( 'revoke' === $action ) {
			tracker_integration_revoke_consent( $plugin_file, true );
			add_settings_error( 'plugin_tracker_consent', 'revoked', __( 'Data sharing disabled. No further information will be sent to the third-party service.', 'easy-php-settings' ), 'updated' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( tracker_integration_get_consent_settings_url() );
		exit;
	}

	if ( isset( $_GET['plugin_tracker_decline'], $_GET['_wpnonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'plugin_tracker_decline' ) ) {
			return;
		}
		update_user_meta( get_current_user_id(), 'plugin_tracker_optin_dismissed', 1 );
		wp_safe_redirect( remove_query_arg( array( 'plugin_tracker_decline', '_wpnonce' ) ) );
		exit;
	}
}
add_action( 'admin_init', 'tracker_integration_handle_consent_actions' );

/**
 * Render the optional opt-in admin notice.
 */
function tracker_integration_render_optin_notice() {
	if ( ! tracker_integration_should_show_optin_notice() ) {
		return;
	}

	$about_url   = tracker_integration_get_consent_settings_url();
	$decline_url = wp_nonce_url(
		add_query_arg( 'plugin_tracker_decline', '1', admin_url() ),
		'plugin_tracker_decline'
	);
	$fields      = tracker_integration_get_disclosure_fields();
	?>
	<div class="notice notice-info plugin-tracker-optin-notice">
		<p style="margin:0.5em 0 0.25em;">
			<strong><?php esc_html_e( 'Easy PHP Settings — optional data sharing', 'easy-php-settings' ); ?></strong>
		</p>
		<p style="margin:0.25em 0;">
			<?php
			printf(
				/* translators: %s: third-party service URL */
				esc_html__( 'You may optionally share site information with Plugin Tracker (CodeEyes) at %s to receive plugin support updates and service offers. Data sharing is disabled by default.', 'easy-php-settings' ),
				'https://plugin.codereyes.com/'
			);
			?>
		</p>
		<p style="margin:0.25em 0;"><strong><?php esc_html_e( 'If you opt in, the following data may be sent:', 'easy-php-settings' ); ?></strong></p>
		<ul style="list-style:disc;margin:0.25em 0 0.5em 1.5em;">
			<?php foreach ( $fields as $field ) : ?>
				<li><?php echo esc_html( $field ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p style="margin:0.25em 0 0.75em;">
			<?php esc_html_e( 'When sent: once when you opt in, then at most once per week via a scheduled task, at most once per hour during admin use, after plugin updates, and once on deactivation if you had opted in. Purpose: improve plugin support and related services.', 'easy-php-settings' ); ?>
		</p>
		<p style="margin:0.25em 0 0.75em;">
			<a href="<?php echo esc_url( PLUGIN_TRACKER_PRIVACY_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'easy-php-settings' ); ?></a>
			&nbsp;|&nbsp;
			<a href="<?php echo esc_url( PLUGIN_TRACKER_TERMS_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of Service', 'easy-php-settings' ); ?></a>
		</p>
		<p style="margin:0 0 0.75em;">
			<form method="post" action="" style="display:inline;margin-right:6px;">
				<?php wp_nonce_field( 'plugin_tracker_consent', 'plugin_tracker_consent_nonce' ); ?>
				<input type="hidden" name="plugin_tracker_consent_action" value="grant" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Allow data sharing', 'easy-php-settings' ); ?></button>
			</form>
			<a class="button" href="<?php echo esc_url( $about_url ); ?>#plugin-tracker-consent-panel"><?php esc_html_e( 'Learn more', 'easy-php-settings' ); ?></a>
			<a class="button" href="<?php echo esc_url( $decline_url ); ?>"><?php esc_html_e( 'No thanks', 'easy-php-settings' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'tracker_integration_render_optin_notice' );
add_action( 'network_admin_notices', 'tracker_integration_render_optin_notice' );

/**
 * Register suggested privacy policy text for Site Health / Privacy tools.
 */
function tracker_integration_register_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$content = '<p>' . esc_html__( 'Easy PHP Settings can optionally send site information to Plugin Tracker (CodeEyes) at https://plugin.codereyes.com/ when an administrator explicitly opts in from the About tab. No data is sent by default.', 'easy-php-settings' ) . '</p>';
	$content .= '<p><strong>' . esc_html__( 'Data that may be sent after opt-in:', 'easy-php-settings' ) . '</strong></p><ul>';
	foreach ( tracker_integration_get_disclosure_fields() as $field ) {
		$content .= '<li>' . esc_html( $field ) . '</li>';
	}
	$content .= '</ul>';
	$content .= '<p>' . esc_html__( 'Frequency: on opt-in, weekly cron, hourly admin sync at most, after plugin updates, and on deactivation if opted in. You can revoke consent at any time from Tools → Easy PHP Settings → About.', 'easy-php-settings' ) . '</p>';
	$content .= '<p><a href="' . esc_url( PLUGIN_TRACKER_PRIVACY_URL ) . '">' . esc_html__( 'Privacy Policy', 'easy-php-settings' ) . '</a> | <a href="' . esc_url( PLUGIN_TRACKER_TERMS_URL ) . '">' . esc_html__( 'Terms of Service', 'easy-php-settings' ) . '</a></p>';

	wp_add_privacy_policy_content( 'Easy PHP Settings', $content );
}
add_action( 'admin_init', 'tracker_integration_register_privacy_policy_content' );

/**
 * Render opt-in controls for the About tab.
 */
function tracker_integration_render_consent_panel() {
	if ( $settings_errors = get_transient( 'settings_errors' ) ) {
		global $wp_settings_errors;
		$wp_settings_errors = array_merge( (array) $wp_settings_errors, $settings_errors );
		delete_transient( 'settings_errors' );
	}

	$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
	$has_consent = tracker_integration_has_consent();
	$consented_at = (int) get_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, 0 );
	$fields       = tracker_integration_get_disclosure_fields();
	?>
	<div id="plugin-tracker-consent-panel" class="easy-php-about-card">
		<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-privacy"></span> <?php esc_html_e( 'Privacy & Data Sharing', 'easy-php-settings' ); ?></h3>

		<p>
			<?php esc_html_e( 'Data sharing with third-party services is optional and disabled by default. Easy PHP Settings works fully without enabling this feature.', 'easy-php-settings' ); ?>
		</p>

		<p>
			<strong><?php esc_html_e( 'Third-party service:', 'easy-php-settings' ); ?></strong>
			<a href="https://plugin.codereyes.com/" target="_blank" rel="noopener">Plugin Tracker (CodeEyes) — https://plugin.codereyes.com/</a>
		</p>

		<p>
			<a href="<?php echo esc_url( PLUGIN_TRACKER_PRIVACY_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'easy-php-settings' ); ?></a>
			&nbsp;|&nbsp;
			<a href="<?php echo esc_url( PLUGIN_TRACKER_TERMS_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Terms of Service', 'easy-php-settings' ); ?></a>
		</p>

		<p><strong><?php esc_html_e( 'Data sent only after you opt in:', 'easy-php-settings' ); ?></strong></p>
		<ul class="easy-php-about-list">
			<?php foreach ( $fields as $field ) : ?>
				<li><?php echo esc_html( $field ); ?></li>
			<?php endforeach; ?>
		</ul>

		<p>
			<?php esc_html_e( 'Purpose: improve plugin support and related services/offers. Frequency: on opt-in, weekly scheduled sync, hourly admin sync at most, after plugin updates, and on deactivation if you had opted in.', 'easy-php-settings' ); ?>
		</p>

		<p>
			<strong><?php esc_html_e( 'Current status:', 'easy-php-settings' ); ?></strong>
			<?php if ( $has_consent ) : ?>
				<span class="easy-php-badge easy-php-badge--success"><?php esc_html_e( 'Opted in', 'easy-php-settings' ); ?></span>
				<?php if ( $consented_at ) : ?>
					<?php
					echo '<span class="description">';
					echo esc_html(
						sprintf(
							/* translators: %s: date when data sharing was enabled */
							__( '(since %s)', 'easy-php-settings' ),
							date_i18n( get_option( 'date_format' ), $consented_at )
						)
					);
					echo '</span>';
					?>
				<?php endif; ?>
			<?php else : ?>
				<span class="easy-php-badge"><?php esc_html_e( 'Opted out (default)', 'easy-php-settings' ); ?></span>
			<?php endif; ?>
		</p>

		<?php settings_errors( 'plugin_tracker_consent' ); ?>

		<?php if ( $plugin_file ) : ?>
			<form method="post" action="" class="easy-php-tracker-consent-form">
				<?php wp_nonce_field( 'plugin_tracker_consent', 'plugin_tracker_consent_nonce' ); ?>
				<?php if ( $has_consent ) : ?>
					<input type="hidden" name="plugin_tracker_consent_action" value="revoke" />
					<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Revoke data sharing and stop all future reports to Plugin Tracker?', 'easy-php-settings' ) ); ?>');">
						<?php esc_html_e( 'Revoke consent', 'easy-php-settings' ); ?>
					</button>
				<?php else : ?>
					<input type="hidden" name="plugin_tracker_consent_action" value="grant" />
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Allow data sharing', 'easy-php-settings' ); ?>
					</button>
				<?php endif; ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
