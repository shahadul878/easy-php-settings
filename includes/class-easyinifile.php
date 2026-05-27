<?php
/**
 * INIFile class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * EasyIniFile class
 *
 * Handles INI file operations.
 *
 * @package EasyPHPSettings
 * @since 1.0.0
 */
class EasyIniFile {

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Get directory path
	 *
	 * @return string
	 */
	public static function get_dir_path() {
		return ABSPATH;
	}

	/**
	 * Get INI file names
	 *
	 * @return array
	 */
	public static function get_ini_file_names() {
		return array(
			self::get_dir_path() . '.user.ini',
			self::get_dir_path() . 'php.ini',
		);
	}

	/**
	 * Write content to INI files
	 *
	 * @param string $content The content to write.
	 * @return array|WP_Error Array of written filenames or WP_Error on failure.
	 */
	public static function write( $content ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'filesystem_unavailable', __( 'WordPress filesystem could not be initialised.', 'easy-php-settings' ) );
		}

		// Validate content size (max 1MB).
		if ( strlen( $content ) > 1048576 ) {
			return new WP_Error( 'content_too_large', __( 'Content exceeds maximum allowed size (1MB).', 'easy-php-settings' ) );
		}

		$files_written = array();
		$dir_path      = self::get_dir_path();

		// File paths here are constants (ABSPATH . '.user.ini' / 'php.ini'),
		// not user-controlled — no path-traversal vector to defend against.
		// We just verify the directory is writable.
		if ( ! $wp_filesystem->is_writable( $dir_path ) ) {
			return new WP_Error( 'directory_not_writable', __( 'Directory is not writable.', 'easy-php-settings' ) );
		}

		foreach ( self::get_ini_file_names() as $filepath ) {
			try {
				// Atomic-ish write: write to temp file, then move.
				$temp_filepath = $filepath . '.tmp';
				$write_result  = $wp_filesystem->put_contents( $temp_filepath, $content );

				if ( $write_result ) {
					if ( $wp_filesystem->exists( $filepath ) ) {
						$wp_filesystem->delete( $filepath );
					}
					$wp_filesystem->move( $temp_filepath, $filepath );
					$wp_filesystem->chmod( $filepath, 0644 );
					$files_written[] = basename( $filepath );
				} elseif ( $wp_filesystem->exists( $temp_filepath ) ) {
					$wp_filesystem->delete( $temp_filepath );
				}
			} catch ( Exception $e ) {
				Easy_Error_Handler::handle_exception( $e, "write_ini_file: {$filepath}" );
				continue;
			}
		}

		return $files_written;
	}

	/**
	 * Remove INI files
	 *
	 * @return array|WP_Error Array of deleted filenames or WP_Error on failure.
	 */
	public static function remove_files() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'filesystem_unavailable', __( 'WordPress filesystem could not be initialised.', 'easy-php-settings' ) );
		}

		$files_deleted = array();

		foreach ( self::get_ini_file_names() as $filepath ) {
			try {
				// Defensive: only ever touch files whose basename matches
				// one of the two we manage. The list itself is hardcoded,
				// but this guards future refactors from accidentally
				// deleting unrelated files.
				$basename = basename( $filepath );
				if ( ! in_array( $basename, array( '.user.ini', 'php.ini' ), true ) ) {
					continue;
				}
				if ( $wp_filesystem->exists( $filepath ) && $wp_filesystem->delete( $filepath ) ) {
					$files_deleted[] = $basename;
				}
			} catch ( Exception $e ) {
				Easy_Error_Handler::handle_exception( $e, "remove_ini_file: {$filepath}" );
				continue;
			}
		}

		return $files_deleted;
	}

	/**
	 * Check if directory is writable
	 *
	 * @return bool
	 */
	public static function is_writable() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem->is_writable( self::get_dir_path() );
	}
}
