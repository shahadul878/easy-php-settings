<?php
/**
 * Status Module
 *
 * Self-contained server status and PHP configuration display.
 *
 * @package EasyPHPSettings
 * @since   1.0.5
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Easy_Module_Status extends Easy_Module_Base {

	protected $module_id          = 'status';
	protected $module_name        = 'Status';
	protected $module_description = 'Server status and PHP configuration information';

	public function get_admin_tab() {
		return array(
			'id'       => 'status',
			'title'    => __( 'Status', 'easy-php-settings' ),
			'callback' => array( $this, 'render_tab' ),
		);
	}

	public function render_tab() {
		$all_settings  = ini_get_all();
		$settings_keys = $this->plugin->get_settings_keys();
		$recommended   = $this->plugin->get_recommended_values();
		$wp_mem_keys   = $this->plugin->get_wp_memory_settings_keys();
		$wp_mem_rec    = $this->plugin->get_wp_memory_recommended_values();

		global $wpdb;
		$db_version  = $wpdb->db_version();
		$db_software = ( strpos( strtolower( $db_version ), 'mariadb' ) !== false ) ? 'MariaDB' : 'MySQL';

		$server_info = array(
			'Server Software'   => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
			'Server IP'         => isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : 'N/A',
			'PHP Version'       => phpversion(),
			'WordPress Version' => get_bloginfo( 'version' ),
			'Database Software' => $db_software,
			'Database Version'  => $db_version,
			'Server API'        => php_sapi_name(),
		);
		?>
		<div id="status-tab">
			<h3><?php esc_html_e( 'PHP Configuration Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Setting', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current Value', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Recommended', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Changeable', 'easy-php-settings' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $settings_keys as $key ) :
					$current = ini_get( $key );
					$rec     = $recommended[ $key ] ?? 'N/A';
					$access  = $all_settings[ $key ]['access'] ?? 0;
					$ok      = ( INI_USER === $access || INI_ALL === $access );
				?>
				<tr>
					<td><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></strong></td>
					<td><?php echo esc_html( $current ); ?></td>
					<td><?php echo esc_html( $rec ); ?></td>
					<td><?php echo $ok ? '<span style="color:green;">' . esc_html__( 'Yes', 'easy-php-settings' ) . '</span>' : '<span style="color:red;">' . esc_html__( 'No', 'easy-php-settings' ) . '</span>'; ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3 style="margin-top:30px;"><?php esc_html_e( 'WordPress Memory Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Setting', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Current Value', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Recommended', 'easy-php-settings' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $wp_mem_keys as $key ) : ?>
				<tr>
					<td><strong><?php echo esc_html( strtoupper( $key ) ); ?></strong></td>
					<td><?php echo esc_html( $this->get_wp_memory_value( $key ) ); ?></td>
					<td><?php echo esc_html( $wp_mem_rec[ $key ] ?? 'N/A' ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h3 style="margin-top:30px;"><?php esc_html_e( 'Server Status', 'easy-php-settings' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Metric', 'easy-php-settings' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Value', 'easy-php-settings' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $server_info as $metric => $val ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $metric ); ?></strong></td>
					<td><?php echo esc_html( $val ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function get_wp_memory_value( $key ) {
		switch ( $key ) {
			case 'wp_memory_limit':
				return defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
			case 'wp_max_memory_limit':
				return defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '256M';
			default:
				return 'N/A';
		}
	}
}
