<?php
/**
 * Module Loader
 *
 * Automatically discovers and loads all modules.
 *
 * @package EasyPHPSettings
 * @since 1.0.6
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Module Loader Class
 */
class Easy_Module_Loader {

	/**
	 * Modules directory path.
	 *
	 * @var string
	 */
	private $modules_dir;

	/**
	 * Module Manager instance.
	 *
	 * @var Easy_Module_Manager
	 */
	private $module_manager;

	/**
	 * Constructor.
	 *
	 * @param Easy_Module_Manager $module_manager Module manager instance.
	 */
	public function __construct( $module_manager ) {
		$this->module_manager = $module_manager;
		$this->modules_dir    = plugin_dir_path( dirname( __FILE__ ) ) . 'modules/';
	}

	/**
	 * Load all modules.
	 *
	 * @return void
	 */
	public function load_all_modules() {
		if ( ! is_dir( $this->modules_dir ) ) {
			return;
		}

		$module_files = glob( $this->modules_dir . 'class-module-*.php' );

		if ( ! $module_files ) {
			return;
		}

		foreach ( $module_files as $module_file ) {
			require_once $module_file;

			// Extract module class name from filename.
			// Format: class-module-general-settings.php -> Easy_Module_General_Settings
			$filename = basename( $module_file, '.php' );
			$filename = str_replace( 'class-', '', $filename );
			$parts = explode( '-', $filename );
			$class_parts = array_map( 'ucfirst', $parts );
			$class_name = 'Easy_' . implode( '_', $class_parts );

			if ( class_exists( $class_name ) && is_subclass_of( $class_name, 'Easy_Module_Base' ) ) {
				// Create temporary instance to get module ID.
				$temp_instance = new $class_name( $this->module_manager->get_plugin() );
				$module_id = $temp_instance->get_module_id();

				// Register the module.
				$this->module_manager->register_module( $module_id, $class_name );
			}
		}
	}
}
