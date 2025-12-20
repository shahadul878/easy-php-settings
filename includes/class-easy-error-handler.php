<?php
/**
 * Error Handler class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Error_Handler class
 *
 * Handles error logging and user-friendly error messages.
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */
class Easy_Error_Handler {

	/**
	 * Log option key
	 *
	 * @var string
	 */
	const LOG_OPTION_KEY = 'easy_php_settings_error_log';

	/**
	 * Maximum log entries
	 *
	 * @var int
	 */
	const MAX_LOG_ENTRIES = 100;

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Log an error
	 *
	 * @param string $message Error message.
	 * @param string $context Additional context.
	 * @param string $level Error level (error, warning, notice).
	 * @return void
	 */
	public static function log_error( $message, $context = '', $level = 'error' ) {
		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'user_id'   => get_current_user_id(),
			'ip'        => self::get_client_ip(),
		);

		$logs = self::get_logs();
		array_unshift( $logs, $log_entry );

		// Keep only the most recent entries.
		if ( count( $logs ) > self::MAX_LOG_ENTRIES ) {
			$logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES );
		}

		// Save logs.
		if ( is_multisite() ) {
			update_site_option( self::LOG_OPTION_KEY, $logs );
		} else {
			update_option( self::LOG_OPTION_KEY, $logs );
		}

		// Also log to WordPress debug log if enabled.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_message = sprintf(
				'[Easy PHP Settings] [%s] %s',
				strtoupper( $level ),
				$message
			);
			if ( ! empty( $context ) ) {
				$log_message .= ' Context: ' . $context;
			}
			error_log( $log_message );
		}
	}

	/**
	 * Get all logs
	 *
	 * @param int $limit Maximum number of logs to return.
	 * @return array Array of log entries.
	 */
	public static function get_logs( $limit = 50 ) {
		$logs = is_multisite() ? get_site_option( self::LOG_OPTION_KEY, array() ) : get_option( self::LOG_OPTION_KEY, array() );
		if ( ! is_array( $logs ) ) {
			return array();
		}
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear logs
	 *
	 * @return bool True on success.
	 */
	public static function clear_logs() {
		if ( is_multisite() ) {
			return delete_site_option( self::LOG_OPTION_KEY );
		}
		return delete_option( self::LOG_OPTION_KEY );
	}

	/**
	 * Handle an exception and log it
	 *
	 * @param Exception|Error $exception The exception to handle.
	 * @param string          $context Additional context.
	 * @return void
	 */
	public static function handle_exception( $exception, $context = '' ) {
		$message = sprintf(
			'Exception: %s in %s on line %d',
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine()
		);
		self::log_error( $message, $context, 'error' );
	}

	/**
	 * Get user-friendly error message
	 *
	 * @param WP_Error|Exception|Error|string $error The error object or message.
	 * @return string User-friendly error message.
	 */
	public static function get_user_message( $error ) {
		if ( is_wp_error( $error ) ) {
			return $error->get_error_message();
		}

		if ( $error instanceof Exception || $error instanceof Error ) {
			// Don't expose file paths to users.
			return __( 'An error occurred. Please check the error log for details.', 'easy-php-settings' );
		}

		return is_string( $error ) ? $error : __( 'An unknown error occurred.', 'easy-php-settings' );
	}

	/**
	 * Get client IP address
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_REAL_IP', // Nginx proxy.
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip );
					$ip = trim( $ip[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Add settings error with logging
	 *
	 * @param string $setting The setting slug.
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param string $type Message type (error, warning, updated).
	 * @return void
	 */
	public static function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		add_settings_error( $setting, $code, $message, $type );
		if ( 'error' === $type ) {
			self::log_error( $message, "Setting: {$setting}, Code: {$code}", 'error' );
		}
	}
}

