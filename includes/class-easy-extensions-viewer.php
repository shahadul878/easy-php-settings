<?php
/**
 * Extensions Viewer class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.4
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Extensions_Viewer class
 *
 * Handles PHP extensions display and categorization.
 *
 * @package EasyPHPSettings
 * @since 1.0.4
 */
class Easy_Extensions_Viewer {

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Get all loaded extensions
	 *
	 * @return array
	 */
	public static function get_loaded_extensions() {
		return get_loaded_extensions();
	}

	/**
	 * Get extensions categorized by type
	 *
	 * @return array
	 */
	public static function get_categorized_extensions() {
		$loaded = self::get_loaded_extensions();

		$categories = array(
			'Core'        => array( 'Core', 'standard', 'SPL', 'Reflection', 'pcre', 'date', 'libxml' ),
			'Database'    => array( 'mysqli', 'mysqlnd', 'pdo', 'pdo_mysql', 'pdo_sqlite', 'sqlite3' ),
			'Image'       => array( 'gd', 'imagick', 'exif', 'getimagesize' ),
			'Performance' => array( 'opcache', 'apcu', 'apc', 'memcached', 'redis' ),
			'XML/JSON'    => array( 'xml', 'xmlreader', 'xmlwriter', 'SimpleXML', 'json', 'soap' ),
			'Crypto'      => array( 'openssl', 'hash', 'mcrypt', 'sodium' ),
			'Compression' => array( 'zlib', 'bz2', 'zip' ),
			'Text'        => array( 'mbstring', 'iconv', 'intl' ),
			'Network'     => array( 'curl', 'ftp', 'sockets' ),
			'Other'       => array(),
		);

		$categorized = array();
		foreach ( $categories as $category => $extensions ) {
			$categorized[ $category ] = array();
		}

		// Categorize loaded extensions.
		foreach ( $loaded as $extension ) {
			$placed = false;
			foreach ( $categories as $category => $extensions ) {
				if ( 'Other' !== $category && in_array( $extension, $extensions, true ) ) {
					$categorized[ $category ][] = $extension;
					$placed                     = true;
					break;
				}
			}
			if ( ! $placed ) {
				$categorized['Other'][] = $extension;
			}
		}

		// Remove empty categories except "Other".
		foreach ( $categorized as $category => $extensions ) {
			if ( empty( $extensions ) && 'Other' !== $category ) {
				unset( $categorized[ $category ] );
			}
		}

		return $categorized;
	}

	/**
	 * Get extension version if available
	 *
	 * @param string $extension The extension name.
	 * @return string
	 */
	public static function get_extension_version( $extension ) {
		$version = phpversion( $extension );
		return $version ? $version : __( 'N/A', 'easy-php-settings' );
	}

	/**
	 * Check if extension is loaded
	 *
	 * @param string $extension The extension name.
	 * @return bool
	 */
	public static function is_loaded( $extension ) {
		return extension_loaded( $extension );
	}

	/**
	 * Get critical missing extensions for WordPress
	 *
	 * @return array
	 */
	public static function get_critical_missing_extensions() {
		$critical = array(
			'mysqli'   => __( 'Required for WordPress database connections', 'easy-php-settings' ),
			'mbstring' => __( 'Required for proper text handling', 'easy-php-settings' ),
			'curl'     => __( 'Required for HTTP requests', 'easy-php-settings' ),
			'zip'      => __( 'Required for plugin/theme installation', 'easy-php-settings' ),
			'gd'       => __( 'Required for image processing', 'easy-php-settings' ),
		);

		$missing = array();
		foreach ( $critical as $ext => $description ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing[ $ext ] = $description;
			}
		}

		return $missing;
	}

	/**
	 * Get recommended extensions for WordPress
	 *
	 * @return array
	 */
	public static function get_recommended_extensions() {
		return array(
			'imagick' => __( 'Better image processing capabilities', 'easy-php-settings' ),
			'intl'    => __( 'Internationalization support', 'easy-php-settings' ),
			'opcache' => __( 'Significant performance improvement', 'easy-php-settings' ),
			'xml'     => __( 'XML processing for various features', 'easy-php-settings' ),
		);
	}
}
