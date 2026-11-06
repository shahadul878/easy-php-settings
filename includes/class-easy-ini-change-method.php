<?php
/**
 * Works out how a PHP setting can be changed on this server.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Ini_Change_Method
 *
 * PHP reports an access level for each directive (ini_get_all()). Settings
 * with INI_PERDIR in their access level can be set per directory: through
 * .user.ini on CGI/FastCGI servers, or php_value in .htaccess under the
 * Apache module. INI_SYSTEM settings can only be set in the server's php.ini.
 */
class Easy_Ini_Change_Method {

	/**
	 * The plugin writes the value to .user.ini and the server reads it.
	 */
	const USER_INI = 'user_ini';

	/**
	 * The value has to go in .htaccess (Apache module).
	 */
	const HTACCESS = 'htaccess';

	/**
	 * Only the host can change it, in the server's php.ini.
	 */
	const HOST = 'host';

	/**
	 * Server APIs that read per-directory .user.ini files.
	 *
	 * @var string[]
	 */
	private static $user_ini_sapis = array( 'cgi', 'cgi-fcgi', 'fpm-fcgi' );

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Whether the server applies .user.ini files.
	 *
	 * @param string $sapi              Result of php_sapi_name().
	 * @param string $user_ini_filename Value of the user_ini.filename directive.
	 * @return bool
	 */
	public static function server_reads_user_ini( $sapi, $user_ini_filename ) {
		return '' !== (string) $user_ini_filename && in_array( $sapi, self::$user_ini_sapis, true );
	}

	/**
	 * How a setting with the given access level can be changed.
	 *
	 * @param int    $access            Access bitmask from ini_get_all().
	 * @param string $sapi              Result of php_sapi_name().
	 * @param string $user_ini_filename Value of the user_ini.filename directive.
	 * @return string One of the class constants.
	 */
	public static function for_access( $access, $sapi, $user_ini_filename ) {
		if ( ! ( (int) $access & INI_PERDIR ) ) {
			return self::HOST;
		}
		if ( self::server_reads_user_ini( $sapi, $user_ini_filename ) ) {
			return self::USER_INI;
		}
		return 'apache2handler' === $sapi ? self::HTACCESS : self::HOST;
	}

	/**
	 * How a named setting can be changed on the current server.
	 *
	 * @param string $key PHP directive name.
	 * @return string One of the class constants.
	 */
	public static function for_setting( $key ) {
		$all    = ini_get_all( null, true );
		$access = isset( $all[ $key ]['access'] ) ? $all[ $key ]['access'] : 0;
		return self::for_access( $access, php_sapi_name(), (string) ini_get( 'user_ini.filename' ) );
	}
}
