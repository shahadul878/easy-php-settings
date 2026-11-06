<?php
/**
 * Grouped debug.log viewer.
 *
 * @var array[] $groups      Groups from Easy_Debug_Log_Parser::group(), newest first.
 * @var int[]   $counts      Occurrences per level.
 * @var string  $log_content Raw log text shown under "Raw log".
 * @var int     $max_groups  Most groups to list.
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$easy_php_settings_level_labels = array(
	'fatal'      => __( 'Fatal', 'easy-php-settings' ),
	'warning'    => __( 'Warning', 'easy-php-settings' ),
	'notice'     => __( 'Notice', 'easy-php-settings' ),
	'deprecated' => __( 'Deprecated', 'easy-php-settings' ),
	'database'   => __( 'Database', 'easy-php-settings' ),
	'other'      => __( 'Other', 'easy-php-settings' ),
);
$easy_php_settings_total        = array_sum( $counts );
?>
<div class="eps-log" data-eps-log>
	<?php if ( ! empty( $groups ) ) : ?>
		<div class="eps-log__filters" role="group" aria-label="<?php esc_attr_e( 'Filter log entries by type', 'easy-php-settings' ); ?>">
			<button type="button" class="eps-log__filter" data-level="all" aria-pressed="true">
				<?php esc_html_e( 'All', 'easy-php-settings' ); ?> <span class="eps-log__count"><?php echo esc_html( number_format_i18n( $easy_php_settings_total ) ); ?></span>
			</button>
			<?php foreach ( $counts as $easy_php_settings_level => $easy_php_settings_count ) : ?>
				<?php if ( $easy_php_settings_count > 0 ) : ?>
					<button type="button" class="eps-log__filter eps-log__filter--<?php echo esc_attr( $easy_php_settings_level ); ?>" data-level="<?php echo esc_attr( $easy_php_settings_level ); ?>" aria-pressed="false">
						<?php echo esc_html( $easy_php_settings_level_labels[ $easy_php_settings_level ] ); ?> <span class="eps-log__count"><?php echo esc_html( number_format_i18n( $easy_php_settings_count ) ); ?></span>
					</button>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<ol class="eps-log__list">
			<?php foreach ( array_slice( $groups, 0, $max_groups ) as $easy_php_settings_group ) : ?>
				<li class="eps-log__item" data-level="<?php echo esc_attr( $easy_php_settings_group['level'] ); ?>">
					<span class="eps-log__level eps-log__level--<?php echo esc_attr( $easy_php_settings_group['level'] ); ?>"><?php echo esc_html( $easy_php_settings_level_labels[ $easy_php_settings_group['level'] ] ); ?></span>
					<div class="eps-log__body">
						<p class="eps-log__message"><?php echo esc_html( wp_strip_all_tags( $easy_php_settings_group['message'] ) ); ?></p>
						<p class="eps-log__meta">
							<?php if ( $easy_php_settings_group['count'] > 1 ) : ?>
								<strong>
									<?php
									/* translators: %s: number of times the error happened */
									echo esc_html( sprintf( __( '%s times', 'easy-php-settings' ), number_format_i18n( $easy_php_settings_group['count'] ) ) );
									?>
								</strong>
								·
								<?php
								/* translators: 1: last time seen, 2: first time seen */
								echo esc_html( sprintf( __( 'last %1$s, first %2$s', 'easy-php-settings' ), $easy_php_settings_group['last_time'], $easy_php_settings_group['first_time'] ) );
								?>
							<?php else : ?>
								<?php echo esc_html( $easy_php_settings_group['last_time'] ); ?>
							<?php endif; ?>
						</p>
						<?php if ( '' !== $easy_php_settings_group['detail'] ) : ?>
							<details class="eps-log__detail">
								<summary><?php esc_html_e( 'Stack trace', 'easy-php-settings' ); ?></summary>
								<pre><?php echo esc_html( $easy_php_settings_group['detail'] ); ?></pre>
							</details>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php if ( count( $groups ) > $max_groups ) : ?>
			<p class="eps-log__more">
				<?php
				/* translators: %d: number of distinct errors shown */
				echo esc_html( sprintf( __( 'Showing the %d most recent distinct errors. Download the full log to see the rest.', 'easy-php-settings' ), $max_groups ) );
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<details class="eps-log__raw">
		<summary><?php esc_html_e( 'Raw log', 'easy-php-settings' ); ?></summary>
		<textarea id="easy_php_settings-log-viewer" rows="18" readonly><?php echo esc_textarea( $log_content ); ?></textarea>
	</details>
</div>
