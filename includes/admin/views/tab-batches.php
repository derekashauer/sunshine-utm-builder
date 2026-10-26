<?php
/**
 * Saved Batches tab: the list, or one batch when ?batch= is set.
 *
 * Variables: $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$batch_id = isset( $_GET['batch'] ) ? absint( $_GET['batch'] ) : 0;
if ( $batch_id ) {
	$batch = Sunshine_UTM_Batches::get( $batch_id );
	if ( $batch ) {
		include SUNSHINE_UTM_DIR . 'includes/admin/views/batch.php';
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'That batch no longer exists.', 'sunshine-utm-builder' ) . '</p></div>';
}

$list_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$list_paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$list_per_page = 20;
$result        = Sunshine_UTM_Batches::query(
	array(
		'search'   => $list_search,
		'page'     => $list_paged,
		'per_page' => $list_per_page,
	)
);
$methods       = array(
	'urls'       => __( 'List of links', 'sunshine-utm-builder' ),
	'social'     => __( 'Social networks', 'sunshine-utm-builder' ),
	'variations' => __( 'Every combination', 'sunshine-utm-builder' ),
	'csv'        => __( 'CSV', 'sunshine-utm-builder' ),
	'api'        => __( 'REST API', 'sunshine-utm-builder' ),
);
?>
<div class="sunshine-utm-toolbar">
	<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" class="sunshine-utm-search" role="search">
		<input type="hidden" name="page" value="<?php echo esc_attr( Sunshine_UTM_Admin::SLUG ); ?>">
		<input type="hidden" name="tab" value="batches">
		<label for="sunshine-utm-batch-search" class="screen-reader-text"><?php esc_html_e( 'Search batches', 'sunshine-utm-builder' ); ?></label>
		<input type="search" id="sunshine-utm-batch-search" name="s" value="<?php echo esc_attr( $list_search ); ?>" placeholder="<?php esc_attr_e( 'Search names and notes', 'sunshine-utm-builder' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'sunshine-utm-builder' ); ?></button>
		<?php if ( '' !== $list_search ) : ?>
			<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'batches' ) ) ); ?>"><?php esc_html_e( 'Clear search', 'sunshine-utm-builder' ); ?></a>
		<?php endif; ?>
	</form>
	<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'bulk' ) ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create a batch', 'sunshine-utm-builder' ); ?></a>
</div>

<section class="wps-panel">
	<?php if ( ! $result['items'] ) : ?>
		<div class="wps-panel__body">
			<p class="sunshine-utm-empty">
				<?php
				if ( '' !== $list_search ) {
					esc_html_e( 'No batches match your search.', 'sunshine-utm-builder' );
				} else {
					esc_html_e( 'No batches yet. Links you make in the Bulk Builder are saved here.', 'sunshine-utm-builder' );
					echo ' ' . Sunshine_UTM_Admin::doc_link( 'utm-builder-saved-batches' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in doc_link().
				}
				?>
			</p>
		</div>
	<?php else : ?>
		<table class="sunshine-utm-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'sunshine-utm-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Links', 'sunshine-utm-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Made from', 'sunshine-utm-builder' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'sunshine-utm-builder' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'sunshine-utm-builder' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['items'] as $item ) : ?>
					<?php $view_url = Sunshine_UTM_Admin::url( array(
'tab'   => 'batches',
'batch' => $item['id']
) ); ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $view_url ); ?>" class="sunshine-utm-table__name"><?php echo esc_html( $item['name'] ); ?></a>
							<?php if ( $item['notes'] ) : ?>
								<span class="sunshine-utm-table__note"><?php echo esc_html( wp_trim_words( $item['notes'], 16 ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( $item['link_count'] ) ); ?></td>
						<td><?php echo esc_html( isset( $methods[ $item['method'] ] ) ? $methods[ $item['method'] ] : '' ); ?></td>
						<td>
							<?php echo esc_html( Sunshine_UTM_Admin::date( $item['created_at'] ) ); ?>
							<?php
							$author = $item['created_by'] ? get_userdata( (int) $item['created_by'] ) : false;
							if ( $author ) {
								/* translators: %s: user's display name. */
								echo '<span class="sunshine-utm-table__note">' . esc_html( sprintf( __( 'by %s', 'sunshine-utm-builder' ), $author->display_name ) ) . '</span>';
							}
							?>
						</td>
						<td class="sunshine-utm-table__actions">
							<a href="<?php echo esc_url( $view_url ); ?>" class="button"><?php esc_html_e( 'View', 'sunshine-utm-builder' ); ?></a>
							<a href="<?php echo esc_url( Sunshine_UTM_Admin::export_url( $item['id'] ) ); ?>" class="button"><?php esc_html_e( 'Download CSV', 'sunshine-utm-builder' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>

<?php
$list_pages = (int) ceil( $result['total'] / $list_per_page );
if ( $list_pages > 1 ) {
	echo '<nav class="sunshine-utm-pagination" aria-label="' . esc_attr__( 'Batch pages', 'sunshine-utm-builder' ) . '">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', Sunshine_UTM_Admin::url( array_filter( array(
'tab' => 'batches',
's'   => rawurlencode( $list_search )
) ) ) ),
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
