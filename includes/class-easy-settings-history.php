<?php
/**
 * Settings History class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.4
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Settings_History class
 *
 * Handles settings history tracking and restoration.
 *
 * @package EasyPHPSettings
 * @since 1.0.4
 */
class Easy_Settings_History {

	/**
	 * Option key for storing history
	 *
	 * @var string
	 */
	const HISTORY_OPTION_KEY = 'easy_php_settings_history';

	/**
	 * Maximum number of history entries to keep
	 *
	 * @var int
	 */
	const MAX_HISTORY_ENTRIES = 50;

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Add a history entry
	 *
	 * @param array  $old_values The old values.
	 * @param array  $new_values The new values.
	 * @param string $setting_type The type of setting (php_settings, wp_memory, debugging).
	 * @return bool
	 */
	public static function add_entry( $old_values, $new_values, $setting_type = 'php_settings' ) {
		$history = self::get_history();
		$user    = wp_get_current_user();

		// Create history entry.
		$entry = array(
			'timestamp'    => current_time( 'mysql' ),
			'user_id'      => $user->ID,
			'user_login'   => $user->user_login,
			'setting_type' => $setting_type,
			'changes'      => array(),
		);

		// Track what changed.
		$all_keys = array_unique( array_merge( array_keys( $old_values ), array_keys( $new_values ) ) );
		foreach ( $all_keys as $key ) {
			$old = isset( $old_values[ $key ] ) ? $old_values[ $key ] : '';
			$new = isset( $new_values[ $key ] ) ? $new_values[ $key ] : '';

			if ( $old !== $new ) {
				$entry['changes'][ $key ] = array(
					'old' => $old,
					'new' => $new,
				);
			}
		}

		// Only add if there were changes.
		if ( ! empty( $entry['changes'] ) ) {
			array_unshift( $history, $entry );

			// Keep only the last MAX_HISTORY_ENTRIES entries.
			if ( count( $history ) > self::MAX_HISTORY_ENTRIES ) {
				$history = array_slice( $history, 0, self::MAX_HISTORY_ENTRIES );
			}

			$saved = self::save_history( $history );

			// Invalidate cache when history changes.
			if ( $saved ) {
				Easy_Settings_Cache::invalidate( 'history' );
			}

			return $saved;
		}

		return false;
	}

	/**
	 * Get history
	 *
	 * @param int $limit Maximum number of entries to return (0 for all).
	 * @param int $offset Offset for pagination.
	 * @return array
	 */
	public static function get_history( $limit = 0, $offset = 0 ) {
		// Check cache first if no pagination.
		if ( 0 === $limit && 0 === $offset ) {
			$cached = Easy_Settings_Cache::get( 'history' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$history = is_multisite() ? get_site_option( self::HISTORY_OPTION_KEY, array() ) : get_option( self::HISTORY_OPTION_KEY, array() );

		if ( ! is_array( $history ) ) {
			return array();
		}

		// Apply pagination if limit is set.
		if ( $limit > 0 ) {
			$history = array_slice( $history, $offset, $limit );
		}

		// Cache full history if no pagination.
		if ( 0 === $limit && 0 === $offset ) {
			Easy_Settings_Cache::set( 'history', $history );
		}

		return $history;
	}

	/**
	 * Get total number of history entries
	 *
	 * @return int
	 */
	public static function get_history_count() {
		$history = is_multisite() ? get_site_option( self::HISTORY_OPTION_KEY, array() ) : get_option( self::HISTORY_OPTION_KEY, array() );
		return is_array( $history ) ? count( $history ) : 0;
	}

	/**
	 * Save history
	 *
	 * @param array $history The history array.
	 * @return bool
	 */
	private static function save_history( $history ) {
		if ( is_multisite() ) {
			return update_site_option( self::HISTORY_OPTION_KEY, $history );
		}
		return update_option( self::HISTORY_OPTION_KEY, $history );
	}

	/**
	 * Clear history
	 *
	 * @return bool
	 */
	public static function clear_history() {
		$deleted = is_multisite() ? delete_site_option( self::HISTORY_OPTION_KEY ) : delete_option( self::HISTORY_OPTION_KEY );

		// Invalidate cache.
		if ( $deleted ) {
			Easy_Settings_Cache::invalidate( 'history' );
		}

		return $deleted;
	}

	/**
	 * Get a specific history entry
	 *
	 * @param int $index The index of the history entry.
	 * @return array|null
	 */
	public static function get_entry( $index ) {
		$history = self::get_history();
		return isset( $history[ $index ] ) ? $history[ $index ] : null;
	}

	/**
	 * Export history as CSV
	 *
	 * @return string
	 */
	public static function export_as_csv() {
		$history = self::get_history();
		$csv     = "Timestamp,User,Setting Type,Setting,Old Value,New Value\n";

		foreach ( $history as $entry ) {
			$timestamp    = $entry['timestamp'];
			$user         = $entry['user_login'];
			$setting_type = $entry['setting_type'];

			foreach ( $entry['changes'] as $key => $change ) {
				$csv .= sprintf(
					'"%s","%s","%s","%s","%s","%s"' . "\n",
					$timestamp,
					$user,
					$setting_type,
					$key,
					$change['old'],
					$change['new']
				);
			}
		}

		return $csv;
	}
}
