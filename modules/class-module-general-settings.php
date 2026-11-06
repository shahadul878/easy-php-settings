<?php
/**
 * General Settings Module
 *
 * Owns settings registration, sanitization, the main settings tab,
 * config-file generation, INI-file management, WP-memory constants,
 * export / import, and reset actions.
 *
 * @package EasyPHPSettings
 * @since   1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_General_Settings extends Easy_Module_Base {

	protected $module_id          = 'general_settings';
	protected $module_name        = 'General Settings';
	protected $module_description = 'Core PHP and WordPress memory settings configuration';

	/* ─── Hooks ───────────────────────────────── */

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function handle_admin_actions() {
		$this->handle_ini_file_actions();
	}

	/* ─── Settings Registration ───────────────── */

	public function register_settings() {
		register_setting( 'easy_php_settings', 'easy_php_settings_settings', array( $this, 'sanitize_callback' ) );
		register_setting( 'easy_php_settings', 'easy_php_settings_wp_memory_settings', array( $this, 'sanitize_wp_memory_callback' ) );

		$this->apply_settings();
	}

	/* ─── Sanitize Callbacks ──────────────────── */

	public function sanitize_callback( $input ) {
		// Defense-in-depth capability check: WP's options.php already enforces
		// it for the registered settings group, but the sanitize callback can
		// fire from other code paths (filters, WP-CLI option write, REST).
		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			return $this->plugin->get_option( 'easy_php_settings_settings', array() );
		}

		return $this->process_php_settings( is_array( $input ) ? $input : array() );
	}

	/**
	 * Save PHP settings outside the Settings API (used by WP-CLI).
	 *
	 * The caller is responsible for authorisation. Messages are reported
	 * through add_settings_error().
	 *
	 * @param array $input Full settings array to save.
	 * @return array The saved settings.
	 */
	public function save_php_settings( array $input ) {
		$new_input = $this->process_php_settings( $input );
		// Avoid a second pass through the sanitize callback if it is registered.
		remove_filter( 'sanitize_option_easy_php_settings_settings', array( $this, 'sanitize_callback' ) );
		$this->plugin->update_option( 'easy_php_settings_settings', $new_input );
		return $new_input;
	}

	/**
	 * Sanitize and validate PHP settings, record history, and write INI files.
	 *
	 * @param array $input Raw settings.
	 * @return array Sanitized settings.
	 */
	private function process_php_settings( array $input ) {
		$old_input = $this->plugin->get_option( 'easy_php_settings_settings', array() );
		$new_input = array();

		foreach ( $this->plugin->get_settings_keys() as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized  = Easy_Settings_Validator::sanitize_setting( $key, $input[ $key ] );
				$validation = Easy_Settings_Validator::validate_setting( $key, $sanitized );
				if ( is_wp_error( $validation ) ) {
					Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'validation_error_' . $key, $validation->get_error_message(), 'error' );
					continue;
				}
				$new_input[ $key ] = $sanitized;
			}
		}

		if ( isset( $input['custom_php_ini'] ) ) {
			list( $clean_ini, $ini_errors ) = Easy_Settings_Validator::sanitize_custom_php_ini( $input['custom_php_ini'] );
			$new_input['custom_php_ini']    = $clean_ini;
			foreach ( $ini_errors as $err ) {
				Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'custom_php_ini_blocked', $err, 'warning' );
			}
		}

		foreach ( Easy_Settings_Validator::validate_settings_relationships( $new_input ) as $error ) {
			add_settings_error( 'easy_php_settings_settings', 'relationship_warning', $error, 'warning' );
		}

		$this->validate_settings( $new_input );

		Easy_Settings_History::add_entry( $old_input, $new_input, 'php_settings' );
		Easy_Settings_Cache::invalidate( 'settings' );

		if ( ! empty( $new_input ) ) {
			try {
				$this->generate_config_files( $new_input );
			} catch ( Exception $e ) {
				Easy_Error_Handler::handle_exception( $e, 'generate_config_files' );
				Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'config_generation_error', __( 'Failed to generate configuration files. Please check error log.', 'easy-php-settings' ), 'error' );
			}
		}

		return $new_input;
	}

	public function sanitize_wp_memory_callback( $input ) {
		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			return $this->plugin->get_option( 'easy_php_settings_wp_memory_settings', array() );
		}

		return $this->process_wp_memory_settings( is_array( $input ) ? $input : array() );
	}

	/**
	 * Save WordPress memory settings outside the Settings API (used by WP-CLI).
	 *
	 * The caller is responsible for authorisation. Messages are reported
	 * through add_settings_error().
	 *
	 * @param array $input Full WordPress memory settings array to save.
	 * @return array The saved settings.
	 */
	public function save_wp_memory_settings( array $input ) {
		$new_input = $this->process_wp_memory_settings( $input );
		remove_filter( 'sanitize_option_easy_php_settings_wp_memory_settings', array( $this, 'sanitize_wp_memory_callback' ) );
		$this->plugin->update_option( 'easy_php_settings_wp_memory_settings', $new_input );
		return $new_input;
	}

	/**
	 * Sanitize and validate WordPress memory settings and update wp-config.php.
	 *
	 * @param array $input Raw settings.
	 * @return array Sanitized settings.
	 */
	private function process_wp_memory_settings( array $input ) {
		$old_input = $this->plugin->get_option( 'easy_php_settings_wp_memory_settings', array() );
		$new_input = array();

		foreach ( $this->plugin->get_wp_memory_settings_keys() as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized  = Easy_Settings_Validator::sanitize_setting( $key, $input[ $key ] );
				$validation = Easy_Settings_Validator::validate_wp_memory_setting( $key, $sanitized );
				if ( is_wp_error( $validation ) ) {
					Easy_Error_Handler::add_settings_error( 'easy_php_settings_wp_memory_settings', 'validation_error_' . $key, $validation->get_error_message(), 'error' );
					continue;
				}
				$new_input[ $key ] = $sanitized;
			}
		}

		Easy_Settings_History::add_entry( $old_input, $new_input, 'wp_memory' );

		if ( ! empty( $new_input ) ) {
			try {
				$this->update_wp_memory_constants( $new_input );
			} catch ( Exception $e ) {
				Easy_Error_Handler::handle_exception( $e, 'update_wp_memory_constants' );
				Easy_Error_Handler::add_settings_error( 'easy_php_settings_wp_memory_settings', 'config_update_error', __( 'Failed to update wp-config.php. Please check error log.', 'easy-php-settings' ), 'error' );
			}
		}

		return $new_input;
	}

	/* ─── Validation ──────────────────────────── */

	private function validate_settings( $settings ) {
		$warnings = array();

		if ( isset( $settings['post_max_size'], $settings['upload_max_filesize'] ) ) {
			if ( $this->plugin->convert_to_bytes( $settings['post_max_size'] ) < $this->plugin->convert_to_bytes( $settings['upload_max_filesize'] ) ) {
				$warnings[] = __( 'post_max_size should be larger than upload_max_filesize.', 'easy-php-settings' );
			}
		}
		if ( isset( $settings['memory_limit'], $settings['post_max_size'] ) ) {
			if ( $this->plugin->convert_to_bytes( $settings['memory_limit'] ) < $this->plugin->convert_to_bytes( $settings['post_max_size'] ) ) {
				$warnings[] = __( 'memory_limit should be larger than post_max_size.', 'easy-php-settings' );
			}
		}
		if ( isset( $settings['max_execution_time'] ) && intval( $settings['max_execution_time'] ) < 30 ) {
			$warnings[] = __( 'max_execution_time is very low (less than 30 seconds) and may cause issues.', 'easy-php-settings' );
		}
		if ( isset( $settings['memory_limit'] ) && $this->plugin->convert_to_bytes( $settings['memory_limit'] ) > 536870912 ) {
			$warnings[] = __( 'memory_limit is very high (over 512M). This may be excessive unless you have a specific need.', 'easy-php-settings' );
		}

		foreach ( $warnings as $w ) {
			add_settings_error( 'easy_php_settings_settings', 'validation_warning', $w, 'warning' );
		}
	}

	/* ─── Config file generation ──────────────── */

	private function generate_config_files( $settings ) {
		$content  = "; PHP Settings generated by Easy PHP Settings plugin\n";
		$content .= "; Created By H M Shahadul Islam\n";
		$content .= '; Generated on: ' . current_time( 'mysql' ) . "\n\n";

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, $this->plugin->get_settings_keys(), true ) && ! empty( $value ) ) {
				$content .= "$key = $value\n";
			}
		}
		if ( ! empty( $settings['custom_php_ini'] ) ) {
			$content .= "\n; Custom php.ini directives\n" . $settings['custom_php_ini'] . "\n";
		}

		$result = EasyIniFile::write( $content );

		if ( is_wp_error( $result ) ) {
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'config_files_error', $result->get_error_message(), 'error' );
		} elseif ( ! empty( $result ) && is_array( $result ) ) {
			$this->plugin->get_live_check()->mark_saved();
			add_settings_error(
				'easy_php_settings_settings',
				'config_files_created',
				sprintf(
					/* translators: 1: comma-separated list of written file names, 2: seconds PHP caches .user.ini for */
					__( 'Settings saved and written to: %1$s. PHP applies .user.ini changes within %2$d seconds; the "In use" values show when they take effect.', 'easy-php-settings' ),
					implode( ', ', $result ),
					(int) ini_get( 'user_ini.cache_ttl' )
				),
				'updated'
			);
		} else {
			add_settings_error( 'easy_php_settings_settings', 'config_files_error', __( 'Settings saved, but could not write to INI files. Please check file permissions.', 'easy-php-settings' ), 'warning' );
		}
	}

	/* ─── Apply runtime ini_set ───────────────── */

	private function apply_settings() {
		$options = $this->plugin->get_option( 'easy_php_settings_settings' );
		if ( empty( $options ) || ! is_array( $options ) ) {
			return;
		}
		$all          = ini_get_all();
		$valid_keys   = $this->plugin->get_settings_keys();
		foreach ( $options as $key => $value ) {
			if ( ! in_array( $key, $valid_keys, true ) || empty( $value ) ) {
				continue;
			}

			// Re-validate at apply time. Defends against the option being
			// tampered after save (compromised plugin, manual SQL, multisite
			// option leakage). Stored values have already been sanitized
			// through Easy_Settings_Validator::sanitize_setting on save,
			// but defense-in-depth costs us nothing here.
			$sanitized = Easy_Settings_Validator::sanitize_setting( $key, $value );
			if ( '' === $sanitized || is_wp_error( Easy_Settings_Validator::validate_setting( $key, $sanitized ) ) ) {
				continue;
			}

			$access = $all[ $key ]['access'] ?? 0;
			if ( INI_USER !== $access && INI_ALL !== $access ) {
				continue;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky, Squiz.PHP.DiscouragedFunctions.Discouraged -- applying administrator-configured INI values is this plugin's purpose.
			@ini_set( $key, $sanitized );
		}
	}

	/* ─── WP Memory Constants ─────────────────── */

	private function update_wp_memory_constants( $input ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->is_writable( $config_path ) ) {
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_wp_memory_settings', 'config_not_writable', __( 'wp-config.php is not writable.', 'easy-php-settings' ), 'error' );
			return;
		}

		$backup = Easy_Config_Backup::create_backup();
		if ( is_wp_error( $backup ) ) {
			Easy_Error_Handler::log_error( $backup->get_error_message(), 'create_backup', 'warning' );
		}

		$config_content = $wp_filesystem->get_contents( $config_path );
		if ( false === $config_content ) {
			throw new Exception( esc_html__( 'Failed to read wp-config.php file.', 'easy-php-settings' ) );
		}

		foreach ( array( 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT' ) as $const ) {
			$key = strtolower( $const );
			if ( empty( $input[ $key ] ) ) {
				continue;
			}
			$trimmed = trim( $input[ $key ] );
			if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $trimmed ) ) {
				throw new Exception(
					esc_html(
						sprintf(
							/* translators: %s: wp-config.php constant name */
							__( 'Invalid value format for %s. Only numbers and K/M/G/T units are allowed.', 'easy-php-settings' ),
							$const
						)
					)
				);
			}
			$sanitized  = preg_replace( '/[^0-9KMGT]/i', '', $trimmed );
			$validation = Easy_Settings_Validator::validate_wp_memory_setting( $key, $sanitized );
			if ( is_wp_error( $validation ) ) {
				throw new Exception( esc_html( $validation->get_error_message() ) );
			}
			$result = Easy_Config_Parser::update_constant( $config_content, $const, $sanitized, 'string' );
			if ( is_wp_error( $result ) ) {
				throw new Exception( esc_html( $result->get_error_message() ) );
			}
			$config_content = $result;
		}

		$validation = Easy_Config_Backup::validate_config_structure( $config_content );
		if ( is_wp_error( $validation ) ) {
			$latest = Easy_Config_Backup::get_latest_backup();
			if ( $latest ) {
				Easy_Config_Backup::restore_backup( $latest['key'] );
			}
			throw new Exception( esc_html( $validation->get_error_message() ) );
		}

		if ( ! $wp_filesystem->put_contents( $config_path, $config_content ) ) {
			$latest = Easy_Config_Backup::get_latest_backup();
			if ( $latest ) {
				Easy_Config_Backup::restore_backup( $latest['key'] );
			}
			throw new Exception( esc_html__( 'Failed to write wp-config.php file.', 'easy-php-settings' ) );
		}

		add_settings_error( 'easy_php_settings_wp_memory_settings', 'wp_memory_updated', __( 'WordPress memory settings updated successfully in wp-config.php.', 'easy-php-settings' ), 'updated' );
	}

	/* ─── INI File Delete Action ──────────────── */

	private function handle_ini_file_actions() {
		if ( ! isset( $_POST['easy_php_settings_delete_ini_files'] ) ) {
			return;
		}
		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'easy-php-settings' ), 403 );
		}
		check_admin_referer( 'easy_php_settings_delete_ini_nonce' );

		$result = EasyIniFile::remove_files();

		if ( is_wp_error( $result ) ) {
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'files_deleted_error', $result->get_error_message(), 'error' );
		} elseif ( ! empty( $result ) && is_array( $result ) ) {
			add_settings_error(
				'easy_php_settings_settings',
				'files_deleted_success',
				sprintf(
					/* translators: %s: comma-separated list of cleaned file names */
					__( 'Removed Easy PHP Settings directives from: %s.', 'easy-php-settings' ),
					implode( ', ', $result )
				),
				'success'
			);
		} else {
			add_settings_error( 'easy_php_settings_settings', 'files_deleted_error', __( 'No Easy PHP Settings directives were found to remove, or the files could not be changed. Check file permissions.', 'easy-php-settings' ), 'warning' );
		}
	}

	/* ─── Admin Tab ───────────────────────────── */

	public function get_admin_tab() {
		return array(
			'id'       => 'general_settings',
			'title'    => __( 'General Settings', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	/* ─── Helpers ─────────────────────────────── */

	public function get_wp_memory_value( $key ) {
		switch ( $key ) {
			case 'wp_memory_limit':
				return defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
			case 'wp_max_memory_limit':
				return defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '256M';
			default:
				return 'N/A';
		}
	}

	private function show_alternative_instructions( $key, $recommended ) {
		$options    = $this->plugin->get_option( 'easy_php_settings_settings' );
		$user_value = isset( $options[ $key ] ) ? $options[ $key ] : ( $recommended[ $key ] ?? '128M' );
		$server_api = php_sapi_name();

		$htaccess_line = 'php_value ' . $key . ' ' . $user_value;
		$user_ini_line = $key . ' = ' . $user_value;
		$php_ini_line  = $key . ' = ' . $user_value;

		$block_id = 'easy-php-manual-' . esc_attr( $key );
		$output   = '<details class="easy-php-manual-config" id="' . $block_id . '">';
		$output  .= '<summary>' . esc_html__( 'Manual configuration instructions', 'easy-php-settings' ) . '</summary>';
		$output  .= '<div class="easy-php-manual-config-inner">';

		if ( strpos( $server_api, 'apache' ) !== false || strpos( $server_api, 'cgi' ) !== false ) {
			$output .= '<div class="easy-php-config-snippet"><label>' . esc_html__( '.htaccess', 'easy-php-settings' ) . '</label>';
			$output .= '<div class="easy-php-snippet-row"><code data-copy="' . esc_attr( $htaccess_line ) . '">' . esc_html( $htaccess_line ) . '</code>';
			$output .= '<button type="button" class="button button-small easy-php-copy-snippet" data-text="' . esc_attr( $htaccess_line ) . '">' . esc_html__( 'Copy', 'easy-php-settings' ) . '</button></div></div>';
		}
		$output .= '<div class="easy-php-config-snippet"><label>' . esc_html__( '.user.ini', 'easy-php-settings' ) . '</label>';
		$output .= '<div class="easy-php-snippet-row"><code data-copy="' . esc_attr( $user_ini_line ) . '">' . esc_html( $user_ini_line ) . '</code>';
		$output .= '<button type="button" class="button button-small easy-php-copy-snippet" data-text="' . esc_attr( $user_ini_line ) . '">' . esc_html__( 'Copy', 'easy-php-settings' ) . '</button></div></div>';
		$output .= '<div class="easy-php-config-snippet"><label>' . esc_html__( 'php.ini', 'easy-php-settings' ) . '</label>';
		$output .= '<div class="easy-php-snippet-row"><code data-copy="' . esc_attr( $php_ini_line ) . '">' . esc_html( $php_ini_line ) . '</code>';
		$output .= '<button type="button" class="button button-small easy-php-copy-snippet" data-text="' . esc_attr( $php_ini_line ) . '">' . esc_html__( 'Copy', 'easy-php-settings' ) . '</button></div></div>';
		$output .= '</div></details>';

		echo wp_kses_post( $output );
	}

	/* ─── Tab Rendering ───────────────────────── */

	/**
	 * Human labels for each setting.
	 *
	 * @return array<string, string>
	 */
	private function get_field_labels() {
		return array(
			'memory_limit'        => __( 'Memory limit', 'easy-php-settings' ),
			'upload_max_filesize' => __( 'Max upload size', 'easy-php-settings' ),
			'post_max_size'       => __( 'Max POST size', 'easy-php-settings' ),
			'max_execution_time'  => __( 'Max execution time', 'easy-php-settings' ),
			'max_input_vars'      => __( 'Max input vars', 'easy-php-settings' ),
			'wp_memory_limit'     => __( 'WordPress memory', 'easy-php-settings' ),
			'wp_max_memory_limit' => __( 'WordPress admin memory', 'easy-php-settings' ),
		);
	}

	/**
	 * Placeholder hint describing the expected unit.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function get_unit_hint( $key ) {
		if ( 'max_execution_time' === $key ) {
			return __( 'seconds', 'easy-php-settings' );
		}
		if ( 'max_input_vars' === $key ) {
			return __( 'count', 'easy-php-settings' );
		}
		return __( 'e.g. 256M', 'easy-php-settings' );
	}

	/**
	 * Build the view data for one group of setting rows.
	 *
	 * @param string[] $keys        Setting keys.
	 * @param string   $option      Option the values are stored in.
	 * @param array    $recommended Recommended values.
	 * @param callable $live_value  Returns the value in effect for a key.
	 * @return array[]
	 */
	private function build_rows( array $keys, $option, array $recommended, callable $live_value ) {
		$saved    = $this->plugin->get_option( $option, array() );
		$saved    = is_array( $saved ) ? $saved : array();
		$labels   = $this->get_field_labels();
		$tooltips = $this->plugin->get_tooltips();
		$rows     = array();

		foreach ( $keys as $key ) {
			$value = isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '';
			$live  = (string) call_user_func( $live_value, $key );
			$rows[] = array(
				'key'         => $key,
				'id'          => 'eps-field-' . $key,
				'name'        => $option . '[' . $key . ']',
				'label'       => isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
				'help'        => isset( $tooltips[ $key ] ) ? $tooltips[ $key ] : '',
				'value'       => $value,
				'live'        => $live,
				'recommended' => isset( $recommended[ $key ] ) ? $recommended[ $key ] : '',
				'unit_hint'   => $this->get_unit_hint( $key ),
				'health'      => Easy_Limit_Health::status( $key, $live, isset( $recommended[ $key ] ) ? $recommended[ $key ] : '' ),
				'applied'     => Easy_Limit_Health::applied_state( $key, $value, $live ),
			);
		}

		return $rows;
	}

	public function render_tab() {
		$options = $this->plugin->get_option( 'easy_php_settings_settings', array() );
		$view    = array(
			'presets'    => $this->plugin->get_presets(),
			'php_rows'   => $this->php_view_rows(),
			'wp_rows'    => $this->build_rows(
				$this->plugin->get_wp_memory_settings_keys(),
				'easy_php_settings_wp_memory_settings',
				$this->plugin->get_wp_memory_recommended_values(),
				array( $this, 'get_wp_memory_value' )
			),
			'custom_ini' => is_array( $options ) && isset( $options['custom_php_ini'] ) ? (string) $options['custom_php_ini'] : '',
			'cache_ttl'  => (int) ini_get( 'user_ini.cache_ttl' ),
			'review'     => $this->plugin->get_module_manager()->get_module( 'review_request' ),
		);

		include plugin_dir_path( dirname( __FILE__ ) ) . 'view/general-settings-tab.php';
	}

	/**
	 * View rows for the PHP settings, using the values in the current request.
	 *
	 * @return array[]
	 */
	public function php_view_rows() {
		return $this->build_rows(
			$this->plugin->get_settings_keys(),
			'easy_php_settings_settings',
			$this->plugin->get_recommended_values(),
			function ( $key ) {
				return (string) ini_get( $key );
			}
		);
	}

	/**
	 * Print the "In use" cell for one row.
	 *
	 * @param array $row    Row from build_rows().
	 * @param bool  $is_php True for PHP INI settings, false for wp-config.php constants.
	 * @return void
	 */
	public function render_row_state( array $row, $is_php ) {
		$health_labels = array(
			Easy_Limit_Health::LOW  => __( 'Below recommended', 'easy-php-settings' ),
			Easy_Limit_Health::OK   => __( 'Recommended range', 'easy-php-settings' ),
			Easy_Limit_Health::HIGH => __( 'Above recommended', 'easy-php-settings' ),
		);
		$cache_ttl     = (int) ini_get( 'user_ini.cache_ttl' );

		include plugin_dir_path( dirname( __FILE__ ) ) . 'view/partials/setting-state.php';
	}

	/**
	 * "In use" cells for every PHP setting, keyed by setting, as HTML.
	 *
	 * Used by the REST live check, which runs without the plugin's ini_set()
	 * calls and so shows the values PHP really uses.
	 *
	 * @return array<string, array{applied: string, html: string}>
	 */
	public function render_php_states() {
		$states = array();
		foreach ( $this->php_view_rows() as $row ) {
			ob_start();
			$this->render_row_state( $row, true );
			$states[ $row['key'] ] = array(
				'applied' => $row['applied'],
				'html'    => (string) ob_get_clean(),
			);
		}
		return $states;
	}

	/**
	 * Render the manual-configuration snippets for one setting.
	 *
	 * Called from the view for settings the server is not using yet.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	public function render_manual_instructions( $key ) {
		$this->show_alternative_instructions( $key, $this->plugin->get_recommended_values() );
	}
}
