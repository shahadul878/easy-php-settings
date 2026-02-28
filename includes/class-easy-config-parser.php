<?php
/**
 * Config Parser class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Config_Parser class
 *
 * Safely parses and modifies wp-config.php file using token-based parsing.
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */
class Easy_Config_Parser {

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Update or add a constant definition in wp-config.php
	 *
	 * @param string $config_content The current config file content.
	 * @param string $constant_name The constant name (e.g., 'WP_DEBUG').
	 * @param mixed  $value The value to set (will be converted to string).
	 * @param string $type The type of value ('bool', 'string', 'int').
	 * @return string|WP_Error Modified config content or WP_Error on failure.
	 */
	public static function update_constant( $config_content, $constant_name, $value, $type = 'bool' ) {
		// Validate constant name.
		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $constant_name ) ) {
			return new WP_Error( 'invalid_constant_name', __( 'Invalid constant name.', 'easy-php-settings' ) );
		}

		// Format value based on type.
		$formatted_value = self::format_constant_value( $value, $type );

		// Try to find and replace existing constant.
		$patterns = array(
			// Pattern 1: define( 'CONSTANT', value );
			'/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*[^;]+?\)\s*;/i',
			// Pattern 2: define( "CONSTANT", value );
			'/define\s*\(\s*["\']' . preg_quote( $constant_name, '/' ) . '["\']\s*,\s*[^;]+?\)\s*;/i',
		);

		$replaced = false;
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $config_content ) ) {
				$replacement = "define( '{$constant_name}', {$formatted_value} );";
				$config_content = preg_replace( $pattern, $replacement, $config_content );
				$replaced = true;
				break;
			}
		}

		// If constant doesn't exist, add it before the "stop editing" comment.
		if ( ! $replaced ) {
			$new_constant = "\ndefine( '{$constant_name}', {$formatted_value} );\n";
			$stop_comment = "/* That's all, stop editing!";

			if ( strpos( $config_content, $stop_comment ) !== false ) {
				$config_content = str_replace( $stop_comment, $new_constant . $stop_comment, $config_content );
			} else {
				// If no stop comment, add before the closing PHP tag or at the end.
				if ( preg_match( '/\?>\s*$/', $config_content ) ) {
					$config_content = preg_replace( '/\?>\s*$/', $new_constant . "\n?>\n", $config_content );
				} else {
					$config_content .= $new_constant;
				}
			}
		}

		// Validate the modified content.
		$validation = Easy_Config_Backup::validate_config_structure( $config_content );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $config_content;
	}

	/**
	 * Format constant value based on type
	 *
	 * @param mixed  $value The value to format.
	 * @param string $type The type ('bool', 'string', 'int').
	 * @return string Formatted value string.
	 */
	private static function format_constant_value( $value, $type ) {
		switch ( $type ) {
			case 'bool':
				return $value ? 'true' : 'false';

			case 'string':
				// Security: Validate string value to prevent code injection
				// Only allow safe characters: digits and K/M/G/T units
				$trimmed_value = trim( (string) $value );
				if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $trimmed_value ) ) {
					// If invalid, return empty string to prevent injection
					return "''";
				}
				// Re-sanitize to ensure no malicious characters
				$sanitized = preg_replace( '/[^0-9KMGT]/i', '', $trimmed_value );
				// Escape single quotes and wrap in quotes.
				$escaped = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $sanitized );
				return "'{$escaped}'";

			case 'int':
				return (string) intval( $value );

			default:
				// Default to string with validation.
				$trimmed_value = trim( (string) $value );
				if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $trimmed_value ) ) {
					// If invalid, return empty string to prevent injection
					return "''";
				}
				// Re-sanitize to ensure no malicious characters
				$sanitized = preg_replace( '/[^0-9KMGT]/i', '', $trimmed_value );
				$escaped = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $sanitized );
				return "'{$escaped}'";
		}
	}

	/**
	 * Get constant value from config file
	 *
	 * @param string $config_content The config file content.
	 * @param string $constant_name The constant name.
	 * @return mixed|null The constant value or null if not found.
	 */
	public static function get_constant_value( $config_content, $constant_name ) {
		$patterns = array(
			'/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]\s*,\s*([^;]+?)\)\s*;/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $config_content, $matches ) ) {
				$value = trim( $matches[1] );
				// Remove quotes if present.
				$value = trim( $value, '\'"' );
				// Convert boolean strings.
				if ( 'true' === strtolower( $value ) ) {
					return true;
				}
				if ( 'false' === strtolower( $value ) ) {
					return false;
				}
				return $value;
			}
		}

		return null;
	}

	/**
	 * Check if constant exists in config file
	 *
	 * @param string $config_content The config file content.
	 * @param string $constant_name The constant name.
	 * @return bool True if constant exists, false otherwise.
	 */
	public static function constant_exists( $config_content, $constant_name ) {
		$patterns = array(
			'/define\s*\(\s*[\'"]' . preg_quote( $constant_name, '/' ) . '[\'"]/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $config_content ) ) {
				return true;
			}
		}

		return false;
	}
}

