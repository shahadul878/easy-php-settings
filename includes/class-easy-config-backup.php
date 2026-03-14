<?php
/**
 * Config Backup class for easy-php-settings plugin
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Config_Backup class
 *
 * Handles backup and restore of wp-config.php file.
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */
class Easy_Config_Backup {

	/**
	 * Backup option key prefix
	 *
	 * @var string
	 */
	const BACKUP_OPTION_PREFIX = 'easy_php_settings_config_backup_';

	/**
	 * Maximum number of backups to keep
	 *
	 * @var int
	 */
	const MAX_BACKUPS = 5;

	/**
	 * Constructor
	 */
	private function __construct() {}

	/**
	 * Create a backup of wp-config.php before modification
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function create_backup() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->exists( $config_path ) ) {
			return new WP_Error( 'config_not_found', __( 'wp-config.php file not found.', 'easy-php-settings' ) );
		}

		if ( ! $wp_filesystem->is_readable( $config_path ) ) {
			return new WP_Error( 'config_not_readable', __( 'wp-config.php file is not readable.', 'easy-php-settings' ) );
		}

		$config_content = $wp_filesystem->get_contents( $config_path );
		if ( false === $config_content ) {
			return new WP_Error( 'config_read_failed', __( 'Failed to read wp-config.php file.', 'easy-php-settings' ) );
		}

		// Create backup entry.
		$backup_data = array(
			'timestamp' => current_time( 'mysql' ),
			'content'   => $config_content,
			'user_id'   => get_current_user_id(),
		);

		$backup_key = self::BACKUP_OPTION_PREFIX . time();
		$saved      = is_multisite() ? update_site_option( $backup_key, $backup_data ) : update_option( $backup_key, $backup_data );

		if ( ! $saved ) {
			return new WP_Error( 'backup_save_failed', __( 'Failed to save backup.', 'easy-php-settings' ) );
		}

		// Clean up old backups.
		self::cleanup_old_backups();

		return true;
	}

	/**
	 * Restore wp-config.php from backup
	 *
	 * @param string $backup_key The backup key to restore from.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function restore_backup( $backup_key ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$backup_data = is_multisite() ? get_site_option( $backup_key ) : get_option( $backup_key );

		if ( ! $backup_data || ! isset( $backup_data['content'] ) ) {
			return new WP_Error( 'backup_not_found', __( 'Backup not found.', 'easy-php-settings' ) );
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->is_writable( $config_path ) ) {
			return new WP_Error( 'config_not_writable', __( 'wp-config.php is not writable.', 'easy-php-settings' ) );
		}

		$result = $wp_filesystem->put_contents( $config_path, $backup_data['content'] );
		if ( ! $result ) {
			return new WP_Error( 'restore_failed', __( 'Failed to restore backup.', 'easy-php-settings' ) );
		}

		return true;
	}

	/**
	 * Get the latest backup
	 *
	 * @return array|null Backup data or null if no backup exists.
	 */
	public static function get_latest_backup() {
		$backups = self::get_all_backups();
		if ( empty( $backups ) ) {
			return null;
		}

		// Sort by timestamp descending.
		usort(
			$backups,
			function( $a, $b ) {
				return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
			}
		);

		return $backups[0];
	}

	/**
	 * Get all backups
	 *
	 * @return array Array of backup data.
	 */
	public static function get_all_backups() {
		global $wpdb;
		$backups = array();

		if ( is_multisite() ) {
			$options = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->sitemeta} WHERE option_name LIKE %s",
					$wpdb->esc_like( self::BACKUP_OPTION_PREFIX ) . '%'
				),
				ARRAY_A
			);
		} else {
			$options = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( self::BACKUP_OPTION_PREFIX ) . '%'
				),
				ARRAY_A
			);
		}

		foreach ( $options as $option ) {
			$backup_data = maybe_unserialize( $option['option_value'] );
			if ( is_array( $backup_data ) && isset( $backup_data['timestamp'] ) ) {
				$backups[] = array_merge(
					$backup_data,
					array( 'key' => $option['option_name'] )
				);
			}
		}

		return $backups;
	}

	/**
	 * Clean up old backups, keeping only the most recent ones
	 *
	 * @return void
	 */
	private static function cleanup_old_backups() {
		$backups = self::get_all_backups();

		if ( count( $backups ) <= self::MAX_BACKUPS ) {
			return;
		}

		// Sort by timestamp descending.
		usort(
			$backups,
			function( $a, $b ) {
				return strtotime( $b['timestamp'] ) - strtotime( $a['timestamp'] );
			}
		);

		// Delete old backups.
		$backups_to_delete = array_slice( $backups, self::MAX_BACKUPS );
		foreach ( $backups_to_delete as $backup ) {
			if ( isset( $backup['key'] ) ) {
				is_multisite() ? delete_site_option( $backup['key'] ) : delete_option( $backup['key'] );
			}
		}
	}

	/**
	 * Validate wp-config.php structure
	 *
	 * @param string $content The config file content.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_config_structure( $content ) {
		// Check for PHP opening tag.
		if ( strpos( $content, '<?php' ) === false ) {
			return new WP_Error( 'invalid_php_tag', __( 'Config file must start with <?php tag.', 'easy-php-settings' ) );
		}

		// Check for basic WordPress constants (allow flexible spacing/quoting: define('X', define( "X", etc.).
		$required_constants = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' );
		foreach ( $required_constants as $constant ) {
			$escaped = preg_quote( $constant, '/' );
			if ( ! preg_match( '/define\s*\(\s*[\'"]' . $escaped . '[\'"]/i', $content ) ) {
				return new WP_Error( 'missing_constant', sprintf( __( 'Required constant %s not found in config file.', 'easy-php-settings' ), $constant ) );
			}
		}

		// Check for balanced parentheses and quotes (basic syntax check).
		$open_parens  = substr_count( $content, '(' );
		$close_parens = substr_count( $content, ')' );
		if ( $open_parens !== $close_parens ) {
			return new WP_Error( 'unbalanced_parens', __( 'Unbalanced parentheses detected in config file.', 'easy-php-settings' ) );
		}

		return true;
	}
}

