<?php
/**
 * Base Module Class
 *
 * All modules should extend this class.
 *
 * @package EasyPHPSettings
 * @since 1.0.6
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Abstract base class for all Easy PHP Settings modules.
 *
 * @abstract
 */
abstract class Easy_Module_Base {

	/**
	 * Module ID (unique identifier).
	 *
	 * @var string
	 */
	protected $module_id;

	/**
	 * Module name (human-readable).
	 *
	 * @var string
	 */
	protected $module_name;

	/**
	 * Module description.
	 *
	 * @var string
	 */
	protected $module_description;

	/**
	 * Module version.
	 *
	 * @var string
	 */
	protected $module_version = '1.0.0';

	/**
	 * Module dependencies (array of module IDs).
	 *
	 * @var array
	 */
	protected $dependencies = array();

	/**
	 * Whether the module is enabled.
	 *
	 * @var bool
	 */
	protected $enabled = true;

	/**
	 * Main plugin instance.
	 *
	 * @var Easy_PHP_Settings
	 */
	protected $plugin;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin Main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->init();
	}

	/**
	 * Initialize the module.
	 * Override this method in child classes.
	 *
	 * @return void
	 */
	protected function init() {
		// Override in child classes.
	}

	/**
	 * Get module ID.
	 *
	 * @return string
	 */
	public function get_module_id() {
		return $this->module_id;
	}

	/**
	 * Get module name.
	 *
	 * @return string
	 */
	public function get_module_name() {
		return $this->module_name;
	}

	/**
	 * Get module description.
	 *
	 * @return string
	 */
	public function get_module_description() {
		return $this->module_description;
	}

	/**
	 * Get module version.
	 *
	 * @return string
	 */
	public function get_module_version() {
		return $this->module_version;
	}

	/**
	 * Get module dependencies.
	 *
	 * @return array
	 */
	public function get_dependencies() {
		return $this->dependencies;
	}

	/**
	 * Check if module is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Enable the module.
	 *
	 * @return void
	 */
	public function enable() {
		$this->enabled = true;
	}

	/**
	 * Disable the module.
	 *
	 * @return void
	 */
	public function disable() {
		$this->enabled = false;
	}

	/**
	 * Register hooks for this module.
	 * Override this method in child classes.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Override in child classes.
	}

	/**
	 * Get admin tab configuration.
	 * Override this method if the module provides an admin tab.
	 *
	 * @return array|null Array with 'id', 'title', 'callback', or null if no tab.
	 */
	public function get_admin_tab() {
		return null;
	}

	/**
	 * Get admin menu items.
	 * Override this method if the module provides menu items.
	 *
	 * @return array Array of menu item configurations.
	 */
	public function get_admin_menu_items() {
		return array();
	}

	/**
	 * Get settings fields.
	 * Override this method if the module provides settings fields.
	 *
	 * @return array Array of settings field configurations.
	 */
	public function get_settings_fields() {
		return array();
	}

	/**
	 * Handle admin actions.
	 * Override this method if the module handles admin actions.
	 *
	 * @return void
	 */
	public function handle_admin_actions() {
		// Override in child classes.
	}

	/**
	 * Enqueue scripts and styles.
	 * Override this method if the module needs custom assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Override in child classes.
	}

	/**
	 * Activation hook.
	 * Override this method if the module needs activation logic.
	 *
	 * @return void
	 */
	public function on_activation() {
		// Override in child classes.
	}

	/**
	 * Deactivation hook.
	 * Override this method if the module needs deactivation logic.
	 *
	 * @return void
	 */
	public function on_deactivation() {
		// Override in child classes.
	}
}
