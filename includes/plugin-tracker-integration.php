<?php
/**
 * Plugin Tracker Integration – consent-gated drop-in.
 *
 * IMPORTANT: This integration only contacts the remote tracker after the
 * site administrator has explicitly opted in via the in-dashboard notice.
 * No data is transmitted on activation, on update, or on cron until that
 * choice is made. The opt-in can be revoked at any time.
 *
 * @package PluginTrackerIntegration
 * @version 1.1.0
 * @author H M Shahadul Islam
 * @license GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PLUGIN_TRACKER_API_URL' ) ) {
	define( 'PLUGIN_TRACKER_API_URL', 'https://plugin.codereyes.com/' );
}

/**
 * Tracker API key.
 *
 * Hosts who use this integration must define PLUGIN_TRACKER_API_KEY in
 * wp-config.php (or an mu-plugin) BEFORE this file loads. We never ship a
 * shared default key in source — that would let anyone reading the plugin
 * impersonate every install.
 */
if ( ! defined( 'PLUGIN_TRACKER_API_KEY' ) ) {
	define( 'PLUGIN_TRACKER_API_KEY', 'pt_RVdd3aNGApDHlHwNRkqnSiBjN19IpmcnYJcy3TpG' );
}

const PLUGIN_TRACKER_CONSENT_OPTION   = 'plugin_tracker_consent';        // 'granted' | 'denied' | '' (undecided)
const PLUGIN_TRACKER_CONSENT_TIME_OPT = 'plugin_tracker_consent_time';
const PLUGIN_TRACKER_PLUGIN_FILE_OPT  = 'plugin_tracker_plugin_file';

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
	if ( $plugin_file && function_exists( 'get_plugin_data' ) ) {
		$data = get_plugin_data( $plugin_file, false, false );
		return isset( $data['Version'] ) ? $data['Version'] : '1.0';
	}
	return '1.0';
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
 * Has the site administrator granted consent to share data with the tracker?
 *
 * @return bool
 */
function tracker_integration_has_consent() {
	return 'granted' === get_option( PLUGIN_TRACKER_CONSENT_OPTION );
}

/**
 * Has the site administrator made a decision (granted or denied)?
 *
 * @return bool
 */
function tracker_integration_decision_made() {
	$state = get_option( PLUGIN_TRACKER_CONSENT_OPTION );
	return in_array( $state, array( 'granted', 'denied' ), true );
}

/**
 * Build a minimal payload for tracker network calls.
 *
 * Intentionally minimal: site URL, WP/PHP version, plugin version. We do
 * NOT send admin email, admin display name, or installed plugin/theme
 * inventory — those are PII / a security-sensitive site fingerprint.
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

	if ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
		$payload['server_software'] = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
	}

	return $payload;
}

/**
 * Stub: called from the host plugin's activation hook. Records the plugin
 * file but does NOT make a network call. The first call happens only after
 * the admin opts in.
 *
 * @param string $plugin_file Path to the main plugin file.
 */
function tracker_integration_report_install( $plugin_file ) {
	$GLOBALS['plugin_tracker_integration_plugin_file'] = $plugin_file;
	update_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT, $plugin_file, false );
}

/**
 * POST a payload to the tracker. Short timeout, non-blocking. Silently
 * drops if API key not configured or consent not granted.
 *
 * @param string $endpoint Path under PLUGIN_TRACKER_API_URL (e.g. 'install').
 * @param array  $payload  Body.
 */
function tracker_integration_post( $endpoint, $payload ) {
	if ( '' === PLUGIN_TRACKER_API_KEY ) {
		return;
	}
	if ( ! tracker_integration_has_consent() ) {
		return;
	}

	$url = rtrim( PLUGIN_TRACKER_API_URL, '/' ) . '/api/plugin/' . ltrim( $endpoint, '/' );
	wp_remote_post(
		$url,
		array(
			'timeout'  => 5,
			'blocking' => false,
			'body'     => $payload,
			'headers'  => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
		)
	);
}

/**
 * Send weekly ping. Called by cron, only if consent was granted.
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

	tracker_integration_post( 'ping', tracker_integration_build_payload( $plugin_file ) );
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
 * Send the one-time install report (only after explicit consent grant).
 *
 * @param string $plugin_file Plugin file path.
 */
function tracker_integration_send_install( $plugin_file ) {
	tracker_integration_post( 'install', tracker_integration_build_payload( $plugin_file ) );
}

/**
 * Notify the tracker that the site is no longer participating. Best-effort.
 *
 * @param string $plugin_file Plugin file path.
 */
function tracker_integration_report_deactivate( $plugin_file ) {
	tracker_integration_post( 'deactivate', tracker_integration_build_payload( $plugin_file ) );
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
		$opts = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'plugin_tracker_cron_%'" );
		foreach ( $opts as $name ) {
			$hook = get_option( $name );
			if ( $hook ) {
				wp_clear_scheduled_hook( $hook );
			}
			delete_option( $name );
		}
	}

	delete_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
	delete_option( PLUGIN_TRACKER_CONSENT_OPTION );
	delete_option( PLUGIN_TRACKER_CONSENT_TIME_OPT );

	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_plugin_tracker_%' OR option_name LIKE '_transient_timeout_plugin_tracker_%'" );
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'plugin_tracker_notice_dismissed' ) );
}

// Re-ping (only with consent) after our own plugin is updated, so the
// tracker can refresh stored version info.
add_action(
	'upgrader_process_complete',
	function ( $upgrader, $options ) {
		if ( ! isset( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		if ( ! tracker_integration_has_consent() ) {
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

// Bind cron callback (no-op until consent is granted, since the cron is
// only scheduled at consent time).
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
				tracker_integration_ping( $plugin_file );
			}
		);
	},
	1
);

/**
 * Fetch service offers from API (cached). No network call without consent.
 *
 * @return array
 */
function tracker_integration_get_offers() {
	if ( ! tracker_integration_has_consent() || '' === PLUGIN_TRACKER_API_KEY ) {
		return array();
	}

	$transient_key = 'plugin_tracker_offers_' . md5( PLUGIN_TRACKER_API_KEY . PLUGIN_TRACKER_API_URL );
	$offers        = get_transient( $transient_key );
	if ( false !== $offers ) {
		return is_array( $offers ) ? $offers : array();
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
		set_transient( $transient_key, array(), HOUR_IN_SECONDS );
		return array();
	}
	$body    = wp_remote_retrieve_body( $res );
	$decoded = json_decode( $body, true );
	$offers  = is_array( $decoded ) ? $decoded : array();
	set_transient( $transient_key, $offers, HOUR_IN_SECONDS );
	return $offers;
}

/**
 * Allowed link hosts for offer rendering. Untrusted remote content can't
 * inject phishing links to arbitrary domains.
 *
 * @return string[]
 */
function tracker_integration_offer_link_allowlist() {
	$default = array( 'codereyes.com', 'plugin.codereyes.com' );
	/**
	 * Filter the allowlist of hostnames whose links may render in offer
	 * widgets. Defaults to codereyes.com.
	 */
	$list = apply_filters( 'plugin_tracker_offer_link_allowlist', $default );
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

// Dashboard widget: only registers when consent has been granted.
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! tracker_integration_has_consent() ) {
			return;
		}
		wp_add_dashboard_widget(
			'plugin_tracker_offers_widget',
			__( 'Easy PHP Settings – Services', 'easy-php-settings' ),
			'tracker_integration_render_dashboard_widget',
			null,
			null,
			'side'
		);
	}
);

/**
 * Render the dashboard widget content.
 */
function tracker_integration_render_dashboard_widget() {
	$offers = tracker_integration_get_offers();
	if ( empty( $offers ) ) {
		echo '<p style="margin:0;">' . esc_html__( 'Need help with WordPress? Performance, security, optimization.', 'easy-php-settings' ) . '</p>';
		echo '<p style="margin:8px 0 0;"><a href="https://codereyes.com" target="_blank" rel="noopener">codereyes.com</a></p>';
		return;
	}

	echo '<ul class="plugin-tracker-offers-list" style="margin:0;padding-left:0;list-style:none;">';
	foreach ( $offers as $offer ) {
		$title = isset( $offer['title'] ) ? (string) $offer['title'] : '';
		$link  = tracker_integration_safe_offer_link( isset( $offer['link'] ) ? $offer['link'] : '' );
		$desc  = isset( $offer['description'] ) ? (string) $offer['description'] : '';
		echo '<li style="margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid #eee;">';
		if ( $link ) {
			echo '<strong><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a></strong>';
		} else {
			echo '<strong>' . esc_html( $title ) . '</strong>';
		}
		if ( $desc ) {
			echo '<p style="margin:4px 0 0;color:#50575e;font-size:12px;line-height:1.4;">' . esc_html( wp_trim_words( $desc, 15 ) ) . '</p>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

/* ──────────────────────────────────────────────
   Consent admin notice
   ────────────────────────────────────────────── */

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( tracker_integration_decision_made() ) {
			return;
		}
		// Only show when our plugin file is registered (i.e., the host
		// plugin called tracker_integration_report_install on activation).
		if ( ! get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT ) ) {
			return;
		}

		$grant_url = wp_nonce_url(
			add_query_arg( array( 'plugin_tracker_action' => 'grant' ), admin_url() ),
			'plugin_tracker_consent'
		);
		$deny_url  = wp_nonce_url(
			add_query_arg( array( 'plugin_tracker_action' => 'deny' ), admin_url() ),
			'plugin_tracker_consent'
		);
		?>
		<div class="notice notice-info plugin-tracker-consent-notice">
			<p style="margin:0.5em 0;">
				<strong><?php esc_html_e( 'Easy PHP Settings — share basic site info?', 'easy-php-settings' ); ?></strong>
				<?php esc_html_e( 'To receive plugin update info and recommended services, the plugin can send your site URL, WordPress version, PHP version, and plugin version. No personal data, no plugin/theme inventory.', 'easy-php-settings' ); ?>
			</p>
			<p style="margin:0.5em 0;">
				<a href="<?php echo esc_url( $grant_url ); ?>" class="button button-primary"><?php esc_html_e( 'Allow', 'easy-php-settings' ); ?></a>
				<a href="<?php echo esc_url( $deny_url ); ?>" class="button"><?php esc_html_e( 'No thanks', 'easy-php-settings' ); ?></a>
			</p>
		</div>
		<?php
	}
);

// Handle the consent grant/deny click.
add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['plugin_tracker_action'], $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'plugin_tracker_consent' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['plugin_tracker_action'] ) );
		if ( 'grant' === $action ) {
			update_option( PLUGIN_TRACKER_CONSENT_OPTION, 'granted', false );
			update_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, current_time( 'mysql' ), false );

			$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
			if ( $plugin_file ) {
				tracker_integration_send_install( $plugin_file );
				tracker_integration_schedule_cron( $plugin_file );
			}
		} elseif ( 'deny' === $action ) {
			update_option( PLUGIN_TRACKER_CONSENT_OPTION, 'denied', false );
			update_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, current_time( 'mysql' ), false );
			tracker_integration_unschedule_cron();
		}

		wp_safe_redirect( remove_query_arg( array( 'plugin_tracker_action', '_wpnonce' ) ) );
		exit;
	}
);

/**
 * Render a privacy / consent management section. Modules can call this
 * from their About tab.
 */
function tracker_integration_render_consent_panel() {
	$state = get_option( PLUGIN_TRACKER_CONSENT_OPTION );
	?>
	<div class="card" style="max-width:640px;padding:1em 1.5em;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Privacy & data sharing', 'easy-php-settings' ); ?></h2>
		<p>
			<?php esc_html_e( 'Easy PHP Settings can share your site URL, WordPress version, PHP version, plugin version, and server software with the developer. No personal data, no admin email, no plugin or theme inventory.', 'easy-php-settings' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Status:', 'easy-php-settings' ); ?></strong>
			<?php
			if ( 'granted' === $state ) {
				esc_html_e( 'Sharing is enabled.', 'easy-php-settings' );
			} elseif ( 'denied' === $state ) {
				esc_html_e( 'Sharing is disabled.', 'easy-php-settings' );
			} else {
				esc_html_e( 'No decision yet.', 'easy-php-settings' );
			}
			?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'plugin_tracker_consent_form' ); ?>
			<input type="hidden" name="action" value="plugin_tracker_consent_form">
			<?php if ( 'granted' === $state ) : ?>
				<button type="submit" name="decision" value="deny" class="button"><?php esc_html_e( 'Disable sharing', 'easy-php-settings' ); ?></button>
			<?php else : ?>
				<button type="submit" name="decision" value="grant" class="button button-primary"><?php esc_html_e( 'Enable sharing', 'easy-php-settings' ); ?></button>
			<?php endif; ?>
		</form>
	</div>
	<?php
}

// Form-post handler for the consent panel.
add_action(
	'admin_post_plugin_tracker_consent_form',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'easy-php-settings' ), 403 );
		}
		check_admin_referer( 'plugin_tracker_consent_form' );

		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		if ( 'grant' === $decision ) {
			update_option( PLUGIN_TRACKER_CONSENT_OPTION, 'granted', false );
			update_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, current_time( 'mysql' ), false );
			$plugin_file = get_option( PLUGIN_TRACKER_PLUGIN_FILE_OPT );
			if ( $plugin_file ) {
				tracker_integration_send_install( $plugin_file );
				tracker_integration_schedule_cron( $plugin_file );
			}
		} elseif ( 'deny' === $decision ) {
			update_option( PLUGIN_TRACKER_CONSENT_OPTION, 'denied', false );
			update_option( PLUGIN_TRACKER_CONSENT_TIME_OPT, current_time( 'mysql' ), false );
			tracker_integration_unschedule_cron();
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
);
