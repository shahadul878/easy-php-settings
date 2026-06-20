<?php
/**
 * PHP Settings Tab Module
 *
 * Handles PHP settings table display.
 *
 * @package EasyPHPSettings
 * @since 1.0.6
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * PHP Settings Tab Module Class
 */
class Easy_Module_PHP_Settings extends Easy_Module_Base {

	/**
	 * Module ID.
	 *
	 * @var string
	 */
	protected $module_id = 'php_settings';

	/**
	 * Module name.
	 *
	 * @var string
	 */
	protected $module_name = 'PHP Settings';

	/**
	 * Module description.
	 *
	 * @var string
	 */
	protected $module_description = 'PHP settings information table';

	/**
	 * Get admin tab configuration.
	 *
	 * @return array
	 */
	public function get_admin_tab() {
		return array(
			'id'       => 'php_settings',
			'title'    => __( 'PHP Settings', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	/**
	 * Render the PHP settings tab.
	 *
	 * @return void
	 */
	public function render_tab() {
		$directive_count = 0;
		if ( class_exists( 'EasyPHPInfo' ) ) {
			$info = EasyPHPInfo::get_as_array();
			if ( isset( $info['Core'] ) && is_array( $info['Core'] ) ) {
				$directive_count = count( $info['Core'] );
			}
		}
		?>
		<div id="php-settings-tab" class="easy-php-php-settings-panel">
			<div class="easy-php-php-settings-header">
				<div class="easy-php-php-settings-header-text">
					<h3><?php esc_html_e( 'PHP Settings Table', 'easy-php-settings' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Browse every loaded PHP directive, filter quickly, and copy selected values to your clipboard.', 'easy-php-settings' ); ?>
					</p>
				</div>
				<div class="easy-php-php-settings-stat" aria-live="polite">
					<span class="easy-php-php-settings-stat-value" id="php-settings-total-count"><?php echo esc_html( (string) $directive_count ); ?></span>
					<span class="easy-php-php-settings-stat-label"><?php esc_html_e( 'directives', 'easy-php-settings' ); ?></span>
				</div>
			</div>

			<div class="easy-php-php-settings-toolbar">
				<label class="easy-php-php-settings-search-wrap" for="php-settings-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input
						type="search"
						id="php-settings-search"
						placeholder="<?php esc_attr_e( 'Search directives, values, or status…', 'easy-php-settings' ); ?>"
						autocomplete="off"
					/>
				</label>
				<div class="easy-php-php-settings-toolbar-actions">
					<span class="easy-php-php-settings-result-count" id="php-settings-visible-count" aria-live="polite"></span>
					<button type="button" class="button button-secondary" id="php-settings-copy-selected">
						<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
						<?php esc_html_e( 'Copy Selected', 'easy-php-settings' ); ?>
					</button>
				</div>
			</div>

			<div id="php-settings-table-wrapper" class="easy-php-php-settings-table-wrap">
				<?php
				if ( class_exists( 'EasyPHPInfo' ) ) {
					echo EasyPHPInfo::render( 'Core' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<p>' . esc_html__( 'PHPInfo class not found.', 'easy-php-settings' ) . '</p>';
				}
				?>
				<p class="easy-php-php-settings-empty" id="php-settings-empty-state" hidden>
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<?php esc_html_e( 'No directives match your search.', 'easy-php-settings' ); ?>
				</p>
			</div>

			<p class="easy-php-php-settings-tip">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				<?php esc_html_e( 'Tip: Select rows with the checkboxes, then click Copy Selected to copy directive = value pairs.', 'easy-php-settings' ); ?>
			</p>
		</div>
		<?php
	}
}
