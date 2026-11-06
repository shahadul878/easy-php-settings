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
	 * Snooze duration when the user clicks "Not now".
	 */
	const SNOOZE_DURATION = 30 * DAY_IN_SECONDS;

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
	const REVIEW_URL = 'https://wordpress.org/support/plugin/easy-php-settings/reviews/';

	/* ─── Hooks ───────────────────────────────── */

	public function register_hooks() {
		// Make sure the install timestamp exists even for sites that had the
		// plugin installed before this module was added.
		add_action( 'admin_init', array( $this, 'maybe_seed_install_time' ) );

		add_action( 'wp_ajax_easy_php_settings_review_action', array( $this, 'handle_ajax_action' ) );
	}

	public function enqueue_assets( $hook ) {
		// The prompt lives on the plugin's General tab only.
		if ( 'tools_page_easy-php-settings' !== $hook && 'tools_page_easy-php-settings-network' !== $hook ) {
			return;
		}
		if ( ! $this->is_eligible() ) {
			return;
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
	 * Whether the user has left the choice open (not rated, not "don't ask
	 * again", not snoozed).
	 *
	 * @param array $state State with dismissed, rated and snooze_until.
	 * @param int   $now   Current Unix time.
	 * @return bool
	 */
	public static function may_ask( array $state, $now ) {
		if ( ! empty( $state['dismissed'] ) || ! empty( $state['rated'] ) ) {
			return false;
		}
		$snooze_until = isset( $state['snooze_until'] ) ? (int) $state['snooze_until'] : 0;
		return $snooze_until <= $now;
	}

	/**
	 * Whether the current user may be asked on this request.
	 *
	 * @return bool
	 */
	private function is_eligible() {
		return is_admin()
			&& ! wp_doing_ajax()
			&& current_user_can( $this->plugin->get_capability() )
			&& self::may_ask( $this->get_state(), time() );
	}

	/**
	 * Print the review request, hidden.
	 *
	 * The General tab reveals it only after its live check confirms that the
	 * user's saved PHP values are really in use, i.e. the plugin has helped.
	 *
	 * @return void
	 */
	public function render_inline_prompt() {
		if ( ! $this->is_eligible() ) {
			return;
		}
		?>
		<div id="easy-php-settings-review-notice" class="eps-review" role="status" hidden>
			<span class="eps-review__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<div class="eps-review__body">
				<p class="eps-review__title"><?php esc_html_e( 'Your saved PHP settings are working', 'easy-php-settings' ); ?></p>
				<p class="eps-review__text"><?php esc_html_e( 'If Easy PHP Settings helped, a short review on WordPress.org helps other site owners find it.', 'easy-php-settings' ); ?></p>
				<p class="eps-review__actions">
					<button type="button" class="button button-primary" data-review-action="rate">
						<?php esc_html_e( 'Leave a review', 'easy-php-settings' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'easy-php-settings' ); ?></span>
					</button>
					<button type="button" class="button" data-review-action="later"><?php esc_html_e( 'Not now', 'easy-php-settings' ); ?></button>
					<button type="button" class="button-link" data-review-action="dismiss"><?php esc_html_e( 'Don\'t ask again', 'easy-php-settings' ); ?></button>
				</p>
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
