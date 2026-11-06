<?php
/**
 * General Settings tab.
 *
 * Variables in scope (from Easy_Module_General_Settings::render_tab()):
 *
 * @var array                        $view Presets, setting rows, custom INI and cache TTL.
 * @var Easy_Module_General_Settings $this Module instance.
 *
 * @package EasyPHPSettings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$easy_php_settings_health_labels = array(
	'low'  => __( 'Below recommended', 'easy-php-settings' ),
	'ok'   => __( 'Recommended range', 'easy-php-settings' ),
	'high' => __( 'Above recommended', 'easy-php-settings' ),
);

/**
 * Print one group of setting rows.
 *
 * @param array[] $rows     Rows from build_rows().
 * @param bool    $is_php   True for PHP INI settings, false for wp-config.php constants.
 */
$easy_php_settings_render_rows = function ( array $rows, $is_php ) use ( $view, $easy_php_settings_health_labels ) {
	foreach ( $rows as $row ) :
		$state_id = $row['id'] . '-state';
		$help_id  = $row['id'] . '-help';
		?>
		<div class="eps-row eps-row--<?php echo esc_attr( $row['applied'] ); ?>" data-key="<?php echo esc_attr( $row['key'] ); ?>">
			<div class="eps-row__meta">
				<label class="eps-row__label" for="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( $row['label'] ); ?></label>
				<code class="eps-row__key"><?php echo esc_html( $is_php ? $row['key'] : strtoupper( $row['key'] ) ); ?></code>
				<?php if ( $row['help'] ) : ?>
					<p class="eps-row__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $row['help'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="eps-row__control">
				<input
					type="text"
					class="eps-input"
					id="<?php echo esc_attr( $row['id'] ); ?>"
					name="<?php echo esc_attr( $row['name'] ); ?>"
					value="<?php echo esc_attr( $row['value'] ); ?>"
					placeholder="<?php echo esc_attr( $row['unit_hint'] ); ?>"
					autocomplete="off"
					spellcheck="false"
					aria-describedby="<?php echo esc_attr( trim( ( $row['help'] ? $help_id : '' ) . ' ' . $state_id ) ); ?>"
				>
				<?php if ( '' !== $row['recommended'] && 'low' === $row['health'] ) : ?>
					<button type="button" class="eps-use-rec" data-target="<?php echo esc_attr( $row['id'] ); ?>" data-value="<?php echo esc_attr( $row['recommended'] ); ?>"<?php echo $row['value'] === $row['recommended'] ? ' hidden' : ''; ?>>
						<?php
						/* translators: %s: recommended value */
						echo esc_html( sprintf( __( 'Use recommended: %s', 'easy-php-settings' ), $row['recommended'] ) );
						?>
					</button>
				<?php endif; ?>
			</div>

			<div class="eps-row__state" id="<?php echo esc_attr( $state_id ); ?>">
				<div class="eps-live">
					<span class="eps-live__label"><?php esc_html_e( 'In use', 'easy-php-settings' ); ?></span>
					<span class="eps-live__value"><?php echo esc_html( '' !== $row['live'] ? $row['live'] : '—' ); ?></span>
					<?php if ( $row['health'] ) : ?>
						<span class="eps-pill eps-pill--<?php echo esc_attr( $row['health'] ); ?>"><?php echo esc_html( $easy_php_settings_health_labels[ $row['health'] ] ); ?></span>
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
									$view['cache_ttl']
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
			</div>
		</div>
		<?php
	endforeach;
};
?>
<form action="options.php" method="post" id="eps-settings-form" class="eps-form">
	<?php settings_fields( 'easy_php_settings' ); ?>

	<?php if ( ! empty( $view['presets'] ) ) : ?>
		<section class="eps-section eps-section--flat" aria-labelledby="eps-presets-title">
			<div class="eps-section__head">
				<p class="eps-kicker"><?php esc_html_e( 'Presets', 'easy-php-settings' ); ?></p>
				<h2 class="eps-section__title" id="eps-presets-title"><?php esc_html_e( 'Start from a preset', 'easy-php-settings' ); ?></h2>
				<p class="eps-section__desc"><?php esc_html_e( 'Fills in the PHP limits and custom directives below. Nothing changes until you save.', 'easy-php-settings' ); ?></p>
			</div>
			<ul class="eps-presets">
				<?php foreach ( $view['presets'] as $preset_key => $preset ) : ?>
					<li>
						<button type="button" class="eps-preset" data-preset="<?php echo esc_attr( $preset_key ); ?>" aria-pressed="false">
							<span class="eps-preset__name"><?php echo esc_html( $preset['name'] ); ?></span>
							<span class="eps-preset__desc"><?php echo esc_html( $preset['description'] ); ?></span>
							<span class="eps-preset__values">
								<?php
								echo esc_html(
									implode(
										' · ',
										array(
											$preset['memory_limit'],
											$preset['upload_max_filesize'],
											$preset['max_execution_time'] . 's',
										)
									)
								);
								?>
							</span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="eps-presets__status" aria-live="polite"></p>
		</section>
	<?php endif; ?>

	<section class="eps-section" aria-labelledby="eps-php-title">
		<div class="eps-section__head">
			<p class="eps-kicker"><?php esc_html_e( 'Written to .user.ini and php.ini', 'easy-php-settings' ); ?></p>
			<h2 class="eps-section__title" id="eps-php-title"><?php esc_html_e( 'PHP limits', 'easy-php-settings' ); ?></h2>
		</div>
		<div class="eps-rows">
			<?php $easy_php_settings_render_rows( $view['php_rows'], true ); ?>
		</div>
	</section>

	<section class="eps-section" aria-labelledby="eps-wp-title">
		<div class="eps-section__head">
			<p class="eps-kicker"><?php esc_html_e( 'Written to wp-config.php (backed up first)', 'easy-php-settings' ); ?></p>
			<h2 class="eps-section__title" id="eps-wp-title"><?php esc_html_e( 'WordPress memory', 'easy-php-settings' ); ?></h2>
		</div>
		<div class="eps-rows">
			<?php $easy_php_settings_render_rows( $view['wp_rows'], false ); ?>
		</div>
	</section>

	<details class="eps-section eps-advanced"<?php echo '' !== trim( $view['custom_ini'] ) ? ' open' : ''; ?>>
		<summary class="eps-advanced__summary">
			<span>
				<span class="eps-kicker"><?php esc_html_e( 'Advanced', 'easy-php-settings' ); ?></span>
				<span class="eps-section__title"><?php esc_html_e( 'Custom php.ini directives', 'easy-php-settings' ); ?></span>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
		</summary>
		<div class="eps-advanced__body">
			<p class="eps-section__desc"><?php esc_html_e( 'One directive per line, for example "max_input_time = 120". Added below the limits above. Unsafe directives such as auto_prepend_file are removed on save.', 'easy-php-settings' ); ?></p>
			<textarea name="easy_php_settings_settings[custom_php_ini]" id="easy_php_settings_custom_php_ini" rows="10" placeholder="<?php esc_attr_e( '; Add custom PHP directives here', 'easy-php-settings' ); ?>"><?php echo esc_textarea( $view['custom_ini'] ); ?></textarea>
		</div>
	</details>

	<div class="eps-savebar" data-state="clean">
		<p class="eps-savebar__status" aria-live="polite">
			<span class="eps-savebar__dot" aria-hidden="true"></span>
			<span class="eps-savebar__text"><?php esc_html_e( 'No unsaved changes', 'easy-php-settings' ); ?></span>
		</p>
		<button type="submit" name="submit" id="easy-php-settings-save-button" class="easy-php-save-button button button-primary button-large">
			<span class="easy-php-save-button__icon dashicons dashicons-saved" aria-hidden="true"></span>
			<span class="easy-php-save-button__label"><?php esc_html_e( 'Save settings', 'easy-php-settings' ); ?></span>
		</button>
	</div>
</form>

<section class="eps-section eps-section--flat eps-tools" aria-labelledby="eps-tools-title">
	<div class="eps-section__head">
		<p class="eps-kicker"><?php esc_html_e( 'Tools', 'easy-php-settings' ); ?></p>
		<h2 class="eps-section__title" id="eps-tools-title"><?php esc_html_e( 'When the plugin cannot write files', 'easy-php-settings' ); ?></h2>
	</div>

	<div class="eps-tools__grid">
		<div class="eps-tool">
			<h3 class="eps-tool__title"><?php esc_html_e( 'Configuration generator', 'easy-php-settings' ); ?></h3>
			<p class="eps-tool__desc"><?php esc_html_e( 'Build .user.ini and .htaccess snippets from the values in the form, to paste into your server yourself.', 'easy-php-settings' ); ?></p>
			<button type="button" id="generate-config" class="button">
				<span class="dashicons dashicons-media-code" aria-hidden="true"></span>
				<?php esc_html_e( 'Generate snippets', 'easy-php-settings' ); ?>
			</button>
			<div id="config-output" class="easy-php-config-output">
				<div class="easy-php-config-block">
					<label for="user-ini-content"><?php esc_html_e( '.user.ini', 'easy-php-settings' ); ?></label>
					<textarea id="user-ini-content" readonly></textarea>
					<button type="button" class="button button-small easy-php-copy-config" data-target="user-ini-content">
						<?php esc_html_e( 'Copy', 'easy-php-settings' ); ?>
					</button>
				</div>
				<div class="easy-php-config-block" id="htaccess-block">
					<label for="htaccess-content"><?php esc_html_e( '.htaccess (Apache)', 'easy-php-settings' ); ?></label>
					<textarea id="htaccess-content" readonly></textarea>
					<button type="button" class="button button-small easy-php-copy-config" data-target="htaccess-content">
						<?php esc_html_e( 'Copy', 'easy-php-settings' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="eps-tool eps-tool--danger">
			<h3 class="eps-tool__title"><?php esc_html_e( 'Remove INI directives', 'easy-php-settings' ); ?></h3>
			<p class="eps-tool__desc"><?php esc_html_e( 'Takes this plugin\'s lines out of .user.ini and php.ini so the server defaults apply again. Lines from other plugins are kept. Your saved values stay in the form.', 'easy-php-settings' ); ?></p>
			<form action="" method="post" class="easy-php-delete-form">
				<?php wp_nonce_field( 'easy_php_settings_delete_ini_nonce' ); ?>
				<button type="submit" name="easy_php_settings_delete_ini_files" class="button eps-button-danger no-loading" onclick="return confirm('<?php echo esc_js( __( 'Remove this plugin\'s directives from .user.ini and php.ini?', 'easy-php-settings' ) ); ?>');">
					<span class="dashicons dashicons-trash" aria-hidden="true"></span>
					<?php esc_html_e( 'Remove directives', 'easy-php-settings' ); ?>
				</button>
			</form>
		</div>
	</div>
</section>
