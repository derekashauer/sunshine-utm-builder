<?php
/**
 * Link Builder tab: one link at a time. Opened from the admin bar or a post's "UTM link", the page
 * comes in as ?url=.
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- prefill only, escaped in the form.
$prefill = isset( $_GET['url'] ) ? trim( (string) wp_unslash( $_GET['url'] ) ) : '';
if ( ! Sunshine_UTM_URL_Builder::is_valid_url( $prefill ) ) {
	$prefill = home_url( '/' );
}
?>
<div class="wps-layout">
	<div class="wps-layout__main">
		<?php
		Sunshine_UTM_Admin::panel_open(
			'builder',
			__( 'Build a link', 'sunshine-utm-builder' ),
			array( 'intro' => __( 'Fill in the tags and your link updates as you type. Copy it into your email, ad or post, and the visits it brings show up in Google Analytics under these names.', 'sunshine-utm-builder' ) )
		);
		$builder_html = $plugin->render_builder(
			array(
				'url' => $prefill,
			)
		);
		echo $builder_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped in includes/views/builder.php.
		Sunshine_UTM_Admin::panel_close();
		?>
	</div>
	<div class="wps-layout__side">
		<div class="wps-card">
			<p class="wps-card__eyebrow"><?php esc_html_e( 'Making many links?', 'sunshine-utm-builder' ); ?></p>
			<p><?php esc_html_e( 'The Bulk Builder tags a whole list of links at once, makes a link for every social network, or reads a CSV. Batches are saved so you can download them later.', 'sunshine-utm-builder' ); ?></p>
			<p><a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'bulk' ) ) ); ?>" class="button"><?php esc_html_e( 'Open the Bulk Builder', 'sunshine-utm-builder' ); ?></a></p>
		</div>
		<?php include SUNSHINE_UTM_DIR . 'includes/admin/views/sidebar.php'; ?>
	</div>
</div>
