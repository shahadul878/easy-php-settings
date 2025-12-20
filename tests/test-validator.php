<?php
/**
 * Tests for Easy_Settings_Validator class
 *
 * @package EasyPHPSettings
 * @since 1.0.5
 */

/**
 * Test class for Easy_Settings_Validator
 */
class Test_Easy_Settings_Validator extends WP_UnitTestCase {

	/**
	 * Test memory limit validation
	 */
	public function test_validate_memory_limit() {
		// Valid values.
		$this->assertTrue( Easy_Settings_Validator::validate_setting( 'memory_limit', '128M' ) );
		$this->assertTrue( Easy_Settings_Validator::validate_setting( 'memory_limit', '256M' ) );
		$this->assertTrue( Easy_Settings_Validator::validate_setting( 'memory_limit', '512M' ) );

		// Invalid values.
		$this->assertWPError( Easy_Settings_Validator::validate_setting( 'memory_limit', 'invalid' ) );
		$this->assertWPError( Easy_Settings_Validator::validate_setting( 'memory_limit', 'abc123' ) );
	}

	/**
	 * Test max_execution_time validation
	 */
	public function test_validate_max_execution_time() {
		// Valid values.
		$this->assertTrue( Easy_Settings_Validator::validate_setting( 'max_execution_time', '30' ) );
		$this->assertTrue( Easy_Settings_Validator::validate_setting( 'max_execution_time', '300' ) );

		// Invalid values.
		$this->assertWPError( Easy_Settings_Validator::validate_setting( 'max_execution_time', '-1' ) );
		$this->assertWPError( Easy_Settings_Validator::validate_setting( 'max_execution_time', '100000' ) );
	}

	/**
	 * Test settings relationships validation
	 */
	public function test_validate_settings_relationships() {
		$settings = array(
			'post_max_size'       => '64M',
			'upload_max_filesize' => '128M', // Invalid: post_max_size < upload_max_filesize.
		);

		$errors = Easy_Settings_Validator::validate_settings_relationships( $settings );
		$this->assertNotEmpty( $errors );
		$this->assertContains( 'post_max_size should be larger', $errors[0] );
	}

	/**
	 * Test convert_to_bytes
	 */
	public function test_convert_to_bytes() {
		$this->assertEquals( 1024, Easy_Settings_Validator::convert_to_bytes( '1K' ) );
		$this->assertEquals( 1048576, Easy_Settings_Validator::convert_to_bytes( '1M' ) );
		$this->assertEquals( 1073741824, Easy_Settings_Validator::convert_to_bytes( '1G' ) );
		$this->assertEquals( 128, Easy_Settings_Validator::convert_to_bytes( '128' ) );
	}
}

