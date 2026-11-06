<?php
/**
 * Reports the PHP values the server is really using.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Live_Check
 *
 * Meant to run in a request where the plugin has not called ini_set() (the
 * REST API), so ini_get() returns what PHP took from its INI files.
 */
class Easy_Live_Check {

	/**
	 * Option holding the time PHP settings were last written to the INI files.
	 */
	const LAST_SAVED_OPTION = 'easy_php_settings_last_saved';

	/**
	 * Plugin orchestrator.
	 *
	 * @var Easy_PHP_Settings
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin Plugin orchestrator.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Rows for the PHP settings managed by the plugin.
	 *
	 * @return array[]
	 */
	public function php_rows() {
		$saved = $this->plugin->get_option( 'easy_php_settings_settings', array() );
		return self::rows(
			$this->plugin->get_settings_keys(),
			is_array( $saved ) ? $saved : array(),
			function ( $key ) {
				return (string) ini_get( $key );
			},
			$this->plugin->get_recommended_values()
		);
	}

	/**
	 * Whether this server applies .user.ini files.
	 *
	 * @return bool
	 */
	public function server_reads_user_ini() {
		return Easy_Ini_Change_Method::server_reads_user_ini( php_sapi_name(), (string) ini_get( 'user_ini.filename' ) );
	}

	/**
	 * Seconds since PHP settings were last written; very large if never.
	 *
	 * @return int
	 */
	public function seconds_since_save() {
		$saved_at = (int) $this->plugin->get_option( self::LAST_SAVED_OPTION, 0 );
		return $saved_at > 0 ? max( 0, time() - $saved_at ) : PHP_INT_MAX;
	}

	/**
	 * Seconds PHP caches .user.ini for.
	 *
	 * @return int
	 */
	public function cache_ttl() {
		return (int) ini_get( 'user_ini.cache_ttl' );
	}

	/**
	 * Record that PHP settings were just written to the INI files.
	 *
	 * @return void
	 */
	public function mark_saved() {
		$this->plugin->update_option( self::LAST_SAVED_OPTION, time() );
	}

	/**
	 * Saved values the server is still not using once PHP should have picked them up.
	 *
	 * With .user.ini support, a value only counts as blocked after the cache
	 * time has passed since the last save. Without it, the plugin cannot apply
	 * the value at all, so it is blocked straight away.
	 *
	 * @param array[] $rows               Rows from rows().
	 * @param bool    $reads_user_ini     Whether the server reads .user.ini.
	 * @param int     $seconds_since_save Seconds since the last save.
	 * @param int     $cache_ttl          Seconds PHP caches .user.ini for.
	 * @return array[] Blocked rows, re-indexed.
	 */
	public static function blocked_rows( array $rows, $reads_user_ini, $seconds_since_save, $cache_ttl ) {
		if ( $reads_user_ini && $seconds_since_save < $cache_ttl ) {
			return array();
		}
		return array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return Easy_Limit_Health::PENDING === $row['applied'];
				}
			)
		);
	}

	/**
	 * Plain-text request a site owner can send to their host.
	 *
	 * @param array[] $blocked  Rows from blocked_rows().
	 * @param string  $site_url Site address.
	 * @return string '' when nothing is blocked.
	 */
	public static function host_request_text( array $blocked, $site_url ) {
		if ( empty( $blocked ) ) {
			return '';
		}
		$lines = array_map(
			function ( $row ) {
				return $row['key'] . ' = ' . $row['saved'];
			},
			$blocked
		);
		return sprintf(
			/* translators: 1: site address, 2: one "setting = value" per line */
			__( "Hello,\n\nPlease set these PHP values for my website %1\$s:\n\n%2\$s\n\nThank you!", 'easy-php-settings' ),
			$site_url,
			implode( "\n", $lines )
		);
	}

	/**
	 * Build rows from saved values, live values and recommendations.
	 *
	 * @param string[] $keys        Setting keys.
	 * @param array    $saved       Saved values by key.
	 * @param callable $live_value  Returns the value in effect for a key.
	 * @param array    $recommended Recommended values by key.
	 * @return array[]
	 */
	public static function rows( array $keys, array $saved, callable $live_value, array $recommended ) {
		$rows = array();
		foreach ( $keys as $key ) {
			$value = isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '';
			$live  = (string) call_user_func( $live_value, $key );
			$rec   = isset( $recommended[ $key ] ) ? (string) $recommended[ $key ] : '';

			$rows[] = array(
				'key'         => $key,
				'saved'       => $value,
				'live'        => $live,
				'recommended' => $rec,
				'health'      => Easy_Limit_Health::status( $key, $live, $rec ),
				'applied'     => Easy_Limit_Health::applied_state( $key, $value, $live ),
			);
		}
		return $rows;
	}
}
