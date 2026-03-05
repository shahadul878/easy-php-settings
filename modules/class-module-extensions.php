<?php
/**
 * Extensions Module
 *
 * Self-contained PHP extensions viewer.
 *
 * @package EasyPHPSettings
 * @since   1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_Extensions extends Easy_Module_Base {

	protected $module_id          = 'extensions';
	protected $module_name        = 'PHP Extensions';
	protected $module_description = 'View PHP extensions status';

	public function get_admin_tab() {
		return array(
			'id'       => 'extensions',
			'title'    => __( 'Extensions', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	public function render_tab() {
		$categorized = Easy_Extensions_Viewer::get_categorized_extensions();
		$missing     = Easy_Extensions_Viewer::get_critical_missing_extensions();
		$recommended = Easy_Extensions_Viewer::get_recommended_extensions();
		?>
		<div id="extensions-tab">
			<h3><?php esc_html_e( 'PHP Extensions', 'easy-php-settings' ); ?></h3>

			<?php if ( ! empty( $missing ) ) : ?>
			<div class="notice notice-error" style="padding:10px;margin:20px 0;">
				<h4 style="margin-top:0;"><?php esc_html_e( 'Critical Missing Extensions', 'easy-php-settings' ); ?></h4>
				<ul>
					<?php foreach ( $missing as $ext => $desc ) : ?>
					<li><strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>

			<div style="margin-bottom:20px;">
				<input type="text" id="extensions-search" placeholder="<?php esc_attr_e( 'Search extensions...', 'easy-php-settings' ); ?>" style="min-width:250px;padding:5px;" />
			</div>

			<?php foreach ( $categorized as $category => $extensions ) : ?>
			<div style="margin-bottom:30px;">
				<h4><?php echo esc_html( $category ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Extension Name', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'easy-php-settings' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Version', 'easy-php-settings' ); ?></th>
					</tr></thead>
					<tbody class="extensions-list">
						<?php foreach ( $extensions as $extension ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $extension ); ?></strong></td>
							<td><span style="color:green;font-weight:bold;">&#10003; <?php esc_html_e( 'Loaded', 'easy-php-settings' ); ?></span></td>
							<td><?php echo esc_html( Easy_Extensions_Viewer::get_extension_version( $extension ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endforeach; ?>

			<div style="margin-top:30px;padding:15px;background:#f0f6fc;border-left:4px solid #0073aa;">
				<h4><?php esc_html_e( 'Recommended Extensions', 'easy-php-settings' ); ?></h4>
				<ul>
					<?php foreach ( $recommended as $ext => $desc ) : ?>
					<li>
						<strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?>
						<?php echo Easy_Extensions_Viewer::is_loaded( $ext )
							? '<span style="color:green;">&#10003; ' . esc_html__( 'Installed', 'easy-php-settings' ) . '</span>'
							: '<span style="color:orange;">&#9888; ' . esc_html__( 'Not Installed', 'easy-php-settings' ) . '</span>'; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}
}
