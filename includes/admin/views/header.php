<?php
/**
 * Header: logo, help links and tabs. Same structure and classes as Confetti and Address Autocomplete.
 *
 * Variables: $tabs, $tab.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$header_links = array(
	'documentation' => array(
		'url'   => 'https://wpsunshine.com/support/?utm_source=plugin&utm_medium=header&utm_campaign=utm-builder',
		'label' => __( 'Documentation', 'sunshine-utm-builder' ),
	),
	'review'        => array(
		'url'   => 'https://wordpress.org/support/plugin/sunshine-utm-builder/reviews/#new-post',
		'label' => __( 'Write a Review', 'sunshine-utm-builder' ),
	),
	'feedback'      => array(
		'url'   => 'https://wpsunshine.com/feedback/?utm_source=plugin&utm_medium=header&utm_campaign=utm-builder',
		'label' => __( 'Feedback', 'sunshine-utm-builder' ),
	),
);
?>
<div class="wps-header">
	<a href="<?php echo esc_url( Sunshine_UTM_Admin::url() ); ?>" class="wps-logo">
		<img src="<?php echo esc_url( SUNSHINE_UTM_URL . 'assets/images/logo.svg' ); ?>" width="265" height="40" alt="<?php esc_attr_e( 'Sunshine UTM Builder', 'sunshine-utm-builder' ); ?>">
	</a>

	<div id="wps-header-links">
		<?php foreach ( $header_links as $key => $header_link ) : ?>
			<a href="<?php echo esc_url( $header_link['url'] ); ?>" target="_blank" rel="noopener" class="wps-header-link--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $header_link['label'] ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'sunshine-utm-builder' ); ?></span></a>
		<?php endforeach; ?>
	</div>

	<nav class="wps-options-menu" aria-label="<?php esc_attr_e( 'UTM Builder sections', 'sunshine-utm-builder' ); ?>">
		<ul>
			<?php foreach ( $tabs as $key => $label ) : ?>
				<li<?php echo $tab === $key ? ' class="wps-options-active"' : ''; ?>>
					<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => $key ) ) ); ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
</div>
