<?php
/**
 * "In use" cell for one setting row.
 *
 * Rendered by Easy_Module_General_Settings::render_row_state(), both on page
 * load and for the REST live check, so the two always match.
 *
 * @var array $row           Row from build_rows().
 * @var bool  $is_php        True for PHP INI settings, false for wp-config.php constants.
 * @var int   $cache_ttl     Seconds PHP caches .user.ini for.
 * @var array $health_labels Labels for low / ok / high.
 * @var Easy_Module_General_Settings $this Module instance.
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="eps-live">
	<span class="eps-live__label"><?php esc_html_e( 'In use', 'easy-php-settings' ); ?></span>
	<span class="eps-live__value"><?php echo esc_html( '' !== $row['live'] ? $row['live'] : '—' ); ?></span>
	<?php if ( $row['health'] ) : ?>
		<span class="eps-pill eps-pill--<?php echo esc_attr( $row['health'] ); ?>"><?php echo esc_html( $health_labels[ $row['health'] ] ); ?></span>
	<?php endif; ?>
</div>

<?php if ( 'applied' === $row['applied'] ) : ?>
	<p class="eps-row__note eps-row__note--ok">
		<span class="dashicons dashicons-yes" aria-hidden="true"></span>
		<?php esc_html_e( 'Your saved value is in use.', 'easy-php-settings' ); ?>
	</p>
<?php elseif ( 'unset' === $row['applied'] ) : ?>
	<p class="eps-row__note">
		<?php esc_html_e( 'Not set here; using the server default.', 'easy-php-settings' ); ?>
	</p>
<?php else : ?>
	<p class="eps-row__note eps-row__note--warn">
		<span class="dashicons dashicons-clock" aria-hidden="true"></span>
		<?php
		if ( $is_php ) {
			echo esc_html(
				sprintf(
					/* translators: 1: saved value, 2: value in use, 3: seconds */
					__( 'Saved %1$s, but the server still uses %2$s. .user.ini changes can take up to %3$d seconds. If it stays like this, your host may block the change.', 'easy-php-settings' ),
					$row['value'],
					$row['live'],
					$cache_ttl
				)
			);
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: saved value, 2: value in use */
					__( 'Saved %1$s, but WordPress uses %2$s. The constant may be set earlier in wp-config.php or by another plugin.', 'easy-php-settings' ),
					$row['value'],
					$row['live']
				)
			);
		}
		?>
	</p>
	<?php
	if ( $is_php ) {
		$this->render_manual_instructions( $row['key'] );
	}
	?>
<?php endif; ?>
