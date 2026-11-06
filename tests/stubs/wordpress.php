<?php
/**
 * Minimal WordPress and WP-CLI stand-ins for unit tests.
 *
 * Only what the classes under test call is stubbed. WP_CLI records every
 * call so tests can assert on output; WP_CLI::error() throws, like the real
 * one halts.
 *
 * @package EasyPHPSettings
 */

// phpcs:disable

namespace {

	class WP_Error {
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		$GLOBALS['wp_settings_errors'][] = array(
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		);
	}

	function get_settings_errors( $setting = '', $sanitize = false ) {
		return isset( $GLOBALS['wp_settings_errors'] ) ? $GLOBALS['wp_settings_errors'] : array();
	}

	class WP_CLI_Halt extends Exception {}

	class WP_CLI {
		public static $calls = array();

		public static function reset() {
			self::$calls = array();
		}

		public static function success( $message ) {
			self::$calls[] = array( 'success', $message );
		}

		public static function warning( $message ) {
			self::$calls[] = array( 'warning', $message );
		}

		public static function log( $message ) {
			self::$calls[] = array( 'log', $message );
		}

		public static function error( $message ) {
			self::$calls[] = array( 'error', $message );
			throw new WP_CLI_Halt( $message );
		}
	}
}

namespace WP_CLI\Utils {

	function format_items( $format, $items, $fields ) {
		\WP_CLI::$calls[] = array( 'format_items', $format, $items, $fields );
	}
}
