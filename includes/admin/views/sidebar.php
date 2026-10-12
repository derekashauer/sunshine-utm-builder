<?php
/**
 * Sidebar cards: Conversion Bridge, then the other WP Sunshine plugins. Same cards as Confetti and
 * Address Autocomplete. Conversion Bridge's card hides when it's already active.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$sunshine_plugins = array(
	array(
		'name'  => 'Confetti',
		'blurb' => __( 'Celebrate form submissions, purchases and more with confetti.', 'sunshine-utm-builder' ),
		'url'   => 'https://wpsunshine.com/plugins/confetti/',
	),
	array(
		'name'  => 'Address Autocomplete',
		'blurb' => __( 'Address suggestions as people type, on checkout and any other form.', 'sunshine-utm-builder' ),
		'url'   => 'https://wpsunshine.com/plugins/address-autocomplete/',
	),
);
?>
<?php if ( ! function_exists( 'CB' ) ) : ?>
	<div class="wps-card wps-card--upgrade">
		<p class="wps-card__eyebrow"><?php esc_html_e( 'Conversion Bridge', 'sunshine-utm-builder' ); ?></p>
		<h3><?php esc_html_e( 'See which links lead to sales and form entries', 'sunshine-utm-builder' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Records the UTM campaign behind each conversion', 'sunshine-utm-builder' ); ?></li>
			<li><?php esc_html_e( 'Sends conversions to Google Analytics, Google Ads, Meta and more', 'sunshine-utm-builder' ); ?></li>
			<li><?php esc_html_e( 'Works with WooCommerce and the popular form plugins, no code', 'sunshine-utm-builder' ); ?></li>
		</ul>
		<p><a href="https://conversionbridgewp.com/?utm_source=plugin&utm_medium=sidebar&utm_campaign=utm-builder" target="_blank" rel="noopener" class="button button-primary"><?php esc_html_e( 'See Conversion Bridge', 'sunshine-utm-builder' ); ?><?php Sunshine_UTM_Admin::new_tab_note(); ?></a></p>
	</div>
<?php endif; ?>

<div class="wps-card wps-card--plugins">
	<p class="wps-card__eyebrow"><?php esc_html_e( 'More from WP Sunshine', 'sunshine-utm-builder' ); ?></p>
	<ul>
		<?php foreach ( $sunshine_plugins as $sunshine_plugin ) : ?>
			<li>
				<a href="<?php echo esc_url( $sunshine_plugin['url'] . '?utm_source=plugin&utm_medium=sidebar&utm_campaign=utm-builder' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sunshine_plugin['name'] ); ?><?php Sunshine_UTM_Admin::new_tab_note(); ?></a>
				<span class="description"><?php echo esc_html( $sunshine_plugin['blurb'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
