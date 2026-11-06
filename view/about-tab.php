<?php
/**
 * About tab: author profile laid out as a CV, plus plugin details and privacy.
 *
 * @var array $about Profile data from Easy_Module_About::profile().
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div id="about-tab" class="eps-cv">

	<section class="eps-cv__hero" aria-labelledby="eps-cv-name">
		<div class="eps-cv__identity">
			<span class="eps-cv__monogram" aria-hidden="true"><?php echo esc_html( $about['initials'] ); ?></span>
			<div>
				<p class="eps-kicker"><?php esc_html_e( 'Plugin author', 'easy-php-settings' ); ?></p>
				<h2 class="eps-cv__name" id="eps-cv-name"><?php echo esc_html( $about['name'] ); ?></h2>
				<p class="eps-cv__role"><?php echo esc_html( $about['role'] ); ?></p>
				<p class="eps-cv__availability">
					<span class="eps-cv__dot" aria-hidden="true"></span>
					<?php esc_html_e( 'Open to freelance and contract WordPress work', 'easy-php-settings' ); ?>
				</p>
			</div>
		</div>
		<div class="eps-cv__cta">
			<a class="button button-primary eps-cv__hire" href="<?php echo esc_url( $about['hire_url'] ); ?>">
				<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Hire me', 'easy-php-settings' ); ?>
			</a>
			<a class="button eps-cv__secondary" href="<?php echo esc_url( $about['github_url'] ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'View GitHub', 'easy-php-settings' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'easy-php-settings' ); ?></span>
			</a>
		</div>
	</section>

	<div class="eps-cv__grid">
		<aside class="eps-cv__side">
			<section class="eps-cv__block" aria-labelledby="eps-cv-contact">
				<h3 class="eps-cv__heading" id="eps-cv-contact"><?php esc_html_e( 'Contact', 'easy-php-settings' ); ?></h3>
				<dl class="eps-cv__facts">
					<div>
						<dt><?php esc_html_e( 'Email', 'easy-php-settings' ); ?></dt>
						<dd><a href="<?php echo esc_url( 'mailto:' . $about['email'] ); ?>"><?php echo esc_html( $about['email'] ); ?></a></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'GitHub', 'easy-php-settings' ); ?></dt>
						<dd><a href="<?php echo esc_url( $about['github_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $about['github_label'] ); ?></a></dd>
					</div>
				</dl>
			</section>

			<section class="eps-cv__block" aria-labelledby="eps-cv-skills">
				<h3 class="eps-cv__heading" id="eps-cv-skills"><?php esc_html_e( 'Skills', 'easy-php-settings' ); ?></h3>
				<ul class="eps-cv__tags">
					<?php foreach ( $about['skills'] as $skill ) : ?>
						<li><?php echo esc_html( $skill ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="eps-cv__block" aria-labelledby="eps-cv-plugin">
				<h3 class="eps-cv__heading" id="eps-cv-plugin"><?php esc_html_e( 'This plugin', 'easy-php-settings' ); ?></h3>
				<dl class="eps-cv__facts">
					<div><dt><?php esc_html_e( 'Version', 'easy-php-settings' ); ?></dt><dd><?php echo esc_html( $about['version'] ); ?></dd></div>
					<div><dt><?php esc_html_e( 'License', 'easy-php-settings' ); ?></dt><dd>GPL-2.0+</dd></div>
					<div><dt><?php esc_html_e( 'Contributors', 'easy-php-settings' ); ?></dt><dd>shahadul878, codereyes</dd></div>
				</dl>
			</section>
		</aside>

		<div class="eps-cv__main">
			<section class="eps-cv__block" aria-labelledby="eps-cv-profile">
				<h3 class="eps-cv__heading" id="eps-cv-profile"><?php esc_html_e( 'Profile', 'easy-php-settings' ); ?></h3>
				<p class="eps-cv__lead"><?php echo esc_html( $about['summary'] ); ?></p>
			</section>

			<section class="eps-cv__block" aria-labelledby="eps-cv-services">
				<h3 class="eps-cv__heading" id="eps-cv-services"><?php esc_html_e( 'What I can help with', 'easy-php-settings' ); ?></h3>
				<ul class="eps-cv__services">
					<?php foreach ( $about['services'] as $service ) : ?>
						<li>
							<strong><?php echo esc_html( $service['title'] ); ?></strong>
							<span><?php echo esc_html( $service['text'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="eps-cv__block" aria-labelledby="eps-cv-work">
				<h3 class="eps-cv__heading" id="eps-cv-work"><?php esc_html_e( 'Selected work', 'easy-php-settings' ); ?></h3>
				<article class="eps-cv__work">
					<div class="eps-cv__work-head">
						<h4><?php esc_html_e( 'Easy PHP Settings', 'easy-php-settings' ); ?></h4>
						<span class="eps-cv__work-meta"><?php esc_html_e( 'WordPress.org plugin · Author', 'easy-php-settings' ); ?></span>
					</div>
					<p><?php esc_html_e( 'Manages PHP limits, WordPress memory and debugging from the dashboard: safe .user.ini and wp-config.php edits with backups, live checks of the values the server really uses, Site Health tests and a WP-CLI command.', 'easy-php-settings' ); ?></p>
					<p class="eps-cv__links">
						<a href="<?php echo esc_url( $about['plugin_repo_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Source on GitHub', 'easy-php-settings' ); ?></a>
						<span aria-hidden="true">·</span>
						<a href="<?php echo esc_url( $about['issues_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Report an issue', 'easy-php-settings' ); ?></a>
						<span aria-hidden="true">·</span>
						<a href="<?php echo esc_url( $about['reviews_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Leave a review', 'easy-php-settings' ); ?></a>
					</p>
				</article>
			</section>

			<section class="eps-cv__block eps-cv__block--callout">
				<div>
					<h3 class="eps-cv__heading"><?php esc_html_e( 'Have a project in mind?', 'easy-php-settings' ); ?></h3>
					<p><?php esc_html_e( 'Tell me what you need and when. I usually reply by email.', 'easy-php-settings' ); ?></p>
				</div>
				<a class="button button-primary eps-cv__hire" href="<?php echo esc_url( $about['hire_url'] ); ?>">
					<?php esc_html_e( 'Hire me', 'easy-php-settings' ); ?>
				</a>
			</section>
		</div>
	</div>

	<?php
	if ( function_exists( 'tracker_integration_render_consent_panel' ) ) {
		tracker_integration_render_consent_panel();
	}
	?>
</div>
