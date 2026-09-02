<?php
/**
 * Config Backup class for easy-php-settings plugin.
 *
 * Backups are stored as non-executable ZIP archives under
 *   wp-content/uploads/easy-php-settings-backups/
 * with .htaccess deny + index.php silence + 0640 perms.
 *
 * wp-config.php source is never written as a plaintext/executable PHP file.
 * The archive contains wp-config.txt (PHP source stored as a non-executable
 * text entry). Legacy .bak files, if present, can still be restored.
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
	const INNER_FILE      = 'wp-config.txt';

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
	 * Create a ZIP backup of wp-config.php.
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

		$basename = sprintf( 'wp-config.%d.%s.zip', time(), wp_generate_password( 8, false, false ) );
		$path     = trailingslashit( $dir ) . $basename;

		$written = self::write_zip_archive( $path, $config_content );
		if ( is_wp_error( $written ) ) {
			return $written;
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

		$basename = basename( (string) $backup_key );
		if ( ! preg_match( '/^wp-config\.\d+\.[a-z0-9]+\.(zip|bak)$/i', $basename ) ) {
			return new WP_Error( 'invalid_backup_key', __( 'Invalid backup identifier.', 'easy-php-settings' ) );
		}
		$path = trailingslashit( $dir ) . $basename;
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'backup_not_found', __( 'Backup not found.', 'easy-php-settings' ) );
		}

		$content = self::read_backup_contents( $path, $basename );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( $wp_filesystem && $wp_filesystem->is_writable( $config_path ) ) {
			if ( ! $wp_filesystem->put_contents( $config_path, $content ) ) {
				return new WP_Error( 'restore_failed', __( 'Failed to restore backup.', 'easy-php-settings' ) );
			}
			return true;
		}

		// phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- restoring wp-config.php is the purpose of this method.
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

		$files = array_merge(
			(array) glob( trailingslashit( $dir ) . 'wp-config.*.zip' ),
			(array) glob( trailingslashit( $dir ) . 'wp-config.*.bak' )
		);

		$entries = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || ! is_file( $file ) ) {
				continue;
			}
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
				wp_delete_file( $path );
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
	 * Migrate any legacy DB-stored backups to ZIP archives and delete
	 * the option rows. Idempotent. Best-effort.
	 *
	 * @return void
	 */
	public static function migrate_legacy_db_backups() {
		global $wpdb;

		$prefix = 'easy_php_settings_config_backup_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration of legacy backup options.
		$opts   = $wpdb->get_results(
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
				$basename = sprintf( 'wp-config.%d.%s.zip', time(), wp_generate_password( 8, false, false ) );
				$path     = trailingslashit( $dir ) . $basename;
				$written  = self::write_zip_archive( $path, (string) $data['content'] );
				if ( ! is_wp_error( $written ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
					@chmod( $path, 0640 );
				}
			}
			delete_option( $row['option_name'] );
			if ( is_multisite() ) {
				delete_site_option( $row['option_name'] );
			}
		}
	}

	/**
	 * Write a ZIP archive containing wp-config source as a .txt entry.
	 *
	 * @param string $path    Absolute path of the .zip file to create.
	 * @param string $content wp-config.php source.
	 * @return true|WP_Error
	 */
	private static function write_zip_archive( $path, $content ) {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip    = new ZipArchive();
			$opened = $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
			if ( true === $opened ) {
				$zip->addFromString( self::INNER_FILE, $content );
				$zip->close();
				if ( file_exists( $path ) && filesize( $path ) > 0 ) {
					return true;
				}
			}
		}

		if ( ! class_exists( 'PclZip' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}

		if ( ! class_exists( 'PclZip' ) ) {
			return new WP_Error( 'backup_zip_unavailable', __( 'Could not create a ZIP backup. Enable the PHP zip extension or ensure WordPress PclZip is available.', 'easy-php-settings' ) );
		}

		$archive = new PclZip( $path );
		$result  = $archive->create(
			array(
				array(
					PCLZIP_ATT_FILE_NAME    => self::INNER_FILE,
					PCLZIP_ATT_FILE_CONTENT => $content,
				),
			)
		);

		if ( 0 === $result ) {
			return new WP_Error( 'backup_save_failed', __( 'Failed to save backup.', 'easy-php-settings' ) );
		}

		return true;
	}

	/**
	 * Convert leftover plaintext .bak backups into ZIP archives.
	 *
	 * @return void
	 */
	public static function convert_legacy_plaintext_backups() {
		$dir = self::get_backup_dir();
		if ( is_wp_error( $dir ) ) {
			return;
		}

		$files = glob( trailingslashit( $dir ) . 'wp-config.*.bak' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$basename = basename( $file );
			$content  = self::read_backup_contents( $file, $basename );
			if ( is_wp_error( $content ) || '' === $content ) {
				continue;
			}
			$zip_name = preg_replace( '/\.bak$/i', '.zip', $basename );
			$zip_path = trailingslashit( $dir ) . $zip_name;
			$written  = self::write_zip_archive( $zip_path, $content );
			if ( is_wp_error( $written ) ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			@chmod( $zip_path, 0640 );
			wp_delete_file( $file );
		}
	}

	/**
	 * Read wp-config source from a ZIP archive or a legacy plaintext .bak file.
	 *
	 * @param string $path     Absolute path.
	 * @param string $basename File basename (used to detect format).
	 * @return string|WP_Error
	 */
	private static function read_backup_contents( $path, $basename ) {
		if ( preg_match( '/\.bak$/i', $basename ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
			$content = file_get_contents( $path );
			if ( false === $content ) {
				return new WP_Error( 'backup_read_failed', __( 'Failed to read backup.', 'easy-php-settings' ) );
			}
			return preg_replace( '/\A(?:;.*\n)+\n/', '', $content, 1 );
		}

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $path ) ) {
				$content = $zip->getFromName( self::INNER_FILE );
				if ( false === $content ) {
					$content = $zip->getFromName( 'wp-config.php' );
				}
				$zip->close();
				if ( false !== $content && '' !== $content ) {
					return $content;
				}
			}
		}

		if ( ! class_exists( 'PclZip' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}

		if ( class_exists( 'PclZip' ) ) {
			$archive = new PclZip( $path );
			$list    = $archive->extract( PCLZIP_OPT_EXTRACT_AS_STRING );
			if ( is_array( $list ) ) {
				foreach ( $list as $entry ) {
					$name = isset( $entry['filename'] ) ? $entry['filename'] : '';
					if ( in_array( $name, array( self::INNER_FILE, 'wp-config.php' ), true ) && ! empty( $entry['content'] ) ) {
						return $entry['content'];
					}
				}
				if ( isset( $list[0]['content'] ) && '' !== $list[0]['content'] ) {
					return $list[0]['content'];
				}
			}
		}

		return new WP_Error( 'backup_read_failed', __( 'Failed to read backup.', 'easy-php-settings' ) );
	}
}
