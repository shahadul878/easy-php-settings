<?php
/**
 * WP-CLI command: wp easy-php
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manage Easy PHP Settings from the command line.
 *
 * Saves go through the same validation, history, backup and file-writing code
 * as the admin screen.
 *
 * ## EXAMPLES
 *
 *     # Show saved and recommended values.
 *     $ wp easy-php list
 *
 *     # Raise the PHP memory limit.
 *     $ wp easy-php set memory_limit 512M
 *
 *     # Turn on debug logging without showing errors to visitors.
 *     $ wp easy-php debug on
 */
class Easy_PHP_Settings_CLI {

	const PHP_OPTION       = 'easy_php_settings_settings';
	const WP_MEMORY_OPTION = 'easy_php_settings_wp_memory_settings';

	/**
	 * Plugin orchestrator.
	 *
	 * @var Easy_PHP_Settings
	 */
	private $plugin;

	/**
	 * Callable that removes the plugin's INI directives.
	 *
	 * @var callable
	 */
	private $remove_ini;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin     Plugin orchestrator.
	 * @param callable|null     $remove_ini Returns cleaned file names or WP_Error. Defaults to EasyIniFile::remove_files().
	 */
	public function __construct( $plugin, $remove_ini = null ) {
		$this->plugin     = $plugin;
		$this->remove_ini = $remove_ini ? $remove_ini : array( 'EasyIniFile', 'remove_files' );
	}

	/**
	 * Lists every setting with its saved and recommended value.
	 *
	 * PHP values are the ones saved by this plugin. The live values for web
	 * requests can differ: the command line uses its own php.ini and does not
	 * read .user.ini. Check Tools → Easy PHP Settings → Status for those.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp easy-php list
	 *     $ wp easy-php list --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$items  = array_merge(
			$this->rows( $this->plugin->get_settings_keys(), self::PHP_OPTION, $this->plugin->get_recommended_values(), '.user.ini / php.ini' ),
			$this->rows( $this->plugin->get_wp_memory_settings_keys(), self::WP_MEMORY_OPTION, $this->plugin->get_wp_memory_recommended_values(), 'wp-config.php' )
		);
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		WP_CLI\Utils\format_items( $format, $items, array( 'setting', 'saved', 'recommended', 'file' ) );
	}

	/**
	 * Prints the saved value of one setting.
	 *
	 * ## OPTIONS
	 *
	 * <setting>
	 * : One of memory_limit, upload_max_filesize, post_max_size, max_execution_time, max_input_vars, wp_memory_limit, wp_max_memory_limit.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp easy-php get memory_limit
	 *     256M
	 *
	 * @param array $args Positional arguments.
	 */
	public function get( $args ) {
		$key    = $args[0];
		$option = $this->option_for( $key );
		$saved  = $this->saved_values( $option );

		if ( ! isset( $saved[ $key ] ) || '' === (string) $saved[ $key ] ) {
			/* translators: %s: setting name */
			WP_CLI::error( sprintf( __( 'No saved value for %s.', 'easy-php-settings' ), $key ) );
		}

		WP_CLI::log( (string) $saved[ $key ] );
	}

	/**
	 * Saves one setting and applies it.
	 *
	 * PHP settings are written to .user.ini and php.ini; WordPress memory
	 * settings are written to wp-config.php (backed up first). The change is
	 * recorded in the settings history.
	 *
	 * ## OPTIONS
	 *
	 * <setting>
	 * : One of memory_limit, upload_max_filesize, post_max_size, max_execution_time, max_input_vars, wp_memory_limit, wp_max_memory_limit.
	 *
	 * <value>
	 * : A size such as 512M (memory and size settings) or a whole number (max_execution_time, max_input_vars).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp easy-php set memory_limit 512M
	 *     $ wp easy-php set max_input_vars 5000
	 *     $ wp easy-php set wp_memory_limit 256M
	 *
	 * @param array $args Positional arguments.
	 */
	public function set( $args ) {
		$key    = $args[0];
		$value  = trim( (string) $args[1] );
		$option = $this->option_for( $key );

		if ( '' === $value ) {
			WP_CLI::error( __( 'Value cannot be empty.', 'easy-php-settings' ) );
		}

		$validation = self::PHP_OPTION === $option
			? Easy_Settings_Validator::validate_setting( $key, $value )
			: Easy_Settings_Validator::validate_wp_memory_setting( $key, $value );
		if ( is_wp_error( $validation ) ) {
			WP_CLI::error( $validation->get_error_message() );
		}

		$module = $this->module( 'general_settings' );
		$input  = array_merge( $this->saved_values( $option ), array( $key => $value ) );

		$this->collect_messages(
			function () use ( $module, $option, $input ) {
				return self::PHP_OPTION === $option
					? $module->save_php_settings( $input )
					: $module->save_wp_memory_settings( $input );
			}
		);

		/* translators: 1: setting name, 2: new value */
		WP_CLI::success( sprintf( __( '%1$s set to %2$s.', 'easy-php-settings' ), $key, $value ) );
	}

	/**
	 * Turns WordPress debugging on or off in wp-config.php.
	 *
	 * "on" sets WP_DEBUG and WP_DEBUG_LOG (errors go to wp-content/debug.log)
	 * and keeps WP_DEBUG_DISPLAY and SCRIPT_DEBUG off unless their flags are
	 * given. "off" turns all four off. wp-config.php is backed up first.
	 *
	 * ## OPTIONS
	 *
	 * <state>
	 * : Turn debugging on or off.
	 * ---
	 * options:
	 *   - on
	 *   - off
	 * ---
	 *
	 * [--display]
	 * : Also show errors on screen (WP_DEBUG_DISPLAY). Avoid on live sites.
	 *
	 * [--script-debug]
	 * : Also load unminified core scripts and styles (SCRIPT_DEBUG).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp easy-php debug on
	 *     $ wp easy-php debug on --display --script-debug
	 *     $ wp easy-php debug off
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function debug( $args, $assoc_args ) {
		$state = isset( $args[0] ) ? $args[0] : '';
		if ( ! in_array( $state, array( 'on', 'off' ), true ) ) {
			WP_CLI::error( __( 'Use "on" or "off".', 'easy-php-settings' ) );
		}

		$input = array();
		if ( 'on' === $state ) {
			$input = array(
				'wp_debug'     => '1',
				'wp_debug_log' => '1',
			);
			if ( ! empty( $assoc_args['display'] ) ) {
				$input['wp_debug_display'] = '1';
			}
			if ( ! empty( $assoc_args['script-debug'] ) ) {
				$input['script_debug'] = '1';
			}
		}

		$module = $this->module( 'tools' );
		$this->collect_messages(
			function () use ( $module, $input ) {
				return $module->save_debug_settings( $input );
			}
		);

		if ( empty( $input ) ) {
			WP_CLI::success( __( 'Debugging disabled.', 'easy-php-settings' ) );
			return;
		}

		$constants = array_map( 'strtoupper', array_keys( $input ) );
		/* translators: %s: comma-separated list of constants */
		WP_CLI::success( sprintf( __( 'Debugging enabled: %s.', 'easy-php-settings' ), implode( ', ', $constants ) ) );
	}

	/**
	 * Removes this plugin's directives from .user.ini and php.ini.
	 *
	 * Lines added by other plugins are kept. A file is deleted only if
	 * nothing else is left in it. Saved settings are not changed.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp easy-php remove-ini
	 *
	 * @subcommand remove-ini
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function remove_ini( $args, $assoc_args ) {
		$result = call_user_func( $this->remove_ini );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		if ( empty( $result ) ) {
			WP_CLI::success( __( 'No Easy PHP Settings directives found; nothing to remove.', 'easy-php-settings' ) );
			return;
		}

		/* translators: %s: comma-separated list of file names */
		WP_CLI::success( sprintf( __( 'Removed Easy PHP Settings directives from: %s.', 'easy-php-settings' ), implode( ', ', $result ) ) );
	}

	/* ─── Helpers ─── */

	/**
	 * Build list rows for a group of settings.
	 *
	 * @param string[] $keys        Setting keys.
	 * @param string   $option      Option the values are stored in.
	 * @param array    $recommended Recommended values.
	 * @param string   $file        Where the setting is written.
	 * @return array[]
	 */
	private function rows( array $keys, $option, array $recommended, $file ) {
		$saved = $this->saved_values( $option );
		$rows  = array();
		foreach ( $keys as $key ) {
			$rows[] = array(
				'setting'     => $key,
				'saved'       => isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '',
				'recommended' => isset( $recommended[ $key ] ) ? (string) $recommended[ $key ] : '',
				'file'        => $file,
			);
		}
		return $rows;
	}

	/**
	 * Return the option a setting belongs to, or halt for unknown settings.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function option_for( $key ) {
		if ( in_array( $key, $this->plugin->get_settings_keys(), true ) ) {
			return self::PHP_OPTION;
		}
		if ( in_array( $key, $this->plugin->get_wp_memory_settings_keys(), true ) ) {
			return self::WP_MEMORY_OPTION;
		}

		$known = array_merge( $this->plugin->get_settings_keys(), $this->plugin->get_wp_memory_settings_keys() );
		/* translators: 1: setting name, 2: comma-separated list of supported settings */
		WP_CLI::error( sprintf( __( 'Unknown setting "%1$s". Supported settings: %2$s.', 'easy-php-settings' ), $key, implode( ', ', $known ) ) );
	}

	/**
	 * Read a stored settings array.
	 *
	 * @param string $option Option name.
	 * @return array
	 */
	private function saved_values( $option ) {
		$saved = $this->plugin->get_option( $option, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Return a loaded module, or halt.
	 *
	 * @param string $module_id Module ID.
	 * @return object
	 */
	private function module( $module_id ) {
		$module = $this->plugin->get_module_manager()->get_module( $module_id );
		if ( ! $module ) {
			/* translators: %s: module ID */
			WP_CLI::error( sprintf( __( 'The "%s" module is not loaded.', 'easy-php-settings' ), $module_id ) );
		}
		return $module;
	}

	/**
	 * Run a save and print the messages it reported through add_settings_error().
	 *
	 * Warnings and notices are printed; any error halts with a non-zero exit.
	 *
	 * @param callable $save Save operation.
	 * @return void
	 */
	private function collect_messages( callable $save ) {
		if ( ! function_exists( 'get_settings_errors' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}
		$GLOBALS['wp_settings_errors'] = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- start from a clean message list for this command.

		$save();

		$errors = array();
		foreach ( get_settings_errors() as $message ) {
			if ( 'error' === $message['type'] ) {
				$errors[] = $message['message'];
			} elseif ( 'warning' === $message['type'] ) {
				WP_CLI::warning( $message['message'] );
			} else {
				WP_CLI::log( $message['message'] );
			}
		}

		if ( ! empty( $errors ) ) {
			WP_CLI::error( implode( ' ', $errors ) );
		}
	}
}
