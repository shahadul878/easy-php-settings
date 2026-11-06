<?php
/**
 * Tests for working out how a PHP setting can be changed on this server.
 *
 * @package EasyPHPSettings
 */

use PHPUnit\Framework\TestCase;

/**
 * Easy_Ini_Change_Method.
 */
class EasyIniChangeMethodTest extends TestCase {

	public function test_fpm_reads_user_ini_when_filename_is_set() {
		$this->assertTrue( Easy_Ini_Change_Method::server_reads_user_ini( 'fpm-fcgi', '.user.ini' ) );
	}

	public function test_cgi_reads_user_ini_when_filename_is_set() {
		$this->assertTrue( Easy_Ini_Change_Method::server_reads_user_ini( 'cgi-fcgi', '.user.ini' ) );
	}

	public function test_user_ini_is_not_read_when_filename_is_empty() {
		$this->assertFalse( Easy_Ini_Change_Method::server_reads_user_ini( 'fpm-fcgi', '' ) );
	}

	public function test_apache_module_does_not_read_user_ini() {
		$this->assertFalse( Easy_Ini_Change_Method::server_reads_user_ini( 'apache2handler', '.user.ini' ) );
	}

	public function test_per_directory_setting_uses_user_ini_on_fpm() {
		$this->assertSame( 'user_ini', Easy_Ini_Change_Method::for_access( INI_PERDIR, 'fpm-fcgi', '.user.ini' ) );
	}

	public function test_ini_all_setting_uses_user_ini_on_fpm() {
		$this->assertSame( 'user_ini', Easy_Ini_Change_Method::for_access( INI_ALL, 'fpm-fcgi', '.user.ini' ) );
	}

	public function test_per_directory_setting_uses_htaccess_on_apache_module() {
		$this->assertSame( 'htaccess', Easy_Ini_Change_Method::for_access( INI_PERDIR, 'apache2handler', '.user.ini' ) );
	}

	public function test_per_directory_setting_needs_host_when_no_file_is_read() {
		$this->assertSame( 'host', Easy_Ini_Change_Method::for_access( INI_PERDIR, 'litespeed', '' ) );
	}

	public function test_system_setting_always_needs_host() {
		$this->assertSame( 'host', Easy_Ini_Change_Method::for_access( INI_SYSTEM, 'fpm-fcgi', '.user.ini' ) );
	}

	public function test_unknown_access_needs_host() {
		$this->assertSame( 'host', Easy_Ini_Change_Method::for_access( 0, 'fpm-fcgi', '.user.ini' ) );
	}
}
