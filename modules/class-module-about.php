<?php
/**
 * About Module
 *
 * Self-contained author and plugin information display.
 *
 * @package EasyPHPSettings
 * @since   1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_About extends Easy_Module_Base {

	protected $module_id          = 'about';
	protected $module_name        = 'About';
	protected $module_description = 'Author and plugin information';

	public function get_admin_tab() {
		return array(
			'id'       => 'about',
			'title'    => __( 'About Me', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	public function render_tab() {
		$version = $this->plugin->get_version();
		?>
		<div id="about-tab" class="easy-php-about-container">
			<div class="easy-php-about-header-card">
				<div class="easy-php-about-avatar">
					<img src="https://avatars.githubusercontent.com/u/13654267?v=4&size=120" alt="<?php esc_attr_e( 'H M Shahadul Islam', 'easy-php-settings' ); ?>" width="120" height="120">
				</div>
				<div class="easy-php-about-info">
					<h2 class="easy-php-about-name"><?php esc_html_e( 'H M Shahadul Islam', 'easy-php-settings' ); ?></h2>
					<p class="easy-php-about-title"><?php esc_html_e( 'WordPress Developer & Plugin Creator', 'easy-php-settings' ); ?></p>
					<p class="easy-php-about-description"><?php esc_html_e( 'Passionate about creating useful WordPress plugins that help businesses grow and improve their WordPress experience. I believe in clean, secure, and user-friendly code that follows WordPress best practices.', 'easy-php-settings' ); ?></p>
				</div>
			</div>

			<div class="easy-php-about-card">
				<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Connect With Me', 'easy-php-settings' ); ?></h3>
				<div class="easy-php-about-contact-links">
					<a href="https://github.com/shahadul878" target="_blank" rel="noopener" class="easy-php-about-contact-link"><span class="dashicons dashicons-admin-links"></span><span><?php esc_html_e( 'GitHub', 'easy-php-settings' ); ?></span><span class="easy-php-link-url">github.com/shahadul878</span></a>
					<a href="mailto:shahadul.islam1@gmail.com" class="easy-php-about-contact-link"><span class="dashicons dashicons-email"></span><span><?php esc_html_e( 'Email', 'easy-php-settings' ); ?></span><span class="easy-php-link-url">shahadul.islam1@gmail.com</span></a>
					<a href="https://github.com/shahadul878" target="_blank" rel="noopener" class="easy-php-about-contact-link"><span class="dashicons dashicons-admin-site"></span><span><?php esc_html_e( 'Website', 'easy-php-settings' ); ?></span><span class="easy-php-link-url">github.com/shahadul878</span></a>
				</div>
			</div>

			<div class="easy-php-about-card">
				<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Plugin Information', 'easy-php-settings' ); ?></h3>
				<div class="easy-php-about-plugin-info">
					<div class="easy-php-info-row"><span class="easy-php-info-label"><?php esc_html_e( 'Version:', 'easy-php-settings' ); ?></span><span class="easy-php-info-value"><?php echo esc_html( $version ); ?></span></div>
					<div class="easy-php-info-row"><span class="easy-php-info-label"><?php esc_html_e( 'Author:', 'easy-php-settings' ); ?></span><span class="easy-php-info-value">H M Shahadul Islam</span></div>
					<div class="easy-php-info-row"><span class="easy-php-info-label"><?php esc_html_e( 'License:', 'easy-php-settings' ); ?></span><span class="easy-php-info-value">GPL-2.0+</span></div>
					<div class="easy-php-info-row"><span class="easy-php-info-label"><?php esc_html_e( 'Contributors:', 'easy-php-settings' ); ?></span><span class="easy-php-info-value">shahadul878, codereyes</span></div>
				</div>
			</div>

			<div class="easy-php-about-card">
				<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Technologies Used', 'easy-php-settings' ); ?></h3>
				<div class="easy-php-tech-stack">
					<span class="easy-php-tech-tag">PHP</span>
					<span class="easy-php-tech-tag">WordPress</span>
					<span class="easy-php-tech-tag">JavaScript</span>
					<span class="easy-php-tech-tag">CSS3</span>
					<span class="easy-php-tech-tag">jQuery</span>
					<span class="easy-php-tech-tag">SVN</span>
					<span class="easy-php-tech-tag">Git</span>
				</div>
			</div>

			<div class="easy-php-about-card">
				<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Credits & Acknowledgments', 'easy-php-settings' ); ?></h3>
				<div class="easy-php-about-section">
					<p><?php esc_html_e( 'This plugin is built with care and attention to WordPress coding standards and best practices. Special thanks to the WordPress community for their continuous support and feedback.', 'easy-php-settings' ); ?></p>
					<p><?php esc_html_e( 'If you find this plugin useful, please consider leaving a review or contributing to its development on GitHub.', 'easy-php-settings' ); ?></p>
				</div>
			</div>

			<div class="easy-php-about-card">
				<h3 class="easy-php-about-card-title"><span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Support & Feedback', 'easy-php-settings' ); ?></h3>
				<div class="easy-php-about-section">
					<p><?php esc_html_e( 'Need help or have suggestions? I\'d love to hear from you!', 'easy-php-settings' ); ?></p>
					<div class="easy-php-about-actions">
						<a href="https://github.com/shahadul878/easy-php-settings/issues" target="_blank" rel="noopener" class="button button-primary"><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e( 'Report an Issue', 'easy-php-settings' ); ?></a>
						<a href="https://github.com/shahadul878/easy-php-settings" target="_blank" rel="noopener" class="button button-secondary"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'Star on GitHub', 'easy-php-settings' ); ?></a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
