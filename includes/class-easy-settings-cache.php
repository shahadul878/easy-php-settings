<?php
/**
 * Settings Cache class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Settings_Cache class
 *
 * Handles caching for expensive operations.
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */
class Easy_Settings_Cache {

	/**
	 * Cache prefix
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'easy_php_settings_cache_';

	/**
	 * Cache expiration times (in seconds)
	 *
	 * @var array
	 */
	private static $expiration_times = array(
		'php_info'     => 3600,      // 1 hour.
		'extensions'   => 3600,      // 1 hour.
		'history'      => 300,       // 5 minutes.
		'settings'     => 300,       // 5 minutes.
	);

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Get cached value
	 *
	 * @param string $key Cache key.
	 * @return mixed|false Cached value or false if not found/expired.
	 */
	public static function get( $key ) {
		$cache_key = self::CACHE_PREFIX . $key;
		return get_transient( $cache_key );
	}

	/**
	 * Set cached value
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $expiration Optional expiration time in seconds.
	 * @return bool True on success.
	 */
	public static function set( $key, $value, $expiration = null ) {
		$cache_key = self::CACHE_PREFIX . $key;

		if ( null === $expiration ) {
			$expiration = self::get_expiration_time( $key );
		}

		return set_transient( $cache_key, $value, $expiration );
	}

	/**
	 * Delete cached value
	 *
	 * @param string $key Cache key.
	 * @return bool True on success.
	 */
	public static function delete( $key ) {
		$cache_key = self::CACHE_PREFIX . $key;
		return delete_transient( $cache_key );
	}

	/**
	 * Clear all plugin caches
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;

		$pattern = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// Also delete timeout entries.
		$pattern = $wpdb->esc_like( '_transient_timeout_' . self::CACHE_PREFIX ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$pattern
			)
		);

		// For multisite.
		if ( is_multisite() ) {
			$pattern = $wpdb->esc_like( '_site_transient_' . self::CACHE_PREFIX ) . '%';
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
					$pattern
				)
			);

			$pattern = $wpdb->esc_like( '_site_transient_timeout_' . self::CACHE_PREFIX ) . '%';
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
					$pattern
				)
			);
		}
	}

	/**
	 * Get expiration time for a cache key
	 *
	 * @param string $key Cache key.
	 * @return int Expiration time in seconds.
	 */
	private static function get_expiration_time( $key ) {
		// Extract base key (remove any suffixes).
		$base_key = explode( '_', $key )[0] ?? $key;

		return self::$expiration_times[ $base_key ] ?? 300; // Default 5 minutes.
	}

	/**
	 * Invalidate cache for a specific key
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public static function invalidate( $key ) {
		self::delete( $key );
	}

	/**
	 * Warm up cache for frequently accessed data
	 *
	 * @return void
	 */
	public static function warm_up() {
		// Cache PHP info if not already cached.
		if ( false === self::get( 'php_info' ) ) {
			if ( class_exists( 'EasyPHPInfo' ) ) {
				$php_info = EasyPHPInfo::get_as_array();
				if ( ! empty( $php_info ) ) {
					self::set( 'php_info', $php_info );
				}
			}
		}

		// Cache extensions if not already cached.
		if ( false === self::get( 'extensions' ) ) {
			if ( class_exists( 'Easy_Extensions_Viewer' ) ) {
				$extensions = Easy_Extensions_Viewer::get_categorized_extensions();
				if ( ! empty( $extensions ) ) {
					self::set( 'extensions', $extensions );
				}
			}
		}
	}
}

