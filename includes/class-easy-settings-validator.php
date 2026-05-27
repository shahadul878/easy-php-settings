<?php
/**
 * Settings Validator class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Settings_Validator class
 *
 * Validates PHP settings and input values.
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */
class Easy_Settings_Validator {

	/**
	 * Valid PHP setting patterns
	 *
	 * @var array
	 */
	private static $setting_patterns = array(
		'memory_limit'        => '/^(\d+)([KMGT]?)$/i',
		'upload_max_filesize' => '/^(\d+)([KMGT]?)$/i',
		'post_max_size'       => '/^(\d+)([KMGT]?)$/i',
		'max_execution_time'  => '/^\d+$/',
		'max_input_vars'      => '/^\d+$/',
	);

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Validate a PHP setting value
	 *
	 * @param string $key The setting key.
	 * @param string $value The value to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_setting( $key, $value ) {
		if ( empty( $value ) ) {
			return true; // Empty values are allowed (will use defaults).
		}

		// Check if we have a pattern for this setting.
		if ( isset( self::$setting_patterns[ $key ] ) ) {
			if ( ! preg_match( self::$setting_patterns[ $key ], trim( $value ) ) ) {
				return new WP_Error(
					'invalid_format',
					sprintf(
						/* translators: %s: Setting key */
						__( 'Invalid format for %s.', 'easy-php-settings' ),
						$key
					)
				);
			}
		}

		// Additional validation based on setting type.
		switch ( $key ) {
			case 'memory_limit':
			case 'upload_max_filesize':
			case 'post_max_size':
				return self::validate_size_value( $value );

			case 'max_execution_time':
				$int_value = intval( $value );
				if ( $int_value < 0 || $int_value > 86400 ) { // Max 24 hours.
					return new WP_Error(
						'invalid_range',
						__( 'max_execution_time must be between 0 and 86400 seconds.', 'easy-php-settings' )
					);
				}
				break;

			case 'max_input_vars':
				$int_value = intval( $value );
				if ( $int_value < 100 || $int_value > 100000 ) {
					return new WP_Error(
						'invalid_range',
						__( 'max_input_vars must be between 100 and 100000.', 'easy-php-settings' )
					);
				}
				break;
		}

		return true;
	}

	/**
	 * Validate a size value (memory, upload, post size)
	 *
	 * @param string $value The value to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	private static function validate_size_value( $value ) {
		$value = trim( $value );
		if ( empty( $value ) ) {
			return true;
		}

		// Match pattern: number followed by optional unit (K, M, G, T).
		if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $value, $matches ) ) {
			return new WP_Error(
				'invalid_size_format',
				__( 'Size value must be a number followed by optional unit (K, M, G, T).', 'easy-php-settings' )
			);
		}

		$number = intval( $matches[1] );
		$unit   = strtoupper( $matches[2] ?? '' );

		// Validate reasonable limits.
		$max_values = array(
			''  => 8589934592,  // 8GB in bytes (no unit = bytes).
			'K' => 8388608,     // 8GB in KB.
			'M' => 8192,        // 8GB in MB.
			'G' => 8,           // 8GB in GB.
			'T' => 0.0078125,   // 8GB in TB.
		);

		if ( isset( $max_values[ $unit ] ) ) {
			$max = $max_values[ $unit ];
			if ( $number > $max ) {
				return new WP_Error(
					'size_too_large',
					sprintf(
						/* translators: %s: Maximum value */
						__( 'Size value is too large. Maximum allowed is %s.', 'easy-php-settings' ),
						$max . $unit
					)
				);
			}
		}

		return true;
	}

	/**
	 * Validate WordPress memory setting
	 *
	 * @param string $key The setting key.
	 * @param string $value The value to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_wp_memory_setting( $key, $value ) {
		if ( empty( $value ) ) {
			return true;
		}

		return self::validate_size_value( $value );
	}

	/**
	 * Validate settings relationships
	 *
	 * @param array $settings The settings array.
	 * @return array Array of validation errors (empty if valid).
	 */
	public static function validate_settings_relationships( $settings ) {
		$errors = array();

		// Check post_max_size >= upload_max_filesize.
		if ( isset( $settings['post_max_size'] ) && isset( $settings['upload_max_filesize'] ) ) {
			$post_max   = self::convert_to_bytes( $settings['post_max_size'] );
			$upload_max = self::convert_to_bytes( $settings['upload_max_filesize'] );
			if ( $post_max > 0 && $upload_max > 0 && $post_max < $upload_max ) {
				$errors[] = __( 'post_max_size should be larger than or equal to upload_max_filesize.', 'easy-php-settings' );
			}
		}

		// Check memory_limit >= post_max_size.
		if ( isset( $settings['memory_limit'] ) && isset( $settings['post_max_size'] ) ) {
			$memory_limit = self::convert_to_bytes( $settings['memory_limit'] );
			$post_max     = self::convert_to_bytes( $settings['post_max_size'] );
			if ( $memory_limit > 0 && $post_max > 0 && $memory_limit < $post_max ) {
				$errors[] = __( 'memory_limit should be larger than or equal to post_max_size.', 'easy-php-settings' );
			}
		}

		return $errors;
	}

	/**
	 * Validate uploaded file for import
	 *
	 * @param array $file The uploaded file array.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_import_file( $file ) {
		// Check if file was uploaded.
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'Invalid file upload.', 'easy-php-settings' ) );
		}

		// Check file extension.
		$file_name = $file['name'] ?? '';
		$file_ext  = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( 'json' !== $file_ext ) {
			return new WP_Error( 'invalid_extension', __( 'Only JSON files are allowed for import.', 'easy-php-settings' ) );
		}

		// Check file size (max 1MB).
		$file_size = $file['size'] ?? 0;
		if ( $file_size > 1048576 ) {
			return new WP_Error( 'file_too_large', __( 'File size exceeds maximum allowed size (1MB).', 'easy-php-settings' ) );
		}

		// Check file is readable.
		if ( ! is_readable( $file['tmp_name'] ) ) {
			return new WP_Error( 'file_not_readable', __( 'File is not readable.', 'easy-php-settings' ) );
		}

		return true;
	}

	/**
	 * Convert value to bytes
	 *
	 * @param string $value The value to convert.
	 * @return int Value in bytes.
	 */
	public static function convert_to_bytes( $value ) {
		if ( empty( $value ) ) {
			return 0;
		}

		$value = trim( $value );
		$last  = strtolower( substr( $value, -1 ) );
		$value = (int) $value;

		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through.
			case 'm':
				$value *= 1024;
				// Fall through.
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Sanitize PHP setting value
	 *
	 * @param string $key The setting key.
	 * @param string $value The value to sanitize.
	 * @return string Sanitized value.
	 */
	public static function sanitize_setting( $key, $value ) {
		$value = trim( (string) $value );

		// Security: Strict whitelist - only allow digits and K/M/G/T units
		// Remove any potentially dangerous characters (everything except allowed chars).
		$sanitized = preg_replace( '/[^0-9KMGT]/i', '', $value );

		// Additional validation: ensure the result matches expected format
		// If sanitization removed everything or result is invalid, return empty string
		if ( empty( $sanitized ) || ! preg_match( '/^(\d+)([KMGT]?)$/i', $sanitized ) ) {
			return '';
		}

		return $sanitized;
	}

	/**
	 * Directives that must never be writable to .user.ini / php.ini from
	 * the admin UI. These can lead to arbitrary code execution, sandbox
	 * escape, or destabilising server-wide configuration.
	 *
	 * @return string[]
	 */
	public static function blocked_ini_directives() {
		return array(
			'auto_prepend_file',
			'auto_append_file',
			'extension',
			'zend_extension',
			'extension_dir',
			'open_basedir',
			'disable_functions',
			'disable_classes',
			'sendmail_path',
			'mail.force_extra_parameters',
			'include_path',
			'safe_mode_exec_dir',
			'curl.cainfo',
			'openssl.cafile',
			'openssl.capath',
			'session.save_path',
			'sys_temp_dir',
			'upload_tmp_dir',
		);
	}

	/**
	 * Validate and sanitize a free-form php.ini block (the "Custom PHP
	 * Configuration" textarea). Strips comment lines and validates each
	 * directive against the denylist + a directive-name whitelist regex.
	 *
	 * @param string $raw Raw textarea content.
	 * @return array{0: string, 1: string[]} Tuple of [sanitized content, errors].
	 */
	public static function sanitize_custom_php_ini( $raw ) {
		$raw     = (string) $raw;
		$blocked = array_map( 'strtolower', self::blocked_ini_directives() );
		$errors  = array();
		$out     = array();

		// Normalise line endings and split.
		$lines = preg_split( '/\r\n|\r|\n/', $raw );

		foreach ( $lines as $line ) {
			$trim = trim( $line );

			// Preserve blank lines and comments verbatim.
			if ( '' === $trim || 0 === strpos( $trim, ';' ) || 0 === strpos( $trim, '#' ) ) {
				$out[] = $line;
				continue;
			}

			// Section headers like [PHP] are allowed but stripped of any
			// stray characters; we keep them simple.
			if ( preg_match( '/^\[[a-z0-9_.\-]+\]$/i', $trim ) ) {
				$out[] = $line;
				continue;
			}

			// Match: directive_name = value
			// Directive names must match PHP's well-known character class:
			// letters, digits, underscores, and dots.
			if ( ! preg_match( '/^([a-z_][a-z0-9_.]*)\s*=\s*(.*)$/i', $trim, $m ) ) {
				/* translators: %s: rejected line */
				$errors[] = sprintf( __( 'Skipped invalid php.ini line: %s', 'easy-php-settings' ), $trim );
				continue;
			}

			$directive = strtolower( $m[1] );
			$value     = $m[2];

			if ( in_array( $directive, $blocked, true ) ) {
				/* translators: %s: directive name */
				$errors[] = sprintf( __( 'Directive %s is not allowed and was removed for security reasons.', 'easy-php-settings' ), $directive );
				continue;
			}

			// Reject value containing any control character or NUL.
			if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value ) ) {
				/* translators: %s: directive name */
				$errors[] = sprintf( __( 'Directive %s contains control characters and was removed.', 'easy-php-settings' ), $directive );
				continue;
			}

			// Strip any trailing inline comment (after a semicolon outside
			// of quoted strings). Conservative: cut at first unquoted ';'.
			$cleaned_value = self::strip_inline_comment( $value );

			$out[] = $directive . ' = ' . trim( $cleaned_value );
		}

		return array( implode( "\n", $out ), $errors );
	}

	/**
	 * Cut a php.ini value at the first unquoted semicolon (php.ini inline
	 * comment marker). Conservative; returns the raw string if no comment.
	 *
	 * @param string $value Raw value portion of a directive line.
	 * @return string
	 */
	private static function strip_inline_comment( $value ) {
		$len    = strlen( $value );
		$quote  = null;
		$result = '';
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $value[ $i ];
			if ( null !== $quote ) {
				$result .= $ch;
				if ( $ch === $quote && ( $i === 0 || '\\' !== $value[ $i - 1 ] ) ) {
					$quote = null;
				}
				continue;
			}
			if ( '"' === $ch || "'" === $ch ) {
				$quote   = $ch;
				$result .= $ch;
				continue;
			}
			if ( ';' === $ch ) {
				break;
			}
			$result .= $ch;
		}
		return $result;
	}
}

