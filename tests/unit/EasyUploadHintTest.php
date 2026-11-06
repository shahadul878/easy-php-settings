<?php
/**
 * Tests for the Media upload hint.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Upload_Hint.
 */
class EasyUploadHintTest extends TestCase {

	public function test_upload_max_filesize_limits_when_smaller() {
		$this->assertSame( 'upload_max_filesize', Easy_Upload_Hint::limiting_setting( '64M', '128M' ) );
	}

	public function test_post_max_size_limits_when_smaller() {
		$this->assertSame( 'post_max_size', Easy_Upload_Hint::limiting_setting( '300M', '8M' ) );
	}

	public function test_units_are_compared_in_bytes() {
		$this->assertSame( 'post_max_size', Easy_Upload_Hint::limiting_setting( '2G', '1024M' ) );
	}

	public function test_equal_values_name_upload_max_filesize() {
		$this->assertSame( 'upload_max_filesize', Easy_Upload_Hint::limiting_setting( '128M', '128M' ) );
	}

	public function test_unlimited_post_size_leaves_upload_max_filesize() {
		$this->assertSame( 'upload_max_filesize', Easy_Upload_Hint::limiting_setting( '64M', '0' ) );
	}

	public function test_message_names_setting_value_and_links_to_plugin() {
		$html = Easy_Upload_Hint::message_html( 'post_max_size', '8M' );

		$this->assertStringContainsString( 'post_max_size', $html );
		$this->assertStringContainsString( '8M', $html );
		$this->assertStringContainsString( 'tools.php?page=easy-php-settings', $html );
	}
}
