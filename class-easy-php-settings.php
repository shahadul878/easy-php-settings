<?php
/**
 * Plugin Name: Easy PHP Settings
 * Plugin URI:  https://github.com/shahadul878/easy-php-settings
 * Description: An easy way to manage common PHP INI settings from the WordPress admin panel.
 * Version:     1.1.9
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

/**
 * Keep Plugin Check aligned with the WordPress.org zip.
 *
 * Git, GitHub Actions, and other development paths are listed in .distignore
 * and are not shipped. Ignore them here so a working-tree scan matches the
 * distributed plugin.
 *
 * @param array $directories Directories Plugin Check already ignores.
 * @return array
 */
function easy_php_settings_plugin_check_ignore_directories( $directories ) {
	return array_merge(
		(array) $directories,
		array( '.git', '.github', '.claude', '.cursor', '.wordpress-org', 'scripts' )
	);
}
add_filter( 'wp_plugin_check_ignore_directories', 'easy_php_settings_plugin_check_ignore_directories' );

/**
 * Ignore development-only hidden files that are not part of the plugin zip.
 *
 * @param array $files Files Plugin Check already ignores.
 * @return array
 */
function easy_php_settings_plugin_check_ignore_files( $files ) {
	return array_merge(
		(array) $files,
		array( '.gitignore', '.distignore', '.gitattributes' )
	);
}
add_filter( 'wp_plugin_check_ignore_files', 'easy_php_settings_plugin_check_ignore_files' );

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
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-ini-change-method.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-limit-health.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-live-check.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-site-health.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-rest-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-upload-hint.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-debug-log-parser.php';

// Module system.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-base.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-module-loader.php';

require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-tracker-integration.php';

register_activation_hook( __FILE__, function () {
	if ( function_exists( 'tracker_integration_on_activate' ) ) {
		tracker_integration_on_activate( __FILE__ );
	}
	// Best-effort: migrate any legacy DB-stored wp-config backups to the
	// filesystem (where they belong) and clear the option rows.
	if ( method_exists( 'Easy_Config_Backup', 'migrate_legacy_db_backups' ) ) {
		Easy_Config_Backup::migrate_legacy_db_backups();
	}
	if ( method_exists( 'Easy_Config_Backup', 'convert_legacy_plaintext_backups' ) ) {
		Easy_Config_Backup::convert_legacy_plaintext_backups();
	}

	// Seed the install timestamp used by the review-request prompt so its
	// delay window is anchored to the real install date, not first admin
	// load (which can be much later on staging environments).
	$install_key = 'easy_php_settings_install_time';
	$existing    = is_multisite() ? get_site_option( $install_key ) : get_option( $install_key );
	if ( ! $existing ) {
		if ( is_multisite() ) {
			update_site_option( $install_key, time() );
		} else {
			update_option( $install_key, time() );
		}
	}
} );

register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'tracker_integration_on_deactivate' ) ) {
		tracker_integration_on_deactivate( __FILE__ );
	}
} );

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
	private $version = '1.1.9';

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

	/**
	 * @var Easy_Live_Check
	 */
	private $live_check;

	public function __construct() {
		$this->live_check = new Easy_Live_Check( $this );
		$site_health      = new Easy_Site_Health( $this->live_check );
		$site_health->register_hooks();
		( new Easy_Rest_Api( $this, $site_health ) )->register_hooks();
		( new Easy_Upload_Hint( $this ) )->register_hooks();

		$this->module_manager = new Easy_Module_Manager( $this );
		$loader = new Easy_Module_Loader( $this->module_manager );
		$loader->load_all_modules();
		$this->module_manager->load_modules();

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

	public function get_live_check() {
		return $this->live_check;
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
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return 0;
		}
		$last  = strtolower( substr( $value, -1 ) );
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
				sprintf( 'jQuery( function() { if ( document.getElementById( "easy_php_settings_custom_php_ini" ) ) { wp.codeEditor.initialize( "easy_php_settings_custom_php_ini", %s ); } } );', wp_json_encode( $settings ) )
			);
		}

		wp_enqueue_style( 'easy-php-settings-styles', plugin_dir_url( __FILE__ ) . 'css/admin-styles.css', array(), $this->version );
		wp_enqueue_style( 'easy-php-settings-theme', plugin_dir_url( __FILE__ ) . 'css/admin-theme.css', array( 'easy-php-settings-styles' ), $this->version );
		wp_enqueue_style( 'easy-php-settings-theme-components', plugin_dir_url( __FILE__ ) . 'css/admin-theme-components.css', array( 'easy-php-settings-theme' ), $this->version );
		wp_enqueue_style( 'easy-php-settings-log-viewer', plugin_dir_url( __FILE__ ) . 'css/admin-log-viewer.css', array( 'easy-php-settings-theme' ), $this->version );
		wp_enqueue_style( 'easy-php-settings-about', plugin_dir_url( __FILE__ ) . 'css/admin-about.css', array( 'easy-php-settings-theme' ), $this->version );

		wp_enqueue_script( 'easy-php-settings-admin', plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_script( 'easy-php-settings-general', plugin_dir_url( __FILE__ ) . 'js/general-settings.js', array( 'easy-php-settings-admin' ), $this->version, true );
		wp_enqueue_script( 'easy-php-settings-log-viewer', plugin_dir_url( __FILE__ ) . 'js/log-viewer.js', array(), $this->version, true );
		wp_localize_script( 'easy-php-settings-admin', 'easy_php_settingsKeys', $this->settings_keys );
		wp_localize_script(
			'easy-php-settings-admin',
			'easy_php_settingsAdminVars',
			array(
				'copiedText'     => esc_html__( 'Copied to clipboard!', 'easy-php-settings' ),
				'liveCheck'      => array(
					'url'   => rest_url( Easy_Rest_Api::NAMESPACE_V1 . '/live-check' ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				),
				'i18n'           => array(
					'noChanges'     => __( 'No unsaved changes', 'easy-php-settings' ),
					'oneChange'     => __( '1 unsaved change', 'easy-php-settings' ),
					/* translators: %d: number of changed fields */
					'manyChanges'   => __( '%d unsaved changes', 'easy-php-settings' ),
					/* translators: 1: preset name, 2: number of fields filled */
					'presetApplied' => __( '%1$s preset filled %2$d fields. Review them, then save.', 'easy-php-settings' ),
					'checking'      => __( 'Checking the server…', 'easy-php-settings' ),
					/* translators: %s: time of day */
					'checkedAt'     => __( 'Checked at %s', 'easy-php-settings' ),
					'checkFailed'   => __( 'Could not check the server. Showing values from this page.', 'easy-php-settings' ),
					'blockedOne'    => __( 'Your server is not using 1 of your saved settings', 'easy-php-settings' ),
					/* translators: %d: number of settings */
					'blockedMany'   => __( 'Your server is not using %d of your saved settings', 'easy-php-settings' ),
					/* translators: 1: setting name, 2: saved value, 3: value in use */
					'blockedItem'   => __( '%1$s: saved %2$s, in use %3$s', 'easy-php-settings' ),
					'blockedHost'   => __( 'The server reads .user.ini but keeps the old values, so your host probably sets them in a way the plugin cannot override. Send them the message below.', 'easy-php-settings' ),
					'blockedNoIni'  => __( 'This server does not read .user.ini, so the plugin cannot apply these values. Send your host the message below, or use the .htaccess snippet in the tools further down.', 'easy-php-settings' ),
				),
				'noRowsSelected' => esc_html__( 'No rows selected.', 'easy-php-settings' ),
				'presets'        => $this->quick_presets,
				'tooltips'       => $this->setting_tooltips,
			)
		);
	}

	/**
	 * Facts about this server shown in the page header.
	 *
	 * Each item has a label, value, hint (tooltip) and state: ok, warn or neutral.
	 *
	 * @return array[]
	 */
	private function get_server_readout() {
		$sapi           = php_sapi_name();
		$user_ini       = (string) ini_get( 'user_ini.filename' );
		$reads_user_ini = Easy_Ini_Change_Method::server_reads_user_ini( $sapi, $user_ini );
		$is_writable    = wp_is_writable( ABSPATH );

		return array(
			array(
				'label' => __( 'PHP', 'easy-php-settings' ),
				'value' => PHP_VERSION,
				'hint'  => __( 'PHP version running this site.', 'easy-php-settings' ),
				'state' => 'neutral',
			),
			array(
				'label' => __( 'Server API', 'easy-php-settings' ),
				'value' => $sapi,
				'hint'  => __( 'How PHP is connected to the web server.', 'easy-php-settings' ),
				'state' => 'neutral',
			),
			array(
				'label' => __( '.user.ini', 'easy-php-settings' ),
				'value' => $reads_user_ini
					/* translators: %d: seconds PHP caches .user.ini for */
					? sprintf( __( 'Read · %ds cache', 'easy-php-settings' ), (int) ini_get( 'user_ini.cache_ttl' ) )
					: __( 'Not read', 'easy-php-settings' ),
				'hint'  => $reads_user_ini
					? __( 'This server applies .user.ini. Changes take effect after the cache time.', 'easy-php-settings' )
					: __( 'This server does not read .user.ini. Use the manual snippets or ask your host.', 'easy-php-settings' ),
				'state' => $reads_user_ini ? 'ok' : 'warn',
			),
			array(
				'label' => __( 'Site root', 'easy-php-settings' ),
				'value' => $is_writable ? __( 'Writable', 'easy-php-settings' ) : __( 'Read-only', 'easy-php-settings' ),
				'hint'  => $is_writable
					? __( 'The plugin can write its INI files.', 'easy-php-settings' )
					: __( 'The plugin cannot write INI files here. Copy the generated snippets instead.', 'easy-php-settings' ),
				'state' => $is_writable ? 'ok' : 'warn',
			),
			array(
				'label' => __( 'WordPress', 'easy-php-settings' ),
				'value' => get_bloginfo( 'version' ),
				'hint'  => __( 'WordPress version.', 'easy-php-settings' ),
				'state' => 'neutral',
			),
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

		$admin_tabs = $this->module_manager->get_admin_tabs();

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

		// Tab navigation is read-only: no nonce needed (a nonce would only
		// add UX friction since they expire after ~24h, and nonces are not
		// an authorization mechanism). Whitelist against known tab IDs.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch, sanitized against known IDs.
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general_settings';
		if ( ! isset( $ordered_tabs[ $active_tab ] ) ) {
			$active_tab = 'general_settings';
		}
		$version = $this->get_version();
		$tab_icons = array(
			'general_settings' => 'dashicons-admin-settings',
			'tools'            => 'dashicons-admin-tools',
			'php_settings'     => 'dashicons-editor-code',
			'extensions'       => 'dashicons-admin-plugins',
			'status'           => 'dashicons-chart-bar',
			'about'            => 'dashicons-admin-users',
		);
		?>
		<div class="wrap easy-php-settings-app">
			<?php // WordPress moves admin notices to just after this marker, so they sit above the header. ?>
			<hr class="wp-header-end">
			<header class="eps-header">
				<div class="eps-header__top">
					<div class="eps-header__brand">
						<span class="eps-header__mark dashicons dashicons-performance" aria-hidden="true"></span>
						<div>
							<h1 class="eps-header__title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
							<p class="eps-header__tagline"><?php esc_html_e( 'PHP limits, WordPress memory and debugging, in one place.', 'easy-php-settings' ); ?></p>
						</div>
					</div>
					<span class="eps-header__version">v<?php echo esc_html( $version ); ?></span>
				</div>

				<dl class="eps-readout" aria-label="<?php esc_attr_e( 'Server summary', 'easy-php-settings' ); ?>">
					<?php foreach ( $this->get_server_readout() as $item ) : ?>
						<div class="eps-readout__item eps-readout__item--<?php echo esc_attr( $item['state'] ); ?>">
							<dt><?php echo esc_html( $item['label'] ); ?></dt>
							<dd title="<?php echo esc_attr( $item['hint'] ); ?>"><?php echo esc_html( $item['value'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>

				<nav class="eps-tabs" aria-label="<?php esc_attr_e( 'Easy PHP Settings sections', 'easy-php-settings' ); ?>">
					<?php foreach ( $ordered_tabs as $id => $cfg ) : ?>
						<?php $icon = isset( $tab_icons[ $id ] ) ? $tab_icons[ $id ] : 'dashicons-admin-generic'; ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'easy-php-settings', 'tab' => $id ), admin_url( 'tools.php' ) ) ); ?>"
							class="eps-tabs__link<?php echo $id === $active_tab ? ' is-active' : ''; ?>"
							<?php echo $id === $active_tab ? 'aria-current="page"' : ''; ?>>
							<span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
							<?php echo esc_html( $cfg['title'] ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</header>

			<?php settings_errors(); ?>

			<div class="easy-php-settings-panel">
			<?php
			if ( isset( $ordered_tabs[ $active_tab ] ) && is_callable( $ordered_tabs[ $active_tab ]['callback'] ) ) {
				call_user_func( $ordered_tabs[ $active_tab ]['callback'] );
			} else {
				echo '<p>' . esc_html__( 'Tab not found.', 'easy-php-settings' ) . '</p>';
			}
			?>
			</div>
		</div>
		<?php
	}
}

$easy_php_settings_plugin = new Easy_PHP_Settings();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-easy-php-settings-cli.php';
	WP_CLI::add_command( 'easy-php', new Easy_PHP_Settings_CLI( $easy_php_settings_plugin ) );
}
