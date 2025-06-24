<?php
/**
 * INIFile class for easy-php-settings plugin
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}

class EasyIniFile
{
    private function __construct() {}

    public static function get_dir_path()
    {
        return ABSPATH;
    }

    public static function get_ini_file_names()
    {
        return [
            self::get_dir_path() . '.user.ini',
            self::get_dir_path() . 'php.ini',
        ];
    }

    public static function write( $content )
    {
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $files_written = [];
        foreach ( self::get_ini_file_names() as $filepath ) {
            if ( $wp_filesystem->put_contents( $filepath, $content ) ) {
                $files_written[] = basename($filepath);
            }
        }
        return $files_written;
    }

    public static function remove_files()
    {
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $files_deleted = [];
        foreach ( self::get_ini_file_names() as $filepath ) {
            if ( $wp_filesystem->exists( $filepath ) ) {
                if ( $wp_filesystem->delete( $filepath ) ) {
                    $files_deleted[] = basename($filepath);
                }
            }
        }
        return $files_deleted;
    }

    public static function is_writable()
    {
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem->is_writable( self::get_dir_path() );
    }
} 