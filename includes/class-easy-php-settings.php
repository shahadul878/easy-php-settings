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

// Classes are now loaded via the main plugin file.

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
	 * Settings API instance
	 *
	 * @var Easy_PHP_Settings_API
	 */
	private $settings_api;

	/**
	 * Export Import Handler instance
	 *
	 * @var Easy_PHP_Settings_Export_Import_Handler
	 */
	private $export_import_handler;

	/**
	 * Reset Handler instance
	 *
	 * @var Easy_PHP_Settings_Reset_Handler
	 */
	private $reset_handler;

	/**
	 *  Initializes plugin settings.
	 */
	public function __construct() {
		$this->init_tooltips();
		$this->quick_presets = Easy_PHP_Settings_Presets::get_presets();

		// Initialize Settings API.
		$this->settings_api = new Easy_PHP_Settings_API(
			$this->settings_keys,
			$this->wp_memory_settings_keys,
			$this->recommended_values,
			$this->wp_memory_recommended_values,
			array( $this, 'get_option' ),
			array( $this, 'update_option' )
		);

		// Initialize Export/Import Handler.
		$this->export_import_handler = new Easy_PHP_Settings_Export_Import_Handler(
			$this->version,
			array( $this, 'get_capability' ),
			array( $this, 'get_option' ),
			array( $this, 'update_option' )
		);

		// Initialize Reset Handler.
		$this->reset_handler = new Easy_PHP_Settings_Reset_Handler(
			array( $this, 'get_capability' ),
			array( $this, 'get_option' ),
			array( $this, 'update_option' ),
			array( $this, 'delete_option' ),
			$this->recommended_values
		);

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
			plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
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
		return Easy_PHP_Settings_Capabilities::get_capability();
	}

	/**
	 * Get Option
	 *
	 * @param string $key The option key.
	 * @param mixed  $default_value The default value.
	 * @return false|mixed|null
	 */
	public function get_option( $key, $default_value = false ) {
		return Easy_PHP_Settings_Helpers::get_option( $key, $default_value );
	}

	/**
	 * Update Option
	 *
	 * @param string $key The option key.
	 * @param mixed  $value The option value.
	 * @return bool
	 */
	public function update_option( $key, $value ) {
		return Easy_PHP_Settings_Helpers::update_option( $key, $value );
	}

	/**
	 * Delete Option
	 *
	 * @param string $key The option key.
	 * @return bool
	 */
	public function delete_option( $key ) {
		return Easy_PHP_Settings_Helpers::delete_option( $key );
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
		$this->settings_api->settings_init();
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

			$files_deleted = Easy_PHP_Settings_File_Handler::remove_files();

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
		$this->settings_api->render_setting_field( $args );
	}



	/**
	 * Render WordPress memory setting field.
	 *
	 * @param array $args The field arguments.
	 * @return void
	 */
	public function render_wp_memory_field( $args ) {
		$this->settings_api->render_wp_memory_field( $args );
	}


	/**
	 * Handle export and import actions
	 *
	 * @return void
	 */
	public function handle_export_import() {
		$this->export_import_handler->handle_export_import();
	}

	/**
	 * Handle reset actions
	 *
	 * @return void
	 */
	public function handle_reset_actions() {
		$this->reset_handler->handle_reset_actions();
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

			$csv = Easy_PHP_Settings_History::export_as_csv();
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

			Easy_PHP_Settings_History::clear_history();
			add_settings_error( 'easy_php_settings_settings', 'history_cleared', __( 'History cleared successfully.', 'easy-php-settings' ), 'updated' );
		}

		// Restore from history.
		if ( isset( $_POST['easy_php_settings_restore_history'] ) && isset( $_POST['history_index'] ) && check_admin_referer( 'easy_php_settings_history_nonce' ) ) {
			if ( ! current_user_can( $this->get_capability() ) ) {
				return;
			}

			$index = intval( $_POST['history_index'] );
			$entry = Easy_PHP_Settings_History::get_entry( $index );

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
						if ( class_exists( 'Easy_PHP_Settings_Info' ) ) {
							echo Easy_PHP_Settings_Info::render( 'Core' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
		$categorized = Easy_PHP_Settings_Extensions::get_categorized_extensions();
		$missing     = Easy_PHP_Settings_Extensions::get_critical_missing_extensions();
		$recommended = Easy_PHP_Settings_Extensions::get_recommended_extensions();
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
							<td><?php echo esc_html( Easy_PHP_Settings_Extensions::get_extension_version( $extension ) ); ?></td>
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
						<?php if ( Easy_PHP_Settings_Extensions::is_loaded( $ext ) ) : ?>
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
		$history = Easy_PHP_Settings_History::get_history();
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
		return Easy_PHP_Settings_WP_Config_Handler::update_debugging_constants( $input );
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
