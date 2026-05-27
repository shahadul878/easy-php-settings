<?php
/**
 * Review Request Module
 *
 * Shows a one-time popup asking the user to leave a WordPress.org review
 * after they have used the plugin for a while. Provides "Rate now",
 * "Remind me later", and "Already did / Dismiss" actions.
 *
 * @package EasyPHPSettings
 * @since   1.1.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_Review_Request extends Easy_Module_Base {

	protected $module_id          = 'review_request';
	protected $module_name        = 'Review Request';
	protected $module_description = 'Prompts the user to leave a review on WordPress.org';

	/**
	 * Minimum age (in seconds) the plugin must be installed before the prompt
	 * is shown for the first time.
	 */
	const INITIAL_DELAY = 7 * DAY_IN_SECONDS;

	/**
	 * Snooze duration when the user clicks "Remind me later".
	 */
	const SNOOZE_DURATION = 7 * DAY_IN_SECONDS;

	/**
	 * Option key that stores the review prompt state.
	 */
	const STATE_OPTION = 'easy_php_settings_review_state';

	/**
	 * Option key that stores the first-install timestamp.
	 */
	const INSTALL_TIME_OPTION = 'easy_php_settings_install_time';

	/**
	 * WordPress.org review URL.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/easy-php-settings/reviews/?filter=5#new-post';

	/* ─── Hooks ───────────────────────────────── */

	public function register_hooks() {
		// Make sure the install timestamp exists even for sites that had the
		// plugin installed before this module was added.
		add_action( 'admin_init', array( $this, 'maybe_seed_install_time' ) );

		add_action( 'admin_notices', array( $this, 'maybe_render_popup' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_render_popup' ) );

		add_action( 'wp_ajax_easy_php_settings_review_action', array( $this, 'handle_ajax_action' ) );
	}

	public function enqueue_assets( $hook ) {
		// Only enqueue when the popup will actually render. We test the same
		// gate the renderer uses so we don't ship assets unnecessarily.
		if ( ! $this->should_show_popup() ) {
			return;
		}

		// The main stylesheet is only registered on the plugin's own admin
		// page; re-register it on the other screens that host the popup so
		// the modal styles are always available.
		if ( ! wp_style_is( 'easy-php-settings-styles', 'enqueued' ) ) {
			wp_enqueue_style(
				'easy-php-settings-styles',
				plugin_dir_url( dirname( __FILE__ ) ) . 'css/admin-styles.css',
				array( 'dashicons' ),
				$this->plugin->get_version()
			);
		}

		wp_enqueue_script(
			'easy-php-settings-review-request',
			plugin_dir_url( dirname( __FILE__ ) ) . 'js/review-request.js',
			array( 'jquery' ),
			$this->plugin->get_version(),
			true
		);

		wp_localize_script(
			'easy-php-settings-review-request',
			'easyPhpSettingsReview',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'easy_php_settings_review' ),
				'reviewUrl' => self::REVIEW_URL,
			)
		);
	}

	/* ─── State helpers ───────────────────────── */

	public function maybe_seed_install_time() {
		if ( ! $this->plugin->get_option( self::INSTALL_TIME_OPTION ) ) {
			$this->plugin->update_option( self::INSTALL_TIME_OPTION, time() );
		}
	}

	/**
	 * Get the current review state.
	 *
	 * @return array
	 */
	private function get_state() {
		$defaults = array(
			'dismissed'    => false,
			'rated'        => false,
			'snooze_until' => 0,
		);

		$state = $this->plugin->get_option( self::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return array_merge( $defaults, $state );
	}

	/**
	 * Return a new state array with the given keys overridden. Caller must
	 * persist with update_option.
	 *
	 * @param array $changes Key/value overrides.
	 * @return array
	 */
	private function with_state_changes( array $changes ) {
		return array_merge( $this->get_state(), $changes );
	}

	/* ─── Display logic ───────────────────────── */

	/**
	 * Decide whether the popup should render on the current admin request.
	 *
	 * @return bool
	 */
	private function should_show_popup() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			return false;
		}

		// Don't show during AJAX or on the network upgrade screen.
		if ( wp_doing_ajax() ) {
			return false;
		}

		$state = $this->get_state();
		if ( $state['dismissed'] || $state['rated'] ) {
			return false;
		}

		if ( $state['snooze_until'] && time() < (int) $state['snooze_until'] ) {
			return false;
		}

		$install_time = (int) $this->plugin->get_option( self::INSTALL_TIME_OPTION );
		if ( ! $install_time ) {
			// Will be seeded by maybe_seed_install_time on this same request;
			// skip until next page load to enforce the delay window.
			return false;
		}

		if ( ( time() - $install_time ) < self::INITIAL_DELAY ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the admin notice prompting for a review.
	 */
	public function maybe_render_popup() {
		if ( ! $this->should_show_popup() ) {
			return;
		}

		// Some admin pages don't trigger admin_enqueue_scripts in time
		// (e.g. notices fire very early). Make sure the script is loaded.
		if ( ! wp_script_is( 'easy-php-settings-review-request', 'enqueued' ) ) {
			$this->enqueue_assets( '' );
		}
		?>
		<div id="easy-php-settings-review-notice" class="notice notice-info easy-php-review-notice">
			<div class="easy-php-review-notice-inner">
				<div class="easy-php-review-notice-icon" aria-hidden="true">
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
				</div>

				<div class="easy-php-review-notice-body">
					<p class="easy-php-review-notice-title">
						<strong><?php esc_html_e( 'Enjoying Easy PHP Settings?', 'easy-php-settings' ); ?></strong>
					</p>
					<p class="easy-php-review-notice-message">
						<?php esc_html_e( 'You\'ve been using Easy PHP Settings for a while now — awesome! Would you mind taking a moment to leave a quick 5-star review on WordPress.org? It would mean a lot and helps other users discover the plugin.', 'easy-php-settings' ); ?>
					</p>
					<p class="easy-php-review-notice-actions">
						<button type="button" class="button button-primary easy-php-review-btn" data-review-action="rate">
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Sure, I\'d love to!', 'easy-php-settings' ); ?>
						</button>
						<button type="button" class="button easy-php-review-btn" data-review-action="later">
							<?php esc_html_e( 'Maybe later', 'easy-php-settings' ); ?>
						</button>
						<button type="button" class="button-link easy-php-review-btn-link" data-review-action="dismiss">
							<?php esc_html_e( 'I already did / Don\'t show again', 'easy-php-settings' ); ?>
						</button>
					</p>
				</div>

				<button type="button" class="notice-dismiss easy-php-review-notice-close" data-review-action="later">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'easy-php-settings' ); ?></span>
				</button>
			</div>
		</div>
		<?php
	}

	/* ─── AJAX handler ────────────────────────── */

	public function handle_ajax_action() {
		check_ajax_referer( 'easy_php_settings_review', 'nonce' );

		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'easy-php-settings' ) ), 403 );
		}

		$action = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';

		switch ( $action ) {
			case 'rate':
				$next_state = $this->with_state_changes( array( 'rated' => true ) );
				break;

			case 'later':
				$next_state = $this->with_state_changes(
					array( 'snooze_until' => time() + self::SNOOZE_DURATION )
				);
				break;

			case 'dismiss':
				$next_state = $this->with_state_changes( array( 'dismissed' => true ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'easy-php-settings' ) ), 400 );
		}

		$this->plugin->update_option( self::STATE_OPTION, $next_state );

		wp_send_json_success( array( 'state' => $next_state ) );
	}
}
