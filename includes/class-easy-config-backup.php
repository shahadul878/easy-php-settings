<?php
/**
 * Config Backup class for easy-php-settings plugin.
 *
 * Backups are stored on the filesystem under
 *   wp-content/uploads/easy-php-settings-backups/
 * with .htaccess deny + index.php silence + 0640 perms.
 *
 * Storing wp-config.php content in the database (alongside DB credentials
 * and salts) is a footgun: any SQL injection or option-read leak in any
 * plugin would exfiltrate the secrets twice over. Filesystem backups stay
 * outside the web-readable scope as long as Apache honours the .htaccess
 * (and most modern hosts do; nginx hosts that don't run htaccess should
 * already block uploads/*.php execution at the server-block level).
 *
 * @package EasyPHPSettings
 * @since   1.1.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Config_Backup {

	const BACKUP_DIR_NAME = 'easy-php-settings-backups';
	const MAX_BACKUPS     = 5;

	/**
	 * Constructor (static-only utility).
	 */
	private function __construct() {}

	/**
	 * Get (and create if needed) the backup directory.
	 *
	 * @return string|WP_Error Absolute path on success, WP_Error on failure.
	 */
	public static function get_backup_dir() {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'uploads_error', $uploads['error'] );
		}
		$base    = trailingslashit( $uploads['basedir'] ) . self::BACKUP_DIR_NAME;
		$created = wp_mkdir_p( $base );
		if ( ! $created ) {
			return new WP_Error( 'backup_dir_create_failed', __( 'Could not create backup directory.', 'easy-php-settings' ) );
		}

		// Drop in protective files (idempotent).
		$htaccess = trailingslashit( $base ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			@chmod( $htaccess, 0640 );
		}
		$index = trailingslashit( $base ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			@chmod( $index, 0640 );
		}

		return $base;
	}

	/**
	 * Create a backup of wp-config.php.
	 *
	 * @return string|WP_Error Backup file basename on success, WP_Error on failure.
	 */
	public static function create_backup() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem || ! $wp_filesystem->exists( $config_path ) ) {
			return new WP_Error( 'config_not_found', __( 'wp-config.php file not found.', 'easy-php-settings' ) );
		}
		if ( ! $wp_filesystem->is_readable( $config_path ) ) {
			return new WP_Error( 'config_not_readable', __( 'wp-config.php file is not readable.', 'easy-php-settings' ) );
		}

		$config_content = $wp_filesystem->get_contents( $config_path );
		if ( false === $config_content ) {
			return new WP_Error( 'config_read_failed', __( 'Failed to read wp-config.php file.', 'easy-php-settings' ) );
		}

		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		// Build backup file: wp-config.<unix-ts>.<random>.bak
		// Random suffix avoids same-second collisions and makes guessing harder.
		$basename = sprintf( 'wp-config.%d.%s.bak', time(), wp_generate_password( 8, false, false ) );
		$path     = trailingslashit( $dir ) . $basename;

		$header = sprintf(
			"; Easy PHP Settings backup\n; Created: %s\n; By user: %d\n\n",
			gmdate( 'c' ),
			get_current_user_id()
		);

		// Use direct file_put_contents — wp_filesystem path may not be
		// available in cron contexts where wp-config writes happen.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$bytes = file_put_contents( $path, $header . $config_content, LOCK_EX );
		if ( false === $bytes ) {
			return new WP_Error( 'backup_save_failed', __( 'Failed to save backup.', 'easy-php-settings' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		@chmod( $path, 0640 );

		self::cleanup_old_backups();

		return $basename;
	}

	/**
	 * Restore wp-config.php from a named backup file.
	 *
	 * @param string $backup_key Backup file basename (matches what was returned by create_backup).
	 * @return bool|WP_Error
	 */
	public static function restore_backup( $backup_key ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		// Path containment: backup_key must be a basename, no traversal.
		$basename = basename( (string) $backup_key );
		if ( ! preg_match( '/^wp-config\.\d+\.[a-z0-9]+\.bak$/i', $basename ) ) {
			return new WP_Error( 'invalid_backup_key', __( 'Invalid backup identifier.', 'easy-php-settings' ) );
		}
		$path = trailingslashit( $dir ) . $basename;
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'backup_not_found', __( 'Backup not found.', 'easy-php-settings' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return new WP_Error( 'backup_read_failed', __( 'Failed to read backup.', 'easy-php-settings' ) );
		}

		// Strip the backup header (everything up to first blank line).
		$content = preg_replace( '/\A(?:;.*\n)+\n/', '', $content, 1 );

		$config_path = ABSPATH . 'wp-config.php';
		if ( $wp_filesystem && $wp_filesystem->is_writable( $config_path ) ) {
			if ( ! $wp_filesystem->put_contents( $config_path, $content ) ) {
				return new WP_Error( 'restore_failed', __( 'Failed to restore backup.', 'easy-php-settings' ) );
			}
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $config_path, $content, LOCK_EX ) ) {
			return new WP_Error( 'restore_failed', __( 'Failed to restore backup.', 'easy-php-settings' ) );
		}

		return true;
	}

	/**
	 * Get the latest backup info.
	 *
	 * @return array|null  ['key' => string, 'timestamp' => string] or null.
	 */
	public static function get_latest_backup() {
		$backups = self::get_all_backups();
		if ( empty( $backups ) ) {
			return null;
		}
		return $backups[0];
	}

	/**
	 * List all backups, newest first.
	 *
	 * @return array  Each entry: ['key', 'timestamp', 'size', 'mtime'].
	 */
	public static function get_all_backups() {
		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return array();
		}

		$files = glob( trailingslashit( $dir ) . 'wp-config.*.bak' );
		if ( ! is_array( $files ) ) {
			return array();
		}

		$entries = array();
		foreach ( $files as $file ) {
			$mtime     = (int) filemtime( $file );
			$entries[] = array(
				'key'       => basename( $file ),
				'timestamp' => gmdate( 'Y-m-d H:i:s', $mtime ),
				'size'      => (int) filesize( $file ),
				'mtime'     => $mtime,
			);
		}

		usort(
			$entries,
			static function ( $a, $b ) {
				return $b['mtime'] - $a['mtime'];
			}
		);

		return $entries;
	}

	/**
	 * Keep only the most recent MAX_BACKUPS entries.
	 *
	 * @return void
	 */
	private static function cleanup_old_backups() {
		$backups = self::get_all_backups();
		if ( count( $backups ) <= self::MAX_BACKUPS ) {
			return;
		}
		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return;
		}
		$old = array_slice( $backups, self::MAX_BACKUPS );
		foreach ( $old as $entry ) {
			$path = trailingslashit( $dir ) . $entry['key'];
			if ( file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
				@unlink( $path );
			}
		}
	}

	/**
	 * Sanity-check a wp-config.php candidate before writing it.
	 *
	 * @param string $content Candidate file content.
	 * @return bool|WP_Error
	 */
	public static function validate_config_structure( $content ) {
		if ( strpos( $content, '<?php' ) === false ) {
			return new WP_Error( 'invalid_php_tag', __( 'Config file must start with <?php tag.', 'easy-php-settings' ) );
		}

		$required = array( 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST' );
		foreach ( $required as $constant ) {
			$escaped = preg_quote( $constant, '/' );
			if ( ! preg_match( '/define\s*\(\s*[\'"]' . $escaped . '[\'"]/i', $content ) ) {
				/* translators: %s: constant name */
				return new WP_Error( 'missing_constant', sprintf( __( 'Required constant %s not found in config file.', 'easy-php-settings' ), $constant ) );
			}
		}
		return true;
	}

	/**
	 * Migrate any legacy DB-stored backups to the filesystem and delete
	 * the option rows. Idempotent. Best-effort.
	 *
	 * @return void
	 */
	public static function migrate_legacy_db_backups() {
		global $wpdb;

		$prefix = 'easy_php_settings_config_backup_';
		// Multisite + single-site share the same option names today.
		$opts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $opts ) ) {
			return;
		}

		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return;
		}

		foreach ( $opts as $row ) {
			$data = maybe_unserialize( $row['option_value'] );
			if ( is_array( $data ) && isset( $data['content'] ) ) {
				$basename = sprintf( 'wp-config.%d.%s.bak', time(), wp_generate_password( 8, false, false ) );
				$path     = trailingslashit( $dir ) . $basename;
				$header   = sprintf(
					"; Migrated from DB backup\n; Original timestamp: %s\n\n",
					isset( $data['timestamp'] ) ? $data['timestamp'] : 'unknown'
				);
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $path, $header . $data['content'], LOCK_EX );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
				@chmod( $path, 0640 );
			}
			delete_option( $row['option_name'] );
			if ( is_multisite() ) {
				delete_site_option( $row['option_name'] );
			}
		}
	}
}
