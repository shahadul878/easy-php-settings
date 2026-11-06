<?php
/**
 * REST API routes.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Rest_Api
 *
 * REST requests do not run admin_init, so the plugin never calls ini_set()
 * here: values read in these routes are the ones PHP really uses.
 */
class Easy_Rest_Api {

	const NAMESPACE_V1 = 'easy-php-settings/v1';

	/**
	 * Plugin orchestrator.
	 *
	 * @var Easy_PHP_Settings
	 */
	private $plugin;

	/**
	 * Site Health tests.
	 *
	 * @var Easy_Site_Health
	 */
	private $site_health;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin      Plugin orchestrator.
	 * @param Easy_Site_Health  $site_health Site Health tests.
	 */
	public function __construct( $plugin, $site_health ) {
		$this->plugin      = $plugin;
		$this->site_health = $site_health;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/live-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'live_check' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/site-health/limits',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this->site_health, 'run_limits_test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/site-health/applied',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this->site_health, 'run_applied_test' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Values PHP really uses, with the General tab's "In use" cells redrawn.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function live_check() {
		$module = $this->plugin->get_module_manager()->get_module( 'general_settings' );
		if ( ! $module ) {
			return new WP_Error( 'easy_php_settings_module_missing', __( 'The General Settings module is not loaded.', 'easy-php-settings' ), array( 'status' => 500 ) );
		}

		$live_check     = $this->plugin->get_live_check();
		$reads_user_ini = $live_check->server_reads_user_ini();
		$blocked        = Easy_Live_Check::blocked_rows(
			$live_check->php_rows(),
			$reads_user_ini,
			$live_check->seconds_since_save(),
			$live_check->cache_ttl()
		);

		$response = rest_ensure_response(
			array(
				'settings'       => $module->render_php_states(),
				'reads_user_ini' => $reads_user_ini,
				'blocked'        => array_map(
					function ( $row ) {
						return array(
							'key'   => $row['key'],
							'saved' => $row['saved'],
							'live'  => $row['live'],
						);
					},
					$blocked
				),
				'host_request'   => Easy_Live_Check::host_request_text( $blocked, home_url() ),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Only users who can manage the plugin may read server details.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( $this->plugin->get_capability() );
	}
}
