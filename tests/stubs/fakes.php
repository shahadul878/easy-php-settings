<?php
/**
 * Test doubles for the plugin and its modules.
 *
 * @package EasyPHPSettings
 */

// phpcs:disable

class Fake_Easy_Plugin {
	public $options = array();
	public $modules = array();

	public function get_settings_keys() {
		return array( 'memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_input_vars' );
	}

	public function get_wp_memory_settings_keys() {
		return array( 'wp_memory_limit', 'wp_max_memory_limit' );
	}

	public function get_recommended_values() {
		return array(
			'memory_limit'        => '256M',
			'upload_max_filesize' => '128M',
			'post_max_size'       => '256M',
			'max_execution_time'  => '300',
			'max_input_vars'      => '10000',
		);
	}

	public function get_wp_memory_recommended_values() {
		return array(
			'wp_memory_limit'     => '256M',
			'wp_max_memory_limit' => '512M',
		);
	}

	public function get_option( $key, $default_value = false ) {
		return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default_value;
	}

	public function get_module_manager() {
		return $this;
	}

	public function get_module( $module_id ) {
		return isset( $this->modules[ $module_id ] ) ? $this->modules[ $module_id ] : null;
	}
}

/**
 * Records save calls and replays queued settings errors, as the real
 * modules report results through add_settings_error().
 */
class Fake_Easy_Module {
	public $calls    = array();
	public $messages = array();

	private function record( $method, array $input ) {
		$this->calls[] = array( $method, $input );
		foreach ( $this->messages as $message ) {
			add_settings_error( 'test', 'code', $message[1], $message[0] );
		}
		return $input;
	}

	public function save_php_settings( array $input ) {
		return $this->record( 'save_php_settings', $input );
	}

	public function save_wp_memory_settings( array $input ) {
		return $this->record( 'save_wp_memory_settings', $input );
	}

	public function save_debug_settings( array $input ) {
		return $this->record( 'save_debug_settings', $input );
	}
}
