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

		add_settings_section(
			'easy_php_settings_section',
			__( 'PHP Configuration Settings', 'easy-php-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Configure PHP settings to optimize your WordPress site performance.', 'easy-php-settings' ) . '</p>';
			},
			'easy_php_settings'
		);

		$labels = array(
			'memory_limit'        => __( 'Memory Limit', 'easy-php-settings' ),
			'upload_max_filesize' => __( 'Upload Max Filesize', 'easy-php-settings' ),
			'post_max_size'       => __( 'Post Max Size', 'easy-php-settings' ),
			'max_execution_time'  => __( 'Max Execution Time', 'easy-php-settings' ),
			'max_input_vars'      => __( 'Max Input Vars', 'easy-php-settings' ),
		);

		foreach ( $this->plugin->get_settings_keys() as $key ) {
			add_settings_field(
				$key,
				$labels[ $key ] ?? ucwords( str_replace( array( '_', '.' ), ' ', $key ) ),
				array( $this, 'render_setting_field' ),
				'easy_php_settings',
				'easy_php_settings_section',
				array( 'key' => $key )
			);
		}

		$this->apply_settings();
	}

	/* ─── Sanitize Callbacks ──────────────────── */

	public function sanitize_callback( $input ) {
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
			$new_input['custom_php_ini'] = trim( $input['custom_php_ini'] );
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
			add_settings_error(
				'easy_php_settings_settings',
				'config_files_created',
				sprintf( __( 'Settings saved and written to: %s. Please restart your web server for changes to take effect.', 'easy-php-settings' ), implode( ', ', $result ) ),
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
		$all = ini_get_all();
		foreach ( $options as $key => $value ) {
			if ( ! in_array( $key, $this->plugin->get_settings_keys(), true ) || empty( $value ) ) {
				continue;
			}
			$access = $all[ $key ]['access'] ?? 0;
			if ( INI_USER !== $access && INI_ALL !== $access ) {
				continue;
			}
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
			@ini_set( $key, $value );
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
			throw new Exception( __( 'Failed to read wp-config.php file.', 'easy-php-settings' ) );
		}

		foreach ( array( 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT' ) as $const ) {
			$key = strtolower( $const );
			if ( empty( $input[ $key ] ) ) {
				continue;
			}
			$trimmed = trim( $input[ $key ] );
			if ( ! preg_match( '/^(\d+)([KMGT]?)$/i', $trimmed ) ) {
				throw new Exception( sprintf( __( 'Invalid value format for %s. Only numbers and K/M/G/T units are allowed.', 'easy-php-settings' ), $const ) );
			}
			$sanitized  = preg_replace( '/[^0-9KMGT]/i', '', $trimmed );
			$validation = Easy_Settings_Validator::validate_wp_memory_setting( $key, $sanitized );
			if ( is_wp_error( $validation ) ) {
				throw new Exception( $validation->get_error_message() );
			}
			$result = Easy_Config_Parser::update_constant( $config_content, $const, $sanitized, 'string' );
			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}
			$config_content = $result;
		}

		$validation = Easy_Config_Backup::validate_config_structure( $config_content );
		if ( is_wp_error( $validation ) ) {
			$latest = Easy_Config_Backup::get_latest_backup();
			if ( $latest ) {
				Easy_Config_Backup::restore_backup( $latest['key'] );
			}
			throw new Exception( $validation->get_error_message() );
		}

		if ( ! $wp_filesystem->put_contents( $config_path, $config_content ) ) {
			$latest = Easy_Config_Backup::get_latest_backup();
			if ( $latest ) {
				Easy_Config_Backup::restore_backup( $latest['key'] );
			}
			throw new Exception( __( 'Failed to write wp-config.php file.', 'easy-php-settings' ) );
		}

		add_settings_error( 'easy_php_settings_wp_memory_settings', 'wp_memory_updated', __( 'WordPress memory settings updated successfully in wp-config.php.', 'easy-php-settings' ), 'updated' );
	}

	/* ─── INI File Delete Action ──────────────── */

	private function handle_ini_file_actions() {
		if ( ! isset( $_POST['easy_php_settings_delete_ini_files'] ) || ! check_admin_referer( 'easy_php_settings_delete_ini_nonce' ) ) {
			return;
		}
		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			return;
		}

		$result = EasyIniFile::remove_files();

		if ( is_wp_error( $result ) ) {
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'files_deleted_error', $result->get_error_message(), 'error' );
		} elseif ( ! empty( $result ) && is_array( $result ) ) {
			add_settings_error( 'easy_php_settings_settings', 'files_deleted_success', sprintf( __( 'Successfully deleted: %s.', 'easy-php-settings' ), implode( ', ', $result ) ), 'success' );
		} else {
			add_settings_error( 'easy_php_settings_settings', 'files_deleted_error', __( 'Could not delete INI files. They may not exist or have permission issues.', 'easy-php-settings' ), 'warning' );
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

	/* ─── Field Renderers ─────────────────────── */

	public function render_setting_field( $args ) {
		$options       = $this->plugin->get_option( 'easy_php_settings_settings' );
		$key           = $args['key'];
		$value         = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$current_value = ini_get( $key );
		$all_settings  = ini_get_all();
		$recommended   = $this->plugin->get_recommended_values();
		$access        = $all_settings[ $key ]['access'] ?? 0;
		$is_changeable = ( INI_USER === $access || INI_ALL === $access );
		$tooltips      = $this->plugin->get_tooltips();
		$tooltip       = isset( $tooltips[ $key ] ) ? $tooltips[ $key ] : '';

		$field_id   = 'easy_php_settings_' . esc_attr( $key );
		$aria_label = sprintf( __( 'Enter value for %s', 'easy-php-settings' ), esc_attr( $key ) );

		$aria_desc = $field_id . '_description';
		if ( $tooltip ) {
			$aria_desc = $field_id . '_help ' . $aria_desc;
		}
		echo '<span class="easy-php-field-wrap">';
		echo "<input type='text' id='" . esc_attr( $field_id ) . "' name='easy_php_settings_settings[" . esc_attr( $key ) . "]' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='" . esc_attr( $recommended[ $key ] ?? '' ) . "' aria-label='" . esc_attr( $aria_label ) . "' aria-describedby='" . esc_attr( $aria_desc ) . "'>";
		if ( $tooltip ) {
			echo '<span id="' . esc_attr( $field_id ) . '_help" class="dashicons dashicons-editor-help easy-php-help-icon" title="' . esc_attr( $tooltip ) . '" aria-label="' . esc_attr__( 'Help', 'easy-php-settings' ) . '"></span>';
		}
		echo '</span>';

		$this->render_status_indicator( $key, $current_value, $recommended );

		if ( ! $is_changeable ) {
			echo '<p class="description easy-php-runtime-warning">' . esc_html__( 'This setting cannot be changed at runtime. Use the configuration generator below.', 'easy-php-settings' ) . '</p>';
			$this->show_alternative_instructions( $key, $recommended );
		}
	}

	public function render_wp_memory_field( $args ) {
		$options       = $this->plugin->get_option( 'easy_php_settings_wp_memory_settings' );
		$key           = $args['key'];
		$value         = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$current_value = $this->get_wp_memory_value( $key );
		$recommended   = $this->plugin->get_wp_memory_recommended_values();
		$tooltips      = $this->plugin->get_tooltips();
		$tooltip       = isset( $tooltips[ $key ] ) ? $tooltips[ $key ] : '';

		$field_id   = 'easy_php_settings_wp_memory_' . esc_attr( $key );
		$aria_label = sprintf( __( 'Enter value for %s', 'easy-php-settings' ), esc_attr( $key ) );

		$aria_desc = $field_id . '_description';
		if ( $tooltip ) {
			$aria_desc = $field_id . '_help ' . $aria_desc;
		}
		echo '<span class="easy-php-field-wrap">';
		echo "<input type='text' id='" . esc_attr( $field_id ) . "' name='easy_php_settings_wp_memory_settings[" . esc_attr( $key ) . "]' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='" . esc_attr( $recommended[ $key ] ?? '' ) . "' aria-label='" . esc_attr( $aria_label ) . "' aria-describedby='" . esc_attr( $aria_desc ) . "'>";
		if ( $tooltip ) {
			echo '<span id="' . esc_attr( $field_id ) . '_help" class="dashicons dashicons-editor-help easy-php-help-icon" title="' . esc_attr( $tooltip ) . '" aria-label="' . esc_attr__( 'Help', 'easy-php-settings' ) . '"></span>';
		}
		echo '</span>';

		$this->render_memory_status_indicator( $key, $current_value, $recommended );
	}

	/* ─── Status Indicators ───────────────────── */

	/**
	 * Get status label and CSS class for a setting value vs recommended.
	 * Returns: 'low' | 'ok' | 'high' (too high / warning).
	 */
	private function get_status_for_value( $key, $current_value, $recommended ) {
		if ( ! isset( $recommended[ $key ] ) ) {
			return null;
		}
		$rec = $recommended[ $key ];
		$is_size = in_array( $key, array( 'memory_limit', 'upload_max_filesize', 'post_max_size', 'wp_memory_limit', 'wp_max_memory_limit' ), true );
		$current_bytes = $is_size ? $this->plugin->convert_to_bytes( $current_value ) : (int) $current_value;
		$rec_bytes     = $is_size ? $this->plugin->convert_to_bytes( $rec ) : (int) $rec;

		if ( $current_bytes < $rec_bytes ) {
			return 'low';
		}
		$threshold = $is_size ? 1.5 : 2;
		if ( $rec_bytes > 0 && $current_bytes > $rec_bytes * $threshold ) {
			return 'high';
		}
		return 'ok';
	}

	private function render_status_indicator( $key, $current_value, $recommended ) {
		$field_id    = 'easy_php_settings_' . esc_attr( $key );
		$description = sprintf( esc_html__( 'Current value: %s', 'easy-php-settings' ), esc_html( $current_value ) );

		if ( isset( $recommended[ $key ] ) ) {
			$rec   = $recommended[ $key ];
			$desc  = sprintf( esc_html__( ' | Recommended: %s', 'easy-php-settings' ), esc_html( $rec ) );
			$status = $this->get_status_for_value( $key, $current_value, $recommended );
			if ( 'low' === $status ) {
				$description .= $desc . ' <span class="status-badge status-low" aria-label="' . esc_attr__( 'Low value', 'easy-php-settings' ) . '">' . esc_html__( 'Low', 'easy-php-settings' ) . '</span>';
			} elseif ( 'high' === $status ) {
				$description .= $desc . ' <span class="status-badge status-high" aria-label="' . esc_attr__( 'Higher than recommended', 'easy-php-settings' ) . '">' . esc_html__( 'Higher than recommended', 'easy-php-settings' ) . '</span>';
			} else {
				$description .= $desc . ' <span class="status-badge status-ok" aria-label="' . esc_attr__( 'OK', 'easy-php-settings' ) . '">' . esc_html__( 'OK', 'easy-php-settings' ) . '</span>';
			}
		}

		echo '<p class="description" id="' . esc_attr( $field_id ) . '_description" role="status">' . wp_kses_post( $description ) . '</p>';
	}

	private function render_memory_status_indicator( $key, $current_value, $recommended ) {
		$field_id    = 'easy_php_settings_wp_memory_' . esc_attr( $key );
		$description = sprintf( esc_html__( 'Current value: %s', 'easy-php-settings' ), esc_html( $current_value ) );

		if ( isset( $recommended[ $key ] ) ) {
			$rec   = $recommended[ $key ];
			$desc  = sprintf( esc_html__( ' | Recommended: %s', 'easy-php-settings' ), esc_html( $rec ) );
			$status = $this->get_status_for_value( $key, $current_value, $recommended );
			if ( 'low' === $status ) {
				$description .= $desc . ' <span class="status-badge status-low" aria-label="' . esc_attr__( 'Low value', 'easy-php-settings' ) . '">' . esc_html__( 'Low', 'easy-php-settings' ) . '</span>';
			} elseif ( 'high' === $status ) {
				$description .= $desc . ' <span class="status-badge status-high" aria-label="' . esc_attr__( 'Higher than recommended', 'easy-php-settings' ) . '">' . esc_html__( 'Higher than recommended', 'easy-php-settings' ) . '</span>';
			} else {
				$description .= $desc . ' <span class="status-badge status-ok" aria-label="' . esc_attr__( 'OK', 'easy-php-settings' ) . '">' . esc_html__( 'OK', 'easy-php-settings' ) . '</span>';
			}
		}

		echo '<p class="description" id="' . esc_attr( $field_id ) . '_description" role="status">' . wp_kses_post( $description ) . '</p>';
	}

	/* ─── Helpers ─────────────────────────────── */

	private function get_wp_memory_value( $key ) {
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

	public function render_tab() {
		$options    = $this->plugin->get_option( 'easy_php_settings_settings' );
		$presets    = $this->plugin->get_presets();
		?>
		<form action="options.php" method="post">
			<?php settings_fields( 'easy_php_settings' ); ?>

			<div class="easy-php-settings-preset-box">
				<h3><span class="dashicons dashicons-admin-settings" style="color:#2271b1;"></span> <?php esc_html_e( 'Quick Configuration Presets', 'easy-php-settings' ); ?></h3>
				<p style="margin-bottom:12px;color:#646970;"><?php esc_html_e( 'Select a pre-configured optimization profile to instantly apply recommended settings for your specific use case.', 'easy-php-settings' ); ?></p>
				<label for="easy_php_settings_preset" style="font-weight:600;display:block;margin-bottom:8px;"><?php esc_html_e( 'Choose a Preset:', 'easy-php-settings' ); ?></label>
				<select id="easy_php_settings_preset">
					<option value=""><?php esc_html_e( '-- Select a Preset Configuration --', 'easy-php-settings' ); ?></option>
					<?php foreach ( $presets as $pk => $pd ) : ?>
						<option value="<?php echo esc_attr( $pk ); ?>"><?php echo esc_html( $pd['name'] ); ?> &mdash; <?php echo esc_html( $pd['description'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="easy-php-settings-config-box">
				<h3><span class="dashicons dashicons-editor-code" style="color:#2271b1;"></span> <?php esc_html_e( 'Custom PHP Configuration', 'easy-php-settings' ); ?></h3>
				<p style="margin-bottom:12px;color:#646970;"><?php esc_html_e( 'Add any additional PHP directives here. These will be included in the generated .user.ini and php.ini files.', 'easy-php-settings' ); ?></p>
				<textarea name="easy_php_settings_settings[custom_php_ini]" id="easy_php_settings_custom_php_ini" rows="10" style="width:100%;" placeholder="<?php esc_attr_e( '; Add custom PHP directives here', 'easy-php-settings' ); ?>"><?php echo isset( $options['custom_php_ini'] ) ? esc_textarea( $options['custom_php_ini'] ) : ''; ?></textarea>
			</div>

			<div class="easy-php-settings-config-box">
				<h3><span class="dashicons dashicons-performance" style="color:#2271b1;"></span> <?php esc_html_e( 'Core PHP Settings', 'easy-php-settings' ); ?></h3>
				<?php do_settings_sections( 'easy_php_settings' ); ?>
			</div>

			<div class="easy-php-settings-config-box">
				<h3><span class="dashicons dashicons-wordpress" style="color:#2271b1;"></span> <?php esc_html_e( 'WordPress Memory Configuration', 'easy-php-settings' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="wp_memory_limit"><?php esc_html_e( 'WP_MEMORY_LIMIT', 'easy-php-settings' ); ?></label></th>
						<td><?php $this->render_wp_memory_field( array( 'key' => 'wp_memory_limit' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_max_memory_limit"><?php esc_html_e( 'WP_MAX_MEMORY_LIMIT', 'easy-php-settings' ); ?></label></th>
						<td><?php $this->render_wp_memory_field( array( 'key' => 'wp_max_memory_limit' ) ); ?></td>
					</tr>
				</table>
			</div>

			<div class="easy-php-settings-actions">
				<p class="submit">
					<input type="submit" name="submit" id="easy-php-settings-save-button" class="button button-primary button-large" value="<?php echo esc_attr( __( 'Save All Settings', 'easy-php-settings' ) ); ?>" />
				</p>
			</div>
		</form>

		<div class="easy-php-settings-danger-zone">
			<h3 class="easy-php-danger-title"><?php esc_html_e( 'Danger Zone', 'easy-php-settings' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Remove configuration files created by this plugin. Use only if you want to revert to server defaults.', 'easy-php-settings' ); ?></p>
			<form action="" method="post" class="easy-php-delete-form">
				<?php wp_nonce_field( 'easy_php_settings_delete_ini_nonce' ); ?>
				<button type="submit" name="easy_php_settings_delete_ini_files" class="button button-secondary easy-php-delete-files-btn" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete the .user.ini and php.ini files created by this plugin?', 'easy-php-settings' ) ); ?>');">
					<?php esc_html_e( 'Delete .ini Files', 'easy-php-settings' ); ?>
				</button>
			</form>
		</div>

		<div class="easy-php-config-generator-box">
			<h3><?php esc_html_e( 'Configuration Generator', 'easy-php-settings' ); ?></h3>
			<p><?php esc_html_e( 'Generate server configuration files with your custom values.', 'easy-php-settings' ); ?></p>
			<button type="button" id="generate-config" class="button button-primary"><?php esc_html_e( 'Generate Configuration Files', 'easy-php-settings' ); ?></button>
			<div id="config-output" class="easy-php-config-output">
				<h4><?php esc_html_e( 'Generated Configuration', 'easy-php-settings' ); ?></h4>
				<div class="easy-php-config-block">
					<label><?php esc_html_e( '.user.ini', 'easy-php-settings' ); ?></label>
					<textarea id="user-ini-content" readonly></textarea>
					<button type="button" class="button button-secondary easy-php-copy-config" data-target="user-ini-content"><?php esc_html_e( 'Copy to Clipboard', 'easy-php-settings' ); ?></button>
				</div>
				<div class="easy-php-config-block" id="htaccess-block">
					<label><?php esc_html_e( '.htaccess (Apache)', 'easy-php-settings' ); ?></label>
					<textarea id="htaccess-content" readonly></textarea>
					<button type="button" class="button button-secondary easy-php-copy-config" data-target="htaccess-content"><?php esc_html_e( 'Copy to Clipboard', 'easy-php-settings' ); ?></button>
				</div>
			</div>
		</div>

		<div class="easy-php-test-settings-box">
			<h3><?php esc_html_e( 'Test Settings', 'easy-php-settings' ); ?></h3>
			<p><?php esc_html_e( 'Test if your current settings can be modified at runtime.', 'easy-php-settings' ); ?></p>
			<button type="button" id="test-settings" class="button button-secondary"><?php esc_html_e( 'Test Settings', 'easy-php-settings' ); ?></button>
			<div id="test-results" class="easy-php-test-results"></div>
		</div>
		<?php
	}
}
