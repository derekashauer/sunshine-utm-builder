<?php
/**
 * Short Links tab: clicks by campaign, then every short link on this site with its clicks, newest
 * first. With ?code= it shows one short link instead (short-link.php). With ?campaign= the list
 * only shows that campaign's links (an empty value means links without a campaign).
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$link_code = isset( $_GET['code'] ) ? sanitize_title( wp_unslash( $_GET['code'] ) ) : '';
if ( '' !== $link_code ) {
	$short_link = Sunshine_UTM_Short_Links::get( $link_code );
	if ( $short_link ) {
		include SUNSHINE_UTM_DIR . 'includes/admin/views/short-link.php';
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'That short link no longer exists.', 'sunshine-utm-builder' ) . '</p></div>';
}
$list_search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$list_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$list_campaign = isset( $_GET['campaign'] ) ? sanitize_text_field( wp_unslash( $_GET['campaign'] ) ) : null;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$list_per_page = 20;
$result        = Sunshine_UTM_Short_Links::query(
	array(
		'search'   => $list_search,
		'campaign' => $list_campaign,
		'page'     => $list_paged,
		'per_page' => $list_per_page,
	)
);
$conversions   = Sunshine_UTM_Conversion_Bridge::active();
$campaigns     = ( '' === $list_search && null === $list_campaign ) ? Sunshine_UTM_Short_Links::campaigns( 10 ) : array();
$campaign_name = function ( $campaign ) {
	return '' === (string) $campaign ? __( '(no campaign)', 'sunshine-utm-builder' ) : $campaign;
};
?>
<?php if ( count( $campaigns ) > 1 || ( $campaigns && '' !== $campaigns[0]['campaign'] ) ) : ?>
	<section class="wps-panel">
		<div class="wps-panel__head">
			<h2 class="wps-panel__title"><?php esc_html_e( 'Clicks by campaign', 'sunshine-utm-builder' ); ?></h2>
		</div>
		<table class="sunshine-utm-table sunshine-utm-short-links">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Campaign', 'sunshine-utm-builder' ); ?></th>
					<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Short links', 'sunshine-utm-builder' ); ?></th>
					<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Clicks', 'sunshine-utm-builder' ); ?></th>
					<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Last 30 days', 'sunshine-utm-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $campaigns as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array(
'tab'      => 'links',
'campaign' => rawurlencode( $row['campaign'] )
) ) ); ?>" class="sunshine-utm-table__name"><?php echo esc_html( $campaign_name( $row['campaign'] ) ); ?></a></td>
						<td class="sunshine-utm-table__num"><?php echo esc_html( number_format_i18n( (int) $row['links'] ) ); ?></td>
						<td class="sunshine-utm-table__num"><?php echo esc_html( number_format_i18n( (int) $row['clicks'] ) ); ?></td>
						<td class="sunshine-utm-table__num"><?php echo esc_html( number_format_i18n( (int) $row['recent'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>
<?php endif; ?>

<div class="sunshine-utm-toolbar">
	<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" class="sunshine-utm-search" role="search">
		<input type="hidden" name="page" value="<?php echo esc_attr( Sunshine_UTM_Admin::SLUG ); ?>">
		<input type="hidden" name="tab" value="links">
		<label for="sunshine-utm-link-search" class="screen-reader-text"><?php esc_html_e( 'Search short links', 'sunshine-utm-builder' ); ?></label>
		<input type="search" id="sunshine-utm-link-search" name="s" value="<?php echo esc_attr( $list_search ); ?>" placeholder="<?php esc_attr_e( 'Search links and tags', 'sunshine-utm-builder' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'sunshine-utm-builder' ); ?></button>
		<?php if ( '' !== $list_search ) : ?>
			<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'links' ) ) ); ?>"><?php esc_html_e( 'Clear search', 'sunshine-utm-builder' ); ?></a>
		<?php endif; ?>
	</form>
</div>

<?php if ( null !== $list_campaign ) : ?>
	<p class="sunshine-utm-filter-note">
		<?php
		/* translators: %s: campaign name. */
		echo esc_html( sprintf( __( 'Showing the campaign: %s', 'sunshine-utm-builder' ), $campaign_name( $list_campaign ) ) );
		?>
		<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'links' ) ) ); ?>"><?php esc_html_e( 'Show all short links', 'sunshine-utm-builder' ); ?></a>
	</p>
<?php endif; ?>

<section class="wps-panel">
	<?php if ( ! $result['items'] ) : ?>
		<div class="wps-panel__body">
			<p class="sunshine-utm-empty">
				<?php
				if ( '' !== $list_search || null !== $list_campaign ) {
					esc_html_e( 'No short links match.', 'sunshine-utm-builder' );
				} else {
					/* translators: %s: this site's domain. */
					echo esc_html( sprintf( __( 'No short links yet. Choose %s under "Shorten with" when you build a link, and its clicks show up here.', 'sunshine-utm-builder' ), Sunshine_UTM_Shortener::label( 'site' ) ) );
					echo ' ' . Sunshine_UTM_Admin::doc_link( 'short-links-on-your-own-domain' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in doc_link().
				}
				?>
			</p>
		</div>
	<?php else : ?>
		<table class="sunshine-utm-table sunshine-utm-short-links">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Short link', 'sunshine-utm-builder' ); ?></th>
					<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Clicks', 'sunshine-utm-builder' ); ?></th>
					<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Last 30 days', 'sunshine-utm-builder' ); ?></th>
					<?php if ( $conversions ) : ?>
						<th scope="col" class="sunshine-utm-table__num"><?php esc_html_e( 'Conversions', 'sunshine-utm-builder' ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'Last click', 'sunshine-utm-builder' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'sunshine-utm-builder' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $item ) : ?>
					<?php
					$short      = Sunshine_UTM_Short_Links::url( $item['code'] );
					$short_bare = preg_replace( '#^https?://#', '', $short );
					$view_url   = Sunshine_UTM_Admin::url(
						array(
							'tab'  => 'links',
							'code' => $item['code'],
						)
					);
					?>
					<tr data-sunshine-utm-qr-after>
						<td>
							<a href="<?php echo esc_url( $view_url ); ?>" class="sunshine-utm-table__name"><?php echo esc_html( $short_bare ); ?></a>
							<span class="sunshine-utm-table__note sunshine-utm-short-links__url"><?php echo esc_html( $item['url'] ); ?></span>
						</td>
						<td class="sunshine-utm-table__num"><?php echo esc_html( number_format_i18n( (int) $item['clicks'] ) ); ?></td>
						<td class="sunshine-utm-table__num"><?php echo esc_html( number_format_i18n( (int) $item['recent'] ) ); ?></td>
						<?php if ( $conversions ) : ?>
							<?php $converted = Sunshine_UTM_Conversion_Bridge::conversions( $item ); ?>
							<td class="sunshine-utm-table__num"><?php echo null === $converted ? '&mdash;' : esc_html( number_format_i18n( $converted ) ); ?></td>
						<?php endif; ?>
						<td><?php echo $item['last_click_at'] ? esc_html( Sunshine_UTM_Admin::date( $item['last_click_at'] ) ) : '&mdash;'; ?></td>
						<td class="sunshine-utm-table__actions">
							<a href="<?php echo esc_url( $view_url ); ?>" class="button"><?php esc_html_e( 'Details', 'sunshine-utm-builder' ); ?><span class="screen-reader-text"> <?php echo esc_html( $short_bare ); ?></span></a>
							<button type="button" class="button" data-sunshine-utm-qr="<?php echo esc_attr( $short ); ?>"><?php esc_html_e( 'QR code', 'sunshine-utm-builder' ); ?><span class="screen-reader-text"> <?php echo esc_html( $short_bare ); ?></span></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
<p class="sunshine-utm-table__note">
	<?php esc_html_e( 'Clicks from bots and link previews (like the one Facebook makes when you post a link) aren\'t counted.', 'sunshine-utm-builder' ); ?>
	<?php echo Sunshine_UTM_Admin::doc_link( 'short-link-clicks-vs-google-analytics', __( 'Why clicks don\'t match Google Analytics', 'sunshine-utm-builder' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in doc_link(). ?>
	<?php if ( $conversions ) : ?>
		<?php
		/* translators: %s: Conversion Bridge, or the name it's white labeled with. */
		echo esc_html( sprintf( __( 'Conversions come from %s: sales, sign-ups and other conversions by visitors who arrived with a link\'s tags since it was made.', 'sunshine-utm-builder' ), Sunshine_UTM_Conversion_Bridge::name() ) );
		?>
	<?php endif; ?>
</p>

<?php
$list_pages = (int) ceil( $result['total'] / $list_per_page );
if ( $list_pages > 1 ) {
	$page_args = array_filter( array(
'tab' => 'links',
's'   => rawurlencode( $list_search )
) );
	if ( null !== $list_campaign ) {
		$page_args['campaign'] = rawurlencode( $list_campaign );
	}
	echo '<nav class="sunshine-utm-pagination" aria-label="' . esc_attr__( 'Short link pages', 'sunshine-utm-builder' ) . '">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', Sunshine_UTM_Admin::url( $page_args ) ),
				'format'    => '',
				'current'   => $list_paged,
				'total'     => $list_pages,
				'prev_text' => __( 'Previous', 'sunshine-utm-builder' ),
				'next_text' => __( 'Next', 'sunshine-utm-builder' ),
			)
		)
	);
	echo '</nav>';
}
