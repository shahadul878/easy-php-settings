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
	 * @return array
	 */
	public static function write( $content ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$files_written = array();
		foreach ( self::get_ini_file_names() as $filepath ) {
			if ( $wp_filesystem->put_contents( $filepath, $content ) ) {
				$files_written[] = basename( $filepath );
			}
		}
		return $files_written;
	}

	/**
	 * Remove INI files
	 *
	 * @return array
	 */
	public static function remove_files() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$files_deleted = array();
		foreach ( self::get_ini_file_names() as $filepath ) {
			if ( $wp_filesystem->exists( $filepath ) ) {
				if ( $wp_filesystem->delete( $filepath ) ) {
					$files_deleted[] = basename( $filepath );
				}
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
