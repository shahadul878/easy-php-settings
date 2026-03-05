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
		?>
		<div id="php-settings-tab">
			<h3><?php esc_html_e( 'PHP Settings Table', 'easy-php-settings' ); ?></h3>
			<div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
				<input type="text" id="php-settings-search" placeholder="<?php esc_attr_e( 'Search for directives...', 'easy-php-settings' ); ?>" style="min-width: 250px; padding: 5px;" />
				<button type="button" class="button button-secondary" id="php-settings-copy-selected"><?php esc_html_e( 'Copy Selected', 'easy-php-settings' ); ?></button>
			</div>
			<div id="php-settings-table-wrapper">
				<?php
				if ( class_exists( 'EasyPHPInfo' ) ) {
					echo EasyPHPInfo::render( 'Core' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo '<p>' . esc_html__( 'PHPInfo class not found.', 'easy-php-settings' ) . '</p>';
				}
				?>
			</div>
			<p style="margin-top: 10px; color: #666;"><em><?php esc_html_e( 'Tip: Use the search box to filter settings. Select rows and click "Copy Selected" to copy them to your clipboard.', 'easy-php-settings' ); ?></em></p>
		</div>
		<?php
	}
}
