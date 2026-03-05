<?php
/**
 * Plugin Name: Easy PHP Settings
 * Plugin URI:  https://github.com/easy-php-settings
 * Description: An easy way to manage common PHP INI settings from the WordPress admin panel.
 * Version:     1.1.0
 * Author:      H M Shahadul Islam
 * Author URI:  https://github.com/shahadul878
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: easy-php-settings
 * Domain Path: /languages
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Helper classes.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyphpinfo.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easyinifile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-settings-history.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-extensions-viewer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-config-backup.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-config-parser.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-error-handler.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-settings-validator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-settings-cache.php';

// Module system.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-base.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-loader.php';

/**
 * Main plugin orchestrator.
 *
 * Boots the module system and provides shared data/utilities.
 * All feature logic lives in modules (modules/ directory).
 */
class Easy_PHP_Settings {

	/**
	 * @var string[]
	 */
	private $settings_keys = array(
		'memory_limit',
		'upload_max_filesize',
		'post_max_size',
		'max_execution_time',
		'max_input_vars',
	);

	/**
	 * @var string[]
	 */
	private $wp_memory_settings_keys = array(
		'wp_memory_limit',
		'wp_max_memory_limit',
	);

	/**
	 * @var string[]
	 */
	private $recommended_values = array(
		'memory_limit'        => '256M',
		'upload_max_filesize' => '128M',
		'post_max_size'       => '256M',
		'max_execution_time'  => '300',
		'max_input_vars'      => '10000',
	);

	/**
	 * @var string[]
	 */
	private $wp_memory_recommended_values = array(
		'wp_memory_limit'     => '256M',
		'wp_max_memory_limit' => '512M',
	);

	/**
	 * @var string
	 */
	private $version = '1.1.0';

	/**
	 * @var array
	 */
	private $setting_tooltips = array();

	/**
	 * @var array
	 */
	private $quick_presets = array();

	/**
	 * @var Easy_Module_Manager
	 */
	private $module_manager;

	public function __construct() {
		$this->module_manager = new Easy_Module_Manager( $this );
		$loader = new Easy_Module_Loader( $this->module_manager );
		$loader->load_all_modules();
		$this->module_manager->load_modules();

		add_action( 'init', array( $this, 'load_textdomain' ), 5 );
		add_action( 'init', array( $this, 'init_tooltips' ), 10 );
		add_action( 'init', array( $this, 'init_presets' ), 10 );

		$hook = is_multisite() ? 'network_admin_menu' : 'admin_menu';
		add_action( $hook, array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Delegate admin_init actions and asset enqueueing to modules.
		add_action( 'admin_init', array( $this->module_manager, 'handle_all_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this->module_manager, 'enqueue_all_assets' ) );
	}

	/* ──────────────────────────────────────────────
	   Public getters – used by modules
	   ────────────────────────────────────────────── */

	public function get_module_manager() {
		return $this->module_manager;
	}

	public function get_settings_keys() {
		return $this->settings_keys;
	}

	public function get_wp_memory_settings_keys() {
		return $this->wp_memory_settings_keys;
	}

	public function get_recommended_values() {
		return $this->recommended_values;
	}

	public function get_wp_memory_recommended_values() {
		return $this->wp_memory_recommended_values;
	}

	public function get_version() {
		return $this->version;
	}

	public function get_tooltips() {
		return $this->setting_tooltips;
	}

	public function get_presets() {
		return $this->quick_presets;
	}

	/* ──────────────────────────────────────────────
	   Shared option helpers (multisite-aware)
	   ────────────────────────────────────────────── */

	public function get_capability() {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	public function get_option( $key, $default_value = false ) {
		return is_multisite() ? get_site_option( $key, $default_value ) : get_option( $key, $default_value );
	}

	public function update_option( $key, $value ) {
		return is_multisite() ? update_site_option( $key, $value ) : update_option( $key, $value );
	}

	public function delete_option( $key ) {
		return is_multisite() ? delete_site_option( $key ) : delete_option( $key );
	}

	/**
	 * Convert a shorthand byte value (e.g. "256M") to bytes.
	 *
	 * @param string $value Shorthand value.
	 * @return int
	 */
	public function convert_to_bytes( $value ) {
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

	/* ──────────────────────────────────────────────
	   Bootstrap helpers
	   ────────────────────────────────────────────── */

	public function load_textdomain() {
		load_plugin_textdomain(
			'easy-php-settings',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	public function init_tooltips() {
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

	public function init_presets() {
		$this->quick_presets = array(
			'default'     => array(
				'name'                => __( 'Default', 'easy-php-settings' ),
				'description'         => __( 'WordPress default values', 'easy-php-settings' ),
				'memory_limit'        => '128M',
				'upload_max_filesize' => '32M',
				'post_max_size'       => '64M',
				'max_execution_time'  => '30',
				'max_input_vars'      => '1000',
				'custom_php_ini'      => "; Additional PHP directives (optional)\nsession.gc_maxlifetime = 1440\nlog_errors = 1\ndate.timezone = UTC\nmax_file_uploads = 20\nmax_input_time = 60",
			),
			'performance' => array(
				'name'                => __( 'Performance Optimized', 'easy-php-settings' ),
				'description'         => __( 'Higher limits for busy sites', 'easy-php-settings' ),
				'memory_limit'        => '256M',
				'upload_max_filesize' => '128M',
				'post_max_size'       => '256M',
				'max_execution_time'  => '300',
				'max_input_vars'      => '10000',
				'custom_php_ini'      => "; Performance optimizations\nsession.gc_maxlifetime = 1440\nlog_errors = 1\ndate.timezone = UTC\nmax_file_uploads = 20\nmax_input_time = 120\nopcache.enable = 1\nopcache.memory_consumption = 128\nopcache.max_accelerated_files = 10000",
			),
			'woocommerce' => array(
				'name'                => __( 'WooCommerce', 'easy-php-settings' ),
				'description'         => __( 'Optimized for e-commerce sites', 'easy-php-settings' ),
				'memory_limit'        => '256M',
				'upload_max_filesize' => '64M',
				'post_max_size'       => '128M',
				'max_execution_time'  => '180',
				'max_input_vars'      => '5000',
				'custom_php_ini'      => "; WooCommerce optimizations\nsession.gc_maxlifetime = 3600\nlog_errors = 1\ndate.timezone = UTC\nmax_file_uploads = 20\nmax_input_time = 90\nsession.cookie_lifetime = 3600",
			),
			'development' => array(
				'name'                => __( 'Development', 'easy-php-settings' ),
				'description'         => __( 'High limits for development environments', 'easy-php-settings' ),
				'memory_limit'        => '512M',
				'upload_max_filesize' => '256M',
				'post_max_size'       => '512M',
				'max_execution_time'  => '600',
				'max_input_vars'      => '10000',
				'custom_php_ini'      => "; Development settings\nsession.gc_maxlifetime = 1440\nlog_errors = 1\ndisplay_errors = 1\nerror_reporting = E_ALL\ndate.timezone = UTC\nmax_file_uploads = 50\nmax_input_time = 300\nxdebug.max_nesting_level = 512",
			),
			'large_media' => array(
				'name'                => __( 'Large Media', 'easy-php-settings' ),
				'description'         => __( 'For sites handling large files', 'easy-php-settings' ),
				'memory_limit'        => '384M',
				'upload_max_filesize' => '512M',
				'post_max_size'       => '768M',
				'max_execution_time'  => '600',
				'max_input_vars'      => '5000',
				'custom_php_ini'      => "; Large file handling\nsession.gc_maxlifetime = 1440\nlog_errors = 1\ndate.timezone = UTC\nmax_file_uploads = 50\nmax_input_time = 300",
			),
		);
	}

	public function enqueue_styles( $hook ) {
		if ( 'tools_page_easy-php-settings' !== $hook ) {
			return;
		}

		$settings = wp_enqueue_code_editor( array( 'type' => 'text/x-ini' ) );
		if ( false !== $settings ) {
			wp_add_inline_script(
				'code-editor',
				sprintf( 'jQuery( function() { wp.codeEditor.initialize( "easy_php_settings_custom_php_ini", %s ); } );', wp_json_encode( $settings ) )
			);
		}

		wp_enqueue_style( 'easy-php-settings-styles', plugin_dir_url( __FILE__ ) . 'css/admin-styles.css', array(), $this->version );

		wp_enqueue_script( 'easy-php-settings-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, true );
		wp_localize_script( 'easy-php-settings-admin', 'easy_php_settingsKeys', $this->settings_keys );
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

	/* ──────────────────────────────────────────────
	   Admin page shell (tab framework)
	   ────────────────────────────────────────────── */

	public function add_admin_menu() {
		add_management_page(
			__( 'PHP Settings Manager', 'easy-php-settings' ),
			__( 'Easy PHP Settings', 'easy-php-settings' ),
			$this->get_capability(),
			'easy-php-settings',
			array( $this, 'options_page_html' )
		);
	}

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
		$admin_tabs    = $this->module_manager->get_admin_tabs();

		$tab_order    = array( 'general_settings', 'tools', 'php_settings', 'extensions', 'status', 'about' );
		$ordered_tabs = array();
		foreach ( $tab_order as $id ) {
			if ( isset( $admin_tabs[ $id ] ) ) {
				$ordered_tabs[ $id ] = $admin_tabs[ $id ];
			}
		}
		foreach ( $admin_tabs as $id => $cfg ) {
			if ( ! isset( $ordered_tabs[ $id ] ) ) {
				$ordered_tabs[ $id ] = $cfg;
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $ordered_tabs as $id => $cfg ) : ?>
					<a href="?page=easy-php-settings&tab=<?php echo esc_attr( $id ); ?>&_wpnonce=<?php echo esc_attr( $tab_nonce_url ); ?>"
					   class="nav-tab <?php echo $id === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $cfg['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			if ( isset( $ordered_tabs[ $active_tab ] ) && is_callable( $ordered_tabs[ $active_tab ]['callback'] ) ) {
				call_user_func( $ordered_tabs[ $active_tab ]['callback'] );
			} else {
				echo '<p>' . esc_html__( 'Tab not found.', 'easy-php-settings' ) . '</p>';
			}
			?>
		</div>
		<?php
	}
}

new Easy_PHP_Settings();
