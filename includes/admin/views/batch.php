<?php
/**
 * One saved batch: its notes, every link with copy buttons and a note of its own, the CSV download
 * and delete.
 *
 * Variables: $batch, $plugin, $admin.
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- included inside a method, so these variables are local to it.

$links  = Sunshine_UTM_Batches::links( (int) $batch['id'] );
$fields = Sunshine_UTM_URL_Builder::fields();
$author = $batch['created_by'] ? get_userdata( (int) $batch['created_by'] ) : false;
$broken = count( $links ) - count( array_filter( wp_list_pluck( $links, 'url_ok' ) ) );
$clicks = Sunshine_UTM_Short_Links::clicks_for( wp_list_pluck( $links, 'short_url' ) );
?>
<p class="sunshine-utm-back">
	<a href="<?php echo esc_url( Sunshine_UTM_Admin::url( array( 'tab' => 'batches' ) ) ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><?php esc_html_e( 'All batches', 'sunshine-utm-builder' ); ?></a>
</p>

<div class="sunshine-utm-batch-head">
	<div>
		<h2 class="sunshine-utm-batch-head__title"><?php echo esc_html( $batch['name'] ); ?></h2>
		<p class="sunshine-utm-batch-head__meta">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of links. */
					_n( '%s link', '%s links', count( $links ), 'sunshine-utm-builder' ),
					number_format_i18n( count( $links ) )
				)
			);
			echo ' &middot; ' . esc_html( Sunshine_UTM_Admin::date( $batch['created_at'] ) );
			if ( $author ) {
				/* translators: %s: user's display name. */
				echo ' &middot; ' . esc_html( sprintf( __( 'by %s', 'sunshine-utm-builder' ), $author->display_name ) );
			}
			?>
		</p>
	</div>
	<div class="sunshine-utm-batch-head__actions">
		<button type="button" class="button button-primary" data-sunshine-utm-copy-all><?php esc_html_e( 'Copy all links', 'sunshine-utm-builder' ); ?></button>
		<a href="<?php echo esc_url( Sunshine_UTM_Admin::export_url( $batch['id'] ) ); ?>" class="button"><?php esc_html_e( 'Download CSV', 'sunshine-utm-builder' ); ?></a>
		<?php Sunshine_UTM_Admin::form_open( 'delete_batch', 'sunshine-utm-inline-form' ); ?>
			<input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch['id'] ); ?>">
			<button type="submit" class="button-link button-link-delete" data-sunshine-utm-confirm="<?php esc_attr_e( 'Delete this batch and all its links? Links you\'ve already shared keep working, but they won\'t be listed here anymore.', 'sunshine-utm-builder' ); ?>"><?php esc_html_e( 'Delete batch', 'sunshine-utm-builder' ); ?></button>
		</form>
	</div>
</div>
<p class="screen-reader-text" role="status" aria-live="polite" data-sunshine-utm-announce></p>

<?php if ( $broken ) : ?>
	<div class="sunshine-utm-alert">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of links. */
					_n( '%s link goes to a page that didn\'t load when the batch was made. It\'s marked below.', '%s links go to pages that didn\'t load when the batch was made. They\'re marked below.', $broken, 'sunshine-utm-builder' ),
					number_format_i18n( $broken )
				)
			);
			?>
		</p>
	</div>
<?php endif; ?>

<?php Sunshine_UTM_Admin::panel_open( 'batch-notes', __( 'Notes', 'sunshine-utm-builder' ) ); ?>
	<?php Sunshine_UTM_Admin::form_open( 'save_batch_notes', 'sunshine-utm-notes-form' ); ?>
		<input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch['id'] ); ?>">
		<label for="sunshine-utm-batch-notes" class="screen-reader-text"><?php esc_html_e( 'Notes', 'sunshine-utm-builder' ); ?></label>
		<textarea id="sunshine-utm-batch-notes" name="notes" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Where these links are used, who asked for them...', 'sunshine-utm-builder' ); ?>"><?php echo esc_textarea( (string) $batch['notes'] ); ?></textarea>
		<p><button type="submit" class="button"><?php esc_html_e( 'Save notes', 'sunshine-utm-builder' ); ?></button></p>
	</form>
<?php Sunshine_UTM_Admin::panel_close(); ?>

<section class="wps-panel">
	<div class="wps-panel__head">
		<h2 class="wps-panel__title"><?php esc_html_e( 'Links', 'sunshine-utm-builder' ); ?></h2>
		<?php if ( count( $links ) > 5 ) : ?>
			<label for="sunshine-utm-link-filter" class="screen-reader-text"><?php esc_html_e( 'Filter links', 'sunshine-utm-builder' ); ?></label>
			<input type="search" id="sunshine-utm-link-filter" class="sunshine-utm-filter" placeholder="<?php esc_attr_e( 'Filter links', 'sunshine-utm-builder' ); ?>" data-sunshine-utm-filter>
		<?php endif; ?>
	</div>
	<table class="sunshine-utm-table sunshine-utm-links">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Page and tags', 'sunshine-utm-builder' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Link to share', 'sunshine-utm-builder' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Note', 'sunshine-utm-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $links as $batch_link ) : ?>
				<tr data-sunshine-utm-row data-sunshine-utm-qr-after data-share="<?php echo esc_attr( Sunshine_UTM_Batches::share_url( $batch_link ) ); ?>">
					<td class="sunshine-utm-links__page">
						<a href="<?php echo esc_url( $batch_link['url'] ); ?>" target="_blank" rel="noopener" class="sunshine-utm-links__url"><?php echo esc_html( $batch_link['url'] ); ?><?php Sunshine_UTM_Admin::new_tab_note(); ?></a>
						<?php if ( ! $batch_link['url_ok'] ) : ?>
							<span class="sunshine-utm-links__broken"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php echo esc_html( $batch_link['url_status'] ); ?></span>
						<?php endif; ?>
						<dl class="sunshine-utm-tags">
							<?php foreach ( $fields as $key => $field ) : ?>
								<?php if ( '' !== $batch_link[ $key ] ) : ?>
									<div><dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $batch_link[ $key ] ); ?></dd></div>
								<?php endif; ?>
							<?php endforeach; ?>
							<?php foreach ( $batch_link['extra_params'] as $name => $value ) : ?>
								<div><dt><?php echo esc_html( $name ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
							<?php endforeach; ?>
						</dl>
					</td>
					<td class="sunshine-utm-links__share">
						<?php if ( $batch_link['short_url'] ) : ?>
							<div class="sunshine-utm-copy-row">
								<input type="text" readonly value="<?php echo esc_attr( $batch_link['short_url'] ); ?>" aria-label="<?php esc_attr_e( 'Short link', 'sunshine-utm-builder' ); ?>">
								<button type="button" class="button" data-sunshine-utm-copy><?php esc_html_e( 'Copy', 'sunshine-utm-builder' ); ?></button>
							</div>
							<?php if ( isset( $clicks[ $batch_link['short_url'] ] ) ) : ?>
								<?php /* translators: %s: number of clicks. */ ?>
								<span class="sunshine-utm-links__clicks"><?php echo esc_html( sprintf( _n( '%s click', '%s clicks', $clicks[ $batch_link['short_url'] ], 'sunshine-utm-builder' ), number_format_i18n( $clicks[ $batch_link['short_url'] ] ) ) ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
						<div class="sunshine-utm-copy-row<?php echo $batch_link['short_url'] ? ' sunshine-utm-copy-row--long' : ''; ?>">
							<input type="text" readonly value="<?php echo esc_attr( $batch_link['tagged_url'] ); ?>" aria-label="<?php esc_attr_e( 'Full link', 'sunshine-utm-builder' ); ?>">
							<button type="button" class="button" data-sunshine-utm-copy><?php esc_html_e( 'Copy', 'sunshine-utm-builder' ); ?></button>
						</div>
						<button type="button" class="button-link sunshine-utm-links__qr" data-sunshine-utm-qr="<?php echo esc_attr( Sunshine_UTM_Batches::share_url( $batch_link ) ); ?>"><?php esc_html_e( 'QR code', 'sunshine-utm-builder' ); ?></button>
					</td>
					<td class="sunshine-utm-links__note">
						<label for="sunshine-utm-note-<?php echo esc_attr( $batch_link['id'] ); ?>" class="screen-reader-text"><?php esc_html_e( 'Note for this link', 'sunshine-utm-builder' ); ?></label>
						<textarea id="sunshine-utm-note-<?php echo esc_attr( $batch_link['id'] ); ?>" rows="2" data-sunshine-utm-note="<?php echo esc_attr( $batch_link['id'] ); ?>" placeholder="<?php esc_attr_e( 'Add a note', 'sunshine-utm-builder' ); ?>"><?php echo esc_textarea( (string) $batch_link['notes'] ); ?></textarea>
						<span class="sunshine-utm-links__saved" data-sunshine-utm-note-status aria-live="polite"></span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="sunshine-utm-empty" data-sunshine-utm-no-match hidden><?php esc_html_e( 'No links match.', 'sunshine-utm-builder' ); ?></p>
</section>
