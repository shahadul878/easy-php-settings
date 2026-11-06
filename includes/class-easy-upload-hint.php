<?php
/**
 * Upload limit hint on Media screens.
 *
 * @package EasyPHPSettings
 * @since 1.1.9
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Easy_Upload_Hint
 *
 * WordPress already prints "Maximum upload file size: X" under the uploader.
 * This adds which PHP setting causes that limit and a link to change it.
 */
class Easy_Upload_Hint {

	/**
	 * Plugin orchestrator.
	 *
	 * @var Easy_PHP_Settings
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Easy_PHP_Settings $plugin Plugin orchestrator.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Fires under the uploader on Media → Add New and inside the media modal.
		add_action( 'post-upload-ui', array( $this, 'render' ) );
	}

	/**
	 * Print the hint for users who can change the settings.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( $this->plugin->get_capability() ) ) {
			return;
		}

		$upload  = (string) ini_get( 'upload_max_filesize' );
		$post    = (string) ini_get( 'post_max_size' );
		$setting = self::limiting_setting( $upload, $post );
		$value   = 'post_max_size' === $setting ? $post : $upload;

		echo wp_kses(
			self::message_html( $setting, $value ),
			array(
				'p' => array( 'class' => array() ),
				'a' => array( 'href' => array() ),
			)
		);
	}

	/**
	 * Which PHP setting sets the upload limit (the smaller one wins).
	 *
	 * @param string $upload_max_filesize Value of upload_max_filesize.
	 * @param string $post_max_size       Value of post_max_size (0 means no limit).
	 * @return string upload_max_filesize or post_max_size.
	 */
	public static function limiting_setting( $upload_max_filesize, $post_max_size ) {
		$post_bytes = Easy_Settings_Validator::convert_to_bytes( $post_max_size );
		if ( $post_bytes > 0 && $post_bytes < Easy_Settings_Validator::convert_to_bytes( $upload_max_filesize ) ) {
			return 'post_max_size';
		}
		return 'upload_max_filesize';
	}

	/**
	 * Hint markup.
	 *
	 * @param string $setting Limiting setting name.
	 * @param string $value   Its value.
	 * @return string
	 */
	public static function message_html( $setting, $value ) {
		$url  = admin_url( 'tools.php?page=easy-php-settings' );
		$text = sprintf(
			/* translators: 1: PHP setting name, 2: its value */
			__( 'This limit comes from the PHP setting %1$s (%2$s).', 'easy-php-settings' ),
			$setting,
			$value
		);

		return '<p class="easy-php-upload-hint">' . esc_html( $text ) . ' <a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Change it in Easy PHP Settings', 'easy-php-settings' ) . '</a></p>';
	}
}
