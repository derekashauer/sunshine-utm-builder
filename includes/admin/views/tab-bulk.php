<?php
/**
 * Bulk Builder tab. assets/js/admin.js shows the fields for the chosen method, fills tags from a
 * preset, keeps the preview up to date, and creates the batch.
 *
 * Preset values go into the tag fields as saved, tokens and all. Tokens are filled in per link on
 * the server.
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$fields      = Sunshine_UTM_URL_Builder::fields();
$presets     = Sunshine_UTM_Presets::get_all();
$shorteners  = Sunshine_UTM_Shortener::ready();
$networks    = Sunshine_UTM_Bulk::social_networks();
$methods     = array(
	'urls'       => array(
		'name'  => __( 'A list of links', 'sunshine-utm-builder' ),
		'blurb' => __( 'Paste links and give them all the same tags.', 'sunshine-utm-builder' ),
	),
	'social'     => array(
		'name'  => __( 'Social networks', 'sunshine-utm-builder' ),
		'blurb' => __( 'One link per network, with the network as the source.', 'sunshine-utm-builder' ),
	),
	'variations' => array(
		'name'  => __( 'Every combination', 'sunshine-utm-builder' ),
		'blurb' => __( 'List a few values for each tag and get a link for every mix.', 'sunshine-utm-builder' ),
	),
	'csv'        => array(
		'name'  => __( 'A CSV file', 'sunshine-utm-builder' ),
		'blurb' => __( 'One row per link, with its own tags. Good for spreadsheets.', 'sunshine-utm-builder' ),
	),
);
$preset_data = array();
foreach ( $presets as $preset ) {
	$preset_data[ $preset['id'] ] = array_intersect_key( $preset, array_flip( Sunshine_UTM_URL_Builder::KEYS ) );
}
?>
<form class="sunshine-utm-bulk" data-sunshine-utm-bulk data-presets="<?php echo esc_attr( wp_json_encode( $preset_data ) ); ?>" novalidate>

	<?php Sunshine_UTM_Admin::panel_open( 'bulk-method', __( 'How do you want to start?', 'sunshine-utm-builder' ) ); ?>
		<fieldset class="sunshine-utm-methods">
			<legend class="screen-reader-text"><?php esc_html_e( 'How do you want to start?', 'sunshine-utm-builder' ); ?></legend>
			<div class="wps-choice-cards">
				<?php foreach ( $methods as $key => $method ) : ?>
					<label class="wps-choice-card">
						<input type="radio" name="method" value="<?php echo esc_attr( $key ); ?>" <?php checked( 'urls', $key ); ?>>
						<span class="wps-choice-card__name"><?php echo esc_html( $method['name'] ); ?></span>
						<span class="wps-choice-card__blurb"><?php echo esc_html( $method['blurb'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php Sunshine_UTM_Admin::panel_close(); ?>

	<?php Sunshine_UTM_Admin::panel_open( 'bulk-links', __( 'Your links', 'sunshine-utm-builder' ) ); ?>

		<div data-sunshine-utm-for="urls social variations">
			<?php Sunshine_UTM_Admin::option_row_open( __( 'Links', 'sunshine-utm-builder' ), 'sunshine-utm-urls' ); ?>
				<textarea id="sunshine-utm-urls" name="urls" rows="6" class="large-text code" spellcheck="false" placeholder="https://example.com/sale/&#10;https://example.com/blog/new-post/"></textarea>
			<?php Sunshine_UTM_Admin::option_row_close( __( 'One per line. Any UTM tags already on a link are replaced. Its other parameters are kept.', 'sunshine-utm-builder' ) ); ?>
		</div>

		<div data-sunshine-utm-for="csv" hidden>
			<?php Sunshine_UTM_Admin::option_row_open( __( 'CSV file', 'sunshine-utm-builder' ), 'sunshine-utm-csv-file' ); ?>
				<input type="file" id="sunshine-utm-csv-file" accept=".csv,.txt,text/csv,text/plain" data-sunshine-utm-csv-file>
			<?php Sunshine_UTM_Admin::option_row_close( __( 'The file is read in your browser and placed in the box below, so you can check it first.', 'sunshine-utm-builder' ) ); ?>

			<?php Sunshine_UTM_Admin::option_row_open( __( 'CSV', 'sunshine-utm-builder' ), 'sunshine-utm-csv' ); ?>
				<textarea id="sunshine-utm-csv" name="csv" rows="8" class="large-text code" spellcheck="false" placeholder="url,source,medium,campaign&#10;https://example.com/sale/,newsletter,email,spring_sale"></textarea>
			<?php
			Sunshine_UTM_Admin::option_row_close(
				__( 'The first row names the columns. You need a url column. Add any of source, medium, campaign, term and content (or utm_source and so on). Any other column, like ref, is added to that row\'s link as its own parameter.', 'sunshine-utm-builder' )
			);
			?>
		</div>

		<div data-sunshine-utm-for="variations" hidden>
			<?php Sunshine_UTM_Admin::option_row_open( __( 'Values to combine', 'sunshine-utm-builder' ) ); ?>
				<div class="sunshine-utm-variations">
					<?php foreach ( $fields as $key => $field ) : ?>
						<div class="sunshine-utm-variations__field">
							<label for="sunshine-utm-variation-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?> <code><?php echo esc_html( $key ); ?></code></label>
							<textarea id="sunshine-utm-variation-<?php echo esc_attr( $key ); ?>" name="variations[<?php echo esc_attr( $key ); ?>]" rows="4" spellcheck="false"></textarea>
						</div>
					<?php endforeach; ?>
				</div>
			<?php Sunshine_UTM_Admin::option_row_close( __( 'One value per line. Leave a box empty to use the tag below instead. Two sources and three contents makes six links for each link above.', 'sunshine-utm-builder' ) ); ?>
		</div>

		<div data-sunshine-utm-for="social" hidden>
			<?php Sunshine_UTM_Admin::option_row_open( __( 'Networks', 'sunshine-utm-builder' ) ); ?>
				<fieldset class="sunshine-utm-networks">
					<legend class="screen-reader-text"><?php esc_html_e( 'Networks', 'sunshine-utm-builder' ); ?></legend>
					<?php foreach ( $networks as $network_id => $network ) : ?>
						<label class="sunshine-utm-networks__item">
							<input type="checkbox" name="networks[]" value="<?php echo esc_attr( $network_id ); ?>">
							<?php echo esc_html( $network['name'] ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php Sunshine_UTM_Admin::option_row_close( __( 'Each network becomes the source, and the medium is social.', 'sunshine-utm-builder' ) ); ?>
		</div>

	<?php Sunshine_UTM_Admin::panel_close(); ?>

	<?php Sunshine_UTM_Admin::panel_open( 'bulk-tags', __( 'Tags', 'sunshine-utm-builder' ) ); ?>

		<p class="sunshine-utm-method-note" data-sunshine-utm-for="variations" hidden><?php esc_html_e( 'These go on every link. A tag with values listed above uses those values instead.', 'sunshine-utm-builder' ); ?></p>
		<p class="sunshine-utm-method-note" data-sunshine-utm-for="csv" hidden><?php esc_html_e( 'These fill in any tag your CSV leaves blank or doesn\'t have a column for.', 'sunshine-utm-builder' ); ?></p>

		<?php if ( $presets ) : ?>
			<?php Sunshine_UTM_Admin::option_row_open( __( 'Preset', 'sunshine-utm-builder' ), 'sunshine-utm-bulk-preset' ); ?>
				<select id="sunshine-utm-bulk-preset" data-sunshine-utm-preset>
					<option value=""><?php esc_html_e( 'No preset', 'sunshine-utm-builder' ); ?></option>
					<?php foreach ( $presets as $preset ) : ?>
						<option value="<?php echo esc_attr( $preset['id'] ); ?>"><?php echo esc_html( $preset['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php Sunshine_UTM_Admin::option_row_close( __( 'Fills in the tags below. You can still change them.', 'sunshine-utm-builder' ) ); ?>
		<?php endif; ?>

		<?php foreach ( $fields as $key => $field ) : ?>
			<div<?php echo in_array( $key, array( 'utm_source', 'utm_medium' ), true ) ? ' data-sunshine-utm-for="urls variations csv"' : ''; ?>>
				<?php Sunshine_UTM_Admin::option_row_open( $field['label'], 'sunshine-utm-tag-' . $key ); ?>
					<input type="text" id="sunshine-utm-tag-<?php echo esc_attr( $key ); ?>" name="tags[<?php echo esc_attr( $key ); ?>]" class="regular-text" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( Sunshine_UTM_URL_Builder::placeholder( $field ) ); ?>">
				<?php Sunshine_UTM_Admin::option_row_close( '<code>' . esc_html( $key ) . '</code> ' . esc_html( $field['help'] ) ); ?>
			</div>
		<?php endforeach; ?>

		<?php Sunshine_UTM_Admin::option_row_open( __( 'Other parameters', 'sunshine-utm-builder' ), 'sunshine-utm-extra' ); ?>
			<textarea id="sunshine-utm-extra" name="extra" rows="3" class="large-text code" spellcheck="false" placeholder="ref=partner"></textarea>
		<?php Sunshine_UTM_Admin::option_row_close( __( 'One per line, like ref=partner. Added to every link exactly as typed.', 'sunshine-utm-builder' ) ); ?>

		<details class="sunshine-utm-tokens">
			<summary><?php esc_html_e( 'Tokens you can use in any tag', 'sunshine-utm-builder' ); ?></summary>
			<ul>
				<?php foreach ( Sunshine_UTM_Presets::tokens() as $token => $what ) : ?>
					<li><code><?php echo esc_html( $token ); ?></code> <?php echo esc_html( $what ); ?></li>
				<?php endforeach; ?>
			</ul>
		</details>

	<?php Sunshine_UTM_Admin::panel_close(); ?>

	<?php Sunshine_UTM_Admin::panel_open( 'bulk-save', __( 'Save', 'sunshine-utm-builder' ) ); ?>

		<?php Sunshine_UTM_Admin::option_row_open( __( 'Batch name', 'sunshine-utm-builder' ), 'sunshine-utm-batch-name' ); ?>
			<input type="text" id="sunshine-utm-batch-name" name="name" class="regular-text" maxlength="191" placeholder="<?php esc_attr_e( 'e.g. Spring sale emails', 'sunshine-utm-builder' ); ?>">
		<?php Sunshine_UTM_Admin::option_row_close( __( 'So you can find these links again under Saved Batches.', 'sunshine-utm-builder' ) ); ?>

		<?php Sunshine_UTM_Admin::option_row_open( __( 'Notes', 'sunshine-utm-builder' ), 'sunshine-utm-batch-notes' ); ?>
			<textarea id="sunshine-utm-batch-notes" name="notes" rows="2" class="large-text"></textarea>
		<?php Sunshine_UTM_Admin::option_row_close( __( 'Optional. Where the links are going, who asked for them, anything you\'ll want to know later.', 'sunshine-utm-builder' ) ); ?>

		<?php if ( $shorteners ) : ?>
			<?php Sunshine_UTM_Admin::option_row_open( __( 'Short links', 'sunshine-utm-builder' ) ); ?>
				<fieldset class="sunshine-utm-checks sunshine-utm-shorteners">
					<legend class="screen-reader-text"><?php esc_html_e( 'Short links', 'sunshine-utm-builder' ); ?></legend>
					<label>
						<input type="radio" name="shortener" value="" checked>
						<?php esc_html_e( 'Don\'t shorten', 'sunshine-utm-builder' ); ?>
					</label>
					<?php foreach ( $shorteners as $service => $label ) : ?>
						<label>
							<input type="radio" name="shortener" value="<?php echo esc_attr( $service ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php
			$outside = array_intersect_key( $shorteners, array(
'bitly'     => 1,
'rebrandly' => 1
) );
			Sunshine_UTM_Admin::option_row_close(
				$outside
					? sprintf(
						/* translators: 1: Bitly and/or Rebrandly, 2: the most links they can shorten at once. */
						__( '%1$s make one request per link and count toward your plan, so they work on batches of up to %2$s links. Short links on your own site work on any batch.', 'sunshine-utm-builder' ),
						esc_html( implode( ' ' . __( 'and', 'sunshine-utm-builder' ) . ' ', $outside ) ),
						number_format_i18n( Sunshine_UTM_Bulk::SHORTEN_MAX )
					)
					: ''
			);
			?>
		<?php endif; ?>

		<?php if ( $plugin->get_setting( 'check_urls' ) ) : ?>
			<p class="sunshine-utm-method-note">
				<?php
				printf(
					/* translators: %s: the most pages that can be checked at once. */
					esc_html__( 'Each page is checked before the batch is saved, for batches going to up to %s different pages. Links to pages that don\'t load are marked.', 'sunshine-utm-builder' ),
					esc_html( number_format_i18n( Sunshine_UTM_Bulk::CHECK_MAX ) )
				);
				?>
			</p>
		<?php endif; ?>

	<?php Sunshine_UTM_Admin::panel_close(); ?>

	<section class="wps-panel sunshine-utm-preview" aria-labelledby="sunshine-utm-preview-title">
		<div class="wps-panel__head">
			<h2 class="wps-panel__title" id="sunshine-utm-preview-title"><?php esc_html_e( 'Preview', 'sunshine-utm-builder' ); ?></h2>
			<span class="sunshine-utm-preview__count" data-sunshine-utm-count aria-live="polite"></span>
		</div>
		<div class="wps-panel__body">
			<div data-sunshine-utm-preview>
				<p class="sunshine-utm-empty"><?php esc_html_e( 'Add your links above and a preview of the first 20 shows here.', 'sunshine-utm-builder' ); ?></p>
			</div>
		</div>
	</section>

	<div class="wps-save-bar">
		<button type="submit" class="button button-primary" data-sunshine-utm-create disabled><?php esc_html_e( 'Create links', 'sunshine-utm-builder' ); ?></button>
		<span class="wps-save-bar__time" data-sunshine-utm-create-status role="status" aria-live="polite"></span>
	</div>
</form>
