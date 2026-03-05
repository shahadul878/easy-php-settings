<?php
/**
 * Module Manager
 *
 * Handles loading and managing all modules.
 *
 * @package EasyPHPSettings
 * @since 1.0.6
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Module Manager Class
 */
class Easy_Module_Manager {

	/**
	 * Registered modules.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Loaded modules.
	 *
	 * @var array
	 */
	private $loaded_modules = array();

	/**
	 * Main plugin instance.
	 *
	 * @var Easy_PHP_Settings
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin Main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get main plugin instance.
	 *
	 * @return Easy_PHP_Settings
	 */
	public function get_plugin() {
		return $this->plugin;
	}

	/**
	 * Register a module.
	 *
	 * @param string $module_id Module ID.
	 * @param string $module_class Module class name.
	 * @return bool True on success, false on failure.
	 */
	public function register_module( $module_id, $module_class ) {
		if ( ! class_exists( $module_class ) ) {
			return false;
		}

		if ( ! is_subclass_of( $module_class, 'Easy_Module_Base' ) ) {
			return false;
		}

		$this->modules[ $module_id ] = $module_class;
		return true;
	}

	/**
	 * Load all registered modules.
	 *
	 * @return void
	 */
	public function load_modules() {
		// Sort modules by dependencies.
		$sorted_modules = $this->sort_modules_by_dependencies();

		foreach ( $sorted_modules as $module_id => $module_class ) {
			$this->load_module( $module_id, $module_class );
		}
	}

	/**
	 * Load a single module.
	 *
	 * @param string $module_id Module ID.
	 * @param string $module_class Module class name.
	 * @return bool True on success, false on failure.
	 */
	private function load_module( $module_id, $module_class ) {
		if ( isset( $this->loaded_modules[ $module_id ] ) ) {
			return true; // Already loaded.
		}

		// Check dependencies.
		$temp_instance = new $module_class( $this->plugin );
		$dependencies = $temp_instance->get_dependencies();

		foreach ( $dependencies as $dep_id ) {
			if ( ! isset( $this->loaded_modules[ $dep_id ] ) ) {
				// Dependency not loaded, skip this module.
				return false;
			}
		}

		// Create module instance.
		$module = new $module_class( $this->plugin );

		if ( ! $module->is_enabled() ) {
			return false;
		}

		// Register hooks.
		$module->register_hooks();

		// Store loaded module.
		$this->loaded_modules[ $module_id ] = $module;

		return true;
	}

	/**
	 * Sort modules by dependencies (topological sort).
	 *
	 * @return array Sorted modules array.
	 */
	private function sort_modules_by_dependencies() {
		$sorted = array();
		$visited = array();
		$visiting = array();

		foreach ( $this->modules as $module_id => $module_class ) {
			if ( ! isset( $visited[ $module_id ] ) ) {
				$this->visit_module( $module_id, $module_class, $visited, $visiting, $sorted );
			}
		}

		return $sorted;
	}

	/**
	 * Visit a module for topological sort.
	 *
	 * @param string $module_id Module ID.
	 * @param string $module_class Module class name.
	 * @param array  $visited Visited modules.
	 * @param array  $visiting Currently visiting modules.
	 * @param array  $sorted Sorted modules array (by reference).
	 * @return void
	 */
	private function visit_module( $module_id, $module_class, &$visited, &$visiting, &$sorted ) {
		if ( isset( $visiting[ $module_id ] ) ) {
			// Circular dependency detected.
			return;
		}

		if ( isset( $visited[ $module_id ] ) ) {
			return;
		}

		$visiting[ $module_id ] = true;

		// Create temporary instance to get dependencies.
		$temp_instance = new $module_class( $this->plugin );
		$dependencies = $temp_instance->get_dependencies();

		foreach ( $dependencies as $dep_id ) {
			if ( isset( $this->modules[ $dep_id ] ) ) {
				$this->visit_module( $dep_id, $this->modules[ $dep_id ], $visited, $visiting, $sorted );
			}
		}

		unset( $visiting[ $module_id ] );
		$visited[ $module_id ] = true;
		$sorted[ $module_id ] = $module_class;
	}

	/**
	 * Get a loaded module instance.
	 *
	 * @param string $module_id Module ID.
	 * @return Easy_Module_Base|null Module instance or null if not found.
	 */
	public function get_module( $module_id ) {
		return isset( $this->loaded_modules[ $module_id ] ) ? $this->loaded_modules[ $module_id ] : null;
	}

	/**
	 * Get all loaded modules.
	 *
	 * @return array Array of module instances.
	 */
	public function get_loaded_modules() {
		return $this->loaded_modules;
	}

	/**
	 * Get all admin tabs from modules.
	 *
	 * @return array Array of tab configurations.
	 */
	public function get_admin_tabs() {
		$tabs = array();

		foreach ( $this->loaded_modules as $module ) {
			$tab = $module->get_admin_tab();
			if ( $tab ) {
				$tabs[ $tab['id'] ] = $tab;
			}
		}

		return $tabs;
	}

	/**
	 * Call handle_admin_actions on all modules.
	 *
	 * @return void
	 */
	public function handle_all_admin_actions() {
		foreach ( $this->loaded_modules as $module ) {
			$module->handle_admin_actions();
		}
	}

	/**
	 * Call enqueue_assets on all modules.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_all_assets( $hook ) {
		foreach ( $this->loaded_modules as $module ) {
			$module->enqueue_assets( $hook );
		}
	}
}
