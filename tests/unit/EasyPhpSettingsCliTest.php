<?php
/**
 * Tests for the `wp easy-php` WP-CLI command.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_PHP_Settings_CLI.
 */
class EasyPhpSettingsCliTest extends TestCase {

	/** @var Fake_Easy_Plugin */
	private $plugin;

	/** @var Fake_Easy_Module */
	private $general;

	/** @var Fake_Easy_Module */
	private $tools;

	/** @var array|WP_Error Value returned by the fake INI remover. */
	private $removed = array();

	/** @var Easy_PHP_Settings_CLI */
	private $cli;

	protected function setUp(): void {
		WP_CLI::reset();
		$GLOBALS['wp_settings_errors'] = array();

		$this->plugin  = new Fake_Easy_Plugin();
		$this->general = new Fake_Easy_Module();
		$this->tools   = new Fake_Easy_Module();

		$this->plugin->modules = array(
			'general_settings' => $this->general,
			'tools'            => $this->tools,
		);
		$this->plugin->options = array(
			'easy_php_settings_settings'           => array(
				'memory_limit'   => '256M',
				'max_input_vars' => '5000',
				'custom_php_ini' => 'date.timezone = UTC',
			),
			'easy_php_settings_wp_memory_settings' => array(
				'wp_memory_limit' => '128M',
			),
		);

		$this->cli = new Easy_PHP_Settings_CLI(
			$this->plugin,
			function () {
				return $this->removed;
			}
		);
	}

	private function calls_of( $type ) {
		return array_values(
			array_filter(
				WP_CLI::$calls,
				function ( $call ) use ( $type ) {
					return $call[0] === $type;
				}
			)
		);
	}

	private function assert_halts_with( $expected, callable $command ) {
		try {
			$command();
		} catch ( WP_CLI_Halt $halt ) {
			$this->assertStringContainsString( $expected, $halt->getMessage() );
			return;
		}
		$this->fail( 'Expected WP_CLI::error() with: ' . $expected );
	}

	/* ─── list ─── */

	public function test_list_shows_every_setting_with_saved_and_recommended_values() {
		$this->cli->list_( array(), array( 'format' => 'json' ) );

		$output = $this->calls_of( 'format_items' );
		$this->assertCount( 1, $output );
		list( , $format, $items, $fields ) = $output[0];

		$this->assertSame( 'json', $format );
		$this->assertSame( array( 'setting', 'saved', 'recommended', 'file' ), $fields );
		$this->assertCount( 7, $items );
		$this->assertSame(
			array( 'setting' => 'memory_limit', 'saved' => '256M', 'recommended' => '256M', 'file' => '.user.ini / php.ini' ),
			$items[0]
		);
		$this->assertSame(
			array( 'setting' => 'upload_max_filesize', 'saved' => '', 'recommended' => '128M', 'file' => '.user.ini / php.ini' ),
			$items[1]
		);
		$this->assertSame(
			array( 'setting' => 'wp_memory_limit', 'saved' => '128M', 'recommended' => '256M', 'file' => 'wp-config.php' ),
			$items[5]
		);
	}

	public function test_list_defaults_to_table_format() {
		$this->cli->list_( array(), array() );

		$this->assertSame( 'table', $this->calls_of( 'format_items' )[0][1] );
	}

	/* ─── get ─── */

	public function test_get_prints_the_saved_value() {
		$this->cli->get( array( 'max_input_vars' ) );

		$this->assertSame( array( array( 'log', '5000' ) ), WP_CLI::$calls );
	}

	public function test_get_prints_a_saved_wp_memory_value() {
		$this->cli->get( array( 'wp_memory_limit' ) );

		$this->assertSame( array( array( 'log', '128M' ) ), WP_CLI::$calls );
	}

	public function test_get_rejects_an_unknown_setting() {
		$this->assert_halts_with( 'Unknown setting "display_errors"', function () {
			$this->cli->get( array( 'display_errors' ) );
		} );
	}

	public function test_get_errors_when_setting_has_no_saved_value() {
		$this->assert_halts_with( 'No saved value for post_max_size', function () {
			$this->cli->get( array( 'post_max_size' ) );
		} );
	}

	/* ─── set ─── */

	public function test_set_saves_php_setting_merged_with_existing_values() {
		$this->cli->set( array( 'memory_limit', '512m' ) );

		$this->assertSame(
			array(
				array(
					'save_php_settings',
					array(
						'memory_limit'   => '512m',
						'max_input_vars' => '5000',
						'custom_php_ini' => 'date.timezone = UTC',
					),
				),
			),
			$this->general->calls
		);
		$this->assertSame( array( array( 'success', 'memory_limit set to 512m.' ) ), $this->calls_of( 'success' ) );
	}

	public function test_set_saves_wp_memory_setting_merged_with_existing_values() {
		$this->cli->set( array( 'wp_max_memory_limit', '512M' ) );

		$this->assertSame(
			array(
				array(
					'save_wp_memory_settings',
					array(
						'wp_memory_limit'     => '128M',
						'wp_max_memory_limit' => '512M',
					),
				),
			),
			$this->general->calls
		);
	}

	public function test_set_trims_the_value() {
		$this->cli->set( array( 'max_execution_time', ' 300 ' ) );

		$this->assertSame( '300', $this->general->calls[0][1]['max_execution_time'] );
	}

	public function test_set_rejects_an_unknown_setting_without_saving() {
		$this->assert_halts_with( 'Unknown setting "auto_prepend_file"', function () {
			$this->cli->set( array( 'auto_prepend_file', '/tmp/x.php' ) );
		} );
		$this->assertSame( array(), $this->general->calls );
	}

	public function test_set_rejects_an_invalid_value_without_saving() {
		$this->assert_halts_with( 'Invalid format for memory_limit', function () {
			$this->cli->set( array( 'memory_limit', '512MB' ) );
		} );
		$this->assertSame( array(), $this->general->calls );
	}

	public function test_set_rejects_an_out_of_range_value_without_saving() {
		$this->assert_halts_with( 'max_input_vars must be between 100 and 100000', function () {
			$this->cli->set( array( 'max_input_vars', '50' ) );
		} );
		$this->assertSame( array(), $this->general->calls );
	}

	public function test_set_rejects_an_empty_value_without_saving() {
		$this->assert_halts_with( 'Value cannot be empty', function () {
			$this->cli->set( array( 'memory_limit', '  ' ) );
		} );
		$this->assertSame( array(), $this->general->calls );
	}

	public function test_set_reports_module_warnings_and_still_succeeds() {
		$this->general->messages = array( array( 'warning', 'post_max_size should be larger than upload_max_filesize.' ) );

		$this->cli->set( array( 'memory_limit', '512M' ) );

		$this->assertSame(
			array( array( 'warning', 'post_max_size should be larger than upload_max_filesize.' ) ),
			$this->calls_of( 'warning' )
		);
		$this->assertCount( 1, $this->calls_of( 'success' ) );
	}

	public function test_set_logs_module_success_messages() {
		$this->general->messages = array( array( 'updated', 'Settings saved and written to: .user.ini.' ) );

		$this->cli->set( array( 'memory_limit', '512M' ) );

		$this->assertSame( array( array( 'log', 'Settings saved and written to: .user.ini.' ) ), $this->calls_of( 'log' ) );
	}

	public function test_set_fails_when_the_module_reports_an_error() {
		$this->general->messages = array( array( 'error', 'wp-config.php is not writable.' ) );

		$this->assert_halts_with( 'wp-config.php is not writable.', function () {
			$this->cli->set( array( 'wp_memory_limit', '256M' ) );
		} );
		$this->assertSame( array(), $this->calls_of( 'success' ) );
	}

	public function test_set_errors_when_the_module_is_not_loaded() {
		$this->plugin->modules = array();

		$this->assert_halts_with( 'module is not loaded', function () {
			$this->cli->set( array( 'memory_limit', '512M' ) );
		} );
	}

	/* ─── debug ─── */

	public function test_debug_on_enables_debug_and_logging_but_not_display() {
		$this->cli->debug( array( 'on' ), array() );

		$this->assertSame(
			array( array( 'save_debug_settings', array( 'wp_debug' => '1', 'wp_debug_log' => '1' ) ) ),
			$this->tools->calls
		);
		$this->assertSame(
			array( array( 'success', 'Debugging enabled: WP_DEBUG, WP_DEBUG_LOG.' ) ),
			$this->calls_of( 'success' )
		);
	}

	public function test_debug_on_with_flags_also_enables_display_and_script_debug() {
		$this->cli->debug( array( 'on' ), array( 'display' => true, 'script-debug' => true ) );

		$this->assertSame(
			array( 'wp_debug' => '1', 'wp_debug_log' => '1', 'wp_debug_display' => '1', 'script_debug' => '1' ),
			$this->tools->calls[0][1]
		);
	}

	public function test_debug_off_disables_every_constant() {
		$this->cli->debug( array( 'off' ), array() );

		$this->assertSame( array( array( 'save_debug_settings', array() ) ), $this->tools->calls );
		$this->assertSame( array( array( 'success', 'Debugging disabled.' ) ), $this->calls_of( 'success' ) );
	}

	public function test_debug_rejects_an_unknown_state() {
		$this->assert_halts_with( 'Use "on" or "off"', function () {
			$this->cli->debug( array( 'maybe' ), array() );
		} );
		$this->assertSame( array(), $this->tools->calls );
	}

	public function test_debug_fails_when_wp_config_cannot_be_written() {
		$this->tools->messages = array( array( 'error', 'Failed to update wp-config.php. Please check error log.' ) );

		$this->assert_halts_with( 'Failed to update wp-config.php', function () {
			$this->cli->debug( array( 'on' ), array() );
		} );
	}

	/* ─── remove-ini ─── */

	public function test_remove_ini_reports_cleaned_files() {
		$this->removed = array( '.user.ini', 'php.ini' );

		$this->cli->remove_ini( array(), array() );

		$this->assertSame(
			array( array( 'success', 'Removed Easy PHP Settings directives from: .user.ini, php.ini.' ) ),
			WP_CLI::$calls
		);
	}

	public function test_remove_ini_reports_when_there_is_nothing_to_remove() {
		$this->removed = array();

		$this->cli->remove_ini( array(), array() );

		$this->assertSame( array( array( 'success', 'No Easy PHP Settings directives found; nothing to remove.' ) ), WP_CLI::$calls );
	}

	public function test_remove_ini_fails_on_filesystem_error() {
		$this->removed = new WP_Error( 'filesystem_unavailable', 'WordPress filesystem could not be initialised.' );

		$this->assert_halts_with( 'WordPress filesystem could not be initialised.', function () {
			$this->cli->remove_ini( array(), array() );
		} );
	}
}
