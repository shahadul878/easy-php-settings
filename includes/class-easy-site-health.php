<?php
/**
 * Site Health tests.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Site_Health
 *
 * Adds two tests to Tools → Site Health. Both run asynchronously through the
 * plugin's REST API, because on admin pages the plugin applies saved values
 * with ini_set(), which would hide whether the server really uses them.
 */
class Easy_Site_Health {

	const LIMITS_TEST  = 'easy_php_settings_limits';
	const APPLIED_TEST = 'easy_php_settings_applied';

	/**
	 * Live check service.
	 *
	 * @var Easy_Live_Check
	 */
	private $live_check;

	/**
	 * Constructor.
	 *
	 * @param Easy_Live_Check $live_check Live check service.
	 */
	public function __construct( $live_check ) {
		$this->live_check = $live_check;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'site_status_tests', array( $this, 'add_tests' ) );
	}

	/**
	 * Register the async tests.
	 *
	 * @param array $tests Site Health tests.
	 * @return array
	 */
	public function add_tests( $tests ) {
		$tests['async'][ self::LIMITS_TEST ]  = array(
			'label'             => __( 'PHP limits', 'easy-php-settings' ),
			'test'              => rest_url( Easy_Rest_Api::NAMESPACE_V1 . '/site-health/limits' ),
			'has_rest'          => true,
			'async_direct_test' => array( $this, 'run_limits_test' ),
		);
		$tests['async'][ self::APPLIED_TEST ] = array(
			'label'             => __( 'Saved PHP settings in use', 'easy-php-settings' ),
			'test'              => rest_url( Easy_Rest_Api::NAMESPACE_V1 . '/site-health/applied' ),
			'has_rest'          => true,
			'async_direct_test' => array( $this, 'run_applied_test' ),
		);
		return $tests;
	}

	/**
	 * Run the limits test in the current request.
	 *
	 * @return array Site Health result.
	 */
	public function run_limits_test() {
		return self::limits_result( $this->live_check->php_rows() );
	}

	/**
	 * Run the "saved values in use" test in the current request.
	 *
	 * @return array Site Health result.
	 */
	public function run_applied_test() {
		return self::applied_result(
			$this->live_check->php_rows(),
			$this->live_check->server_reads_user_ini(),
			$this->live_check->seconds_since_save(),
			$this->live_check->cache_ttl()
		);
	}

	/* ─── Result builders (pure) ─── */

	/**
	 * Result for "are PHP limits high enough?".
	 *
	 * @param array[] $rows Rows with key, live, recommended and health.
	 * @return array Site Health result.
	 */
	public static function limits_result( array $rows ) {
		$low = array_filter(
			$rows,
			function ( $row ) {
				return Easy_Limit_Health::LOW === $row['health'];
			}
		);

		if ( empty( $low ) ) {
			return self::result(
				self::LIMITS_TEST,
				'good',
				__( 'PHP limits meet WordPress recommendations', 'easy-php-settings' ),
				__( 'Memory, upload, execution time and input limits are at or above the values Easy PHP Settings recommends.', 'easy-php-settings' ),
				''
			);
		}

		$items = array();
		foreach ( $low as $row ) {
			/* translators: 1: setting name, 2: value in use, 3: recommended value */
			$items[] = sprintf( __( '%1$s is %2$s (recommended: %3$s)', 'easy-php-settings' ), $row['key'], $row['live'], $row['recommended'] );
		}

		return self::result(
			self::LIMITS_TEST,
			'recommended',
			__( 'Some PHP limits are lower than recommended', 'easy-php-settings' ),
			__( 'Low limits can cause failed uploads, out-of-memory errors or timeouts on larger pages and imports.', 'easy-php-settings' ),
			self::list_html( $items )
		);
	}

	/**
	 * Result for "is the server using the values saved in the plugin?".
	 *
	 * @param array[] $rows               Rows with key, saved, live and applied.
	 * @param bool    $reads_user_ini     Whether the server reads .user.ini.
	 * @param int     $seconds_since_save Seconds since settings were last saved.
	 * @param int     $cache_ttl          Seconds PHP caches .user.ini for.
	 * @return array Site Health result.
	 */
	public static function applied_result( array $rows, $reads_user_ini, $seconds_since_save, $cache_ttl ) {
		$pending = array_filter(
			$rows,
			function ( $row ) {
				return Easy_Limit_Health::PENDING === $row['applied'];
			}
		);

		if ( empty( $pending ) ) {
			return self::result(
				self::APPLIED_TEST,
				'good',
				__( 'PHP settings from Easy PHP Settings are in use', 'easy-php-settings' ),
				__( 'Every PHP value saved in Easy PHP Settings is the value the server is using.', 'easy-php-settings' ),
				''
			);
		}

		$blocked = Easy_Live_Check::blocked_rows( $rows, $reads_user_ini, $seconds_since_save, $cache_ttl );
		if ( empty( $blocked ) ) {
			return self::result(
				self::APPLIED_TEST,
				'good',
				__( 'PHP settings were saved recently', 'easy-php-settings' ),
				/* translators: %d: seconds PHP caches .user.ini for */
				sprintf( __( 'PHP picks up .user.ini changes within %d seconds. Check again shortly.', 'easy-php-settings' ), $cache_ttl ),
				''
			);
		}

		$items = array();
		foreach ( $blocked as $row ) {
			/* translators: 1: setting name, 2: saved value, 3: value in use */
			$items[] = sprintf( __( '%1$s: saved %2$s, in use %3$s', 'easy-php-settings' ), $row['key'], $row['saved'], $row['live'] );
		}

		$why = $reads_user_ini
			? __( 'The server reads .user.ini, but these values are not being used. Your host may set them in a way that cannot be overridden; ask them to change them, or use the snippets on the General tab.', 'easy-php-settings' )
			: __( 'This server does not read .user.ini, so the plugin cannot apply these values. Use the .htaccess or php.ini snippets on the General tab, or ask your host.', 'easy-php-settings' );

		return self::result(
			self::APPLIED_TEST,
			'recommended',
			__( 'Some PHP settings saved in Easy PHP Settings are not in use', 'easy-php-settings' ),
			$why,
			self::list_html( $items )
		);
	}

	/**
	 * Build a Site Health result array.
	 *
	 * @param string $test        Test ID.
	 * @param string $status      good, recommended or critical.
	 * @param string $label       Plain-text heading.
	 * @param string $explanation Plain-text explanation.
	 * @param string $extra_html  Already-escaped extra HTML.
	 * @return array
	 */
	private static function result( $test, $status, $label, $explanation, $extra_html ) {
		$settings_url = admin_url( 'tools.php?page=easy-php-settings' );
		return array(
			'test'        => $test,
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Performance', 'easy-php-settings' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $explanation ) . '</p>' . $extra_html,
			'actions'     => 'good' === $status
				? ''
				: '<p><a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Open Easy PHP Settings', 'easy-php-settings' ) . '</a></p>',
		);
	}

	/**
	 * Escape and wrap items in a list.
	 *
	 * @param string[] $items Plain-text items.
	 * @return string
	 */
	private static function list_html( array $items ) {
		return '<ul>' . implode(
			'',
			array_map(
				function ( $item ) {
					return '<li>' . esc_html( $item ) . '</li>';
				},
				$items
			)
		) . '</ul>';
	}
}
