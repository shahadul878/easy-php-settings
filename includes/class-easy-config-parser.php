<?php
/**
 * Config Parser class for easy-php-settings plugin.
 *
 * Modifies wp-config.php using PHP's tokenizer rather than fragile regex.
 * The previous regex implementation failed on:
 *   - Multi-line define() calls
 *   - define() inside comments / heredocs / strings
 *   - defined()-guarded define() patterns
 *   - Mixed quoting / spacing inside the call
 * On a regex miss, the old code APPENDED a duplicate define(), which
 * corrupted wp-config.php on repeated saves.
 *
 * @package EasyPHPSettings
 * @since   1.1.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Config_Parser {

	/**
	 * Constructor (static-only utility).
	 */
	private function __construct() {}

	/**
	 * Update or add a constant definition in a wp-config.php buffer.
	 *
	 * @param string $config_content The current config file content.
	 * @param string $constant_name  The constant name (e.g., 'WP_DEBUG').
	 * @param mixed  $value          Value to set.
	 * @param string $type           One of 'bool', 'string', 'int'.
	 * @return string|WP_Error Modified content or WP_Error.
	 */
	public static function update_constant( $config_content, $constant_name, $value, $type = 'bool' ) {
		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $constant_name ) ) {
			return new WP_Error( 'invalid_constant_name', __( 'Invalid constant name.', 'easy-php-settings' ) );
		}

		$formatted_value = self::format_constant_value( $value, $type );
		if ( is_wp_error( $formatted_value ) ) {
			return $formatted_value;
		}

		$replacement = "define( '{$constant_name}', {$formatted_value} );";

		$range = self::find_define_range( $config_content, $constant_name );
		if ( null !== $range ) {
			$before = substr( $config_content, 0, $range[0] );
			$after  = substr( $config_content, $range[1] );
			$out    = $before . $replacement . $after;
		} else {
			$out = self::insert_define( $config_content, $replacement );
		}

		$validation = Easy_Config_Backup::validate_config_structure( $out );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $out;
	}

	/**
	 * Locate the byte range of an existing top-level define( CONST_NAME, ... );
	 * statement in $code, ignoring matches inside comments, strings, and
	 * heredocs.
	 *
	 * @param string $code     PHP source.
	 * @param string $constant Target constant name.
	 * @return array{0:int,1:int}|null Inclusive start, exclusive end byte offsets, or null if not found.
	 */
	private static function find_define_range( $code, $constant ) {
		$tokens   = token_get_all( $code );
		$count    = count( $tokens );
		$pos      = 0; // Byte offset cursor in source.
		$ranges   = array();

		// First pass: find each `define` T_STRING with the right argument.
		for ( $i = 0; $i < $count; $i++ ) {
			$tok = $tokens[ $i ];
			$len = is_array( $tok ) ? strlen( $tok[1] ) : strlen( $tok );

			if ( is_array( $tok ) && T_STRING === $tok[0] && 0 === strcasecmp( $tok[1], 'define' ) ) {
				// Skip whitespace/comments to find '('.
				$j         = $i + 1;
				$paren_pos = self::skip_trivia( $tokens, $j, $count );
				if ( $paren_pos >= $count || '(' !== self::tok_text( $tokens[ $paren_pos ] ) ) {
					$pos += $len;
					continue;
				}
				// Skip whitespace/comments inside parens to find first arg.
				$arg_pos = self::skip_trivia( $tokens, $paren_pos + 1, $count );
				if ( $arg_pos >= $count ) {
					$pos += $len;
					continue;
				}

				$first_arg = $tokens[ $arg_pos ];
				if ( ! is_array( $first_arg ) || T_CONSTANT_ENCAPSED_STRING !== $first_arg[0] ) {
					$pos += $len;
					continue;
				}

				$literal = trim( $first_arg[1], "\"'" );
				if ( 0 !== strcmp( $literal, $constant ) ) {
					$pos += $len;
					continue;
				}

				// Walk forward through tokens, keeping a paren counter, until
				// we find the matching ')' followed by ';'.
				$start_byte = self::byte_offset_for_token( $tokens, $i );
				$depth      = 0;
				$end_byte   = null;
				$walking    = $i;
				while ( $walking < $count ) {
					$t        = $tokens[ $walking ];
					$txt      = self::tok_text( $t );
					$tlen     = strlen( $txt );
					if ( '(' === $txt ) {
						$depth++;
					} elseif ( ')' === $txt ) {
						$depth--;
						if ( 0 === $depth ) {
							// Look for the trailing ';'. There may be
							// whitespace/comments between ')' and ';'.
							$close_byte = self::byte_offset_for_token( $tokens, $walking ) + $tlen;
							$k          = self::skip_trivia( $tokens, $walking + 1, $count );
							if ( $k < $count && ';' === self::tok_text( $tokens[ $k ] ) ) {
								$semi_byte = self::byte_offset_for_token( $tokens, $k ) + 1;
								$end_byte  = $semi_byte;
							} else {
								$end_byte = $close_byte;
							}
							break;
						}
					}
					$walking++;
				}

				if ( null !== $end_byte ) {
					$ranges[] = array( $start_byte, $end_byte );
				}
			}

			$pos += $len;
		}

		if ( empty( $ranges ) ) {
			return null;
		}

		// If there are multiple matches (rare; would already be malformed),
		// prefer the last one (overrides win in PHP).
		return end( $ranges );
	}

	/**
	 * Compute byte offset of a token by summing all preceding token texts.
	 *
	 * @param array $tokens token_get_all output.
	 * @param int   $index  Target token index.
	 * @return int
	 */
	private static function byte_offset_for_token( $tokens, $index ) {
		$offset = 0;
		for ( $i = 0; $i < $index; $i++ ) {
			$offset += strlen( self::tok_text( $tokens[ $i ] ) );
		}
		return $offset;
	}

	/**
	 * Get the textual form of a token (string or array).
	 *
	 * @param array|string $tok
	 * @return string
	 */
	private static function tok_text( $tok ) {
		return is_array( $tok ) ? $tok[1] : $tok;
	}

	/**
	 * Advance index past whitespace/comments.
	 *
	 * @param array $tokens
	 * @param int   $i
	 * @param int   $count
	 * @return int
	 */
	private static function skip_trivia( $tokens, $i, $count ) {
		while ( $i < $count ) {
			$tok = $tokens[ $i ];
			if ( is_array( $tok ) && in_array( $tok[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$i++;
				continue;
			}
			break;
		}
		return $i;
	}

	/**
	 * Insert a new define() at a sensible position in wp-config.php.
	 *
	 * @param string $config_content
	 * @param string $statement Full define() including trailing ';'.
	 * @return string
	 */
	private static function insert_define( $config_content, $statement ) {
		$marker = "/* That's all, stop editing!";
		if ( false !== strpos( $config_content, $marker ) ) {
			return str_replace( $marker, $statement . "\n\n" . $marker, $config_content );
		}
		// Fall back to before any closing PHP tag.
		if ( preg_match( '/\?>\s*$/', $config_content ) ) {
			return preg_replace( '/\?>\s*$/', $statement . "\n?>\n", $config_content );
		}
		return rtrim( $config_content ) . "\n\n" . $statement . "\n";
	}

	/**
	 * Format a constant value for inlining into PHP source.
	 *
	 * @param mixed  $value
	 * @param string $type
	 * @return string|WP_Error
	 */
	private static function format_constant_value( $value, $type ) {
		switch ( $type ) {
			case 'bool':
				return $value ? 'true' : 'false';

			case 'int':
				return (string) intval( $value );

			case 'string':
			default:
				$trimmed = trim( (string) $value );
				if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $trimmed ) ) {
					// Reject anything that isn't a size literal. Stops
					// arbitrary values being persisted into wp-config.php.
					return new WP_Error( 'invalid_value', __( 'Constant value has an unsupported format.', 'easy-php-settings' ) );
				}
				$sanitized = preg_replace( '/[^0-9KMGT]/i', '', $trimmed );
				$escaped   = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $sanitized );
				return "'{$escaped}'";
		}
	}

	/**
	 * Read the current value of a constant by static parsing.
	 *
	 * @param string $config_content
	 * @param string $constant_name
	 * @return mixed|null
	 */
	public static function get_constant_value( $config_content, $constant_name ) {
		$range = self::find_define_range( $config_content, $constant_name );
		if ( null === $range ) {
			return null;
		}
		$snippet = substr( $config_content, $range[0], $range[1] - $range[0] );
		if ( ! preg_match( '/define\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*([^)]+?)\)\s*;/is', $snippet, $m ) ) {
			return null;
		}
		$raw = trim( $m[1] );
		if ( 0 === strcasecmp( $raw, 'true' ) ) {
			return true;
		}
		if ( 0 === strcasecmp( $raw, 'false' ) ) {
			return false;
		}
		if ( preg_match( '/^[\'"](.*)[\'"]$/s', $raw, $sm ) ) {
			return stripslashes( $sm[1] );
		}
		if ( preg_match( '/^-?\d+$/', $raw ) ) {
			return (int) $raw;
		}
		return $raw;
	}

	/**
	 * @param string $config_content
	 * @param string $constant_name
	 * @return bool
	 */
	public static function constant_exists( $config_content, $constant_name ) {
		return null !== self::find_define_range( $config_content, $constant_name );
	}
}
