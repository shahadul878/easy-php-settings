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

	/**
	 * Address the "Hire me" buttons write to.
	 */
	const HIRE_EMAIL = 'shahadul.islam1@gmail.com';

	/**
	 * Profile shown on the About tab.
	 *
	 * @return array
	 */
	private function profile() {
		$hire_url = add_query_arg(
			array(
				'subject' => rawurlencode( __( 'Project enquiry (via Easy PHP Settings)', 'easy-php-settings' ) ),
			),
			'mailto:' . self::HIRE_EMAIL
		);

		return array(
			'name'            => 'H M Shahadul Islam',
			'initials'        => 'HS',
			'role'            => __( 'WordPress Developer & Plugin Creator', 'easy-php-settings' ),
			'summary'         => __( 'I build WordPress plugins that help businesses grow and make WordPress easier to run. I care about clean, secure, user-friendly code that follows WordPress coding standards and best practices.', 'easy-php-settings' ),
			'email'           => self::HIRE_EMAIL,
			'hire_url'        => $hire_url,
			'github_url'      => 'https://github.com/shahadul878',
			'github_label'    => 'github.com/shahadul878',
			'plugin_repo_url' => 'https://github.com/shahadul878/easy-php-settings',
			'issues_url'      => 'https://github.com/shahadul878/easy-php-settings/issues',
			'reviews_url'     => Easy_Module_Review_Request::REVIEW_URL,
			'version'         => $this->plugin->get_version(),
			'skills'          => array( 'PHP', 'WordPress', 'JavaScript', 'jQuery', 'CSS3', 'REST API', 'WP-CLI', 'PHPUnit', 'Git', 'SVN' ),
			'services'        => array(
				array(
					'title' => __( 'Custom plugins', 'easy-php-settings' ),
					'text'  => __( 'New features built as standalone, maintainable WordPress plugins.', 'easy-php-settings' ),
				),
				array(
					'title' => __( 'Server and PHP tuning', 'easy-php-settings' ),
					'text'  => __( 'Memory, upload and timeout limits, debugging and error logs.', 'easy-php-settings' ),
				),
				array(
					'title' => __( 'Fixes and code review', 'easy-php-settings' ),
					'text'  => __( 'Tracking down errors and bringing code up to WordPress standards.', 'easy-php-settings' ),
				),
				array(
					'title' => __( 'WordPress.org releases', 'easy-php-settings' ),
					'text'  => __( 'Preparing plugins for review, readme files and SVN deployment.', 'easy-php-settings' ),
				),
			),
		);
	}

	public function render_tab() {
		$about = $this->profile();
		include plugin_dir_path( __DIR__ ) . 'view/about-tab.php';
	}
}
