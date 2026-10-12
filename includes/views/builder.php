<?php
/**
 * The link builder form on the plugin's admin screen. assets/js/builder.js does the work.
 *
 * Variables: $url, $id, $presets, $shorteners (service => label, the ones to offer, own domain
 * first),
 * $format, $summary, $fields, $datalist, $allowed (utm key => values when Settings only allows
 * the team's lists).
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.
?>
<form class="sunshine-utm-builder" data-sunshine-utm-builder data-format="<?php echo esc_attr( wp_json_encode( $format ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( Sunshine_UTM::NONCE ) ); ?>"<?php echo $allowed ? ' data-allowed="' . esc_attr( wp_json_encode( $allowed ) ) . '"' : ''; ?> novalidate>

	<?php if ( $presets ) : ?>
		<div class="sunshine-utm-builder__field sunshine-utm-builder__field--preset">
			<label for="<?php echo esc_attr( $id ); ?>-preset"><?php esc_html_e( 'Start from a preset', 'sunshine-utm-builder' ); ?></label>
			<select id="<?php echo esc_attr( $id ); ?>-preset" data-sunshine-utm-preset>
				<option value=""><?php esc_html_e( 'No preset', 'sunshine-utm-builder' ); ?></option>
				<?php foreach ( $presets as $preset ) : ?>
					<option value="<?php echo esc_attr( $preset['id'] ); ?>"><?php echo esc_html( $preset['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<div class="sunshine-utm-builder__field sunshine-utm-builder__field--url">
		<label for="<?php echo esc_attr( $id ); ?>-url"><?php esc_html_e( 'Website URL', 'sunshine-utm-builder' ); ?> <span class="sunshine-utm-builder__required" aria-hidden="true">*</span></label>
		<input type="url" id="<?php echo esc_attr( $id ); ?>-url" name="url" value="<?php echo esc_attr( $url ); ?>" required inputmode="url" autocomplete="url" spellcheck="false" aria-describedby="<?php echo esc_attr( $id ); ?>-url-help">
		<p class="sunshine-utm-builder__help" id="<?php echo esc_attr( $id ); ?>-url-help"><?php esc_html_e( 'The page you want people to land on.', 'sunshine-utm-builder' ); ?></p>
	</div>

	<div class="sunshine-utm-builder__tags">
		<?php foreach ( $fields as $key => $field ) : ?>
			<?php $field_id = $id . '-' . $key; ?>
			<div class="sunshine-utm-builder__field">
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( 'utm_source' === $key ) : ?>
						<span class="sunshine-utm-builder__required" aria-hidden="true">*</span>
					<?php endif; ?>
					<code class="sunshine-utm-builder__key"><?php echo esc_html( $key ); ?></code>
				</label>
				<input type="text" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $key ); ?>" autocomplete="off" spellcheck="false" aria-describedby="<?php echo esc_attr( $field_id ); ?>-help"<?php echo 'utm_source' === $key ? ' required' : ''; ?><?php echo isset( $datalist[ $key ] ) ? ' list="' . esc_attr( $field_id ) . '-list"' : ''; ?>>
				<p class="sunshine-utm-builder__help" id="<?php echo esc_attr( $field_id ); ?>-help">
					<?php echo esc_html( $field['help'] ); ?>
					<span class="sunshine-utm-builder__examples">
						<?php
						/* translators: %s: example values. */
						printf( esc_html__( 'Like: %s', 'sunshine-utm-builder' ), esc_html( $field['example'] ) );
						?>
					</span>
				</p>
				<?php if ( isset( $datalist[ $key ] ) ) : ?>
					<datalist id="<?php echo esc_attr( $field_id ); ?>-list">
						<?php foreach ( $datalist[ $key ] as $value ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>

	<details class="sunshine-utm-builder__more">
		<summary><?php esc_html_e( 'More parameters', 'sunshine-utm-builder' ); ?></summary>
		<div class="sunshine-utm-builder__field">
			<label for="<?php echo esc_attr( $id ); ?>-extra"><?php esc_html_e( 'Other parameters', 'sunshine-utm-builder' ); ?></label>
			<textarea id="<?php echo esc_attr( $id ); ?>-extra" name="extra" rows="3" spellcheck="false" placeholder="ref=partner" aria-describedby="<?php echo esc_attr( $id ); ?>-extra-help"></textarea>
			<p class="sunshine-utm-builder__help" id="<?php echo esc_attr( $id ); ?>-extra-help"><?php esc_html_e( 'One per line, like ref=partner or utm_id=summer. These are added exactly as you type them.', 'sunshine-utm-builder' ); ?></p>
		</div>
	</details>

	<?php if ( $shorteners ) : ?>
		<fieldset class="sunshine-utm-builder__field sunshine-utm-builder__shorteners" data-sunshine-utm-shorteners>
			<legend><?php esc_html_e( 'Shorten with', 'sunshine-utm-builder' ); ?></legend>
			<label>
				<input type="radio" name="shortener" value="" checked>
				<?php esc_html_e( 'None', 'sunshine-utm-builder' ); ?>
			</label>
			<?php foreach ( $shorteners as $service => $label ) : ?>
				<label>
					<input type="radio" name="shortener" value="<?php echo esc_attr( $service ); ?>">
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
	<?php endif; ?>

	<div class="sunshine-utm-builder__result">
		<label for="<?php echo esc_attr( $id ); ?>-link" class="sunshine-utm-builder__result-label"><?php esc_html_e( 'Your link', 'sunshine-utm-builder' ); ?></label>
		<textarea id="<?php echo esc_attr( $id ); ?>-link" class="sunshine-utm-builder__link" rows="3" readonly data-sunshine-utm-link></textarea>
		<p class="sunshine-utm-builder__status" role="status" aria-live="polite" data-sunshine-utm-status></p>
		<p class="sunshine-utm-builder__channel" data-sunshine-utm-channel hidden></p>
		<?php if ( $summary ) : ?>
			<p class="sunshine-utm-builder__format">
				<?php echo esc_html( $summary ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', Sunshine_UTM::builder_url() ) ); ?>"><?php esc_html_e( 'Change this', 'sunshine-utm-builder' ); ?></a>
			</p>
		<?php endif; ?>

		<div class="sunshine-utm-builder__actions">
			<button type="button" class="button button-primary" data-sunshine-utm-copy="link" disabled><?php esc_html_e( 'Copy link', 'sunshine-utm-builder' ); ?></button>
			<button type="button" class="button" data-sunshine-utm-qr="" hidden><?php esc_html_e( 'QR code', 'sunshine-utm-builder' ); ?></button>
			<a class="button" href="#" target="_blank" rel="noopener" data-sunshine-utm-open hidden><?php esc_html_e( 'Open link', 'sunshine-utm-builder' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'sunshine-utm-builder' ); ?></span></a>
			<button type="reset" class="sunshine-utm-builder__reset"><?php esc_html_e( 'Start over', 'sunshine-utm-builder' ); ?></button>
		</div>

		<div class="sunshine-utm-builder__short" data-sunshine-utm-short hidden>
			<label for="<?php echo esc_attr( $id ); ?>-short"><?php esc_html_e( 'Short link', 'sunshine-utm-builder' ); ?></label>
			<div class="sunshine-utm-builder__short-row">
				<input type="text" id="<?php echo esc_attr( $id ); ?>-short" readonly data-sunshine-utm-short-url>
				<button type="button" class="button" data-sunshine-utm-copy="short"><?php esc_html_e( 'Copy', 'sunshine-utm-builder' ); ?></button>
			</div>
		</div>

		<p class="screen-reader-text" role="status" aria-live="polite" data-sunshine-utm-announce></p>
	</div>
</form>
