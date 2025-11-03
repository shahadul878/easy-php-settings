<?php
/**
 * Plugin Name: Easy PHP Settings
 * Plugin URI:  https://github.com/easy-php-settings
 * Description: An easy way to manage common PHP INI settings from the WordPress admin panel.
 * Version:     1.0.4
 * Author:      H M Shahadul Islam
 * Author URI:  https://github.com/shahadul878
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: easy-php-settings
 * Domain Path: /languages
 *
 * @package EasyPHPSettings
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyphpinfo.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyinifile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-settings-history.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-extensions-viewer.php';

/**
 * Class Easy_PHP_Settings
 *
 * Handles the easy configuration of PHP settings.
 */
class Easy_PHP_Settings {
	/**
	 * Settings Key
	 *
	 * @var string[] $settings_key .
	 */
	private $settings_keys = array(
		'memory_limit',
		'upload_max_filesize',
		'post_max_size',
		'max_execution_time',
		'max_input_vars',
	);

	/**
	 * WordPress Memory Settings Keys
	 *
	 * @var string[] $wp_memory_settings_keys .
	 */
	private $wp_memory_settings_keys = array(
		'wp_memory_limit',
		'wp_max_memory_limit',
	);

	/**
	 * Settings Recommended Value
	 *
	 * @var string[] $recommended_values
	 */
	private $recommended_values = array(
		'memory_limit'        => '256M',
		'upload_max_filesize' => '128M',
		'post_max_size'       => '256M',
		'max_execution_time'  => '300',
		'max_input_vars'      => '10000',
	);

	/**
	 * WordPress Memory Settings Recommended Values
	 *
	 * @var string[] $wp_memory_recommended_values
	 */
	private $wp_memory_recommended_values = array(
		'wp_memory_limit'     => '256M',
		'wp_max_memory_limit' => '512M',
	);

	/**
	 * Version
	 *
	 * @var string
	 */
	private $version = '1.0.4';

	/**
	 * Setting tooltips
	 *
	 * @var array
	 */
	private $setting_tooltips = array();

	/**
	 * Quick presets
	 *
	 * @var array
	 */
	private $quick_presets = array();

	/**
	 *  Initializes plugin settings.
	 */
	public function __construct() {
		$this->init_tooltips();
		$this->init_presets();
		$hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
		add_action( $hook, array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_init', array( $this, 'handle_ini_file_actions' ) );
		add_action( 'admin_init', array( $this, 'debugging_settings_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_init', array( $this, 'handle_log_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_export_import' ) );
		add_action( 'admin_init', array( $this, 'handle_reset_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_history_actions' ) );
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our admin page.
		if ( 'tools_page_easy-php-settings' !== $hook ) {
			return;
		}

		// Enqueue code editor and settings for php.ini files.
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/x-ini' ) );
		if ( false !== $settings ) {
			wp_add_inline_script(
				'code-editor',
				sprintf( 'jQuery( function() { wp.codeEditor.initialize( "easy_php_settings_custom_php_ini", %s ); } );', wp_json_encode( $settings ) )
			);
		}

		wp_enqueue_style(
			'easy-php-settings-styles',
			plugin_dir_url( __FILE__ ) . 'css/admin-styles.css',
			array(),
			'1.0.2'
		);

		// Enqueue admin.js and pass settings keys and strings.
		wp_enqueue_script(
			'easy-php-settings-admin',
			plugin_dir_url( __FILE__ ) . 'js/admin.js',
			array( 'jquery' ),
			'1.0.2',
			true
		);
		wp_localize_script(
			'easy-php-settings-admin',
			'easy_php_settingsKeys',
			$this->settings_keys
		);
		wp_localize_script(
			'easy-php-settings-admin',
			'easy_php_settingsAdminVars',
			array(
				'copiedText'     => esc_html__( 'Copied to clipboard!', 'easy-php-settings' ),
				'testCompleted'  => esc_html__( 'Settings test completed. Check the Status tab for detailed information.', 'easy-php-settings' ),
				'noRowsSelected' => esc_html__( 'No rows selected.', 'easy-php-settings' ),
				'presets'        => $this->quick_presets,
				'tooltips'       => $this->setting_tooltips,
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'easy_php_settings_ajax_nonce' ),
			)
		);
	}

	/**
	 * Get Capability
	 *
	 * @return string
	 */
	public function get_capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Get Option
	 *
	 * @param string $key The option key.
	 * @param mixed  $default_value The default value.
	 * @return false|mixed|null
	 */
	public function get_option( $key, $default_value = false ) {
		return is_multisite() ? get_site_option( $key, $default_value ) : get_option( $key, $default_value );
	}

	/**
	 * Update Option
	 *
	 * @param string $key The option key.
	 * @param mixed  $value The option value.
	 * @return bool
	 */
	public function update_option( $key, $value ) {
		return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value );
	}

	/**
	 * Delete Option
	 *
	 * @param string $key The option key.
	 * @return bool
	 */
	public function delete_option( $key ) {
		return is_multisite() ? delete_site_option( $key ) : delete_option( $key );
	}

	/**
	 * Initialize tooltips
	 *
	 * @return void
	 */
	private function init_tooltips() {
		$this->setting_tooltips = array(
			'memory_limit'        => __( 'Maximum amount of memory a script may consume. Increase for large sites or complex operations.', 'easy-php-settings' ),
			'upload_max_filesize' => __( 'Maximum size of an uploaded file. Important for media uploads.', 'easy-php-settings' ),
			'post_max_size'       => __( 'Maximum size of POST data. Must be larger than upload_max_filesize.', 'easy-php-settings' ),
			'max_execution_time'  => __( 'Maximum time in seconds a script is allowed to run before it is terminated.', 'easy-php-settings' ),
			'max_input_vars'      => __( 'Maximum number of input variables accepted. Increase for large forms or page builders.', 'easy-php-settings' ),
			'wp_memory_limit'     => __( 'WordPress memory limit for normal operations.', 'easy-php-settings' ),
			'wp_max_memory_limit' => __( 'WordPress memory limit for admin operations (usually higher).', 'easy-php-settings' ),
		);
	}

	/**
	 * Initialize presets
	 *
	 * @return void
	 */
	private function init_presets() {
		$this->quick_presets = array(
			'default'     => array(
				'name'                => __( 'Default', 'easy-php-settings' ),
				'description'         => __( 'WordPress default values', 'easy-php-settings' ),
				'memory_limit'        => '128M',
				'upload_max_filesize' => '32M',
				'post_max_size'       => '64M',
				'max_execution_time'  => '30',
				'max_input_vars'      => '1000',
				'custom_php_ini'      => '; Additional PHP directives (optional)
session.gc_maxlifetime = 1440
log_errors = 1
date.timezone = UTC
max_file_uploads = 20
max_input_time = 60',
			),
			'performance' => array(
				'name'                => __( 'Performance Optimized', 'easy-php-settings' ),
				'description'         => __( 'Higher limits for busy sites', 'easy-php-settings' ),
				'memory_limit'        => '256M',
				'upload_max_filesize' => '128M',
				'post_max_size'       => '256M',
				'max_execution_time'  => '300',
				'max_input_vars'      => '10000',
				'custom_php_ini'      => '; Performance optimizations
session.gc_maxlifetime = 1440
log_errors = 1
date.timezone = UTC
max_file_uploads = 20
max_input_time = 120
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000',
			),
			'woocommerce' => array(
				'name'                => __( 'WooCommerce', 'easy-php-settings' ),
				'description'         => __( 'Optimized for e-commerce sites', 'easy-php-settings' ),
				'memory_limit'        => '256M',
				'upload_max_filesize' => '64M',
				'post_max_size'       => '128M',
				'max_execution_time'  => '180',
				'max_input_vars'      => '5000',
				'custom_php_ini'      => '; WooCommerce optimizations
session.gc_maxlifetime = 3600
log_errors = 1
date.timezone = UTC
max_file_uploads = 20
max_input_time = 90
session.cookie_lifetime = 3600',
			),
			'development' => array(
				'name'                => __( 'Development', 'easy-php-settings' ),
				'description'         => __( 'High limits for development environments', 'easy-php-settings' ),
				'memory_limit'        => '512M',
				'upload_max_filesize' => '256M',
				'post_max_size'       => '512M',
				'max_execution_time'  => '600',
				'max_input_vars'      => '10000',
				'custom_php_ini'      => '; Development settings
session.gc_maxlifetime = 1440
log_errors = 1
display_errors = 1
error_reporting = E_ALL
date.timezone = UTC
max_file_uploads = 50
max_input_time = 300
xdebug.max_nesting_level = 512',
			),
			'large_media' => array(
				'name'                => __( 'Large Media', 'easy-php-settings' ),
				'description'         => __( 'For sites handling large files', 'easy-php-settings' ),
				'memory_limit'        => '384M',
				'upload_max_filesize' => '512M',
				'post_max_size'       => '768M',
				'max_execution_time'  => '600',
				'max_input_vars'      => '5000',
				'custom_php_ini'      => '; Large file handling
session.gc_maxlifetime = 1440
log_errors = 1
date.timezone = UTC
max_file_uploads = 50
max_input_time = 300
post_max_size = 768M
upload_max_filesize = 512M',
			),
		);
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'PHP Settings Manager', 'easy-php-settings' ),
			__( 'Easy PHP Settings', 'easy-php-settings' ),
			$this->get_capability(),
			'easy-php-settings',
			array( $this, 'options_page_html' )
		);
	}

	/**
	 * Settings Init.
	 *
	 * @return void
	 */
	public function settings_init() {
		register_setting( 'easy_php_settings', 'easy_php_settings_settings', array( $this, 'sanitize_callback' ) );
		register_setting( 'easy_php_settings', 'easy_php_settings_wp_memory_settings', array( $this, 'sanitize_wp_memory_callback' ) );

		// Add PHP Settings section and fields.
		add_settings_section(
			'easy_php_settings_section',
			__( 'PHP Configuration Settings', 'easy-php-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Configure PHP settings to optimize your WordPress site performance.', 'easy-php-settings' ) . '</p>';
			},
			'easy_php_settings'
		);

		// Register fields for each PHP setting.
		$setting_labels = array(
			'memory_limit'        => __( 'Memory Limit', 'easy-php-settings' ),
			'upload_max_filesize' => __( 'Upload Max Filesize', 'easy-php-settings' ),
			'post_max_size'       => __( 'Post Max Size', 'easy-php-settings' ),
			'max_execution_time'  => __( 'Max Execution Time', 'easy-php-settings' ),
			'max_input_vars'      => __( 'Max Input Vars', 'easy-php-settings' ),
		);

		foreach ( $this->settings_keys as $key ) {
			add_settings_field(
				$key,
				$setting_labels[ $key ] ?? ucwords( str_replace( array( '_', '.' ), ' ', $key ) ),
				array( $this, 'render_setting_field' ),
				'easy_php_settings',
				'easy_php_settings_section',
				array( 'key' => $key )
			);
		}

		// Apply settings.
		$this->apply_settings();
	}

	/**
	 * Sanitize callback for settings.
	 *
	 * @param array $input The input array to sanitize.
	 * @return array The sanitized input array.
	 */
	public function sanitize_callback( $input ) {
		$old_input = $this->get_option( 'easy_php_settings_settings', array() );
		$new_input = array();

		foreach ( $this->settings_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$new_input[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		// Save custom php.ini textarea.
		if ( isset( $input['custom_php_ini'] ) ) {
			$new_input['custom_php_ini'] = trim( $input['custom_php_ini'] );
		}

		// Validate settings and show warnings.
		$this->validate_settings( $new_input );

		// Track history.
		Easy_Settings_History::add_entry( $old_input, $new_input, 'php_settings' );

		// Auto-generate configuration files when settings are saved.
		if ( ! empty( $new_input ) ) {
			$this->generate_config_files( $new_input );
		}

		return $new_input;
	}

	/**
	 * Sanitize callback for WordPress memory settings.
	 *
	 * @param array $input The input array to sanitize.
	 * @return array The sanitized input array.
	 */
	public function sanitize_wp_memory_callback( $input ) {
		$old_input = $this->get_option( 'easy_php_settings_wp_memory_settings', array() );
		$new_input = array();

		foreach ( $this->wp_memory_settings_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$new_input[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		// Track history.
		Easy_Settings_History::add_entry( $old_input, $new_input, 'wp_memory' );

		// Update wp-config.php with WordPress memory settings.
		if ( ! empty( $new_input ) ) {
			$this->update_wp_memory_constants( $new_input );
		}

		return $new_input;
	}

	/**
	 * Generate configuration files from settings.
	 *
	 * @param array $settings The settings array.
	 * @return void
	 */
	public function generate_config_files( $settings ) {
		// Generate .user.ini content from both regular and custom settings.
		$user_ini_content  = "; PHP Settings generated by Easy PHP Settings plugin\n";
		$user_ini_content .= "; Created By H M Shahadul Islam\n";
		$user_ini_content .= '; Generated on: ' . current_time( 'mysql' ) . "\n\n";

		foreach ( $settings as $key => $value ) {
			if ( in_array( $key, $this->settings_keys, true ) && ! empty( $value ) ) {
				$user_ini_content .= "$key = $value\n";
			}
		}

		// Append custom php.ini config.
		if ( ! empty( $settings['custom_php_ini'] ) ) {
			$user_ini_content .= "\n; Custom php.ini directives\n" . $settings['custom_php_ini'] . "\n";
		}

		// Write to files using the new INIFile class.
		$files_written = EasyIniFile::write( $user_ini_content );

		// Show success/error messages.
		if ( ! empty( $files_written ) ) {

			$message = sprintf(
			/* translators: %s: List of written INI file names. */
				__( 'Settings saved and written to: %s. Please restart your web server for changes to take effect.', 'easy-php-settings' ),
				implode( ', ', $files_written )
			);
			add_settings_error( 'easy_php_settings_settings', 'config_files_created', $message, 'updated' );
		} else {
			add_settings_error( 'easy_php_settings_settings', 'config_files_error', __( 'Settings saved, but could not write to INI files. Please check file permissions.', 'easy-php-settings' ), 'warning' );
		}
	}

	/**
	 * Handle INI file actions.
	 *
	 * @return void
	 */
	public function handle_ini_file_actions() {
		if ( isset( $_POST['easy_php_settings_delete_ini_files'] ) && check_admin_referer( 'easy_php_settings_delete_ini_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			$files_deleted = EasyIniFile::remove_files();

			if ( ! empty( $files_deleted ) ) {

				$message = sprintf(
				/* translators: %s: List of deleted INI file names. */
					__( 'Successfully deleted: %s.', 'easy-php-settings' ),
					implode( ', ', $files_deleted )
				);
				add_settings_error( 'easy_php_settings_settings', 'files_deleted_success', $message, 'success' );
			} else {
				add_settings_error( 'easy_php_settings_settings', 'files_deleted_error', __( 'Could not delete INI files. They may not exist or have permission issues.', 'easy-php-settings' ), 'warning' );
			}
		}
	}

	/**
	 * Render setting field.
	 *
	 * @param array $args The field arguments.
	 * @return void
	 */
	public function render_setting_field( $args ) {
		$options       = $this->get_option( 'easy_php_settings_settings' );
		$key           = $args['key'];
		$value         = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$current_value = ini_get( $key );
		$all_settings  = ini_get_all();

		// Settings are only changeable at runtime if their access level is INI_USER or INI_ALL.
		$access        = $all_settings[ $key ]['access'] ?? 0;
		$is_changeable = ( INI_USER === $access || INI_ALL === $access );

		echo "<input type='text' name='easy_php_settings_settings[" . esc_attr( $key ) . "]' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='" . esc_attr( $this->recommended_values[ $key ] ?? '' ) . "'>";

		$this->render_status_indicator( $key, $current_value );

		if ( ! $is_changeable ) {
			echo '<p class="description" style="color: #d63638;">' . esc_html__( '⚠ This setting cannot be changed at runtime. Use the configuration generator below.', 'easy-php-settings' ) . '</p>';
			$this->show_alternative_instructions( $key );
		}
	}



	/**
	 * Render WordPress memory setting field.
	 *
	 * @param array $args The field arguments.
	 * @return void
	 */
	public function render_wp_memory_field( $args ) {
		$options       = $this->get_option( 'easy_php_settings_wp_memory_settings' );
		$key           = $args['key'];
		$value         = isset( $options[ $key ] ) ? $options[ $key ] : '';
		$current_value = $this->get_wp_memory_value( $key );

		echo "<input type='text' name='easy_php_settings_wp_memory_settings[" . esc_attr( $key ) . "]' value='" . esc_attr( $value ) . "' class='regular-text' placeholder='" . esc_attr( $this->wp_memory_recommended_values[ $key ] ?? '' ) . "'>";

		$this->render_wp_memory_status_indicator( $key, $current_value );
	}

	/**
	 * Render status indicator.
	 *
	 * @param string $key The setting key.
	 * @param string $current_value The current value.
	 * @return void
	 */
	private function render_status_indicator( $key, $current_value ) {
		/* translators: %s: Current PHP value */
		$description = sprintf( esc_html__( 'Current value: %s', 'easy-php-settings' ), esc_html( $current_value ) );
		if ( isset( $this->recommended_values[ $key ] ) ) {
			$recommended_value_str = $this->recommended_values[ $key ];
			/* translators: %s: Recommended PHP value */
			$description .= sprintf( esc_html__( ' | Recommended: %s', 'easy-php-settings' ), esc_html( $recommended_value_str ) );

			// Convert values to bytes for comparison.
			$current_val_bytes     = $this->convert_to_bytes( $current_value );
			$recommended_val_bytes = $this->convert_to_bytes( $recommended_value_str );

			if ( $current_val_bytes < $recommended_val_bytes ) {
				$description .= ' <span style="color: red;">' . esc_html__( '(Low)', 'easy-php-settings' ) . '</span>';
			} else {
				$description .= ' <span style="color: green;">' . esc_html__( '(OK)', 'easy-php-settings' ) . '</span>';
			}
		}
		echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
	}

	/**
	 * Get WordPress memory value.
	 *
	 * @param string $key The memory setting key.
	 * @return string The current memory value.
	 */
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

	/**
	 * Render WordPress memory status indicator.
	 *
	 * @param string $key The setting key.
	 * @param string $current_value The current value.
	 * @return void
	 */
	private function render_wp_memory_status_indicator( $key, $current_value ) {
		/* translators: %s: Current WordPress memory value */
		$description = sprintf( esc_html__( 'Current value: %s', 'easy-php-settings' ), esc_html( $current_value ) );
		if ( isset( $this->wp_memory_recommended_values[ $key ] ) ) {
			$recommended_value_str = $this->wp_memory_recommended_values[ $key ];
			/* translators: %s: Recommended WordPress memory value */
			$description .= sprintf( esc_html__( ' | Recommended: %s', 'easy-php-settings' ), esc_html( $recommended_value_str ) );

			// Convert values to bytes for comparison.
			$current_val_bytes     = $this->convert_to_bytes( $current_value );
			$recommended_val_bytes = $this->convert_to_bytes( $recommended_value_str );

			if ( $current_val_bytes < $recommended_val_bytes ) {
				$description .= ' <span style="color: red;">' . esc_html__( '(Low)', 'easy-php-settings' ) . '</span>';
			} else {
				$description .= ' <span style="color: green;">' . esc_html__( '(OK)', 'easy-php-settings' ) . '</span>';
			}
		}
		echo '<p class="description">' . wp_kses_post( $description ) . '</p>';
	}

	/**
	 * Convert value to bytes.
	 *
	 * @param string $value The value to convert.
	 * @return int The value in bytes.
	 */
	private function convert_to_bytes( $value ) {
		$value = trim( $value );
		$last  = strtolower( $value[ strlen( $value ) - 1 ] );
		$value = (int) $value;
		switch ( $last ) {
			case 'g':
				$value *= 1024;
				// Fall through.
			case 'm':
				$value *= 1024;
				// Fall through.
			case 'k':
				$value *= 1024;
		}
		return $value;
	}

	/**
	 * Show alternative instructions.
	 *
	 * @param string $key The setting key.
	 * @return void
	 */
	private function show_alternative_instructions( $key ) {
		$server_api        = php_sapi_name();
		$recommended_value = $this->recommended_values[ $key ] ?? '128M';

		$output  = '<div style="background: #f9f9f9; padding: 10px; border-left: 4px solid #0073aa; margin: 10px 0;">';
		$output .= '<p style="margin: 0 0 10px 0; font-weight: bold; color: #0073aa;">' . esc_html__( 'Manual Configuration Required', 'easy-php-settings' ) . '</p>';
		$output .= '<p style="margin: 0 0 10px 0;">' . esc_html__( 'This setting requires server-level configuration. Choose one of the following methods:', 'easy-php-settings' ) . '</p>';

		// Method 1: .htaccess (Apache).
		if ( strpos( $server_api, 'apache' ) !== false || strpos( $server_api, 'cgi' ) !== false ) {
			$output .= '<div style="margin: 10px 0;">';
			$output .= '<strong>' . esc_html__( 'Method 1: .htaccess file', 'easy-php-settings' ) . '</strong><br/>';
			$output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">php_value ' . esc_html( $key ) . ' ' . esc_html( $recommended_value ) . '</code>';
			$output .= '</div>';
		}

		// Method 2: .user.ini.
		$output .= '<div style="margin: 10px 0;">';
		$output .= '<strong>' . esc_html__( 'Method 2: .user.ini file', 'easy-php-settings' ) . '</strong><br/>';
		$output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">' . esc_html( $key ) . ' = ' . esc_html( $recommended_value ) . '</code>';
		$output .= '</div>';

		// Method 3: php.ini.
		$output .= '<div style="margin: 10px 0;">';
		$output .= '<strong>' . esc_html__( 'Method 3: php.ini file', 'easy-php-settings' ) . '</strong><br/>';
		$output .= '<code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">' . esc_html( $key ) . ' = ' . esc_html( $recommended_value ) . '</code>';
		$output .= '</div>';

		$output .= '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">' . esc_html__( 'Note: After making changes, restart your web server or contact your hosting provider.', 'easy-php-settings' ) . '</p>';
		$output .= '</div>';

		echo wp_kses_post( $output );
	}

	/**
	 * Validate settings and show warnings
	 *
	 * @param array $settings The settings array to validate.
	 * @return void
	 */
	private function validate_settings( $settings ) {
		$warnings = array();

		// Check if post_max_size < upload_max_filesize.
		if ( isset( $settings['post_max_size'] ) && isset( $settings['upload_max_filesize'] ) ) {
			$post_max   = $this->convert_to_bytes( $settings['post_max_size'] );
			$upload_max = $this->convert_to_bytes( $settings['upload_max_filesize'] );
			if ( $post_max < $upload_max ) {
				$warnings[] = __( 'post_max_size should be larger than upload_max_filesize.', 'easy-php-settings' );
			}
		}

		// Check if memory_limit < post_max_size.
		if ( isset( $settings['memory_limit'] ) && isset( $settings['post_max_size'] ) ) {
			$memory_limit = $this->convert_to_bytes( $settings['memory_limit'] );
			$post_max     = $this->convert_to_bytes( $settings['post_max_size'] );
			if ( $memory_limit < $post_max ) {
				$warnings[] = __( 'memory_limit should be larger than post_max_size.', 'easy-php-settings' );
			}
		}

		// Check if max_execution_time is too low.
		if ( isset( $settings['max_execution_time'] ) && intval( $settings['max_execution_time'] ) < 30 ) {
			$warnings[] = __( 'max_execution_time is very low (less than 30 seconds) and may cause issues.', 'easy-php-settings' );
		}

		// Check if memory_limit is excessive.
		if ( isset( $settings['memory_limit'] ) ) {
			$memory_limit = $this->convert_to_bytes( $settings['memory_limit'] );
			if ( $memory_limit > 536870912 ) { // 512M in bytes.
				$warnings[] = __( 'memory_limit is very high (over 512M). This may be excessive unless you have a specific need.', 'easy-php-settings' );
			}
		}

		// Show warnings if any.
		foreach ( $warnings as $warning ) {
			add_settings_error( 'easy_php_settings_settings', 'validation_warning', $warning, 'warning' );
		}
	}

	/**
	 * Apply settings.
	 *
	 * @return void
	 */
	public function apply_settings() {
		$options = $this->get_option( 'easy_php_settings_settings' );
		if ( ! empty( $options ) && is_array( $options ) ) {
			$all_settings = ini_get_all();
			foreach ( $options as $key => $value ) {
				if ( in_array( $key, $this->settings_keys, true ) && ! empty( $value ) ) {

					// Settings are only changeable at runtime if their access level is INI_USER or INI_ALL.
					$access        = $all_settings[ $key ]['access'] ?? 0;
					$is_changeable = ( INI_USER === $access || INI_ALL === $access );

					if ( $is_changeable ) {
						$old_value = ini_get( $key );
						// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky -- Required for plugin functionality to test runtime PHP configuration changes.
						if ( @ini_set( $key, $value ) === false ) {
							add_action(
								'admin_notices',
								function () use ( $key ) {
									?>
								<div class="notice notice-warning is-dismissible">
									<p>
									<?php
										/* translators: %s: Name of the PHP setting */
										echo wp_kses_post( sprintf( esc_html__( 'Could not set %s. The setting might be disabled by your hosting provider.', 'easy-php-settings' ), '<strong>' . esc_html( $key ) . '</strong>' ) );
									?>
									</p>
								</div>
									<?php
								}
							);
						} else {
							add_action(
								'admin_notices',
								function () use ( $key, $value, $old_value ) {
									?>
								<div class="notice notice-success is-dismissible">
									<p>
									<?php
										/* translators: 1: Name of the PHP setting, 2: Old value, 3: New value */
										echo wp_kses_post( sprintf( esc_html__( 'Successfully changed %1$s from %2$s to %3$s.', 'easy-php-settings' ), '<strong>' . esc_html( $key ) . '</strong>', esc_html( $old_value ), esc_html( $value ) ) );
									?>
									</p>
								</div>
									<?php
								}
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Handle export and import actions
	 *
	 * @return void
	 */
	public function handle_export_import() {
		// Handle export.
		if ( isset( $_POST['easy_php_settings_export'] ) && check_admin_referer( 'easy_php_settings_export_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			$settings = array(
				'php_settings'    => $this->get_option( 'easy_php_settings_settings', array() ),
				'wp_memory'       => $this->get_option( 'easy_php_settings_wp_memory_settings', array() ),
				'plugin_version'  => $this->version,
				'export_time'     => current_time( 'mysql' ),
				'export_site_url' => get_site_url(),
			);

			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="easy-php-settings-' . gmdate( 'Y-m-d-His' ) . '.json"' );
			echo wp_json_encode( $settings, JSON_PRETTY_PRINT );
			exit;
		}

		// Handle import.
		if ( isset( $_POST['easy_php_settings_import'] ) && check_admin_referer( 'easy_php_settings_import_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['tmp_name'] ) ) {
				add_settings_error( 'easy_php_settings_settings', 'import_no_file', __( 'No file selected for import.', 'easy-php-settings' ), 'error' );
				return;
			}

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$json_data = $wp_filesystem->get_contents( $_FILES['import_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$settings  = json_decode( $json_data, true );

			if ( ! $settings || ! is_array( $settings ) ) {
				add_settings_error( 'easy_php_settings_settings', 'import_invalid', __( 'Invalid settings file format.', 'easy-php-settings' ), 'error' );
				return;
			}

			// Create backup before importing.
			$backup = array(
				'php_settings' => $this->get_option( 'easy_php_settings_settings', array() ),
				'wp_memory'    => $this->get_option( 'easy_php_settings_wp_memory_settings', array() ),
			);
			$this->update_option( 'easy_php_settings_import_backup', $backup );

			// Import settings.
			if ( isset( $settings['php_settings'] ) ) {
				$this->update_option( 'easy_php_settings_settings', $settings['php_settings'] );
			}
			if ( isset( $settings['wp_memory'] ) ) {
				$this->update_option( 'easy_php_settings_wp_memory_settings', $settings['wp_memory'] );
			}

			add_settings_error( 'easy_php_settings_settings', 'import_success', __( 'Settings imported successfully. A backup of your previous settings was created.', 'easy-php-settings' ), 'updated' );
		}
	}

	/**
	 * Handle reset actions
	 *
	 * @return void
	 */
	public function handle_reset_actions() {
		// Reset to recommended values.
		if ( isset( $_POST['easy_php_settings_reset_recommended'] ) && check_admin_referer( 'easy_php_settings_reset_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			// Create backup.
			$backup = $this->get_option( 'easy_php_settings_settings', array() );
			$this->update_option( 'easy_php_settings_reset_backup', $backup );

			// Set recommended values.
			$this->update_option( 'easy_php_settings_settings', $this->recommended_values );

			add_settings_error( 'easy_php_settings_settings', 'reset_success', __( 'Settings reset to recommended values. A backup was created.', 'easy-php-settings' ), 'updated' );
		}

		// Reset to server defaults.
		if ( isset( $_POST['easy_php_settings_reset_default'] ) && check_admin_referer( 'easy_php_settings_reset_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			// Create backup.
			$backup = $this->get_option( 'easy_php_settings_settings', array() );
			$this->update_option( 'easy_php_settings_reset_backup', $backup );

			// Clear all settings.
			$this->delete_option( 'easy_php_settings_settings' );

			add_settings_error( 'easy_php_settings_settings', 'reset_default_success', __( 'Settings cleared. Server defaults will now apply. A backup was created.', 'easy-php-settings' ), 'updated' );
		}
	}

	/**
	 * Handle history actions
	 *
	 * @return void
	 */
	public function handle_history_actions() {
		// Export history as CSV.
		if ( isset( $_POST['easy_php_settings_export_history'] ) && check_admin_referer( 'easy_php_settings_history_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			$csv = Easy_Settings_History::export_as_csv();
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="easy-php-settings-history-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
			echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// Clear history.
		if ( isset( $_POST['easy_php_settings_clear_history'] ) && check_admin_referer( 'easy_php_settings_history_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			Easy_Settings_History::clear_history();
			add_settings_error( 'easy_php_settings_settings', 'history_cleared', __( 'History cleared successfully.', 'easy-php-settings' ), 'updated' );
		}

		// Restore from history.
		if ( isset( $_POST['easy_php_settings_restore_history'] ) && isset( $_POST['history_index'] ) && check_admin_referer( 'easy_php_settings_history_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			$index = intval( $_POST['history_index'] );
			$entry = Easy_Settings_History::get_entry( $index );

			if ( $entry ) {
				// Build settings from the old values in the history entry.
				$restored_settings = array();
				foreach ( $entry['changes'] as $key => $change ) {
					$restored_settings[ $key ] = $change['old'];
				}

				if ( 'php_settings' === $entry['setting_type'] ) {
					$this->update_option( 'easy_php_settings_settings', $restored_settings );
					add_settings_error( 'easy_php_settings_settings', 'restore_success', __( 'Settings restored from history successfully.', 'easy-php-settings' ), 'updated' );
				}
			} else {
				add_settings_error( 'easy_php_settings_settings', 'restore_failed', __( 'Failed to restore settings from history.', 'easy-php-settings' ), 'error' );
			}
		}
	}

	/**
	 * Options page HTML.
	 *
	 * @return void
	 */
	public function options_page_html() {
		if ( ! current_user_can( $this->get_capability() ) ) {
			return;
		}

		$active_tab = 'general_settings';
		$nonce      = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : null;
		if ( isset( $_GET['tab'] ) && wp_verify_nonce( $nonce, 'easy_php_settings_tab_nonce' ) ) {
			$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
		}

		$tab_nonce_url = wp_create_nonce( 'easy_php_settings_tab_nonce' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=easy-php-settings&tab=general_settings&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'general_settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General Settings', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=debugging&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'debugging' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Debugging', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=php_settings&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'php_settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'PHP Settings', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=extensions&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'extensions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Extensions', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=status&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'status' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Status', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=history&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'history' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'History', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=tools&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tools', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=log_viewer&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'log_viewer' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Log Viewer', 'easy-php-settings' ); ?></a>
				<a href="?page=easy-php-settings&tab=pro&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>" class="nav-tab <?php echo 'pro' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Pro', 'easy-php-settings' ); ?></a>
			</h2>

			<?php if ( 'general_settings' === $active_tab ) : ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'easy_php_settings' );
				$options           = $this->get_option( 'easy_php_settings_settings' );
				$wp_memory_options = $this->get_option( 'easy_php_settings_wp_memory_settings' );
				?>
				<div class="easy-php-settings-preset-box">
					<h3>
						<span class="dashicons dashicons-admin-settings" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Quick Configuration Presets', 'easy-php-settings' ); ?>
					</h3>
					<p style="margin-bottom: 12px; color: #646970;">
						<?php esc_html_e( 'Select a pre-configured optimization profile to instantly apply recommended settings for your specific use case.', 'easy-php-settings' ); ?>
					</p>
					<label for="easy_php_settings_preset" style="font-weight: 600; display: block; margin-bottom: 8px;">
						<?php esc_html_e( 'Choose a Preset:', 'easy-php-settings' ); ?>
					</label>
					<select id="easy_php_settings_preset">
						<option value=""><?php esc_html_e( '-- Select a Preset Configuration --', 'easy-php-settings' ); ?></option>
						<?php foreach ( $this->quick_presets as $preset_key => $preset_data ) : ?>
							<option value="<?php echo esc_attr( $preset_key ); ?>">
								<?php echo esc_html( $preset_data['name'] ); ?> &mdash; <?php echo esc_html( $preset_data['description'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="easy-php-settings-config-box">
					<h3>
						<span class="dashicons dashicons-editor-code" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Custom PHP Configuration', 'easy-php-settings' ); ?>
					</h3>
					<p style="margin-bottom: 12px; color: #646970;">
						<?php esc_html_e( 'Add any additional PHP directives here. These will be included in the generated .user.ini and php.ini files.', 'easy-php-settings' ); ?>
					</p>
					<textarea name="easy_php_settings_settings[custom_php_ini]" id="easy_php_settings_custom_php_ini" rows="10" style="width:100%;" placeholder="; Add any custom PHP directives here
; Examples:
session.gc_maxlifetime = 1440
log_errors = 1
date.timezone = UTC
max_file_uploads = 20
max_input_time = 60
display_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED"><?php echo isset( $options['custom_php_ini'] ) ? esc_textarea( $options['custom_php_ini'] ) : ''; ?></textarea>
					<p class="description" style="margin-top: 10px;">
						<span class="dashicons dashicons-info" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Use this section for additional PHP directives such as session management, timezone configuration, error logging, and file upload settings.', 'easy-php-settings' ); ?>
					</p>
				</div>

				<div class="easy-php-settings-config-box">
					<h3>
						<span class="dashicons dashicons-performance" style="color: #2271b1;"></span>
						<?php esc_html_e( 'Core PHP Settings', 'easy-php-settings' ); ?>
					</h3>
					<p style="margin-bottom: 12px; color: #646970;">
						<?php esc_html_e( 'Configure the essential PHP settings that affect WordPress performance and functionality.', 'easy-php-settings' ); ?>
					</p>
					<?php do_settings_sections( 'easy_php_settings' ); ?>
				</div>

				<div class="easy-php-settings-config-box">
					<h3>
						<span class="dashicons dashicons-wordpress" style="color: #2271b1;"></span>
						<?php esc_html_e( 'WordPress Memory Configuration', 'easy-php-settings' ); ?>
					</h3>
					<p style="margin-bottom: 12px; color: #646970;">
						<?php esc_html_e( 'Configure WordPress-specific memory limits. These constants will be added to your wp-config.php file and control the memory allocated to WordPress operations.', 'easy-php-settings' ); ?>
					</p>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="wp_memory_limit"><?php esc_html_e( 'WP_MEMORY_LIMIT', 'easy-php-settings' ); ?></label>
							</th>
							<td>
								<?php $this->render_wp_memory_field( array( 'key' => 'wp_memory_limit' ) ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wp_max_memory_limit"><?php esc_html_e( 'WP_MAX_MEMORY_LIMIT', 'easy-php-settings' ); ?></label>
							</th>
							<td>
								<?php $this->render_wp_memory_field( array( 'key' => 'wp_max_memory_limit' ) ); ?>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save All Settings', 'easy-php-settings' ) ); ?>
			</form>



			<form action="" method="post" style="margin-top: 20px;">
				<?php wp_nonce_field( 'easy_php_settings_delete_ini_nonce' ); ?>
				<button type="submit" name="easy_php_settings_delete_ini_files" class="button button-danger" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete the .user.ini and php.ini files created by this plugin?', 'easy-php-settings' ) ); ?>');">
					<?php esc_html_e( 'Delete .ini Files', 'easy-php-settings' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'This will remove the .user.ini and php.ini files from your WordPress root directory.', 'easy-php-settings' ); ?></p>
			</form>
			
			<div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
				<h3><?php esc_html_e( 'Configuration Generator', 'easy-php-settings' ); ?></h3>
				<p><?php esc_html_e( 'Generate server configuration files with your custom values:', 'easy-php-settings' ); ?></p>
				<button type="button" id="generate-config" class="button button-primary"><?php esc_html_e( 'Generate Configuration Files', 'easy-php-settings' ); ?></button>
				
				<div id="config-output" style="margin-top: 20px; display: none;">
					<h4><?php esc_html_e( 'Generated Configuration Files:', 'easy-php-settings' ); ?></h4>
					
					<div style="margin: 15px 0;">
						<h5><?php esc_html_e( '.user.ini file:', 'easy-php-settings' ); ?></h5>
						<textarea id="user-ini-content" style="width: 100%; height: 200px; font-family: monospace; background: #fff; padding: 10px;" readonly></textarea>
						<button type="button" class="button button-secondary" onclick="copyToClipboard('user-ini-content')"><?php esc_html_e( 'Copy to Clipboard', 'easy-php-settings' ); ?></button>
					</div>
					
					<div style="margin: 15px 0;">
						<h5><?php esc_html_e( 'Instructions:', 'easy-php-settings' ); ?></h5>
						<ol>
							<li><?php esc_html_e( 'Copy the .user.ini content and save it as ".user.ini" in your WordPress root directory', 'easy-php-settings' ); ?></li>
							<li><?php esc_html_e( 'Or copy the .htaccess content and add it to your existing .htaccess file', 'easy-php-settings' ); ?></li>
							<li><?php esc_html_e( 'Restart your web server or contact your hosting provider', 'easy-php-settings' ); ?></li>
						</ol>
					</div>
				</div>
			</div>
			
			<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
				<h3><?php esc_html_e( 'Test Settings', 'easy-php-settings' ); ?></h3>
				<p><?php esc_html_e( 'Click the button below to test if your current settings can be modified at runtime:', 'easy-php-settings' ); ?></p>
				<button type="button" id="test-settings" class="button button-secondary"><?php esc_html_e( 'Test Settings', 'easy-php-settings' ); ?></button>
				<div id="test-results" style="margin-top: 10px;"></div>
			</div>
			<?php elseif ( 'debugging' === $active_tab ) : ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'easy_php_settings_debugging' );
				do_settings_sections( 'easy_php_settings_debugging' );
				submit_button( __( 'Save Debugging Settings', 'easy-php-settings' ) );
				?>
			</form>
			<?php elseif ( 'extensions' === $active_tab ) : ?>
				<?php $this->render_extensions_tab(); ?>
			<?php elseif ( 'history' === $active_tab ) : ?>
				<?php $this->render_history_tab(); ?>
			<?php elseif ( 'tools' === $active_tab ) : ?>
				<?php $this->render_tools_tab(); ?>
			<?php elseif ( 'log_viewer' === $active_tab ) : ?>
				<?php $this->render_log_viewer_tab(); ?>
			<?php elseif ( 'status' === $active_tab ) : ?>
				<?php $this->render_status_tab(); ?>
			<?php elseif ( 'php_settings' === $active_tab ) : ?>
				<div id="php-settings-tab">
					<h3><?php esc_html_e( 'PHP Settings Table', 'easy-php-settings' ); ?></h3>
					<div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
						<input type="text" id="php-settings-search" placeholder="<?php esc_attr_e( 'Search for directives...', 'easy-php-settings' ); ?>" style="min-width: 250px; padding: 5px;" />
						<button type="button" class="button button-secondary" id="php-settings-copy-selected"><?php esc_html_e( 'Copy Selected', 'easy-php-settings' ); ?></button>
					</div>
					<div id="php-settings-table-wrapper">
						<?php
						if ( class_exists( 'EasyPHPInfo' ) ) {
							echo EasyPHPInfo::render( 'Core' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo '<p>' . esc_html__( 'PHPInfo class not found.', 'easy-php-settings' ) . '</p>';
						}
						?>
					</div>
					<p style="margin-top: 10px; color: #666;"><em><?php esc_html_e( 'Tip: Use the search box to filter settings. Select rows and click "Copy Selected" to copy them to your clipboard.', 'easy-php-settings' ); ?></em></p>
				</div>
			<?php elseif ( 'pro' === $active_tab ) : ?>
				<?php $this->render_pro_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render log viewer tab.
	 *
	 * @return void
	 */
	public function render_log_viewer_tab() {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		?>
		<div id="log-viewer-tab">
			<h3><?php esc_html_e( 'Debug Log Viewer', 'easy-php-settings' ); ?></h3>
			<?php
			if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html__( 'WP_DEBUG_LOG is not enabled. To use the log viewer, please enable it from the Debugging tab.', 'easy-php-settings' )
				);
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
				echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug log file does not exist. It will be created when errors are logged.', 'easy-php-settings' ) . '</p></div>';
			}
			?>
			
			<form method="post" style="margin-bottom: 15px;">
				<?php wp_nonce_field( 'easy_php_settings_clear_log_nonce' ); ?>
				<input type="submit" name="easy_php_settings_clear_log" class="button button-danger" value="<?php esc_attr_e( 'Clear Log File', 'easy-php-settings' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete the debug log?', 'easy-php-settings' ) ); ?>');">
			</form>

			<textarea id="easy_php_settings-log-viewer" style="width: 100%; height: 500px; font-family: monospace;"><?php echo esc_textarea( $log_content ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render status tab.
	 *
	 * @return void
	 */
	public function render_status_tab() {
		$all_settings = ini_get_all();
		global $wpdb;

		$db_version  = $wpdb->db_version();
		$db_software = 'MySQL';
		if ( strpos( strtolower( $db_version ), 'mariadb' ) !== false ) {
			$db_software = 'MariaDB';
		}

		$server_info = array(
			'Server Software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
			'Server IP'         => isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : 'N/A',
			'PHP Version'       => phpversion(),
			'WordPress Version' => get_bloginfo( 'version' ),
			'Database Software' => $db_software,
			'Database Version'  => $db_version,
			'Server API'        => php_sapi_name(),
		);
		?>
		<div id="status-tab">
			<h3><?php esc_html_e( 'PHP Configuration Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current Value', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Recommended', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Changeable', 'easy-php-settings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $this->settings_keys as $key ) :
						$current_value     = ini_get( $key );
						$recommended_value = $this->recommended_values[ $key ] ?? 'N/A';
						$access            = $all_settings[ $key ]['access'] ?? 0;
						$is_changeable     = ( INI_USER === $access || INI_ALL === $access );
						?>
					<tr>
						<td><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></strong></td>
						<td><?php echo esc_html( $current_value ); ?></td>
						<td><?php echo esc_html( $recommended_value ); ?></td>
						<td>
							<?php if ( $is_changeable ) : ?>
								<span style="color: green;"><?php esc_html_e( 'Yes', 'easy-php-settings' ); ?></span>
							<?php else : ?>
								<span style="color: red;"><?php esc_html_e( 'No', 'easy-php-settings' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3 style="margin-top: 30px;"><?php esc_html_e( 'WordPress Memory Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Current Value', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Recommended', 'easy-php-settings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->wp_memory_settings_keys as $key ) : ?>
					<tr>
						<td><strong><?php echo esc_html( strtoupper( $key ) ); ?></strong></td>
						<td><?php echo esc_html( $this->get_wp_memory_value( $key ) ); ?></td>
						<td><?php echo esc_html( $this->wp_memory_recommended_values[ $key ] ?? 'N/A' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3 style="margin-top: 30px;"><?php esc_html_e( 'Server Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Metric', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'easy-php-settings' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $server_info as $metric => $value ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $metric ); ?></strong></td>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Pro tab.
	 *
	 * @return void
	 */
	public function render_pro_tab() {
		?>
		<div id="pro-tab" style="max-width: 980px;">
			<div style="display:flex; align-items:center; justify-content: space-between; gap: 12px; margin-bottom: 12px;">
				<h3 style="margin:0;">
					<?php echo esc_html__( 'Easy PHP Settings – Pro', 'easy-php-settings' ); ?>
				</h3>
				<a class="button button-primary" target="_blank" rel="noopener" href="https://github.com/easy-php-settings">
					<?php echo esc_html__( 'Get Pro', 'easy-php-settings' ); ?>
				</a>
			</div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Advanced PHP & Server Controls', 'easy-php-settings' ); ?></h2>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'Manage all PHP INI directives (memory, upload, post size, execution time, input vars, OPcache, sessions, error_reporting).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Advanced Config Generator (Apache .htaccess, NGINX snippets, cPanel/LiteSpeed compatibility).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Per-site overrides in Multisite (instead of only Network Admin).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'PHP Extension Checker → Detects missing extensions (imagick, intl, bcmath, etc.) and gives install guidance.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Real-time Server Health Monitor → CPU, RAM, disk usage, PHP-FPM pool stats.', 'easy-php-settings' ); ?></li>
				</ul>
			</div>

			<div class="card" style="padding:20px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Optimization & Performance', 'easy-php-settings' ); ?></h2>
				<p><strong><?php echo esc_html__( 'One-click Optimization Profiles (ready presets):', 'easy-php-settings' ); ?></strong></p>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'WooCommerce Stores', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Elementor / Page Builders', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'LMS (LearnDash, TutorLMS)', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'High Traffic Blogs', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Multisite Networks', 'easy-php-settings' ); ?></li>
				</ul>
				<ul class="ul-disc" style="margin-top:8px;">
					<li><?php echo esc_html__( 'Smart Recommendations → Suggest best values based on your hosting/server.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'OPcache Manager → Enable/disable and tune OPcache.', 'easy-php-settings' ); ?></li>
				</ul>
			</div>

			<div class="card" style="padding:20px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Safety & Reliability', 'easy-php-settings' ); ?></h2>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'Backup & Restore Configurations (before/after editing .user.ini & php.ini).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Safe Mode → If wrong values break the site, plugin auto-rolls back to last working config.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Error Log Viewer → View PHP error logs and debug logs directly from dashboard.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Email Alerts & Notifications → Sends warnings if PHP limits are too low, or site hits memory/time limits.', 'easy-php-settings' ); ?></li>
				</ul>
			</div>

			<div class="card" style="padding:20px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Productivity & Agency Tools', 'easy-php-settings' ); ?></h2>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'Import / Export Settings → Save your preferred config and apply on other sites.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Multi-Site Templates → Apply one config across the network.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'White-label Option → Rebrand plugin for agencies (hide “Easy PHP Settings” branding).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Role-based Access → Allow only specific roles (like Admins, Developers) to change PHP settings.', 'easy-php-settings' ); ?></li>
				</ul>
			</div>

			<div class="card" style="padding:20px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Premium Experience', 'easy-php-settings' ); ?></h2>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'Priority Support (faster replies, email/ticket).', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Regular Pro Updates with new hosting compatibility.', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Advanced Documentation & Tutorials (step-by-step setup guides).', 'easy-php-settings' ); ?></li>
				</ul>
			</div>

			<div class="card" style="padding:20px; margin-top:16px;">
				<h2 style="margin-top:0;"><?php echo esc_html__( 'Summary (Pro Highlights)', 'easy-php-settings' ); ?></h2>
				<ul class="ul-disc">
					<li><?php echo esc_html__( 'Advanced Settings (all directives, OPcache, sessions)', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Profiles (WooCommerce, LMS, high traffic, etc.)', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Monitoring (server health, error logs)', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Backup/Restore + Safe Mode', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Import/Export & Agency Tools', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Alerts & Notifications', 'easy-php-settings' ); ?></li>
					<li><?php echo esc_html__( 'Premium Support', 'easy-php-settings' ); ?></li>
				</ul>
				<p>
					<a class="button button-primary" target="_blank" rel="noopener" href="https://github.com/easy-php-settings"><?php echo esc_html__( 'Upgrade to Pro', 'easy-php-settings' ); ?></a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Extensions tab.
	 *
	 * @return void
	 */
	public function render_extensions_tab() {
		$categorized = Easy_Extensions_Viewer::get_categorized_extensions();
		$missing     = Easy_Extensions_Viewer::get_critical_missing_extensions();
		$recommended = Easy_Extensions_Viewer::get_recommended_extensions();
		?>
		<div id="extensions-tab">
			<h3><?php esc_html_e( 'PHP Extensions', 'easy-php-settings' ); ?></h3>

			<?php if ( ! empty( $missing ) ) : ?>
			<div class="notice notice-error" style="padding: 10px; margin: 20px 0;">
				<h4 style="margin-top: 0;"><?php esc_html_e( 'Critical Missing Extensions', 'easy-php-settings' ); ?></h4>
				<ul>
					<?php foreach ( $missing as $ext => $desc ) : ?>
					<li><strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div style="margin-bottom: 20px;">
				<input type="text" id="extensions-search" placeholder="<?php esc_attr_e( 'Search extensions...', 'easy-php-settings' ); ?>" style="min-width: 250px; padding: 5px;" />
			</div>

			<?php foreach ( $categorized as $category => $extensions ) : ?>
			<div style="margin-bottom: 30px;">
				<h4><?php echo esc_html( $category ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Extension Name', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Version', 'easy-php-settings' ); ?></th>
						</tr>
					</thead>
					<tbody class="extensions-list">
						<?php foreach ( $extensions as $extension ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $extension ); ?></strong></td>
							<td><span style="color: green; font-weight: bold;">✓ <?php esc_html_e( 'Loaded', 'easy-php-settings' ); ?></span></td>
							<td><?php echo esc_html( Easy_Extensions_Viewer::get_extension_version( $extension ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>

			<div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa;">
				<h4><?php esc_html_e( 'Recommended Extensions', 'easy-php-settings' ); ?></h4>
				<ul>
					<?php foreach ( $recommended as $ext => $desc ) : ?>
					<li>
						<strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?>
						<?php if ( Easy_Extensions_Viewer::is_loaded( $ext ) ) : ?>
							<span style="color: green;">✓ <?php esc_html_e( 'Installed', 'easy-php-settings' ); ?></span>
						<?php else : ?>
							<span style="color: orange;">⚠ <?php esc_html_e( 'Not Installed', 'easy-php-settings' ); ?></span>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render History tab.
	 *
	 * @return void
	 */
	public function render_history_tab() {
		$history = Easy_Settings_History::get_history();
		?>
		<div id="history-tab">
			<h3><?php esc_html_e( 'Settings Change History', 'easy-php-settings' ); ?></h3>
			<p><?php esc_html_e( 'Track all changes made to your PHP and WordPress settings. You can restore previous configurations if needed.', 'easy-php-settings' ); ?></p>

			<div style="margin-bottom: 20px;">
				<form method="post" style="display: inline-block; margin-right: 10px;">
					<?php wp_nonce_field( 'easy_php_settings_history_nonce' ); ?>
					<input type="submit" name="easy_php_settings_export_history" class="button button-secondary" value="<?php esc_attr_e( 'Export as CSV', 'easy-php-settings' ); ?>">
				</form>
				<form method="post" style="display: inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all history?', 'easy-php-settings' ) ); ?>');">
					<?php wp_nonce_field( 'easy_php_settings_history_nonce' ); ?>
					<input type="submit" name="easy_php_settings_clear_history" class="button button-danger" value="<?php esc_attr_e( 'Clear History', 'easy-php-settings' ); ?>">
				</form>
			</div>

			<?php if ( empty( $history ) ) : ?>
				<p><?php esc_html_e( 'No changes have been recorded yet. History will appear here after you save settings.', 'easy-php-settings' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date & Time', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'User', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Changes', 'easy-php-settings' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'easy-php-settings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $history as $index => $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['timestamp'] ); ?></td>
							<td><?php echo esc_html( $entry['user_login'] ); ?></td>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['setting_type'] ) ) ); ?></td>
							<td>
								<details>
									<summary><?php echo esc_html( count( $entry['changes'] ) ); ?> <?php esc_html_e( 'settings changed', 'easy-php-settings' ); ?></summary>
									<ul style="margin: 5px 0; padding-left: 20px;">
										<?php foreach ( $entry['changes'] as $key => $change ) : ?>
										<li><strong><?php echo esc_html( $key ); ?>:</strong> <?php echo esc_html( $change['old'] ); ?> → <?php echo esc_html( $change['new'] ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							</td>
							<td>
								<form method="post" style="display: inline;">
									<?php wp_nonce_field( 'easy_php_settings_history_nonce' ); ?>
									<input type="hidden" name="history_index" value="<?php echo esc_attr( $index ); ?>">
									<input type="submit" name="easy_php_settings_restore_history" class="button button-small" value="<?php esc_attr_e( 'Restore', 'easy-php-settings' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to restore these settings?', 'easy-php-settings' ) ); ?>');">
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Tools tab.
	 *
	 * @return void
	 */
	public function render_tools_tab() {
		?>
		<div id="tools-tab">
			<div class="easy-php-info-box">
				<p>
					<span class="dashicons dashicons-admin-tools" style="vertical-align: middle;"></span>
					<strong><?php esc_html_e( 'Backup & Migration Tools', 'easy-php-settings' ); ?></strong> &mdash; 
					<?php esc_html_e( 'Use these tools to backup, restore, and migrate your PHP configuration settings between WordPress installations.', 'easy-php-settings' ); ?>
				</p>
			</div>

			<div class="easy-php-settings-tool-section">
				<h4>
					<span class="dashicons dashicons-download" style="color: #2271b1;"></span>
					<?php esc_html_e( 'Export Configuration', 'easy-php-settings' ); ?>
				</h4>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php esc_html_e( 'Download your current PHP and WordPress settings as a JSON file. Use this to create backups or migrate configurations to other sites.', 'easy-php-settings' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'easy_php_settings_export_nonce' ); ?>
					<button type="submit" name="easy_php_settings_export" class="button button-primary">
						<span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: 3px;"></span>
						<?php esc_html_e( 'Export Settings', 'easy-php-settings' ); ?>
					</button>
				</form>
			</div>

			<div class="easy-php-settings-tool-section">
				<h4>
					<span class="dashicons dashicons-upload" style="color: #2271b1;"></span>
					<?php esc_html_e( 'Import Configuration', 'easy-php-settings' ); ?>
				</h4>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php esc_html_e( 'Import settings from a previously exported JSON file. Your current settings will be automatically backed up before import.', 'easy-php-settings' ); ?>
				</p>
				<div class="easy-php-info-box" style="background: #fff3cd; border-left-color: #f0ad4e; margin-bottom: 15px;">
					<p style="margin: 0; color: #646970;">
						<span class="dashicons dashicons-warning" style="color: #f0ad4e;"></span>
						<?php esc_html_e( 'Importing will overwrite your current settings. A backup will be created automatically.', 'easy-php-settings' ); ?>
					</p>
				</div>
				<form method="post" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
					<?php wp_nonce_field( 'easy_php_settings_import_nonce' ); ?>
					<input type="file" name="import_file" accept=".json" required style="flex: 1; min-width: 250px;">
					<button type="submit" name="easy_php_settings_import" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'This will overwrite your current settings. A backup will be created. Continue?', 'easy-php-settings' ) ); ?>');">
						<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-top: 3px;"></span>
						<?php esc_html_e( 'Import Settings', 'easy-php-settings' ); ?>
					</button>
				</form>
			</div>

			<div class="easy-php-settings-tool-section easy-php-settings-warning-box">
				<h4>
					<span class="dashicons dashicons-update" style="color: #f0ad4e;"></span>
					<?php esc_html_e( 'Reset Configuration', 'easy-php-settings' ); ?>
				</h4>
				<p style="color: #646970; margin-bottom: 15px;">
					<?php esc_html_e( 'Reset your PHP settings to recommended values or clear all customizations. A backup will be created automatically before any reset operation.', 'easy-php-settings' ); ?>
				</p>
				<div style="display: flex; gap: 10px; flex-wrap: wrap;">
					<form method="post">
						<?php wp_nonce_field( 'easy_php_settings_reset_nonce' ); ?>
						<button type="submit" name="easy_php_settings_reset_recommended" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Reset all settings to recommended values? Your current configuration will be backed up.', 'easy-php-settings' ) ); ?>');">
							<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-top: 3px;"></span>
							<?php esc_html_e( 'Reset to Recommended', 'easy-php-settings' ); ?>
						</button>
					</form>
					<form method="post">
						<?php wp_nonce_field( 'easy_php_settings_reset_nonce' ); ?>
						<button type="submit" name="easy_php_settings_reset_default" class="button button-danger" onclick="return confirm('<?php echo esc_js( __( 'This will clear ALL custom settings and revert to server defaults. Your current configuration will be backed up. This action should only be used if you want to start fresh. Continue?', 'easy-php-settings' ) ); ?>');">
							<span class="dashicons dashicons-dismiss" style="vertical-align: middle; margin-top: 3px;"></span>
							<?php esc_html_e( 'Reset to Server Defaults', 'easy-php-settings' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Debugging settings init.
	 *
	 * @return void
	 */
	public function debugging_settings_init() {
		register_setting( 'easy_php_settings_debugging', 'easy_php_settings_debugging_settings', array( $this, 'update_wp_config_constants' ) );

		add_settings_section(
			'easy_php_settings_debugging_section',
			__( 'Debugging Constants', 'easy-php-settings' ),
			function () {
				echo '<p>' . esc_html__( 'Control WordPress debugging constants defined in wp-config.php.', 'easy-php-settings' ) . '</p>';
			},
			'easy_php_settings_debugging'
		);

		add_settings_field(
			'wp_debug',
			'WP_DEBUG',
			array( $this, 'render_debugging_field' ),
			'easy_php_settings_debugging',
			'easy_php_settings_debugging_section',
			array( 'name' => 'wp_debug' )
		);
		add_settings_field(
			'wp_debug_log',
			'WP_DEBUG_LOG',
			array( $this, 'render_debugging_field' ),
			'easy_php_settings_debugging',
			'easy_php_settings_debugging_section',
			array( 'name' => 'wp_debug_log' )
		);
		add_settings_field(
			'wp_debug_display',
			'WP_DEBUG_DISPLAY',
			array( $this, 'render_debugging_field' ),
			'easy_php_settings_debugging',
			'easy_php_settings_debugging_section',
			array( 'name' => 'wp_debug_display' )
		);
		add_settings_field(
			'script_debug',
			'SCRIPT_DEBUG',
			array( $this, 'render_debugging_field' ),
			'easy_php_settings_debugging',
			'easy_php_settings_debugging_section',
			array( 'name' => 'script_debug' )
		);
	}

	/**
	 * Check if a constant is defined.
	 *
	 * @param string $constant The constant name.
	 * @return bool True if defined, false otherwise.
	 */
	public function is_constant_defined( $constant ) {
		return defined( $constant ) && constant( $constant );
	}

	/**
	 * Render debugging field.
	 *
	 * @param array $args The field arguments.
	 * @return void
	 */
	public function render_debugging_field( $args ) {
		$name        = $args['name'];
		$is_defined  = $this->is_constant_defined( strtoupper( $name ) );
		$is_disabled = ( 'wp_debug' !== $name && ! $this->is_constant_defined( 'WP_DEBUG' ) );

		$html  = '<label class="switch">';
		$html .= '<input type="checkbox" name="easy_php_settings_debugging_settings[' . esc_attr( $name ) . ']" value="1" ' . checked( 1, $is_defined, false ) . ' ' . disabled( $is_disabled, true, false ) . '>';
		$html .= '<span class="slider round"></span>';
		$html .= '</label>';

		echo wp_kses(
			$html,
			array(
				'label' => array( 'class' => array() ),
				'input' => array(
					'type'     => array(),
					'name'     => array(),
					'value'    => array(),
					'checked'  => array(),
					'disabled' => array(),
				),
				'span'  => array( 'class' => array() ),
			)
		);
	}

	/**
	 * Update WordPress config constants.
	 *
	 * @param array $input The input array.
	 * @return array The updated options.
	 */
	public function update_wp_config_constants( $input ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->is_writable( $config_path ) ) {
			add_settings_error( 'easy_php_settings_debugging_settings', 'config_not_writable', __( 'wp-config.php is not writable.', 'easy-php-settings' ), 'error' );
			return get_option( 'easy_php_settings_debugging_settings' );
		}

		$config_content = $wp_filesystem->get_contents( $config_path );
		$constants      = array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG' );

		$new_options = get_option( 'easy_php_settings_debugging_settings', array() );

		foreach ( $constants as $const ) {
			$key   = strtolower( $const );
			$value = isset( $input[ $key ] ) ? 'true' : 'false';

			if ( preg_match( '/define\(\s*\'' . $const . '\'\s*,\s*(true|false)\s*\);/i', $config_content ) ) {
				$config_content = preg_replace( '/define\(\s*\'' . $const . '\'\s*,\s*(true|false)\s*\);/i', "define( '$const', $value );", $config_content );
			} else {
				$config_content = str_replace( "/* That's all, stop editing!", "define( '$const', $value );\n\n/* That's all, stop editing!", $config_content );
			}

			$new_options[ $key ] = 'true' === $value;
		}

		$wp_filesystem->put_contents( $config_path, $config_content );

		add_settings_error( 'easy_php_settings_debugging_settings', 'settings_updated', __( 'Debugging settings updated successfully.', 'easy-php-settings' ), 'updated' );

		return $new_options;
	}

	/**
	 * Update WordPress memory constants in wp-config.php.
	 *
	 * @param array $input The input array.
	 * @return void
	 */
	public function update_wp_memory_constants( $input ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$config_path = ABSPATH . 'wp-config.php';
		if ( ! $wp_filesystem->is_writable( $config_path ) ) {
			add_settings_error( 'easy_php_settings_wp_memory_settings', 'config_not_writable', __( 'wp-config.php is not writable.', 'easy-php-settings' ), 'error' );
			return;
		}

		$config_content = $wp_filesystem->get_contents( $config_path );
		$constants      = array( 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT' );

		foreach ( $constants as $const ) {
			$key = strtolower( $const );
			if ( isset( $input[ $key ] ) && ! empty( $input[ $key ] ) ) {
				$value = "'" . $input[ $key ] . "'";

				if ( preg_match( '/define\(\s*\'' . $const . '\'\s*,\s*\'.*?\'\s*\);/i', $config_content ) ) {
					$config_content = preg_replace( '/define\(\s*\'' . $const . '\'\s*,\s*\'.*?\'\s*\);/i', "define( '$const', $value );", $config_content );
				} else {
					$config_content = str_replace( "/* That's all, stop editing!", "define( '$const', $value );\n\n/* That's all, stop editing!", $config_content );
				}
			}
		}

		$wp_filesystem->put_contents( $config_path, $config_content );

		add_settings_error( 'easy_php_settings_wp_memory_settings', 'wp_memory_updated', __( 'WordPress memory settings updated successfully in wp-config.php.', 'easy-php-settings' ), 'updated' );
	}

	/**
	 * Handle log actions.
	 *
	 * @return void
	 */
	public function handle_log_actions() {
		if ( isset( $_POST['easy_php_settings_clear_log'] ) ) {
			check_admin_referer( 'easy_php_settings_clear_log_nonce' );

			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$log_file = WP_CONTENT_DIR . '/debug.log';

			if ( $wp_filesystem->exists( $log_file ) && $wp_filesystem->is_writable( $log_file ) ) {
				$wp_filesystem->put_contents( $log_file, '' );
				add_settings_error( 'easy_php_settings_settings', 'log_cleared', __( 'Debug log file cleared successfully.', 'easy-php-settings' ), 'updated' );
			} else {
				add_settings_error( 'easy_php_settings_settings', 'log_clear_error', __( 'Could not clear debug log file. Check file permissions.', 'easy-php-settings' ), 'error' );
			}
		}
	}
}

new Easy_PHP_Settings();
