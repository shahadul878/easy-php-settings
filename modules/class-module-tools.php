<?php
/**
 * Tools Module
 *
 * Owns debugging settings, log viewer, export/import tools, and reset actions.
 *
 * @package EasyPHPSettings
 * @since   1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_Tools extends Easy_Module_Base {

	protected $module_id          = 'tools';
	protected $module_name        = 'Tools';
	protected $module_description = 'Debugging settings, log viewer, export/import, and reset tools';

	/* ─── Hooks ───────────────────────────────── */

	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_debugging_settings' ) );
	}

	public function handle_admin_actions() {
		$this->handle_log_actions();
		$this->handle_export_import();
		$this->handle_reset_actions();
	}

	/* ─── Debugging Settings Registration ─────── */

	public function register_debugging_settings() {
		register_setting(
			'easy_php_settings_debugging',
			'easy_php_settings_debugging_settings',
			array(
				'sanitize_callback' => array( $this, 'update_wp_config_constants' ),
			)
		);

		add_settings_section(
			'easy_php_settings_debugging_section',
			__( 'Debugging Constants', 'easy-php-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Control WordPress debugging constants defined in wp-config.php.', 'easy-php-settings' ) . '</p>';
			},
			'easy_php_settings_debugging'
		);

		$fields = array(
			'wp_debug'         => 'WP_DEBUG',
			'wp_debug_log'     => 'WP_DEBUG_LOG',
			'wp_debug_display' => 'WP_DEBUG_DISPLAY',
			'script_debug'     => 'SCRIPT_DEBUG',
		);

		foreach ( $fields as $name => $label ) {
			add_settings_field( $name, $label, array( $this, 'render_debugging_field' ), 'easy_php_settings_debugging', 'easy_php_settings_debugging_section', array( 'name' => $name ) );
		}
	}

	/* ─── Debugging Field Renderer ────────────── */

	public function render_debugging_field( $args ) {
		$name        = $args['name'];
		$is_defined  = defined( strtoupper( $name ) ) && constant( strtoupper( $name ) );
		$is_disabled = ( 'wp_debug' !== $name && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) );

		$html  = '<label class="switch">';
		$html .= '<input type="checkbox" name="easy_php_settings_debugging_settings[' . esc_attr( $name ) . ']" value="1" ' . checked( 1, $is_defined, false ) . ' ' . disabled( $is_disabled, true, false ) . '>';
		$html .= '<span class="slider round"></span>';
		$html .= '</label>';

		echo wp_kses( $html, array(
			'label' => array( 'class' => array() ),
			'input' => array( 'type' => array(), 'name' => array(), 'value' => array(), 'checked' => array(), 'disabled' => array() ),
			'span'  => array( 'class' => array() ),
		) );
	}

	/* ─── Update wp-config.php debugging constants ─ */

	public function update_wp_config_constants( $input ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->is_writable( $config_path ) ) {
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_debugging_settings', 'config_not_writable', __( 'wp-config.php is not writable.', 'easy-php-settings' ), 'error' );
			return get_option( 'easy_php_settings_debugging_settings' );
		}

		$backup = Easy_Config_Backup::create_backup();
		if ( is_wp_error( $backup ) ) {
			Easy_Error_Handler::log_error( $backup->get_error_message(), 'create_backup', 'warning' );
		}

		try {
			$config_content = $wp_filesystem->get_contents( $config_path );
			if ( false === $config_content ) {
				throw new Exception( __( 'Failed to read wp-config.php file.', 'easy-php-settings' ) );
			}

			$constants   = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG' );
			$new_options = get_option( 'easy_php_settings_debugging_settings', array() );

			foreach ( $constants as $const ) {
				$key   = strtolower( $const );
				$value = isset( $input[ $key ] ) ? true : false;

				$result = Easy_Config_Parser::update_constant( $config_content, $const, $value, 'bool' );
				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}
				$config_content      = $result;
				$new_options[ $key ] = $value;
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

			add_settings_error( 'easy_php_settings_debugging_settings', 'settings_updated', __( 'Debugging settings updated successfully.', 'easy-php-settings' ), 'updated' );

		} catch ( Exception $e ) {
			Easy_Error_Handler::handle_exception( $e, 'update_wp_config_constants' );
			Easy_Error_Handler::add_settings_error( 'easy_php_settings_debugging_settings', 'config_update_error', __( 'Failed to update wp-config.php. Please check error log.', 'easy-php-settings' ), 'error' );
		}

		return $new_options;
	}

	/* ─── Log Actions ─────────────────────────── */

	private function handle_log_actions() {
		if ( ! isset( $_POST['easy_php_settings_clear_log'] ) || '1' !== $_POST['easy_php_settings_clear_log'] ) {
			return;
		}
		check_admin_referer( 'easy_php_settings_clear_log_nonce' );

		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'easy-php-settings' ) );
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$creds = request_filesystem_credentials( '', '', false, false, null );
			if ( false === $creds || ! WP_Filesystem( $creds ) ) {
				add_settings_error( 'easy_php_settings_settings', 'log_clear_error', __( 'Could not initialize filesystem.', 'easy-php-settings' ), 'error' );
				return;
			}
		}

		$log_file = WP_CONTENT_DIR . '/debug.log';
		$cleared  = false;

		if ( $wp_filesystem->exists( $log_file ) ) {
			$cleared = $wp_filesystem->is_writable( $log_file )
				? $wp_filesystem->put_contents( $log_file, '' )
				: $wp_filesystem->delete( $log_file );
		} else {
			$cleared = true;
		}

		if ( ! $cleared && file_exists( $log_file ) && is_writable( $log_file ) ) {
			$cleared = ( false !== file_put_contents( $log_file, '' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		if ( $cleared ) {
			add_settings_error( 'easy_php_settings_settings', 'log_cleared', __( 'Debug log file cleared successfully.', 'easy-php-settings' ), 'updated' );
			set_transient( 'easy_php_settings_log_cleared', true, 30 );
		} else {
			add_settings_error( 'easy_php_settings_settings', 'log_clear_error', __( 'Could not clear debug log file. Check file permissions.', 'easy-php-settings' ), 'error' );
		}
	}

	/* ─── Export / Import ─────────────────────── */

	private function handle_export_import() {
		if ( isset( $_POST['easy_php_settings_export'] ) && check_admin_referer( 'easy_php_settings_export_nonce' ) ) {
			if ( ! current_user_can( $this->plugin->get_capability() ) ) {
				return;
			}
			$data = array(
				'php_settings'    => $this->plugin->get_option( 'easy_php_settings_settings', array() ),
				'wp_memory'       => $this->plugin->get_option( 'easy_php_settings_wp_memory_settings', array() ),
				'plugin_version'  => $this->plugin->get_version(),
				'export_time'     => current_time( 'mysql' ),
				'export_site_url' => get_site_url(),
			);
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="easy-php-settings-' . gmdate( 'Y-m-d-His' ) . '.json"' );
			echo wp_json_encode( $data, JSON_PRETTY_PRINT );
			exit;
		}

		if ( isset( $_POST['easy_php_settings_import'] ) && check_admin_referer( 'easy_php_settings_import_nonce' ) ) {
			if ( ! current_user_can( $this->plugin->get_capability() ) ) {
				return;
			}
			if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
				Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'import_no_file', __( 'No file selected for import.', 'easy-php-settings' ), 'error' );
				return;
			}

			try {
				$file_validation = Easy_Settings_Validator::validate_import_file( $_FILES['import_file'] );
				if ( is_wp_error( $file_validation ) ) {
					throw new Exception( $file_validation->get_error_message() );
				}

				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}
				$json = $wp_filesystem->get_contents( $_FILES['import_file']['tmp_name'] );
				if ( false === $json ) {
					throw new Exception( __( 'Failed to read import file.', 'easy-php-settings' ) );
				}
				$settings = json_decode( $json, true );
				if ( ! $settings || ! is_array( $settings ) ) {
					throw new Exception( __( 'Invalid settings file format.', 'easy-php-settings' ) );
				}
				if ( isset( $settings['php_settings'] ) && is_array( $settings['php_settings'] ) ) {
					foreach ( $settings['php_settings'] as $key => $value ) {
						if ( in_array( $key, $this->plugin->get_settings_keys(), true ) ) {
							$v = Easy_Settings_Validator::validate_setting( $key, $value );
							if ( is_wp_error( $v ) ) {
								throw new Exception( sprintf( __( 'Invalid value for %s: %s', 'easy-php-settings' ), $key, $v->get_error_message() ) );
							}
						}
					}
				}

				$this->plugin->update_option( 'easy_php_settings_import_backup', array(
					'php_settings' => $this->plugin->get_option( 'easy_php_settings_settings', array() ),
					'wp_memory'    => $this->plugin->get_option( 'easy_php_settings_wp_memory_settings', array() ),
				) );

				if ( isset( $settings['php_settings'] ) ) {
					$this->plugin->update_option( 'easy_php_settings_settings', $settings['php_settings'] );
					Easy_Settings_Cache::invalidate( 'settings' );
				}
				if ( isset( $settings['wp_memory'] ) ) {
					$this->plugin->update_option( 'easy_php_settings_wp_memory_settings', $settings['wp_memory'] );
				}
				add_settings_error( 'easy_php_settings_settings', 'import_success', __( 'Settings imported successfully. A backup of your previous settings was created.', 'easy-php-settings' ), 'updated' );

			} catch ( Exception $e ) {
				Easy_Error_Handler::handle_exception( $e, 'handle_export_import' );
				Easy_Error_Handler::add_settings_error( 'easy_php_settings_settings', 'import_error', __( 'Failed to import settings. Please check error log.', 'easy-php-settings' ), 'error' );
			}
		}
	}

	/* ─── Reset Actions ───────────────────────── */

	private function handle_reset_actions() {
		if ( isset( $_POST['easy_php_settings_reset_recommended'] ) && check_admin_referer( 'easy_php_settings_reset_nonce' ) ) {
			if ( ! current_user_can( $this->plugin->get_capability() ) ) {
				return;
			}
			$this->plugin->update_option( 'easy_php_settings_reset_backup', $this->plugin->get_option( 'easy_php_settings_settings', array() ) );
			$this->plugin->update_option( 'easy_php_settings_settings', $this->plugin->get_recommended_values() );
			add_settings_error( 'easy_php_settings_settings', 'reset_success', __( 'Settings reset to recommended values. A backup was created.', 'easy-php-settings' ), 'updated' );
		}

		if ( isset( $_POST['easy_php_settings_reset_default'] ) && check_admin_referer( 'easy_php_settings_reset_nonce' ) ) {
			if ( ! current_user_can( $this->plugin->get_capability() ) ) {
				return;
			}
			$this->plugin->update_option( 'easy_php_settings_reset_backup', $this->plugin->get_option( 'easy_php_settings_settings', array() ) );
			$this->plugin->delete_option( 'easy_php_settings_settings' );
			add_settings_error( 'easy_php_settings_settings', 'reset_default_success', __( 'Settings cleared. Server defaults will now apply. A backup was created.', 'easy-php-settings' ), 'updated' );
		}
	}

	/* ─── Admin Tab ───────────────────────────── */

	public function get_admin_tab() {
		return array(
			'id'       => 'tools',
			'title'    => __( 'Tools', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	public function render_tab() {
		?>
		<div id="tools-tab">
			<!-- Debugging Settings -->
			<div class="easy-php-settings-config-box" style="margin-bottom:30px;">
				<h3><span class="dashicons dashicons-admin-tools" style="color:#2271b1;"></span> <?php esc_html_e( 'WordPress Debugging Settings', 'easy-php-settings' ); ?></h3>
				<p style="margin-bottom:12px;color:#646970;"><?php esc_html_e( 'Control WordPress debugging constants defined in wp-config.php.', 'easy-php-settings' ); ?></p>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'easy_php_settings_debugging' );
					do_settings_sections( 'easy_php_settings_debugging' );
					?>
					<div class="easy-php-settings-save-wrapper">
						<div class="easy-php-settings-save-box">
							<p class="submit" style="margin:0;padding:0;text-align:center;">
								<input type="submit" name="submit" id="easy-php-settings-debug-save-button" class="button button-primary button-large" value="<?php echo esc_attr( __( 'Save Debugging Settings', 'easy-php-settings' ) ); ?>">
							</p>
						</div>
					</div>
				</form>
			</div>

			<!-- Log Viewer -->
			<div class="easy-php-settings-config-box" style="margin-bottom:30px;">
				<h3><span class="dashicons dashicons-visibility" style="color:#2271b1;"></span> <?php esc_html_e( 'Debug Log Viewer', 'easy-php-settings' ); ?></h3>
				<?php $this->render_log_viewer(); ?>
			</div>

			<!-- Export / Import -->
			<div class="easy-php-settings-config-box" style="margin-bottom:30px;">
				<h3><span class="dashicons dashicons-download" style="color:#2271b1;"></span> <?php esc_html_e( 'Export / Import Configuration', 'easy-php-settings' ); ?></h3>

				<h4><?php esc_html_e( 'Export', 'easy-php-settings' ); ?></h4>
				<p style="color:#646970;"><?php esc_html_e( 'Download your current settings as a JSON file.', 'easy-php-settings' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'easy_php_settings_export_nonce' ); ?>
					<button type="submit" name="easy_php_settings_export" class="button button-primary"><span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:3px;"></span> <?php esc_html_e( 'Export Settings', 'easy-php-settings' ); ?></button>
				</form>

				<h4 style="margin-top:20px;"><?php esc_html_e( 'Import', 'easy-php-settings' ); ?></h4>
				<p style="color:#646970;"><?php esc_html_e( 'Import settings from a previously exported JSON file. Your current settings will be backed up first.', 'easy-php-settings' ); ?></p>
				<form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
					<?php wp_nonce_field( 'easy_php_settings_import_nonce' ); ?>
					<input type="file" name="import_file" accept=".json" required style="flex:1;min-width:250px;">
					<button type="submit" name="easy_php_settings_import" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'This will overwrite your current settings. A backup will be created. Continue?', 'easy-php-settings' ) ); ?>');"><span class="dashicons dashicons-upload" style="vertical-align:middle;margin-top:3px;"></span> <?php esc_html_e( 'Import Settings', 'easy-php-settings' ); ?></button>
				</form>
			</div>

			<!-- Reset -->
			<div class="easy-php-settings-config-box easy-php-settings-warning-box">
				<h3><span class="dashicons dashicons-update" style="color:#f0ad4e;"></span> <?php esc_html_e( 'Reset Configuration', 'easy-php-settings' ); ?></h3>
				<p style="color:#646970;"><?php esc_html_e( 'Reset settings to recommended values or clear all customizations. A backup is created automatically.', 'easy-php-settings' ); ?></p>
				<div style="display:flex;gap:10px;flex-wrap:wrap;">
					<form method="post">
						<?php wp_nonce_field( 'easy_php_settings_reset_nonce' ); ?>
						<button type="submit" name="easy_php_settings_reset_recommended" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Reset all settings to recommended values?', 'easy-php-settings' ) ); ?>');"><?php esc_html_e( 'Reset to Recommended', 'easy-php-settings' ); ?></button>
					</form>
					<form method="post">
						<?php wp_nonce_field( 'easy_php_settings_reset_nonce' ); ?>
						<button type="submit" name="easy_php_settings_reset_default" class="button button-danger" onclick="return confirm('<?php echo esc_js( __( 'Clear ALL custom settings and revert to server defaults?', 'easy-php-settings' ) ); ?>');"><?php esc_html_e( 'Reset to Server Defaults', 'easy-php-settings' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─── Log Viewer Sub-Render ───────────────── */

	private function render_log_viewer() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( get_transient( 'easy_php_settings_log_cleared' ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Debug log file cleared successfully.', 'easy-php-settings' ) );
			delete_transient( 'easy_php_settings_log_cleared' );
		}

		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html__( 'WP_DEBUG_LOG is not enabled. Enable it from the Debugging Settings above.', 'easy-php-settings' ) );
			return;
		}

		$log_file    = WP_CONTENT_DIR . '/debug.log';
		$log_content = '';

		if ( $wp_filesystem->exists( $log_file ) && $wp_filesystem->is_readable( $log_file ) ) {
			$log_content = $wp_filesystem->get_contents( $log_file );
			if ( empty( $log_content ) ) {
				echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug log file is empty.', 'easy-php-settings' ) . '</p></div>';
			}
		} else {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug log file does not exist yet.', 'easy-php-settings' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=easy-php-settings&tab=tools' ) ); ?>" style="margin-bottom:15px;">
			<?php wp_nonce_field( 'easy_php_settings_clear_log_nonce' ); ?>
			<input type="hidden" name="easy_php_settings_clear_log" value="1">
			<input type="submit" id="easy-php-settings-clear-log-button" class="button button-danger" value="<?php esc_attr_e( 'Clear Log File', 'easy-php-settings' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete the debug log?', 'easy-php-settings' ) ); ?>');">
		</form>
		<textarea id="easy_php_settings-log-viewer" style="width:100%;height:500px;font-family:monospace;" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
		<?php
	}
}
