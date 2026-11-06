<?php
/**
 * PHPUnit bootstrap for pure-logic unit tests (no WordPress runtime).
 *
 * @package EasyPHPSettings
 */

define( 'WPINC', 'wp-includes' );
define( 'ABSPATH', sys_get_temp_dir() . '/' );

require_once __DIR__ . '/stubs/wordpress.php';
require_once __DIR__ . '/stubs/fakes.php';

require_once dirname( __DIR__ ) . '/includes/class-easyinifile.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-settings-validator.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-php-settings-cli.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-ini-change-method.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-limit-health.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-site-health.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-live-check.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-upload-hint.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-debug-log-parser.php';
require_once dirname( __DIR__ ) . '/includes/class-easy-module-base.php';
require_once dirname( __DIR__ ) . '/modules/class-module-review-request.php';
