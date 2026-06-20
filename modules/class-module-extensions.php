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
			<div class="easy-php-section-card">
				<div class="easy-php-section-card__header">
					<h3 class="easy-php-section-card__title"><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'PHP Extensions', 'easy-php-settings' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Review loaded PHP extensions grouped by category and check recommended modules.', 'easy-php-settings' ); ?></p>
				</div>

				<?php if ( ! empty( $missing ) ) : ?>
				<div class="notice notice-error">
					<h4 style="margin-top:0;"><?php esc_html_e( 'Critical Missing Extensions', 'easy-php-settings' ); ?></h4>
					<ul>
						<?php foreach ( $missing as $ext => $desc ) : ?>
						<li><strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<div class="easy-php-toolbar">
					<label class="easy-php-extensions-search-wrap" for="extensions-search">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" id="extensions-search" placeholder="<?php esc_attr_e( 'Search extensions...', 'easy-php-settings' ); ?>" autocomplete="off" />
					</label>
				</div>

				<?php foreach ( $categorized as $category => $extensions ) : ?>
				<div class="easy-php-extensions-category">
					<h4><?php echo esc_html( $category ); ?></h4>
					<div class="easy-php-data-table-wrap">
						<table class="easy-php-data-table">
							<thead><tr>
								<th scope="col"><?php esc_html_e( 'Extension Name', 'easy-php-settings' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'easy-php-settings' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Version', 'easy-php-settings' ); ?></th>
							</tr></thead>
							<tbody class="extensions-list">
								<?php foreach ( $extensions as $extension ) : ?>
								<tr>
									<td><code><?php echo esc_html( $extension ); ?></code></td>
									<td><span class="easy-php-badge easy-php-badge--success">&#10003; <?php esc_html_e( 'Loaded', 'easy-php-settings' ); ?></span></td>
									<td><?php echo esc_html( Easy_Extensions_Viewer::get_extension_version( $extension ) ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endforeach; ?>

				<div class="easy-php-extensions-recommended">
					<h4><?php esc_html_e( 'Recommended Extensions', 'easy-php-settings' ); ?></h4>
					<ul>
						<?php foreach ( $recommended as $ext => $desc ) : ?>
						<li>
							<strong><?php echo esc_html( $ext ); ?>:</strong> <?php echo esc_html( $desc ); ?>
							<?php
							echo Easy_Extensions_Viewer::is_loaded( $ext )
								? ' <span class="easy-php-badge easy-php-badge--success">&#10003; ' . esc_html__( 'Installed', 'easy-php-settings' ) . '</span>'
								: ' <span class="easy-php-badge easy-php-badge--danger">&#9888; ' . esc_html__( 'Not Installed', 'easy-php-settings' ) . '</span>';
							?>
						</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
